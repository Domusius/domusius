<?php
require __DIR__ . '/config/config.php';
require __DIR__ . '/system/mail_functions.php';
require_once __DIR__ . '/inc/favicon.php';

$message = '';

// Sitets navn til overskriften
$siteName = 'Domusius';
try {
    $stmt = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'site_name'");
    $siteName = $stmt->fetchColumn() ?: 'Domusius';
} catch (Throwable $e) {
    // Fallback hvis tabellen mangler
}

/**
 * Adressen, som gendannelses-linket skal pege på (uden afsluttende /).
 * 1) En fast adresse (site_settings: site_url) er sikrest, fordi den ikke kan påvirkes udefra.
 * 2) Ellers bruges adressen fra anmodningen, men kun hvis den ser ud som et almindeligt værtsnavn.
 * Giver null, hvis der ikke er en brugbar adresse.
 */
function reset_base_url(PDO $pdo): ?string
{
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'site_url'");
        $stmt->execute();
        $url = rtrim(trim((string)$stmt->fetchColumn()), '/');
        if ($url !== '' && preg_match('#^https?://[A-Za-z0-9.-]+(:[0-9]{1,5})?(/[A-Za-z0-9._~/-]*)?$#', $url)) {
            return $url;
        }
    } catch (Throwable $e) {
        // Ingen fast adresse indstillet endnu
    }

    $host = $_SERVER['HTTP_HOST'] ?? '';
    if (!preg_match('/^[A-Za-z0-9.-]+(:[0-9]{1,5})?$/', $host)) {
        return null;
    }
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $dir   = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    return ($https ? 'https://' : 'http://') . $host . $dir;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        $base = reset_base_url($pdo);

        if ($base !== null) {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // I databasen gemmes kun et fingeraftryk (hash) af koden. Selve koden står kun i mailen.
            $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?")
                ->execute([hash('sha256', $token), $expires, $user['id']]);

            $resetLink = $base . "/reset_password.php?token=" . $token;

            $subject = "Gendan din adgangskode";
            $body = "Hej " . $user['username'] . ",\n\nKlik på linket herunder for at vælge en ny adgangskode. Linket virker i 1 time.\n\n" . $resetLink;

            if (!sendSystemMail($pdo, $email, $subject, $body)) {
                // Fejlen vises ikke på siden, så ingen kan se, om emailen findes
                error_log('Domusius: gendannelsesmail kunne ikke sendes.');
            }
        } else {
            error_log('Domusius: gendannelsesmail ikke sendt, fordi adressen på siden ikke er gyldig.');
        }
    }

    // Samme svar, uanset om emailen findes eller ej
    $message = "Hvis emailen findes i vores system, er der sendt et link.";
}
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Glemt adgangskode – <?= htmlspecialchars($siteName) ?></title>
    <?= favicon_html($pdo) ?>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-page">

<div class="login-card">
    <h1><?= htmlspecialchars($siteName) ?></h1>
    <div class="login-subtitle">Glemt adgangskode</div>

    <?php if ($message): ?>
        <div class="login-success">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <form method="post">
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" name="email" id="email" required>
        </div>

        <button type="submit">Send gendannelses-link</button>
    </form>

    <div class="login-footer">
        <a href="index.php">← Tilbage til login</a>
    </div>
</div>

</body>
</html>