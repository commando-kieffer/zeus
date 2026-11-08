<?php

/**
 * Fonctions pures de la bibliothèque de documents (/documents) : mise en forme
 * des tailles et des types, nettoyage des noms de fichiers fournis par le
 * navigateur, en-têtes de téléchargement, limites d'envoi de PHP.
 *
 * Aucune dépendance au framework, de façon à les tester sans base de données.
 */

/**
 * Types servis directement dans le navigateur (en-tête « inline »). Tout le
 * reste est forcé en téléchargement : un document HTML ou SVG envoyé par un
 * membre pourrait sinon exécuter du script depuis l'origine de l'application
 * et agir au nom du membre qui l'ouvre.
 */
const DOCUMENT_INLINE_MIME_TYPES = [
    'application/pdf',
    'image/png',
    'image/jpeg',
    'image/gif',
    'image/webp',
];

/** Longueur maximale d'un nom de fichier conservé, alignée sur le schéma SQL. */
const DOCUMENT_FILE_NAME_MAX_LENGTH = 255;

if (!function_exists('document_format_size')) {
    /**
     * Taille lisible, avec les unités françaises : 512 o, 1,5 Ko, 150 Ko, 2 Mo.
     */
    function document_format_size(int $bytes): string
    {
        $units = ['o', 'Ko', 'Mo', 'Go'];
        $value = (float) max(0, $bytes);
        $unit  = 0;

        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }

        if ($unit === 0) {
            return (int) $value . ' o';
        }

        // Une décimale, sans « ,0 » inutile (2 Mo et non 2,0 Mo).
        $formatted = rtrim(rtrim(number_format($value, 1, ',', ''), '0'), ',');

        return $formatted . ' ' . $units[$unit];
    }
}

if (!function_exists('document_file_label')) {
    /**
     * Pastille indiquant le type de fichier d'après son extension : PDF, ZIP...
     * « FICHIER » quand il n'y en a pas, ou quand elle n'a pas l'allure d'une
     * extension (l'étiquette doit rester courte pour tenir sur la vignette).
     */
    function document_file_label(string $file_name): string
    {
        $extension = strtoupper(pathinfo($file_name, PATHINFO_EXTENSION));

        return preg_match('/^[A-Z0-9]{1,5}$/', $extension) === 1 ? $extension : 'FICHIER';
    }
}

if (!function_exists('document_is_inline_mime')) {
    /**
     * Vrai si ce type MIME peut être affiché dans le navigateur plutôt que
     * téléchargé (voir DOCUMENT_INLINE_MIME_TYPES).
     */
    function document_is_inline_mime(string $mime): bool
    {
        return in_array(strtolower($mime), DOCUMENT_INLINE_MIME_TYPES, true);
    }
}

if (!function_exists('document_clean_file_name')) {
    /**
     * Nettoie un nom de fichier fourni par le navigateur avant de le conserver.
     *
     * Le nom n'est jamais utilisé comme chemin (les fichiers sont stockés sous
     * un nom aléatoire) ; il ne sert qu'à l'affichage et au téléchargement. On
     * retire donc ce qui poserait problème à cet endroit : chemin d'accès,
     * caractères de contrôle (dont les retours à la ligne, qui permettraient
     * d'injecter un en-tête HTTP), octets UTF-8 invalides.
     */
    function document_clean_file_name(string $name): string
    {
        $name = mb_scrub($name);
        $name = basename(str_replace('\\', '/', $name));
        $name = trim((string) preg_replace('/[\x00-\x1F\x7F]/u', '', $name));

        // « . » et « .. » sont des noms de répertoires, pas de fichiers.
        if ($name === '' || $name === '.' || $name === '..') {
            return 'document';
        }

        if (mb_strlen($name) > DOCUMENT_FILE_NAME_MAX_LENGTH) {
            // On sacrifie le début du nom pour garder l'extension : c'est elle
            // qui dit au système quoi faire du fichier téléchargé.
            $extension = pathinfo($name, PATHINFO_EXTENSION);
            $suffix    = ($extension !== '' && mb_strlen($extension) <= 16) ? '.' . $extension : '';
            $name      = mb_substr($name, 0, DOCUMENT_FILE_NAME_MAX_LENGTH - mb_strlen($suffix)) . $suffix;
        }

        return $name;
    }
}

if (!function_exists('document_content_disposition')) {
    /**
     * Valeur de l'en-tête Content-Disposition pour un nom de fichier quelconque.
     *
     * Deux formes sont émises : filename (repli ASCII, avec les caractères qui
     * casseraient la chaîne entre guillemets remplacés) et filename* (RFC 6266,
     * UTF-8), que les navigateurs récents préfèrent, pour que « Livret
     * éclaireur.pdf » garde ses accents.
     *
     * @param string $type « inline » ou « attachment »
     */
    function document_content_disposition(string $type, string $file_name): string
    {
        $type      = $type === 'inline' ? 'inline' : 'attachment';
        $file_name = mb_scrub($file_name);
        // Modificateur u : un caractère accentué donne un seul « _ », pas un par octet.
        $fallback  = (string) preg_replace('/[^\x20-\x7E]|["\\\\%]/u', '_', $file_name);

        return sprintf('%s; filename="%s"; filename*=UTF-8\'\'%s', $type, $fallback, rawurlencode($file_name));
    }
}

if (!function_exists('document_parse_ini_size')) {
    /**
     * Convertit une taille de php.ini (« 2M », « 512K », « 1G », « 8388608 »)
     * en octets. Une valeur illisible, 0 ou négative (-1 : illimité) donne un
     * résultat nul ou négatif, que document_effective_upload_limit() lit comme
     * « pas de limite ».
     */
    function document_parse_ini_size(string $value): int
    {
        $value = trim($value);

        if ($value === '' || !preg_match('/^(-?\d+)\s*([kmg]?)b?$/i', $value, $matches)) {
            return 0;
        }

        $number = (int) $matches[1];

        return match (strtolower($matches[2])) {
            'g'     => $number * 1024 ** 3,
            'm'     => $number * 1024 ** 2,
            'k'     => $number * 1024,
            default => $number,
        };
    }
}

if (!function_exists('document_effective_upload_limit')) {
    /**
     * Taille maximale réellement acceptable pour un fichier envoyé avec un
     * formulaire, en octets : la plus petite des limites qui s'appliquent.
     *
     * @param int $app_limit        limite voulue par l'application
     * @param int $upload_max       upload_max_filesize de PHP (0 ou moins : illimité)
     * @param int $post_max         post_max_size de PHP (0 ou moins : illimité)
     * @param int $reserved_in_post octets réservés au reste de la requête (l'autre fichier du formulaire)
     */
    function document_effective_upload_limit(int $app_limit, int $upload_max, int $post_max, int $reserved_in_post = 0): int
    {
        $limits = [$app_limit];

        if ($upload_max > 0) {
            $limits[] = $upload_max;
        }

        if ($post_max > 0) {
            // L'envoi complet (tous les fichiers + champs) doit tenir dans
            // post_max_size, sinon PHP vide $_POST et $_FILES sans prévenir.
            $limits[] = max(0, $post_max - $reserved_in_post);
        }

        return min($limits);
    }
}
