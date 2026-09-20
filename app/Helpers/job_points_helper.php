<?php

use App\Models\MetierModel;

/**
 * Métiers pour lesquels un membre a le droit d'attribuer des points.
 *
 * L'état-major encadre l'ensemble des services et peut récompenser n'importe
 * quel métier ; un chef de service ne peut récompenser que le ou les métiers
 * qu'il dirige.
 *
 * Le drapeau is_staff n'entre volontairement PAS dans ce calcul, bien qu'il
 * commande l'affichage du menu « Points ». Il est porté par la quasi-totalité
 * des membres ayant une responsabilité, chefs de service compris : s'en servir
 * pour ouvrir tous les métiers viderait de son sens le périmètre du chef, qui
 * est précisément ce que cette page cherche à respecter.
 *
 * Fonction unique et partagée entre l'affichage et l'enregistrement : c'est
 * elle qui décide, des deux côtés, de ce qui est permis. Deux listes calculées
 * séparément finiraient tôt ou tard par diverger, et l'écart se solderait par
 * une autorisation accordée à tort.
 *
 * @return array<int, string> group_id du métier => intitulé, trié par intitulé
 */
if (!function_exists('awardable_jobs')) {
    function awardable_jobs(array $user): array
    {
        $jobs = is_team_leader($user)
            ? MetierModel::all_jobs()
            : MetierModel::jobs_led_by($user['secondary_group_ids']);

        asort($jobs);

        return $jobs;
    }
}

/**
 * Droit d'accéder à la page d'attribution des points de métier.
 */
if (!function_exists('can_award_job_points')) {
    function can_award_job_points(array $user): bool
    {
        return awardable_jobs($user) !== [];
    }
}
