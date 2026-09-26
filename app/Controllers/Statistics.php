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

        helper('period');
    }

    private function render_message(string $message): string
    {
        return view('generic/head')
            . view('generic/header')
            . view('404', ['message' => $message])
            . view('generic/footer')
            . view('generic/foot');
    }

    public function index()
    {
        if (!is_team_leader(session('user'))) {
            return $this->render_message("Vous n'avez pas la permission d'accéder à cette page.");
        }

        $today = new \DateTime('today');
        $default_end = $today->format('Y-m-d');
        $default_start = (clone $today)->modify('-29 days')->format('Y-m-d');

        $start = parse_period_date($_GET['start'] ?? null) ?? $default_start;
        $end = parse_period_date($_GET['end'] ?? null) ?? $default_end;
        if ($start > $end) {
            [$start, $end] = [$end, $start];
        }

        $granularity = parse_period_granularity($_GET['granularity'] ?? null, ['day', 'week', 'month'], 'week');
        // Les opérations n'ont lieu qu'une fois par semaine : un intervalle
        // d'un jour n'aurait aucun sens pour le taux de présence, d'où une
        // liste de granularités autorisées plus courte que celle du
        // graphique des arrivées.
        $presence_granularity = parse_period_granularity($_GET['presence_granularity'] ?? null, ['week', 'month'], 'week');

        // Toute la période, un point par bucket à partir du début de la
        // période, y compris les buckets sans aucune arrivée (comptés à 0) :
        // les lignes du graphique ne doivent pas sauter d'un point à l'autre
        // en fonction des seuls buckets ayant des données.
        $start_date = new \DateTime($start);
        $end_date = new \DateTime($end);

        $buckets = build_period_buckets($start_date, $end_date, $granularity);

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
            $bucket_key = period_bucket_key($buckets, $start_date, new \DateTime($row->join_date), $granularity);

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
        $presence_buckets = build_period_buckets($start_date, $end_date, $presence_granularity);

        $presence_present = [];
        $presence_reported = [];
        foreach (StatisticsModel::TROOPS as $troop_id => $troop) {
            $presence_present[$troop_id] = array_fill_keys($presence_buckets, 0);
            $presence_reported[$troop_id] = array_fill_keys($presence_buckets, 0);
        }
        $presence_present_total = array_fill_keys($presence_buckets, 0);
        $presence_reported_total = array_fill_keys($presence_buckets, 0);

        foreach ($stats_model->get_operation_presence($start, $end) as $row) {
            $bucket_key = period_bucket_key($presence_buckets, $start_date, new \DateTime($row->op_date), $presence_granularity);

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
                'presence_count_by_troop' => $presence_present,
                'presence_count_total' => $presence_present_total,
                'has_presence' => $has_presence,
            ])
            . view('generic/footer')
            . view('generic/foot');
    }
}
