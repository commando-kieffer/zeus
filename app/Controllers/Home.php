<?php

namespace App\Controllers;

use CodeIgniter\HTTP\Exceptions\RedirectException;

use \App\Models\ProfileModel;
use \App\Models\PointsModel;

class Home extends BaseController
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

    public function index(): string
    {
        return view('generic/head')
            . view('generic/header')
            . view('home')
            . view('generic/footer')
            . view('generic/foot');
    }

    public function not_found(): string
    {
        return view('generic/head')
        . view('generic/header')
        . view('404', [
            'message' => "J'ai cherché mais je n'ai pas trouvé la page que vous avez demandée... =(",
        ])
        . view('generic/footer')
        . view('generic/foot');
    }

    public function profil($user_id = -1)
    {
        $profil_model = model(ProfileModel::class);
        $points_model = model(PointsModel::class);
        $user = session("user");

        if ($user_id != -1) {
            $user = $profil_model->get_user_profile($user_id);
            if (empty($user) || !$profil_model->is_member($user)) {
                return view('generic/head')
                    . view('generic/header')
                    . view('404', [
                        'message' => "Le membre recherché n'existe pas ou ne fait plus partie du commando Kieffer.",
                    ])
                    . view('generic/footer')
                    . view('generic/foot');
            }
        }

        $profil = [
            'grade' => $profil_model->get_profil_title($user['user_group_id']),
            'stats' => $profil_model->get_profil_stats($user['user_id']),
            'troop_bordee_spe' => $profil_model->get_profil_troop_bordee_spe($user['secondary_group_ids']),
            'metiers' => $profil_model->get_profil_metier($user['secondary_group_ids']),
            'medailles' => $profil_model->get_profil_medal($user['user_id']),
        ];

        $points_history = $points_model->get_history($user['user_id']);

        return view('generic/head')
            . view('generic/header')
            . view('profil', [
                'user' => $user,
                'profil' => $profil,
                'points_history' => $points_history
            ])
            . view('generic/footer')
            . view('generic/foot');
    }

    public function training()
    {
        $points_model = model(PointsModel::class);

        $members = $points_model->get_active_members_by_troop($points_model->get_active_members());

        return view('generic/head')
            . view('generic/header')
            . view('training', ['members' => $members])
            . view('generic/footer')
            . view('generic/foot');
    }

    public function correct_point()
    {
        $points_model = model(PointsModel::class);

        $members = $points_model->get_active_members_by_troop($points_model->get_active_members());

        return view('generic/head')
            . view('generic/header')
            . view('correct_point', ['members' => $members])
            . view('generic/footer')
            . view('generic/foot');
    }

    public function blame()
    {
        $points_model = model(PointsModel::class);

        $members = $points_model->get_active_members_by_troop($points_model->get_active_members());

        return view('generic/head')
            . view('generic/header')
            . view('blame', ['members' => $members])
            . view('generic/footer')
            . view('generic/foot');
    }

    public function warning()
    {
        $points_model = model(PointsModel::class);

        $members = $points_model->get_active_members_by_troop($points_model->get_active_members());

        return view('generic/head')
            . view('generic/header')
            . view('warning', ['members' => $members])
            . view('generic/footer')
            . view('generic/foot');
    }

    public function work()
    {
        $points_model = model(PointsModel::class);

        $members = $points_model->get_active_members_by_work($points_model->get_active_members());

        return view('generic/head')
            . view('generic/header')
            . view('work', ['members' => $members])
            . view('generic/footer')
            . view('generic/foot');
    }

    public function select_training()
    {
        $points_model = model(PointsModel::class);

        $trainings = $points_model->get_training_list();

        return view('generic/head')
            . view('generic/header')
            . view('select_training', ['trainings' => $trainings])
            . view('generic/footer')
            . view('generic/foot');
    }

    public function modify_training()
    {
        $points_model = model(PointsModel::class);

        $members = $points_model->get_active_members_by_troop($points_model->get_active_members());
        $presence = $points_model->get_training_presence($_POST['training_id']);
        $training = $points_model->get_specific_training($_POST['training_id']);


        return view('generic/head')
            . view('generic/header')
            . view('modify_training', [
                'members' => $members,
                'presence' => $presence,
                'training' => $training
            ])
            . view('generic/footer')
            . view('generic/foot');
    }
}
