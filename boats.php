<?php
require_once __DIR__ . '/lib/functions.php';

$boats = get_boats_overview();
$latestYear = latest_year();
$currentYear = $latestYear ?: (int)date('Y');
ensure_year_exists($currentYear);
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
                <input type="search" id="boatSearch" placeholder="Bootsnummer oder Notizen …" data-table-search="#boatTable">
            </label>
        </div>
        <table id="boatTable">
            <thead>
                <tr>
                    <th>Bootsnummer</th>
                    <th>Lizenznehmer</th>
                    <th>Bootsnotizen</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$boats): ?>
                <tr data-empty-row>
                    <td colspan="3" class="empty">Keine Boote erfasst.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($boats as $boat): ?>
                    <tr>
                        <td><?= htmlspecialchars($boat['bootnummer'] ?? '–') ?></td>
                        <td>
                            <?php if (!empty($boat['nachname']) || !empty($boat['vorname'])): ?>
                                <strong><?= htmlspecialchars($boat['nachname'] ?? '') ?><?= !empty($boat['nachname']) && !empty($boat['vorname']) ? ', ' : '' ?><?= htmlspecialchars($boat['vorname'] ?? '') ?></strong><br>
                                <small>Telefon: <?= htmlspecialchars($boat['telefon'] ?? '-') ?> · E-Mail: <?= htmlspecialchars($boat['email'] ?? '-') ?></small><br>
                                <?php if (!empty($boat['lizenz_id'])): ?>
                                    <a class="button-link inline" href="index.php?jahr=<?= $boat['jahr'] ?>#license-<?= $boat['lizenz_id'] ?>">Zur Lizenz</a>
                                <?php endif; ?>
                            <?php else: ?>
                                <em>Kein Lizenznehmer zugewiesen</em>
                            <?php endif; ?>
                        </td>
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
            <h2>Boot hinzufügen</h2>
            <button class="close" data-close>&times;</button>
        </header>
        <form id="addBoatForm">
            <section class="form-section">
                <label>Bootsnummer
                    <input type="text" id="boatNumber" data-validate="required" required>
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

<script>const CURRENT_YEAR = <?= json_encode($currentYear) ?>;</script>
<script src="assets/js/validation.js"></script>
<script src="assets/js/table-search.js"></script>
<script src="assets/js/boats.js"></script>
</body>
</html>
