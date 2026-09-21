<?php

namespace App\Controllers;

use CodeIgniter\HTTP\Exceptions\RedirectException;

use App\Models\PointsModel;
use App\Models\ProfileModel;
use App\Models\ToolsModel;

class Tools extends BaseController
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

    /**
     * Troops actives (cf. PointsModel::get_active_members_by_troop), non
     * vides, chacune enrichie de son état "cochée" et de chaque membre de son
     * multiplicateur — depuis $_POST si $from_post, sinon des valeurs par
     * défaut (troop cochée, multiplicateur à 1).
     */
    private function get_raffle_troops(bool $from_post): array
    {
        $points_model = model(PointsModel::class);
        $troops = $points_model->get_active_members_by_troop($points_model->get_active_members());

        foreach ($troops as $troop_id => &$troop) {
            $troop['checked'] = $from_post ? isset($_POST['troop_' . $troop_id]) : true;

            foreach ($troop['members'] as $member) {
                $member->troop_id = $troop_id;
                $member->troop_title = $troop['title'];

                $multiplier = $from_post ? (float) ($_POST['multiplier_' . $member->user_id] ?? 1) : 1.0;
                $member->multiplier = max(0.0, $multiplier);
            }
        }
        unset($troop);

        return array_filter($troops, fn($troop) => !empty($troop['members']));
    }

    public function raffle()
    {
        return view('generic/head')
            . view('generic/header')
            . view('raffle', [
                'troops' => $this->get_raffle_troops(false),
                'rounds' => 1,
                'players_per_round' => 1,
                'results' => null,
                'errors' => [],
            ])
            . view('generic/footer')
            . view('generic/foot');
    }

    public function draw_raffle()
    {
        $troops = $this->get_raffle_troops(true);

        $rounds = (int) ($_POST['rounds'] ?? 0);
        $players_per_round = (int) ($_POST['players_per_round'] ?? 0);

        $pool = [];
        foreach ($troops as $troop) {
            if (!$troop['checked']) continue;
            foreach ($troop['members'] as $member) {
                if ($member->multiplier <= 0) continue;
                $pool[] = ['member' => $member, 'weight' => $member->multiplier];
            }
        }

        $errors = [];
        if ($rounds < 1) {
            $errors[] = "Le nombre de tirages doit être d'au moins 1.";
        } elseif ($rounds > 100) {
            $errors[] = "Le nombre de tirages ne peut pas dépasser 100.";
        }
        if ($players_per_round < 1) {
            $errors[] = "Le nombre de membres tirés au sort doit être d'au moins 1.";
        }
        if (empty($pool)) {
            $errors[] = "Aucun membre éligible : vérifiez qu'au moins une troop est sélectionnée et qu'au moins un membre a un multiplicateur supérieur à 0.";
        } elseif ($players_per_round > count($pool)) {
            $errors[] = "Le nombre de membres tirés au sort (" . $players_per_round . ") ne peut pas dépasser le nombre de membres éligibles (" . count($pool) . ").";
        }

        $results = null;
        if (empty($errors)) {
            $results = model(ToolsModel::class)->draw_raffle($pool, $players_per_round, $rounds);
        }

        return view('generic/head')
            . view('generic/header')
            . view('raffle', [
                'troops' => $troops,
                'rounds' => $rounds,
                'players_per_round' => $players_per_round,
                'results' => $results,
                'errors' => $errors,
            ])
            . view('generic/footer')
            . view('generic/foot');
    }

    /**
     * Troops actives, non vides, chacune enrichie de l'état "coché" de chaque
     * membre (sélectionné pour la formation d'équipes) et de sa spécialité —
     * depuis $_POST si $from_post, sinon des valeurs par défaut (tous les
     * membres cochés).
     */
    private function get_team_troops(bool $from_post): array
    {
        $points_model = model(PointsModel::class);
        $profil_model = model(ProfileModel::class);

        $troops = $points_model->get_active_members_by_troop($points_model->get_active_members());
        $spe_titles = $profil_model->get_group_titles(ProfileModel::SPE_REF_ID);

        foreach ($troops as $troop_id => &$troop) {
            foreach ($troop['members'] as $member) {
                $member->troop_id = $troop_id;
                $member->troop_title = $troop['title'];
                $member->spe_id = $profil_model->extract_spe_id($member->secondary_group_ids);
                $member->spe_title = $spe_titles[$member->spe_id] ?? null;
                $member->checked = $from_post ? isset($_POST['member_' . $member->user_id]) : true;
            }
        }
        unset($troop);

        return array_filter($troops, fn($troop) => !empty($troop['members']));
    }

    public function teams()
    {
        return view('generic/head')
            . view('generic/header')
            . view('team_formation', [
                'troops' => $this->get_team_troops(false),
                'team_count' => 2,
                'consider_rank' => true,
                'consider_spe' => true,
                'teams' => null,
                'errors' => [],
            ])
            . view('generic/footer')
            . view('generic/foot');
    }

    public function form_teams()
    {
        $troops = $this->get_team_troops(true);

        $team_count = (int) ($_POST['team_count'] ?? 0);
        $consider_rank = isset($_POST['consider_rank']);
        $consider_spe = isset($_POST['consider_spe']);

        $selected = [];
        foreach ($troops as $troop) {
            foreach ($troop['members'] as $member) {
                if ($member->checked) $selected[] = $member;
            }
        }

        $errors = [];
        if ($team_count < 2) {
            $errors[] = "Il faut au moins 2 équipes.";
        }
        if (empty($selected)) {
            $errors[] = "Aucun membre sélectionné.";
        } elseif ($team_count > count($selected)) {
            $errors[] = "Le nombre d'équipes (" . $team_count . ") ne peut pas dépasser le nombre de membres sélectionnés (" . count($selected) . ").";
        }

        $teams = null;
        if (empty($errors)) {
            $teams = model(ToolsModel::class)->form_teams($selected, $team_count, $consider_rank, $consider_spe);
        }

        return view('generic/head')
            . view('generic/header')
            . view('team_formation', [
                'troops' => $troops,
                'team_count' => $team_count,
                'consider_rank' => $consider_rank,
                'consider_spe' => $consider_spe,
                'teams' => $teams,
                'errors' => $errors,
            ])
            . view('generic/footer')
            . view('generic/foot');
    }
}
