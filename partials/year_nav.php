<?php
$years = $years ?? available_years();
$currentYear = $currentYear ?? null;
if (!$currentYear && $years) {
    $currentYear = max($years);
}
if (!$currentYear) {
    $currentYear = (int)date('Y');
}
$currentPage = $currentPage ?? '';
$showCreateYearButton = $showCreateYearButton ?? false;
$yearDisplayLimit = isset($yearDisplayLimit) ? (int)$yearDisplayLimit : null;
$displayYears = $years;
if ($yearDisplayLimit !== null && $yearDisplayLimit > 0 && count($displayYears) > $yearDisplayLimit) {
    $displayYears = array_slice($displayYears, -$yearDisplayLimit);
    if (in_array($currentYear, $years, true) && !in_array($currentYear, $displayYears, true)) {
        $displayYears[0] = $currentYear;
        $displayYears = array_values(array_unique($displayYears));
        sort($displayYears);
        while (count($displayYears) > $yearDisplayLimit) {
            if ($displayYears[0] === $currentYear && count($displayYears) > 1) {
                array_splice($displayYears, 1, 1);
            } else {
                array_shift($displayYears);
            }
        }
    }
}
?>
<nav class="year-nav">
    <span>Jahr wählen:</span>
    <ul>
        <?php foreach ($displayYears as $year): ?>
            <li class="<?= (int)$year === (int)$currentYear ? 'active' : '' ?>">
                <a href="index.php?jahr=<?= $year ?>"><?= $year ?></a>
            </li>
        <?php endforeach; ?>
    </ul>
    <a class="button-link<?= $currentPage === 'neuwerber' ? ' active' : '' ?>" href="neuwerber.php">Neuwerber</a>
    <a class="button-link<?= $currentPage === 'boats' ? ' active' : '' ?>" href="boats.php">Bootsübersicht</a>
    <a class="button-link<?= $currentPage === 'blocklist' ? ' active' : '' ?>" href="sperrliste.php">Sperrliste</a>
    <a class="button-link<?= $currentPage === 'admin' ? ' active' : '' ?>" href="admin.php">Admin</a>
</nav>
