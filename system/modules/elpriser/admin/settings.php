<?php
// system/modules/elpriser/admin/settings.php
// Modulets opsætning i admin. Kun administratorer.
if (!isset($user) || $user['role'] !== 'admin') {
    exit('Adgang nægtet');
}

// Sikkerhedstoken (CSRF). Kernen kan allerede have indlæst det.
if (!function_exists('csrf_token')) {
    require_once __DIR__ . '/../../../../inc/csrf.php';
}
require_once __DIR__ . '/../functions.php';

$current_module_key = 'elpriser';   // skal være det samme som "key" i module.json
$message = '';
$error = '';

// Læs tilgængelige widgets fra widgets/widgets.json
$widgets_folder = __DIR__ . '/../widgets/';
$available_widgets = [];
if (file_exists($widgets_folder . 'widgets.json')) {
    $data = json_decode(file_get_contents($widgets_folder . 'widgets.json'), true);
    $available_widgets = $data['widgets'] ?? [];
}

$engines = elpriser_engines();

/**
 * Tal fra en formular (komma eller punktum). Giver null, hvis det ikke er et tal i intervallet.
 */
$num = function ($v, float $min, float $max): ?float {
    $v = str_replace(',', '.', trim((string)$v));
    if ($v === '' || !is_numeric($v)) {
        return null;
    }
    $f = (float)$v;
    return ($f < $min || $f > $max) ? null : $f;
};

// Alle formularer sender sikkerhedstokenet med (csrf_field()). Uden et gyldigt token gemmes der ingenting.
$isPost  = $_SERVER['REQUEST_METHOD'] === 'POST';
$tokenOk = !$isPost || csrf_check($_POST['csrf'] ?? '');
if ($isPost && !$tokenOk) {
    $error = 'Handlingen blev afvist, fordi sikkerhedstokenet manglede eller var forkert. Prøv igen fra denne side.';
}

// 1. Dashboard-layout (vis/skjul, størrelse, rækkefølge)
if ($isPost && $tokenOk && isset($_POST['save_layout'])) {
    $newState = isset($_POST['show_on_dashboard']) ? 1 : 0;

    // Kun en widget-fil, der står i widgets.json, må vælges
    $allowedFiles = array_column($available_widgets, 'file');
    $newWidget = $_POST['dashboard_widget_type'] ?? '';
    if (!in_array($newWidget, $allowedFiles, true)) {
        $newWidget = $allowedFiles[0] ?? 'dashboard_view.php';
    }

    // Kun de størrelser, databasen kender
    $newSize = $_POST['dashboard_size'] ?? 'medium';
    if (!in_array($newSize, ['small', 'medium', 'full'], true)) {
        $newSize = 'medium';
    }
    $newOrder = (int)($_POST['dashboard_order'] ?? 0);

    $stmt = $pdo->prepare("UPDATE modules SET show_on_dashboard = ?, dashboard_widget_type = ?, dashboard_size = ?, dashboard_order = ? WHERE module_key = ?");
    $stmt->execute([$newState, $newWidget, $newSize, $newOrder, $current_module_key]);
    $message = 'Dashboard layout opdateret!';
}

// 2. Kilde, region, moms og automatisk hentning
if ($isPost && $tokenOk && isset($_POST['save_source'])) {
    $region = strtoupper(trim((string)($_POST['region'] ?? '')));
    $method = (string)($_POST['fetch_method'] ?? '');
    $url    = trim((string)($_POST['sheets_url'] ?? ''));
    $vat    = $num($_POST['vat_percent'] ?? '', 0, 100);
    $start  = trim((string)($_POST['start_date'] ?? ''));

    if (!in_array($region, ['DK1', 'DK2'], true)) {
        $error = 'Vælg DK1 eller DK2.';
    } elseif (!isset($engines[$method])) {
        $error = 'Vælg en af de kilder, der står på listen.';
    } elseif ($url !== '' && !elpriser_valid_https_url($url)) {
        $error = 'Adressen til Google-arket skal begynde med https:// og må ikke indeholde mellemrum.';
    } elseif ($vat === null) {
        $error = 'Moms skal være et tal mellem 0 og 100.';
    } elseif ($start !== '' && !elpriser_valid_date($start)) {
        $error = 'Startdatoen skal være en rigtig dato (ÅÅÅÅ-MM-DD).';
    } else {
        elpriser_save_setting($pdo, 'region', $region);
        elpriser_save_setting($pdo, 'fetch_method', $method);
        elpriser_save_setting($pdo, 'sheets_url', $url);
        elpriser_save_setting($pdo, 'vat_percent', (string)$vat);
        elpriser_save_setting($pdo, 'start_date', $start);
        elpriser_save_setting($pdo, 'auto_fetch', isset($_POST['auto_fetch']) ? '1' : '0');
        $message = 'Indstillingerne er gemt.';
    }
}

// 3. Afgifter: tilføj eller ret en række
if ($isPost && $tokenOk && isset($_POST['add_rate'])) {
    $rateDate = trim((string)($_POST['rate_date'] ?? ''));
    $afgift   = $num($_POST['elafgift'] ?? '', 0, 99);
    $transp   = $num($_POST['transport'] ?? '', 0, 99);
    $abon     = $num($_POST['abonnement'] ?? '', 0, 99);

    if (!elpriser_valid_date($rateDate)) {
        $error = 'Vælg en rigtig startdato.';
    } elseif ($afgift === null || $transp === null || $abon === null) {
        $error = 'Elafgift, transport og abonnement skal være tal mellem 0 og 99 (kr pr. kWh).';
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO electricity_rates_history (start_dato, elafgift, transport_samlet, abonnement_kwh)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE elafgift = VALUES(elafgift), transport_samlet = VALUES(transport_samlet), abonnement_kwh = VALUES(abonnement_kwh)
        ");
        $stmt->execute([$rateDate, $afgift, $transp, $abon]);
        $message = 'Afgifterne er gemt. De bruges på dage, der hentes herefter. Hent perioden igen for at regne ældre dage om.';
    }
}

// 4. Afgifter: slet en række
if ($isPost && $tokenOk && isset($_POST['delete_rate'])) {
    $pdo->prepare("DELETE FROM electricity_rates_history WHERE id = ?")->execute([(int)$_POST['delete_rate']]);
    $message = 'Rækken er slettet.';
}

// 5. Hent priser nu
if ($isPost && $tokenOk && (isset($_POST['sync_missing']) || isset($_POST['sync_range']))) {
    @set_time_limit(120);
    if (isset($_POST['sync_missing'])) {
        $res = elpriser_sync_missing($pdo, 60);
    } else {
        $res = elpriser_sync_range($pdo, trim((string)($_POST['sync_from'] ?? '')), trim((string)($_POST['sync_to'] ?? '')));
    }
    if ($res['ok']) {
        $message = $res['message'];
    } else {
        $error = $res['message'];
    }
}

// Hent nuværende værdier
$modStmt = $pdo->prepare("SELECT show_on_dashboard, dashboard_widget_type, dashboard_size, dashboard_order FROM modules WHERE module_key = ?");
$modStmt->execute([$current_module_key]);
$moduleData = $modStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$showOnDash    = (int)($moduleData['show_on_dashboard'] ?? 0);
$currentWidget = $moduleData['dashboard_widget_type'] ?? '';
$currentSize   = $moduleData['dashboard_size'] ?? 'medium';
$currentOrder  = (int)($moduleData['dashboard_order'] ?? 0);

$cfgRegion = strtoupper(elpriser_setting($pdo, 'region', 'DK1'));
$cfgMethod = elpriser_setting($pdo, 'fetch_method', 'energinet');
$cfgUrl    = elpriser_setting($pdo, 'sheets_url', '');
$cfgVat    = elpriser_setting($pdo, 'vat_percent', '25');
$cfgStart  = elpriser_setting($pdo, 'start_date', '');
$cfgAuto   = elpriser_setting($pdo, 'auto_fetch', '0') === '1';

$rates = $pdo->query("SELECT id, start_dato, elafgift, transport_samlet, abonnement_kwh FROM electricity_rates_history ORDER BY start_dato DESC")->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT COUNT(*), MIN(dato), MAX(dato) FROM electricity_prices WHERE region = ?");
$stmt->execute([$cfgRegion]);
[$daysCount, $firstDay, $lastDay] = $stmt->fetch(PDO::FETCH_NUM);

$stmt = $pdo->prepare("SELECT dato, price_gns, price_max, price_min, spot_price_raw FROM electricity_prices WHERE region = ? ORDER BY dato DESC LIMIT 10");
$stmt->execute([$cfgRegion]);
$latest = $stmt->fetchAll(PDO::FETCH_ASSOC);

$inp = 'width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box;';
$box = 'padding: 20px; border: 1px solid #eee; border-radius: 8px;';
$hint = 'display: block; color: #888; margin-top: 5px; font-size: 0.8rem;';
?>
<h3>El-pris Opsætning</h3>

<?php if ($error): ?>
    <div style="background: #f8d7da; color: #721c24; padding: 10px 15px; border-radius: 8px; margin-bottom: 20px;">
        <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<?php if ($message): ?>
    <div style="background: #d4edda; color: #155724; padding: 10px 15px; border-radius: 8px; margin-bottom: 20px;">
        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<!-- Dashboard: modulet vises først på dashboardet, når du slår det til her -->
<div style="background: white; padding: 15px 20px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 25px;">
    <form method="post" style="display: flex; align-items: center; justify-content: space-between; gap: 15px; flex-wrap: wrap;">
        <?= csrf_field() ?>
        <div style="display: flex; align-items: center; gap: 12px;">
            <input type="checkbox" name="show_on_dashboard" value="1" <?= $showOnDash ? 'checked' : '' ?> style="width: 18px; height: 18px; cursor: pointer;">
            <span style="font-weight: 700; color: #334155;">Vis på dashboard</span>
            <span style="background: <?= $showOnDash ? '#dcfce7' : '#fee2e2' ?>; color: <?= $showOnDash ? '#166534' : '#991b1b' ?>; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: 800;">
                <?= $showOnDash ? 'SYNLIG' : 'SKJULT' ?>
            </span>
        </div>

        <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
            <select name="dashboard_widget_type" style="padding: 6px 10px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;">
                <?php foreach ($available_widgets as $widget): ?>
                    <option value="<?= htmlspecialchars($widget['file']) ?>" <?= ($currentWidget === $widget['file']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($widget['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <div style="font-size: 11px; font-weight: 700; color: #64748b;">STØRRELSE:
                <select name="dashboard_size" style="padding: 6px; border-radius: 6px; border: 1px solid #cbd5e1; margin-left: 5px;">
                    <option value="small"  <?= $currentSize === 'small'  ? 'selected' : '' ?>>Lille</option>
                    <option value="medium" <?= $currentSize === 'medium' ? 'selected' : '' ?>>Mellem</option>
                    <option value="full"   <?= $currentSize === 'full'   ? 'selected' : '' ?>>Fuld bredde</option>
                </select>
            </div>

            <div style="font-size: 11px; font-weight: 700; color: #64748b;">NR:
                <input type="number" name="dashboard_order" value="<?= $currentOrder ?>" style="width: 55px; padding: 5px; border: 1px solid #cbd5e1; border-radius: 6px; margin-left: 5px;">
            </div>

            <button type="submit" name="save_layout" style="background: #1e293b; color: white; border: none; padding: 8px 15px; border-radius: 6px; font-weight: 700; cursor: pointer; font-size: 12px; width: auto;">Gem layout</button>
        </div>
    </form>
</div>

<!-- Kilde og region -->
<div class="content-card" style="margin-bottom: 25px;">
    <h3><i class="fas fa-plug"></i> Kilde og region</h3>
    <form method="post">
        <?= csrf_field() ?>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 15px;">
            <div style="<?= $box ?>">
                <h4 style="margin-top: 0;">Hvor kommer priserne fra?</h4>
                <div style="margin-bottom: 15px;">
                    <label>Kilde:</label>
                    <select name="fetch_method" style="<?= $inp ?>">
                        <?php foreach ($engines as $id => $label): ?>
                            <option value="<?= htmlspecialchars($id) ?>" <?= $cfgMethod === $id ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="margin-bottom: 15px;">
                    <label>Prisområde:</label>
                    <select name="region" style="<?= $inp ?>">
                        <option value="DK1" <?= $cfgRegion === 'DK1' ? 'selected' : '' ?>>DK1 (Vestdanmark)</option>
                        <option value="DK2" <?= $cfgRegion === 'DK2' ? 'selected' : '' ?>>DK2 (Østdanmark)</option>
                    </select>
                </div>
                <div>
                    <label>Adresse til Google-ark (kun hvis kilden er Google Sheets):</label>
                    <input type="text" name="sheets_url" value="<?= htmlspecialchars($cfgUrl) ?>" placeholder="https://docs.google.com/spreadsheets/d/e/.../pub?output=csv" style="<?= $inp ?>">
                    <small style="<?= $hint ?>">Arket skal være udgivet på nettet som CSV. Se vejledningen nederst.</small>
                </div>
            </div>

            <div style="<?= $box ?>">
                <h4 style="margin-top: 0;">Beregning og hentning</h4>
                <div style="margin-bottom: 15px;">
                    <label>Moms (%):</label>
                    <input type="text" name="vat_percent" value="<?= htmlspecialchars($cfgVat) ?>" style="<?= $inp ?>">
                    <small style="<?= $hint ?>">Lægges på spotpris, elafgift, transport og abonnement.</small>
                </div>
                <div style="margin-bottom: 15px;">
                    <label>Hent historik fra (valgfri):</label>
                    <input type="date" name="start_date" value="<?= htmlspecialchars($cfgStart) ?>" style="<?= $inp ?>">
                    <small style="<?= $hint ?>">Første gang hentes der kun 30 dage tilbage. Vælg en dato her for at starte tidligere.</small>
                </div>
                <label style="display: block; cursor: pointer;">
                    <input type="checkbox" name="auto_fetch" value="1" <?= $cfgAuto ? 'checked' : '' ?>>
                    Hent manglende dage automatisk, når nogen logger ind
                </label>
            </div>
        </div>
        <button type="submit" name="save_source" class="btn btn-primary" style="width: 100%; margin-top: 20px;">
            <i class="fas fa-save"></i> Gem indstillinger
        </button>
    </form>
</div>

<!-- Afgifter -->
<div class="content-card" style="margin-bottom: 25px;">
    <h3><i class="fas fa-coins"></i> Afgifter og tarif</h3>
    <p style="font-size: 0.9rem; color: #666; margin: 5px 0 0;">Samlet pris pr. kWh = (spotpris + elafgift + transport + abonnement) &times; (1 + moms). Alle tal er i kr pr. kWh uden moms. En række gælder fra sin startdato og til den næste.</p>
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 15px;">
        <div style="<?= $box ?>">
            <h4 style="margin-top: 0;">Tilføj eller ret</h4>
            <form method="post">
                <?= csrf_field() ?>
                <div style="margin-bottom: 12px;">
                    <label>Gælder fra:</label>
                    <input type="date" name="rate_date" required style="<?= $inp ?>">
                </div>
                <div style="margin-bottom: 12px;">
                    <label>Elafgift (kr/kWh):</label>
                    <input type="text" name="elafgift" required style="<?= $inp ?>">
                </div>
                <div style="margin-bottom: 12px;">
                    <label>Transport samlet (kr/kWh):</label>
                    <input type="text" name="transport" required style="<?= $inp ?>">
                    <small style="<?= $hint ?>">Nettarif, Energinets tariffer og eventuelle andre tillæg lagt sammen.</small>
                </div>
                <div style="margin-bottom: 15px;">
                    <label>Abonnement pr. kWh (kr):</label>
                    <input type="text" name="abonnement" required style="<?= $inp ?>">
                    <small style="<?= $hint ?>">Dit abonnement/handelstillæg regnet om til kr pr. kWh.</small>
                </div>
                <button type="submit" name="add_rate" class="btn btn-primary" style="width: 100%;">
                    <i class="fas fa-plus"></i> Gem række
                </button>
            </form>
        </div>

        <div style="<?= $box ?>">
            <h4 style="margin-top: 0;">Gemte rækker</h4>
            <?php if (!$rates): ?>
                <p style="font-size: 0.9rem; color: #666;">Ingen endnu. Uden afgifter er prisen kun spotprisen med moms.</p>
            <?php else: ?>
                <table class="admin-table" style="width: 100%;">
                    <thead><tr><th>Fra</th><th>Elafgift</th><th>Transport</th><th>Abon.</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($rates as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['start_dato']) ?></td>
                            <td><?= number_format((float)$r['elafgift'], 4, ',', '.') ?></td>
                            <td><?= number_format((float)$r['transport_samlet'], 4, ',', '.') ?></td>
                            <td><?= number_format((float)$r['abonnement_kwh'], 4, ',', '.') ?></td>
                            <td>
                                <form method="post" style="margin: 0;">
                                    <?= csrf_field() ?>
                                    <button type="submit" name="delete_rate" value="<?= (int)$r['id'] ?>" class="btn btn-reject" style="width: auto; padding: 4px 10px;" onclick="return confirm('Slet rækken?')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Hent priser -->
<div class="content-card" style="margin-bottom: 25px;">
    <h3><i class="fas fa-cloud-download-alt"></i> Hent priser</h3>
    <p style="font-size: 0.9rem; color: #666; margin: 5px 0 0;">
        <?php if ((int)$daysCount > 0): ?>
            Der er <?= (int)$daysCount ?> dage gemt for <?= htmlspecialchars($cfgRegion) ?>, fra <?= htmlspecialchars((string)$firstDay) ?> til <?= htmlspecialchars((string)$lastDay) ?>.
        <?php else: ?>
            Der er ingen priser gemt endnu for <?= htmlspecialchars($cfgRegion) ?>.
        <?php endif; ?>
        Kilden er: <strong><?= htmlspecialchars($engines[$cfgMethod] ?? $cfgMethod) ?></strong>.
    </p>
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 15px;">
        <div style="<?= $box ?>">
            <h4 style="margin-top: 0;">Hent manglende dage</h4>
            <p style="font-size: 0.9rem; color: #666; margin-bottom: 20px;">Henter fra dagen efter den nyeste gemte dag og op til 60 dage frem.</p>
            <form method="post">
                <?= csrf_field() ?>
                <button type="submit" name="sync_missing" class="btn btn-primary" style="width: 100%;">
                    <i class="fas fa-sync"></i> Hent nu
                </button>
            </form>
        </div>
        <div style="<?= $box ?>">
            <h4 style="margin-top: 0;">Hent en bestemt periode</h4>
            <form method="post">
                <?= csrf_field() ?>
                <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                    <input type="date" name="sync_from" value="<?= date('Y-m-d', strtotime('-30 days')) ?>" required style="<?= $inp ?>">
                    <input type="date" name="sync_to" value="<?= date('Y-m-d') ?>" required style="<?= $inp ?>">
                </div>
                <button type="submit" name="sync_range" class="btn btn-primary" style="width: 100%;">
                    <i class="fas fa-calendar-alt"></i> Hent perioden
                </button>
            </form>
            <small style="<?= $hint ?>">Højst 400 dage ad gangen. Dage, der allerede er gemt, regnes om igen.</small>
        </div>
    </div>
</div>

<!-- Seneste dage -->
<div class="content-card" style="margin-bottom: 25px;">
    <h3><i class="fas fa-list"></i> Seneste dage</h3>
    <?php if (!$latest): ?>
        <p style="font-size: 0.9rem; color: #666;">Ingen priser endnu.</p>
    <?php else: ?>
        <table class="admin-table" style="width: 100%;">
            <thead><tr><th>Dato</th><th>Gns.</th><th>Højeste</th><th>Laveste</th><th>Spotpris (gns.)</th></tr></thead>
            <tbody>
            <?php foreach ($latest as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['dato']) ?></td>
                    <td><?= number_format((float)$row['price_gns'], 2, ',', '.') ?></td>
                    <td><?= number_format((float)$row['price_max'], 2, ',', '.') ?></td>
                    <td><?= number_format((float)$row['price_min'], 2, ',', '.') ?></td>
                    <td><?= number_format((float)$row['spot_price_raw'], 3, ',', '.') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <small style="<?= $hint ?>">Alle priser i kr pr. kWh. Gns., højeste og laveste er samlede priser med afgifter og moms.</small>
    <?php endif; ?>
</div>

<!-- Vejledning -->
<div class="content-card">
    <details>
        <summary style="cursor: pointer; font-weight: 700;"><i class="fas fa-book"></i> Vejledning: Google-ark som kilde</summary>
        <div style="margin-top: 15px; font-size: 0.9rem; color: #444; line-height: 1.6;">
            <p>Brug Google-ark, hvis du selv samler priserne et sted. Så skal arket se sådan ud:</p>
            <ol>
                <li>Første række har kolonnenavnene <code>dato</code>, <code>spot_gns</code> og gerne <code>spot_max</code> og <code>spot_min</code> (i vilkårlig rækkefølge).</li>
                <li><code>dato</code> kan være <code>2026-11-05</code> eller <code>05-11-2026</code>.</li>
                <li>Priserne er spotpriser i kr pr. kWh <strong>uden</strong> afgifter og moms. Afgifter og moms lægger modulet på, så alle kilder regnes ens.</li>
                <li>Vælg <em>Filer &rarr; Del &rarr; Publicer på nettet</em>, vælg fanen og formatet <em>CSV</em>, og kopier adressen ind i feltet ovenfor.</li>
            </ol>
            <p>Hvis du ikke bruger et Google-ark, så vælg kilden <em>Energi Data Service (Energinet)</em>. Den kræver ingen nøgle.</p>
        </div>
    </details>
</div>
