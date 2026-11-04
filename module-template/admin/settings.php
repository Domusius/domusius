<?php
// system/modules/eksempel/admin/settings.php
// Modulets opsætning i admin. Kun administratorer.
if (!isset($user) || $user['role'] !== 'admin') {
    exit('Adgang nægtet');
}

// Sikkerhedstoken (CSRF). Kernen kan allerede have indlæst det.
if (!function_exists('csrf_token')) {
    require_once __DIR__ . '/../../../../inc/csrf.php';
}

$current_module_key = 'eksempel';   // skal være det samme som "key" i module.json
$message = '';
$error = '';

// 1. Læs tilgængelige widgets fra widgets/widgets.json
$widgets_folder = __DIR__ . '/../widgets/';
$available_widgets = [];
if (file_exists($widgets_folder . 'widgets.json')) {
    $data = json_decode(file_get_contents($widgets_folder . 'widgets.json'), true);
    $available_widgets = $data['widgets'] ?? [];
}

// Alle formularer skal sende sikkerhedstokenet med: csrf_field() i formularen.
// Uden et gyldigt token gemmes der ingenting.
$isPost  = $_SERVER['REQUEST_METHOD'] === 'POST';
$tokenOk = !$isPost || csrf_check($_POST['csrf'] ?? '');
if ($isPost && !$tokenOk) {
    $error = 'Handlingen blev afvist, fordi sikkerhedstokenet manglede eller var forkert. Prøv igen fra denne side.';
}

// 2. Gem dashboard-layout (vis/skjul, størrelse, rækkefølge)
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

// 3. Gem modulets egen indstilling
if ($isPost && $tokenOk && isset($_POST['save_text'])) {
    $value = trim($_POST['eksempel_tekst'] ?? '');
    $stmt = $pdo->prepare(
        "INSERT INTO site_settings (setting_key, setting_value) VALUES ('eksempel_tekst', :v)
         ON DUPLICATE KEY UPDATE setting_value = :v"
    );
    $stmt->execute([':v' => $value]);
    $message = 'Teksten er gemt!';
}

// 4. Hent nuværende værdier
$modStmt = $pdo->prepare("SELECT show_on_dashboard, dashboard_widget_type, dashboard_size, dashboard_order FROM modules WHERE module_key = ?");
$modStmt->execute([$current_module_key]);
$moduleData = $modStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$showOnDash    = (int)($moduleData['show_on_dashboard'] ?? 0);
$currentWidget = $moduleData['dashboard_widget_type'] ?? '';
$currentSize   = $moduleData['dashboard_size'] ?? 'medium';
$currentOrder  = (int)($moduleData['dashboard_order'] ?? 0);

$stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'eksempel_tekst'");
$stmt->execute();
$tekst = (string)$stmt->fetchColumn();
?>
<h3>Eksempel Opsætning</h3>

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

            <button type="submit" name="save_layout" style="background: #1e293b; color: white; border: none; padding: 8px 15px; border-radius: 6px; font-weight: 700; cursor: pointer; font-size: 12px;">Gem layout</button>
        </div>
    </form>
</div>

<!-- Modulets egen indstilling -->
<form method="post">
    <?= csrf_field() ?>
    <label>Tekst</label>
    <input type="text" name="eksempel_tekst" value="<?= htmlspecialchars($tekst) ?>">
    <button type="submit" name="save_text">Gem</button>
</form>