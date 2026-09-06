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
     * Les 3 statuts de présence possibles pour un membre sur une opération.
     */
    public const STATUSES = ['present', 'absent', 'unjustified'];

    /**
     * Effet de chaque statut sur les compteurs du membre, relatif à l'absence
     * de tout rapport (0 partout) : points, présences (panel_prs), absences
     * (panel_abs). "unjustified" retire les mêmes points qu'un "absent"
     * classique, seul le libellé/l'historique diffère.
     */
    private const STATUS_EFFECT = [
        'present' => ['points' => 20, 'prs' => 1, 'abs' => 0],
        'absent' => ['points' => -5, 'prs' => 0, 'abs' => 1],
        'unjustified' => ['points' => -5, 'prs' => 0, 'abs' => 1],
    ];

    private const STATUS_LABEL = [
        'present' => 'présent',
        'absent' => 'absent',
        'unjustified' => 'absence injustifiée',
    ];

    private const STATUS_INITIAL_MESSAGE = [
        'present' => 'Présence',
        'absent' => 'Absence',
        'unjustified' => 'Absence injustifiée',
    ];

    /**
     * Rapport complet d'une opération : member_id => statut ('present',
     * 'absent' ou 'unjustified').
     */
    public function get_operation_report($operation_id)
    {
        $query = "SELECT member_id, status FROM operation_report WHERE operation_id = ?";
        $result = $this->db->query($query, array($operation_id));

        $report = [];
        foreach ($result->getResult() as $row) {
            $report[$row->member_id] = $row->status;
        }

        return $report;
    }

    /**
     * Statut de présence d'un membre pour une opération donnée : 'present',
     * 'absent', 'unjustified', ou null (aucun rapport pour ce membre).
     */
    public function get_member_presence($operation_id, $member_id)
    {
        $query = "SELECT status FROM operation_report WHERE operation_id = ? AND member_id = ?";
        $result = $this->db->query($query, array($operation_id, $member_id));
        $row = $result->getResult()[0] ?? null;

        return $row === null ? null : $row->status;
    }

    /**
     * Premier rapport d'un membre pour une opération (aucune ligne
     * operation_report préexistante). $status doit être une valeur de
     * self::STATUSES.
     */
    public function record_operation_status($member_id, $operation, $reported_by, string $status)
    {
        $query = "INSERT INTO operation_report (operation_id, member_id, status, reported_by, reported_at) VALUES (?, ?, ?, ?, NOW())";
        $this->db->query($query, array($operation["id"], $member_id, $status, $reported_by));

        $effect = self::STATUS_EFFECT[$status];

        $query = "UPDATE xf_user SET panel_pts = panel_pts + ?, panel_prs = panel_prs + ?, panel_abs = panel_abs + ? WHERE user_id = ?";
        $this->db->query($query, array($effect['points'], $effect['prs'], $effect['abs'], $member_id));

        $query = "INSERT INTO panel_points_hist (user_id, category_id, points, given_by, message) VALUES (?, ?, ?, ?, ?)";
        $this->db->query($query, [
            $member_id,
            PointsCategoryModel::Operation->value,
            $effect['points'],
            $reported_by,
            self::STATUS_INITIAL_MESSAGE[$status] . ' à l\'opération "' . $operation["name"] . '" du ' . format_date_fr($operation["date"])
        ]);
    }

    /**
     * Correction du statut d'un membre déjà rapporté pour cette opération.
     * Applique uniquement la différence de points/compteurs entre l'ancien
     * et le nouveau statut (par exemple absent <-> absence injustifiée n'a
     * aucun effet sur les points, seul l'historique en garde la trace).
     */
    public function correct_operation_status($member_id, $operation, $updated_by, string $old_status, string $new_status)
    {
        if ($old_status === $new_status) return;

        $query = "UPDATE operation_report SET status = ?, updated_by = ?, updated_at = NOW() WHERE operation_id = ? AND member_id = ?";
        $this->db->query($query, array($new_status, $updated_by, $operation["id"], $member_id));

        $old_effect = self::STATUS_EFFECT[$old_status];
        $new_effect = self::STATUS_EFFECT[$new_status];
        $points_diff = $new_effect['points'] - $old_effect['points'];
        $prs_diff = $new_effect['prs'] - $old_effect['prs'];
        $abs_diff = $new_effect['abs'] - $old_effect['abs'];

        $query = "UPDATE xf_user SET panel_pts = panel_pts + ?, panel_prs = panel_prs + ?, panel_abs = panel_abs + ? WHERE user_id = ?";
        $this->db->query($query, array($points_diff, $prs_diff, $abs_diff, $member_id));

        $query = "INSERT INTO panel_points_hist (user_id, category_id, points, given_by, message) VALUES (?, ?, ?, ?, ?)";
        $this->db->query($query, [
            $member_id,
            PointsCategoryModel::Operation->value,
            $points_diff,
            $updated_by,
            'Correction de présence (' . self::STATUS_LABEL[$old_status] . ' -> ' . self::STATUS_LABEL[$new_status] . ') pour l\'opération "' . $operation["name"] . '" du ' . format_date_fr($operation["date"])
        ]);
    }
}
