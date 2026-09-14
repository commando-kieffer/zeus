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
     * Taille d'un point de la courbe, en jours : 1 (par défaut) ou 7.
     */
    private function parse_granularity(?string $granularity): int
    {
        return $granularity === 'week' ? 7 : 1;
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

        $granularity = ($_GET['granularity'] ?? 'day') === 'week' ? 'week' : 'day';
        $bucket_days = $this->parse_granularity($granularity);

        // Toute la période, un point tous les $bucket_days jours à partir du
        // début de la période, y compris les points sans aucune arrivée
        // (comptés à 0) : les lignes du graphique ne doivent pas sauter d'un
        // point à l'autre en fonction des seuls jours ayant des données. Le
        // dernier point peut couvrir une plage plus courte si la période ne
        // se découpe pas en un nombre entier de points.
        $start_date = new \DateTime($start);
        $end_date = new \DateTime($end);

        $buckets = [];
        $cursor = clone $start_date;
        while ($cursor <= $end_date) {
            $buckets[] = $cursor->format('Y-m-d');
            $cursor->modify('+' . $bucket_days . ' days');
        }

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
            $days_since_start = (int) $start_date->diff(new \DateTime($row->join_date))->days;
            $bucket_key = $buckets[intdiv($days_since_start, $bucket_days)];

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

        return view('generic/head')
            . view('generic/header')
            . view('statistics', [
                'start' => $start,
                'end' => $end,
                'granularity' => $granularity,
                'buckets' => $buckets,
                'troops' => StatisticsModel::TROOPS,
                'series_by_troop' => $series_by_troop,
                'series_total' => $series_total,
                'pie_troops' => $pie_troops,
                'still_here' => $still_here,
                'left' => $left,
            ])
            . view('generic/footer')
            . view('generic/foot');
    }
}
