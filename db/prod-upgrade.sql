-- Production upgrade for the current application code.
-- Run this in phpMyAdmin during a maintenance window after taking a full DB backup.
-- Reviewed against the provided production dump d04564c5.sql.

SET NAMES utf8mb4;

-- Add key-tracking columns expected by the current application.
ALTER TABLE `lizenznehmer`
    ADD COLUMN IF NOT EXISTS `schluessel_ausgegeben` TINYINT(1) NOT NULL DEFAULT 0 AFTER `fischerkartennummer`;

ALTER TABLE `lizenznehmer`
    ADD COLUMN IF NOT EXISTS `schluessel_ausgegeben_am` DATE NULL AFTER `schluessel_ausgegeben`;

-- Create the key history table expected by the current application.
CREATE TABLE IF NOT EXISTS `lizenznehmer_schluessel_historie` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `lizenznehmer_id` INT NOT NULL,
    `schluessel_ausgegeben_am` DATE NOT NULL,
    `schluessel_zurueckgegeben_am` DATE NULL,
    `erstellt_am` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `aktualisiert_am` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_lizenznehmer_schluessel_historie_lizenznehmer`
        FOREIGN KEY (`lizenznehmer_id`) REFERENCES `lizenznehmer` (`id`) ON DELETE CASCADE,
    INDEX `idx_lizenznehmer_schluessel_historie_lizenznehmer` (`lizenznehmer_id`),
    INDEX `idx_lizenznehmer_schluessel_historie_offen` (`lizenznehmer_id`, `schluessel_zurueckgegeben_am`),
    INDEX `idx_lizenznehmer_schluessel_historie_ausgegeben_am` (`schluessel_ausgegeben_am`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- If active keys already exist when this script is re-run later,
-- ensure they have an open history row.
INSERT INTO `lizenznehmer_schluessel_historie` (`lizenznehmer_id`, `schluessel_ausgegeben_am`)
SELECT `lizenznehmer`.`id`, COALESCE(`lizenznehmer`.`schluessel_ausgegeben_am`, CURDATE())
FROM `lizenznehmer`
WHERE `lizenznehmer`.`schluessel_ausgegeben` = 1
  AND NOT EXISTS (
      SELECT 1
      FROM `lizenznehmer_schluessel_historie` `history`
      WHERE `history`.`lizenznehmer_id` = `lizenznehmer`.`id`
        AND `history`.`schluessel_zurueckgegeben_am` IS NULL
  );

-- Add the per-year license-number columns expected by the current application.
ALTER TABLE `lizenzen_2025`
    ADD COLUMN IF NOT EXISTS `lizenznummer` INT NULL AFTER `lizenznehmer_id`;

ALTER TABLE `lizenzen_2026`
    ADD COLUMN IF NOT EXISTS `lizenznummer` INT NULL AFTER `lizenznehmer_id`;

-- Backfill 2026 license numbers from the leading numeric part in the legacy note field.
-- Examples:
--   '001' -> lizenznummer = 1, notizen = NULL
--   '50\n\nElba Ueberweisung' -> lizenznummer = 50, notizen = 'Elba Ueberweisung'
UPDATE `lizenzen_2026`
SET `lizenznummer` = CAST(TRIM(`notizen`) AS UNSIGNED)
WHERE `lizenznummer` IS NULL
  AND TRIM(COALESCE(`notizen`, '')) REGEXP '^[0-9]+';

UPDATE `lizenzen_2026`
SET `notizen` = NULLIF(
        TRIM(
            REGEXP_REPLACE(
                TRIM(`notizen`),
                '^[0-9]+[[:space:]]*',
                ''
            )
        ),
        ''
    )
WHERE TRIM(COALESCE(`notizen`, '')) REGEXP '^[0-9]+';

-- Intentional choice:
-- do not alter lizenzen_2025.notizen because the provided production dump
-- shows those values are already real notes or NULL.

-- Review rows that still have no license number after the 2026 backfill.
SELECT
    `id`,
    `lizenznehmer_id`,
    `lizenztyp`,
    `zahlungsdatum`,
    `notizen`
FROM `lizenzen_2026`
WHERE `lizenznummer` IS NULL
ORDER BY `id`;

-- Review duplicate license numbers after the backfill.
-- The current application allows duplicate numbers only across the Kinder vs Standard groups.
-- Fix any rows returned here manually before re-opening production.
SELECT
    CASE
        WHEN `lizenztyp` = 'Kinder' THEN 'Kinder'
        ELSE 'Standard'
    END AS `lizenznummer_gruppe`,
    `lizenznummer`,
    COUNT(*) AS `anzahl`,
    GROUP_CONCAT(
        CONCAT(
            'id=', `id`,
            ', lizenznehmer_id=', `lizenznehmer_id`,
            ', typ=', `lizenztyp`,
            ', notiz=', COALESCE(REPLACE(REPLACE(`notizen`, '\r', ' '), '\n', ' '), 'NULL')
        )
        ORDER BY `id`
        SEPARATOR ' | '
    ) AS `betroffene_zeilen`
FROM `lizenzen_2026`
WHERE `lizenznummer` IS NOT NULL
GROUP BY
    CASE
        WHEN `lizenztyp` = 'Kinder' THEN 'Kinder'
        ELSE 'Standard'
    END,
    `lizenznummer`
HAVING COUNT(*) > 1
ORDER BY `lizenznummer_gruppe`, `lizenznummer`;

-- Helper query for choosing manual replacement numbers in the Standard group.
SELECT COALESCE(MAX(`lizenznummer`), 0) + 1 AS `naechste_freie_standard_nummer`
FROM `lizenzen_2026`
WHERE `lizenztyp` <> 'Kinder';
