<?php

namespace App\Controllers;

use CodeIgniter\HTTP\Exceptions\RedirectException;

use \App\Models\PointsModel;

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
    
    public function add_training()
	{
        $points_model = model(PointsModel::class);

        $points_model->set_new_training($_POST['title'], $_POST['date']);
        $active_members = $points_model->get_active_members();

        $training = [
            "id" => $points_model->get_last_training(),
            "title" => $_POST['title'],
            "date" => $_POST['date']
        ];

        foreach($active_members as $member) {
            if (isset($_POST[$member->user_id])) {
                $points_model->set_training_presence($member->user_id, $training);
            }
            else {
                $points_model->set_training_absence($member->user_id, $training);
            }
        }

        return redirect('operation_success');
	}

    public function add_correct_point()
    {
        $points_model = model(PointsModel::class);

        $active_members = $points_model->get_active_members();

        foreach($active_members as $member) {
            if (isset($_POST[$member->user_id]))
                $points_model->set_point($_POST[$member->user_id], $member->user_id);
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

    public function add_work()
    {
        $points_model = model(PointsModel::class);

        $active_members = $points_model->get_active_members();

        foreach($active_members as $member) {
            if (isset($_POST[$member->user_id]))
                $points_model->set_work($member->user_id);
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
