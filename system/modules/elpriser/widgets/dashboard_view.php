<?php
/**
 * system/modules/elpriser/widgets/dashboard_view.php
 * Dagens pris (eller den nyeste, hvis i dag ikke findes endnu).
 * Kolonner: price_gns, price_max, price_min, dato
 */
require_once __DIR__ . '/../functions.php';

try {
    $region = strtoupper(elpriser_setting($pdo, 'region', 'DK1'));

    $price_stmt = $pdo->prepare("SELECT dato, price_gns, price_max, price_min FROM electricity_prices WHERE region = ? AND dato = ? LIMIT 1");
    $price_stmt->execute([$region, date('Y-m-d')]);
    $prices = $price_stmt->fetch(PDO::FETCH_ASSOC);

    // Hvis i dag ikke findes endnu, tager vi den nyeste
    if (!$prices) {
        $price_stmt = $pdo->prepare("SELECT dato, price_gns, price_max, price_min FROM electricity_prices WHERE region = ? ORDER BY dato DESC LIMIT 1");
        $price_stmt->execute([$region]);
        $prices = $price_stmt->fetch(PDO::FETCH_ASSOC);
    }

    $gns = number_format($prices['price_gns'] ?? 0, 2, ',', '.');
    $max = number_format($prices['price_max'] ?? 0, 2, ',', '.');
    $min = number_format($prices['price_min'] ?? 0, 2, ',', '.');
    $isToday = isset($prices['dato']) && $prices['dato'] === date('Y-m-d');
    $label = !$prices ? 'Ingen priser endnu' : ($isToday ? 'Gennemsnit i dag' : 'Gennemsnit ' . date('d-m', strtotime($prices['dato'])));
} catch (PDOException $e) {
    echo "<p style='color: #94a3b8; font-size: 0.8em;'>Fejl i elpris-data.</p>";
    return;
}
?>

<div style="display: flex; flex-direction: column; gap: 10px;">
    <div style="text-align: center; padding: 10px; background: #f8fafc; border-radius: 12px; border: 1px solid #e2e8f0;">
        <div style="font-size: 0.7em; color: #64748b; font-weight: 800; text-transform: uppercase;"><?= htmlspecialchars($label) ?></div>
        <div style="font-size: 1.5em; font-weight: 900; color: #3b82f6;"><?= $gns ?> <span style="font-size: 0.5em;">kr/kWh</span></div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
        <div style="padding: 8px; background: #fff5f5; border-radius: 8px; text-align: center; border: 1px solid #fed7d7;">
            <div style="font-size: 0.6em; color: #9b2c2c; font-weight: 700;">MAX</div>
            <div style="font-weight: 800; color: #9b2c2c;"><?= $max ?></div>
        </div>
        <div style="padding: 8px; background: #f0fdf4; border-radius: 8px; text-align: center; border: 1px solid #dcfce7;">
            <div style="font-size: 0.6em; color: #166534; font-weight: 700;">MIN</div>
            <div style="font-weight: 800; color: #166534;"><?= $min ?></div>
        </div>
    </div>
</div>
