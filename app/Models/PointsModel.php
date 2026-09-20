<?php

namespace App\Models;

use CodeIgniter\Model;
use DateTime;

class PointsModel extends Model
{
    /** Points accordés à un membre pour son travail dans un métier. */
    public const WORK_POINTS = 25;

    public function __construct()
    {
        parent::__construct();
        $this->table = "-";
    }

    public function get_active_members()
    {
        $members = [];

        $query = "SELECT username, user_group_id, secondary_group_ids, user_id FROM xf_user ORDER BY user_order ASC, user_group_id DESC";
        $result = $this->db->query($query);

        foreach ($result->getResult() as $member) {
            if ($member->user_group_id >= 5 && $member->user_group_id <= 20 || $member->user_group_id == 50 || $member->user_group_id == 54) {
                array_push($members, $member);
            }
        }

        return $members;
    }

    public function get_active_members_with_points()
    {
        $members = [];

        $query = "SELECT username, user_group_id, secondary_group_ids, user_id, panel_pts, panel_prs, panel_abs FROM xf_user ORDER BY user_order ASC, user_group_id DESC";
        $result = $this->db->query($query);

        foreach ($result->getResult() as $member) {
            if ($member->user_group_id >= 5 && $member->user_group_id <= 20 || $member->user_group_id == 50 || $member->user_group_id == 54) {
                array_push($members, $member);
            }
        }

        return $members;
    }

    public function reparer_les_betises(int $operation_id)
    {
      $query = "SELECT * FROM panel_operation WHERE id = " . $operation_id;
      if ($this->query($query)->getNumRows() < 1) return;

      $active_members = $this->get_active_members();
      $query = "SELECT DISTINCT id_user from panel_historique WHERE id_operation = " . $operation_id;
      $presents = array_map(fn($row) => $row["id_user"], $this->db->query($query)->getResultArray());

      foreach ($active_members as $member) {
        $query = "";
        if (in_array($member->user_id, $presents)) {
          $query = "UPDATE xf_user SET panel_pts = panel_pts - 20, panel_prs = panel_prs - 1 WHERE user_id = ?";
        }
        else {
          $query = "UPDATE xf_user SET panel_pts = panel_pts + 5, panel_abs = panel_abs - 1 WHERE user_id = ?";
        }

        $this->db->query($query, array($member->user_id));
      }

      $query = "DELETE FROM panel_operation WHERE id = " . $operation_id;
      $this->db->query($query);

      $query = "DELETE FROM panel_historique WHERE id_operation = " . $operation_id;
      $this->db->query($query);
    }

    public function get_active_members_by_troop($members_list)
    {
        $troop_ref_id = [38, 39, 40, 41, 45, 46];
        $members = [
            "38" => [
                "title" => "TROOP 1",
                "id" => "38",
                "members" => [],
            ],
            "39" => [
                "title" => "TROOP 8",
                "id" => "39",
                "members" => [],
            ],
            "40" => [
                "title" => "TROOP 9 KG",
                "id" => "40",
                "members" => [],
            ],
            "41" => [
                "title" => "TROOP QG",
                "id" => "41",
                "members" => [],
            ],
            "45" => [
                "title" => "TROOP 2",
                "id" => "45",
                "members" => [],
            ],
            "46" => [
                "title" => "TROOP 3",
                "id" => "46",
                "members" => [],
            ],
        ];

        foreach ($members_list as $member) {
            $secondary_group_ids = $array = explode(",", $member->secondary_group_ids);
            foreach ($secondary_group_ids as $group_id) {
                if (in_array($group_id, $troop_ref_id)) {
                    array_push($members[$group_id]['members'], $member);
                }
            }
        }

        return $members;
    }

    /**
     * Membres actifs exerçant un métier donné.
     *
     * FIND_IN_SET et non LIKE : secondary_group_ids est une liste séparée par
     * des virgules, et un LIKE '%21%' attraperait aussi les groupes 121 ou 210.
     */
    public function get_members_by_job(array $job_group_ids): array
    {
        $job_group_ids = array_values(array_unique(array_map('intval', $job_group_ids)));
        if ($job_group_ids === []) {
            return [];
        }

        // Un service peut couvrir plusieurs groupes (l'Ambassade réunit
        // ambassadeurs et consuls) : un membre en fait partie s'il appartient
        // à l'un d'eux, d'où la disjonction.
        $conditions = implode(' OR ', array_fill(0, count($job_group_ids), 'FIND_IN_SET(?, secondary_group_ids) > 0'));

        $query = "SELECT username, user_group_id, secondary_group_ids, user_id
                  FROM xf_user
                  WHERE (((user_group_id BETWEEN 5 AND 20) OR user_group_id IN (50, 54)))
                    AND ($conditions)
                  ORDER BY user_order ASC, user_group_id DESC";

        return $this->db->query($query, $job_group_ids)->getResult();
    }

    /**
     * Nombre total de lignes d'historique de points d'un membre, pour la
     * pagination de l'historique sur la page de profil.
     */
    public function get_history_count(int $user_id): int
    {
        $query = "SELECT COUNT(*) AS total FROM panel_points_hist WHERE user_id = ?";
        return (int) $this->db->query($query, [$user_id])->getResult()[0]->total;
    }

    public function get_history(int $user_id, int $page = 0)
    {
        $skip = 10 * $page;
        $query = "SELECT category_id, points, message, date FROM panel_points_hist WHERE user_id = $user_id ORDER BY date DESC LIMIT 10 OFFSET $skip";
        $result = $this->db->query($query);

        $hist = [];
        foreach ($result->getResult() as $row) {
            array_push($hist, [
                "title" => PointsCategoryModel::from($row->category_id)->asText(),
                "message" => $row->message,
                "points" => $row->points,
                "date" => (new DateTime($row->date))->format('d/m/Y'),
            ]);
        }

        return $hist;
    }

    public function set_training_presence($member_id, $training)
    {
        $query = "INSERT INTO panel_historique VALUES(?, ?, ?)";
        $this->db->query($query, array($training["id"], $member_id, $training["date"]));

        $query = "UPDATE xf_user SET panel_pts = panel_pts + 20, panel_prs = panel_prs + 1 WHERE user_id = ?";
        $this->db->query($query, array($member_id));

        $query = "INSERT INTO panel_points_hist (user_id, category_id, points, given_by, message) VALUES (?, ?, ?, ?, ?)";
        $this->db->query($query, [
            $member_id,
            PointsCategoryModel::Training->value,
            20,
            session("user")["user_id"],
            'Présence pour ' . $training["title"] . ' du ' . $training["date"]
        ]);
    }

    public function set_training_absence($member_id, $training)
    {
        $query = "UPDATE xf_user SET panel_pts = panel_pts - 5, panel_abs = panel_abs + 1 WHERE user_id = ?";
        $this->db->query($query, array($member_id));

        $query = "INSERT INTO panel_points_hist (user_id, category_id, points, given_by, message) VALUES (?, ?, ?, ?, ?)";
        $this->db->query($query, [
            $member_id,
            PointsCategoryModel::Training->value,
            -5,
            session("user")["user_id"],
            'Absence pour ' . $training["title"] . ' du ' . $training["date"]
        ]);
    }

    public function set_point($value, $member_id)
    {
        if ($value == 0) return;

        $query = "UPDATE xf_user SET panel_pts = panel_pts + ? WHERE user_id = ?";
        $this->db->query($query, array($value, $member_id));

        $query = "INSERT INTO panel_points_hist (user_id, category_id, points, given_by) VALUES (?, ?, ?, ?)";
        $this->db->query($query, [
            $member_id,
            PointsCategoryModel::Correction->value,
            $value,
            session("user")["user_id"]
        ]);
    }

    public function set_blame($member_id)
    {
        $query = "UPDATE xf_user SET panel_pts = panel_pts - 75 WHERE user_id = ?";
        $this->db->query($query, array($member_id));

        $query = "INSERT INTO panel_points_hist (user_id, category_id, points, given_by) VALUES (?, ?, ?, ?)";
        $this->db->query($query, [
            $member_id,
            PointsCategoryModel::Blame->value,
            -75,
            session("user")["user_id"]
        ]);
    }

    public function set_warning($member_id)
    {
        $query = "UPDATE xf_user SET panel_pts = panel_pts - 45 WHERE user_id = ?";
        $this->db->query($query, array($member_id));

        $query = "INSERT INTO panel_points_hist (user_id, category_id, points, given_by) VALUES (?, ?, ?, ?)";
        $this->db->query($query, [
            $member_id,
            PointsCategoryModel::Warning->value,
            -45,
            session("user")["user_id"]
        ]);
    }

    /**
     * Récompense le travail d'un membre dans un métier.
     *
     * L'intitulé du métier est conservé dans l'historique : sans lui, un
     * membre exerçant plusieurs métiers ne pourrait pas savoir lequel a été
     * récompensé, et une ligne « Métier +25 » n'apprendrait rien.
     */
    public function set_work($member_id, ?string $job_title = null)
    {
        $query = "UPDATE xf_user SET panel_pts = panel_pts + ? WHERE user_id = ?";
        $this->db->query($query, array(self::WORK_POINTS, $member_id));

        $query = "INSERT INTO panel_points_hist (user_id, category_id, points, given_by, message) VALUES (?, ?, ?, ?, ?)";
        $this->db->query($query, [
            $member_id,
            PointsCategoryModel::Work->value,
            self::WORK_POINTS,
            session("user")["user_id"],
            $job_title === null ? null : mb_substr($job_title, 0, 256)
        ]);
    }

    public function get_training()
    {
        $query = "SELECT DISTINCT id_operation FROM panel_historique";
        $result = $this->db->query($query);
        return $result->getResult();
    }

    public function get_specific_training($training_id)
    {
        $query = "SELECT * FROM panel_operation WHERE id = ?";
        $result = $this->db->query($query, array($training_id));
        return $result->getResult();
    }

    public function get_full_training($training_id)
    {
        $query = "SELECT * FROM panel_operation WHERE id = ?";
        $result = $this->db->query($query, array($training_id));
        return $result->getResult();
    }

    public function get_training_list()
    {
        $training_list = [];
        $trainings_id = $this->get_training();

        foreach ($trainings_id as $training_id) {
            $training = $this->get_full_training($training_id->id_operation);
            array_push($training_list, $training);
        }

        return $training_list;
    }

    public function delete_historic($training_id)
    {
        $this->load->model('points_model');

        $query = "SELECT DISTINCT id_user FROM panel_historique WHERE id_operation = ?";
        $result = $this->db->query($query, array($training_id))->getResult();
        $members_id = [];

        foreach ($result as $member_id) {
            array_push($members_id, $member_id->id_user);
        }

        $active_members = $this->points_model->get_active_members();

        foreach ($active_members as $member) {
            if (in_array($member->user_id, $members_id)) {
                $this->points_model->set_point("-20", $member->user_id);

                $query = "UPDATE xf_user SET panel_prs = panel_prs - 1 WHERE user_id = ?";
                $this->db->query($query, array($member->user_id));
            } else {
                $this->points_model->set_point("5", $member->user_id);

                $query = "UPDATE xf_user SET panel_abs = panel_abs + 1 WHERE user_id = ?";
                $this->db->query($query, array($member->user_id));
            }
        }

        $query = "DELETE FROM panel_historique WHERE id_operation = ?";
        $this->db->query($query, array($training_id));
    }

    public function get_training_presence($training_id)
    {

        $query = "SELECT DISTINCT id_user FROM panel_historique WHERE id_operation = ?";
        $result = $this->db->query($query, array($training_id));
        return $result->getResult();
    }
}
