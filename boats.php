<?php
require_once __DIR__ . '/lib/functions.php';

$boats = get_boats_overview();
$latestYear = latest_year();
$currentYear = $latestYear ?: (int)date('Y');
ensure_year_exists($currentYear);
$prices = get_license_prices($currentYear);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bootsübersicht · Wörderner Fischereiverein</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="app-header">
    <div class="branding">
        <h1>Bootsübersicht</h1>
        <p>Wörderner Fischereiverein</p>
    </div>
    <nav class="year-nav">
        <a class="button-link" href="index.php">Zur Lizenzverwaltung</a>
        <a class="button-link" href="neuwerber.php">Neuwerber</a>
    </nav>
</header>

<main>
    <section class="dashboard">
        <div>
            <h2>Alle Boote</h2>
            <p>Erfasste Boote gesamt: <strong><?= count($boats) ?></strong></p>
        </div>
        <div>
            <button type="button" class="primary" id="openAddBoat">Boot hinzufügen</button>
        </div>
    </section>

    <section class="table-section">
        <div class="table-filter">
            <label class="table-search">
                <span class="table-search-label">Suche:</span>
                <input type="search" id="boatSearch" placeholder="Bootsnummer, Lizenznehmer oder Notizen …" data-table-search="#boatTable">
            </label>
        </div>
        <table id="boatTable">
            <thead>
                <tr>
                    <th>Bootsnummer</th>
                    <th>Lizenznehmer</th>
                    <th>Lizenzjahr</th>
                    <th>Zahlungsdatum</th>
                    <th>Bootsnotizen</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$boats): ?>
                <tr data-empty-row>
                    <td colspan="5" class="empty">Keine Boote erfasst.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($boats as $boat): ?>
                    <tr>
                        <td><?= htmlspecialchars($boat['bootnummer'] ?? '–') ?></td>
                        <td>
                            <strong><?= htmlspecialchars($boat['nachname']) ?>, <?= htmlspecialchars($boat['vorname']) ?></strong><br>
                            <small>Telefon: <?= htmlspecialchars($boat['telefon'] ?? '-') ?> · E-Mail: <?= htmlspecialchars($boat['email'] ?? '-') ?></small><br>
                            <a class="button-link inline" href="index.php?jahr=<?= $boat['jahr'] ?>#license-<?= $boat['lizenz_id'] ?>">Zur Lizenz</a>
                        </td>
                        <td><?= htmlspecialchars((string)$boat['jahr']) ?></td>
                        <td><?= ($formattedDate = format_date($boat['zahlungsdatum'] ?? null)) ? htmlspecialchars($formattedDate) : '–' ?></td>
                        <td>
                            <?= nl2br(htmlspecialchars($boat['boot_notizen'] ?? '')) ?>
                            <?php if (!empty($boat['lizenz_notizen'])): ?>
                                <details>
                                    <summary>Lizenznotizen</summary>
                                    <?= nl2br(htmlspecialchars($boat['lizenz_notizen'])) ?>
                                </details>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </section>
</main>
<div class="modal" id="addBoatModal" hidden>
    <div class="modal-content">
        <header>
            <h2>Bootslizenz hinzufügen</h2>
            <button class="close" data-close>&times;</button>
        </header>
        <form id="addBoatForm">
            <section class="form-section">
                <div class="form-grid">
                    <label>Jahr
                        <input type="number" id="boatYear" min="2000" data-validate="required" required>
                    </label>
                    <label>Bootsnummer
                        <input type="text" id="boatNumber">
                    </label>
                    <label>Kosten (€)
                        <input type="number" id="boatCost" step="0.01" min="0" data-validate="required" required>
                    </label>
                    <label>Trinkgeld (€)
                        <input type="number" id="boatTip" step="0.01" min="0" value="0">
                    </label>
                    <label>Gesamt (€)
                        <input type="text" id="boatTotal" readonly>
                    </label>
                    <label>Zahlungsdatum
                        <input type="date" id="boatDate">
                    </label>
                </div>
                <h3>Lizenznehmer</h3>
                <div class="form-grid">
                    <label>Vorname
                        <input type="text" id="boatFirstName" data-validate="required" required>
                    </label>
                    <label>Nachname
                        <input type="text" id="boatLastName" data-validate="required" required>
                    </label>
                    <label>Straße
                        <input type="text" id="boatStreet">
                    </label>
                    <label>PLZ
                        <input type="text" id="boatZip" data-validate="zip">
                    </label>
                    <label>Ort
                        <input type="text" id="boatCity">
                    </label>
                    <label>Telefon
                        <input type="text" id="boatPhone">
                    </label>
                    <label>E-Mail
                        <input type="email" id="boatEmail" data-validate="email">
                    </label>
                    <label>Fischerkartennummer
                        <input type="text" id="boatCard">
                    </label>
                </div>
                <label>Lizenznotizen
                    <textarea id="boatLicenseNotes" rows="3"></textarea>
                </label>
                <label>Bootsnotizen
                    <textarea id="boatNotes" rows="3"></textarea>
                </label>
            </section>
            <footer class="modal-footer">
                <button type="button" class="secondary" data-close>Abbrechen</button>
                <button type="submit" class="primary">Speichern</button>
            </footer>
        </form>
    </div>
</div>

<script>const CURRENT_YEAR = <?= json_encode($currentYear) ?>; const BOOT_PRICE = <?= json_encode($prices['Boot'] ?? 0) ?>;</script>
<script src="assets/js/validation.js"></script>
<script src="assets/js/table-search.js"></script>
<script src="assets/js/boats.js"></script>
</body>
</html>
