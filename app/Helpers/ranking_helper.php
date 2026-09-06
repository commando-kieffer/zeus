<?php

if (!function_exists('format_ranking_value')) {
    /**
     * Valeur mise en avant sur le podium pour un membre, selon le
     * classement actif (voir RankingModel::RANKINGS).
     */
    function format_ranking_value(string $ranking, $member): string
    {
        switch ($ranking) {
            case 'presence_rate':
                return ceil($member->presence_rate * 100) . '%';
            case 'absence_rate':
                return ceil($member->absence_rate * 100) . '%';
            case 'presence':
                return $member->panel_prs . ' présences';
            case 'absence':
                return $member->panel_abs . ' absences';
            case 'seniority':
            case 'juniority':
                return $member->joined_at !== null ? (new DateTime($member->joined_at))->format('d/m/y') : '-';
            case 'medals':
                return $member->medal_count . ' médailles';
            case 'points':
            default:
                return $member->panel_pts . ' pts';
        }
    }
}
