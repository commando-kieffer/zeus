<?php

namespace App\Models;

use CodeIgniter\Model;

class ToolsModel extends Model
{
    public function __construct()
    {
        parent::__construct();
        $this->table = "-";
        helper('role');
    }

    /**
     * Tire au sort $picks_per_round membres, sans remise au sein d'un même
     * tirage, $rounds fois de suite. Chaque tirage repart du pool complet :
     * un même membre peut donc être choisi lors de plusieurs tirages, comme
     * pour des mini-jeux indépendants les uns des autres.
     *
     * @param array $pool [['member' => object, 'weight' => float], ...], déjà
     *                     filtré aux membres éligibles (troop cochée, poids > 0).
     * @return array<int, array> un sous-tableau de membres tirés, par tirage.
     */
    public function draw_raffle(array $pool, int $picks_per_round, int $rounds): array
    {
        $results = [];
        for ($i = 0; $i < $rounds; $i++) {
            $results[] = $this->weighted_sample($pool, $picks_per_round);
        }

        return $results;
    }

    /**
     * Tirage pondéré sans remise : à chaque tirage, la probabilité de chaque
     * membre restant est proportionnelle à son multiplicateur.
     */
    private function weighted_sample(array $pool, int $count): array
    {
        $picked = [];
        $remaining = $pool;

        for ($i = 0; $i < $count && !empty($remaining); $i++) {
            $total_weight = array_sum(array_column($remaining, 'weight'));
            $target = mt_rand() / mt_getrandmax() * $total_weight;

            $cumulative = 0.0;
            foreach ($remaining as $key => $entry) {
                $cumulative += $entry['weight'];
                if ($target <= $cumulative) {
                    $picked[] = $entry['member'];
                    unset($remaining[$key]);
                    break;
                }
            }
        }

        return $picked;
    }

    /**
     * Répartit $members en $team_count équipes aléatoires.
     *
     * Chaque membre est affecté, dans un ordre aléatoire, à l'équipe qui lui
     * convient le mieux : d'abord celle qui compte le moins de membres de sa
     * spécialité (member->spe_id, voir ProfileModel::extract_spe_id) si
     * $consider_spe, puis, à égalité, celle qui compte le moins de membres
     * de sa catégorie de grade (État-Major / QM / troupe) si $consider_rank,
     * puis, à égalité, la moins peuplée ; les égalités restantes sont
     * tranchées aléatoirement (ordre des équipes mélangé à chaque membre).
     *
     * La spécialité est traitée en priorité (avant le grade) : c'est ce qui
     * garantit que CHAQUE spécialité se répartit à un membre près entre les
     * équipes, quel que soit le grade de ses membres. Grade et spécialité ne
     * peuvent pas toujours être également équilibrés en même temps — un
     * unique infirmier à l'État-Major, par exemple, ne peut rejoindre qu'une
     * seule équipe à la fois — et c'est la spécialité qui l'emporte dans ce
     * genre de cas.
     *
     * @return array<int, array> un sous-tableau de membres par équipe.
     */
    public function form_teams(array $members, int $team_count, bool $consider_rank, bool $consider_spe): array
    {
        $teams = array_fill(0, $team_count, []);
        if (empty($members) || $team_count < 1) return $teams;

        shuffle($members);

        // Base assez grande pour qu'aucun nombre de membres d'une même
        // spécialité ou d'un même grade (toujours ≤ count($members)) ne
        // puisse jamais peser plus lourd qu'une différence sur le critère
        // de priorité supérieure : un score se lit comme trois chiffres
        // empilés (spécialité, grade, taille), du plus au moins significatif.
        $base = count($members) + 1;

        $spe_counts = array_fill(0, $team_count, []);
        $rank_counts = array_fill(0, $team_count, []);

        foreach ($members as $member) {
            $spe_key = $consider_spe ? ($member->spe_id ?? 0) : null;
            $rank_key = $consider_rank ? $this->rank_bucket($member) : null;

            $team_order = range(0, $team_count - 1);
            shuffle($team_order);

            $best_team = $team_order[0];
            $best_score = null;

            foreach ($team_order as $team_id) {
                $score = (($spe_key !== null ? ($spe_counts[$team_id][$spe_key] ?? 0) : 0) * $base * $base)
                    + (($rank_key !== null ? ($rank_counts[$team_id][$rank_key] ?? 0) : 0) * $base)
                    + count($teams[$team_id]);

                if ($best_score === null || $score < $best_score) {
                    $best_score = $score;
                    $best_team = $team_id;
                }
            }

            $teams[$best_team][] = $member;
            if ($spe_key !== null) {
                $spe_counts[$best_team][$spe_key] = ($spe_counts[$best_team][$spe_key] ?? 0) + 1;
            }
            if ($rank_key !== null) {
                $rank_counts[$best_team][$rank_key] = ($rank_counts[$best_team][$rank_key] ?? 0) + 1;
            }
        }

        // Affichage : chaque équipe liste d'abord son État-Major, puis ses QM,
        // puis le reste, quelle que soit la valeur de $consider_rank (qui ne
        // gouverne que la répartition, pas la présentation du résultat).
        // rank_bucket() est appelé ici plutôt que dans le tri lui-même pour
        // n'annoter chaque membre qu'une fois, tri stable (PHP >= 8) à l'appui
        // pour ne pas mélanger à nouveau l'ordre aléatoire au sein d'un même
        // grade.
        $priority = ['etat_major' => 0, 'qm' => 1, 'troupe' => 2];
        foreach ($teams as &$team) {
            foreach ($team as $member) {
                $member->rank_bucket = $this->rank_bucket($member);
            }
            usort($team, fn($a, $b) => $priority[$a->rank_bucket] <=> $priority[$b->rank_bucket]);
        }
        unset($team);

        return $teams;
    }

    /** Catégorie de grade d'un membre : État-Major, QM (quartier-maître), ou troupe. */
    private function rank_bucket($member): string
    {
        $user = ['user_group_id' => (int) $member->user_group_id];

        if (is_team_leader($user)) return 'etat_major';
        if (is_squad_leader($user)) return 'qm';

        return 'troupe';
    }
}
