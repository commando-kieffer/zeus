<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Association entre le rapport de présence d'une troop pour une opération et
 * le message posté sur le forum correspondant (XenForo post_id). Permet,
 * lorsqu'un rapport déjà publié est corrigé, de mettre à jour ce message au
 * lieu d'en créer un nouveau.
 */
class OperationReportPostModel extends Model
{
    public function __construct()
    {
        parent::__construct();
        $this->table = "-";
    }

    public function get_post_id($operation_id, $troop_id): ?int
    {
        $query = "SELECT post_id FROM operation_report_post WHERE operation_id = ? AND troop_id = ?";
        $result = $this->db->query($query, array($operation_id, $troop_id));
        $row = $result->getResult()[0] ?? null;

        return $row === null ? null : (int) $row->post_id;
    }

    public function set_post_id($operation_id, $troop_id, $post_id): void
    {
        $query = "INSERT INTO operation_report_post (operation_id, troop_id, post_id, created_at) VALUES (?, ?, ?, NOW())";
        $this->db->query($query, array($operation_id, $troop_id, $post_id));
    }

    public function touch_updated($operation_id, $troop_id): void
    {
        $query = "UPDATE operation_report_post SET updated_at = NOW() WHERE operation_id = ? AND troop_id = ?";
        $this->db->query($query, array($operation_id, $troop_id));
    }
}
