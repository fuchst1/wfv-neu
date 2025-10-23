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
?>
<nav class="year-nav">
    <span>Jahr wählen:</span>
    <ul>
        <?php foreach ($years as $year): ?>
            <li class="<?= (int)$year === (int)$currentYear ? 'active' : '' ?>">
                <a href="index.php?jahr=<?= $year ?>"><?= $year ?></a>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php if ($showCreateYearButton): ?>
        <button class="primary" id="openCreateYear">Neues Jahr anlegen</button>
    <?php endif; ?>
    <a class="button-link<?= $currentPage === 'neuwerber' ? ' active' : '' ?>" href="neuwerber.php">Neuwerber</a>
    <a class="button-link<?= $currentPage === 'boats' ? ' active' : '' ?>" href="boats.php">Bootsübersicht</a>
</nav>
