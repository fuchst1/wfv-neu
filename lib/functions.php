<?php
require_once __DIR__ . '/db.php';

function app_timezone(): DateTimeZone
{
    static $timezone = null;

    if (!$timezone) {
        $timezone = new DateTimeZone('Europe/Vienna');
    }

    return $timezone;
}

function app_today(): DateTimeImmutable
{
    return new DateTimeImmutable('today', app_timezone());
}

function app_today_string(): string
{
    return app_today()->format('Y-m-d');
}

function available_years(): array
{
    $pdo = get_pdo();
    $stmt = $pdo->prepare("SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE 'lizenzen\\_%' ORDER BY table_name ASC");
    $stmt->execute();
    $years = [];
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $table) {
        $years[] = (int)substr($table, strlen('lizenzen_'));
    }
    sort($years);
    return $years;
}

function latest_year(): ?int
{
    $years = available_years();
    return $years ? max($years) : null;
}

function license_table(int $year): string
{
    return 'lizenzen_' . $year;
}

function boats_table(): string
{
    return 'boote';
}

function licensee_key_history_table(): string
{
    return 'lizenznehmer_schluessel_historie';
}

function license_types(): array
{
    return ['Angel', 'Daubel', 'Boot', 'Intern', 'Kinder', 'Jugend'];
}

function is_valid_license_type(string $licenseType): bool
{
    return in_array($licenseType, license_types(), true);
}

function is_kinder_license_type(string $licenseType): bool
{
    return $licenseType === 'Kinder';
}

function license_number_group_key_for_type(string $licenseType): string
{
    return is_kinder_license_type($licenseType) ? 'kinder' : 'standard';
}

function license_number_group_label_for_type(string $licenseType): string
{
    return is_kinder_license_type($licenseType) ? 'Kinder' : 'Standard';
}

function license_type_labels(): array
{
    return [
        'Angel' => 'Angel',
        'Daubel' => 'Daubel',
        'Boot' => 'Boot',
        'Intern' => 'Intern',
        'Kinder' => 'Kinder',
        'Jugend' => 'Jugend',
    ];
}

function license_type_class(string $type): string
{
    $normalized = strtolower($type);
    $allowedTypes = array_map('strtolower', license_types());

    if (!in_array($normalized, $allowedTypes, true)) {
        return 'badge-license-default';
    }

    return 'badge-license-' . $normalized;
}

function boat_license_types(): array
{
    return ['Boot', 'Intern'];
}

function quote_list(PDO $pdo, array $values): string
{
    return implode(', ', array_map(fn ($value): string => $pdo->quote((string)$value), $values));
}

function ensure_license_price_enum(?PDO $pdo = null): void
{
    $pdo = $pdo ?? get_pdo();
    $columnStmt = $pdo->query("SHOW COLUMNS FROM `lizenzpreise` LIKE 'lizenztyp'");
    $needsAlter = true;
    if ($columnStmt !== false) {
        $column = $columnStmt->fetch(PDO::FETCH_ASSOC);
        if ($column && isset($column['Type'])) {
            $typeDefinition = (string)$column['Type'];
            $needsAlter = false;
            foreach (license_types() as $type) {
                $escaped = "'" . str_replace("'", "''", $type) . "'";
                if (strpos($typeDefinition, $escaped) === false) {
                    $needsAlter = true;
                    break;
                }
            }
        }
    }

    if (!$needsAlter) {
        return;
    }

    $enumValues = quote_list($pdo, license_types());
    $pdo->exec("ALTER TABLE `lizenzpreise` MODIFY lizenztyp ENUM({$enumValues}) NOT NULL");
}

function blocklist_table(): string
{
    return 'sperrliste';
}

function ensure_boats_table_exists(): void
{
    $pdo = get_pdo();
    $boatsTable = boats_table();

    $pdo->exec("CREATE TABLE IF NOT EXISTS {$boatsTable} (
        id INT PRIMARY KEY AUTO_INCREMENT,
        lizenznehmer_id INT NULL,
        bootnummer VARCHAR(50),
        notizen TEXT,
        erstellt_am TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        aktualisiert_am TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_boote_lizenznehmer FOREIGN KEY (lizenznehmer_id) REFERENCES lizenznehmer(id) ON DELETE SET NULL,
        INDEX idx_boote_lizenznehmer (lizenznehmer_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function ensure_column_exists(PDO $pdo, string $table, string $column, string $definition): void
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column'
    );
    $stmt->execute([
        'table' => $table,
        'column' => $column,
    ]);

    if ((int)$stmt->fetchColumn() === 0) {
        $pdo->exec(sprintf('ALTER TABLE `%s` ADD COLUMN %s', $table, $definition));
    }
}

function ensure_blocklist_table_exists(): void
{
    $pdo = get_pdo();
    $blocklistTable = blocklist_table();

    $pdo->exec("CREATE TABLE IF NOT EXISTS {$blocklistTable} (
        id INT PRIMARY KEY AUTO_INCREMENT,
        vorname VARCHAR(100) NOT NULL,
        nachname VARCHAR(100) NOT NULL,
        lizenznummer VARCHAR(100),
        erstellt_am TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        aktualisiert_am TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    ensure_column_exists($pdo, $blocklistTable, 'notiz', 'notiz TEXT NULL');
}

function ensure_person_birthdate_columns(): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $pdo = get_pdo();
    ensure_column_exists($pdo, 'lizenznehmer', 'geburtsdatum', 'geburtsdatum DATE NULL');
    ensure_column_exists($pdo, 'bewerber', 'geburtsdatum', 'geburtsdatum DATE NULL');
    $ensured = true;
}

function ensure_licensee_key_columns(): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $pdo = get_pdo();
    ensure_column_exists($pdo, 'lizenznehmer', 'schluessel_ausgegeben', 'schluessel_ausgegeben TINYINT(1) NOT NULL DEFAULT 0 AFTER fischerkartennummer');
    ensure_column_exists($pdo, 'lizenznehmer', 'schluessel_ausgegeben_am', 'schluessel_ausgegeben_am DATE NULL AFTER schluessel_ausgegeben');
    ensure_licensee_key_history_table($pdo);
    $ensured = true;
}

function ensure_licensee_key_history_table(?PDO $pdo = null): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $pdo = $pdo ?? get_pdo();
    $historyTable = licensee_key_history_table();

    $pdo->exec("CREATE TABLE IF NOT EXISTS {$historyTable} (
        id INT PRIMARY KEY AUTO_INCREMENT,
        lizenznehmer_id INT NOT NULL,
        schluessel_ausgegeben_am DATE NOT NULL,
        schluessel_zurueckgegeben_am DATE NULL,
        erstellt_am TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        aktualisiert_am TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_lizenznehmer_schluessel_historie_lizenznehmer FOREIGN KEY (lizenznehmer_id) REFERENCES lizenznehmer(id) ON DELETE CASCADE,
        INDEX idx_lizenznehmer_schluessel_historie_lizenznehmer (lizenznehmer_id),
        INDEX idx_lizenznehmer_schluessel_historie_offen (lizenznehmer_id, schluessel_zurueckgegeben_am),
        INDEX idx_lizenznehmer_schluessel_historie_ausgegeben_am (schluessel_ausgegeben_am)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $backfillStmt = $pdo->prepare(
        "INSERT INTO {$historyTable} (lizenznehmer_id, schluessel_ausgegeben_am)
         SELECT lizenznehmer.id, COALESCE(lizenznehmer.schluessel_ausgegeben_am, :today)
         FROM lizenznehmer
         WHERE lizenznehmer.schluessel_ausgegeben = 1
           AND NOT EXISTS (
               SELECT 1
               FROM {$historyTable} history
               WHERE history.lizenznehmer_id = lizenznehmer.id
                 AND history.schluessel_zurueckgegeben_am IS NULL
           )"
    );
    $backfillStmt->execute([
        'today' => app_today_string(),
    ]);

    $ensured = true;
}

function ensure_year_closure_table_exists(): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $pdo = get_pdo();
    $pdo->exec("CREATE TABLE IF NOT EXISTS jahresabschluesse (
        jahr INT PRIMARY KEY,
        abgeschlossen_am TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        anzahl_lizenzen INT,
        gesamt_kosten DECIMAL(10,2),
        gesamt_trinkgeld DECIMAL(10,2),
        gesamt_einnahmen DECIMAL(10,2),
        notizen TEXT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $ensured = true;
}

function ensure_year_exists(int $year): bool
{
    $pdo = get_pdo();
    $licenseTable = license_table($year);

    $enumValues = quote_list($pdo, license_types());

    $pdo->exec("CREATE TABLE IF NOT EXISTS {$licenseTable} (
        id INT PRIMARY KEY AUTO_INCREMENT,
        lizenznehmer_id INT NOT NULL,
        lizenznummer INT NULL,
        lizenztyp ENUM({$enumValues}) NOT NULL,
        kosten DECIMAL(10,2) NOT NULL,
        trinkgeld DECIMAL(10,2) DEFAULT 0.00,
        gesamt DECIMAL(10,2) NOT NULL,
        zahlungsdatum DATE,
        notizen TEXT,
        erstellt_am TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (lizenznehmer_id) REFERENCES lizenznehmer(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $quotedLicenseTable = sprintf('`%s`', str_replace('`', '``', $licenseTable));
    $columnStmt = $pdo->query("SHOW COLUMNS FROM {$quotedLicenseTable} LIKE 'lizenztyp'");
    $needsAlter = true;
    if ($columnStmt !== false) {
        $column = $columnStmt->fetch(PDO::FETCH_ASSOC);
        if ($column && isset($column['Type'])) {
            $typeDefinition = (string)$column['Type'];
            $needsAlter = false;
            foreach (license_types() as $type) {
                $escaped = "'" . str_replace("'", "''", $type) . "'";
                if (strpos($typeDefinition, $escaped) === false) {
                    $needsAlter = true;
                    break;
                }
            }
        }
    }

    if ($needsAlter) {
        $pdo->exec("ALTER TABLE {$quotedLicenseTable} MODIFY lizenztyp ENUM({$enumValues}) NOT NULL");
    }

    ensure_year_license_number_column($year, $pdo);
    ensure_boats_table_exists();

    return true;
}

function ensure_year_license_number_column(int $year, ?PDO $pdo = null): void
{
    $pdo = $pdo ?? get_pdo();
    $licenseTable = license_table($year);

    ensure_column_exists($pdo, $licenseTable, 'lizenznummer', 'lizenznummer INT NULL AFTER lizenznehmer_id');
}

function get_next_license_number_for_year(int $year, string $licenseType, ?PDO $pdo = null): int
{
    $pdo = $pdo ?? get_pdo();
    ensure_year_exists($year);
    ensure_year_license_number_column($year, $pdo);

    if (!is_valid_license_type($licenseType)) {
        throw new InvalidArgumentException('Ungültiger Lizenztyp.');
    }

    $licenseTable = license_table($year);
    if (is_kinder_license_type($licenseType)) {
        $stmt = $pdo->prepare("SELECT COALESCE(MAX(lizenznummer), 0) + 1 FROM {$licenseTable} WHERE lizenztyp = :lizenztyp");
        $stmt->execute([
            'lizenztyp' => 'Kinder',
        ]);
    } else {
        $stmt = $pdo->prepare("SELECT COALESCE(MAX(lizenznummer), 0) + 1 FROM {$licenseTable} WHERE lizenztyp <> :lizenztyp");
        $stmt->execute([
            'lizenztyp' => 'Kinder',
        ]);
    }
    $nextNumber = (int)$stmt->fetchColumn();

    return $nextNumber > 0 ? $nextNumber : 1;
}

function get_year_closures(): array
{
    ensure_year_closure_table_exists();

    $pdo = get_pdo();
    $stmt = $pdo->query('SELECT jahr, abgeschlossen_am, anzahl_lizenzen, gesamt_kosten, gesamt_trinkgeld, gesamt_einnahmen, notizen FROM jahresabschluesse ORDER BY jahr');

    $closures = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $year = (int)$row['jahr'];
        $closures[$year] = [
            'abgeschlossen_am' => $row['abgeschlossen_am'] ?? null,
            'anzahl_lizenzen' => isset($row['anzahl_lizenzen']) ? (int)$row['anzahl_lizenzen'] : 0,
            'gesamt_kosten' => isset($row['gesamt_kosten']) ? (float)$row['gesamt_kosten'] : 0.0,
            'gesamt_trinkgeld' => isset($row['gesamt_trinkgeld']) ? (float)$row['gesamt_trinkgeld'] : 0.0,
            'gesamt_einnahmen' => isset($row['gesamt_einnahmen']) ? (float)$row['gesamt_einnahmen'] : 0.0,
            'notizen' => $row['notizen'] ?? null,
        ];
    }

    return $closures;
}

function get_year_closure(int $year): ?array
{
    static $cache = [];
    if (array_key_exists($year, $cache)) {
        return $cache[$year];
    }

    ensure_year_closure_table_exists();

    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT jahr, abgeschlossen_am, anzahl_lizenzen, gesamt_kosten, gesamt_trinkgeld, gesamt_einnahmen, notizen FROM jahresabschluesse WHERE jahr = :jahr LIMIT 1');
    $stmt->execute(['jahr' => $year]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row === false) {
        $cache[$year] = null;
        return null;
    }

    $cache[$year] = [
        'jahr' => (int)$row['jahr'],
        'abgeschlossen_am' => $row['abgeschlossen_am'] ?? null,
        'anzahl_lizenzen' => isset($row['anzahl_lizenzen']) ? (int)$row['anzahl_lizenzen'] : 0,
        'gesamt_kosten' => isset($row['gesamt_kosten']) ? (float)$row['gesamt_kosten'] : 0.0,
        'gesamt_trinkgeld' => isset($row['gesamt_trinkgeld']) ? (float)$row['gesamt_trinkgeld'] : 0.0,
        'gesamt_einnahmen' => isset($row['gesamt_einnahmen']) ? (float)$row['gesamt_einnahmen'] : 0.0,
        'notizen' => $row['notizen'] ?? null,
    ];

    return $cache[$year];
}

function is_year_closed(int $year): bool
{
    return get_year_closure($year) !== null;
}

function get_blocklist_entries(): array
{
    $pdo = get_pdo();
    ensure_blocklist_table_exists();
    $table = blocklist_table();

    $stmt = $pdo->query("SELECT * FROM {$table} ORDER BY nachname, vorname, id");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function find_blocklist_entry(string $vorname, string $nachname, ?string $lizenznummer = null): ?array
{
    $pdo = get_pdo();
    ensure_blocklist_table_exists();
    $table = blocklist_table();

    $vorname = trim($vorname);
    $nachname = trim($nachname);
    $lizenznummer = $lizenznummer !== null ? trim($lizenznummer) : null;

    if ($lizenznummer !== null && $lizenznummer !== '') {
        $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE LOWER(TRIM(lizenznummer)) = LOWER(TRIM(:lizenznummer)) ORDER BY id ASC LIMIT 1");
        $stmt->execute(['lizenznummer' => $lizenznummer]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row !== false) {
            return $row;
        }
    }

    if ($vorname !== '' && $nachname !== '') {
        $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE LOWER(TRIM(vorname)) = LOWER(TRIM(:vorname)) AND LOWER(TRIM(nachname)) = LOWER(TRIM(:nachname)) ORDER BY id ASC LIMIT 1");
        $stmt->execute([
            'vorname' => $vorname,
            'nachname' => $nachname,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row !== false) {
            return $row;
        }
    }

    if ($nachname !== '') {
        $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE LOWER(TRIM(nachname)) = LOWER(TRIM(:nachname)) ORDER BY id ASC LIMIT 1");
        $stmt->execute(['nachname' => $nachname]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row !== false) {
            return $row;
        }
    }

    return null;
}

function save_blocklist_entry(array $entry): int
{
    $pdo = get_pdo();
    ensure_blocklist_table_exists();
    $table = blocklist_table();

    $id = isset($entry['id']) ? (int)$entry['id'] : 0;
    $vorname = trim((string)($entry['vorname'] ?? ''));
    $nachname = trim((string)($entry['nachname'] ?? ''));
    $lizenznummerRaw = trim((string)($entry['lizenznummer'] ?? ''));
    $lizenznummer = $lizenznummerRaw !== '' ? $lizenznummerRaw : null;
    $notizRaw = trim((string)($entry['notiz'] ?? ''));
    $notiz = $notizRaw !== '' ? $notizRaw : null;

    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE {$table} SET vorname = :vorname, nachname = :nachname, lizenznummer = :lizenznummer, notiz = :notiz WHERE id = :id");
        $stmt->execute([
            'vorname' => $vorname,
            'nachname' => $nachname,
            'lizenznummer' => $lizenznummer,
            'notiz' => $notiz,
            'id' => $id,
        ]);
        return $id;
    }

    $stmt = $pdo->prepare("INSERT INTO {$table} (vorname, nachname, lizenznummer, notiz) VALUES (:vorname, :nachname, :lizenznummer, :notiz)");
    $stmt->execute([
        'vorname' => $vorname,
        'nachname' => $nachname,
        'lizenznummer' => $lizenznummer,
        'notiz' => $notiz,
    ]);

    return (int)$pdo->lastInsertId();
}

function delete_blocklist_entry(int $id): bool
{
    $pdo = get_pdo();
    ensure_blocklist_table_exists();
    $table = blocklist_table();

    $stmt = $pdo->prepare("DELETE FROM {$table} WHERE id = :id");
    return $stmt->execute(['id' => $id]);
}

function get_license_prices(int $year): array
{
    $pdo = get_pdo();
    ensure_license_price_enum($pdo);
    $stmt = $pdo->prepare('SELECT lizenztyp, preis FROM lizenzpreise WHERE jahr = :jahr');
    $stmt->execute(['jahr' => $year]);
    $prices = $stmt->fetchAll();
    $map = [];
    foreach ($prices as $row) {
        $map[$row['lizenztyp']] = (float)$row['preis'];
    }
    return $map;
}

function get_all_licensees(): array
{
    $pdo = get_pdo();
    ensure_person_birthdate_columns();
    ensure_licensee_key_columns();
    $stmt = $pdo->query('SELECT * FROM lizenznehmer ORDER BY nachname, vorname');
    return $stmt->fetchAll();
}

function get_licensees_with_keys(): array
{
    $pdo = get_pdo();
    ensure_person_birthdate_columns();
    ensure_licensee_key_columns();

    $stmt = $pdo->query(
        "SELECT id, vorname, nachname, geburtsdatum, strasse, plz, ort, telefon, email, fischerkartennummer, schluessel_ausgegeben, schluessel_ausgegeben_am
         FROM lizenznehmer
         WHERE schluessel_ausgegeben = 1
         ORDER BY nachname, vorname, id"
    );

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_newcomers(): array
{
    $pdo = get_pdo();
    ensure_person_birthdate_columns();
    $stmt = $pdo->query('SELECT * FROM bewerber ORDER BY bewerbungsdatum ASC, nachname, vorname');
    return $stmt->fetchAll();
}

function get_licensees_for_year(int $year): array
{
    $pdo = get_pdo();
    ensure_person_birthdate_columns();
    $licenseTable = license_table($year);

    ensure_year_exists($year);
    ensure_year_license_number_column($year, $pdo);
    ensure_boats_table_exists();
    $boatsTable = boats_table();

    $sql = "SELECT l.id AS lizenz_id, l.lizenznehmer_id, l.lizenznummer, l.lizenztyp, l.kosten, l.trinkgeld, l.gesamt, l.zahlungsdatum, l.notizen AS lizenz_notizen,
                ln.*,
                (SELECT b2.id FROM {$boatsTable} b2 WHERE b2.lizenznehmer_id = ln.id ORDER BY b2.id ASC LIMIT 1) AS boot_id,
                (SELECT b2.bootnummer FROM {$boatsTable} b2 WHERE b2.lizenznehmer_id = ln.id ORDER BY b2.id ASC LIMIT 1) AS bootnummer,
                (SELECT b2.notizen FROM {$boatsTable} b2 WHERE b2.lizenznehmer_id = ln.id ORDER BY b2.id ASC LIMIT 1) AS boot_notizen
            FROM {$licenseTable} l
            JOIN lizenznehmer ln ON ln.id = l.lizenznehmer_id
            ORDER BY ln.nachname, ln.vorname";
    $stmt = $pdo->query($sql);
    $licensees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$licensees) {
        return $licensees;
    }

    $licenseeIds = [];
    foreach ($licensees as $row) {
        if (isset($row['lizenznehmer_id'])) {
            $licenseeIds[] = (int)$row['lizenznehmer_id'];
        } elseif (isset($row['id'])) {
            $licenseeIds[] = (int)$row['id'];
        }
    }
    $licenseeIds = array_values(array_unique(array_filter($licenseeIds, fn (int $id): bool => $id > 0)));

    if (!$licenseeIds) {
        return $licensees;
    }

    $previousYears = array_values(array_filter(available_years(), fn (int $availableYear): bool => $availableYear < $year));

    if (!$previousYears) {
        return $licensees;
    }

    $history = [];
    $placeholders = implode(',', array_fill(0, count($licenseeIds), '?'));

    foreach ($previousYears as $previousYear) {
        $previousLicenseTable = license_table($previousYear);
        $historyStmt = $pdo->prepare("SELECT DISTINCT lizenznehmer_id FROM {$previousLicenseTable} WHERE lizenznehmer_id IN ({$placeholders})");
        $historyStmt->execute($licenseeIds);

        while ($historyRow = $historyStmt->fetch(PDO::FETCH_ASSOC)) {
            $historyId = isset($historyRow['lizenznehmer_id']) ? (int)$historyRow['lizenznehmer_id'] : null;
            if ($historyId === null) {
                continue;
            }

            if (!isset($history[$historyId])) {
                $history[$historyId] = [];
            }

            $history[$historyId][] = $previousYear;
        }
    }

    foreach ($history as &$yearsList) {
        rsort($yearsList);
    }
    unset($yearsList);

    foreach ($licensees as &$licenseeRow) {
        $licenseeRowId = null;
        if (isset($licenseeRow['lizenznehmer_id'])) {
            $licenseeRowId = (int)$licenseeRow['lizenznehmer_id'];
        } elseif (isset($licenseeRow['id'])) {
            $licenseeRowId = (int)$licenseeRow['id'];
        }

        $licenseeRow['previous_license_years'] = $licenseeRowId !== null && isset($history[$licenseeRowId])
            ? $history[$licenseeRowId]
            : [];
    }
    unset($licenseeRow);

    return $licensees;
}

function get_boats_overview(): array
{
    $pdo = get_pdo();
    ensure_boats_table_exists();
    ensure_person_birthdate_columns();
    $boatsTable = boats_table();

    $sql = "SELECT b.id AS boot_id, b.bootnummer, b.notizen AS boot_notizen, b.lizenznehmer_id,
                   ln.vorname, ln.nachname, ln.telefon, ln.email
            FROM {$boatsTable} b
            LEFT JOIN lizenznehmer ln ON ln.id = b.lizenznehmer_id
            ORDER BY b.bootnummer, ln.nachname, ln.vorname";

    $boats = $pdo->query($sql)->fetchAll();

    $assignments = [];
    $years = available_years();
    rsort($years);

    $boatTypeList = quote_list($pdo, boat_license_types());

    foreach ($years as $year) {
        $licenseTable = license_table($year);
        $stmt = $pdo->query("SELECT lizenznehmer_id, id, notizen, zahlungsdatum FROM {$licenseTable} WHERE lizenztyp IN ({$boatTypeList})");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $licenseeId = (int)$row['lizenznehmer_id'];
            if (!isset($assignments[$licenseeId])) {
                $assignments[$licenseeId] = [
                    'jahr' => $year,
                    'lizenz_id' => (int)$row['id'],
                    'lizenz_notizen' => $row['notizen'] ?? null,
                    'zahlungsdatum' => $row['zahlungsdatum'] ?? null,
                ];
            }
        }
    }

    foreach ($boats as &$boat) {
        $boat['jahr'] = null;
        $boat['lizenz_id'] = null;
        $boat['lizenz_notizen'] = null;
        $boat['zahlungsdatum'] = null;

        $licenseeId = $boat['lizenznehmer_id'] !== null ? (int)$boat['lizenznehmer_id'] : null;
        if ($licenseeId !== null && isset($assignments[$licenseeId])) {
            $boat['jahr'] = $assignments[$licenseeId]['jahr'];
            $boat['lizenz_id'] = $assignments[$licenseeId]['lizenz_id'];
            $boat['lizenz_notizen'] = $assignments[$licenseeId]['lizenz_notizen'];
            $boat['zahlungsdatum'] = $assignments[$licenseeId]['zahlungsdatum'];
        }
    }
    unset($boat);

    usort($boats, function (array $a, array $b): int {
        $numberCompare = strnatcmp((string)($a['bootnummer'] ?? ''), (string)($b['bootnummer'] ?? ''));
        if ($numberCompare !== 0) {
            return $numberCompare;
        }

        return ($b['jahr'] <=> $a['jahr']);
    });

    return $boats;
}

function get_year_overview(int $year): array
{
    $pdo = get_pdo();
    ensure_year_exists($year);
    $licenseTable = license_table($year);

    $sql = "SELECT lizenztyp, COUNT(*) AS anzahl, COALESCE(SUM(kosten), 0) AS summe_kosten, COALESCE(SUM(trinkgeld), 0) AS summe_trinkgeld, COALESCE(SUM(gesamt), 0) AS summe_gesamt
            FROM {$licenseTable}
            GROUP BY lizenztyp
            ORDER BY lizenztyp";

    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $overview = [
        'types' => [],
        'total_count' => 0,
        'total_cost' => 0.0,
        'total_tip' => 0.0,
        'total_combined' => 0.0,
    ];

    foreach ($rows as $row) {
        $count = (int)($row['anzahl'] ?? 0);
        $cost = (float)($row['summe_kosten'] ?? 0.0);
        $tip = (float)($row['summe_trinkgeld'] ?? 0.0);
        $combined = (float)($row['summe_gesamt'] ?? 0.0);

        $overview['types'][] = [
            'lizenztyp' => $row['lizenztyp'],
            'count' => $count,
            'sum_cost' => $cost,
            'sum_tip' => $tip,
            'sum_total' => $combined,
        ];

        $overview['total_count'] += $count;
        $overview['total_cost'] += $cost;
        $overview['total_tip'] += $tip;
        $overview['total_combined'] += $combined;
    }

    return $overview;
}

function format_currency(float $value): string
{
    return number_format($value, 2, ',', '.');
}

function format_datetime(?string $value): ?string
{
    if ($value === null) {
        return null;
    }

    $value = trim($value);
    if ($value === '' || $value === '0000-00-00 00:00:00') {
        return null;
    }

    $date = DateTime::createFromFormat('Y-m-d H:i:s', $value);
    if (!$date) {
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }
        $date = (new DateTime())->setTimestamp($timestamp);
    }

    return $date->format('d.m.Y H:i');
}

function format_date(?string $value): ?string
{
    if ($value === null) {
        return null;
    }

    $value = trim($value);
    if ($value === '' || $value === '0000-00-00') {
        return null;
    }

    $date = DateTime::createFromFormat('Y-m-d', $value);
    if (!$date) {
        return null;
    }

    return $date->format('d.m.Y');
}

function truncate_words(string $text, int $limit = 10): string
{
    $text = trim($text);
    if ($text === '') {
        return '';
    }

    $words = preg_split('/\s+/', $text);
    if (!$words || count($words) <= $limit) {
        return $text;
    }

    return implode(' ', array_slice($words, 0, $limit)) . ' …';
}

function calculate_age(?string $date): ?int
{
    if ($date === null) {
        return null;
    }

    $date = trim($date);
    if ($date === '' || $date === '0000-00-00') {
        return null;
    }

    $birthdate = DateTimeImmutable::createFromFormat('!Y-m-d', $date, app_timezone());
    if (!$birthdate) {
        return null;
    }

    $today = app_today();
    if ($birthdate > $today) {
        return null;
    }

    $diff = $birthdate->diff($today);
    $age = (int)$diff->y;
    return $age >= 0 ? $age : null;
}
