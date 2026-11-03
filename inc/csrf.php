<?php
/**
 * inc/csrf.php
 * Sikkerhedstoken (CSRF-beskyttelse), der deles af alle sider og moduler.
 *
 * Brug:
 *   require_once __DIR__ . '/../../inc/csrf.php';   (sti afhænger af filens placering)
 *
 *   Link:      href="?side=admin&page=x&action=slet&csrf=<?= csrf_token() ?>"
 *   Formular:  <form method="post"> <?= csrf_field() ?> ... </form>
 *
 *   Kontrol (GET-link):      csrf_check($_GET['csrf'] ?? '')
 *   Kontrol (POST-formular): csrf_check($_POST['csrf'] ?? '')
 */

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

/**
 * Returnerer sessionens token. Den oprettes første gang, den bruges.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Skjult formularfelt med tokenet.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Sand, hvis det indsendte token er det samme som sessionens.
 */
function csrf_check($submitted): bool
{
    if (!is_string($submitted) || $submitted === '') {
        return false;
    }
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $submitted);
}
