-- =====================================================================
--  Ajout du compteur d'OPEX (opérations extérieures : matchs et tournois)
--  au panel.
--
--  Les compteurs du panel sont préfixés panel_ ; les colonnes ck_ sont
--  l'ancienne génération, qui n'est plus alimentée. Cette migration crée
--  panel_opex sur le modèle de panel_prs / panel_abs et le remplit à partir
--  de ck_opex, qui détient les valeurs historiques.
--
--  ck_opex est conservée volontairement : la supprimer ferait perdre le
--  seul exemplaire des données si la reprise devait être rejouée. Elle
--  pourra être retirée plus tard, une fois panel_opex éprouvée.
--
--  À exécuter une seule fois. MySQL n'accepte pas IF NOT EXISTS sur
--  ADD COLUMN : une seconde exécution échouera sur « Duplicate column
--  name », sans dommage.
-- =====================================================================

ALTER TABLE `xf_user`
    ADD COLUMN `panel_opex` INT NOT NULL DEFAULT 0 AFTER `panel_abs`;

UPDATE `xf_user` SET `panel_opex` = `ck_opex`;


-- ---------------------------------------------------------------------
--  Contrôle : les deux colonnes doivent être identiques partout.
--  La requête doit renvoyer 0.
-- ---------------------------------------------------------------------

-- SELECT COUNT(*) AS ecarts FROM `xf_user` WHERE `panel_opex` <> `ck_opex`;
