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
        $query = "SELECT panel_pts, panel_prs, panel_abs, panel_opex FROM xf_user WHERE user_id = ?";
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

    public const SPE_REF_ID = [24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 47, 51, 63];

    public function extract_spe($secondary_group_ids) {
        $spe_id = $this->extract_spe_id($secondary_group_ids);

        $query = "SELECT title FROM xf_user_group WHERE user_group_id = ?";
        $result = $this->db->query($query, array($spe_id));

        $spe = [
            "spe_id" => $spe_id,
            'spe_title' => $result->getResult()[0],
        ];

        return $spe;
    }

    /**
     * Identifiant de spécialité (cf. SPE_REF_ID) sans requête en base, utile
     * pour traiter la spécialité de nombreux membres sans une requête par
     * membre (voir extract_spe, qui reste la version avec libellé pour la
     * page de profil, où un seul membre est concerné à la fois).
     */
    public function extract_spe_id($secondary_group_ids): ?int {
        if (is_string($secondary_group_ids)) {
            $secondary_group_ids = explode(',', $secondary_group_ids);
        }

        $spe_id = null;
        foreach ($secondary_group_ids as $group_id) {
            if (in_array((int) $group_id, self::SPE_REF_ID, true)) {
                $spe_id = (int) $group_id;
            }
        }

        return $spe_id;
    }

    /**
     * Libellés (xf_user_group.title) d'un ensemble de group_id, en une seule
     * requête : utile partout où plusieurs membres doivent être annotés sans
     * répéter une requête par membre (contrairement à get_profil_title, qui
     * ne traite qu'un seul group_id, pour la page de profil).
     *
     * @return array<int, string> group_id => title
     */
    public function get_group_titles(array $group_ids): array {
        if (empty($group_ids)) return [];

        $placeholders = implode(',', array_fill(0, count($group_ids), '?'));
        $query = "SELECT user_group_id, title FROM xf_user_group WHERE user_group_id IN ($placeholders)";
        $result = $this->db->query($query, $group_ids);

        $titles = [];
        foreach ($result->getResult() as $row) {
            $titles[(int) $row->user_group_id] = $row->title;
        }

        return $titles;
    }

    public function extract_metier($secondary_group_ids) {
        // Liste dérivée de MetierModel, et non recopiée : la même énumération
        // vivait jusqu'ici en trois exemplaires, si bien que retirer un métier
        // demandait trois modifications — et qu'en oublier une laissait la
        // page de profil afficher un métier que le reste du panel ignorait.
        $spe_ref_id = array_keys(MetierModel::METIERS);
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
