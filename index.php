<?php
require_once __DIR__ . '/lib/functions.php';

$years = available_years();
$currentYear = isset($_GET['jahr']) ? (int)$_GET['jahr'] : null;
if (!$currentYear && $years) {
    $currentYear = max($years);
}
if (!$currentYear) {
    $currentYear = (int)date('Y');
    ensure_year_exists($currentYear);
    $years = available_years();
}

$licensees = get_licensees_for_year($currentYear);
$allLicensees = get_all_licensees();
$prices = get_license_prices($currentYear);
$yearOverview = get_year_overview($currentYear);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lizenzverwaltung · Wörderner Fischereiverein</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="app-header">
    <div class="branding">
        <h1>Lizenzverwaltung</h1>
        <p>Wörderner Fischereiverein</p>
    </div>
    <nav class="year-nav">
        <span>Jahr wählen:</span>
        <ul>
            <?php foreach ($years as $year): ?>
                <li class="<?= $year === $currentYear ? 'active' : '' ?>">
                    <a href="?jahr=<?= $year ?>"><?= $year ?></a>
                </li>
            <?php endforeach; ?>
        </ul>
        <button class="primary" id="openCreateYear">Neues Jahr anlegen</button>
        <a class="button-link" href="neuwerber.php">Neuwerber</a>
        <a class="button-link" href="boats.php">Bootsübersicht</a>
    </nav>
</header>

<main>
    <section class="dashboard">
        <div>
            <h2>Überblick <?= $currentYear ?></h2>
            <p>Lizenznehmer gesamt: <strong><?= count($licensees) ?></strong></p>
            <div class="dashboard-stats">
                <div class="stat">
                    <span class="stat-label">Einnahmen</span>
                    <span class="stat-value"><?= format_currency($yearOverview['total_cost']) ?> €</span>
                </div>
                <div class="stat">
                    <span class="stat-label">Trinkgeld</span>
                    <span class="stat-value"><?= format_currency($yearOverview['total_tip']) ?> €</span>
                </div>
                <div class="stat">
                    <span class="stat-label">Summe</span>
                    <span class="stat-value"><?= format_currency($yearOverview['total_combined']) ?> €</span>
                </div>
            </div>
        </div>
        <div>
            <button class="primary" id="openAddLicense">Lizenz hinzufügen</button>
        </div>
    </section>

    <section class="summary-section">
        <h3>Verkäufe nach Lizenztyp</h3>
        <?php if (!$yearOverview['types']): ?>
            <p class="empty">Noch keine Lizenzen für dieses Jahr verkauft.</p>
        <?php else: ?>
            <table class="summary-table">
                <thead>
                    <tr>
                        <th>Lizenztyp</th>
                        <th>Anzahl</th>
                        <th>Summe Kosten</th>
                        <th>Summe Trinkgeld</th>
                        <th>Summe Gesamt</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($yearOverview['types'] as $summary): ?>
                        <tr>
                            <td><?= htmlspecialchars($summary['lizenztyp']) ?></td>
                            <td><?= $summary['count'] ?></td>
                            <td><?= format_currency($summary['sum_cost']) ?> €</td>
                            <td><?= format_currency($summary['sum_tip']) ?> €</td>
                            <td><?= format_currency($summary['sum_total']) ?> €</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th>Gesamt</th>
                        <th><?= $yearOverview['total_count'] ?></th>
                        <th><?= format_currency($yearOverview['total_cost']) ?> €</th>
                        <th><?= format_currency($yearOverview['total_tip']) ?> €</th>
                        <th><?= format_currency($yearOverview['total_combined']) ?> €</th>
                    </tr>
                </tfoot>
            </table>
        <?php endif; ?>
    </section>

    <section class="table-section">
        <table>
            <thead>
                <tr>
                    <th>Lizenznehmer</th>
                    <th>Lizenztyp</th>
                    <th>Kosten</th>
                    <th>Trinkgeld</th>
                    <th>Gesamt</th>
                    <th>Zahlungsdatum</th>
                    <th>Notizen</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$licensees): ?>
                    <tr>
                        <td colspan="8" class="empty">Keine Lizenzen für dieses Jahr vorhanden.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($licensees as $row): ?>
                        <tr id="license-<?= $row['lizenz_id'] ?>" data-license='<?= json_encode($row, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>'>
                            <td>
                                <strong><?= htmlspecialchars($row['nachname']) ?>, <?= htmlspecialchars($row['vorname']) ?></strong><br>
                                <small><?= htmlspecialchars($row['strasse'] ?? '') ?>, <?= htmlspecialchars($row['plz'] ?? '') ?> <?= htmlspecialchars($row['ort'] ?? '') ?></small><br>
                                <small>Telefon: <?= htmlspecialchars($row['telefon'] ?? '-') ?> · E-Mail: <?= htmlspecialchars($row['email'] ?? '-') ?></small>
                            </td>
                            <td><?= htmlspecialchars($row['lizenztyp']) ?></td>
                            <td><?= format_currency((float)$row['kosten']) ?> €</td>
                            <td><?= format_currency((float)$row['trinkgeld']) ?> €</td>
                            <td><?= format_currency((float)$row['gesamt']) ?> €</td>
                            <td><?= $row['zahlungsdatum'] ? htmlspecialchars($row['zahlungsdatum']) : '–' ?></td>
                            <td>
                                <?= nl2br(htmlspecialchars($row['lizenz_notizen'] ?? '')) ?>
                                <?php if ($row['lizenztyp'] === 'Boot' && ($row['bootnummer'] || $row['boot_notizen'])): ?>
                                    <details>
                                        <summary>Boot</summary>
                                        <div>Nummer: <?= htmlspecialchars($row['bootnummer'] ?? '-') ?></div>
                                        <div><?= nl2br(htmlspecialchars($row['boot_notizen'] ?? '')) ?></div>
                                    </details>
                                <?php endif; ?>
                            </td>
                            <td class="actions">
                                <button class="secondary edit">Bearbeiten</button>
                                <button class="secondary extend">Verlängern</button>
                                <button class="danger delete">Löschen</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </section>
</main>

<div class="modal" id="licenseModal" hidden>
    <div class="modal-content">
        <header>
            <h2 id="licenseModalTitle">Lizenz bearbeiten</h2>
            <button class="close" data-close>&times;</button>
        </header>
        <form id="licenseForm">
            <input type="hidden" name="license[id]" id="licenseId">
            <input type="hidden" name="licensee[id]" id="licenseeId">
            <section class="form-section">
                <h3>Lizenznehmer</h3>
                <label for="licenseeSelect">Bestehenden Lizenznehmer wählen</label>
                <select id="licenseeSelect">
                    <option value="">Neuer Lizenznehmer</option>
                    <?php foreach ($allLicensees as $licensee): ?>
                        <option value="<?= $licensee['id'] ?>"
                            data-vorname="<?= htmlspecialchars($licensee['vorname']) ?>"
                            data-nachname="<?= htmlspecialchars($licensee['nachname']) ?>"
                            data-strasse="<?= htmlspecialchars($licensee['strasse'] ?? '') ?>"
                            data-plz="<?= htmlspecialchars($licensee['plz'] ?? '') ?>"
                            data-ort="<?= htmlspecialchars($licensee['ort'] ?? '') ?>"
                            data-telefon="<?= htmlspecialchars($licensee['telefon'] ?? '') ?>"
                            data-email="<?= htmlspecialchars($licensee['email'] ?? '') ?>"
                            data-karte="<?= htmlspecialchars($licensee['fischerkartennummer'] ?? '') ?>"
                        ><?= htmlspecialchars($licensee['nachname'] . ', ' . $licensee['vorname']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-grid">
                    <label>Vorname
                        <input type="text" id="vorname" data-validate="required" required>
                    </label>
                    <label>Nachname
                        <input type="text" id="nachname" data-validate="required" required>
                    </label>
                    <label>Straße
                        <input type="text" id="strasse">
                    </label>
                    <label>PLZ
                        <input type="text" id="plz" data-validate="zip">
                    </label>
                    <label>Ort
                        <input type="text" id="ort" data-validate="required" required>
                    </label>
                    <label>Telefon
                        <input type="text" id="telefon">
                    </label>
                    <label>E-Mail
                        <input type="email" id="email" data-validate="email">
                    </label>
                    <label>Fischerkartennummer
                        <input type="text" id="fischerkartennummer">
                    </label>
                </div>
            </section>
            <section class="form-section">
                <h3>Lizenz</h3>
                <div class="form-grid">
                    <label>Lizenztyp
                        <select id="lizenztyp" data-validate="required" required>
                            <option value="">– bitte wählen –</option>
                            <option value="Angel">Angel</option>
                            <option value="Daubel">Daubel</option>
                            <option value="Boot">Boot</option>
                            <option value="Kinder">Kinder</option>
                            <option value="Jugend">Jugend</option>
                        </select>
                    </label>
                    <label>Kosten (€)
                        <input type="number" id="kosten" step="0.01" min="0" data-validate="required" required>
                    </label>
                    <label>Trinkgeld (€)
                        <input type="number" id="trinkgeld" step="0.01" min="0" value="0">
                    </label>
                    <label>Gesamt (€)
                        <input type="text" id="gesamt" readonly>
                    </label>
                    <label>Zahlungsdatum
                        <input type="date" id="zahlungsdatum">
                    </label>
                </div>
                <label>Notizen
                    <textarea id="lizenzNotizen" rows="3"></textarea>
                </label>
                <details id="boatDetails">
                    <summary>Bootsdaten</summary>
                    <label>Bootsnummer
                        <input type="text" id="bootnummer">
                    </label>
                    <label>Bootsnotizen
                        <textarea id="bootNotizen" rows="2"></textarea>
                    </label>
                </details>
            </section>
            <footer class="modal-footer">
                <button type="button" class="secondary" data-close>Abbrechen</button>
                <button type="submit" class="primary">Speichern</button>
            </footer>
        </form>
    </div>
</div>

<div class="modal" id="deleteModal" hidden>
    <div class="modal-content">
        <header>
            <h2>Lizenz löschen</h2>
            <button class="close" data-close>&times;</button>
        </header>
        <p>Bist du sicher, dass diese Lizenz gelöscht werden soll?</p>
        <footer class="modal-footer">
            <button class="secondary" data-close>Abbrechen</button>
            <button class="danger" id="confirmDelete">Löschen</button>
        </footer>
    </div>
</div>

<div class="modal" id="extendModal" hidden>
    <div class="modal-content">
        <header>
            <h2>Lizenz verlängern</h2>
            <button class="close" data-close>&times;</button>
        </header>
        <form id="extendForm">
            <section class="form-section">
                <div class="form-grid">
                    <label>Neues Jahr
                        <input type="number" id="extendYear" min="2000" data-validate="required" required>
                    </label>
                    <label>Kosten (€)
                        <input type="number" id="extendCost" step="0.01" min="0" data-validate="required" required>
                    </label>
                    <label>Trinkgeld (€)
                        <input type="number" id="extendTip" step="0.01" min="0" value="0">
                    </label>
                    <label>Gesamt (€)
                        <input type="text" id="extendTotal" readonly>
                    </label>
                    <label>Zahlungsdatum
                        <input type="date" id="extendDate">
                    </label>
                </div>
                <label>Notizen
                    <textarea id="extendNotes" rows="2"></textarea>
                </label>
            </section>
            <footer class="modal-footer">
                <button type="button" class="secondary" data-close>Abbrechen</button>
                <button type="submit" class="primary">Übernehmen</button>
            </footer>
        </form>
    </div>
</div>

<div class="modal" id="createYearModal" hidden>
    <div class="modal-content">
        <header>
            <h2>Neues Jahr anlegen</h2>
            <button class="close" data-close>&times;</button>
        </header>
        <form id="createYearForm">
            <section class="form-section">
                <label>Jahr
                    <input type="number" id="newYear" min="2000" value="<?= $currentYear + 1 ?>" data-validate="required" required>
                </label>
                <p>Bitte Preise für jede Lizenzart festlegen:</p>
                <div class="form-grid">
                    <?php $types = ['Angel', 'Daubel', 'Boot', 'Kinder', 'Jugend']; ?>
                    <?php foreach ($types as $type): ?>
                        <label><?= $type ?> (€)
                            <input type="number" class="price-input" data-type="<?= $type ?>" step="0.01" min="0" required>
                        </label>
                    <?php endforeach; ?>
                </div>
            </section>
            <footer class="modal-footer">
                <button type="button" class="secondary" data-close>Abbrechen</button>
                <button type="submit" class="primary">Jahr erstellen</button>
            </footer>
        </form>
    </div>
</div>

<script>
const CURRENT_YEAR = <?= json_encode($currentYear) ?>;
const LICENSE_PRICES = <?= json_encode($prices) ?>;
const OPEN_LICENSE_MODAL = <?= json_encode($_GET['create'] ?? null) ?>;
</script>
<script src="assets/js/validation.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>
