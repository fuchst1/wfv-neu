<?php
require_once __DIR__ . '/db.php';

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

function ensure_year_exists(int $year): bool
{
    $pdo = get_pdo();
    $licenseTable = license_table($year);

    $pdo->exec("CREATE TABLE IF NOT EXISTS {$licenseTable} (
        id INT PRIMARY KEY AUTO_INCREMENT,
        lizenznehmer_id INT NOT NULL,
        lizenztyp ENUM('Angel', 'Daubel', 'Boot', 'Kinder', 'Jugend') NOT NULL,
        kosten DECIMAL(10,2) NOT NULL,
        trinkgeld DECIMAL(10,2) DEFAULT 0.00,
        gesamt DECIMAL(10,2) NOT NULL,
        zahlungsdatum DATE,
        notizen TEXT,
        erstellt_am TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (lizenznehmer_id) REFERENCES lizenznehmer(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    ensure_boats_table_exists();

    return true;
}

function get_license_prices(int $year): array
{
    $pdo = get_pdo();
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
    $stmt = $pdo->query('SELECT * FROM lizenznehmer ORDER BY nachname, vorname');
    return $stmt->fetchAll();
}

function get_newcomers(): array
{
    $pdo = get_pdo();
    $stmt = $pdo->query('SELECT * FROM bewerber ORDER BY bewerbungsdatum DESC, nachname, vorname');
    return $stmt->fetchAll();
}

function get_licensees_for_year(int $year): array
{
    $pdo = get_pdo();
    $licenseTable = license_table($year);

    ensure_year_exists($year);
    ensure_boats_table_exists();
    $boatsTable = boats_table();

    $sql = "SELECT l.id AS lizenz_id, l.lizenztyp, l.kosten, l.trinkgeld, l.gesamt, l.zahlungsdatum, l.notizen AS lizenz_notizen,
                ln.*, 
                (SELECT b2.id FROM {$boatsTable} b2 WHERE b2.lizenznehmer_id = ln.id ORDER BY b2.id ASC LIMIT 1) AS boot_id,
                (SELECT b2.bootnummer FROM {$boatsTable} b2 WHERE b2.lizenznehmer_id = ln.id ORDER BY b2.id ASC LIMIT 1) AS bootnummer,
                (SELECT b2.notizen FROM {$boatsTable} b2 WHERE b2.lizenznehmer_id = ln.id ORDER BY b2.id ASC LIMIT 1) AS boot_notizen
            FROM {$licenseTable} l
            JOIN lizenznehmer ln ON ln.id = l.lizenznehmer_id
            ORDER BY ln.nachname, ln.vorname";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll();
}

function get_boats_overview(): array
{
    $pdo = get_pdo();
    ensure_boats_table_exists();
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

    foreach ($years as $year) {
        $licenseTable = license_table($year);
        $stmt = $pdo->query("SELECT lizenznehmer_id, id, notizen, zahlungsdatum FROM {$licenseTable} WHERE lizenztyp = 'Boot'");
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
