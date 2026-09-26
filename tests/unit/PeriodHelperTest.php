<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class PeriodHelperTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper('period');
    }

    public function testParsePeriodDateAcceptsOnlyValidCalendarDates(): void
    {
        $this->assertSame('2026-09-26', parse_period_date('2026-09-26'));
        $this->assertNull(parse_period_date('2026-02-30'));
        $this->assertNull(parse_period_date('26/09/2026'));
        $this->assertNull(parse_period_date(''));
        $this->assertNull(parse_period_date(null));
        // ?start[]=x : un tableau n'est pas une date, ce n'est pas une erreur de type.
        $this->assertNull(parse_period_date(['2026-09-26']));
    }

    public function testParsePeriodGranularityFallsBackToDefault(): void
    {
        $this->assertSame('month', parse_period_granularity('month', ['week', 'month'], 'week'));
        $this->assertSame('week', parse_period_granularity('day', ['week', 'month'], 'week'));
        $this->assertSame('week', parse_period_granularity(null, ['week', 'month'], 'week'));
        $this->assertSame('week', parse_period_granularity(['month'], ['week', 'month'], 'week'));
    }

    public function testMonthBucketsAreCalendarMonthsAcrossYears(): void
    {
        $buckets = build_period_buckets(new DateTime('2025-11-20'), new DateTime('2026-02-03'), 'month');

        $this->assertSame(['2025-11-01', '2025-12-01', '2026-01-01', '2026-02-01'], $buckets);
    }

    public function testWeekBucketsStartAtTheStartDate(): void
    {
        $buckets = build_period_buckets(new DateTime('2026-01-01'), new DateTime('2026-01-20'), 'week');

        $this->assertSame(['2026-01-01', '2026-01-08', '2026-01-15'], $buckets);
    }

    public function testBucketKeyPicksTheContainingBucketAndClampsOutOfRangeDates(): void
    {
        $start = new DateTime('2026-01-01');
        $buckets = build_period_buckets($start, new DateTime('2026-01-20'), 'week');

        $this->assertSame('2026-01-08', period_bucket_key($buckets, $start, new DateTime('2026-01-14'), 'week'));
        $this->assertSame('2026-01-01', period_bucket_key($buckets, $start, new DateTime('2025-12-01'), 'week'));
        $this->assertSame('2026-01-15', period_bucket_key($buckets, $start, new DateTime('2026-06-01'), 'week'));
    }
}
