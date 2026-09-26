<?php

use App\Models\StatisticsModel;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class PresenceSeriesTest extends CIUnitTestCase
{
    private function rows(array $status_by_date): array
    {
        $rows = [];
        foreach ($status_by_date as $date => $statuses) {
            foreach ((array) $statuses as $status) {
                $rows[] = (object) ['op_date' => $date, 'status' => $status];
            }
        }

        return $rows;
    }

    public function testWeeklySeriesHasOnePointPerWeekAndGapsWithoutReport(): void
    {
        $series = StatisticsModel::build_member_presence_series($this->rows([
            '2026-01-02' => 'present',
            '2026-01-09' => 'absent',
            '2026-01-16' => 'unjustified',
            '2026-01-30' => 'present',
        ]), '2026-01-02', '2026-01-30', 'week');

        $this->assertSame(
            ['2026-01-02', '2026-01-09', '2026-01-16', '2026-01-23', '2026-01-30'],
            array_column($series, 'bucket')
        );
        // Une absence injustifiée compte comme une absence, et une semaine sans
        // rapport reste null (un trou), pas 0.
        $this->assertSame([1, 0, 0, null, 1], array_column($series, 'value'));
    }

    public function testWeeklySeriesStaysBinaryEvenWithSeveralOperationsInTheWeek(): void
    {
        $series = StatisticsModel::build_member_presence_series($this->rows([
            '2026-01-02' => ['absent', 'present'],
            '2026-01-09' => ['absent', 'unjustified'],
        ]), '2026-01-02', '2026-01-15', 'week');

        $this->assertSame([1, 0], array_column($series, 'value'));
        $this->assertSame([2, 2], array_column($series, 'reported'));
    }

    public function testMonthlySeriesIsThePresenceRateOfTheMonth(): void
    {
        $series = StatisticsModel::build_member_presence_series($this->rows([
            '2026-01-09' => 'absent',
            '2026-01-23' => 'present',
            '2026-02-06' => 'absent',
            '2026-02-20' => 'unjustified',
            '2026-04-03' => ['present', 'present', 'present', 'absent'],
        ]), '2026-01-15', '2026-04-10', 'month');

        $this->assertSame(['2026-01-01', '2026-02-01', '2026-03-01', '2026-04-01'], array_column($series, 'bucket'));
        // Un mois n'est pas binaire : c'est présences / opérations rapportées, et
        // un mois sans rapport reste null (un trou), pas 0.
        $this->assertSame([0.5, 0.0, null, 0.75], array_column($series, 'value'));
        $this->assertSame([1, 0, 0, 3], array_column($series, 'present'));
        $this->assertSame([2, 2, 0, 4], array_column($series, 'reported'));
    }

    public function testSeriesWithoutAnyReportIsAllGaps(): void
    {
        $series = StatisticsModel::build_member_presence_series([], '2026-01-02', '2026-01-30', 'week');

        $this->assertCount(5, $series);
        $this->assertSame([null, null, null, null, null], array_column($series, 'value'));
        $this->assertSame(0, array_sum(array_column($series, 'reported')));
    }
}
