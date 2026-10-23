<?php
// index.php - Version 0.04 (Front Controller)
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
ob_start();

/* --- 1. System Check --- */
if (!file_exists(__DIR__ . '/install/install.lock')) { 
    header('Location: install/install.php'); 
    exit; 
}

/* --- 2. Indlæs Core & DB --- */
require_once __DIR__ . '/inc/hooks.php';
require __DIR__ . '/config/config.php';
require __DIR__ . '/config/init.php';
require_once __DIR__ . '/inc/module_loader.php'; 

$error = '';

/* --- 3. Login Logik (Integreret) --- */
// Vi tjekker om der er postet data til denne side
if (!isset($_SESSION['user_id']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT id, username, password, role, status FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $loginUser = $stmt->fetch();

    if ($loginUser && password_verify($password, $loginUser['password'])) {
        if ($loginUser['status'] !== 'active') {
            $error = 'Din konto er endnu ikke godkendt af en administrator.';
        } else {
            $_SESSION['user_id'] = $loginUser['id'];
            $_SESSION['role'] = $loginUser['role'];

            // Kør synkroniserings-hooks (f.eks. Growatt)
            foreach (glob(__DIR__ . '/hooks/*.php') as $hookFile) {
                require_once $hookFile;
            }
            do_action('user_login_sync', $loginUser);
            
            // Vi behøver ingen header() her - koden kører bare videre til dashboardet herunder!
        }
    } else {
        $error = 'Forkert brugernavn eller adgangskode.';
    }
}

/* --- 4. Visning: Login eller Dashboard? --- */
if (!isset($_SESSION['user_id'])) {
    // Brugeren er ikke logget ind - Hent indstillinger og vis login-filen
    $stmtSettings = $pdo->query("SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ('site_name', 'registration_open')");
    $settings = $stmtSettings->fetchAll(PDO::FETCH_KEY_PAIR);
    $site_name = $settings['site_name'] ?? 'Domusius';
    $registrationOpen = ($settings['registration_open'] ?? '0') === '1';

    // Vi inkluderer visningen her
    include __DIR__ . '/inc/login_view.php';
    exit; // Stop her, så dashboardet ikke tegnes nedenunder
}

/* --- 5. Dashboard Data (Kun hvis logget ind) --- */
$stmt = $pdo->prepare("SELECT id, username, email, role FROM users WHERE id = :id");
$stmt->execute([':id' => $_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$site_name = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'site_name'")->fetchColumn() ?: 'Domusius';
do_action('page_load');

/* --- 6. Menu & Routing (Dit originale design) --- */
$stmtMenu = $pdo->query("SELECT m.module_key, s.menu_title FROM modules m JOIN module_settings s ON m.module_key = s.module_key WHERE m.is_active = 1 AND s.menu_title IS NOT NULL ORDER BY s.display_order ASC");
$mainMenus = $stmtMenu->fetchAll(PDO::FETCH_ASSOC);

$side = $_GET['side'] ?? 'dashboard';
$allowed_sides = ['dashboard', 'profil', 'admin'];
foreach ($mainMenus as $m) { $allowed_sides[] = $m['module_key']; }
if (!in_array($side, $allowed_sides)) { $side = 'dashboard'; }
if ($side === 'admin' && $user['role'] !== 'admin') { $side = 'dashboard'; }
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($site_name) ?></title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <?php if ($side === 'admin'): ?>
        <link rel="stylesheet" href="assets/admin.css">
    <?php endif; ?>
    <?php do_action('header_scripts'); ?>
</head>
<body>

<header class="site-header">
    <div class="container">
        <div class="header-top">
            <div class="logo"><?= htmlspecialchars($site_name) ?></div>
            <div class="user-info">
                <i class="fas fa-user"></i> 
                <a href="?side=profil" class="user-profile-link"><?= htmlspecialchars($user['username']) ?></a>
                <span class="divider">|</span>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Log ud</a>
            </div>
        </div>

        <nav class="nav-bar">
            <a class="nav-item <?= $side==='dashboard'?'active':'' ?>" href="?side=dashboard">Dashboard</a>
            <?php foreach ($mainMenus as $menu): ?>
                <a class="nav-item <?= $side===$menu['module_key']?'active':'' ?>" href="?side=<?= $menu['module_key'] ?>">
                    <?= htmlspecialchars($menu['menu_title']) ?>
                </a>
            <?php endforeach; ?>
            <?php if ($user['role'] === 'admin'): ?>
                <a class="nav-item <?= $side==='admin'?'active':'' ?>" href="?side=admin" style="margin-left: auto;">
                    <i class="fas fa-user-shield"></i> Admin
                </a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<div class="site-wrapper">
    <main><br>
        <div class="content-card">
            <?php
            if ($side === 'admin') {
                include __DIR__ . '/admin/admin_panel.php';
            } elseif ($side === 'dashboard') {
                do_action('dashboard_top');
                if (file_exists(__DIR__ . '/inc/dashboard.php')) {
                    include __DIR__ . '/inc/dashboard.php';
                } else {
                    echo "<h2>Dashboard</h2><p>Velkommen.</p>";
                }
            } elseif ($side === 'profil') {
                include __DIR__ . '/inc/profil.php';
            } else {
                $moduleDir = __DIR__ . '/system/modules/' . $side;
                $file = file_exists($moduleDir . '/' . $side . '.php') ? $moduleDir . '/' . $side . '.php' : $moduleDir . '/index.php';
                if (file_exists($file)) {
                    include $file;
                } else {
                    echo "<div style='text-align:center; padding:50px;'><h2>Modul ikke fundet</h2></div>";
                }
            }
            ?>
        </div>
    </main>
</div>

<footer class="site-footer">
    <div class="container">
        <span>&copy; <?= date('Y') ?> <?= htmlspecialchars($site_name) ?></span>
        <span class="footer-divider">|</span>
        <span class="developer-credit">Powered by <a href="https://github.com/Domusius/domusius" target="_blank" rel="noopener">Domusius</a> &middot; Udviklet af Espersen-Glasius</span>
        <?php do_action('footer_info'); ?>
    </div>
</footer>

<?php do_action('footer_scripts'); ?>
</body>
</html>