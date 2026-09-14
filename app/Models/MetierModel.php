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
        49 => ['Aide de camp', 'AC', '#009688', false],
        62 => ['Ambassadeur', 'AM', '#b8860b', false],
        66 => ['Consul', 'CO', '#3f51b5', false],
        72 => ['Cotisé', 'CT', '#e91e63', false],
        76 => ['Communication', 'CM', '#00bcd4', false],
        73 => ['Chef de la communication', 'CM', '#00bcd4', true],
    ];

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
