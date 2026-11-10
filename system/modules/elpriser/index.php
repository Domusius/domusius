<?php
// system/modules/elpriser/index.php
// Modulets side: vises på ?side=elpriser
// $pdo, $user og $site_name er tilgængelige fra kernen.

require_once __DIR__ . '/functions.php';

$monthNames = [1 => 'Januar', 'Februar', 'Marts', 'April', 'Maj', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'December'];
$monthShort = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Maj', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dec'];

$region = strtoupper(elpriser_setting($pdo, 'region', 'DK1'));

/**
 * 1. STARTPUNKT: den første måned, der har data
 */
$stmt = $pdo->prepare("SELECT MIN(dato) FROM electricity_prices WHERE region = ?");
$stmt->execute([$region]);
$firstDate = $stmt->fetchColumn();
$startTS   = strtotime(date('Y-m-01', $firstDate ? strtotime($firstDate) : time()));
$nowMonthTS = strtotime(date('Y-m-01'));

/**
 * 2. NAVIGATION: værdierne fra adresselinjen valideres, før de bruges
 */
$view = (($_GET['view'] ?? '') === 'year') ? 'year' : 'month';
$date = (string)($_GET['date'] ?? '');
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $date)) {
    $date = date('Y-m');
}
$currentTime = strtotime($date . '-01');

// Man kan ikke navigere til før første data eller ind i fremtiden
if ($currentTime < $startTS) {
    $currentTime = $startTS;
}
if ($currentTime > $nowMonthTS) {
    $currentTime = $nowMonthTS;
}
$date = date('Y-m', $currentTime);
$selectedYear = (int)date('Y', $currentTime);

$prevMonthTS = strtotime('-1 month', $currentTime);
$nextMonthTS = strtotime('+1 month', $currentTime);
$prevYearTS  = strtotime('-1 year', $currentTime);
$nextYearTS  = strtotime('+1 year', $currentTime);

$prevYearDisabled  = ((int)date('Y', $prevYearTS) < (int)date('Y', $startTS)) ? 'disabled' : '';
$prevMonthDisabled = ($prevMonthTS < $startTS) ? 'disabled' : '';
$nextYearDisabled  = ($nextYearTS > $nowMonthTS) ? 'disabled' : '';
$nextMonthDisabled = ($nextMonthTS > $nowMonthTS) ? 'disabled' : '';

$link = function (int $ts) use ($view): string {
    return '?side=elpriser&amp;view=' . $view . '&amp;date=' . date('Y-m', $ts);
};

/**
 * 3. DATA
 */
$stmt = $pdo->prepare("SELECT price_gns FROM electricity_prices WHERE region = ? ORDER BY dato DESC LIMIT 1");
$stmt->execute([$region]);
$stats_today = (float)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT AVG(price_gns) FROM electricity_prices WHERE region = ? AND dato > DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
$stmt->execute([$region]);
$stats_30days = (float)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT AVG(price_gns) FROM electricity_prices WHERE region = ? AND YEAR(dato) = ?");
$stmt->execute([$region, $selectedYear]);
$avg_selected_year = (float)$stmt->fetchColumn();

if ($view === 'year') {
    $stmt = $pdo->prepare("SELECT DATE_FORMAT(dato, '%Y-%m') AS periode, AVG(price_gns) AS price_gns FROM electricity_prices WHERE region = ? AND YEAR(dato) = ? GROUP BY periode ORDER BY periode ASC");
    $stmt->execute([$region, $selectedYear]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM electricity_prices WHERE region = ? AND dato >= ? AND dato < ? ORDER BY dato ASC");
    $stmt->execute([$region, date('Y-m-01', $currentTime), date('Y-m-01', $nextMonthTS)]);
}
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);

$avg_period = !empty($history) ? array_sum(array_column($history, 'price_gns')) / count($history) : 0;

/**
 * 4. AKSE OG LAYOUT
 */
$max_val   = !empty($history) ? max(array_column($history, 'price_gns')) : 0;
$max_axis  = ($max_val > 0) ? ceil($max_val * 1.25) : 5;
$steps     = 5;
$step_size = $max_axis / $steps;
?>

<div class="el-module">
    <div class="el-navigation">
        <div class="nav-combined">
            <div class="nav-unit">
                <span class="nav-label">ÅR</span>
                <div class="nav-controls">
                    <a href="<?= $prevYearDisabled ? '#' : $link($prevYearTS) ?>" class="nav-btn <?= $prevYearDisabled ?>"><i class="fas fa-angle-double-left"></i></a>
                    <span class="nav-display"><?= $selectedYear ?></span>
                    <a href="<?= $nextYearDisabled ? '#' : $link($nextYearTS) ?>" class="nav-btn <?= $nextYearDisabled ?>"><i class="fas fa-angle-double-right"></i></a>
                </div>
            </div>
            <?php if ($view !== 'year'): ?>
            <div class="nav-unit">
                <span class="nav-label">MÅNED</span>
                <div class="nav-controls">
                    <a href="<?= $prevMonthDisabled ? '#' : $link($prevMonthTS) ?>" class="nav-btn <?= $prevMonthDisabled ?>"><i class="fas fa-chevron-left"></i></a>
                    <span class="nav-display"><?= $monthNames[(int)date('n', $currentTime)] ?></span>
                    <a href="<?= $nextMonthDisabled ? '#' : $link($nextMonthTS) ?>" class="nav-btn <?= $nextMonthDisabled ?>"><i class="fas fa-chevron-right"></i></a>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <div class="view-switcher">
            <a href="?side=elpriser&amp;view=month&amp;date=<?= $date ?>" class="view-btn <?= $view === 'month' ? 'active' : '' ?>">Måned</a>
            <a href="?side=elpriser&amp;view=year&amp;date=<?= $date ?>" class="view-btn <?= $view === 'year' ? 'active' : '' ?>">År</a>
        </div>
    </div>

    <div class="el-stats-grid">
        <div class="el-card"><span class="el-label">SENESTE PRIS</span><span class="el-value"><?= number_format($stats_today, 2, ',', '.') ?> kr</span></div>
        <div class="el-card"><span class="el-label">GNS. FOR <?= $selectedYear ?></span><span class="el-value"><?= number_format($avg_selected_year, 2, ',', '.') ?> kr</span></div>
        <div class="el-card highlight"><span class="el-label">GNS. PERIODE</span><span class="el-value"><?= number_format($avg_period, 2, ',', '.') ?> kr</span></div>
        <div class="el-card"><span class="el-label">SIDSTE 30 DAGE</span><span class="el-value"><?= number_format($stats_30days, 2, ',', '.') ?> kr</span></div>
    </div>

    <div class="el-chart-box" style="padding: 40px 20px 60px 20px;">
        <?php if (!empty($history)): ?>
            <div style="display: flex; height: 300px; position: relative;">

                <div style="display: flex; flex-direction: column-reverse; justify-content: space-between; width: 50px; color: #999; font-size: 0.75rem; border-right: 2px solid #f0f0f0; text-align: right; padding-right: 10px;">
                    <?php for ($i = 0; $i <= $steps; $i++): ?>
                        <span style="height: 0; line-height: 0; display: flex; align-items: center; justify-content: flex-end;"><?= number_format($i * $step_size, 1, ',', '.') ?></span>
                    <?php endfor; ?>
                </div>

                <div style="flex: 1; display: flex; align-items: flex-end; gap: 4px; position: relative; padding-left: 5px;">

                    <?php for ($i = 1; $i <= $steps; $i++): ?>
                        <div style="position: absolute; left: 0; right: 0; bottom: <?= ($i / $steps) * 100 ?>%; border-bottom: 1px dashed #eee; height: 0; z-index: 1;"></div>
                    <?php endfor; ?>

                    <?php foreach ($history as $row):
                        $val = (float)$row['price_gns'];
                        $heightPct = ($val / $max_axis) * 100;

                        // Farve-logik baseret på gennemsnit
                        if ($val > ($avg_period * 1.2)) $color = '#d32f2f';
                        elseif ($val > $avg_period) $color = '#f39c12';
                        elseif ($val > ($avg_period * 0.8)) $color = '#2ecc71';
                        else $color = '#27ae60';

                        $label = $view === 'year'
                            ? $monthShort[(int)substr($row['periode'], 5, 2)]
                            : date('d.', strtotime($row['dato']));
                    ?>
                        <div style="flex: 1; display: flex; flex-direction: column; justify-content: flex-end; height: 100%; position: relative; z-index: 2;">

                            <span style="position: absolute; bottom: <?= $heightPct ?>%; left: 50%; transform: translate(-50%, -5px); font-size: 0.65rem; font-weight: bold; color: #444; white-space: nowrap;">
                                <?= number_format($val, 2, ',', '.') ?>
                            </span>

                            <div style="height: <?= max(0.5, $heightPct) ?>%; background-color: <?= $color ?>; width: 100%; border-radius: 2px 2px 0 0;"
                                 title="<?= htmlspecialchars($label) ?>: <?= number_format($val, 2, ',', '.') ?> kr">
                            </div>

                            <span style="position: absolute; top: 100%; left: 0; right: 0; text-align: center; font-size: 0.7rem; color: #888; padding-top: 8px;">
                                <?= htmlspecialchars($label) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="no-data-msg" style="padding: 50px;">Ingen data fundet for denne periode.</div>
        <?php endif; ?>
    </div>

    <?php if (elpriser_setting($pdo, 'fetch_method', 'energinet') === 'energinet'): ?>
        <div style="margin-top: 10px; font-size: 0.75rem; color: #999; text-align: right;">Spotpriser: Energi Data Service (Energinet). Priserne er samlede priser pr. kWh med afgifter og moms.</div>
    <?php endif; ?>
</div>
