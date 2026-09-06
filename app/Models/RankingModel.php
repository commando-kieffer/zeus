<?php

namespace App\Models;

use CodeIgniter\Model;

class RankingModel extends Model
{
    /**
     * Classements disponibles : libellé affiché dans le menu déroulant, et
     * si le classement est "négatif" (le haut du classement met en avant un
     * défaut plutôt qu'une qualité - bonnet d'âne sur le podium).
     */
    public const RANKINGS = [
        'points' => ['label' => 'Points', 'negative' => false],
        'presence_rate' => ['label' => 'Taux de présence', 'negative' => false],
        'absence_rate' => ['label' => "Taux d'absence", 'negative' => true],
        'presence' => ['label' => 'Présences', 'negative' => false],
        'absence' => ['label' => 'Absences', 'negative' => true],
        'seniority' => ['label' => 'Ancienneté', 'negative' => false],
        'juniority' => ['label' => 'Derniers arrivés', 'negative' => false],
        'medals' => ['label' => 'Nombre de médailles', 'negative' => false],
    ];

    public function __construct()
    {
        parent::__construct();
        $this->table = "-";
    }

    /**
     * Tous les membres actifs (toutes troops confondues), enrichis des
     * données nécessaires aux classements : taux de présence/absence,
     * nombre de médailles, date d'adhésion (infos_recrutement.date, null
     * pour un compte antérieur au suivi des recrutements).
     */
    public function get_members_with_stats(): array
    {
        $points_model = model(PointsModel::class);
        $medal_model = model(MedalModel::class);

        $members = $points_model->get_active_members_with_points();
        $member_ids = array_map(fn($member) => (int) $member->user_id, $members);

        $medals_by_member = $medal_model->get_medal_ids_by_member($member_ids);
        $joined_at_by_member = $this->get_joined_at_by_member($member_ids);

        foreach ($members as $member) {
            $total = $member->panel_prs + $member->panel_abs;
            $member->presence_rate = $total === 0 ? 0.0 : $member->panel_prs / $total;
            $member->absence_rate = $total === 0 ? 0.0 : $member->panel_abs / $total;
            $member->medal_count = count($medals_by_member[$member->user_id] ?? []);
            $member->joined_at = $joined_at_by_member[$member->user_id] ?? null;
        }

        return $members;
    }

    /**
     * Trie une liste de membres (déjà enrichie par get_members_with_stats())
     * selon le classement demandé, du meilleur (ou pire, pour un classement
     * négatif) au moins bon.
     */
    public function sort_members(array $members, string $ranking): array
    {
        $sorted = $members;
        usort($sorted, $this->get_comparator($ranking));

        return $sorted;
    }

    /**
     * Position (1er, 2e, ...) de chaque membre d'une liste déjà triée par
     * sort_members() pour ce même classement. Deux membres ex-aequo (le
     * comparateur les considère égaux) partagent le même rang, et le rang
     * suivant saute en conséquence (1, 1, 3, 4...), comme un classement
     * sportif classique - jamais deux membres à égalité n'obtiennent un rang
     * différent simplement parce que l'un précède l'autre dans la liste.
     *
     * @return int[] même longueur et même ordre que $sorted_members.
     */
    public function compute_ranks(array $sorted_members, string $ranking): array
    {
        $comparator = $this->get_comparator($ranking);

        $ranks = [];
        $rank = 0;
        foreach ($sorted_members as $i => $member) {
            if ($i === 0 || $comparator($sorted_members[$i - 1], $member) !== 0) {
                $rank = $i + 1;
            }
            $ranks[] = $rank;
        }

        return $ranks;
    }

    private function get_comparator(string $ranking): \Closure
    {
        switch ($ranking) {
            case 'presence_rate':
                return fn($a, $b) => $b->presence_rate <=> $a->presence_rate;
            case 'absence_rate':
                return fn($a, $b) => $b->absence_rate <=> $a->absence_rate;
            case 'presence':
                return fn($a, $b) => $b->panel_prs <=> $a->panel_prs;
            case 'absence':
                return fn($a, $b) => $b->panel_abs <=> $a->panel_abs;
            case 'seniority':
                // Le plus ancien d'abord ; une date d'adhésion inconnue est
                // toujours reléguée en fin de classement.
                return fn($a, $b) => $this->compare_joined_at($a, $b, true);
            case 'juniority':
                return fn($a, $b) => $this->compare_joined_at($a, $b, false);
            case 'medals':
                return fn($a, $b) => $b->medal_count <=> $a->medal_count;
            case 'points':
            default:
                return fn($a, $b) => $b->panel_pts <=> $a->panel_pts;
        }
    }

    private function compare_joined_at($a, $b, bool $ascending): int
    {
        if ($a->joined_at === null) {
            return $b->joined_at === null ? 0 : 1;
        }
        if ($b->joined_at === null) {
            return -1;
        }

        // Comparaison au jour près (comme la date affichée, sans l'heure) :
        // deux membres arrivés le même jour sont ex-aequo, même si l'horaire
        // exact d'adhésion diffère de quelques heures.
        $a_date = substr($a->joined_at, 0, 10);
        $b_date = substr($b->joined_at, 0, 10);

        return $ascending ? strcmp($a_date, $b_date) : strcmp($b_date, $a_date);
    }

    private function get_joined_at_by_member(array $member_ids): array
    {
        if (empty($member_ids)) return [];

        $placeholders = implode(',', array_fill(0, count($member_ids), '?'));
        $query = "SELECT user_id, date FROM infos_recrutement WHERE user_id IN ($placeholders)";
        $result = $this->db->query($query, $member_ids);

        $joined_at = [];
        foreach ($result->getResult() as $row) {
            $joined_at[$row->user_id] = $row->date;
        }

        return $joined_at;
    }
}
