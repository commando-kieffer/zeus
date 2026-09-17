<?php

namespace App\Controllers;

use \App\Models\ApiUserModel;

class Login extends BaseController
{
    public function index(): string
    {
        return $this->render();
    }

    /**
     * Affiche le formulaire de connexion.
     *
     * Le nom saisi est réaffiché après un échec : le membre n'a que son mot de
     * passe à retaper. Le champ de mot de passe reste vide, il n'est jamais
     * renvoyé au navigateur.
     */
    private function render(string $error = '', string $nickname = ''): string
    {
        return view('generic/head')
            . view('connection', ['error' => $error, 'nickname' => $nickname])
            . view('generic/foot');
    }

    public function api_login()
    {
        $nickname = trim((string) ($_POST['nickname'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($nickname === '' || $password === '') {
            return $this->render('Identifiants incorrects', $nickname);
        }

        $failure = model(ApiUserModel::class)->api_login($nickname, $password);

        if ($failure !== null) {
            // Un forum injoignable n'est pas une faute du membre : le lui dire
            // évite de le laisser retaper indéfiniment un mot de passe correct.
            $message = $failure === ApiUserModel::FAILED_UNAVAILABLE
                ? "Le forum est momentanément injoignable. Réessayez dans un instant."
                : 'Identifiants incorrects';

            return $this->render($message, $nickname);
        }

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
