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
 *
 * Egne ikoner gemmes som custom-ÅÅÅÅMMDD-TTMMSS.png (eller .ico), så der kan være flere at skifte mellem.
 */

/** Hvor mange egne ikoner der højst kan gemmes. */
const FAVICON_MAX_CUSTOM = 12;

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

/**
 * Er filnavnet et eget (uploadet) ikon? Både det nye navn med tidsstempel og det ældre custom.png/.ico.
 */
function favicon_is_custom(string $file): bool
{
    return (bool)preg_match('/^custom(-\d{8}-\d{6}(-\d+)?)?\.(png|ico)$/', $file);
}

/**
 * Tidsstemplet i et eget ikons navn (ÅÅÅÅMMDD-TTMMSS), eller nuller hvis navnet ikke har et.
 */
function favicon_stamp(string $file): string
{
    return preg_match('/^custom-(\d{8}-\d{6})/', $file, $m) ? $m[1] : '00000000-000000';
}

/**
 * Alle egne ikoner, nyeste først.
 */
function favicon_custom_files(): array
{
    $dir   = __DIR__ . '/../assets/favicons/';
    $files = [];
    foreach ((array)@scandir($dir) as $f) {
        if (is_string($f) && favicon_is_custom($f) && is_file($dir . $f)) {
            $files[] = $f;
        }
    }
    usort($files, function ($a, $b) {
        return strcmp(favicon_stamp($b), favicon_stamp($a)) ?: strcmp($b, $a);
    });
    return $files;
}

/**
 * Visningsnavn for et eget ikon, fx "Eget ikon 04-11-2026 21:53".
 */
function favicon_custom_label(string $file): string
{
    if (preg_match('/^custom-(\d{4})(\d{2})(\d{2})-(\d{2})(\d{2})(\d{2})/', $file, $m)) {
        return 'Eget ikon ' . $m[3] . '-' . $m[2] . '-' . $m[1] . ' ' . $m[4] . ':' . $m[5];
    }
    return 'Eget ikon';
}

/**
 * Et nyt, ledigt filnavn med tidsstempel til et uploadet ikon.
 */
function favicon_new_custom_name(string $ext): string
{
    $dir  = __DIR__ . '/../assets/favicons/';
    $base = 'custom-' . date('Ymd-His');
    $name = $base . '.' . $ext;
    $n    = 2;
    while (is_file($dir . $name)) {
        $name = $base . '-' . $n . '.' . $ext;
        $n++;
    }
    return $name;
}

/**
 * Tjekker en uploadet fil ud fra dens indhold (ikke filnavnet).
 * Giver [filendelse, fejlbesked]: enten 'png' / 'ico' uden fejl, eller null med en fejlbesked.
 * Kun PNG (16-512 pixel) og ICO er tilladt. SVG tillades ikke, fordi det kan indeholde kode.
 */
function favicon_validate_upload(string $tmpPath, int $size): array
{
    if ($size <= 0) {
        return [null, 'Filen er tom.'];
    }
    if ($size > 200 * 1024) {
        return [null, 'Filen er for stor (højst 200 KB).'];
    }

    $head = (string)file_get_contents($tmpPath, false, null, 0, 6);
    $info = @getimagesize($tmpPath);

    if ($info !== false && $info[2] === IMAGETYPE_PNG
        && $info[0] >= 16 && $info[1] >= 16 && $info[0] <= 512 && $info[1] <= 512) {
        return ['png', null];
    }

    // ICO: starter med 00 00 01 00 og et antal billeder (1-20)
    if (strlen($head) === 6 && substr($head, 0, 4) === "\x00\x00\x01\x00") {
        $count = ord($head[4]) + 256 * ord($head[5]);
        if ($count >= 1 && $count <= 20 && $size >= 22) {
            return ['ico', null];
        }
    }

    return [null, 'Kun PNG-filer (16 til 512 pixel) eller ICO-filer kan bruges.'];
}