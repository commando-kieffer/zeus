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
