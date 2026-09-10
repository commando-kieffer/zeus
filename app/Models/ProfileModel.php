<?php

namespace App\Models;

use CodeIgniter\Model;
use \Config\Database;
use PhpParser\Node\Expr\Cast\Object_;

class ProfileModel extends Model
{
    public function __construct()
    {
        parent::__construct();
        $this->table = "-";
    }

    public function get_user_profile($user_id)
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => env("FORUM_BASE_URI") . "/index.php/api/users/$user_id",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_TIMEOUT => 0,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'XF-Api-User: 8', // Avec l'ID du compte Commando Kieffer, pour avoir accès à des champs restreints (ex : groupes) aux utilisateurs non-admin
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

        if (empty($data["errors"]) && !empty($data["user"]))
            return $data["user"];
    
        return [];
    }

    public function is_member($user): bool
    {
        return ($user["user_group_id"] >= 5 && $user["user_group_id"] <= 20) ||
            $user["user_group_id"] == 50 ||
            $user["user_group_id"] == 54;
    }

    public function get_profil_title($user_group_id)
    {
        $query = "SELECT title FROM xf_user_group WHERE user_group_id = ?";
        $result = $this->db->query($query, array($user_group_id));
        return $result->getResult()[0];
    }

    public function get_profil_stats($user_id) {
        $query = "SELECT panel_pts, panel_prs, panel_abs FROM xf_user WHERE user_id = ?";
        $result = $this->db->query($query, array($user_id));
        return $result->getResult()[0];
    }

    /**
     * Date d'entrée du membre (infos_recrutement.date, jointe sur user_id),
     * ou null pour un compte antérieur au suivi des recrutements.
     */
    public function get_profil_joined_at($user_id) {
        $query = "SELECT date FROM infos_recrutement WHERE user_id = ?";
        $row = $this->db->query($query, array($user_id))->getResult()[0] ?? null;
        return $row === null ? null : $row->date;
    }

    public function get_profil_troop_bordee_spe($secondary_group_ids) {
        $troop_bordee_spe = [
            'troop' => $this->extract_troop($secondary_group_ids),
            'bordee' => $this->extract_bordee($secondary_group_ids),
            'spe' => $this->extract_spe($secondary_group_ids),
        ];

        return $troop_bordee_spe;
    }

    public function get_profil_metier($secondary_group_ids) {
        return $this->extract_metier($secondary_group_ids);
    }

    public function get_profil_medal($user_id) {
        return $this->extract_user_medal($this->extract_user_medal_id($user_id));
    }

    // Fonctions réutilisables

    public function extract_troop($secondary_group_ids) {
        $troop_ref_id = [38, 39, 40, 41, 45, 46];
        $troop_id = null;

        foreach ($secondary_group_ids as $group_id) {
            if (in_array($group_id, $troop_ref_id)) {
                $troop_id = $group_id;
            }
        }

        $query = "SELECT title FROM xf_user_group WHERE user_group_id = ?";
        $result = $this->db->query($query, array($troop_id));

        $troop = [
            "troop_id" => $troop_id,
            'troop_title' => $result->getResult()[0],
        ];

        return $troop;
    }

    public function extract_bordee($secondary_group_ids) {
        $bordee_ref_id = [52, 53];

        foreach ($secondary_group_ids as $group_id) {
            if (in_array($group_id, $bordee_ref_id)) {
                $bordee_id = $group_id;
            }
        }

        $query = "SELECT title FROM xf_user_group WHERE user_group_id = ?";
        $result = $this->db->query($query, array($bordee_id));

        $bordee = [
            "bordee_id" => $bordee_id,
            'bordee_title' => $result->getResult()[0],
        ];

        return $bordee;
    }

    public function extract_spe($secondary_group_ids) {
        $spe_ref_id = [24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 47, 51, 63];

        foreach ($secondary_group_ids as $group_id) {
            if (in_array($group_id, $spe_ref_id)) {
                $spe_id = $group_id;
            }
        }

        $query = "SELECT title FROM xf_user_group WHERE user_group_id = ?";
        $result = $this->db->query($query, array($spe_id));

        $spe = [
            "spe_id" => $spe_id,
            'spe_title' => $result->getResult()[0],
        ];

        return $spe;
    }

    public function extract_metier($secondary_group_ids) {
        $spe_ref_id = [21, 22, 23, 37, 44, 49, 55, 56, 57, 58, 59, 60, 61, 62, 64, 66, 67, 68, 69, 70, 71, 72, 73, 76, 77, 78, 79, 80];
        $metier_list = [];

        foreach ($secondary_group_ids as $group_id) {
            if (in_array($group_id, $spe_ref_id)) {
                $query = "SELECT title FROM xf_user_group WHERE user_group_id = ?";
                $result = $this->db->query($query, array($group_id));
        
                $metier = [
                    "group_id" => $group_id,
                    'metier_title' => $result->getResult()[0],
                ];
                array_push($metier_list, $metier);
            }
        }
        
        if (empty($metier_list)) {
            $metier = [
                'group_id' => null,
                'metier_title' => (object) (['title' => "Pas encore ? Cela doit être une erreur... Hop, hop, hop ! Manifeste toi !"]),
            ];
            array_push($metier_list, $metier);
        }

        return $metier_list;
    }

    public function extract_user_medal_id($user_id) {
        $query = "SELECT id_medal, description AS attribution_description, `date` AS attribution_date FROM medal_attribut WHERE id_user = ?";
        $result = $this->db->query($query, array($user_id));

        return $result->getResult();
    }

    public function extract_user_medal($medals_array) {
        $medals_list = [];
        foreach ($medals_array as $attribut) {
            $query = "SELECT name, title, description FROM medal WHERE id = ?";
            $rows = $this->db->query($query, array($attribut->id_medal))->getResult();
            if (empty($rows)) {
                continue;
            }

            // Complément propre à cette attribution (page de profil uniquement) :
            // date de remise et description spécifique, en plus de la description
            // générique de la médaille.
            $rows[0]->attribution_description = $attribut->attribution_description;
            $rows[0]->attribution_date = $attribut->attribution_date;
            array_push($medals_list, $rows);
        }

        return $medals_list;
    }
}
