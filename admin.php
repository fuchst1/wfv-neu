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

$referenceYear = $years ? max($years) : $currentYear;
$prices = $referenceYear ? get_license_prices((int)$referenceYear) : [];
$yearClosures = get_year_closures();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration · Wörderner Fischereiverein</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="app-header">
    <div class="branding">
        <h1>Administration</h1>
        <p>Wörderner Fischereiverein</p>
    </div>
    <?php
        $currentPage = 'admin';
        $yearDisplayLimit = null;
        include __DIR__ . '/partials/year_nav.php';
    ?>
</header>

<main>
    <section class="dashboard">
        <div>
            <h2>Jahre verwalten</h2>
            <p>Verfügbare Jahre: <strong><?= count($years) ?></strong></p>
        </div>
        <div>
            <button type="button" class="primary" id="openCreateYear">Neues Jahr anlegen</button>
        </div>
    </section>

    <section class="table-section">
        <h3>Bestehende Jahre</h3>
        <?php if (!$years): ?>
            <p class="empty">Es wurden noch keine Jahre angelegt.</p>
        <?php else: ?>
            <table class="summary-table" id="yearTable">
                <thead>
                    <tr>
                        <th>Jahr</th>
                        <th>Status</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_reverse($years) as $year): ?>
                        <?php $closure = $yearClosures[$year] ?? null; ?>
                        <?php $closedAt = $closure && isset($closure['abgeschlossen_am']) ? format_datetime($closure['abgeschlossen_am']) : null; ?>
                        <tr>
                            <td><?= $year ?></td>
                            <td>
                                <?php if ($closure): ?>
                                    <span class="badge badge-closed">Abgeschlossen</span>
                                    <?php if ($closedAt): ?>
                                        <br><small>am <?= htmlspecialchars($closedAt) ?></small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge">Offen</span>
                                <?php endif; ?>
                            </td>
                            <td class="actions">
                                <button type="button" class="secondary" data-close-year="<?= $year ?>"<?= $closure ? ' disabled aria-disabled="true"' : '' ?>>Jahr abschließen</button>
                                <button type="button" class="danger" data-delete-year="<?= $year ?>"<?= $closure ? ' disabled aria-disabled="true"' : '' ?>>Jahr löschen</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <section class="table-section">
        <h3>Lizenznehmer-Suche</h3>
        <form id="licenseeSearchForm" class="table-search" autocomplete="off">
            <label class="sr-only" for="licenseeSearchInput">Lizenznehmer suchen</label>
            <input type="search" id="licenseeSearchInput" name="query" placeholder="Name, Lizenznummer oder Ort" spellcheck="false" aria-describedby="licenseeSearchMessage">
            <button type="submit" class="primary">Suchen</button>
        </form>
        <p id="licenseeSearchMessage" class="search-message">Geben Sie einen Namen ein, um die Suche zu starten.</p>
        <div id="licenseeSearchResults" class="licensee-results" hidden></div>
    </section>
</main>

<div class="modal" id="createYearModal" hidden>
    <div class="modal-content">
        <header>
            <h2>Neues Jahr anlegen</h2>
            <button class="close" data-close>&times;</button>
        </header>
        <form id="createYearForm">
            <section class="form-section">
                <label>Jahr
                    <input type="number" id="newYear" min="2000" value="<?= (int)date('Y') + 1 ?>" data-validate="required" required>
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

<div class="modal" id="deleteYearModal" hidden>
    <div class="modal-content">
        <header>
            <h2>Jahr löschen</h2>
            <button class="close" data-close>&times;</button>
        </header>
        <p>Möchten Sie das Jahr <strong id="yearToDelete"></strong> wirklich löschen? Alle zugehörigen Daten werden entfernt.</p>
        <footer class="modal-footer">
            <button type="button" class="secondary" data-close>Abbrechen</button>
            <button type="button" class="danger" id="confirmDeleteYear">Löschen</button>
        </footer>
    </div>
</div>

<div class="modal" id="closeYearModal" hidden>
    <div class="modal-content">
        <header>
            <h2>Jahr abschließen</h2>
            <button class="close" data-close>&times;</button>
        </header>
        <p>Soll das Jahr <strong id="yearToClose"></strong> wirklich abgeschlossen werden? Danach sind keine Änderungen mehr möglich.</p>
        <footer class="modal-footer">
            <button type="button" class="secondary" data-close>Abbrechen</button>
            <button type="button" class="primary" id="confirmCloseYear">Abschließen</button>
        </footer>
    </div>
</div>

<script>
const LICENSE_PRICES = <?= json_encode($prices) ?>;
const EXISTING_YEARS = <?= json_encode($years) ?>;
</script>
<script src="assets/js/validation.js"></script>
<script src="assets/js/admin.js"></script>
</body>
</html>
