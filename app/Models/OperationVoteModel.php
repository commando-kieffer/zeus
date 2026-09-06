<?php

namespace App\Models;

use CodeIgniter\Model;

class OperationVoteModel extends Model
{
    /**
     * Critères de notation : champ du formulaire / colonne => libellé complet.
     */
    public const CRITERIA = [
        'map_rating' => 'Choix de la carte',
        'mapping_rating' => 'Mapping',
        'defense_rating' => 'Jouabilité en défense',
        'attack_rating' => 'Jouabilité en attaque',
    ];

    /**
     * Libellés courts, utilisés sur les cartes d'opération (accueil, liste).
     */
    public const CRITERIA_SHORT = [
        'map_rating' => 'Carte',
        'mapping_rating' => 'Mapping',
        'defense_rating' => 'Défense',
        'attack_rating' => 'Attaque',
    ];

    public function __construct()
    {
        parent::__construct();
        $this->table = "-";
        helper('date');
    }

    public function has_voted($operation_id, $member_id): bool
    {
        $query = "SELECT COUNT(*) AS total FROM operation_vote WHERE operation_id = ? AND member_id = ?";
        $result = $this->db->query($query, array($operation_id, $member_id));

        return $result->getResult()[0]->total > 0;
    }

    public function submit_vote($operation_id, $member_id, $map_rating, $mapping_rating, $defense_rating, $attack_rating)
    {
        $query = "INSERT INTO operation_vote (operation_id, member_id, map_rating, mapping_rating, defense_rating, attack_rating, voted_at) VALUES (?, ?, ?, ?, ?, ?, NOW())";
        $this->db->query($query, array($operation_id, $member_id, $map_rating, $mapping_rating, $defense_rating, $attack_rating));
    }

    /**
     * Moyennes (arrondies à 1 décimale) et nombre de votants pour une opération.
     * Les moyennes sont nulles tant qu'aucun vote n'a été enregistré.
     */
    public function get_averages($operation_id)
    {
        $query = "SELECT
                ROUND(AVG(map_rating), 1) AS map_rating_avg,
                ROUND(AVG(mapping_rating), 1) AS mapping_rating_avg,
                ROUND(AVG(defense_rating), 1) AS defense_rating_avg,
                ROUND(AVG(attack_rating), 1) AS attack_rating_avg,
                COUNT(*) AS voter_count
            FROM operation_vote WHERE operation_id = ?";
        $result = $this->db->query($query, array($operation_id));

        return $result->getResult()[0];
    }

    /**
     * Comme get_averages(), mais pour plusieurs opérations en une seule requête
     * (utilisé par les listes de cartes) : operation_id => moyennes.
     */
    public function get_averages_for_operations(array $operation_ids)
    {
        if (empty($operation_ids)) return [];

        $placeholders = implode(',', array_fill(0, count($operation_ids), '?'));
        $query = "SELECT
                operation_id,
                ROUND(AVG(map_rating), 1) AS map_rating_avg,
                ROUND(AVG(mapping_rating), 1) AS mapping_rating_avg,
                ROUND(AVG(defense_rating), 1) AS defense_rating_avg,
                ROUND(AVG(attack_rating), 1) AS attack_rating_avg,
                COUNT(*) AS voter_count
            FROM operation_vote WHERE operation_id IN ($placeholders) GROUP BY operation_id";
        $result = $this->db->query($query, $operation_ids);

        $averages = [];
        foreach ($result->getResult() as $row) {
            $averages[$row->operation_id] = $row;
        }

        return $averages;
    }

    private function get_voted_operation_ids($member_id, array $operation_ids)
    {
        if (empty($operation_ids)) return [];

        $placeholders = implode(',', array_fill(0, count($operation_ids), '?'));
        $query = "SELECT operation_id FROM operation_vote WHERE member_id = ? AND operation_id IN ($placeholders)";
        $result = $this->db->query($query, array_merge([$member_id], $operation_ids));

        return array_map(fn($row) => $row->operation_id, $result->getResult());
    }

    /**
     * Moyennes visibles pour un membre donné, sur un ensemble d'opérations
     * (utilisé pour les cartes de l'accueil et de la liste des opérations) :
     * operation_id => moyennes, uniquement pour les opérations où ce membre
     * est autorisé à voir la moyenne (cf. get_visible_averages_for_member)
     * et qui ont déjà reçu au moins un vote. Prend des objets opération
     * complets (pas de simples IDs) car la date est nécessaire pour savoir
     * si le vote est encore ouvert.
     */
    public function get_visible_averages_for_member($member_id, array $operations)
    {
        if (empty($operations)) return [];

        $operation_ids = array_map(fn($op) => $op->id, $operations);
        $averages = $this->get_averages_for_operations($operation_ids);

        $placeholders = implode(',', array_fill(0, count($operation_ids), '?'));
        $presence_query = "SELECT operation_id, status FROM operation_report WHERE member_id = ? AND operation_id IN ($placeholders)";
        $presence_result = $this->db->query($presence_query, array_merge([$member_id], $operation_ids));

        $presence = [];
        foreach ($presence_result->getResult() as $row) {
            $presence[$row->operation_id] = $row->status === 'present';
        }

        $voted_ids = $this->get_voted_operation_ids($member_id, $operation_ids);

        $visible = [];
        foreach ($operations as $operation) {
            $is_present = $presence[$operation->id] ?? false;
            $has_voted = in_array($operation->id, $voted_ids);
            // Une fois le vote fermé, même un participant qui n'a jamais noté
            // voit le résultat : il ne peut de toute façon plus voter.
            $can_view = !($is_present && !$has_voted) || is_voting_closed($operation->date);
            $row = $averages[$operation->id] ?? null;

            if ($can_view && $row !== null && (int) $row->voter_count > 0) {
                $visible[$operation->id] = $row;
            }
        }

        return $visible;
    }
}
