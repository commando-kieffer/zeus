<?php

namespace App\Models;

use CodeIgniter\Model;

class ApiUserModel extends Model {

    /** Identifiants refusés par le forum. */
    public const FAILED_CREDENTIALS = 'credentials';

    /** Forum injoignable : panne réseau, API hors service. */
    public const FAILED_UNAVAILABLE = 'unavailable';

    public function __construct()
    {
        parent::__construct();
        $this->table = "-";
    }

    /**
     * Authentifie un membre auprès du forum et ouvre sa session.
     *
     * Renvoie null en cas de succès, sinon l'une des constantes FAILED_*.
     * L'échec n'est volontairement pas une exception : un mot de passe erroné
     * est un déroulement normal, pas une anomalie du programme, et c'est au
     * contrôleur de décider quoi afficher.
     */
    public function api_login($nickname, $password): ?string
    {
        $curl = curl_init();

        $url_name = rawurlencode($nickname);
        $url_pw = rawurlencode($password);

        curl_setopt_array($curl, array(
            CURLOPT_URL => env("FORUM_BASE_URI") . "/index.php/api/auth/?login=" . $url_name . "&password=" . $url_pw,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_TIMEOUT => 0,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER => array(
                'XF-Api-Key: ' . env("XEN_API_KEY"),
            ),
        ));

        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($curl);
        $curl_error = curl_error($curl);

        curl_close($curl);

        if ($curl_error !== '') {
            log_message('error', 'Connexion : appel au forum impossible — ' . $curl_error);

            return self::FAILED_UNAVAILABLE;
        }

        $data = json_decode($response, true);

        // Quand les identifiants sont refusés, le forum ne renvoie PAS de clé
        // "success" : il renvoie une structure d'erreur. Lire cette clé sans
        // précaution provoquait une erreur fatale, et donc la page vide
        // constatée à la place du formulaire.
        if (!is_array($data) || empty($data['success']) || empty($data['user'])) {
            return self::FAILED_CREDENTIALS;
        }

        $session = session();
        $session->set('user', $data['user']);
        $session->set('is_logged_in', true);

        return null;
    }
}
