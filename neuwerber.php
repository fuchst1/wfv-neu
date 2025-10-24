<?php
require_once __DIR__ . '/lib/functions.php';

$years = available_years();
$currentYear = isset($_GET['jahr']) ? (int)$_GET['jahr'] : null;

if (!$currentYear && $years) {
    $currentYear = max($years);
}

if (!$currentYear) {
    $currentYear = (int)date('Y');
}

ensure_year_exists($currentYear);
$years = available_years();

$newcomers = get_newcomers();
$prices = get_license_prices($currentYear);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Neuwerber · Wörderner Fischereiverein</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="app-header">
    <div class="branding">
        <h1>Neuwerber</h1>
        <p>Wörderner Fischereiverein</p>
    </div>
    <?php
        $currentPage = 'neuwerber';
        include __DIR__ . '/partials/year_nav.php';
    ?>
</header>

<main>
    <section class="dashboard">
        <div>
            <h2>Neuwerber insgesamt</h2>
            <p>Anzahl: <strong id="newcomerCount"><?= count($newcomers) ?></strong></p>
        </div>
        <div>
            <button class="primary" id="openAddApplicant">Neuwerber hinzufügen</button>
        </div>
    </section>

    <section class="table-section">
        <div class="table-filter">
            <label class="table-search">
                <span class="table-search-label">Suche:</span>
                <input type="search" id="newcomerSearch" placeholder="Name, Ort oder Notizen durchsuchen …" data-table-search="#newcomerTable">
            </label>
        </div>
        <table id="newcomerTable">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Adresse & Kontakt</th>
                    <th>Bewerbungsdatum</th>
                    <th>Notizen</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$newcomers): ?>
                <tr data-empty-row>
                    <td colspan="5" class="empty">Keine Neuwerber vorhanden.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($newcomers as $applicant): ?>
                    <tr data-applicant='<?= json_encode($applicant, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>'>
                        <td>
                            <strong><?= htmlspecialchars($applicant['nachname'] ?? '') ?>, <?= htmlspecialchars($applicant['vorname'] ?? '') ?></strong>
                        </td>
                        <td>
                            <small><?= htmlspecialchars($applicant['strasse'] ?? '') ?></small><br>
                            <small><?= htmlspecialchars($applicant['plz'] ?? '') ?> <?= htmlspecialchars($applicant['ort'] ?? '') ?></small><br>
                            <small>Telefon: <?= htmlspecialchars($applicant['telefon'] ?? '-') ?> · E-Mail: <?= htmlspecialchars($applicant['email'] ?? '-') ?></small><br>
                            <small>Fischerkartennummer: <?= htmlspecialchars($applicant['fischerkartennummer'] ?? '-') ?></small>
                        </td>
                        <td><?= ($formattedDate = format_date($applicant['bewerbungsdatum'] ?? null)) ? htmlspecialchars($formattedDate) : '–' ?></td>
                        <td><?= nl2br(htmlspecialchars($applicant['notizen'] ?? '')) ?></td>
                        <td class="actions">
                            <button class="primary assign">Lizenz zuweisen</button>
                            <button class="secondary edit">Bearbeiten</button>
                            <button class="danger delete">Löschen</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </section>
</main>

<div class="modal" id="newApplicantModal" hidden>
    <div class="modal-content">
        <header>
            <h2>Neuwerber hinzufügen</h2>
            <button class="close" data-close>&times;</button>
        </header>
        <form id="newApplicantForm">
            <section class="form-section">
                <div class="form-grid">
                    <label>Vorname <span aria-hidden="true">*</span>
                        <input type="text" id="applicantFirstName" data-validate="required" required>
                    </label>
                    <label>Nachname <span aria-hidden="true">*</span>
                        <input type="text" id="applicantLastName" data-validate="required" required>
                    </label>
                    <label>Straße <span aria-hidden="true">*</span>
                        <input type="text" id="applicantStreet" data-validate="required" required>
                    </label>
                    <label>PLZ <span aria-hidden="true">*</span>
                        <input type="text" id="applicantZip" data-validate="required,zip" required>
                    </label>
                    <label>Ort <span aria-hidden="true">*</span>
                        <input type="text" id="applicantCity" data-validate="required" required>
                    </label>
                    <label>Telefon
                        <input type="text" id="applicantPhone" data-validate="phone">
                    </label>
                    <label>E-Mail
                        <input type="email" id="applicantEmail" data-validate="email">
                    </label>
                    <label>Fischerkartennummer <span aria-hidden="true">*</span>
                        <input type="text" id="applicantCard" data-validate="required" required>
                    </label>
                </div>
                <label>Bewerbungsdatum
                    <input type="date" id="applicantDate">
                </label>
                <label>Notizen
                    <textarea id="applicantNotes" rows="3"></textarea>
                </label>
            </section>
            <footer class="modal-footer">
                <button type="button" class="secondary" data-close>Abbrechen</button>
                <button type="submit" class="primary">Speichern</button>
            </footer>
        </form>
    </div>
</div>

<div class="modal" id="assignModal" hidden>
    <div class="modal-content">
        <header>
            <h2>Lizenz zuweisen</h2>
            <button class="close" data-close>&times;</button>
        </header>
        <form id="assignForm">
            <section class="form-section">
                <div class="form-grid">
                    <label>Jahr
                        <input type="number" id="assignYear" min="2000" data-validate="required" required>
                    </label>
                    <label>Lizenztyp
                        <select id="assignType" data-validate="required" required>
                            <option value="">– bitte wählen –</option>
                            <option value="Angel" selected>Angel</option>
                            <option value="Daubel">Daubel</option>
                            <option value="Boot">Boot</option>
                            <option value="Kinder">Kinder</option>
                            <option value="Jugend">Jugend</option>
                        </select>
                    </label>
                    <label>Kosten (€)
                        <input type="number" id="assignCost" step="0.01" min="0" data-validate="required" required>
                    </label>
                    <label>Trinkgeld (€)
                        <input type="number" id="assignTip" step="0.01" min="0" value="0">
                    </label>
                    <label>Gesamt (€)
                        <input type="text" id="assignTotal" readonly>
                    </label>
                    <label>Zahlungsdatum
                        <input type="date" id="assignDate">
                    </label>
                </div>
                <label>Notizen
                    <textarea id="assignNotes" rows="3"></textarea>
                </label>
            </section>
            <footer class="modal-footer">
                <button type="button" class="secondary" data-close>Abbrechen</button>
                <button type="submit" class="primary">Zuweisen</button>
            </footer>
        </form>
    </div>
</div>

<div class="modal" id="assignBlockWarningModal" hidden>
    <div class="modal-content">
        <header>
            <h2>Auf Sperrliste</h2>
            <button class="close" type="button" id="closeAssignBlockWarning">&times;</button>
        </header>
        <p id="assignBlockWarningText">Der ausgewählte Bewerber steht auf der Sperrliste.</p>
        <footer class="modal-footer">
            <button type="button" class="secondary" id="cancelAssignBlockWarning">Abbrechen</button>
            <button type="button" class="primary" id="confirmAssignBlockOverride">Fortfahren</button>
        </footer>
    </div>
</div>

<script>const CURRENT_YEAR = <?= json_encode($currentYear) ?>; const LICENSE_PRICES = <?= json_encode($prices) ?>;</script>
<script src="assets/js/validation.js"></script>
<script src="assets/js/table-search.js"></script>
<script src="assets/js/newcomers.js"></script>
</body>
</html>
