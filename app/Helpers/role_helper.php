<?php

/**
 * Rôles dérivés des groupes XenForo de l'utilisateur.
 * user_group_id (primaire) : 8 = QM2, 9 = QM1 (quartier-maîtres, chefs de troop) ;
 * 10-20 ou 50 : état-major ; 17-20 ou 50 : officiers (sous-ensemble de l'état-major).
 * secondary_group_ids (tableau) : 55 = scénariste.
 */

if (!function_exists('is_squad_leader')) {
    function is_squad_leader(array $user): bool
    {
        return in_array($user['user_group_id'], [8, 9]);
    }
}

if (!function_exists('is_team_leader')) {
    function is_team_leader(array $user): bool
    {
        return ($user['user_group_id'] >= 10 && $user['user_group_id'] <= 20) || $user['user_group_id'] == 50;
    }
}

if (!function_exists('is_officer')) {
    function is_officer(array $user): bool
    {
        return in_array($user['user_group_id'], [17, 18, 19, 20, 50]);
    }
}

if (!function_exists('is_scenario_maker')) {
    function is_scenario_maker(array $user): bool
    {
        return in_array(55, $user['secondary_group_ids']);
    }
}

/**
 * Peut créer une opération : scénaristes et état-major (pas les simples chefs de troop).
 */
if (!function_exists('can_create_operations')) {
    function can_create_operations(array $user): bool
    {
        return is_scenario_maker($user) || is_team_leader($user);
    }
}

/**
 * Peut rédiger un rapport de présence pour sa troop et consulter le rapport
 * complet d'une opération : chefs de troop et état-major.
 */
if (!function_exists('is_squad_or_team_leader')) {
    function is_squad_or_team_leader(array $user): bool
    {
        return is_squad_leader($user) || is_team_leader($user);
    }
}
