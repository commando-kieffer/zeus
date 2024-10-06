<?php

namespace App\Models;

use CodeIgniter\Model;
use DateTime;

class PointsModel extends Model
{

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

    public function get_active_members_by_work($members_list)
    {
        $work_ref_id = [21, 22, 23, 37, 44, 49, 55, 56, 57, 58, 59, 60, 61, 62, 64, 66, 67, 68, 69, 70, 71, 72, 73, 76, 77, 78, 79, 80];
        $troops_members_list = $this->get_active_members_by_troop($members_list);



        foreach ($troops_members_list as $troop) {

            foreach ($troop['members'] as $key => $member) {
                $secondary_group_ids = $array = explode(",", $member->secondary_group_ids);
                if (empty(array_intersect($secondary_group_ids, $work_ref_id))) {
                    unset($troops_members_list[$troop['id']]['members'][$key]);
                }
            }
        }

        return $troops_members_list;
    }

    public function get_history(int $user_id, int $page = 0)
    {
        $skip = 10 * $page;
        $query = "SELECT category_id, points, message, date FROM panel_points_histo WHERE user_id = $user_id ORDER BY date DESC LIMIT 10 OFFSET $skip";
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

    public function set_new_training($title, $date)
    {
        $query = "INSERT INTO panel_operation VALUES(NULL, ?, ?)";
        $this->db->query($query, array($title, $date));
    }

    public function get_last_training()
    {
        $query = "SELECT id FROM panel_operation ORDER BY id DESC LIMIT 1";
        $result = $this->db->query($query);
        return $result->getResult()[0]->id;
    }

    public function set_training_presence($member_id, $training)
    {
        $query = "INSERT INTO panel_historique VALUES(?, ?, ?)";
        $this->db->query($query, array($training["id"], $member_id, $training["date"]));

        $query = "UPDATE xf_user SET panel_pts = panel_pts + 20, panel_prs = panel_prs + 1 WHERE user_id = ?";
        $this->db->query($query, array($member_id));

        $query = "INSERT INTO panel_points_histo (user_id, category_id, points, message) VALUES (?, ?, ?, ?)";
        $this->db->query($query, [
            $member_id,
            PointsCategoryModel::Training->value,
            20,
            'Présence pour ' . $training["title"] . ' du ' . $training["date"]
        ]);
    }

    public function set_training_absence($member_id, $training)
    {
        $query = "UPDATE xf_user SET panel_pts = panel_pts - 5, panel_abs = panel_abs + 1 WHERE user_id = ?";
        $this->db->query($query, array($member_id));

        $query = "INSERT INTO panel_points_histo (user_id, category_id, points, message) VALUES (?, ?, ?, ?)";
        $this->db->query($query, [
            $member_id,
            PointsCategoryModel::Training->value,
            -5,
            'Absence pour ' . $training["title"] . ' du ' . $training["date"]
        ]);
    }

    public function set_point($value, $member_id)
    {
        $query = "UPDATE xf_user SET panel_pts = panel_pts + ? WHERE user_id = ?";
        $this->db->query($query, array($value, $member_id));

        $query = "INSERT INTO panel_points_histo (user_id, category_id, points) VALUES (?, ?, ?)";
        $this->db->query($query, [
            $member_id,
            PointsCategoryModel::Correction->value,
            $value
        ]);
    }

    public function set_blame($member_id)
    {
        $query = "UPDATE xf_user SET panel_pts = panel_pts - 75 WHERE user_id = ?";
        $this->db->query($query, array($member_id));

        $query = "INSERT INTO panel_points_histo (user_id, category_id, points) VALUES (?, ?, ?)";
        $this->db->query($query, [
            $member_id,
            PointsCategoryModel::Blame->value,
            -75
        ]);
    }

    public function set_warning($member_id)
    {
        $query = "UPDATE xf_user SET panel_pts = panel_pts - 45 WHERE user_id = ?";
        $this->db->query($query, array($member_id));

        $query = "INSERT INTO panel_points_histo (user_id, category_id, points) VALUES (?, ?, ?)";
        $this->db->query($query, [
            $member_id,
            PointsCategoryModel::Warning->value,
            -45
        ]);
    }

    public function set_work($member_id)
    {
        $query = "UPDATE xf_user SET panel_pts = panel_pts + 25 WHERE user_id = ?";
        $this->db->query($query, array($member_id));

        $query = "INSERT INTO panel_points_histo (user_id, category_id, points) VALUES (?, ?, ?)";
        $this->db->query($query, [
            $member_id,
            PointsCategoryModel::Work->value,
            25
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
