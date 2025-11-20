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

$blocklistEntries = get_blocklist_entries();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sperrliste · Wörderner Fischereiverein</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="app-header">
    <div class="branding">
        <h1>Sperrliste</h1>
        <p>Wörderner Fischereiverein</p>
    </div>
    <?php
        $currentPage = 'blocklist';
        include __DIR__ . '/partials/year_nav.php';
    ?>
</header>

<main>
    <section class="dashboard">
        <div>
            <h2>Gesperrte Lizenznehmer</h2>
            <p>Einträge gesamt: <strong><?= count($blocklistEntries) ?></strong></p>
        </div>
    </section>

    <section class="table-section">
        <div class="table-actions-bar">
            <div class="table-actions">
                <button type="button" class="primary" id="openAddBlockEntry">Person hinzufügen</button>
            </div>
        </div>
        <div class="table-search-row">
            <div class="table-search">
                <label class="table-search-label" for="blocklistSearch">Suche:</label>
                <input type="search" id="blocklistSearch" placeholder="Name oder Lizenznummer …" data-table-search="#blocklistTable">
            </div>
        </div>
        <table id="blocklistTable">
            <thead>
                <tr>
                    <th>Nachname</th>
                    <th>Vorname</th>
                    <th>Lizenznummer</th>
                    <th>Notiz</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$blocklistEntries): ?>
                <tr data-empty-row>
                    <td colspan="5" class="empty">Keine Einträge auf der Sperrliste vorhanden.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($blocklistEntries as $entry): ?>
                    <?php
                        $entryData = [
                            'id' => (int)$entry['id'],
                            'vorname' => $entry['vorname'],
                            'nachname' => $entry['nachname'],
                            'lizenznummer' => $entry['lizenznummer'] ?? null,
                            'notiz' => $entry['notiz'] ?? null,
                        ];
                    ?>
                    <tr data-entry='<?= json_encode($entryData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>'>
                        <td><?= htmlspecialchars($entry['nachname']) ?></td>
                        <td><?= htmlspecialchars($entry['vorname']) ?></td>
                        <td><?= htmlspecialchars($entry['lizenznummer'] ?? '–') ?></td>
                        <td><?= $entry['notiz'] ? nl2br(htmlspecialchars($entry['notiz'])) : '–' ?></td>
                        <td class="actions">
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

<div class="modal" id="blocklistModal" hidden>
    <div class="modal-content">
        <header>
            <h2 id="blocklistModalTitle">Person hinzufügen</h2>
            <button class="close" data-close>&times;</button>
        </header>
        <form id="blocklistForm">
            <input type="hidden" id="blocklistId">
            <section class="form-section">
                <div class="form-grid">
                    <label>
                        <span class="label-title">Vorname <span class="required-indicator" aria-hidden="true">*</span></span>
                        <input type="text" id="blocklistFirstName" data-validate="required" required>
                    </label>
                    <label>
                        <span class="label-title">Nachname <span class="required-indicator" aria-hidden="true">*</span></span>
                        <input type="text" id="blocklistLastName" data-validate="required" required>
                    </label>
                    <label>Lizenznummer
                        <input type="text" id="blocklistLicenseNumber">
                    </label>
                    <label>
                        <span class="label-title">Notiz</span>
                        <textarea id="blocklistNote" rows="3" placeholder="Grund für die Sperre …"></textarea>
                    </label>
                </div>
            </section>
            <footer class="modal-footer">
                <button type="button" class="secondary" data-close>Abbrechen</button>
                <button type="submit" class="primary">Speichern</button>
            </footer>
        </form>
    </div>
</div>

<div class="modal" id="blocklistDeleteModal" hidden>
    <div class="modal-content">
        <header>
            <h2>Eintrag löschen</h2>
            <button class="close" data-close>&times;</button>
        </header>
        <p id="blocklistDeleteText">Soll dieser Eintrag gelöscht werden?</p>
        <footer class="modal-footer">
            <button type="button" class="secondary" data-close>Abbrechen</button>
            <button type="button" class="danger" id="confirmBlocklistDelete">Löschen</button>
        </footer>
    </div>
</div>

<script src="assets/js/validation.js"></script>
<script src="assets/js/table-search.js"></script>
<script src="assets/js/blocklist.js"></script>
</body>
</html>
