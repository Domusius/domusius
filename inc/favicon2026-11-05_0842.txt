<?php
/**
 * inc/favicon.php
 * Ikon på browserfanen (favicon). Samme ikon på alle sider.
 *
 * Brug i <head> på en side:
 *   require_once __DIR__ . '/inc/favicon.php';        (sti afhænger af filens placering)
 *   <?= favicon_html($pdo) ?>
 *
 * Det valgte ikon står i site_settings under nøglen 'site_favicon' (et filnavn i assets/favicons/).
 * Er intet valgt, eller findes filen ikke, bruges standardikonet house.svg.
 */

/**
 * De fire standardikoner: filnavn => visningsnavn.
 */
function favicon_defaults(): array
{
    return [
        'house.svg'   => 'Hus',
        'bolt.svg'    => 'Lyn',
        'sun.svg'     => 'Sol',
        'squares.svg' => 'Firkanter',
    ];
}

/**
 * Det valgte ikons filnavn (kun navnet, uden mappe).
 */
function favicon_current(PDO $pdo): string
{
    $dir  = __DIR__ . '/../assets/favicons/';
    $file = 'house.svg';
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'site_favicon'");
        $stmt->execute();
        $val = (string)$stmt->fetchColumn();
        // Kun almindelige filnavne i mappen assets/favicons/ er tilladt
        if (preg_match('/^[A-Za-z0-9_-]+\.(svg|png|ico)$/', $val) && is_file($dir . $val)) {
            $file = $val;
        }
    } catch (Throwable $e) {
        // Ingen indstilling endnu: brug standardikonet
    }
    return $file;
}

/**
 * <link>-tagget til <head>. $prefix bruges af sider i en undermappe, fx '../'.
 */
function favicon_html(PDO $pdo, string $prefix = ''): string
{
    $dir   = __DIR__ . '/../assets/favicons/';
    $file  = favicon_current($pdo);
    $ext   = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $types = ['svg' => 'image/svg+xml', 'png' => 'image/png', 'ico' => 'image/x-icon'];
    // ?v=... gør, at browseren henter ikonet igen, når det skiftes
    $href  = $prefix . 'assets/favicons/' . $file . '?v=' . (int)@filemtime($dir . $file);
    return '<link rel="icon" type="' . $types[$ext] . '" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">';
}
