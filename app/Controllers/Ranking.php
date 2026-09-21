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

        $hide_team_leaders = !empty($_GET['hide_team_leaders']);

        $members = $ranking_model->get_members_with_stats();
        if ($hide_team_leaders) {
            // L'état-major (chefs de troop exclus, cf. is_team_leader) peut être
            // masqué du classement pour ne comparer que les membres de troop.
            $members = array_values(array_filter(
                $members,
                fn($member) => !is_team_leader(['user_group_id' => $member->user_group_id])
            ));
        }

        $members = $ranking_model->sort_members($members, $ranking);
        $ranks = $ranking_model->compute_ranks($members, $ranking);

        // Deux membres ex-aequo partagent le même rang (voir compute_ranks) :
        // on associe donc chaque membre à son rang plutôt que de déduire sa
        // position de son seul index dans la liste. Sur le podium, les
        // membres ex-aequo sont regroupés sur une seule et même marche
        // (podium_steps : rang => membres de ce rang), pas une marche
        // dupliquée par membre.
        //
        // Seule une valeur non nulle donne droit à une marche : c'est ce qui
        // empêche les soixante-sept membres à zéro OPEX de se partager la
        // troisième place en vidant le tableau. Le nombre d'ex-aequo, lui,
        // n'est pas plafonné : huit membres à 100 % de présence méritent tous
        // leur place, et les écarter laisserait un podium vide, ce qui serait
        // pire que l'encombrement.
        $podium_steps = [];
        $rest = [];
        foreach ($members as $i => $member) {
            $rank = $ranks[$i];
            if ($rank <= 3 && $ranking_model->is_podium_worthy($ranking, $member)) {
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
                'hide_team_leaders' => $hide_team_leaders,
                'current_user_id' => (int) session('user')['user_id'],
            ])
            . view('generic/footer')
            . view('generic/foot');
    }
}
