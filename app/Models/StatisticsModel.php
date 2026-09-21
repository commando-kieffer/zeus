<?php

namespace App\Models;

use CodeIgniter\Model;

class StatisticsModel extends Model
{
    /**
     * Troops suivies par les statistiques d'arrivées, avec la couleur
     * utilisée pour leur ligne/part de camembert (reprise des teintes des
     * badges de troop, voir templates/elements/_badge.scss).
     */
    public const TROOPS = [
        38 => ['title' => 'TROOP 1', 'color' => '#2591fb'],
        39 => ['title' => 'TROOP 8', 'color' => '#e21818'],
        40 => ['title' => 'TROOP 9 KG', 'color' => '#3d8361'],
        41 => ['title' => 'TROOP QG', 'color' => '#f9c43b'],
        45 => ['title' => 'TROOP 2', 'color' => '#ff9153'],
        46 => ['title' => 'TROOP 3', 'color' => '#a3157e'],
    ];

    public function __construct()
    {
        parent::__construct();
        $this->table = "-";
    }

    /**
     * Une ligne par arrivée enregistrée dans infos_recrutement sur la
     * période [start, end] (bornes incluses, dates au format Y-m-d), avec la
     * troop ACTUELLE du membre déduite de xf_user.secondary_group_ids (null
     * s'il n'appartient plus à aucune troop connue : parti, ou compte
     * disparu du forum). Une ré-inscription (plusieurs lignes
     * infos_recrutement pour un même membre) compte comme autant d'arrivées
     * distinctes, la table ne gardant pas d'autre trace de la troop
     * d'origine.
     *
     * @return array<int, object{join_date: string, troop_id: ?int}>
     */
    public function get_new_joiners(string $start, string $end): array
    {
        $troop_case = $this->troop_case_sql();
        $query = "SELECT DATE(ir.date) AS join_date, $troop_case AS troop_id
            FROM infos_recrutement ir
            LEFT JOIN xf_user u ON u.user_id = ir.user_id
            WHERE DATE(ir.date) BETWEEN ? AND ?
            ORDER BY join_date ASC";

        $result = $this->db->query($query, [$start, $end]);

        $rows = [];
        foreach ($result->getResult() as $row) {
            $row->troop_id = $row->troop_id === null ? null : (int) $row->troop_id;
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Une ligne par présence rapportée (operation_report) pour une opération
     * dont la date tombe dans [start, end] (bornes incluses, dates au format
     * Y-m-d). troop_id est la troop figée sur le rapport au moment où il a
     * été rempli, pas l'appartenance actuelle du membre : un membre parti ou
     * ayant changé de troop depuis reste compté dans la troop qu'il avait ce
     * jour-là.
     *
     * @return array<int, object{op_date: string, troop_id: ?int, status: string}>
     */
    public function get_operation_presence(string $start, string $end): array
    {
        $query = "SELECT o.date AS op_date, r.troop_id, r.status
            FROM operation_report r
            INNER JOIN operation o ON o.id = r.operation_id
            WHERE o.date BETWEEN ? AND ?
            ORDER BY o.date ASC";

        $result = $this->db->query($query, [$start, $end]);

        $rows = [];
        foreach ($result->getResult() as $row) {
            $row->troop_id = $row->troop_id === null ? null : (int) $row->troop_id;
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Couleurs des plateformes du graphique de répartition.
     */
    public const PLATFORM_COLORS = [
        'PC'          => '#6b7280',
        'PlayStation' => '#2e6db4',
        'Xbox'        => '#107c10',
        'Inconnue'    => '#c7cad1',
    ];

    /**
     * Répartition des membres ACTUELS par plateforme de jeu.
     *
     * Volontairement indépendant de la période choisie : il s'agit d'un état
     * des lieux de l'effectif d'aujourd'hui, pas d'un flux d'arrivées. Un
     * membre sans fiche panel_user est compté comme "Inconnue" plutôt
     * qu'ignoré, sans quoi le total du camembert ne correspondrait pas à
     * l'effectif réel.
     *
     * @return array<string, int> plateforme => nombre de membres
     */
    public function get_platform_distribution(): array
    {
        $query = 'SELECT COALESCE(pu.platform, ?) AS platform, COUNT(*) AS total
                  FROM xf_user u
                  LEFT JOIN panel_user pu ON pu.user_id = u.user_id
                  WHERE (u.user_group_id BETWEEN 5 AND 20) OR u.user_group_id IN (50, 54)
                  GROUP BY COALESCE(pu.platform, ?)';

        $counts = array_fill_keys(array_keys(self::PLATFORM_COLORS), 0);

        foreach ($this->db->query($query, ['Inconnue', 'Inconnue'])->getResult() as $row) {
            $counts[$row->platform] = (int) $row->total;
        }

        return $counts;
    }

    private function troop_case_sql(): string
    {
        $cases = [];
        foreach (array_keys(self::TROOPS) as $troop_id) {
            $cases[] = "WHEN FIND_IN_SET($troop_id, u.secondary_group_ids) > 0 THEN $troop_id";
        }

        return 'CASE ' . implode(' ', $cases) . ' ELSE NULL END';
    }
}
