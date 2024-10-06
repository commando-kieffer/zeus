<?php

namespace App\Models;

use CodeIgniter\HTTP\Exceptions\RedirectException;

use CodeIgniter\Model;

class ApiUserModel extends Model {

    public function __construct()
    {
        parent::__construct();
        $this->table = "-";
    }

    public function api_login($nickname, $password)
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://forum.commandokieffer.com/index.php/api/auth/?login=" . $nickname . "&password=" . $password,
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
        
        if (curl_error($curl)) {
            // var_dump(curl_error($curl));
            die("Une erreur cURL est survenue.");
        }
        
        curl_close($curl);
        
        $data = json_decode($response, true);

        $session = session();
        
        if ($data["success"]) {
            $session->set('user', $data["user"]);
            $session->set('is_logged_in', true);
        } else {
            throw new RedirectException('');
        }
    }
}