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
$yearClosure = get_year_closure($currentYear);
$isYearClosed = $yearClosure !== null;
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
    <?php
        $currentPage = 'index';
        $yearDisplayLimit = 5;
        include __DIR__ . '/partials/year_nav.php';
    ?>
</header>

<main>
    <section class="dashboard">
        <div>
            <h2>Überblick <?= $currentYear ?></h2>
            <p>Lizenznehmer gesamt: <strong><?= count($licensees) ?></strong></p>
            <?php if ($isYearClosed): ?>
                <p class="year-status-message">
                    Dieses Jahr wurde<?= $yearClosure && $yearClosure['abgeschlossen_am'] ? ' am ' . htmlspecialchars(format_datetime($yearClosure['abgeschlossen_am'])) : '' ?> abgeschlossen. Änderungen sind nicht mehr möglich.
                </p>
            <?php endif; ?>
        </div>
        <div>
            <button class="primary" id="openAddLicense"<?= $isYearClosed ? ' disabled aria-disabled="true" title="Jahr abgeschlossen"' : '' ?>>Lizenz hinzufügen</button>
        </div>
    </section>

    <section class="table-section">
        <div class="table-filter">
            <label class="table-search">
                <span class="table-search-label">Suche:</span>
                <input type="search" id="licenseSearch" placeholder="Lizenznehmer, Lizenztyp, Notizen …" data-table-search="#licenseTable">
            </label>
            <div class="table-actions">
                <a class="button-link inline" href="export.php?jahr=<?= $currentYear ?>&format=csv">CSV exportieren</a>
                <a class="button-link inline" href="export.php?jahr=<?= $currentYear ?>&format=xlsx">XLSX exportieren</a>
            </div>
        </div>
        <table id="licenseTable">
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
                    <tr data-empty-row>
                        <td colspan="8" class="empty">Keine Lizenzen für dieses Jahr vorhanden.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($licensees as $row): ?>
                        <tr id="license-<?= $row['lizenz_id'] ?>" data-license='<?= json_encode($row, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>'>
                            <td>
                                <strong><?= htmlspecialchars($row['nachname']) ?>, <?= htmlspecialchars($row['vorname']) ?></strong><br>
                                <small><?= htmlspecialchars($row['strasse'] ?? '') ?>, <?= htmlspecialchars($row['plz'] ?? '') ?> <?= htmlspecialchars($row['ort'] ?? '') ?></small><br>
                                <small>Telefon: <?= htmlspecialchars($row['telefon'] ?? '-') ?> · E-Mail: <?= htmlspecialchars($row['email'] ?? '-') ?></small>
                                <?php
                                    $licenseeBirthdate = $row['geburtsdatum'] ?? null;
                                    $licenseeBirthdateDisplay = $licenseeBirthdate ? format_date($licenseeBirthdate) : null;
                                    $licenseeAge = $licenseeBirthdate ? calculate_age($licenseeBirthdate) : null;
                                ?>
                                <?php if ($licenseeBirthdateDisplay): ?>
                                    <br><small>Geburtsdatum: <?= htmlspecialchars($licenseeBirthdateDisplay) ?><?= $licenseeAge !== null ? ' (Alter: ' . (int)$licenseeAge . ')' : '' ?></small>
                                <?php endif; ?>
                                <?php if (!empty($row['previous_license_years'])): ?>
                                    <br><small>Vorjahre: <?= htmlspecialchars(implode(', ', $row['previous_license_years'])) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($row['lizenztyp']) ?></td>
                            <td><?= format_currency((float)$row['kosten']) ?> €</td>
                            <td><?= format_currency((float)$row['trinkgeld']) ?> €</td>
                            <td><?= format_currency((float)$row['gesamt']) ?> €</td>
                            <td><?= ($formattedDate = format_date($row['zahlungsdatum'] ?? null)) ? htmlspecialchars($formattedDate) : '–' ?></td>
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
                                <?php if ($isYearClosed): ?>
                                    <span class="badge badge-closed">Jahr abgeschlossen</span>
                                <?php else: ?>
                                    <button class="primary extend">Verlängern</button>
                                    <button class="secondary edit">Bearbeiten</button>
                                    <button class="danger delete">Löschen</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
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
                <div class="form-grid">
                    <label>
                        <span class="label-title">Vorname <span class="required-indicator" aria-hidden="true">*</span></span>
                        <input type="text" id="vorname" data-validate="required" required>
                    </label>
                    <label>
                        <span class="label-title">Nachname <span class="required-indicator" aria-hidden="true">*</span></span>
                        <input type="text" id="nachname" data-validate="required" required>
                    </label>
                    <label>
                        <span class="label-title">Straße <span class="required-indicator" aria-hidden="true">*</span></span>
                        <input type="text" id="strasse" data-validate="required" required>
                    </label>
                    <label>
                        <span class="label-title">PLZ <span class="required-indicator" aria-hidden="true">*</span></span>
                        <input type="text" id="plz" data-validate="required,zip" required>
                    </label>
                    <label>
                        <span class="label-title">Ort <span class="required-indicator" aria-hidden="true">*</span></span>
                        <input type="text" id="ort" data-validate="required" required>
                    </label>
                    <label>Telefon
                        <input type="text" id="telefon" data-validate="phone">
                    </label>
                    <label>E-Mail
                        <input type="email" id="email" data-validate="email">
                    </label>
                    <label>
                        <span class="label-title">Geburtsdatum</span>
                        <input type="date" id="geburtsdatum">
                        <span class="form-hint" id="licenseAgeHint"></span>
                        <span class="form-warning" id="licenseAgeWarning" hidden></span>
                    </label>
                    <label>
                        <span class="label-title">Fischerkartennummer <span class="required-indicator" aria-hidden="true">*</span></span>
                        <input type="text" id="fischerkartennummer" data-validate="required" required>
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
                <details id="boatDetails" hidden>
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
                    <?php
                        $extendYears = array_filter($years, function ($year) use ($currentYear) {
                            return (int)$year !== (int)$currentYear;
                        });
                        rsort($extendYears);
                    ?>
                    <label>Neues Jahr
                        <select id="extendYear" data-validate="required" required <?= $extendYears ? '' : 'disabled' ?>>
                            <option value="">– bitte wählen –</option>
                            <?php foreach ($extendYears as $yearOption): ?>
                                <option value="<?= (int)$yearOption ?>"><?= (int)$yearOption ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Lizenz
                        <select id="extendLicense" data-validate="required" required>
                            <option value="">– bitte wählen –</option>
                        </select>
                    </label>
                    <label>Lizenztyp
                        <select id="extendType" data-validate="required" required>
                            <option value="">– bitte wählen –</option>
                            <option value="Angel">Angel</option>
                            <option value="Daubel">Daubel</option>
                            <option value="Boot">Boot</option>
                            <option value="Kinder">Kinder</option>
                            <option value="Jugend">Jugend</option>
                        </select>
                    </label>
                    <?php if (!$extendYears): ?>
                        <p class="form-hint">Kein weiteres Jahr vorhanden. Neues Jahr im Adminbereich anlegen.</p>
                    <?php endif; ?>
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

<div class="modal" id="blockWarningModal" hidden>
    <div class="modal-content">
        <header>
            <h2>Auf Sperrliste</h2>
            <button class="close" id="closeBlockWarning" type="button">&times;</button>
        </header>
        <p id="blockWarningText">Der ausgewählte Lizenznehmer steht auf der Sperrliste.</p>
        <footer class="modal-footer">
            <button type="button" class="secondary" id="cancelBlockWarning">Zurück</button>
            <button type="button" class="primary" id="confirmBlockOverride">Trotzdem speichern</button>
        </footer>
    </div>
</div>

<script>
const CURRENT_YEAR = <?= json_encode($currentYear) ?>;
const LICENSE_PRICES = <?= json_encode($prices) ?>;
const IS_YEAR_CLOSED = <?= $isYearClosed ? 'true' : 'false' ?>;
const OPEN_LICENSE_MODAL = <?= json_encode($isYearClosed ? null : ($_GET['create'] ?? null)) ?>;
</script>
<script src="assets/js/validation.js"></script>
<script src="assets/js/table-search.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>
