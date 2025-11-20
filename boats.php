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

$boats = get_boats_overview();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bootsübersicht · Wörderner Fischereiverein</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body data-current-year="<?= (int)$currentYear ?>">
<header class="app-header">
    <div class="branding">
        <h1>Bootsübersicht</h1>
        <p>Wörderner Fischereiverein</p>
    </div>
    <?php
        $currentPage = 'boats';
        include __DIR__ . '/partials/year_nav.php';
    ?>
</header>

<main>
    <section class="dashboard">
        <div>
            <h2>Alle Boote</h2>
            <p>Erfasste Boote gesamt: <strong><?= count($boats) ?></strong></p>
        </div>
    </section>

    <section class="table-section">
        <div class="table-filter">
            <div class="table-search">
                <label class="table-search-label" for="boatSearch">Suche:</label>
                <input type="search" id="boatSearch" placeholder="Bootsnummer oder Notizen …" data-table-search="#boatTable">
            </div>
            <div class="table-actions">
                <button type="button" class="primary" id="openAddBoat">Boot hinzufügen</button>
            </div>
        </div>
        <table id="boatTable">
            <thead>
                <tr>
                    <th>Bootsnummer</th>
                    <th>Lizenznehmer</th>
                    <th>Bootsnotizen</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$boats): ?>
                <tr data-empty-row>
                    <td colspan="4" class="empty">Keine Boote erfasst.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($boats as $boat): ?>
                    <?php
                        $boatData = [
                            'id' => $boat['boot_id'] !== null ? (int)$boat['boot_id'] : null,
                            'jahr' => isset($boat['jahr']) ? (int)$boat['jahr'] : null,
                            'bootnummer' => $boat['bootnummer'],
                            'notizen' => $boat['boot_notizen'],
                            'lizenz_id' => $boat['lizenz_id'] !== null ? (int)$boat['lizenz_id'] : null,
                            'lizenznehmer' => [
                                'id' => $boat['lizenznehmer_id'] !== null ? (int)$boat['lizenznehmer_id'] : null,
                                'vorname' => $boat['vorname'] ?? null,
                                'nachname' => $boat['nachname'] ?? null,
                            ],
                        ];
                    ?>
                    <tr data-boat='<?= json_encode($boatData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>'>
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
                        <td class="actions">
                            <button type="button" class="primary assign">Lizenznehmer zuweisen</button>
                            <button type="button" class="secondary edit">Bearbeiten</button>
                            <button type="button" class="danger delete">Löschen</button>
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
            <h2 id="boatModalTitle">Boot hinzufügen</h2>
            <button class="close" data-close>&times;</button>
        </header>
        <form id="addBoatForm">
            <input type="hidden" id="boatId">
            <section class="form-section">
                <label>Bootsnummer
                    <input type="text" id="boatNumber">
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

<div class="modal" id="assignLicenseModal" hidden>
    <div class="modal-content">
        <header>
            <h2>Lizenznehmer zuweisen</h2>
            <button class="close" data-close>&times;</button>
        </header>
        <form id="assignLicenseForm">
            <input type="hidden" id="assignBoatId">
            <section class="form-section">
                <p id="assignBoatInfo"></p>
                <label for="assignLicenseSelect">Lizenznehmer wählen</label>
                <select id="assignLicenseSelect">
                    <option value="">Kein Lizenznehmer</option>
                </select>
                <p class="form-hint" id="assignLicenseHint"></p>
            </section>
            <footer class="modal-footer">
                <button type="button" class="secondary" data-close>Abbrechen</button>
                <button type="submit" class="primary">Speichern</button>
            </footer>
        </form>
    </div>
</div>

<script src="assets/js/validation.js"></script>
<script src="assets/js/table-search.js"></script>
<script src="assets/js/boats.js"></script>
</body>
</html>
