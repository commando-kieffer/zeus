-- =====================================================================
--  Documents : bibliothèque de documents du Commando (livrets
--  instructeurs, recruteurs, scénaristes, etc.), consultable par tous les
--  membres et gérée par l'état-major.
--
--  Chaque ligne décrit un document : un titre, une image d'aperçu et le
--  fichier lui-même (PDF, image, archive... tout type est accepté).
--
--  Les fichiers ne sont PAS en base : ils sont stockés hors de la racine
--  web, dans writable/uploads/documents/, sous un nom aléatoire (file_path
--  et preview_path, 32 caractères hexadécimaux) et servis par l'application
--  après contrôle de la connexion. Le nom d'origine (file_name) n'est
--  conservé que pour l'affichage et le nom du téléchargement.
--
--  created_at et updated_at sont en UTC (comme l'application) ; la
--  conversion en heure de Paris est faite à l'affichage.
--
--  À exécuter une seule fois sur commandokieffer_database. Sans danger si
--  rejoué : la table n'est créée que si elle n'existe pas.
-- =====================================================================

CREATE TABLE IF NOT EXISTS `document` (
    `id`           INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `title`        VARCHAR(128)     NOT NULL,
    `file_name`    VARCHAR(255)     NOT NULL,
    `file_path`    CHAR(32)         NOT NULL,
    `file_mime`    VARCHAR(127)     NOT NULL,
    `file_size`    BIGINT UNSIGNED  NOT NULL,
    `preview_path` CHAR(32)         NOT NULL,
    `preview_mime` VARCHAR(127)     NOT NULL,
    `created_at`   DATETIME         NOT NULL,
    `updated_at`   DATETIME         NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_document_title` (`title`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ---------------------------------------------------------------------
--  Contrôle : la table doit exister et être vide à la première exécution.
-- ---------------------------------------------------------------------

-- SELECT COUNT(*) AS documents FROM `document`;
