<?php
require_once __DIR__ . '/lib/functions.php';

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
<body>
<header class="app-header">
    <div class="branding">
        <h1>Bootsübersicht</h1>
        <p>Wörderner Fischereiverein</p>
    </div>
    <nav class="year-nav">
        <a class="button-link" href="index.php">Zurück zur Lizenzverwaltung</a>
    </nav>
</header>

<main>
    <section class="dashboard">
        <div>
            <h2>Alle Boote</h2>
            <p>Erfasste Boote gesamt: <strong><?= count($boats) ?></strong></p>
        </div>
    </section>

    <section class="table-section">
        <table>
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
                <tr>
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
                        <td><?= $boat['zahlungsdatum'] ? htmlspecialchars($boat['zahlungsdatum']) : '–' ?></td>
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
</body>
</html>
