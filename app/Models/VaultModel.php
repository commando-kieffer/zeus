<?php

namespace App\Models;

use CodeIgniter\Model;
use Config\Database;

/**
 * Accès à la base commandokieffer_vault.
 *
 * Toutes les colonnes binaires sont lues avec HEX() et écrites avec UNHEX() :
 * aucun octet brut ne traverse la couche d'échappement de CodeIgniter. Ce
 * n'est pas de la superstition — remove_invisible_characters(), employé par
 * l'échappement générique du framework, supprime purement et simplement les
 * octets de contrôle, ce qui tronquerait silencieusement une clé ou un nonce.
 * Un chiffrement dont la clé a été amputée sans bruit est exactement le genre
 * de panne qu'on ne veut pas avoir à diagnostiquer plus tard.
 */
class VaultModel extends Model
{
    protected $DBGroup = 'vault';

    /** Échecs consécutifs tolérés depuis une même IP avant blocage. */
    public const MAX_FAILURES = 3;

    /** Plancher d'itérations PBKDF2 accepté à l'enrôlement (recommandation OWASP). */
    public const KDF_MIN_ITERATIONS = 600000;

    public function __construct()
    {
        parent::__construct();
        $this->table = '-';
    }

    // -----------------------------------------------------------------
    //  Enrôlement
    // -----------------------------------------------------------------

    public function is_enrolled(int $user_id): bool
    {
        $query = 'SELECT 1 FROM `vault_user` WHERE `user_id` = ?';

        return $this->db->query($query, [$user_id])->getResult() !== [];
    }

    /**
     * Matériel de clé d'un membre, clé privée chiffrée comprise.
     *
     * À N'APPELER QUE POUR LE PROPRIÉTAIRE. private_key_enc est opaque pour
     * le serveur, mais le servir à quelqu'un d'autre offrirait à celui-ci une
     * cible d'attaque hors ligne contre le mot de passe personnel du membre.
     */
    public function get_own_key_material(int $user_id): ?object
    {
        $query = 'SELECT `user_id`,
                         HEX(`public_key`)             AS `public_key`,
                         HEX(`private_key_enc`)        AS `private_key_enc`,
                         HEX(`private_key_nonce`)      AS `private_key_nonce`,
                         HEX(`sign_public_key`)        AS `sign_public_key`,
                         HEX(`sign_private_key_enc`)   AS `sign_private_key_enc`,
                         HEX(`sign_private_key_nonce`) AS `sign_private_key_nonce`,
                         HEX(`kdf_salt`)               AS `kdf_salt`,
                         `kdf_iterations`,
                         `version`
                  FROM `vault_user`
                  WHERE `user_id` = ?';

        return $this->db->query($query, [$user_id])->getResult()[0] ?? null;
    }

    /**
     * Clé publique de SIGNATURE d'un membre, en hexadécimal.
     *
     * C'est avec celle du Commandant que le serveur contrôle la validité des
     * autorisations. Elle est publique : la servir ne présente aucun risque.
     */
    public function get_sign_public_key(int $user_id): ?string
    {
        $query = 'SELECT HEX(`sign_public_key`) AS `sign_public_key` FROM `vault_user` WHERE `user_id` = ?';
        $row   = $this->db->query($query, [$user_id])->getResult()[0] ?? null;

        return $row === null ? null : $row->sign_public_key;
    }

    /**
     * Clés publiques des membres enrôlés parmi ceux demandés.
     *
     * @return array<int, string> user_id => clé publique en hexadécimal
     */
    public function get_public_keys(array $user_ids): array
    {
        $user_ids = array_values(array_unique(array_map('intval', $user_ids)));
        if ($user_ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($user_ids), '?'));
        $query        = "SELECT `user_id`, HEX(`public_key`) AS `public_key`
                         FROM `vault_user`
                         WHERE `user_id` IN ($placeholders)";

        $keys = [];
        foreach ($this->db->query($query, $user_ids)->getResult() as $row) {
            $keys[(int) $row->user_id] = $row->public_key;
        }

        return $keys;
    }

    /**
     * Enregistre (ou remplace) la paire de clés d'un membre.
     *
     * En cas de réenrôlement — mot de passe personnel oublié — l'ancienne
     * paire disparaît, et avec elle la capacité d'ouvrir les DEK qui lui
     * avaient été encapsulées. Ces lignes entry_key devenues inexploitables
     * sont donc supprimées : les garder laisserait croire à un accès qui
     * n'existe plus. Les entrées concernées doivent être repartagées.
     *
     * @return int nombre d'accès invalidés par un éventuel réenrôlement
     */
    public function enrol(
        int $user_id,
        string $public_key_hex,
        string $private_key_enc_hex,
        string $private_key_nonce_hex,
        string $sign_public_key_hex,
        string $sign_private_key_enc_hex,
        string $sign_private_key_nonce_hex,
        string $kdf_salt_hex,
        int $kdf_iterations
    ): int {
        $revoked = 0;

        if ($this->is_enrolled($user_id)) {
            $this->db->query('DELETE FROM `entry_key` WHERE `user_id` = ?', [$user_id]);
            $revoked = $this->db->affectedRows();
        }

        $query = 'INSERT INTO `vault_user`
                      (`user_id`, `public_key`, `private_key_enc`, `private_key_nonce`,
                       `sign_public_key`, `sign_private_key_enc`, `sign_private_key_nonce`,
                       `kdf_salt`, `kdf_iterations`, `version`, `enrolled_at`)
                  VALUES (?, UNHEX(?), UNHEX(?), UNHEX(?), UNHEX(?), UNHEX(?), UNHEX(?), UNHEX(?), ?, 1, ?)
                  ON DUPLICATE KEY UPDATE
                      `public_key`             = UNHEX(?),
                      `private_key_enc`        = UNHEX(?),
                      `private_key_nonce`      = UNHEX(?),
                      `sign_public_key`        = UNHEX(?),
                      `sign_private_key_enc`   = UNHEX(?),
                      `sign_private_key_nonce` = UNHEX(?),
                      `kdf_salt`               = UNHEX(?),
                      `kdf_iterations`         = ?,
                      `enrolled_at`            = ?';

        $now = date('Y-m-d H:i:s');

        $this->db->query($query, [
            $user_id,
            $public_key_hex, $private_key_enc_hex, $private_key_nonce_hex,
            $sign_public_key_hex, $sign_private_key_enc_hex, $sign_private_key_nonce_hex,
            $kdf_salt_hex, $kdf_iterations, $now,
            $public_key_hex, $private_key_enc_hex, $private_key_nonce_hex,
            $sign_public_key_hex, $sign_private_key_enc_hex, $sign_private_key_nonce_hex,
            $kdf_salt_hex, $kdf_iterations, $now,
        ]);

        return $revoked;
    }

    // -----------------------------------------------------------------
    //  Entrées
    // -----------------------------------------------------------------

    /**
     * Toutes les entrées, avec le drapeau d'autorisation du membre courant.
     *
     * Les entrées non autorisées restent listées — nom et date seulement.
     * Aucun élément chiffré n'est renvoyé ici : la liste ne sert qu'à
     * l'affichage, le déchiffrement passe par get_entry_for_user().
     */
    public function list_entries(int $user_id): array
    {
        $query = 'SELECT e.`id`,
                         e.`name`,
                         e.`creation_date`,
                         e.`created_by`,
                         (ek.`user_id` IS NOT NULL) AS `authorized`
                  FROM `entry` e
                  LEFT JOIN `entry_key` ek
                         ON ek.`entry_id` = e.`id` AND ek.`user_id` = ?
                  ORDER BY e.`name` ASC';

        return $this->db->query($query, [$user_id])->getResult();
    }

    /**
     * Entrée chiffrée accompagnée de la DEK encapsulée pour ce membre.
     *
     * La jointure INTERNE sur entry_key porte le contrôle d'accès : pas de
     * ligne, pas de résultat. Vérification et lecture sont donc une seule et
     * même opération, impossible à désynchroniser par une évolution du code.
     */
    public function get_entry_for_user(string $entry_id, int $user_id): ?object
    {
        $query = 'SELECT e.`id`,
                         e.`name`,
                         HEX(e.`nonce`)        AS `nonce`,
                         HEX(e.`server_nonce`) AS `server_nonce`,
                         HEX(e.`server_tag`)   AS `server_tag`,
                         HEX(e.`cipher`)       AS `cipher`,
                         e.`version`,
                         HEX(ek.`wrapped_dek`) AS `wrapped_dek`,
                         HEX(ek.`signature`)   AS `signature`,
                         ek.`signed_by`
                  FROM `entry` e
                  INNER JOIN `entry_key` ek
                          ON ek.`entry_id` = e.`id` AND ek.`user_id` = ?
                  WHERE e.`id` = ?';

        return $this->db->query($query, [$user_id, $entry_id])->getResult()[0] ?? null;
    }

    public function entry_exists(string $entry_id): bool
    {
        $query = 'SELECT 1 FROM `entry` WHERE `id` = ?';

        return $this->db->query($query, [$entry_id])->getResult() !== [];
    }

    /**
     * Crée une entrée et les accès associés, de façon atomique : une entrée
     * dont les clés encapsulées manqueraient serait définitivement illisible.
     *
     * @param array<int, array{wrapped_dek: string, signature: string}> $grants user_id => éléments (hex)
     */
    public function create_entry(
        string $entry_id,
        string $name,
        string $nonce_hex,
        string $server_nonce_hex,
        string $server_tag_hex,
        string $cipher_hex,
        int $created_by,
        array $grants,
        int $signed_by
    ): bool {
        $now = date('Y-m-d H:i:s');

        $this->db->transBegin();

        $this->db->query(
            'INSERT INTO `entry`
                 (`id`, `name`, `nonce`, `server_nonce`, `server_tag`, `cipher`,
                  `version`, `created_by`, `creation_date`)
             VALUES (?, ?, UNHEX(?), UNHEX(?), UNHEX(?), UNHEX(?), 1, ?, ?)',
            [$entry_id, $name, $nonce_hex, $server_nonce_hex, $server_tag_hex, $cipher_hex, $created_by, $now]
        );

        foreach ($grants as $user_id => $grant) {
            $this->db->query(
                'INSERT INTO `entry_key` (`entry_id`, `user_id`, `wrapped_dek`, `signature`, `signed_by`, `created_at`)
                 VALUES (?, ?, UNHEX(?), UNHEX(?), ?, ?)',
                [$entry_id, (int) $user_id, $grant['wrapped_dek'], $grant['signature'], $signed_by, $now]
            );
        }

        if ($this->db->transStatus() === false) {
            $this->db->transRollback();

            return false;
        }

        $this->db->transCommit();

        return true;
    }

    /**
     * Membres ayant accès à une entrée.
     *
     * @return list<int>
     */
    public function get_authorized_user_ids(string $entry_id): array
    {
        $query = 'SELECT `user_id` FROM `entry_key` WHERE `entry_id` = ?';

        return array_map(
            static fn($row) => (int) $row->user_id,
            $this->db->query($query, [$entry_id])->getResult()
        );
    }

    /**
     * Accès de toutes les entrées, pour l'écran d'administration du Commandant.
     *
     * @return array<string, list<int>> entry_id => user_id autorisés
     */
    public function get_all_authorizations(): array
    {
        $map = [];
        foreach ($this->db->query('SELECT `entry_id`, `user_id` FROM `entry_key`')->getResult() as $row) {
            $map[$row->entry_id][] = (int) $row->user_id;
        }

        return $map;
    }

    /**
     * Remplace le contenu chiffré d'une entrée et la totalité de ses accès.
     *
     * Toute modification renouvelle la DEK, donc réécrit chaque encapsulation.
     * Ce n'est pas une facilité d'implémentation mais l'essentiel du mécanisme :
     * sans renouvellement, retirer un accès n'aurait aucun effet rétroactif —
     * l'intéressé aurait pu conserver son ancienne ligne entry_key, qui
     * resterait valable. En changeant de DEK, l'ancienne encapsulation ne
     * déchiffre plus rien.
     *
     * Le nom de l'entrée n'est jamais modifié : il est lié en AAD de la couche
     * client, le changer rendrait l'entrée indéchiffrable.
     *
     * @param array<int, array{wrapped_dek: string, signature: string}> $grants user_id => éléments (hex)
     */
    public function update_entry(
        string $entry_id,
        string $nonce_hex,
        string $server_nonce_hex,
        string $server_tag_hex,
        string $cipher_hex,
        array $grants,
        int $signed_by
    ): bool {
        $now = date('Y-m-d H:i:s');

        $this->db->transBegin();

        $this->db->query(
            'UPDATE `entry`
                SET `nonce`        = UNHEX(?),
                    `server_nonce` = UNHEX(?),
                    `server_tag`   = UNHEX(?),
                    `cipher`       = UNHEX(?)
              WHERE `id` = ?',
            [$nonce_hex, $server_nonce_hex, $server_tag_hex, $cipher_hex, $entry_id]
        );

        $this->db->query('DELETE FROM `entry_key` WHERE `entry_id` = ?', [$entry_id]);

        foreach ($grants as $user_id => $grant) {
            $this->db->query(
                'INSERT INTO `entry_key` (`entry_id`, `user_id`, `wrapped_dek`, `signature`, `signed_by`, `created_at`)
                 VALUES (?, ?, UNHEX(?), UNHEX(?), ?, ?)',
                [$entry_id, (int) $user_id, $grant['wrapped_dek'], $grant['signature'], $signed_by, $now]
            );
        }

        if ($this->db->transStatus() === false) {
            $this->db->transRollback();

            return false;
        }

        $this->db->transCommit();

        return true;
    }

    // -----------------------------------------------------------------
    //  Annuaire (base principale)
    // -----------------------------------------------------------------

    /**
     * État-major, depuis commandokieffer_database.
     *
     * Connexion explicite au groupe 'default' : ck_vault n'a — volontairement
     * — aucun droit sur la base du forum, les deux mondes ne peuvent donc pas
     * être joints en SQL. Le recoupement se fait en PHP, dans le contrôleur.
     */
    /**
     * Le Commandant en fonction, d'après xf_user.
     *
     * Sa clé publique de signature est la référence qui valide toutes les
     * autorisations. La fonction est donc lue à chaque contrôle, jamais mise
     * en cache ni recopiée dans la base du coffre : une rétrogradation doit
     * prendre effet immédiatement.
     */
    public function get_commandant(int $group_id): ?object
    {
        $query = 'SELECT `user_id`, `username` FROM `xf_user` WHERE `user_group_id` = ? ORDER BY `user_id` ASC LIMIT 1';

        return Database::connect('default')->query($query, [$group_id])->getResult()[0] ?? null;
    }

    public function get_team_leaders(): array
    {
        $query = 'SELECT `user_id`, `username`, `user_group_id`
                  FROM `xf_user`
                  WHERE (`user_group_id` BETWEEN 10 AND 20) OR `user_group_id` = 50
                  ORDER BY `username` ASC';

        return Database::connect('default')->query($query)->getResult();
    }

    // -----------------------------------------------------------------
    //  Journal
    // -----------------------------------------------------------------

    public function log(
        ?int $user_id,
        ?string $entry_id,
        string $action,
        bool $success,
        string $ip,
        ?string $detail = null
    ): void {
        $query = 'INSERT INTO `vault_logs`
                      (`user_id`, `entry_id`, `action`, `success`, `ip`, `detail`, `created_at`)
                  VALUES (?, ?, ?, ?, ?, ?, ?)';

        $this->db->query($query, [
            $user_id,
            $entry_id,
            $action,
            $success ? 1 : 0,
            substr($ip, 0, 45),
            $detail === null ? null : substr($detail, 0, 255),
            date('Y-m-d H:i:s'),
        ]);
    }

    // -----------------------------------------------------------------
    //  Liste noire
    // -----------------------------------------------------------------

    public function is_blacklisted(string $ip): bool
    {
        $query = 'SELECT `blocked` FROM `vault_blacklist` WHERE `ip` = ?';
        $row   = $this->db->query($query, [substr($ip, 0, 45)])->getResult()[0] ?? null;

        return $row !== null && (int) $row->blocked === 1;
    }

    /**
     * Comptabilise un échec et bloque l'IP au MAX_FAILURES-ième consécutif.
     *
     * Lecture puis écriture plutôt qu'un INSERT ... ON DUPLICATE KEY UPDATE
     * astucieux : deux échecs simultanés peuvent fausser le compteur d'une
     * unité, ce qui est sans conséquence ici, alors qu'un ordre d'évaluation
     * subtil dans la clause UPDATE serait une source de bugs durable.
     *
     * @return bool true si l'IP vient d'être bloquée
     */
    public function register_failure(string $ip): bool
    {
        $ip  = substr($ip, 0, 45);
        $now = date('Y-m-d H:i:s');
        $row = $this->db->query(
            'SELECT `failure_count`, `blocked` FROM `vault_blacklist` WHERE `ip` = ?',
            [$ip]
        )->getResult()[0] ?? null;

        if ($row === null) {
            $count = 1;
            $this->db->query(
                'INSERT INTO `vault_blacklist`
                     (`ip`, `failure_count`, `blocked`, `first_failure_at`, `last_failure_at`, `blocked_at`)
                 VALUES (?, 1, ?, ?, ?, ?)',
                [$ip, $count >= self::MAX_FAILURES ? 1 : 0, $now, $now, $count >= self::MAX_FAILURES ? $now : null]
            );
        } else {
            $count = (int) $row->failure_count + 1;
            $block = $count >= self::MAX_FAILURES;

            $this->db->query(
                'UPDATE `vault_blacklist`
                    SET `failure_count`   = ?,
                        `blocked`         = ?,
                        `last_failure_at` = ?,
                        `blocked_at`      = COALESCE(`blocked_at`, ?)
                  WHERE `ip` = ?',
                [$count, $block || (int) $row->blocked === 1 ? 1 : 0, $now, $block ? $now : null, $ip]
            );
        }

        return $count >= self::MAX_FAILURES;
    }

    public function reset_failures(string $ip): void
    {
        $this->db->query(
            'UPDATE `vault_blacklist` SET `failure_count` = 0 WHERE `ip` = ? AND `blocked` = 0',
            [substr($ip, 0, 45)]
        );
    }
}
