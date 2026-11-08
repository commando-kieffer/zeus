<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Bibliothèque de documents (table `document`, voir sql/documents.sql).
 *
 * Les dates sont enregistrées en UTC ; leur conversion en heure de Paris se
 * fait à l'affichage (format_utc_date_fr()).
 */
class DocumentModel extends Model
{
    public function __construct()
    {
        parent::__construct();
        $this->table = "-";
    }

    private function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    /**
     * Tous les documents, par ordre alphabétique du titre.
     */
    public function get_all_documents(): array
    {
        $query = "SELECT id, title, file_name, file_mime, file_size, created_at, updated_at
                  FROM document
                  ORDER BY title ASC, id ASC";

        return $this->db->query($query)->getResult();
    }

    public function get_document(int $id): ?object
    {
        $query = "SELECT id, title, file_name, file_path, file_mime, file_size,
                         preview_path, preview_mime, created_at, updated_at
                  FROM document
                  WHERE id = ?";

        return $this->db->query($query, [$id])->getRow();
    }

    /**
     * @param array{name: string, path: string, mime: string, size: int} $file
     * @param array{path: string, mime: string}                          $preview
     *
     * @return int identifiant du document créé
     */
    public function create_document(string $title, array $file, array $preview): int
    {
        $now = $this->now();

        $query = "INSERT INTO document
                      (title, file_name, file_path, file_mime, file_size, preview_path, preview_mime, created_at, updated_at)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $this->db->query($query, [
            $title,
            $file['name'],
            $file['path'],
            $file['mime'],
            $file['size'],
            $preview['path'],
            $preview['mime'],
            $now,
            $now,
        ]);

        return (int) $this->db->insertID();
    }

    /**
     * Met à jour un document. $file et $preview valent null quand le fichier
     * correspondant n'est pas remplacé : ses colonnes ne sont alors pas touchées.
     * La date de dernière modification est renouvelée dans tous les cas.
     *
     * @param array{name: string, path: string, mime: string, size: int}|null $file
     * @param array{path: string, mime: string}|null                          $preview
     */
    public function update_document(int $id, string $title, ?array $file, ?array $preview): void
    {
        $columns = ['title = ?', 'updated_at = ?'];
        $values  = [$title, $this->now()];

        if ($file !== null) {
            array_push($columns, 'file_name = ?', 'file_path = ?', 'file_mime = ?', 'file_size = ?');
            array_push($values, $file['name'], $file['path'], $file['mime'], $file['size']);
        }

        if ($preview !== null) {
            array_push($columns, 'preview_path = ?', 'preview_mime = ?');
            array_push($values, $preview['path'], $preview['mime']);
        }

        $values[] = $id;

        $this->db->query('UPDATE document SET ' . implode(', ', $columns) . ' WHERE id = ?', $values);
    }

    public function delete_document(int $id): void
    {
        $this->db->query("DELETE FROM document WHERE id = ?", [$id]);
    }
}
