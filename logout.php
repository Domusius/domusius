<?php
/**
 * logout.php - Rettet til Front Controller systemet
 */
session_start();

/* 1. Fjern alle session-variabler */
$_SESSION = [];

/* 2. Slet session-cookien (ekstra sikkerhed) */
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

/* 3. Afslut sessionen */
session_destroy();

/* 4. Send brugeren tilbage til forsiden (som nu viser login-skærmen) */
header('Location: index.php');
exit;