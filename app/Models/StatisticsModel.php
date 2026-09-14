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

    private function troop_case_sql(): string
    {
        $cases = [];
        foreach (array_keys(self::TROOPS) as $troop_id) {
            $cases[] = "WHEN FIND_IN_SET($troop_id, u.secondary_group_ids) > 0 THEN $troop_id";
        }

        return 'CASE ' . implode(' ', $cases) . ' ELSE NULL END';
    }
}
