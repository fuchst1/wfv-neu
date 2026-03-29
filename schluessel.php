<?php
require_once __DIR__ . '/lib/functions.php';

$years = available_years();
$currentYear = $years ? max($years) : (int)date('Y');
$keyLicensees = get_licensees_with_keys();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schlüsselverwaltung · Wörderner Fischereiverein</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="app-header">
    <div class="branding">
        <h1>Schlüsselverwaltung</h1>
        <p>Wörderner Fischereiverein</p>
    </div>
    <?php
        $currentPage = 'keys';
        $yearDisplayLimit = null;
        include __DIR__ . '/partials/year_nav.php';
    ?>
</header>

<main>
    <section class="dashboard">
        <div>
            <h2>Spezialschlüssel für Lizenznehmer</h2>
            <p>Schlüssel werden direkt am Lizenznehmer gespeichert und sind nicht an ein Jahr gebunden.</p>
        </div>
    </section>

    <section class="table-section">
        <h3>Lizenznehmer-Suche</h3>
        <form id="keySearchForm" class="table-search" autocomplete="off">
            <label class="table-search-label" for="keySearchInput">Suche:</label>
            <input type="search" id="keySearchInput" name="query" placeholder="Name, Lizenznummer, Fischerkartennummer oder Ort" spellcheck="false" aria-describedby="keySearchMessage">
            <button type="submit" class="primary">Suchen</button>
            <button type="reset" class="secondary" id="keySearchReset">Zurücksetzen</button>
        </form>
        <p id="keySearchMessage" class="search-message">Geben Sie einen Namen ein, um die Suche zu starten.</p>
        <div id="keySearchResults" class="licensee-results" hidden></div>
    </section>

    <section class="table-section">
        <div class="section-header">
            <div>
                <h3>Lizenznehmer mit Schlüssel</h3>
                <p>Aktuell ausgegebene Schlüssel: <strong id="keyOverviewCount"><?= count($keyLicensees) ?></strong></p>
            </div>
        </div>
        <table class="summary-table" id="keyOverviewTable">
            <thead>
                <tr>
                    <th>Lizenznehmer</th>
                    <th>Fischerkartennummer</th>
                    <th>Adresse</th>
                    <th>Ausgegeben am</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody id="keyOverviewBody">
                <?php if (!$keyLicensees): ?>
                    <tr data-empty-row>
                        <td colspan="5" class="empty">Aktuell ist kein Schlüssel ausgegeben.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($keyLicensees as $licensee): ?>
                        <?php
                            $keyLicenseeRow = [
                                'id' => isset($licensee['id']) ? (int)$licensee['id'] : 0,
                                'vorname' => $licensee['vorname'] ?? null,
                                'nachname' => $licensee['nachname'] ?? null,
                                'geburtsdatum' => $licensee['geburtsdatum'] ?? null,
                                'geburtsdatum_formatted' => format_date($licensee['geburtsdatum'] ?? null),
                                'alter' => calculate_age($licensee['geburtsdatum'] ?? null),
                                'strasse' => $licensee['strasse'] ?? null,
                                'plz' => $licensee['plz'] ?? null,
                                'ort' => $licensee['ort'] ?? null,
                                'telefon' => $licensee['telefon'] ?? null,
                                'email' => $licensee['email'] ?? null,
                                'fischerkartennummer' => $licensee['fischerkartennummer'] ?? null,
                                'schluessel_ausgegeben' => !empty($licensee['schluessel_ausgegeben']),
                                'schluessel_ausgegeben_am' => ($licensee['schluessel_ausgegeben_am'] ?? '') !== '' ? $licensee['schluessel_ausgegeben_am'] : null,
                                'schluessel_ausgegeben_am_formatted' => format_date(($licensee['schluessel_ausgegeben_am'] ?? '') !== '' ? $licensee['schluessel_ausgegeben_am'] : null),
                            ];
                            $addressParts = [];
                            if (!empty($licensee['strasse'])) {
                                $addressParts[] = $licensee['strasse'];
                            }
                            $cityParts = [];
                            if (!empty($licensee['plz'])) {
                                $cityParts[] = $licensee['plz'];
                            }
                            if (!empty($licensee['ort'])) {
                                $cityParts[] = $licensee['ort'];
                            }
                            if ($cityParts) {
                                $addressParts[] = implode(' ', $cityParts);
                            }
                        ?>
                        <tr data-key-licensee='<?= json_encode($keyLicenseeRow, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>'>
                            <td><strong><?= htmlspecialchars(trim((string)(($licensee['nachname'] ?? '') . ', ' . ($licensee['vorname'] ?? '')), ' ,')) ?></strong></td>
                            <td><?= htmlspecialchars($licensee['fischerkartennummer'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($addressParts ? implode(', ', $addressParts) : '–') ?></td>
                            <td><?= htmlspecialchars(format_date(($licensee['schluessel_ausgegeben_am'] ?? '') !== '' ? $licensee['schluessel_ausgegeben_am'] : null) ?? '–') ?></td>
                            <td class="actions">
                                <button type="button" class="primary edit-key">Schlüssel bearbeiten</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </section>
</main>

<div class="modal" id="keyModal" hidden>
    <div class="modal-content">
        <header>
            <h2>Schlüssel bearbeiten</h2>
            <button class="close" data-close>&times;</button>
        </header>
        <form id="keyForm">
            <input type="hidden" id="keyLicenseeId">
            <section class="form-section">
                <p class="muted">Lizenznehmer: <strong id="keyLicenseeName">–</strong></p>
                <label class="form-checkbox-label" for="keyGiven">
                    <input type="checkbox" id="keyGiven">
                    <span>Schlüssel ausgegeben</span>
                </label>
                <label>
                    <span class="label-title">Ausgegeben am</span>
                    <input type="date" id="keyGivenDate">
                </label>
                <p class="form-hint">Beim Aktivieren ohne Datum wird automatisch das heutige Datum vorgeschlagen.</p>
            </section>
            <footer class="modal-footer">
                <button type="button" class="secondary" data-close>Abbrechen</button>
                <button type="submit" class="primary">Speichern</button>
            </footer>
        </form>
    </div>
</div>

<script src="assets/js/keys.js"></script>
</body>
</html>
