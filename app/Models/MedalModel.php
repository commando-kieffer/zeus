<?php

namespace App\Models;

use CodeIgniter\Model;

class MedalModel extends Model
{
    public function __construct()
    {
        parent::__construct();
        $this->table = "-";
    }

    public function get_all_medals()
    {
        $query = "SELECT id, name, title, description FROM medal ORDER BY title ASC";
        $result = $this->db->query($query);
        return $result->getResult();
    }

    /**
     * Médailles détenues par un ensemble de membres, en une seule requête.
     *
     * @return array<int, int[]> id_user => [id_medal, ...]
     */
    public function get_medal_ids_by_member(array $member_ids)
    {
        if (empty($member_ids)) return [];

        $placeholders = implode(',', array_fill(0, count($member_ids), '?'));
        $query = "SELECT id_user, id_medal FROM medal_attribut WHERE id_user IN ($placeholders)";
        $result = $this->db->query($query, $member_ids);

        $medals_by_member = [];
        foreach ($result->getResult() as $row) {
            $medals_by_member[$row->id_user][] = $row->id_medal;
        }

        return $medals_by_member;
    }

    public function member_has_medal($member_id, $medal_id): bool
    {
        $query = "SELECT COUNT(*) AS total FROM medal_attribut WHERE id_user = ? AND id_medal = ?";
        $result = $this->db->query($query, array($member_id, $medal_id));
        return $result->getResult()[0]->total > 0;
    }

    /**
     * Attribue une médaille à un membre. $description est le texte affiché en
     * complément de la description générique de la médaille sur la page de
     * profil (null pour n'afficher que la description générique) ; $date est la
     * date d'attribution au format Y-m-d.
     */
    public function add_medal($member_id, $medal_id, ?string $description = null, ?string $date = null)
    {
        $query = "INSERT INTO medal_attribut (id_medal, id_user, description, `date`) VALUES (?, ?, ?, ?)";
        $this->db->query($query, array($medal_id, $member_id, $description, $date));
    }

    public function remove_medal($member_id, $medal_id)
    {
        $query = "DELETE FROM medal_attribut WHERE id_medal = ? AND id_user = ?";
        $this->db->query($query, array($medal_id, $member_id));
    }
}
