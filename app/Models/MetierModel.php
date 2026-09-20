<?php

namespace App\Models;

/**
 * Badges "Métiers" affichés sur le tableau des membres de la page d'accueil :
 * un badge de 2 lettres par métier, coloré par métier (l'abréviation et/ou
 * la couleur peuvent être réutilisées par un autre métier, seule la paire
 * abréviation+couleur identifie un métier de façon unique). Un chef de
 * service (ex. Chef Instructeur) porte le même badge que le métier de base
 * (ex. Instructeur), rendu avec un style plus soigné (voir 'chief').
 *
 * Ne concerne que l'affichage : contrairement à ProfileModel::extract_metier
 * (utilisé sur la page de profil, avec le libellé complet du métier), cette
 * liste est dédiée aux badges compacts de la page d'accueil.
 */
class MetierModel
{
    public const METIERS = [
        // group_id => [title, abréviation, couleur, chef ?]
        21 => ['Instructeur', 'IN', '#c0392b', false],
        56 => ['Chef Instructeur', 'IN', '#c0392b', true],
        44 => ['Instructeur spé armes', 'IN', '#2980b9', false],
        58 => ['Chef Instructeur spé armes', 'IN', '#2980b9', true],
        64 => ['Instructeur Béret Vert', 'IN', '#4b5320', false],
        57 => ['Chef Instructeur Béret Vert', 'IN', '#4b5320', true],
        22 => ['Recruteur', 'RC', '#8e44ad', false],
        59 => ['Chef Recruteur', 'RC', '#8e44ad', true],
        23 => ['Opérateur technique', 'OP', '#795548', false],
        60 => ['Chef Opérateur technique', 'OP', '#795548', true],
        37 => ['Police militaire', 'PM', '#607d8b', false],
        61 => ['Chef Police militaire', 'PM', '#607d8b', true],
        55 => ['Scénariste', 'GN', '#795548', false],
        67 => ['Chef scénariste', 'GN', '#795548', true],
        68 => ['Graphiste & Design', 'GR', '#795548', false],
        69 => ['Chef Graphiste & Design', 'GR', '#795548', true],
        70 => ['Rédacteur', 'RD', '#795548', false],
        71 => ['Rédacteur en chef', 'RD', '#795548', true],
        78 => ['Section Historique', 'HT', '#795548', false],
        77 => ['Chef section Historique', 'HT', '#795548', true],
        80 => ['Cartographe', 'CR', '#795548', false],
        79 => ['Chef cartographe', 'CR', '#795548', true],
        62 => ['Ambassadeur', 'AM', '#b8860b', false],
        66 => ['Consul', 'CO', '#3f51b5', false],
        76 => ['Communication', 'CM', '#00bcd4', false],
        73 => ['Chef de la communication', 'CM', '#00bcd4', true],
    ];

    /**
     * Chef de service => métier qu'il dirige.
     *
     * Cette correspondance est explicite, et non déduite de l'abréviation et
     * de la couleur partagées par les deux entrées de METIERS : celles-ci sont
     * des attributs d'affichage, et faire reposer une autorisation dessus
     * signifierait qu'un simple ajustement de couleur pourrait changer qui a
     * le droit d'attribuer des points.
     *
     * Diriger un métier et en exercer un ne s'excluent pas : l'Ambassadeur
     * encadre les Consuls tout en restant un métier à part entière, qui peut
     * lui-même recevoir des points. Le drapeau « chef » de METIERS reste donc
     * une indication d'affichage — il commande le rendu du badge — et n'a pas
     * à coïncider avec cette table, qui seule fait autorité sur l'encadrement.
     */
    public const CHIEF_OF = [
        56 => 21, // Chef Instructeur               -> Instructeur
        58 => 44, // Chef Instructeur spé armes     -> Instructeur spé armes
        57 => 64, // Chef Instructeur Béret Vert    -> Instructeur Béret Vert
        59 => 22, // Chef Recruteur                 -> Recruteur
        60 => 23, // Chef Opérateur technique       -> Opérateur technique
        61 => 37, // Chef Police militaire          -> Police militaire
        67 => 55, // Chef scénariste                -> Scénariste
        69 => 68, // Chef Graphiste & Design        -> Graphiste & Design
        71 => 70, // Rédacteur en chef              -> Rédacteur
        77 => 78, // Chef section Historique        -> Section Historique
        79 => 80, // Chef cartographe               -> Cartographe
        73 => 76, // Chef de la communication       -> Communication
        62 => 66, // Ambassadeur                    -> Consul
    ];

    /**
     * Normalise secondary_group_ids, reçu tantôt en tableau, tantôt en chaîne
     * CSV selon l'appelant.
     *
     * @return int[]
     */
    private static function group_ids($secondary_group_ids): array
    {
        if (is_string($secondary_group_ids)) {
            $secondary_group_ids = explode(',', $secondary_group_ids);
        }

        return array_map('intval', (array) $secondary_group_ids);
    }

    /**
     * Métiers dont un membre est chef.
     *
     * @return array<int, string> group_id du métier => intitulé du métier
     */
    public static function jobs_led_by($secondary_group_ids): array
    {
        $group_ids = self::group_ids($secondary_group_ids);
        $jobs = [];

        foreach (self::CHIEF_OF as $chief_group_id => $job_group_id) {
            if (in_array($chief_group_id, $group_ids, true)) {
                // Diriger un métier absorbé par un service, c'est diriger le
                // service entier : le chef le récompense d'un seul geste.
                $representative = self::unit_representative($job_group_id);
                $jobs[$representative] = self::job_title($representative);
            }
        }

        return $jobs;
    }

    /**
     * Métiers présentés sous un intitulé commun lors de l'attribution des
     * points : groupe représentatif => [intitulé affiché, groupes couverts].
     *
     * L'Ambassadeur et le Consul forment un seul service, l'Ambassade, et se
     * récompensent ensemble. Ils gardent en revanche des badges distincts sur
     * la page d'accueil : ce regroupement ne concerne que l'attribution.
     */
    public const JOB_UNITS = [
        62 => ['Ambassade', [62, 66]],
    ];

    /**
     * Groupes couverts par un métier sélectionnable. Un métier ordinaire ne
     * couvre que lui-même.
     *
     * @return int[]
     */
    public static function job_group_ids(int $job_id): array
    {
        return self::JOB_UNITS[$job_id][1] ?? [$job_id];
    }

    /**
     * Groupe représentatif du service auquel appartient un métier : lui-même,
     * sauf s'il est absorbé par une unité.
     */
    public static function unit_representative(int $group_id): int
    {
        foreach (self::JOB_UNITS as $representative => [, $group_ids]) {
            if (in_array($group_id, $group_ids, true)) {
                return $representative;
            }
        }

        return $group_id;
    }

    /**
     * Intitulé d'un métier sélectionnable.
     */
    public static function job_title(int $job_id): string
    {
        return self::JOB_UNITS[$job_id][0] ?? (self::METIERS[$job_id][0] ?? '');
    }

    /**
     * Tous les métiers pouvant recevoir des points, chefs exclus : les points
     * récompensent le travail dans un métier, et le groupe « chef » n'est pas
     * un métier distinct. Les métiers absorbés par une unité n'apparaissent
     * pas séparément, ils sont récompensés via leur service.
     *
     * @return array<int, string> group_id représentatif => intitulé
     */
    public static function all_jobs(): array
    {
        $absorbed = [];
        foreach (self::JOB_UNITS as $representative => [, $group_ids]) {
            foreach ($group_ids as $group_id) {
                if ($group_id !== $representative) {
                    $absorbed[$group_id] = true;
                }
            }
        }

        $jobs = [];
        foreach (self::METIERS as $group_id => [, , , $chief]) {
            if (!$chief && !isset($absorbed[$group_id])) {
                $jobs[$group_id] = self::job_title($group_id);
            }
        }

        return $jobs;
    }

    /**
     * Badges applicables à un membre à partir de ses secondary_group_ids
     * (tableau ou chaîne CSV, comme extract_troop/extract_bordee/...).
     *
     * @return array<int, array{title:string, abbr:string, color:string, chief:bool}>
     */
    public static function badges_for_groups($secondary_group_ids): array
    {
        if (is_string($secondary_group_ids)) {
            $secondary_group_ids = explode(',', $secondary_group_ids);
        }

        $badges = [];
        foreach (self::METIERS as $group_id => [$title, $abbr, $color, $chief]) {
            if (in_array($group_id, $secondary_group_ids)) {
                $badges[] = ['title' => $title, 'abbr' => $abbr, 'color' => $color, 'chief' => $chief];
            }
        }

        return $badges;
    }
}
