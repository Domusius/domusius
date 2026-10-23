<?php
ob_start();

/**
 * admin/admin_panel.php
 * Hovedpanel for administration med dynamisk modul-indlæsning og filter for system-moduler.
 */

if (!isset($user) || $user['role'] !== 'admin') {
    exit('Adgang nægtet');
}

// Inkluder log-funktionen så den er tilgængelig globalt
require_once __DIR__ . '/pages/log_admin_action.php';

/* -------------------------------------------------
    NYT: HÅNDTER DEBUG MODE TOGGLE
------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_global_debug'])) {
    $newDebugVal = $_POST['current_debug'] === '1' ? '0' : '1';
    $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) 
                           VALUES ('growatt_debug_mode', :val) 
                           ON DUPLICATE KEY UPDATE setting_value = :val");
    $stmt->execute([':val' => $newDebugVal]);
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

// Hent nuværende debug status
$stmtStatus = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'growatt_debug_mode'");
$globalDebug = $stmtStatus ? ($stmtStatus->fetchColumn() === '1') : false;
/* ------------------------------------------------- */

// Sæt standard-siden til 'brugere'
$page = $_GET['page'] ?? 'brugere';

// Beskyttede moduler der ikke skal vises under "Ekstra Moduler"
$protected_modules = ['core', 'system', 'users'];

/* -------------------------------------------------
    Hent aktive moduler fra DB (til ekstra menuer)
    Vi bruger JOIN for at få 'admin_menu_title' fra module_settings
------------------------------------------------- */
$stmtMod = $pdo->query("
    SELECT m.module_key, s.admin_menu_title 
    FROM modules m 
    LEFT JOIN module_settings s ON m.module_key = s.module_key 
    WHERE m.is_active = 1 
    ORDER BY m.module_key ASC
");
$dynamicModules = $stmtMod->fetchAll(PDO::FETCH_ASSOC);

// Dine faste system-sider
$fixed_pages = [
    'brugere'     => ['title' => 'Brugere', 'icon' => 'fa-user-friends'],
    'system'      => ['title' => 'System', 'icon' => 'fa-cog'],
    'modules'     => ['title' => 'Moduler', 'icon' => 'fa-cubes'],
    'mail_system' => ['title' => 'Mail System', 'icon' => 'fa-envelope'],
    'log'         => ['title' => 'Log', 'icon' => 'fa-list']
];

// Tjek om den valgte side er tilladt (Enten fast side eller et aktivt modul)
$allowed_pages = array_merge(array_keys($fixed_pages), array_column($dynamicModules, 'module_key'));
if (!in_array($page, $allowed_pages)) {
    $page = 'brugere';
}

/* -------------------------------------------------
    AUTOMATISK LOGNING AF SIDEVISNING
------------------------------------------------- */
if (function_exists('log_admin_action')) {
    $pageTitle = $fixed_pages[$page]['title'] ?? $page;
    log_admin_action($pdo, 'VIEW_ADMIN_TAB', "Besøgte siden: " . $pageTitle);
}
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<style>
    .admin-layout { display: flex; gap: 25px; align-items: flex-start; padding-top: 10px; }
    .admin-sidebar { width: 260px; background: #343a40; border-radius: 15px; padding: 20px; color: #fff; flex-shrink: 0; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
    .sidebar-label { font-size: 0.75rem; text-transform: uppercase; color: #adb5bd; letter-spacing: 1.5px; margin-bottom: 15px; font-weight: bold; border-bottom: 1px solid #495057; padding-bottom: 5px; }
    .admin-nav { display: flex; flex-direction: column; gap: 8px; }
    .nav-link { 
        display: flex; align-items: center; gap: 12px; padding: 12px 18px; 
        color: #e9ecef; text-decoration: none; border-radius: 10px; transition: all 0.3s ease; font-size: 0.95rem;
    }
    .nav-link:hover { background: rgba(255,255,255,0.1); color: #fff; transform: translateX(5px); }
    .nav-link.active { background: #007bff; color: #fff; font-weight: 600; box-shadow: 0 4px 12px rgba(0,123,255,0.3); }
    .nav-link i { width: 22px; text-align: center; font-size: 1.1rem; }
    .admin-content { flex-grow: 1; min-width: 0; }
</style>

<div class="admin-layout">
    <aside class="admin-sidebar">
        
        <div class="sidebar-label">System Status</div>
        <form method="post" style="margin-bottom: 20px;">
            <input type="hidden" name="current_debug" value="<?= $globalDebug ? '1' : '0' ?>">
            <button type="submit" name="toggle_global_debug" style="
                width: 100%; padding: 10px; border-radius: 8px; border: none; cursor: pointer;
                background: <?= $globalDebug ? '#28a745' : '#495057' ?>; color: #fff;
                display: flex; align-items: center; gap: 10px; font-size: 0.85rem; transition: 0.3s;
            ">
                <i class="fas <?= $globalDebug ? 'fa-bug' : 'fa-bug-slash' ?>"></i>
                Debug: <?= $globalDebug ? 'AKTIV' : 'FRA' ?>
            </button>
        </form>

        <div class="sidebar-label">Administration</div>
        <nav class="admin-nav">
            
            <?php foreach ($fixed_pages as $key => $data): ?>
                <a href="?side=admin&page=<?= $key ?>" class="nav-link <?= $page === $key ? 'active' : '' ?>">
                    <i class="fas <?= $data['icon'] ?>"></i> <?= $data['title'] ?>
                </a>
            <?php endforeach; ?>

            <?php if (!empty($dynamicModules)): ?>
                <?php 
                // Tjek om der rent faktisk er moduler der skal vises (som ikke er faste/beskyttede)
                $hasExtra = false;
                foreach($dynamicModules as $m) {
                    if(!in_array($m['module_key'], $protected_modules) && !isset($fixed_pages[$m['module_key']])) {
                        $hasExtra = true; break;
                    }
                }
                
                if($hasExtra): ?>
                    <div class="sidebar-label" style="margin-top: 25px;">Ekstra Moduler</div>
                    <?php foreach ($dynamicModules as $mod): ?>
                        <?php if (!in_array($mod['module_key'], $protected_modules) && !isset($fixed_pages[$mod['module_key']])): ?>
                            <a href="?side=admin&page=<?= htmlspecialchars($mod['module_key']) ?>" 
                               class="nav-link <?= $page === $mod['module_key'] ? 'active' : '' ?>">
                                <i class="fas fa-puzzle-piece"></i> <?= htmlspecialchars($mod['admin_menu_title'] ?? $mod['module_key']) ?>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endif; ?>

        </nav>
    </aside>

    <section class="admin-content">
        <div class="content-card" style="background: #fff; padding: 30px; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); border: 1px solid #edf2f7;">
            <?php
            // 1. Søg efter filen i admin/pages/ (faste sider)
            $systemPath = __DIR__ . '/pages/' . $page . '.php';
            
            // 2. Søg efter filen i modulet selv (i admin/settings.php)
            $moduleAdminPath = dirname(__DIR__) . '/system/modules/' . $page . '/admin/settings.php';

            if (file_exists($systemPath)) {
                include $systemPath;
            } elseif (file_exists($moduleAdminPath)) {
                include $moduleAdminPath;
            } else {
                echo "<div style='text-align: center; padding: 40px;'>
                        <i class='fas fa-search' style='font-size: 3rem; color: #ccc; margin-bottom: 20px;'></i>
                        <h2>Siden blev ikke fundet</h2>
                        <p>Kunne ikke finde filen: <strong>$page.php</strong> eller modul-indstillinger.</p>
                      </div>";
            }
            ?>
        </div>
    </section>
</div>