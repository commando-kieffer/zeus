<?php

/**
 * Découpage d'une période en intervalles (jour, semaine, mois) pour les
 * graphiques en courbe : page des statistiques et historique des présences
 * du profil.
 */

if (!function_exists('parse_period_date')) {
    /**
     * Date au format Y-m-d (et calendaire valide), sinon null. Accepte
     * n'importe quelle valeur brute de l'URL : un paramètre répété sous forme
     * de tableau (?start[]=x) est simplement invalide, pas une erreur de type.
     */
    function parse_period_date(mixed $date): ?string
    {
        if (!is_string($date)) {
            return null;
        }

        $parsed = \DateTime::createFromFormat('Y-m-d', $date);

        return ($parsed !== false && $parsed->format('Y-m-d') === $date) ? $date : null;
    }
}

if (!function_exists('parse_period_granularity')) {
    /**
     * Granularité demandée si elle fait partie des valeurs autorisées pour
     * ce graphique, sinon $default. $default est aussi ce qui s'applique
     * quand aucune granularité n'est fournie (une semaine plutôt qu'un jour :
     * un jour par point est trop fin dès que la période dépasse quelques
     * semaines).
     *
     * @param array<int, string> $allowed
     */
    function parse_period_granularity(mixed $granularity, array $allowed, string $default): string
    {
        return in_array($granularity, $allowed, true) ? $granularity : $default;
    }
}

if (!function_exists('build_period_buckets')) {
    /**
     * Liste ordonnée des clés de buckets (une par point de courbe, au format
     * Y-m-d) couvrant [start_date, end_date] pour la granularité donnée. Le
     * dernier bucket peut couvrir une plage plus courte si la période ne se
     * découpe pas en un nombre entier de points.
     *
     * "month" avance de mois calendaire en mois calendaire (une clé est le
     * 1er du mois) plutôt que par tranches de 30 jours : les mois n'ont pas
     * tous la même longueur, et des tranches fixes finiraient par dériver
     * des vrais débuts de mois.
     *
     * @return array<int, string>
     */
    function build_period_buckets(\DateTime $start_date, \DateTime $end_date, string $granularity): array
    {
        $buckets = [];

        if ($granularity === 'month') {
            $cursor = new \DateTime($start_date->format('Y-m-01'));
            $end_month = new \DateTime($end_date->format('Y-m-01'));
            while ($cursor <= $end_month) {
                $buckets[] = $cursor->format('Y-m-d');
                $cursor->modify('+1 month');
            }

            return $buckets;
        }

        $bucket_days = $granularity === 'week' ? 7 : 1;
        $cursor = clone $start_date;
        while ($cursor <= $end_date) {
            $buckets[] = $cursor->format('Y-m-d');
            $cursor->modify('+' . $bucket_days . ' days');
        }

        return $buckets;
    }
}

if (!function_exists('period_bucket_key')) {
    /**
     * Clé du bucket (parmi celles retournées par build_period_buckets pour
     * les mêmes $start_date/$granularity) auquel appartient $row_date.
     *
     * @param array<int, string> $buckets
     */
    function period_bucket_key(array $buckets, \DateTime $start_date, \DateTime $row_date, string $granularity): string
    {
        if ($granularity === 'month') {
            $months = ((int) $row_date->format('Y') - (int) $start_date->format('Y')) * 12
                + ((int) $row_date->format('n') - (int) $start_date->format('n'));
            $index = max(0, min($months, count($buckets) - 1));

            return $buckets[$index];
        }

        $bucket_days = $granularity === 'week' ? 7 : 1;
        // DateTime::diff()->days est une valeur absolue : une date avant le
        // début serait sinon rangée dans le dernier bucket au lieu du premier.
        $diff = $start_date->diff($row_date);
        $days_since_start = $diff->invert ? 0 : (int) $diff->days;
        $index = max(0, min(intdiv($days_since_start, $bucket_days), count($buckets) - 1));

        return $buckets[$index];
    }
}
