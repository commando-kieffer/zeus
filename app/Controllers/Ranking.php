<?php

namespace App\Controllers;

use CodeIgniter\HTTP\Exceptions\RedirectException;

use App\Models\RankingModel;

class Ranking extends BaseController
{
    public function __construct()
    {
        if (!session('is_logged_in')) {
            $route = empty(uri_string()) ? '/' : uri_string();
            session()->set('after_login_url', $route);
            throw new RedirectException('login');
            exit;
        }

        helper('ranking');
    }

    public function index()
    {
        $ranking_model = model(RankingModel::class);

        $ranking = $_GET['ranking'] ?? array_key_first(RankingModel::RANKINGS);
        if (!array_key_exists($ranking, RankingModel::RANKINGS)) {
            $ranking = array_key_first(RankingModel::RANKINGS);
        }

        $members = $ranking_model->sort_members($ranking_model->get_members_with_stats(), $ranking);
        $ranks = $ranking_model->compute_ranks($members, $ranking);

        // Deux membres ex-aequo partagent le même rang (voir compute_ranks) :
        // on associe donc chaque membre à son rang plutôt que de déduire sa
        // position de son seul index dans la liste. Sur le podium, les
        // membres ex-aequo sont regroupés sur une seule et même marche
        // (podium_steps : rang => membres de ce rang), pas une marche
        // dupliquée par membre.
        $podium_steps = [];
        $rest = [];
        foreach ($members as $i => $member) {
            $rank = $ranks[$i];
            if ($rank <= 3) {
                $podium_steps[$rank][] = $member;
            } else {
                $rest[] = ['rank' => $rank, 'member' => $member];
            }
        }

        return view('generic/head')
            . view('generic/header')
            . view('rankings', [
                'rankings' => RankingModel::RANKINGS,
                'ranking' => $ranking,
                'is_negative' => RankingModel::RANKINGS[$ranking]['negative'],
                'podium_steps' => $podium_steps,
                'rest' => $rest,
            ])
            . view('generic/footer')
            . view('generic/foot');
    }
}
