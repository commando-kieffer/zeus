<?php

namespace App\Models;

use CodeIgniter\Model;

class OperationModel extends Model
{
    private const TROOP_REF_ID = [38, 39, 40, 41, 45, 46];

    public function __construct()
    {
        parent::__construct();
        $this->table = "-";
        helper('date');
    }

    private const OPERATION_SELECT = "SELECT o.*, su.username AS scenarist_name FROM operation o LEFT JOIN xf_user su ON su.user_id = o.scenarist_id";

    public function get_all_operations()
    {
        $query = self::OPERATION_SELECT . " ORDER BY o.date DESC";
        $result = $this->db->query($query);
        return $result->getResult();
    }

    public function get_operation($operation_id)
    {
        $query = self::OPERATION_SELECT . " WHERE o.id = ?";
        $result = $this->db->query($query, array($operation_id));
        $rows = $result->getResult();
        return $rows[0] ?? null;
    }

    public function create_operation($name, $date, $location, $description, $created_by, $scenarist_id)
    {
        $query = "INSERT INTO operation (name, date, location, description, created_by, scenarist_id, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())";
        $this->db->query($query, array($name, $date, $location, $description, $created_by, $scenarist_id));
        return $this->db->insertID();
    }

    /**
     * Dernière opération passée (date la plus récente avant aujourd'hui).
     */
    public function get_last_operation()
    {
        $query = self::OPERATION_SELECT . " WHERE o.date < CURDATE() ORDER BY o.date DESC LIMIT 1";
        $result = $this->db->query($query);
        $rows = $result->getResult();
        return $rows[0] ?? null;
    }

    /**
     * Prochaine opération à venir (date la plus proche après aujourd'hui).
     */
    public function get_next_operation()
    {
        $query = self::OPERATION_SELECT . " WHERE o.date > CURDATE() ORDER BY o.date ASC LIMIT 1";
        $result = $this->db->query($query);
        $rows = $result->getResult();
        return $rows[0] ?? null;
    }

    /**
     * Détermine la troop d'un membre à partir de ses secondary_group_ids
     * (tableau, comme fourni par la session utilisateur XenForo).
     */
    public function get_member_troop_id($secondary_group_ids)
    {
        foreach ($secondary_group_ids as $group_id) {
            if (in_array($group_id, self::TROOP_REF_ID)) {
                return $group_id;
            }
        }

        return null;
    }

    public function has_troop_reported($operation_id, array $member_ids)
    {
        if (empty($member_ids)) return false;

        $placeholders = implode(',', array_fill(0, count($member_ids), '?'));
        $query = "SELECT COUNT(*) AS total FROM operation_report WHERE operation_id = ? AND member_id IN ($placeholders)";
        $result = $this->db->query($query, array_merge([$operation_id], $member_ids));

        return $result->getResult()[0]->total > 0;
    }

    /**
     * Opérations pour lesquelles la troop donnée n'a pas encore de rapport.
     */
    public function get_pending_operations_for_troop(array $member_ids)
    {
        if (empty($member_ids)) {
            return $this->get_all_operations();
        }

        $placeholders = implode(',', array_fill(0, count($member_ids), '?'));
        $query = "SELECT * FROM operation WHERE id NOT IN (
            SELECT DISTINCT operation_id FROM operation_report WHERE member_id IN ($placeholders)
        ) ORDER BY date DESC";
        $result = $this->db->query($query, $member_ids);

        return $result->getResult();
    }

    /**
     * Récupère les membres correspondant aux IDs donnés tels qu'enregistrés
     * dans xf_user, sans filtrer sur l'appartenance active (contrairement à
     * PointsModel::get_active_members()). Utile pour retrouver, dans un
     * rapport de présence passé, les membres qui ont depuis quitté le
     * commando ou changé de troop : leur nom doit rester visible même s'ils
     * ne correspondent plus aux critères de membre actif.
     */
    public function get_members_by_ids(array $member_ids)
    {
        if (empty($member_ids)) return [];

        $placeholders = implode(',', array_fill(0, count($member_ids), '?'));
        $query = "SELECT username, user_group_id, secondary_group_ids, user_id FROM xf_user WHERE user_id IN ($placeholders)";
        $result = $this->db->query($query, $member_ids);

        return $result->getResult();
    }

    /**
     * Rapport complet d'une opération : member_id => présent (bool).
     */
    public function get_operation_report($operation_id)
    {
        $query = "SELECT member_id, present FROM operation_report WHERE operation_id = ?";
        $result = $this->db->query($query, array($operation_id));

        $report = [];
        foreach ($result->getResult() as $row) {
            $report[$row->member_id] = (bool) $row->present;
        }

        return $report;
    }

    /**
     * Statut de présence d'un membre pour une opération donnée :
     * true (présent), false (marqué absent), ou null (aucun rapport pour ce membre).
     */
    public function get_member_presence($operation_id, $member_id)
    {
        $query = "SELECT present FROM operation_report WHERE operation_id = ? AND member_id = ?";
        $result = $this->db->query($query, array($operation_id, $member_id));
        $row = $result->getResult()[0] ?? null;

        return $row === null ? null : (bool) $row->present;
    }

    public function set_operation_presence($member_id, $operation, $reported_by)
    {
        $query = "INSERT INTO operation_report (operation_id, member_id, present, reported_by, reported_at) VALUES (?, ?, 1, ?, NOW())";
        $this->db->query($query, array($operation["id"], $member_id, $reported_by));

        $query = "UPDATE xf_user SET panel_pts = panel_pts + 20, panel_prs = panel_prs + 1 WHERE user_id = ?";
        $this->db->query($query, array($member_id));

        $query = "INSERT INTO panel_points_hist (user_id, category_id, points, given_by, message) VALUES (?, ?, ?, ?, ?)";
        $this->db->query($query, [
            $member_id,
            PointsCategoryModel::Operation->value,
            20,
            $reported_by,
            'Présence à l\'opération "' . $operation["name"] . '" du ' . format_date_fr($operation["date"])
        ]);
    }

    public function set_operation_absence($member_id, $operation, $reported_by)
    {
        $query = "INSERT INTO operation_report (operation_id, member_id, present, reported_by, reported_at) VALUES (?, ?, 0, ?, NOW())";
        $this->db->query($query, array($operation["id"], $member_id, $reported_by));

        $query = "UPDATE xf_user SET panel_pts = panel_pts - 5, panel_abs = panel_abs + 1 WHERE user_id = ?";
        $this->db->query($query, array($member_id));

        $query = "INSERT INTO panel_points_hist (user_id, category_id, points, given_by, message) VALUES (?, ?, ?, ?, ?)";
        $this->db->query($query, [
            $member_id,
            PointsCategoryModel::Operation->value,
            -5,
            $reported_by,
            'Absence à l\'opération "' . $operation["name"] . '" du ' . format_date_fr($operation["date"])
        ]);
    }

    /**
     * Correction : un membre déjà marqué absent pour cette opération passe présent.
     * Annule le malus d'absence et applique le bonus de présence en une seule écriture nette.
     */
    public function correct_to_present($member_id, $operation, $updated_by)
    {
        $query = "UPDATE operation_report SET present = 1, updated_by = ?, updated_at = NOW() WHERE operation_id = ? AND member_id = ?";
        $this->db->query($query, array($updated_by, $operation["id"], $member_id));

        $query = "UPDATE xf_user SET panel_pts = panel_pts + 25, panel_prs = panel_prs + 1, panel_abs = panel_abs - 1 WHERE user_id = ?";
        $this->db->query($query, array($member_id));

        $query = "INSERT INTO panel_points_hist (user_id, category_id, points, given_by, message) VALUES (?, ?, ?, ?, ?)";
        $this->db->query($query, [
            $member_id,
            PointsCategoryModel::Operation->value,
            25,
            $updated_by,
            'Correction de présence (absent -> présent) pour l\'opération "' . $operation["name"] . '" du ' . format_date_fr($operation["date"])
        ]);
    }

    /**
     * Correction : un membre déjà marqué présent pour cette opération passe absent.
     * Annule le bonus de présence et applique le malus d'absence en une seule écriture nette.
     */
    public function correct_to_absent($member_id, $operation, $updated_by)
    {
        $query = "UPDATE operation_report SET present = 0, updated_by = ?, updated_at = NOW() WHERE operation_id = ? AND member_id = ?";
        $this->db->query($query, array($updated_by, $operation["id"], $member_id));

        $query = "UPDATE xf_user SET panel_pts = panel_pts - 25, panel_prs = panel_prs - 1, panel_abs = panel_abs + 1 WHERE user_id = ?";
        $this->db->query($query, array($member_id));

        $query = "INSERT INTO panel_points_hist (user_id, category_id, points, given_by, message) VALUES (?, ?, ?, ?, ?)";
        $this->db->query($query, [
            $member_id,
            PointsCategoryModel::Operation->value,
            -25,
            $updated_by,
            'Correction de présence (présent -> absent) pour l\'opération "' . $operation["name"] . '" du ' . format_date_fr($operation["date"])
        ]);
    }
}
