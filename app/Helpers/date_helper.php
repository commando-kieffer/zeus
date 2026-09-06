<?php

if (!function_exists('format_date_fr')) {
    /**
     * Formate une date brute de la base de données (ex. : 2026-07-12, format
     * ISO renvoyé par les colonnes DATE de MySQL) au format français
     * jour/mois/année (ex. : 12/07/2026).
     */
    function format_date_fr(string $date): string
    {
        return (new DateTime($date))->format('d/m/Y');
    }
}

if (!function_exists('is_operation_done')) {
    /**
     * Une opération est considérée comme terminée à partir de 21h (heure de
     * Paris) le jour de l'opération, quelle que soit l'heure du serveur.
     * C'est à partir de ce moment que le rapport de présence peut être rédigé.
     */
    function is_operation_done(string $operation_date): bool
    {
        $paris = new DateTimeZone('Europe/Paris');
        $done_at = new DateTime($operation_date . ' 21:00:00', $paris);
        $now = new DateTime('now', $paris);

        return $now >= $done_at;
    }
}

if (!function_exists('is_voting_closed')) {
    /**
     * Le vote sur le scénario d'une opération se ferme deux semaines après
     * l'opération, à 21h (heure de Paris) comme pour la disponibilité du
     * rapport de présence. Passé ce délai, un membre n'ayant pas encore noté
     * l'opération ne peut plus le faire.
     */
    function is_voting_closed(string $operation_date): bool
    {
        $paris = new DateTimeZone('Europe/Paris');
        $closes_at = (new DateTime($operation_date . ' 21:00:00', $paris))->modify('+14 days');
        $now = new DateTime('now', $paris);

        return $now >= $closes_at;
    }
}
