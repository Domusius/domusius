<?php
/**
 * Widget: Gennemsnitlig elpris (sidste 7 dage)
 * Baseret på tabellen 'electricity_prices' (samlet pris pr. kWh)
 */
require_once __DIR__ . '/../functions.php';

$region = strtoupper(elpriser_setting($pdo, 'region', 'DK1'));

$stmtAvg = $pdo->prepare("
    SELECT AVG(price_gns)
    FROM electricity_prices
    WHERE region = ?
    AND dato >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
");
$stmtAvg->execute([$region]);
$avg_price = (float)($stmtAvg->fetchColumn() ?: 0);

// Farvekode
$color = "#22c55e"; // Grøn
if ($avg_price > 1.5) $color = "#eab308"; // Gul
if ($avg_price > 2.5) $color = "#ef4444"; // Rød
?>

<div style="text-align: center; padding: 10px;">
    <div style="font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; margin-bottom: 5px;">
        Gns. pris (7 dage) - <?= htmlspecialchars($region) ?>
    </div>
    <div style="font-size: 24px; font-weight: 900; color: <?= $color ?>;">
        <?= number_format($avg_price, 2, ',', '.') ?> <span style="font-size: 14px;">kr/kWh</span>
    </div>
    <div style="font-size: 10px; color: #94a3b8; margin-top: 5px;">
        Baseret på daglige gennemsnit
    </div>
</div>
