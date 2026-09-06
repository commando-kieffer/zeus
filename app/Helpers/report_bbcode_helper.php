<?php

if (!function_exists('format_operation_report_bbcode')) {
    /**
     * Formate en BBCode le rapport de présence d'une troop pour une
     * opération, dans le format utilisé sur le sujet du forum dédié à cette
     * troop (voir .example-report-bbcode à la racine du projet pour un
     * exemple concret) : informations générales, compte-rendu, décompte par
     * bordée puis liste nominative par bordée.
     *
     * Bordée 1 = secondary_group_ids contient 52, bordée 2 = contient 53.
     * À l'intérieur d'une bordée, les membres sont triés par rang
     * (user_group_id) décroissant, du grade le plus haut au plus bas. Un
     * membre sans bordée reconnue est rattaché à la bordée 1 par défaut :
     * ça ne devrait pas arriver avec des données à jour, mais évite de le
     * faire disparaître silencieusement du décompte.
     *
     * $members : tableau d'objets avec au moins username, user_group_id,
     * secondary_group_ids (chaîne CSV, comme renvoyée par xf_user) et status
     * ('present', 'absent' ou 'unjustified').
     */
    function format_operation_report_bbcode($operation, string $note_content, array $members): string
    {
        $bordee_1 = [];
        $bordee_2 = [];

        foreach ($members as $member) {
            $group_ids = explode(',', (string) $member->secondary_group_ids);

            if (in_array('53', $group_ids)) {
                $bordee_2[] = $member;
            } else {
                // Bordée 1 par défaut, y compris pour un membre sans bordée reconnue.
                $bordee_1[] = $member;
            }
        }

        $by_rank_desc = fn($a, $b) => $b->user_group_id <=> $a->user_group_id;
        usort($bordee_1, $by_rank_desc);
        usort($bordee_2, $by_rank_desc);

        $counts_1 = report_bbcode_counts($bordee_1);
        $counts_2 = report_bbcode_counts($bordee_2);
        $counts_total = [
            'present' => $counts_1['present'] + $counts_2['present'],
            'absent' => $counts_1['absent'] + $counts_2['absent'],
            'unjustified' => $counts_1['unjustified'] + $counts_2['unjustified'],
            'total' => $counts_1['total'] + $counts_2['total'],
        ];

        $bbcode = "[SIZE=6][B][U]Informations générales[/U][/B][/SIZE]\n\n";
        $bbcode .= "[B][U]Opération :[/U][/B] {$operation->name}\n";
        $bbcode .= "[B][U]Carte :[/U][/B] {$operation->location}\n";
        $bbcode .= "[B][U]Date :[/U][/B] " . format_date_fr($operation->date) . "\n\n";

        $bbcode .= "[SIZE=6][B][U]Compte-rendu[/U][/B][/SIZE]\n\n";
        $bbcode .= $note_content . "\n\n";

        $bbcode .= "[U][B][SIZE=6]Effectif[/SIZE]\n\n[SIZE=5]Décompte[/SIZE][/B][/U]\n\n";
        $bbcode .= "[U]Bordée 1 :[/U] " . report_bbcode_count_line($counts_1) . "\n";
        $bbcode .= "[U]Bordée 2 :[/U] " . report_bbcode_count_line($counts_2) . "\n\n";
        $bbcode .= "[U]Total :[/U] " . report_bbcode_count_line($counts_total) . "\n\n\n";

        $bbcode .= "[U][SIZE=5][B]Bordée 1[/B][/SIZE][/U]\n\n";
        $bbcode .= report_bbcode_member_list($bordee_1);
        $bbcode .= "\n\n\n[U][SIZE=5][B]Bordée 2[/B][/SIZE][/U]\n\n";
        $bbcode .= report_bbcode_member_list($bordee_2);

        return $bbcode;
    }
}

if (!function_exists('report_bbcode_counts')) {
    /**
     * Décompte des statuts d'une liste de membres déjà affectés à une
     * bordée : present / absent / unjustified / total.
     */
    function report_bbcode_counts(array $members): array
    {
        $counts = ['present' => 0, 'absent' => 0, 'unjustified' => 0, 'total' => 0];

        foreach ($members as $member) {
            $counts[$member->status]++;
            $counts['total']++;
        }

        return $counts;
    }
}

if (!function_exists('report_bbcode_count_line')) {
    /**
     * Ligne de décompte telle qu'utilisée pour chaque bordée et pour le total.
     */
    function report_bbcode_count_line(array $counts): string
    {
        return "{$counts['present']}/{$counts['total']} [COLOR=rgb(97, 189, 109)]présents[/COLOR] "
            . "([COLOR=rgb(250, 197, 28)]absences justifiées[/COLOR] : {$counts['absent']}, "
            . "[COLOR=rgb(209, 72, 65)]absences injustifiées[/COLOR] : {$counts['unjustified']})";
    }
}

if (!function_exists('report_bbcode_member_list')) {
    /**
     * Liste nominative (une ligne par membre) d'une bordée, déjà triée.
     * Chaque membre est précédé de l'abréviation de son grade (ex.
     * "Qm2.Danson"), voir report_bbcode_rank_shorthand().
     */
    function report_bbcode_member_list(array $members): string
    {
        $labels = [
            'present' => 'Présent',
            'absent' => 'Absent',
            'unjustified' => 'Absence injustifiée',
        ];
        $colors = [
            'present' => 'rgb(97, 189, 109)',
            'absent' => 'rgb(250, 197, 28)',
            'unjustified' => 'rgb(209, 72, 65)',
        ];

        $lines = array_map(function ($member) use ($labels, $colors) {
            $rank = report_bbcode_rank_shorthand((int) $member->user_group_id);
            $name = $rank !== '' ? "$rank.{$member->username}" : $member->username;

            return "[B]{$name}[/B] ([COLOR={$colors[$member->status]}]{$labels[$member->status]}[/COLOR])";
        }, $members);

        return implode("\n", $lines);
    }
}

if (!function_exists('report_bbcode_rank_shorthand')) {
    /**
     * Abréviation de grade (convention Marine nationale) affichée devant le
     * pseudo dans la liste nominative, ex. "Qm2" pour "Quartier-maître de
     * seconde classe". xf_user_group ne stocke que le titre complet, d'où
     * cette table de correspondance dédiée - à ajuster si un grade a une
     * abréviation "maison" différente de la convention standard.
     */
    function report_bbcode_rank_shorthand(int $user_group_id): string
    {
        $shorthands = [
            5 => 'Cadet',
            6 => 'Mtl',
            7 => 'Mtb',
            8 => 'Qm2',
            9 => 'Qm1',
            10 => 'Smm',
            11 => 'Sm2',
            12 => 'Sm1',
            13 => 'M',
            14 => 'Pm',
            15 => 'Mp',
            16 => 'Maj',
            17 => 'Ev2',
            18 => 'Ev1',
            19 => 'Lv',
            20 => 'CptC',
            50 => 'Asp',
            54 => 'Rsv',
        ];

        return $shorthands[$user_group_id] ?? '';
    }
}
