<?php
// system/modules/eksempel/init.php
// Indlæses automatisk af inc/module_loader.php, når modulet er aktivt.
// Menupunktet oprettes af kernen ud fra module.json (menu_title) - det skal IKKE laves her.

// Modulets egen CSS - kun på modulets egen side
add_action('header_scripts', function () {
    if (($_GET['side'] ?? '') === 'eksempel') {
        $css = __DIR__ . '/eksempel.css';
        $v   = file_exists($css) ? filemtime($css) : 1;
        echo '<link rel="stylesheet" href="system/modules/eksempel/eksempel.css?v=' . $v . '">';
    }
});
