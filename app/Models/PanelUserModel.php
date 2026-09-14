<?php

namespace App\Models;

use CodeIgniter\Model;

class PanelUserModel extends Model
{
    /**
     * Plateformes de jeu autorisées (valeurs de la colonne ENUM panel_user.platform).
     */
    public const PLATFORMS = ['PC', 'PlayStation', 'Xbox'];

    /**
     * Icône (public/pictures/icons) affichée pour chaque plateforme.
     */
    public const PLATFORM_ICONS = [
        'PC' => 'pc.png',
        'PlayStation' => 'ps.png',
        'Xbox' => 'xbox.jpg',
    ];

    public function __construct()
    {
        parent::__construct();
        $this->table = "-";
    }

    public function get_by_user_id($user_id)
    {
        $query = "SELECT user_id, platform_username, platform FROM panel_user WHERE user_id = ?";
        $result = $this->db->query($query, array($user_id));
        return $result->getResult()[0] ?? null;
    }

    /**
     * Pseudo in-game et plateforme d'un ensemble de membres, en une seule
     * requête : user_id => objet {platform_username, platform}.
     */
    public function get_by_user_ids(array $user_ids): array
    {
        if (empty($user_ids)) return [];

        $placeholders = implode(',', array_fill(0, count($user_ids), '?'));
        $query = "SELECT user_id, platform_username, platform FROM panel_user WHERE user_id IN ($placeholders)";
        $result = $this->db->query($query, $user_ids);

        $by_user = [];
        foreach ($result->getResult() as $row) {
            $by_user[(int) $row->user_id] = $row;
        }

        return $by_user;
    }

    /**
     * Crée ou met à jour le pseudo in-game et la plateforme d'un membre
     * (utilisé par la modale "Modifier mes informations" du profil).
     */
    public function upsert($user_id, string $platform_username, string $platform): void
    {
        $query = "INSERT INTO panel_user (user_id, platform_username, platform) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE platform_username = VALUES(platform_username), platform = VALUES(platform)";
        $this->db->query($query, array($user_id, $platform_username, $platform));
    }
}
