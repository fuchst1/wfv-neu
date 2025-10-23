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
        case 'get_prices':
            get_prices();
            break;
        case 'assign_newcomer':
            assign_newcomer();
            break;
        case 'create_newcomer':
            create_newcomer();
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
    if ($plz === '') {
        echo json_encode(['success' => false]);
        return;
    }
    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT ort FROM plz_orte WHERE plz = :plz');
    $stmt->execute(['plz' => $plz]);
    $row = $stmt->fetch();
    echo json_encode(['success' => (bool)$row, 'ort' => $row['ort'] ?? null]);
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

    $pdo = get_pdo();
    $pdo->beginTransaction();

    try {
        $licenseeId = isset($data['licensee']['id']) ? (int)$data['licensee']['id'] : 0;
        $licenseePayload = $data['licensee'];

        if ($licenseeId > 0) {
            $stmt = $pdo->prepare('UPDATE lizenznehmer SET vorname=:vorname, nachname=:nachname, strasse=:strasse, plz=:plz, ort=:ort, telefon=:telefon, email=:email, fischerkartennummer=:karte WHERE id = :id');
            $stmt->execute([
                'vorname' => $licenseePayload['vorname'],
                'nachname' => $licenseePayload['nachname'],
                'strasse' => $licenseePayload['strasse'] ?? null,
                'plz' => $licenseePayload['plz'] ?? null,
                'ort' => $licenseePayload['ort'] ?? null,
                'telefon' => $licenseePayload['telefon'] ?? null,
                'email' => $licenseePayload['email'] ?? null,
                'karte' => $licenseePayload['fischerkartennummer'] ?? null,
                'id' => $licenseeId,
            ]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO lizenznehmer (vorname, nachname, strasse, plz, ort, telefon, email, fischerkartennummer) VALUES (:vorname, :nachname, :strasse, :plz, :ort, :telefon, :email, :karte)');
            $stmt->execute([
                'vorname' => $licenseePayload['vorname'],
                'nachname' => $licenseePayload['nachname'],
                'strasse' => $licenseePayload['strasse'] ?? null,
                'plz' => $licenseePayload['plz'] ?? null,
                'ort' => $licenseePayload['ort'] ?? null,
                'telefon' => $licenseePayload['telefon'] ?? null,
                'email' => $licenseePayload['email'] ?? null,
                'karte' => $licenseePayload['fischerkartennummer'] ?? null,
            ]);
            $licenseeId = (int)$pdo->lastInsertId();
        }

        $licenseId = isset($data['license']['id']) ? (int)$data['license']['id'] : 0;
        $licensePayload = $data['license'];
        $gesamt = (float)$licensePayload['kosten'] + (float)$licensePayload['trinkgeld'];

        if ($licenseId > 0) {
            $stmt = $pdo->prepare("UPDATE {$licenseTable} SET lizenznehmer_id=:licensee_id, lizenztyp=:typ, kosten=:kosten, trinkgeld=:trinkgeld, gesamt=:gesamt, zahlungsdatum=:datum, notizen=:notizen WHERE id=:id");
            $stmt->execute([
                'licensee_id' => $licenseeId,
                'typ' => $licensePayload['lizenztyp'],
                'kosten' => $licensePayload['kosten'],
                'trinkgeld' => $licensePayload['trinkgeld'],
                'gesamt' => $gesamt,
                'datum' => $licensePayload['zahlungsdatum'] ?: null,
                'notizen' => $licensePayload['notizen'] ?? null,
                'id' => $licenseId,
            ]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO {$licenseTable} (lizenznehmer_id, lizenztyp, kosten, trinkgeld, gesamt, zahlungsdatum, notizen) VALUES (:licensee_id, :typ, :kosten, :trinkgeld, :gesamt, :datum, :notizen)");
            $stmt->execute([
                'licensee_id' => $licenseeId,
                'typ' => $licensePayload['lizenztyp'],
                'kosten' => $licensePayload['kosten'],
                'trinkgeld' => $licensePayload['trinkgeld'],
                'gesamt' => $gesamt,
                'datum' => $licensePayload['zahlungsdatum'] ?: null,
                'notizen' => $licensePayload['notizen'] ?? null,
            ]);
            $licenseId = (int)$pdo->lastInsertId();
        }

        $boatPayload = $data['boat'] ?? null;
        if ($licensePayload['lizenztyp'] === 'Boot') {
            $boatNumber = isset($boatPayload['bootnummer']) ? trim((string)$boatPayload['bootnummer']) : null;
            $boatNumber = $boatNumber === '' ? null : $boatNumber;
            $boatNotes = isset($boatPayload['notizen']) ? trim((string)$boatPayload['notizen']) : null;
            $boatNotes = $boatNotes === '' ? null : $boatNotes;

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
    $boatNumber = trim((string)($boatPayload['bootnummer'] ?? ''));
    $boatNotes = isset($boatPayload['notizen']) ? trim((string)$boatPayload['notizen']) : null;
    $boatNotes = $boatNotes === '' ? null : $boatNotes;

    if ($boatNumber === '') {
        echo json_encode(['success' => false, 'message' => 'Bootsnummer ist erforderlich.']);
        return;
    }

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
    $boatNumber = trim((string)($boatPayload['bootnummer'] ?? ''));
    $boatNotes = isset($boatPayload['notizen']) ? trim((string)$boatPayload['notizen']) : null;
    $boatNotes = $boatNotes === '' ? null : $boatNotes;

    if ($boatId <= 0 || $boatNumber === '') {
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

    $sql = "SELECT ln.id, ln.vorname, ln.nachname, ln.telefon, ln.email,
                   (SELECT b2.id FROM {$boatsTable} b2 WHERE b2.lizenznehmer_id = ln.id ORDER BY b2.id ASC LIMIT 1) AS boat_id,
                   (SELECT b2.bootnummer FROM {$boatsTable} b2 WHERE b2.lizenznehmer_id = ln.id ORDER BY b2.id ASC LIMIT 1) AS bootnummer
            FROM lizenznehmer ln
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
    if ($fromYear < 2000 || $toYear < 2000 || $licenseId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Ungültige Daten.']);
        return;
    }

    $fromTable = license_table($fromYear);
    $toTable = license_table($toYear);
    ensure_year_exists($toYear);

    $pdo = get_pdo();

    $stmt = $pdo->prepare("SELECT * FROM {$fromTable} WHERE id = :id");
    $stmt->execute(['id' => $licenseId]);
    $row = $stmt->fetch();
    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Lizenz nicht gefunden.']);
        return;
    }

    $gesamt = (float)$data['kosten'] + (float)$data['trinkgeld'];

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO {$toTable} (lizenznehmer_id, lizenztyp, kosten, trinkgeld, gesamt, zahlungsdatum, notizen) VALUES (:licensee_id, :typ, :kosten, :trinkgeld, :gesamt, :datum, :notizen)");
        $stmt->execute([
            'licensee_id' => $row['lizenznehmer_id'],
            'typ' => $row['lizenztyp'],
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

    ensure_year_exists($year);

    $pdo = get_pdo();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('DELETE FROM lizenzpreise WHERE jahr = :jahr');
        $stmt->execute(['jahr' => $year]);
        $stmt = $pdo->prepare('INSERT INTO lizenzpreise (jahr, lizenztyp, preis) VALUES (:jahr, :typ, :preis)');
        foreach ($data['preise'] as $type => $price) {
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

    $allowedTypes = ['Angel', 'Daubel', 'Boot', 'Kinder', 'Jugend'];
    if ($applicantId <= 0 || $year < 2000 || !in_array($type, $allowedTypes, true) || $cost === null || $tip === null) {
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

    ensure_year_exists($year);
    $licenseTable = license_table($year);

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('INSERT INTO lizenznehmer (vorname, nachname, strasse, plz, ort, telefon, email, fischerkartennummer) VALUES (:vorname, :nachname, :strasse, :plz, :ort, :telefon, :email, :karte)');
        $stmt->execute([
            'vorname' => $applicant['vorname'] ?? '',
            'nachname' => $applicant['nachname'] ?? '',
            'strasse' => $applicant['strasse'] ?? null,
            'plz' => $applicant['plz'] ?? null,
            'ort' => $applicant['ort'] ?? null,
            'telefon' => $applicant['telefon'] ?? null,
            'email' => $applicant['email'] ?? null,
            'karte' => $applicant['fischerkartennummer'] ?? null,
        ]);
        $licenseeId = (int)$pdo->lastInsertId();

        $total = (float)$cost + (float)$tip;
        $stmt = $pdo->prepare("INSERT INTO {$licenseTable} (lizenznehmer_id, lizenztyp, kosten, trinkgeld, gesamt, zahlungsdatum, notizen) VALUES (:licensee_id, :typ, :kosten, :trinkgeld, :gesamt, :datum, :notizen)");
        $stmt->execute([
            'licensee_id' => $licenseeId,
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

    $firstName = trim((string)($data['vorname'] ?? ''));
    $lastName = trim((string)($data['nachname'] ?? ''));
    if ($firstName === '' || $lastName === '') {
        echo json_encode(['success' => false, 'message' => 'Vor- und Nachname sind erforderlich.']);
        return;
    }

    $street = trim((string)($data['strasse'] ?? '')) ?: null;
    $zip = trim((string)($data['plz'] ?? '')) ?: null;
    $city = trim((string)($data['ort'] ?? '')) ?: null;
    $phone = trim((string)($data['telefon'] ?? '')) ?: null;
    $email = trim((string)($data['email'] ?? '')) ?: null;
    $card = trim((string)($data['fischerkartennummer'] ?? '')) ?: null;
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

    $pdo = get_pdo();
    $stmt = $pdo->prepare('INSERT INTO bewerber (vorname, nachname, strasse, plz, ort, telefon, email, fischerkartennummer, bewerbungsdatum, notizen) VALUES (:vorname, :nachname, :strasse, :plz, :ort, :telefon, :email, :karte, :datum, :notizen)');
    $stmt->execute([
        'vorname' => $firstName,
        'nachname' => $lastName,
        'strasse' => $street,
        'plz' => $zip,
        'ort' => $city,
        'telefon' => $phone,
        'email' => $email,
        'karte' => $card,
        'datum' => $date ?: date('Y-m-d'),
        'notizen' => $notes,
    ]);

    $id = (int)$pdo->lastInsertId();
    $stmt = $pdo->prepare('SELECT * FROM bewerber WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $applicant = $stmt->fetch();

    echo json_encode(['success' => true, 'bewerber' => $applicant]);
}
