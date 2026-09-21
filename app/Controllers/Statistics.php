<?php

namespace App\Controllers;

use CodeIgniter\HTTP\Exceptions\RedirectException;

use App\Models\StatisticsModel;

class Statistics extends BaseController
{
    public function __construct()
    {
        if (!session('is_logged_in')) {
            $route = empty(uri_string()) ? '/' : uri_string();
            session()->set('after_login_url', $route);
            throw new RedirectException('login');
            exit;
        }
    }

    private function render_message(string $message): string
    {
        return view('generic/head')
            . view('generic/header')
            . view('404', ['message' => $message])
            . view('generic/footer')
            . view('generic/foot');
    }

    /**
     * Date au format Y-m-d (et calendaire valide), sinon null.
     */
    private function parse_date(?string $date): ?string
    {
        if ($date === null) {
            return null;
        }

        $parsed = \DateTime::createFromFormat('Y-m-d', $date);

        return ($parsed !== false && $parsed->format('Y-m-d') === $date) ? $date : null;
    }

    /**
     * Granularité demandée si elle fait partie des valeurs autorisées pour
     * ce graphique, sinon $default. $default est aussi ce qui s'applique
     * quand aucune granularité n'est fournie (une semaine plutôt qu'un jour :
     * un jour par point est trop fin dès que la période dépasse quelques
     * semaines).
     *
     * @param array<int, string> $allowed
     */
    private function parse_granularity(?string $granularity, array $allowed, string $default): string
    {
        return in_array($granularity, $allowed, true) ? $granularity : $default;
    }

    /**
     * Liste ordonnée des clés de buckets (une par point de courbe, au format
     * Y-m-d) couvrant [start_date, end_date] pour la granularité donnée. Le
     * dernier bucket peut couvrir une plage plus courte si la période ne se
     * découpe pas en un nombre entier de points.
     *
     * "month" avance de mois calendaire en mois calendaire (une clé est le
     * 1er du mois) plutôt que par tranches de 30 jours : les mois n'ont pas
     * tous la même longueur, et des tranches fixes finiraient par dériver
     * des vrais débuts de mois.
     */
    private function build_buckets(\DateTime $start_date, \DateTime $end_date, string $granularity): array
    {
        $buckets = [];

        if ($granularity === 'month') {
            $cursor = new \DateTime($start_date->format('Y-m-01'));
            $end_month = new \DateTime($end_date->format('Y-m-01'));
            while ($cursor <= $end_month) {
                $buckets[] = $cursor->format('Y-m-d');
                $cursor->modify('+1 month');
            }

            return $buckets;
        }

        $bucket_days = $granularity === 'week' ? 7 : 1;
        $cursor = clone $start_date;
        while ($cursor <= $end_date) {
            $buckets[] = $cursor->format('Y-m-d');
            $cursor->modify('+' . $bucket_days . ' days');
        }

        return $buckets;
    }

    /**
     * Clé du bucket (parmi celles retournées par build_buckets pour les
     * mêmes $start_date/$granularity) auquel appartient $row_date.
     *
     * @param array<int, string> $buckets
     */
    private function bucket_key(array $buckets, \DateTime $start_date, \DateTime $row_date, string $granularity): string
    {
        if ($granularity === 'month') {
            $months = ((int) $row_date->format('Y') - (int) $start_date->format('Y')) * 12
                + ((int) $row_date->format('n') - (int) $start_date->format('n'));
            $index = max(0, min($months, count($buckets) - 1));

            return $buckets[$index];
        }

        $bucket_days = $granularity === 'week' ? 7 : 1;
        $days_since_start = (int) $start_date->diff($row_date)->days;
        $index = max(0, min(intdiv($days_since_start, $bucket_days), count($buckets) - 1));

        return $buckets[$index];
    }

    public function index()
    {
        if (!is_team_leader(session('user'))) {
            return $this->render_message("Vous n'avez pas la permission d'accéder à cette page.");
        }

        $today = new \DateTime('today');
        $default_end = $today->format('Y-m-d');
        $default_start = (clone $today)->modify('-29 days')->format('Y-m-d');

        $start = $this->parse_date($_GET['start'] ?? null) ?? $default_start;
        $end = $this->parse_date($_GET['end'] ?? null) ?? $default_end;
        if ($start > $end) {
            [$start, $end] = [$end, $start];
        }

        $granularity = $this->parse_granularity($_GET['granularity'] ?? null, ['day', 'week', 'month'], 'week');
        // Les opérations n'ont lieu qu'une fois par semaine : un intervalle
        // d'un jour n'aurait aucun sens pour le taux de présence, d'où une
        // liste de granularités autorisées plus courte que celle du
        // graphique des arrivées.
        $presence_granularity = $this->parse_granularity($_GET['presence_granularity'] ?? null, ['week', 'month'], 'week');

        // Toute la période, un point par bucket à partir du début de la
        // période, y compris les buckets sans aucune arrivée (comptés à 0) :
        // les lignes du graphique ne doivent pas sauter d'un point à l'autre
        // en fonction des seuls buckets ayant des données.
        $start_date = new \DateTime($start);
        $end_date = new \DateTime($end);

        $buckets = $this->build_buckets($start_date, $end_date, $granularity);

        $series_by_troop = [];
        foreach (StatisticsModel::TROOPS as $troop_id => $troop) {
            $series_by_troop[$troop_id] = array_fill_keys($buckets, 0);
        }
        $series_total = array_fill_keys($buckets, 0);
        $pie_troops = array_fill_keys(array_keys(StatisticsModel::TROOPS), 0);
        $still_here = 0;
        $left = 0;

        $stats_model = model(StatisticsModel::class);
        foreach ($stats_model->get_new_joiners($start, $end) as $row) {
            $bucket_key = $this->bucket_key($buckets, $start_date, new \DateTime($row->join_date), $granularity);

            // Toute arrivée compte dans le total, qu'elle ait ou non gardé
            // une troop reconnue depuis (un départ ne doit pas la faire
            // disparaître du décompte global, seulement de sa ligne de troop).
            $series_total[$bucket_key]++;

            if ($row->troop_id !== null) {
                $series_by_troop[$row->troop_id][$bucket_key]++;
                $pie_troops[$row->troop_id]++;
                $still_here++;
            } else {
                $left++;
            }
        }

        // Taux de présence par troop et au global, à partir des rapports de
        // présence des opérations (une ligne operation_report par membre
        // rapporté). La troop retenue est celle figée sur le rapport, pas
        // l'appartenance actuelle du membre.
        $presence_buckets = $this->build_buckets($start_date, $end_date, $presence_granularity);

        $presence_present = [];
        $presence_reported = [];
        foreach (StatisticsModel::TROOPS as $troop_id => $troop) {
            $presence_present[$troop_id] = array_fill_keys($presence_buckets, 0);
            $presence_reported[$troop_id] = array_fill_keys($presence_buckets, 0);
        }
        $presence_present_total = array_fill_keys($presence_buckets, 0);
        $presence_reported_total = array_fill_keys($presence_buckets, 0);

        foreach ($stats_model->get_operation_presence($start, $end) as $row) {
            $bucket_key = $this->bucket_key($presence_buckets, $start_date, new \DateTime($row->op_date), $presence_granularity);

            $presence_reported_total[$bucket_key]++;
            if ($row->status === 'present') {
                $presence_present_total[$bucket_key]++;
            }

            if ($row->troop_id !== null && isset($presence_reported[$row->troop_id])) {
                $presence_reported[$row->troop_id][$bucket_key]++;
                if ($row->status === 'present') {
                    $presence_present[$row->troop_id][$bucket_key]++;
                }
            }
        }

        // Un taux n'a de sens que s'il existe au moins un rapport sur le
        // bucket : sans quoi un bucket sans opération afficherait 0 % au
        // lieu de simplement laisser un trou dans la courbe.
        $presence_rate_by_troop = [];
        foreach (StatisticsModel::TROOPS as $troop_id => $troop) {
            $presence_rate_by_troop[$troop_id] = [];
            foreach ($presence_buckets as $bucket) {
                $reported = $presence_reported[$troop_id][$bucket];
                $presence_rate_by_troop[$troop_id][$bucket] = $reported > 0
                    ? round(100 * $presence_present[$troop_id][$bucket] / $reported, 1)
                    : null;
            }
        }
        $presence_rate_total = [];
        foreach ($presence_buckets as $bucket) {
            $reported = $presence_reported_total[$bucket];
            $presence_rate_total[$bucket] = $reported > 0
                ? round(100 * $presence_present_total[$bucket] / $reported, 1)
                : null;
        }
        $has_presence = array_sum($presence_reported_total) > 0;

        return view('generic/head')
            . view('generic/header')
            . view('statistics', [
                'start' => $start,
                'end' => $end,
                'granularity' => $granularity,
                'presence_granularity' => $presence_granularity,
                'buckets' => $buckets,
                'troops' => StatisticsModel::TROOPS,
                'series_by_troop' => $series_by_troop,
                'series_total' => $series_total,
                'pie_troops' => $pie_troops,
                'still_here' => $still_here,
                'left' => $left,
                'platforms' => $stats_model->get_platform_distribution(),
                'platform_colors' => StatisticsModel::PLATFORM_COLORS,
                'presence_buckets' => $presence_buckets,
                'presence_rate_by_troop' => $presence_rate_by_troop,
                'presence_rate_total' => $presence_rate_total,
                'has_presence' => $has_presence,
            ])
            . view('generic/footer')
            . view('generic/foot');
    }
}
