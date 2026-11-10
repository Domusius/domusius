<?php
// system/modules/elpriser/init.php
// Menupunktet bygges af kernen ud fra module.json / databasen.

require_once __DIR__ . '/functions.php';

// Modulets CSS - kun på modulets egen side
add_action('header_scripts', function () {
    if (($_GET['side'] ?? '') === 'elpriser') {
        $css = __DIR__ . '/style.css';
        $v   = file_exists($css) ? filemtime($css) : 1;
        echo '<link rel="stylesheet" href="system/modules/elpriser/style.css?v=' . $v . '">';
    }
});

// Henter manglende dage, når nogen logger ind (kun hvis det er slået til under El-pris Opsætning)
add_action('user_login_sync', function ($loginUser = null) {
    global $pdo;
    if (!($pdo instanceof PDO)) {
        return;
    }
    try {
        if (elpriser_setting($pdo, 'auto_fetch', '0') === '1') {
            elpriser_sync_missing($pdo, 7);
        }
    } catch (Throwable $e) {
        error_log('Elpriser: automatisk hentning fejlede: ' . $e->getMessage());
    }
});
