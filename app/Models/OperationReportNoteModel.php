<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Compte-rendu texte libre d'une troop pour une opération, rédigé à la
 * soumission du rapport de présence et modifiable ensuite par les officiers
 * (mêmes permissions que la correction du rapport de présence lui-même).
 */
class OperationReportNoteModel extends Model
{
    public function __construct()
    {
        parent::__construct();
        $this->table = "-";
    }

    public function get_note($operation_id, $troop_id)
    {
        $query = "SELECT * FROM operation_report_note WHERE operation_id = ? AND troop_id = ?";
        $result = $this->db->query($query, array($operation_id, $troop_id));
        return $result->getResult()[0] ?? null;
    }

    /**
     * Tous les comptes-rendus d'une opération, indexés par troop_id.
     */
    public function get_notes_for_operation($operation_id)
    {
        $query = "SELECT * FROM operation_report_note WHERE operation_id = ?";
        $result = $this->db->query($query, array($operation_id));

        $notes = [];
        foreach ($result->getResult() as $row) {
            $notes[$row->troop_id] = $row;
        }

        return $notes;
    }

    public function set_note($operation_id, $troop_id, $content, $written_by)
    {
        $query = "INSERT INTO operation_report_note (operation_id, troop_id, content, written_by, written_at) VALUES (?, ?, ?, ?, NOW())";
        $this->db->query($query, array($operation_id, $troop_id, $content, $written_by));
    }

    public function update_note($operation_id, $troop_id, $content, $updated_by)
    {
        $query = "UPDATE operation_report_note SET content = ?, updated_by = ?, updated_at = NOW() WHERE operation_id = ? AND troop_id = ?";
        $this->db->query($query, array($content, $updated_by, $operation_id, $troop_id));
    }
}
