<?php
require_once __DIR__ . '/lib/functions.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? $_POST['action'] ?? null;
if (!$action) {
    echo json_encode(['success' => false, 'message' => 'Keine Aktion angegeben.']);
    exit;
}

try {
    switch ($action) {
        case 'lookup_zip':
            lookup_zip();
            break;
        case 'save_license':
            save_license();
            break;
        case 'save_boat':
            save_boat();
            break;
        case 'update_boat':
            update_boat();
            break;
        case 'delete_boat':
            delete_boat();
            break;
        case 'get_boat_licenses':
            get_boat_licenses();
            break;
        case 'assign_boat_license':
            assign_boat_license();
            break;
        case 'delete_license':
            delete_license();
            break;
        case 'move_license':
            move_license();
            break;
        case 'create_year':
            create_year();
            break;
        case 'delete_year':
            delete_year();
            break;
        case 'close_year':
            close_year();
            break;
        case 'year_overview':
            year_overview();
            break;
        case 'get_prices':
            get_prices();
            break;
        case 'get_next_license_number':
            get_next_license_number();
            break;
        case 'assign_newcomer':
            assign_newcomer();
            break;
        case 'create_newcomer':
            create_newcomer();
            break;
        case 'update_newcomer':
            update_newcomer();
            break;
        case 'delete_newcomer':
            delete_newcomer();
            break;
        case 'get_blocklist':
            get_blocklist();
            break;
        case 'save_block_entry':
            save_block_entry();
            break;
        case 'delete_block_entry':
            delete_block_entry();
            break;
        case 'search_licensees':
            search_licensees();
            break;
        case 'get_licensee_key_history':
            get_licensee_key_history();
            break;
        case 'save_licensee_key':
            save_licensee_key();
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Unbekannte Aktion.']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function lookup_zip(): void
{
    $plz = trim($_GET['plz'] ?? '');
    $ort = trim($_GET['ort'] ?? '');

    if ($plz === '' && $ort === '') {
        echo json_encode(['success' => false]);
        return;
    }

    $pdo = get_pdo();

    if ($plz !== '') {
        $stmt = $pdo->prepare('SELECT plz, ort FROM plz_orte WHERE plz = :plz LIMIT 1');
        $stmt->execute(['plz' => $plz]);
    } else {
        $stmt = $pdo->prepare(
            "SELECT plz, ort
             FROM plz_orte
             WHERE REPLACE(REPLACE(LOWER(TRIM(ort)), '-', ''), ' ', '') = REPLACE(REPLACE(LOWER(TRIM(:ort)), '-', ''), ' ', '')
             ORDER BY plz ASC
             LIMIT 1"
        );
        $stmt->execute(['ort' => $ort]);
    }

    $row = $stmt->fetch();
    echo json_encode([
        'success' => (bool)$row,
        'plz' => $row['plz'] ?? null,
        'ort' => $row['ort'] ?? null,
    ]);
}

function get_next_license_number(): void
{
    $year = (int)($_GET['year'] ?? 0);
    $licenseType = trim((string)($_GET['license_type'] ?? ($_GET['lizenztyp'] ?? '')));
    if ($year < 2000) {
        echo json_encode(['success' => false, 'message' => 'Ungültiges Jahr.']);
        return;
    }

    if (!is_valid_license_type($licenseType)) {
        echo json_encode(['success' => false, 'message' => 'Ungültiger Lizenztyp.']);
        return;
    }

    if (!in_array($year, available_years(), true)) {
        echo json_encode(['success' => false, 'message' => 'Dieses Jahr existiert nicht.']);
        return;
    }

    echo json_encode([
        'success' => true,
        'year' => $year,
        'license_type' => $licenseType,
        'license_number_group' => license_number_group_key_for_type($licenseType),
        'next_license_number' => get_next_license_number_for_year($year, $licenseType),
    ]);
}

function validate_year_license_number_input($value): array
{
    $rawValue = trim((string)$value);
    if ($rawValue === '') {
        return ['value' => null, 'error' => 'Lizenznummer ist erforderlich.'];
    }

    if (!preg_match('/^\d+$/', $rawValue)) {
        return ['value' => null, 'error' => 'Lizenznummer muss eine ganze Zahl sein.'];
    }

    $licenseNumber = (int)$rawValue;
    if ($licenseNumber < 1) {
        return ['value' => null, 'error' => 'Lizenznummer muss mindestens 1 sein.'];
    }

    return ['value' => $licenseNumber, 'error' => null];
}

function duplicate_license_number_message(string $licenseType, string $yearContext = 'in diesem Jahr'): string
{
    return sprintf(
        'Für diese Lizenznummer existiert %s bereits eine Lizenz in der Zählgruppe "%s".',
        $yearContext,
        license_number_group_label_for_type($licenseType)
    );
}

function has_duplicate_yearly_license_number(PDO $pdo, int $year, int $licenseNumber, string $licenseType, ?int $excludeLicenseId = null): bool
{
    ensure_year_exists($year);
    ensure_year_license_number_column($year, $pdo);

    if (!is_valid_license_type($licenseType)) {
        throw new InvalidArgumentException('Ungültiger Lizenztyp.');
    }

    $licenseTable = license_table($year);
    $sql = "SELECT COUNT(*) FROM {$licenseTable} WHERE lizenznummer = :lizenznummer";
    $params = [
        'lizenznummer' => $licenseNumber,
        'kinder_typ' => 'Kinder',
    ];

    if (is_kinder_license_type($licenseType)) {
        $sql .= ' AND lizenztyp = :kinder_typ';
    } else {
        $sql .= ' AND lizenztyp <> :kinder_typ';
    }

    if ($excludeLicenseId !== null && $excludeLicenseId > 0) {
        $sql .= ' AND id <> :exclude_id';
        $params['exclude_id'] = $excludeLicenseId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return (int)$stmt->fetchColumn() > 0;
}

function save_license(): void
{
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        echo json_encode(['success' => false, 'message' => 'Ungültige Daten.']);
        return;
    }

    $year = (int)($data['year'] ?? 0);
    if ($year < 2000) {
        echo json_encode(['success' => false, 'message' => 'Ungültiges Jahr.']);
        return;
    }

    $licenseTable = license_table($year);
    ensure_year_exists($year);
    ensure_boats_table_exists();
    $boatTable = boats_table();

    if (is_year_closed($year)) {
        echo json_encode(['success' => false, 'message' => 'Dieses Jahr wurde bereits abgeschlossen und kann nicht mehr bearbeitet werden.']);
        return;
    }

    $licenseePayload = $data['licensee'] ?? [];
    $licensePayload = $data['license'] ?? [];
    $boatPayload = $data['boat'] ?? [];
    $force = !empty($data['force']);

    $licenseeId = isset($licenseePayload['id']) ? (int)$licenseePayload['id'] : 0;
    $vorname = trim((string)($licenseePayload['vorname'] ?? ''));
    $nachname = trim((string)($licenseePayload['nachname'] ?? ''));

    if ($vorname === '' || $nachname === '') {
        echo json_encode(['success' => false, 'message' => 'Vor- und Nachname sind erforderlich.']);
        return;
    }

    $licenseePayload['vorname'] = $vorname;
    $licenseePayload['nachname'] = $nachname;

    $licenseNumberRaw = isset($licenseePayload['fischerkartennummer']) ? trim((string)$licenseePayload['fischerkartennummer']) : '';
    $blockedEntry = find_blocklist_entry($vorname, $nachname, $licenseNumberRaw !== '' ? $licenseNumberRaw : null);
    if ($blockedEntry && !$force) {
        echo json_encode([
            'success' => false,
            'blocked' => true,
            'entry' => $blockedEntry,
            'message' => 'Der Lizenznehmer befindet sich auf der Sperrliste.'
        ]);
        return;
    }

    ensure_person_birthdate_columns();

    $birthdateValidation = validate_birthdate_input($licenseePayload['geburtsdatum'] ?? null, 'Geburtsdatum');
    if ($birthdateValidation['error']) {
        echo json_encode(['success' => false, 'message' => $birthdateValidation['error']]);
        return;
    }
    $licenseePayload['geburtsdatum'] = $birthdateValidation['value'];

    $optionalFields = ['strasse', 'plz', 'ort', 'telefon', 'email', 'fischerkartennummer'];
    foreach ($optionalFields as $field) {
        if (array_key_exists($field, $licenseePayload)) {
            $value = trim((string)$licenseePayload[$field]);
            $licenseePayload[$field] = $value === '' ? null : $value;
        } else {
            $licenseePayload[$field] = null;
        }
    }

    $licenseId = isset($licensePayload['id']) ? (int)$licensePayload['id'] : 0;
    $lizenztyp = $licensePayload['lizenztyp'] ?? '';
    $kosten = (float)($licensePayload['kosten'] ?? 0);
    $trinkgeld = (float)($licensePayload['trinkgeld'] ?? 0);
    $zahlungsdatum = isset($licensePayload['zahlungsdatum']) && $licensePayload['zahlungsdatum'] !== '' ? $licensePayload['zahlungsdatum'] : null;
    $notizenRaw = isset($licensePayload['notizen']) ? trim((string)$licensePayload['notizen']) : '';
    $notizen = $notizenRaw === '' ? null : $notizenRaw;
    $licenseNumberValidation = validate_year_license_number_input($licensePayload['lizenznummer'] ?? null);
    if ($licenseNumberValidation['error']) {
        echo json_encode(['success' => false, 'message' => $licenseNumberValidation['error']]);
        return;
    }
    $lizenznummer = (int)$licenseNumberValidation['value'];
    $gesamt = $kosten + $trinkgeld;

    if (!is_valid_license_type($lizenztyp)) {
        echo json_encode(['success' => false, 'message' => 'Ungültiger Lizenztyp.']);
        return;
    }

    $pdo = get_pdo();
    ensure_license_price_enum($pdo);
    ensure_year_license_number_column($year, $pdo);

    if (has_duplicate_yearly_license_number($pdo, $year, $lizenznummer, $lizenztyp, $licenseId > 0 ? $licenseId : null) && !$force) {
        echo json_encode([
            'success' => false,
            'duplicate' => true,
            'message' => duplicate_license_number_message($lizenztyp, 'in diesem Jahr'),
            'license_number' => $lizenznummer,
        ]);
        return;
    }

    $pdo->beginTransaction();

    try {
        if ($licenseeId > 0) {
            $stmt = $pdo->prepare('UPDATE lizenznehmer SET vorname=:vorname, nachname=:nachname, geburtsdatum=:geburtsdatum, strasse=:strasse, plz=:plz, ort=:ort, telefon=:telefon, email=:email, fischerkartennummer=:karte WHERE id = :id');
            $stmt->execute([
                'vorname' => $licenseePayload['vorname'],
                'nachname' => $licenseePayload['nachname'],
                'geburtsdatum' => $licenseePayload['geburtsdatum'],
                'strasse' => $licenseePayload['strasse'],
                'plz' => $licenseePayload['plz'],
                'ort' => $licenseePayload['ort'],
                'telefon' => $licenseePayload['telefon'],
                'email' => $licenseePayload['email'],
                'karte' => $licenseePayload['fischerkartennummer'],
                'id' => $licenseeId,
            ]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO lizenznehmer (vorname, nachname, geburtsdatum, strasse, plz, ort, telefon, email, fischerkartennummer) VALUES (:vorname, :nachname, :geburtsdatum, :strasse, :plz, :ort, :telefon, :email, :karte)');
            $stmt->execute([
                'vorname' => $licenseePayload['vorname'],
                'nachname' => $licenseePayload['nachname'],
                'geburtsdatum' => $licenseePayload['geburtsdatum'],
                'strasse' => $licenseePayload['strasse'],
                'plz' => $licenseePayload['plz'],
                'ort' => $licenseePayload['ort'],
                'telefon' => $licenseePayload['telefon'],
                'email' => $licenseePayload['email'],
                'karte' => $licenseePayload['fischerkartennummer'],
            ]);
            $licenseeId = (int)$pdo->lastInsertId();
        }

        if ($licenseId > 0) {
            $stmt = $pdo->prepare("UPDATE {$licenseTable} SET lizenznehmer_id=:licensee_id, lizenznummer=:lizenznummer, lizenztyp=:typ, kosten=:kosten, trinkgeld=:trinkgeld, gesamt=:gesamt, zahlungsdatum=:datum, notizen=:notizen WHERE id=:id");
            $stmt->execute([
                'licensee_id' => $licenseeId,
                'lizenznummer' => $lizenznummer,
                'typ' => $lizenztyp,
                'kosten' => $kosten,
                'trinkgeld' => $trinkgeld,
                'gesamt' => $gesamt,
                'datum' => $zahlungsdatum,
                'notizen' => $notizen,
                'id' => $licenseId,
            ]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO {$licenseTable} (lizenznehmer_id, lizenznummer, lizenztyp, kosten, trinkgeld, gesamt, zahlungsdatum, notizen) VALUES (:licensee_id, :lizenznummer, :typ, :kosten, :trinkgeld, :gesamt, :datum, :notizen)");
            $stmt->execute([
                'licensee_id' => $licenseeId,
                'lizenznummer' => $lizenznummer,
                'typ' => $lizenztyp,
                'kosten' => $kosten,
                'trinkgeld' => $trinkgeld,
                'gesamt' => $gesamt,
                'datum' => $zahlungsdatum,
                'notizen' => $notizen,
            ]);
            $licenseId = (int)$pdo->lastInsertId();
        }

        $boatNumber = isset($boatPayload['bootnummer']) ? trim((string)$boatPayload['bootnummer']) : '';
        $boatNumber = $boatNumber === '' ? null : $boatNumber;
        $boatNotesRaw = isset($boatPayload['notizen']) ? trim((string)$boatPayload['notizen']) : '';
        $boatNotes = $boatNotesRaw === '' ? null : $boatNotesRaw;

        if (in_array($lizenztyp, boat_license_types(), true)) {
            $stmt = $pdo->prepare("SELECT id FROM {$boatTable} WHERE lizenznehmer_id = :id ORDER BY id ASC LIMIT 1");
            $stmt->execute(['id' => $licenseeId]);
            $boatId = $stmt->fetchColumn();

            if ($boatId) {
                $stmt = $pdo->prepare("UPDATE {$boatTable} SET bootnummer = :nummer, notizen = :notizen WHERE id = :id");
                $stmt->execute([
                    'nummer' => $boatNumber,
                    'notizen' => $boatNotes,
                    'id' => $boatId,
                ]);
            } elseif ($boatNumber !== null || $boatNotes !== null) {
                $stmt = $pdo->prepare("INSERT INTO {$boatTable} (lizenznehmer_id, bootnummer, notizen) VALUES (:licensee_id, :nummer, :notizen)");
                $stmt->execute([
                    'licensee_id' => $licenseeId,
                    'nummer' => $boatNumber,
                    'notizen' => $boatNotes,
                ]);
            }
        }

        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function save_boat(): void
{
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        echo json_encode(['success' => false, 'message' => 'Ungültige Daten.']);
        return;
    }

    $boatPayload = $data['boat'] ?? [];
    $boatNumberRaw = trim((string)($boatPayload['bootnummer'] ?? ''));
    $boatNumber = $boatNumberRaw === '' ? null : $boatNumberRaw;
    $boatNotes = isset($boatPayload['notizen']) ? trim((string)$boatPayload['notizen']) : null;
    $boatNotes = $boatNotes === '' ? null : $boatNotes;

    ensure_boats_table_exists();

    $boatTable = boats_table();
    $pdo = get_pdo();

    $stmt = $pdo->prepare("INSERT INTO {$boatTable} (lizenznehmer_id, bootnummer, notizen) VALUES (NULL, :nummer, :notizen)");
    $stmt->execute([
        'nummer' => $boatNumber,
        'notizen' => $boatNotes !== null ? $boatNotes : null,
    ]);

    echo json_encode(['success' => true]);
}

function update_boat(): void
{
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        echo json_encode(['success' => false, 'message' => 'Ungültige Daten.']);
        return;
    }

    $boatPayload = $data['boat'] ?? [];
    $boatId = (int)($boatPayload['id'] ?? 0);
    $boatNumberRaw = trim((string)($boatPayload['bootnummer'] ?? ''));
    $boatNumber = $boatNumberRaw === '' ? null : $boatNumberRaw;
    $boatNotes = isset($boatPayload['notizen']) ? trim((string)$boatPayload['notizen']) : null;
    $boatNotes = $boatNotes === '' ? null : $boatNotes;

    if ($boatId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Ungültige Daten.']);
        return;
    }

    ensure_boats_table_exists();
    $boatTable = boats_table();
    $pdo = get_pdo();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$boatTable} WHERE id = :id");
    $stmt->execute(['id' => $boatId]);
    if (!$stmt->fetchColumn()) {
        echo json_encode(['success' => false, 'message' => 'Boot wurde nicht gefunden.']);
        return;
    }

    $stmt = $pdo->prepare("UPDATE {$boatTable} SET bootnummer = :nummer, notizen = :notizen WHERE id = :id");
    $stmt->execute([
        'nummer' => $boatNumber,
        'notizen' => $boatNotes !== null ? $boatNotes : null,
        'id' => $boatId,
    ]);

    echo json_encode(['success' => true]);
}

function delete_boat(): void
{
    $data = json_decode(file_get_contents('php://input'), true);
    $boatId = (int)($data['boat_id'] ?? 0);
    if ($boatId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Ungültige Daten.']);
        return;
    }

    ensure_boats_table_exists();
    $boatTable = boats_table();
    $pdo = get_pdo();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$boatTable} WHERE id = :id");
    $stmt->execute(['id' => $boatId]);
    if (!$stmt->fetchColumn()) {
        echo json_encode(['success' => false, 'message' => 'Boot wurde nicht gefunden.']);
        return;
    }

    $stmt = $pdo->prepare("DELETE FROM {$boatTable} WHERE id = :id");
    $stmt->execute(['id' => $boatId]);

    echo json_encode(['success' => true]);
}

function get_boat_licenses(): void
{
    $pdo = get_pdo();
    ensure_boats_table_exists();
    $boatsTable = boats_table();

    $yearParam = $_GET['jahr'] ?? $_POST['jahr'] ?? null;
    $year = $yearParam !== null && $yearParam !== '' ? (int)$yearParam : null;
    if (!$year) {
        $year = latest_year();
    }
    if (!$year) {
        $year = (int)date('Y');
    }

    ensure_year_exists($year);
    $licenseTable = license_table($year);

    $boatTypeList = quote_list($pdo, boat_license_types());

    $sql = "SELECT DISTINCT ln.id, ln.vorname, ln.nachname, ln.telefon, ln.email,
                   (SELECT b2.id FROM {$boatsTable} b2 WHERE b2.lizenznehmer_id = ln.id ORDER BY b2.id ASC LIMIT 1) AS boat_id,
                   (SELECT b2.bootnummer FROM {$boatsTable} b2 WHERE b2.lizenznehmer_id = ln.id ORDER BY b2.id ASC LIMIT 1) AS bootnummer
            FROM {$licenseTable} l
            JOIN lizenznehmer ln ON ln.id = l.lizenznehmer_id
            WHERE l.lizenztyp IN ({$boatTypeList})
            ORDER BY ln.nachname, ln.vorname, ln.id";

    $stmt = $pdo->query($sql);
    $licensees = $stmt->fetchAll();

    echo json_encode(['success' => true, 'licensees' => $licensees]);
}

function assign_boat_license(): void
{
    $data = json_decode(file_get_contents('php://input'), true);
    $boatId = (int)($data['boat_id'] ?? 0);
    $licenseeId = isset($data['licensee_id']) && $data['licensee_id'] !== null ? (int)$data['licensee_id'] : null;

    if ($boatId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Ungültige Daten.']);
        return;
    }

    ensure_boats_table_exists();
    $boatTable = boats_table();
    $pdo = get_pdo();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$boatTable} WHERE id = :id");
    $stmt->execute(['id' => $boatId]);
    if (!$stmt->fetchColumn()) {
        echo json_encode(['success' => false, 'message' => 'Boot wurde nicht gefunden.']);
        return;
    }

    if ($licenseeId !== null) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM lizenznehmer WHERE id = :id');
        $stmt->execute(['id' => $licenseeId]);
        if (!$stmt->fetchColumn()) {
            echo json_encode(['success' => false, 'message' => 'Lizenznehmer wurde nicht gefunden.']);
            return;
        }
    }

    $pdo->beginTransaction();
    try {
        if ($licenseeId !== null) {
            $stmt = $pdo->prepare("UPDATE {$boatTable} SET lizenznehmer_id = NULL WHERE lizenznehmer_id = :licensee_id AND id <> :boat_id");
            $stmt->execute([
                'licensee_id' => $licenseeId,
                'boat_id' => $boatId,
            ]);
        }

        $stmt = $pdo->prepare("UPDATE {$boatTable} SET lizenznehmer_id = :licensee_id WHERE id = :boat_id");
        $stmt->execute([
            'licensee_id' => $licenseeId,
            'boat_id' => $boatId,
        ]);

        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function delete_license(): void
{
    $data = json_decode(file_get_contents('php://input'), true);
    $year = (int)($data['year'] ?? 0);
    $licenseId = (int)($data['license_id'] ?? 0);
    if ($year < 2000 || $licenseId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Ungültige Daten.']);
        return;
    }
    $licenseTable = license_table($year);
    ensure_year_exists($year);

    if (is_year_closed($year)) {
        echo json_encode(['success' => false, 'message' => 'Dieses Jahr wurde bereits abgeschlossen und kann nicht mehr bearbeitet werden.']);
        return;
    }

    $pdo = get_pdo();
    $stmt = $pdo->prepare("DELETE FROM {$licenseTable} WHERE id = :id");
    $stmt->execute(['id' => $licenseId]);
    echo json_encode(['success' => true]);
}

function move_license(): void
{
    $data = json_decode(file_get_contents('php://input'), true);
    $fromYear = (int)($data['from_year'] ?? 0);
    $toYear = (int)($data['to_year'] ?? 0);
    $licenseId = (int)($data['license_id'] ?? 0);
    $force = !empty($data['force']);
    if ($fromYear < 2000 || $toYear < 2000 || $licenseId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Ungültige Daten.']);
        return;
    }

    $fromTable = license_table($fromYear);
    $toTable = license_table($toYear);
    ensure_year_exists($toYear);
    ensure_year_license_number_column($toYear);

    if (is_year_closed($toYear)) {
        echo json_encode(['success' => false, 'message' => 'Das Zieljahr wurde bereits abgeschlossen und kann keine Änderungen mehr aufnehmen.']);
        return;
    }

    $pdo = get_pdo();

    $stmt = $pdo->prepare("SELECT * FROM {$fromTable} WHERE id = :id");
    $stmt->execute(['id' => $licenseId]);
    $row = $stmt->fetch();
    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Lizenz nicht gefunden.']);
        return;
    }

    $licenseeId = isset($row['lizenznehmer_id']) ? (int)$row['lizenznehmer_id'] : 0;
    if ($licenseeId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Lizenznehmer für die Verlängerung wurde nicht gefunden.']);
        return;
    }

    $type = $data['lizenztyp'] ?? $row['lizenztyp'];
    if (!is_valid_license_type($type)) {
        $type = $row['lizenztyp'];
    }

    $licenseNumberValidation = validate_year_license_number_input($data['lizenznummer'] ?? null);
    if ($licenseNumberValidation['error']) {
        echo json_encode(['success' => false, 'message' => $licenseNumberValidation['error']]);
        return;
    }

    $lizenznummer = (int)$licenseNumberValidation['value'];
    if (has_duplicate_yearly_license_number($pdo, $toYear, $lizenznummer, $type) && !$force) {
        echo json_encode([
            'success' => false,
            'duplicate' => true,
            'message' => duplicate_license_number_message($type, 'im Zieljahr'),
            'license_number' => $lizenznummer,
        ]);
        return;
    }

    $gesamt = (float)$data['kosten'] + (float)$data['trinkgeld'];

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO {$toTable} (lizenznehmer_id, lizenznummer, lizenztyp, kosten, trinkgeld, gesamt, zahlungsdatum, notizen) VALUES (:licensee_id, :lizenznummer, :typ, :kosten, :trinkgeld, :gesamt, :datum, :notizen)");
        $stmt->execute([
            'licensee_id' => $row['lizenznehmer_id'],
            'lizenznummer' => $lizenznummer,
            'typ' => $type,
            'kosten' => $data['kosten'],
            'trinkgeld' => $data['trinkgeld'],
            'gesamt' => $gesamt,
            'datum' => $data['zahlungsdatum'] ?: null,
            'notizen' => $data['notizen'] ?? null,
        ]);
        $newLicenseId = (int)$pdo->lastInsertId();

        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function create_year(): void
{
    $data = json_decode(file_get_contents('php://input'), true);
    $year = (int)($data['year'] ?? 0);
    if ($year < 2000) {
        echo json_encode(['success' => false, 'message' => 'Ungültiges Jahr.']);
        return;
    }

    if (in_array($year, available_years(), true)) {
        echo json_encode(['success' => false, 'message' => 'Dieses Jahr existiert bereits.']);
        return;
    }

    ensure_year_exists($year);

    $pdo = get_pdo();
    ensure_license_price_enum($pdo);
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('DELETE FROM lizenzpreise WHERE jahr = :jahr');
        $stmt->execute(['jahr' => $year]);
        $stmt = $pdo->prepare('INSERT INTO lizenzpreise (jahr, lizenztyp, preis) VALUES (:jahr, :typ, :preis)');
        $allowedTypes = license_types();
        foreach ($data['preise'] as $type => $price) {
            if (!in_array($type, $allowedTypes, true)) {
                continue;
            }
            $stmt->execute([
                'jahr' => $year,
                'typ' => $type,
                'preis' => $price,
            ]);
        }
        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function delete_year(): void
{
    $data = json_decode(file_get_contents('php://input'), true);
    $year = (int)($data['year'] ?? 0);
    if ($year < 2000) {
        echo json_encode(['success' => false, 'message' => 'Ungültiges Jahr.']);
        return;
    }

    if (!in_array($year, available_years(), true)) {
        echo json_encode(['success' => false, 'message' => 'Dieses Jahr existiert nicht.']);
        return;
    }

    if (is_year_closed($year)) {
        echo json_encode(['success' => false, 'message' => 'Abgeschlossene Jahre können nicht gelöscht werden.']);
        return;
    }

    $pdo = get_pdo();
    $licenseTable = license_table($year);
    $quotedLicenseTable = sprintf('`%s`', str_replace('`', '``', $licenseTable));

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    try {
        $pdo->exec("DROP TABLE IF EXISTS {$quotedLicenseTable}");
    } finally {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    $stmt = $pdo->prepare('DELETE FROM lizenzpreise WHERE jahr = :jahr');
    $stmt->execute(['jahr' => $year]);

    echo json_encode(['success' => true]);
}

function close_year(): void
{
    $data = json_decode(file_get_contents('php://input'), true);
    $year = (int)($data['year'] ?? 0);
    if ($year < 2000) {
        echo json_encode(['success' => false, 'message' => 'Ungültiges Jahr.']);
        return;
    }

    if (!in_array($year, available_years(), true)) {
        echo json_encode(['success' => false, 'message' => 'Dieses Jahr existiert nicht.']);
        return;
    }

    if (is_year_closed($year)) {
        echo json_encode(['success' => false, 'message' => 'Dieses Jahr wurde bereits abgeschlossen.']);
        return;
    }

    ensure_year_exists($year);
    ensure_year_closure_table_exists();

    $overview = get_year_overview($year);
    $notesRaw = isset($data['notes']) ? trim((string)$data['notes']) : '';
    $notes = $notesRaw !== '' ? $notesRaw : null;

    $pdo = get_pdo();
    $stmt = $pdo->prepare('INSERT INTO jahresabschluesse (jahr, abgeschlossen_am, anzahl_lizenzen, gesamt_kosten, gesamt_trinkgeld, gesamt_einnahmen, notizen) VALUES (:jahr, NOW(), :anzahl, :kosten, :trinkgeld, :gesamt, :notizen)');
    $stmt->execute([
        'jahr' => $year,
        'anzahl' => $overview['total_count'] ?? 0,
        'kosten' => $overview['total_cost'] ?? 0,
        'trinkgeld' => $overview['total_tip'] ?? 0,
        'gesamt' => $overview['total_combined'] ?? 0,
        'notizen' => $notes,
    ]);

    $closure = get_year_closure($year);
    if ($closure) {
        $closure['abgeschlossen_am_formatted'] = format_datetime($closure['abgeschlossen_am'] ?? null);
    }

    echo json_encode([
        'success' => true,
        'closure' => $closure,
    ]);
}

function year_overview(): void
{
    $year = (int)($_GET['year'] ?? 0);
    if ($year < 2000) {
        echo json_encode(['success' => false, 'message' => 'Ungültiges Jahr.']);
        return;
    }

    if (!in_array($year, available_years(), true)) {
        echo json_encode(['success' => false, 'message' => 'Dieses Jahr existiert nicht.']);
        return;
    }

    $overview = get_year_overview($year);
    echo json_encode(['success' => true, 'overview' => $overview]);
}

function get_prices(): void
{
    $year = (int)($_GET['year'] ?? 0);
    if ($year < 2000) {
        echo json_encode(['success' => false]);
        return;
    }
    $prices = get_license_prices($year);
    echo json_encode(['success' => true, 'preise' => $prices]);
}

function search_licensees(): void
{
    $query = trim((string)($_GET['query'] ?? ($_POST['query'] ?? '')));

    if ($query === '') {
        echo json_encode(['success' => false, 'message' => 'Bitte einen Suchbegriff angeben.']);
        return;
    }

    $length = function_exists('mb_strlen') ? mb_strlen($query, 'UTF-8') : strlen($query);
    if ($length < 2) {
        echo json_encode(['success' => false, 'message' => 'Bitte mindestens zwei Zeichen eingeben.']);
        return;
    }

    $lower = function_exists('mb_strtolower')
        ? mb_strtolower($query, 'UTF-8')
        : strtolower($query);

    $searchTerm = '%' . $lower . '%';

    $pdo = get_pdo();
    ensure_person_birthdate_columns();
    ensure_licensee_key_columns();
    $years = available_years();
    $matchingLicenseIds = [];

    foreach ($years as $year) {
        ensure_year_license_number_column((int)$year, $pdo);
        $licenseTable = license_table((int)$year);
        $licenseNumberStmt = $pdo->prepare("SELECT DISTINCT lizenznehmer_id FROM {$licenseTable} WHERE lizenznummer IS NOT NULL AND CAST(lizenznummer AS CHAR) LIKE :term");
        $licenseNumberStmt->execute(['term' => $searchTerm]);
        while (($matchedLicenseeId = $licenseNumberStmt->fetchColumn()) !== false) {
            $licenseeId = (int)$matchedLicenseeId;
            if ($licenseeId > 0) {
                $matchingLicenseIds[$licenseeId] = $licenseeId;
            }
        }
    }

    $sql = "SELECT id, vorname, nachname, geburtsdatum, strasse, plz, ort, telefon, email, fischerkartennummer, schluessel_ausgegeben, schluessel_ausgegeben_am
            FROM lizenznehmer
            WHERE LOWER(COALESCE(vorname, '')) LIKE :term
               OR LOWER(COALESCE(nachname, '')) LIKE :term
               OR LOWER(CONCAT_WS(' ', COALESCE(vorname, ''), COALESCE(nachname, ''))) LIKE :term
               OR LOWER(COALESCE(fischerkartennummer, '')) LIKE :term
               OR LOWER(COALESCE(strasse, '')) LIKE :term
               OR LOWER(COALESCE(ort, '')) LIKE :term
               OR LOWER(COALESCE(plz, '')) LIKE :term
            ORDER BY nachname, vorname, id
            LIMIT 50";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['term' => $searchTerm]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $rowsById = [];
    foreach ($rows as $row) {
        $rowId = isset($row['id']) ? (int)$row['id'] : 0;
        if ($rowId > 0) {
            $rowsById[$rowId] = $row;
        }
    }

    $missingLicenseIds = array_values(array_diff(array_values($matchingLicenseIds), array_keys($rowsById)));
    if ($missingLicenseIds) {
        $placeholders = implode(',', array_fill(0, count($missingLicenseIds), '?'));
        $missingStmt = $pdo->prepare(
            "SELECT id, vorname, nachname, geburtsdatum, strasse, plz, ort, telefon, email, fischerkartennummer, schluessel_ausgegeben, schluessel_ausgegeben_am
             FROM lizenznehmer
             WHERE id IN ({$placeholders})"
        );
        $missingStmt->execute($missingLicenseIds);
        foreach ($missingStmt->fetchAll(PDO::FETCH_ASSOC) as $missingRow) {
            $rowId = isset($missingRow['id']) ? (int)$missingRow['id'] : 0;
            if ($rowId > 0) {
                $rowsById[$rowId] = $missingRow;
            }
        }
    }

    if (!$rowsById) {
        echo json_encode(['success' => true, 'results' => []]);
        return;
    }

    $rows = array_values($rowsById);
    usort($rows, static function (array $a, array $b): int {
        $lastNameCompare = strcasecmp((string)($a['nachname'] ?? ''), (string)($b['nachname'] ?? ''));
        if ($lastNameCompare !== 0) {
            return $lastNameCompare;
        }

        $firstNameCompare = strcasecmp((string)($a['vorname'] ?? ''), (string)($b['vorname'] ?? ''));
        if ($firstNameCompare !== 0) {
            return $firstNameCompare;
        }

        return ((int)($a['id'] ?? 0)) <=> ((int)($b['id'] ?? 0));
    });

    $results = [];
    $orderedIds = [];
    $licenseeIds = [];

    foreach ($rows as $row) {
        $id = isset($row['id']) ? (int)$row['id'] : 0;
        if ($id <= 0) {
            continue;
        }

        $birthdate = $row['geburtsdatum'] ?? null;
        $formattedBirthdate = format_date($birthdate);
        $age = calculate_age($birthdate);

        $results[$id] = [
            'id' => $id,
            'vorname' => $row['vorname'] ?? null,
            'nachname' => $row['nachname'] ?? null,
            'geburtsdatum' => $birthdate ?: null,
            'geburtsdatum_formatted' => $formattedBirthdate,
            'alter' => $age,
            'strasse' => $row['strasse'] ?? null,
            'plz' => $row['plz'] ?? null,
            'ort' => $row['ort'] ?? null,
            'telefon' => $row['telefon'] ?? null,
            'email' => $row['email'] ?? null,
            'fischerkartennummer' => $row['fischerkartennummer'] ?? null,
            'schluessel_ausgegeben' => !empty($row['schluessel_ausgegeben']),
            'schluessel_ausgegeben_am' => ($row['schluessel_ausgegeben_am'] ?? '') !== '' ? $row['schluessel_ausgegeben_am'] : null,
            'schluessel_ausgegeben_am_formatted' => format_date(($row['schluessel_ausgegeben_am'] ?? '') !== '' ? $row['schluessel_ausgegeben_am'] : null),
            'licenses' => [],
        ];

        $orderedIds[] = $id;
        $licenseeIds[] = $id;
    }

    if (!$results) {
        echo json_encode(['success' => true, 'results' => []]);
        return;
    }

    $licenseeIds = array_values(array_unique($licenseeIds));

    if ($years && $licenseeIds) {
        rsort($years);
        $placeholders = implode(',', array_fill(0, count($licenseeIds), '?'));

        foreach ($years as $year) {
            $licenseTable = license_table((int)$year);
            ensure_year_license_number_column((int)$year, $pdo);
            $querySql = "SELECT id, lizenznehmer_id, lizenznummer, lizenztyp, kosten, trinkgeld, gesamt, zahlungsdatum, notizen
                         FROM {$licenseTable}
                         WHERE lizenznehmer_id IN ({$placeholders})";

            $licenseStmt = $pdo->prepare($querySql);
            $licenseStmt->execute($licenseeIds);

            while ($licenseRow = $licenseStmt->fetch(PDO::FETCH_ASSOC)) {
                $licenseeId = isset($licenseRow['lizenznehmer_id']) ? (int)$licenseRow['lizenznehmer_id'] : 0;
                if (!$licenseeId || !isset($results[$licenseeId])) {
                    continue;
                }

                $licenseId = isset($licenseRow['id']) ? (int)$licenseRow['id'] : null;
                $cost = isset($licenseRow['kosten']) ? (float)$licenseRow['kosten'] : null;
                $tip = isset($licenseRow['trinkgeld']) ? (float)$licenseRow['trinkgeld'] : null;
                $total = isset($licenseRow['gesamt']) ? (float)$licenseRow['gesamt'] : null;
                $paymentDate = $licenseRow['zahlungsdatum'] ?? null;

                $results[$licenseeId]['licenses'][] = [
                    'jahr' => (int)$year,
                    'lizenz_id' => $licenseId,
                    'lizenznummer' => isset($licenseRow['lizenznummer']) ? (int)$licenseRow['lizenznummer'] : null,
                    'lizenztyp' => $licenseRow['lizenztyp'] ?? null,
                    'kosten' => $cost,
                    'kosten_formatted' => $cost !== null ? format_currency($cost) : null,
                    'trinkgeld' => $tip,
                    'trinkgeld_formatted' => $tip !== null ? format_currency($tip) : null,
                    'gesamt' => $total,
                    'gesamt_formatted' => $total !== null ? format_currency($total) : null,
                    'zahlungsdatum' => $paymentDate ?: null,
                    'zahlungsdatum_formatted' => format_date($paymentDate ?: null),
                    'notizen' => $licenseRow['notizen'] ?? null,
                ];
            }
        }
    }

    foreach ($results as &$licensee) {
        if (!empty($licensee['licenses'])) {
            usort($licensee['licenses'], function (array $a, array $b): int {
                $yearCompare = ($b['jahr'] ?? 0) <=> ($a['jahr'] ?? 0);
                if ($yearCompare !== 0) {
                    return $yearCompare;
                }

                $dateA = $a['zahlungsdatum'] ?? null;
                $dateB = $b['zahlungsdatum'] ?? null;

                if ($dateA && $dateB && $dateA !== $dateB) {
                    return strcmp($dateB, $dateA);
                }
                if ($dateA && !$dateB) {
                    return -1;
                }
                if (!$dateA && $dateB) {
                    return 1;
                }

                return ($b['lizenz_id'] ?? 0) <=> ($a['lizenz_id'] ?? 0);
            });
            $licensee['licenses'] = array_values($licensee['licenses']);
        }
    }
    unset($licensee);

    $orderedResults = [];
    foreach ($orderedIds as $id) {
        if (isset($results[$id])) {
            $orderedResults[] = $results[$id];
        }
    }

    echo json_encode([
        'success' => true,
        'results' => $orderedResults,
    ], JSON_UNESCAPED_UNICODE);
}

function format_licensee_key_payload(array $licensee, ?array $activeHistory = null): array
{
    $activeIssueDate = ($activeHistory['schluessel_ausgegeben_am'] ?? '') !== ''
        ? $activeHistory['schluessel_ausgegeben_am']
        : null;
    $cacheIssueDate = ($licensee['schluessel_ausgegeben_am'] ?? '') !== ''
        ? $licensee['schluessel_ausgegeben_am']
        : null;
    $hasActiveKey = $activeHistory !== null || !empty($licensee['schluessel_ausgegeben']);
    $issueDate = $activeIssueDate ?? ($hasActiveKey ? $cacheIssueDate : null);

    return [
        'id' => isset($licensee['id']) ? (int)$licensee['id'] : 0,
        'vorname' => $licensee['vorname'] ?? null,
        'nachname' => $licensee['nachname'] ?? null,
        'schluessel_ausgegeben' => $hasActiveKey,
        'schluessel_ausgegeben_am' => $issueDate,
        'schluessel_ausgegeben_am_formatted' => format_date($issueDate),
    ];
}

function format_licensee_key_history_entry(array $entry): array
{
    $issueDate = ($entry['schluessel_ausgegeben_am'] ?? '') !== ''
        ? $entry['schluessel_ausgegeben_am']
        : null;
    $returnDate = ($entry['schluessel_zurueckgegeben_am'] ?? '') !== ''
        ? $entry['schluessel_zurueckgegeben_am']
        : null;

    return [
        'id' => isset($entry['id']) ? (int)$entry['id'] : 0,
        'lizenznehmer_id' => isset($entry['lizenznehmer_id']) ? (int)$entry['lizenznehmer_id'] : 0,
        'schluessel_ausgegeben_am' => $issueDate,
        'schluessel_ausgegeben_am_formatted' => format_date($issueDate),
        'schluessel_zurueckgegeben_am' => $returnDate,
        'schluessel_zurueckgegeben_am_formatted' => format_date($returnDate),
        'offen' => $returnDate === null,
        'erstellt_am' => $entry['erstellt_am'] ?? null,
        'aktualisiert_am' => $entry['aktualisiert_am'] ?? null,
    ];
}

function fetch_licensee_key_record(PDO $pdo, int $licenseeId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, vorname, nachname, schluessel_ausgegeben, schluessel_ausgegeben_am
         FROM lizenznehmer
         WHERE id = :id'
    );
    $stmt->execute(['id' => $licenseeId]);
    $licensee = $stmt->fetch(PDO::FETCH_ASSOC);

    return $licensee ?: null;
}

function fetch_active_licensee_key_history(PDO $pdo, int $licenseeId): ?array
{
    $historyTable = licensee_key_history_table();
    $stmt = $pdo->prepare(
        "SELECT id, lizenznehmer_id, schluessel_ausgegeben_am, schluessel_zurueckgegeben_am, erstellt_am, aktualisiert_am
         FROM {$historyTable}
         WHERE lizenznehmer_id = :licensee_id
           AND schluessel_zurueckgegeben_am IS NULL
         ORDER BY id DESC
         LIMIT 1"
    );
    $stmt->execute(['licensee_id' => $licenseeId]);
    $entry = $stmt->fetch(PDO::FETCH_ASSOC);

    return $entry ?: null;
}

function fetch_licensee_key_history_entries(PDO $pdo, int $licenseeId): array
{
    $historyTable = licensee_key_history_table();
    $stmt = $pdo->prepare(
        "SELECT id, lizenznehmer_id, schluessel_ausgegeben_am, schluessel_zurueckgegeben_am, erstellt_am, aktualisiert_am
         FROM {$historyTable}
         WHERE lizenznehmer_id = :licensee_id
         ORDER BY schluessel_ausgegeben_am DESC, id DESC"
    );
    $stmt->execute(['licensee_id' => $licenseeId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_licensee_key_history(): void
{
    $licenseeId = isset($_GET['licensee_id']) ? (int)$_GET['licensee_id'] : 0;
    if ($licenseeId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Ungültige Lizenznehmer-ID.']);
        return;
    }

    ensure_licensee_key_columns();

    $pdo = get_pdo();
    $licensee = fetch_licensee_key_record($pdo, $licenseeId);
    if (!$licensee) {
        echo json_encode(['success' => false, 'message' => 'Lizenznehmer nicht gefunden.']);
        return;
    }

    $activeHistory = fetch_active_licensee_key_history($pdo, $licenseeId);
    $historyEntries = fetch_licensee_key_history_entries($pdo, $licenseeId);

    echo json_encode([
        'success' => true,
        'licensee' => format_licensee_key_payload($licensee, $activeHistory),
        'active_history' => $activeHistory ? format_licensee_key_history_entry($activeHistory) : null,
        'history' => array_map('format_licensee_key_history_entry', $historyEntries),
    ], JSON_UNESCAPED_UNICODE);
}

function save_licensee_key(): void
{
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        echo json_encode(['success' => false, 'message' => 'Ungültige Daten.']);
        return;
    }

    $licenseeId = isset($data['licensee_id']) ? (int)$data['licensee_id'] : 0;
    if ($licenseeId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Ungültige Lizenznehmer-ID.']);
        return;
    }

    ensure_licensee_key_columns();

    $keyGivenRaw = $data['schluessel_ausgegeben'] ?? false;
    $keyGiven = filter_var($keyGivenRaw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    $schluesselAusgegeben = $keyGiven === null ? !empty($keyGivenRaw) : $keyGiven;

    $issueDateValidation = validate_birthdate_input($data['schluessel_ausgegeben_am'] ?? null, 'Ausgegeben am');
    if ($issueDateValidation['error']) {
        echo json_encode(['success' => false, 'message' => $issueDateValidation['error']]);
        return;
    }

    $returnDateValidation = validate_birthdate_input($data['schluessel_zurueckgegeben_am'] ?? null, 'Zurückgegeben am');
    if ($returnDateValidation['error']) {
        echo json_encode(['success' => false, 'message' => $returnDateValidation['error']]);
        return;
    }

    $pdo = get_pdo();
    $licensee = fetch_licensee_key_record($pdo, $licenseeId);
    if (!$licensee) {
        echo json_encode(['success' => false, 'message' => 'Lizenznehmer nicht gefunden.']);
        return;
    }

    $historyTable = licensee_key_history_table();

    try {
        $pdo->beginTransaction();
        $lockStmt = $pdo->prepare('SELECT id FROM lizenznehmer WHERE id = :id FOR UPDATE');
        $lockStmt->execute(['id' => $licenseeId]);

        $activeHistory = fetch_active_licensee_key_history($pdo, $licenseeId);

        if ($schluesselAusgegeben) {
            $issueDate = $issueDateValidation['value'] ?? app_today_string();

            if ($activeHistory) {
                $updateHistoryStmt = $pdo->prepare(
                    "UPDATE {$historyTable}
                     SET schluessel_ausgegeben_am = :issue_date
                     WHERE id = :id"
                );
                $updateHistoryStmt->execute([
                    'issue_date' => $issueDate,
                    'id' => $activeHistory['id'],
                ]);
            } else {
                $insertHistoryStmt = $pdo->prepare(
                    "INSERT INTO {$historyTable} (lizenznehmer_id, schluessel_ausgegeben_am)
                     VALUES (:licensee_id, :issue_date)"
                );
                $insertHistoryStmt->execute([
                    'licensee_id' => $licenseeId,
                    'issue_date' => $issueDate,
                ]);
            }

            $updateLicenseeStmt = $pdo->prepare(
                'UPDATE lizenznehmer
                 SET schluessel_ausgegeben = 1,
                     schluessel_ausgegeben_am = :issue_date
                 WHERE id = :id'
            );
            $updateLicenseeStmt->execute([
                'issue_date' => $issueDate,
                'id' => $licenseeId,
            ]);
        } else {
            if (!$activeHistory) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Für diesen Lizenznehmer ist aktuell kein Schlüssel ausgegeben.']);
                return;
            }

            $issueDate = $issueDateValidation['value']
                ?? (($activeHistory['schluessel_ausgegeben_am'] ?? '') !== '' ? $activeHistory['schluessel_ausgegeben_am'] : app_today_string());
            $returnDate = $returnDateValidation['value'] ?? app_today_string();

            if ($returnDate < $issueDate) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Zurückgegeben am darf nicht vor Ausgegeben am liegen.']);
                return;
            }

            $updateHistoryStmt = $pdo->prepare(
                "UPDATE {$historyTable}
                 SET schluessel_ausgegeben_am = :issue_date,
                     schluessel_zurueckgegeben_am = :return_date
                 WHERE id = :id"
            );
            $updateHistoryStmt->execute([
                'issue_date' => $issueDate,
                'return_date' => $returnDate,
                'id' => $activeHistory['id'],
            ]);

            $updateLicenseeStmt = $pdo->prepare(
                'UPDATE lizenznehmer
                 SET schluessel_ausgegeben = 0,
                     schluessel_ausgegeben_am = NULL
                 WHERE id = :id'
            );
            $updateLicenseeStmt->execute([
                'id' => $licenseeId,
            ]);
        }

        $pdo->commit();
    } catch (Throwable $throwable) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $throwable;
    }

    $updatedLicensee = fetch_licensee_key_record($pdo, $licenseeId);
    $updatedActiveHistory = fetch_active_licensee_key_history($pdo, $licenseeId);

    echo json_encode([
        'success' => true,
        'message' => 'Schlüsselstatus gespeichert.',
        'licensee' => format_licensee_key_payload($updatedLicensee ?: $licensee, $updatedActiveHistory),
    ], JSON_UNESCAPED_UNICODE);
}

function get_blocklist(): void
{
    $entries = get_blocklist_entries();
    echo json_encode(['success' => true, 'entries' => $entries]);
}

function save_block_entry(): void
{
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['entry'])) {
        echo json_encode(['success' => false, 'message' => 'Ungültige Daten.']);
        return;
    }

    $entry = $data['entry'];
    $vorname = trim((string)($entry['vorname'] ?? ''));
    $nachname = trim((string)($entry['nachname'] ?? ''));
    $lizenznummer = trim((string)($entry['lizenznummer'] ?? ''));
    $notiz = trim((string)($entry['notiz'] ?? ''));
    $id = isset($entry['id']) ? (int)$entry['id'] : 0;

    if ($vorname === '' || $nachname === '') {
        echo json_encode(['success' => false, 'message' => 'Vor- und Nachname sind erforderlich.']);
        return;
    }

    save_blocklist_entry([
        'id' => $id,
        'vorname' => $vorname,
        'nachname' => $nachname,
        'lizenznummer' => $lizenznummer,
        'notiz' => $notiz,
    ]);

    echo json_encode(['success' => true]);
}

function delete_block_entry(): void
{
    $data = json_decode(file_get_contents('php://input'), true);
    $id = isset($data['id']) ? (int)$data['id'] : 0;
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Ungültige ID.']);
        return;
    }

    $success = delete_blocklist_entry($id);
    echo json_encode(['success' => $success]);
}

function assign_newcomer(): void
{
    $data = json_decode(file_get_contents('php://input'), true);
    $applicantId = (int)($data['applicant_id'] ?? 0);
    $year = (int)($data['year'] ?? 0);
    $type = $data['license_type'] ?? '';
    $cost = isset($data['kosten']) ? (float)$data['kosten'] : null;
    $tip = isset($data['trinkgeld']) ? (float)$data['trinkgeld'] : null;
    $date = $data['zahlungsdatum'] ?? null;
    $notes = $data['notizen'] ?? null;

    $force = !empty($data['force']);
    if ($applicantId <= 0 || $year < 2000 || !is_valid_license_type($type) || $cost === null || $tip === null) {
        echo json_encode(['success' => false, 'message' => 'Ungültige Daten.']);
        return;
    }

    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT * FROM bewerber WHERE id = :id');
    $stmt->execute(['id' => $applicantId]);
    $applicant = $stmt->fetch();
    if (!$applicant) {
        echo json_encode(['success' => false, 'message' => 'Neuwerber nicht gefunden.']);
        return;
    }

    $blockedEntry = find_blocklist_entry(
        (string)($applicant['vorname'] ?? ''),
        (string)($applicant['nachname'] ?? ''),
        isset($applicant['fischerkartennummer']) && $applicant['fischerkartennummer'] !== '' ? (string)$applicant['fischerkartennummer'] : null
    );
    if ($blockedEntry && !$force) {
        echo json_encode([
            'success' => false,
            'blocked' => true,
            'entry' => $blockedEntry,
            'message' => 'Die Person steht auf der Sperrliste.'
        ]);
        return;
    }

    $availableYears = available_years();
    if (!in_array($year, $availableYears, true)) {
        echo json_encode(['success' => false, 'message' => 'Dieses Jahr existiert nicht. Bitte im Adminbereich anlegen.']);
        return;
    }

    ensure_year_exists($year);
    ensure_person_birthdate_columns();
    ensure_year_license_number_column($year, $pdo);
    $licenseTable = license_table($year);

    if (is_year_closed($year)) {
        echo json_encode(['success' => false, 'message' => 'Dieses Jahr wurde bereits abgeschlossen und kann keine neuen Lizenzen mehr aufnehmen.']);
        return;
    }

    $licenseNumberValidation = array_key_exists('lizenznummer', $data)
        ? validate_year_license_number_input($data['lizenznummer'])
        : ['value' => get_next_license_number_for_year($year, $type, $pdo), 'error' => null];
    if ($licenseNumberValidation['error']) {
        echo json_encode(['success' => false, 'message' => $licenseNumberValidation['error']]);
        return;
    }

    $lizenznummer = (int)$licenseNumberValidation['value'];
    if (has_duplicate_yearly_license_number($pdo, $year, $lizenznummer, $type) && !$force) {
        echo json_encode([
            'success' => false,
            'duplicate' => true,
            'message' => duplicate_license_number_message($type, 'in diesem Jahr'),
            'license_number' => $lizenznummer,
        ]);
        return;
    }

    $pdo->beginTransaction();
    try {
        $birthdateData = validate_birthdate_input($applicant['geburtsdatum'] ?? null, 'Geburtsdatum');
        $applicantBirthdate = $birthdateData['error'] ? null : $birthdateData['value'];

        $stmt = $pdo->prepare('INSERT INTO lizenznehmer (vorname, nachname, geburtsdatum, strasse, plz, ort, telefon, email, fischerkartennummer) VALUES (:vorname, :nachname, :geburtsdatum, :strasse, :plz, :ort, :telefon, :email, :karte)');
        $stmt->execute([
            'vorname' => $applicant['vorname'] ?? '',
            'nachname' => $applicant['nachname'] ?? '',
            'geburtsdatum' => $applicantBirthdate,
            'strasse' => $applicant['strasse'] ?? null,
            'plz' => $applicant['plz'] ?? null,
            'ort' => $applicant['ort'] ?? null,
            'telefon' => $applicant['telefon'] ?? null,
            'email' => $applicant['email'] ?? null,
            'karte' => $applicant['fischerkartennummer'] ?? null,
        ]);
        $licenseeId = (int)$pdo->lastInsertId();

        $total = (float)$cost + (float)$tip;
        $stmt = $pdo->prepare("INSERT INTO {$licenseTable} (lizenznehmer_id, lizenznummer, lizenztyp, kosten, trinkgeld, gesamt, zahlungsdatum, notizen) VALUES (:licensee_id, :lizenznummer, :typ, :kosten, :trinkgeld, :gesamt, :datum, :notizen)");
        $stmt->execute([
            'licensee_id' => $licenseeId,
            'lizenznummer' => $lizenznummer,
            'typ' => $type,
            'kosten' => $cost,
            'trinkgeld' => $tip,
            'gesamt' => $total,
            'datum' => $date ?: null,
            'notizen' => $notes ?: null,
        ]);

        $stmt = $pdo->prepare('DELETE FROM bewerber WHERE id = :id');
        $stmt->execute(['id' => $applicantId]);

        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function create_newcomer(): void
{
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        echo json_encode(['success' => false, 'message' => 'Ungültige Daten.']);
        return;
    }

    $force = !empty($data['force']);

    $firstName = trim((string)($data['vorname'] ?? ''));
    $lastName = trim((string)($data['nachname'] ?? ''));
    if ($firstName === '' || $lastName === '') {
        echo json_encode(['success' => false, 'message' => 'Vor- und Nachname sind erforderlich.']);
        return;
    }

    ensure_person_birthdate_columns();

    $street = trim((string)($data['strasse'] ?? '')) ?: null;
    $zip = trim((string)($data['plz'] ?? '')) ?: null;
    $city = trim((string)($data['ort'] ?? '')) ?: null;
    $phone = trim((string)($data['telefon'] ?? '')) ?: null;
    $emailRaw = trim((string)($data['email'] ?? ''));
    $email = $emailRaw !== '' ? $emailRaw : null;
    $cardRaw = trim((string)($data['fischerkartennummer'] ?? ''));
    $card = $cardRaw !== '' ? $cardRaw : null;
    $birthdateValidation = validate_birthdate_input($data['geburtsdatum'] ?? null, 'Geburtsdatum');
    if ($birthdateValidation['error']) {
        echo json_encode(['success' => false, 'message' => $birthdateValidation['error']]);
        return;
    }
    $birthdate = $birthdateValidation['value'];
    $date = trim((string)($data['bewerbungsdatum'] ?? '')) ?: null;
    $notes = trim((string)($data['notizen'] ?? '')) ?: null;

    if ($date && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        echo json_encode(['success' => false, 'message' => 'Ungültiges Bewerbungsdatum.']);
        return;
    }

    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Ungültige E-Mail-Adresse.']);
        return;
    }

    $blockedEntry = find_blocklist_entry($firstName, $lastName, $card);
    if ($blockedEntry && !$force) {
        echo json_encode([
            'success' => false,
            'blocked' => true,
            'entry' => $blockedEntry,
            'message' => 'Die Person steht auf der Sperrliste.'
        ]);
        return;
    }

    $pdo = get_pdo();
    $stmt = $pdo->prepare('INSERT INTO bewerber (vorname, nachname, geburtsdatum, strasse, plz, ort, telefon, email, fischerkartennummer, bewerbungsdatum, notizen) VALUES (:vorname, :nachname, :geburtsdatum, :strasse, :plz, :ort, :telefon, :email, :karte, :datum, :notizen)');
    $stmt->execute([
        'vorname' => $firstName,
        'nachname' => $lastName,
        'geburtsdatum' => $birthdate,
        'strasse' => $street,
        'plz' => $zip,
        'ort' => $city,
        'telefon' => $phone,
        'email' => $email,
        'karte' => $card,
        'datum' => $date ?: app_today_string(),
        'notizen' => $notes,
    ]);

    $id = (int)$pdo->lastInsertId();
    $stmt = $pdo->prepare('SELECT * FROM bewerber WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $applicant = $stmt->fetch();

    echo json_encode(['success' => true, 'bewerber' => $applicant]);
}

function update_newcomer(): void
{
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        echo json_encode(['success' => false, 'message' => 'Ungültige Daten.']);
        return;
    }

    $force = !empty($data['force']);

    $id = isset($data['id']) ? (int)$data['id'] : 0;
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Ungültige Neuwerber-ID.']);
        return;
    }

    $firstName = trim((string)($data['vorname'] ?? ''));
    $lastName = trim((string)($data['nachname'] ?? ''));
    if ($firstName === '' || $lastName === '') {
        echo json_encode(['success' => false, 'message' => 'Vor- und Nachname sind erforderlich.']);
        return;
    }

    ensure_person_birthdate_columns();

    $street = trim((string)($data['strasse'] ?? '')) ?: null;
    $zip = trim((string)($data['plz'] ?? '')) ?: null;
    $city = trim((string)($data['ort'] ?? '')) ?: null;
    $phone = trim((string)($data['telefon'] ?? '')) ?: null;
    $emailRaw = trim((string)($data['email'] ?? ''));
    $email = $emailRaw !== '' ? $emailRaw : null;
    $cardRaw = trim((string)($data['fischerkartennummer'] ?? ''));
    $card = $cardRaw !== '' ? $cardRaw : null;
    $birthdateValidation = validate_birthdate_input($data['geburtsdatum'] ?? null, 'Geburtsdatum');
    if ($birthdateValidation['error']) {
        echo json_encode(['success' => false, 'message' => $birthdateValidation['error']]);
        return;
    }
    $birthdate = $birthdateValidation['value'];
    $dateRaw = trim((string)($data['bewerbungsdatum'] ?? ''));
    $date = $dateRaw !== '' ? $dateRaw : null;
    $notesRaw = trim((string)($data['notizen'] ?? ''));
    $notes = $notesRaw !== '' ? $notesRaw : null;

    if ($date && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        echo json_encode(['success' => false, 'message' => 'Ungültiges Bewerbungsdatum.']);
        return;
    }

    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Ungültige E-Mail-Adresse.']);
        return;
    }

    $blockedEntry = find_blocklist_entry($firstName, $lastName, $card);
    if ($blockedEntry && !$force) {
        echo json_encode([
            'success' => false,
            'blocked' => true,
            'entry' => $blockedEntry,
            'message' => 'Die Person steht auf der Sperrliste.'
        ]);
        return;
    }

    $pdo = get_pdo();
    $stmt = $pdo->prepare('UPDATE bewerber SET vorname=:vorname, nachname=:nachname, geburtsdatum=:geburtsdatum, strasse=:strasse, plz=:plz, ort=:ort, telefon=:telefon, email=:email, fischerkartennummer=:karte, bewerbungsdatum=:datum, notizen=:notizen WHERE id = :id');
    $stmt->execute([
        'vorname' => $firstName,
        'nachname' => $lastName,
        'geburtsdatum' => $birthdate,
        'strasse' => $street,
        'plz' => $zip,
        'ort' => $city,
        'telefon' => $phone,
        'email' => $email,
        'karte' => $card,
        'datum' => $date,
        'notizen' => $notes,
        'id' => $id,
    ]);

    $stmt = $pdo->prepare('SELECT * FROM bewerber WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $applicant = $stmt->fetch();

    if (!$applicant) {
        echo json_encode(['success' => false, 'message' => 'Neuwerber nicht gefunden.']);
        return;
    }

    echo json_encode(['success' => true, 'bewerber' => $applicant]);
}

function delete_newcomer(): void
{
    $data = json_decode(file_get_contents('php://input'), true);
    $id = isset($data['id']) ? (int)$data['id'] : 0;
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Ungültige Neuwerber-ID.']);
        return;
    }

    $pdo = get_pdo();
    $stmt = $pdo->prepare('DELETE FROM bewerber WHERE id = :id');
    $stmt->execute(['id' => $id]);

    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Neuwerber nicht gefunden.']);
        return;
    }

    echo json_encode(['success' => true]);
}

function validate_birthdate_input($value, string $fieldLabel = 'Geburtsdatum'): array
{
    $rawValue = $value !== null ? trim((string)$value) : '';
    if ($rawValue === '') {
        return ['value' => null, 'error' => null];
    }

    $normalizedValue = $rawValue;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $normalizedValue)) {
        if (preg_match('/^(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{4})$/', $normalizedValue, $matches)) {
            $day = (int)$matches[1];
            $month = (int)$matches[2];
            $year = (int)$matches[3];
            if (!checkdate($month, $day, $year)) {
                return ['value' => null, 'error' => $fieldLabel . ' ist ungültig.'];
            }
            $normalizedValue = sprintf('%04d-%02d-%02d', $year, $month, $day);
        } else {
            return ['value' => null, 'error' => $fieldLabel . ' ist ungültig.'];
        }
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $normalizedValue, app_timezone());
    if (!$date || $date->format('Y-m-d') !== $normalizedValue) {
        return ['value' => null, 'error' => $fieldLabel . ' ist ungültig.'];
    }

    $today = app_today();
    if ($date > $today) {
        return ['value' => null, 'error' => $fieldLabel . ' darf nicht in der Zukunft liegen.'];
    }

    return ['value' => $date->format('Y-m-d'), 'error' => null];
}
