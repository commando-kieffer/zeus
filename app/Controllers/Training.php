<?php

namespace App\Controllers;

use CodeIgniter\HTTP\Exceptions\RedirectException;

use \App\Models\PointsModel;
use \App\Models\MetierModel;

class Training extends BaseController
{
    public function __construct()
    {
        if (!session('is_logged_in'))
        {
            $route = empty(uri_string()) ? '/' : uri_string();
            session()->set('after_login_url', $route);
            throw new RedirectException('login');
            exit;
        }
    }
    
    public function add_correct_point()
    {
        $points_model = model(PointsModel::class);

        $active_members = $points_model->get_active_members();

        foreach($active_members as $member) {
          if (!isset($_POST[$member->user_id])) continue;

          $points = $_POST[$member->user_id];
          if (!is_numeric($points)) continue;

          $points = intval($points);
          if ($points === 0) continue;
    
          $points_model->set_point($points, $member->user_id);
        }

        return redirect('operation_success');
    }

    public function add_blame()
    {
        $points_model = model(PointsModel::class);

        $active_members = $points_model->get_active_members();

        foreach($active_members as $member) {
            if (isset($_POST[$member->user_id]))
                $points_model->set_blame($member->user_id);
        }

        return redirect('operation_success');
    }

    public function add_warning()
    {
        $points_model = model(PointsModel::class);

        $active_members = $points_model->get_active_members();

        foreach($active_members as $member) {
            if (isset($_POST[$member->user_id]))
                $points_model->set_warning($member->user_id);
        }

        return redirect('operation_success');
    }

    /**
     * Attribue les points de métier.
     *
     * Les droits sont recalculés ici à partir de la session, jamais déduits de
     * la requête : le métier soumis doit figurer parmi ceux que l'auteur peut
     * récompenser, et chaque membre coché doit réellement exercer ce métier.
     * Sans ce second contrôle, un identifiant ajouté à la main dans le
     * formulaire suffirait à récompenser n'importe qui.
     */
    public function add_work()
    {
        helper('job_points');

        $user = session('user');
        $jobs = awardable_jobs($user);
        $job = (int) ($_POST['job'] ?? 0);

        if (!isset($jobs[$job])) {
            return view('generic/head')
                . view('generic/header')
                . view('404', ['message' => "Vous n'avez pas la permission de récompenser ce métier."])
                . view('generic/footer')
                . view('generic/foot');
        }

        $points_model = model(PointsModel::class);

        $job_title = $jobs[$job];

        foreach ($points_model->get_members_by_job(MetierModel::job_group_ids($job)) as $member) {
            if (isset($_POST[$member->user_id])) {
                $points_model->set_work($member->user_id, $job_title);
            }
        }

        return redirect('operation_success');
    }

    public function modify_training_presence()
    {
        $points_model = model(PointsModel::class);

        $points_model->delete_historic($_POST['id']);
        $active_members = $points_model->get_active_members();

        foreach($active_members as $member) {
            if (isset($_POST[$member->user_id]))
                $points_model->set_training_presence($_POST['date'], $member->user_id, $_POST['id']);
            else 
                $points_model->set_training_absence($_POST['date'], $member->user_id, $_POST['id']);
        }

        return redirect('operation_success');
    }

    public function operation_success()
    {
        return view('generic/head')
            .view('generic/header')
		    .view('operation_success')
            .view('generic/footer')
		    .view('generic/foot');
    }
}
