<?php
/**
 * admin/pages/modules.php
 */
$base = __DIR__ . '/../../system/modules';
require_once __DIR__ . '/../../system/modules/module_manager.php';

$protected_modules = ['core', 'system', 'users'];

if (isset($_GET['action']) && isset($_GET['module'])) {
    $action = $_GET['action'];
    $key = $_GET['module'];
    $jsonPath = "$base/$key/module.json";
    $sqlPath = "$base/$key/install.sql";

    if ($action === 'install' || $action === 'activate' || $action === 'upgrade') {
        $check = checkModuleRequirements($pdo, $key);
        if (!$check['status']) {
            header("Location: ?side=admin&page=modules&error=" . urlencode($check['message']));
            exit;
        }
    }

    if (in_array($key, $protected_modules) && ($action === 'deactivate' || $action === 'uninstall')) {
        header("Location: ?side=admin&page=modules&error=protected");
        exit;
    }

    if (($action === 'install' || $action === 'upgrade') && file_exists($jsonPath)) {
        $m = json_decode(file_get_contents($jsonPath), true);
        if (file_exists($sqlPath)) {
            $sql = file_get_contents($sqlPath);
            if (!empty(trim($sql))) { $pdo->exec($sql); }
        }
        $name = $m['name'] ?? $key;
        $ver = str_replace('v', '', $m['version'] ?? '0.00');
        
        $stmt1 = $pdo->prepare("INSERT INTO modules (module_key, name, description, version, is_installed, is_active, installed_at) VALUES (:key, :name, :desc, :ver, 1, 0, NOW()) ON DUPLICATE KEY UPDATE name = :name, version = :ver, is_installed = 1");
        $stmt1->execute([':key' => $key, ':name' => $name, ':desc' => $m['description'] ?? '', ':ver' => $ver]);

        $stmt2 = $pdo->prepare("INSERT INTO module_settings (module_key, menu_title, admin_menu_title, version) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE menu_title=?, admin_menu_title=?, version=?");
        $stmt2->execute([$key, $m['menu_title'] ?? $name, $m['admin_menu_title'] ?? $name, $ver, $m['menu_title'] ?? $name, $m['admin_menu_title'] ?? $name, $ver]);

        $upgradeFile = "$base/$key/upgrades/DB_$ver.php";
        if (file_exists($upgradeFile)) { include $upgradeFile; }
    }

    if ($action === 'activate') { $pdo->prepare("UPDATE modules SET is_active = 1 WHERE module_key = ?")->execute([$key]); }
    if ($action === 'deactivate') { $pdo->prepare("UPDATE modules SET is_active = 0 WHERE module_key = ?")->execute([$key]); }

    if ($action === 'uninstall') {
        if (isset($_GET['mode']) && $_GET['mode'] === 'full') {
            $uninstallFile = "$base/$key/uninstall.php";
            if (file_exists($uninstallFile)) { include $uninstallFile; }
        }
        $pdo->prepare("DELETE FROM modules WHERE module_key = ?")->execute([$key]);
        $pdo->prepare("DELETE FROM module_settings WHERE module_key = ?")->execute([$key]);
    }

    header("Location: ?side=admin&page=modules&success=1");
    exit;
}

$fsModules = [];
foreach (glob($base . '/*/module.json') as $file) {
    $json = json_decode(file_get_contents($file), true);
    if ($json && isset($json['key'])) { $fsModules[$json['key']] = $json; }
}

$stmt = $pdo->query("SELECT m.*, s.version as db_version FROM modules m LEFT JOIN module_settings s ON m.module_key = s.module_key");
$dbModules = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $m) { $dbModules[$m['module_key']] = $m; }
?>

<div style="background: #fff; padding: 30px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.02);">
    <h2 style="margin-bottom: 25px;">🧩 Modul Styring</h2>

    <?php if (isset($_GET['error'])): ?>
        <?php $err = $_GET['error'] === 'protected'
            ? 'Systemmoduler (Kerne-system, System og Brugerstyring) kan ikke deaktiveres eller slettes, fordi resten af systemet bruger dem.'
            : $_GET['error']; ?>
        <div style="background: #f8d7da; color: #721c24; padding: 12px 15px; border-radius: 8px; margin-bottom: 20px;">
            ⚠️ <?= htmlspecialchars($err) ?>
        </div>
    <?php elseif (isset($_GET['success'])): ?>
        <div style="background: #d4edda; color: #155724; padding: 12px 15px; border-radius: 8px; margin-bottom: 20px;">
            ✅ Handling udført.
        </div>
    <?php endif; ?>

    <table style="width: 100%; border-collapse: separate; border-spacing: 0 10px;">
        <thead>
            <tr style="text-align: left; color: #718096; text-transform: uppercase; font-size: 12px;">
                <th style="padding: 15px; width: 60px; text-align: center;">Status</th>
                <th style="padding: 15px;">Navn</th>
                <th style="padding: 15px; width: 100px;">Version</th>
                <th style="padding: 15px; width: 260px;">Handlinger</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($fsModules as $key => $m): 
                $db = $dbModules[$key] ?? null;
                $isInstalled = ($db && $db['is_installed'] == 1);
                $isActive = ($db && $db['is_active'] == 1);
                $isProtected = in_array($key, $protected_modules, true);
            ?>
            <tr style="background: #fcfcfc;">
                <td style="padding: 20px; text-align: center;">
                    <span style="display:inline-block; width:12px; height:12px; border-radius:50%; background:<?= $isActive ? '#2ecc71' : ($isInstalled ? '#f6993f' : '#cbd5e0') ?>;"></span>
                </td>
                <td style="padding: 20px;"><strong><?= htmlspecialchars($m['name']) ?></strong></td>
                <td style="padding: 20px;">v<?= $db['version'] ?? '0.00' ?></td>
                <td style="padding: 20px;">
                    <div style="display: flex; gap: 10px; align-items: center; min-width: 240px;">
                        <?php if (!$isInstalled): ?>
                            <a class="btn" style="min-width: 100px; text-align: center;" href="?side=admin&page=modules&action=install&module=<?= $key ?>">Installér</a>
                        <?php elseif ($isProtected): ?>
                            <span class="btn" style="min-width: 100px; text-align: center; background: #cbd5e0; color: #718096; cursor: not-allowed; pointer-events: none;">Deaktivér</span>
                            <span style="color: #718096; font-size: 0.85em;">Systemmodul – kan ikke deaktiveres</span>
                        <?php else: ?>
                            <a class="btn" style="min-width: 100px; text-align: center;" href="?side=admin&page=modules&action=<?= $isActive ? 'deactivate' : 'activate' ?>&module=<?= $key ?>">
                                <?= $isActive ? 'Deaktivér' : 'Aktivér' ?>
                            </a>
                            
                            <div style="width: 80px;">
                                <?php if (!$isActive): ?>
                                    <a class="btn" style="background:#e53e3e; color:white; cursor:pointer; width: 100%; text-align: center; border-radius: 5px; padding: 5px 10px;" 
                                       onclick="if(confirm('Vil du slette modulet?\n\nTryk OK for FULD SLETNING (inkl. databasetabeller).\nTryk Annuller for KUN at fjerne fra modul-listen.')) { window.location.href='?side=admin&page=modules&action=uninstall&module=<?= $key ?>&mode=full'; } else { window.location.href='?side=admin&page=modules&action=uninstall&module=<?= $key ?>&mode=soft'; }">
                                       Slet
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
