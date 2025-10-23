CREATE DATABASE IF NOT EXISTS woerdener_fischereiverein CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE woerdener_fischereiverein;

CREATE TABLE IF NOT EXISTS lizenznehmer (
    id INT PRIMARY KEY AUTO_INCREMENT,
    vorname VARCHAR(100) NOT NULL,
    nachname VARCHAR(100) NOT NULL,
    strasse VARCHAR(150),
    plz VARCHAR(10),
    ort VARCHAR(100),
    telefon VARCHAR(50),
    email VARCHAR(100),
    fischerkartennummer VARCHAR(50),
    erstellt_am TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sperrliste (
    id INT PRIMARY KEY AUTO_INCREMENT,
    vorname VARCHAR(100) NOT NULL,
    nachname VARCHAR(100) NOT NULL,
    lizenznummer VARCHAR(100),
    erstellt_am TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    aktualisiert_am TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bewerber (
    id INT PRIMARY KEY AUTO_INCREMENT,
    vorname VARCHAR(100),
    nachname VARCHAR(100),
    strasse VARCHAR(150),
    plz VARCHAR(10),
    ort VARCHAR(100),
    telefon VARCHAR(50),
    email VARCHAR(100),
    fischerkartennummer VARCHAR(50),
    bewerbungsdatum DATE,
    notizen TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plz_orte (
    plz VARCHAR(10) PRIMARY KEY,
    ort VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS jahresabschluesse (
    jahr INT PRIMARY KEY,
    abgeschlossen_am TIMESTAMP,
    anzahl_lizenzen INT,
    gesamt_kosten DECIMAL(10,2),
    gesamt_trinkgeld DECIMAL(10,2),
    gesamt_einnahmen DECIMAL(10,2),
    notizen TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lizenzpreise (
    jahr INT,
    lizenztyp ENUM('Angel', 'Daubel', 'Boot', 'Kinder', 'Jugend') NOT NULL,
    preis DECIMAL(10,2) NOT NULL,
    PRIMARY KEY (jahr, lizenztyp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Beispiel-Initialdaten
INSERT INTO plz_orte (plz, ort) VALUES
    ('2301', 'Groß-Enzersdorf'),
    ('2304', 'Orth an der Donau'),
    ('2305', 'Witzelsdorf')
ON DUPLICATE KEY UPDATE ort = VALUES(ort);

SET @jahr := YEAR(CURDATE());

SET @licenseTable := CONCAT('lizenzen_', @jahr);

SET @sql := CONCAT('CREATE TABLE IF NOT EXISTS ', @licenseTable, ' (
    id INT PRIMARY KEY AUTO_INCREMENT,
    lizenznehmer_id INT NOT NULL,
    lizenztyp ENUM("Angel", "Daubel", "Boot", "Kinder", "Jugend") NOT NULL,
    kosten DECIMAL(10,2) NOT NULL,
    trinkgeld DECIMAL(10,2) DEFAULT 0.00,
    gesamt DECIMAL(10,2) NOT NULL,
    zahlungsdatum DATE,
    notizen TEXT,
    erstellt_am TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lizenznehmer_id) REFERENCES lizenznehmer(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT INTO lizenzpreise (jahr, lizenztyp, preis) VALUES
    (@jahr, 'Angel', 60.00),
    (@jahr, 'Daubel', 45.00),
    (@jahr, 'Boot', 30.00),
    (@jahr, 'Kinder', 15.00),
    (@jahr, 'Jugend', 25.00)
ON DUPLICATE KEY UPDATE preis = VALUES(preis);

CREATE TABLE IF NOT EXISTS boote (
    id INT PRIMARY KEY AUTO_INCREMENT,
    lizenznehmer_id INT NULL,
    bootnummer VARCHAR(50),
    notizen TEXT,
    erstellt_am TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    aktualisiert_am TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_boote_lizenznehmer FOREIGN KEY (lizenznehmer_id) REFERENCES lizenznehmer(id) ON DELETE SET NULL,
    INDEX idx_boote_lizenznehmer (lizenznehmer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
