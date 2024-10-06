<?php

namespace App\Controllers;

use \App\Models\ApiUserModel;

class Login extends BaseController
{
    public function index(): string
    {
		return view('generic/head')
            .view('connection')
            .view('generic/foot');
    }

    public function api_login()
	{
        $api_user_model = model(ApiUserModel::class);
		$api_user_model->api_login($_POST['nickname'], $_POST['password']);

        $route = session('after_login_url') ?? '';
        session()->remove('after_login_url');
        return redirect()->to($route);
	}

    public function logout()
    {
        $session = session();
        $session->remove('user');
        $session->remove('is_logged_in');

        return redirect('/');
    }
}