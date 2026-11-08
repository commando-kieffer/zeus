<?php

namespace App\Libraries;

use CodeIgniter\HTTP\Files\UploadedFile;
use RuntimeException;

/**
 * Stockage sur disque des fichiers de la bibliothèque de documents.
 *
 * Les fichiers vivent dans writable/, hors de la racine web : ils ne sont donc
 * atteignables que par le contrôleur Documents, qui vérifie la connexion. Ils
 * sont enregistrés sous un nom aléatoire de 32 caractères hexadécimaux, sans
 * extension ni rapport avec le nom d'origine : rien de ce que le navigateur
 * envoie n'entre dans un chemin, et deux envois ne peuvent pas se télescoper.
 */
class DocumentStorage
{
    /** Forme des noms de fichiers stockés (voir store()). */
    private const STORED_NAME_PATTERN = '/^[0-9a-f]{32}$/';

    private string $directory;

    public function __construct(?string $directory = null)
    {
        $this->directory = rtrim($directory ?? WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'documents', '/\\');
    }

    /**
     * Déplace un fichier envoyé dans le stockage et renvoie son nom stocké.
     *
     * @throws RuntimeException si le dossier ne peut pas être créé ou le fichier déplacé
     */
    public function store(UploadedFile $file): string
    {
        // 0755 plutôt que le 0777 que prendrait UploadedFile::move() en créant
        // lui-même le dossier : le serveur web seul doit pouvoir y écrire.
        if (!is_dir($this->directory) && !mkdir($this->directory, 0755, true) && !is_dir($this->directory)) {
            throw new RuntimeException('Impossible de créer le dossier des documents.');
        }

        $stored_name = bin2hex(random_bytes(16));

        try {
            $file->move($this->directory, $stored_name);
        } catch (\Throwable $e) {
            throw new RuntimeException("Impossible d'enregistrer le fichier : " . $e->getMessage(), 0, $e);
        }

        return $stored_name;
    }

    /**
     * Chemin complet d'un fichier stocké, ou null s'il n'existe pas ou si le
     * nom n'a pas la forme produite par store().
     *
     * Le contrôle du nom protège contre une valeur altérée en base : elle ne
     * pourrait jamais désigner un fichier hors du dossier des documents.
     */
    public function path(?string $stored_name): ?string
    {
        if (!$this->is_valid_name($stored_name)) {
            return null;
        }

        $path = $this->directory . DIRECTORY_SEPARATOR . $stored_name;

        return is_file($path) ? $path : null;
    }

    /**
     * Supprime un fichier stocké. Sans effet si le nom est invalide ou si le
     * fichier a déjà disparu : appelé en nettoyage, il ne doit jamais faire
     * échouer l'opération qui l'a déclenché.
     */
    public function delete(?string $stored_name): void
    {
        $path = $this->path($stored_name);

        if ($path !== null && !@unlink($path)) {
            log_message('error', 'Documents : suppression impossible de ' . $stored_name);
        }
    }

    public function is_valid_name(?string $stored_name): bool
    {
        return $stored_name !== null && preg_match(self::STORED_NAME_PATTERN, $stored_name) === 1;
    }
}
