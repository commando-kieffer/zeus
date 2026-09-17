-- =====================================================================
--  Coffre-fort (Coffre) — création de la base, de l'utilisateur dédié
--  et des tables.
--
--  À exécuter une seule fois, avec un compte MySQL administrateur.
--
--  ATTENTION PRODUCTION : le mot de passe 'ck_vault' ci-dessous ('1234')
--  n'est valable que pour l'environnement de développement. En production,
--  remplacer par un mot de passe fort généré aléatoirement, par exemple :
--      openssl rand -base64 32
--  et le reporter dans le groupe de connexion 'vault' de
--  app/Config/Database.php (fichier non versionné, à éditer sur le serveur).
--
--
--  MODÈLE CRYPTOGRAPHIQUE : chiffrement par enveloppe.
--
--  Chaque entrée possède sa propre clé aléatoire de 256 bits (la DEK).
--  Le mot de passe est chiffré UNE fois sous cette DEK. La DEK est ensuite
--  encapsulée séparément pour chaque destinataire, sous SA clé publique.
--
--  L'autorisation devient donc cryptographique et non plus déclarative :
--  sans ligne dans `entry_key`, il n'existe aucune copie de la DEK qu'un
--  utilisateur puisse ouvrir, et une copie complète de la base n'y change
--  rien.
--
--  Chaque membre détient une paire de clés RSA-OAEP 3072 bits. La moitié
--  publique est stockée en clair (elle sert à partager une entrée avec lui
--  sans qu'il soit présent). La moitié privée est stockée chiffrée sous une
--  clé dérivée de SON mot de passe de coffre personnel, que le serveur ne
--  voit jamais.
--
--  Le serveur ne manipule aucun élément de clé : il applique uniquement une
--  seconde couche de chiffrement au repos avec .vault_key.
-- =====================================================================


-- ---------------------------------------------------------------------
--  1. Base de données
-- ---------------------------------------------------------------------

CREATE DATABASE IF NOT EXISTS `commandokieffer_vault`
    DEFAULT CHARACTER SET utf8mb4
    DEFAULT COLLATE utf8mb4_general_ci;

USE `commandokieffer_vault`;


-- ---------------------------------------------------------------------
--  2. Utilisateur dédié
--
--  Cloisonnement : ck_vault n'a aucun droit sur commandokieffer_database,
--  et aucun droit DDL (pas de CREATE / DROP / ALTER) sur sa propre base.
--  Une injection SQL passant par le coffre ne peut donc ni lire le forum,
--  ni modifier le schéma.
-- ---------------------------------------------------------------------

CREATE USER IF NOT EXISTS 'ck_vault'@'localhost' IDENTIFIED BY '1234';

GRANT SELECT, INSERT, UPDATE, DELETE
    ON `commandokieffer_vault`.*
    TO 'ck_vault'@'localhost';

FLUSH PRIVILEGES;


-- ---------------------------------------------------------------------
--  3. Enrôlement des membres
--
--  Une ligne par membre ayant créé sa paire de clés.
--
--  `public_key` est en clair : c'est ce qui permet de partager une entrée
--  avec quelqu'un sans son concours ni sa présence.
--
--  `private_key_enc` est la clé privée (format PKCS#8) chiffrée en
--  AES-256-GCM sous KEK = PBKDF2-SHA256(mot de passe personnel, kdf_salt,
--  kdf_iterations). Le tag GCM n'a pas de colonne dédiée : WebCrypto le
--  concatène déjà à la fin du chiffré. Le serveur ne peut pas ouvrir ce
--  blob, et ne doit JAMAIS le renvoyer à quelqu'un d'autre que son
--  propriétaire.
--
--  Un mot de passe personnel oublié est définitif : la clé privée est
--  irrécupérable. Le membre se réenrôle (nouvelle paire de clés) et les
--  entrées auxquelles il avait accès doivent lui être repartagées par
--  quelqu'un qui les détient encore.
-- ---------------------------------------------------------------------

--  Chaque membre possède DEUX paires de clés, protégées par le même mot de
--  passe personnel mais sans usage commun :
--    - une paire de CHIFFREMENT (RSA-OAEP), qui reçoit les DEK encapsulées ;
--    - une paire de SIGNATURE (RSASSA-PKCS1-v1_5), qui sert au Commandant à
--      attester qu'une autorisation vient bien de lui.
--  Les deux usages restent séparés parce qu'une clé WebCrypto est liée à son
--  algorithme : une clé RSA-OAEP ne peut pas signer, et inversement.

CREATE TABLE IF NOT EXISTS `vault_user` (
    `user_id`                INT UNSIGNED      NOT NULL COMMENT 'xf_user.user_id',
    `public_key`             VARBINARY(512)    NOT NULL COMMENT 'Cle publique RSA-OAEP 3072, format SPKI DER, en clair',
    `private_key_enc`        VARBINARY(2560)   NOT NULL COMMENT 'Cle privee RSA-OAEP PKCS8 chiffree AES-256-GCM (tag inclus)',
    `private_key_nonce`      VARBINARY(12)     NOT NULL COMMENT 'Nonce GCM de private_key_enc',
    `sign_public_key`        VARBINARY(512)    NOT NULL COMMENT 'Cle publique de signature RSA 3072, SPKI DER, en clair',
    `sign_private_key_enc`   VARBINARY(2560)   NOT NULL COMMENT 'Cle privee de signature PKCS8 chiffree AES-256-GCM',
    `sign_private_key_nonce` VARBINARY(12)     NOT NULL COMMENT 'Nonce GCM de sign_private_key_enc',
    `kdf_salt`               VARBINARY(32)     NOT NULL COMMENT 'Sel PBKDF2 propre au membre',
    `kdf_iterations`         INT UNSIGNED      NOT NULL COMMENT 'Iterations PBKDF2 utilisees a l enrolement',
    `version`                SMALLINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Version du schema cryptographique',
    `enrolled_at`            DATETIME          NOT NULL,
    PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ---------------------------------------------------------------------
--  4. Entrées du coffre
--
--  `cipher` contient DEUX couches superposées :
--    - couche interne, faite par le navigateur : AES-256-GCM sous la DEK
--      de l'entrée, nonce = `nonce`, AAD = nom de l'entree. Son tag GCM est
--      concatene par WebCrypto, donc deja inclus dans `cipher`.
--    - couche externe, faite par PHP : AES-256-GCM sous une cle derivee de
--      .vault_key, nonce = `server_nonce`, AAD = `id`. openssl_encrypt()
--      renvoie son tag separement, d'ou la colonne `server_tag`.
--
--  La couche externe protege contre une fuite de la seule base : sans
--  .vault_key, `cipher` est inexploitable meme pour un destinataire
--  legitime. Elle n'a aucun role dans le controle d'acces, qui est assure
--  par `entry_key`.
--
--  `name` est lie en AAD de la couche interne : il est donc immuable. Le
--  renommer invaliderait le dechiffrement. Cela garantit qu'un chiffre
--  deplace d'une entree a l'autre est detecte au lieu d'etre affiche sous
--  un mauvais libelle.
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `entry` (
    `id`            CHAR(32)          NOT NULL COMMENT '16 octets aleatoires en hex ; lie en AAD de la couche serveur',
    `name`          VARCHAR(128)      NOT NULL COMMENT 'Libelle affiche ; lie en AAD de la couche client, donc immuable',
    `nonce`         VARBINARY(12)     NOT NULL COMMENT 'Nonce GCM couche client, sous la DEK (CSPRNG navigateur)',
    `server_nonce`  VARBINARY(12)     NOT NULL COMMENT 'Nonce GCM couche serveur (random_bytes)',
    `server_tag`    VARBINARY(16)     NOT NULL COMMENT 'Tag d authentification GCM couche serveur',
    `cipher`        VARBINARY(2048)   NOT NULL COMMENT 'Chiffre sous la DEK, re-chiffre par le serveur',
    `version`       SMALLINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Version du schema cryptographique',
    `created_by`    INT UNSIGNED      NOT NULL COMMENT 'xf_user.user_id du createur',
    `creation_date` DATETIME          NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ---------------------------------------------------------------------
--  5. Clés encapsulées — remplace la table `authorization`
--
--  La présence d'une ligne EST l'autorisation, et elle est cryptographique :
--  `wrapped_dek` est la DEK de l'entrée chiffrée en RSA-OAEP vers la clé
--  publique de `user_id`. Sans ligne, il n'existe nulle part de copie de la
--  DEK que cet utilisateur puisse ouvrir.
--
--  `signature` atteste que l'autorisation émane bien du Commandant. Le
--  Commandant signe, dans son navigateur et avec sa clé privée de signature,
--  le message canonique :
--
--      ck-vault:grant:v1|<entry_id>|<user_id>|<wrapped_dek en hexadecimal>
--
--  Le serveur vérifie cette signature avec la clé publique de signature du
--  Commandant, à l'écriture ET à la lecture. La vérification à la LECTURE est
--  la plus importante : une ligne entry_key insérée par un autre chemin que
--  l'application — injection SQL, accès direct à la base — ne porterait pas de
--  signature valable et ne serait donc jamais servie.
--
--  Les trois champs signés sont exactement ceux qui définissent
--  l'autorisation : quelle entrée, pour qui, avec quelle clé. Aucun d'eux ne
--  peut être modifié sans invalider la signature, et une ligne ne peut pas
--  être déplacée vers une autre entrée ni réattribuée à un autre membre.
--
--  Retirer un accès = supprimer la ligne. Attention toutefois : cela
--  empêche les déchiffrements FUTURS, mais quelqu'un qui aurait déjà lu et
--  conservé le mot de passe le connaît toujours. Une révocation complète
--  suppose de changer le mot de passe concerné.
--
--  Pas de clé étrangère sur user_id : xf_user vit dans une autre base, à
--  laquelle ck_vault n'a volontairement aucun accès.
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `entry_key` (
    `entry_id`    CHAR(32)       NOT NULL,
    `user_id`     INT UNSIGNED   NOT NULL COMMENT 'xf_user.user_id du destinataire',
    `wrapped_dek` VARBINARY(512) NOT NULL COMMENT 'DEK chiffree RSA-OAEP vers la cle publique du destinataire',
    `signature`   VARBINARY(512) NOT NULL COMMENT 'Signature du Commandant sur (entry_id, user_id, wrapped_dek)',
    `signed_by`   INT UNSIGNED   NOT NULL COMMENT 'xf_user.user_id du Commandant signataire',
    `created_at`  DATETIME       NOT NULL,
    PRIMARY KEY (`entry_id`, `user_id`),
    KEY `idx_user` (`user_id`),
    CONSTRAINT `fk_entry_key_entry`
        FOREIGN KEY (`entry_id`) REFERENCES `entry` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ---------------------------------------------------------------------
--  6. Journal d'accès
--
--  Succès ET échecs. Pas de clé étrangère sur entry_id : le journal doit
--  survivre à la suppression d'une entrée.
--
--  L'action 'decrypt_failed' est DÉCLARATIVE : elle est signalée par le
--  navigateur quand le déchiffrement local échoue (mot de passe personnel
--  erroné). Un attaquant peut évidemment s'abstenir de la signaler — elle
--  sert à la traçabilité et au verrouillage après erreurs de saisie, pas
--  comme garantie de sécurité.
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `vault_logs` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED    NULL COMMENT 'xf_user.user_id, NULL si non authentifie',
    `entry_id`   CHAR(32)        NULL,
    `action`     ENUM('enrol', 'list', 'create', 'decrypt', 'decrypt_failed', 'share', 'revoke', 'denied', 'blocked') NOT NULL,
    `success`    TINYINT(1)      NOT NULL,
    `ip`         VARCHAR(45)     NOT NULL COMMENT 'IPv4 ou IPv6, en clair pour consultation directe',
    `detail`     VARCHAR(255)    NULL COMMENT 'Motif d echec le cas echeant',
    `created_at` DATETIME        NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_user_time` (`user_id`, `created_at`),
    KEY `idx_ip_time` (`ip`, `created_at`),
    KEY `idx_entry` (`entry_id`),
    KEY `idx_action` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ---------------------------------------------------------------------
--  7. Liste noire d'adresses IP
--
--  3 échecs CONSÉCUTIFS depuis une même IP la bloquent. Sont comptés :
--    - les refus d'autorisation (demande d'une entrée non autorisée,
--      identifiant d'entrée inconnu, requête malformée) ;
--    - les échecs de déchiffrement signalés par le navigateur.
--  Un déchiffrement réussi remet le compteur à zéro.
--
--  Déblocage manuel :
--      DELETE FROM `vault_blacklist` WHERE `ip` = '203.0.113.4';
--  ou, pour conserver l'historique :
--      UPDATE `vault_blacklist` SET `blocked` = 0, `failure_count` = 0
--          WHERE `ip` = '203.0.113.4';
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `vault_blacklist` (
    `ip`               VARCHAR(45)      NOT NULL COMMENT 'IPv4 ou IPv6, en clair pour consultation directe',
    `failure_count`    TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Echecs consecutifs ; remis a 0 par un succes',
    `blocked`          TINYINT(1)       NOT NULL DEFAULT 0,
    `first_failure_at` DATETIME         NULL,
    `last_failure_at`  DATETIME         NULL,
    `blocked_at`       DATETIME         NULL,
    PRIMARY KEY (`ip`),
    KEY `idx_blocked` (`blocked`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
