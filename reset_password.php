<?php
require __DIR__ . '/config/config.php';
require_once __DIR__ . '/inc/favicon.php';

$token = $_GET['token'] ?? '';
$error = '';
$success = false;

// Sitets navn til overskriften
$siteName = 'Domusius';
try {
    $stmt = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'site_name'");
    $siteName = $stmt->fetchColumn() ?: 'Domusius';
} catch (Throwable $e) {
    // Fallback hvis tabellen mangler
}

// I databasen står kun et fingeraftryk (hash) af koden, så vi slår op på hashen.
// Udløbstiden sammenlignes med PHP's tid, ligesom forgot_password.php sætter den.
$user = false;
if (is_string($token) && $token !== '') {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expires > ? LIMIT 1");
    $stmt->execute([hash('sha256', $token), date('Y-m-d H:i:s')]);
    $user = $stmt->fetch();
}

if (!$user) {
    http_response_code(400);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass = $_POST['password'] ?? '';
    if (strlen($pass) < 6) {
        $error = "Koden skal være mindst 6 tegn.";
    } else {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?")
            ->execute([$hash, $user['id']]);
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="referrer" content="no-referrer">
    <title>Vælg ny adgangskode – <?= htmlspecialchars($siteName) ?></title>
    <?= favicon_html($pdo) ?>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-page">

<div class="login-card">
    <h1><?= htmlspecialchars($siteName) ?></h1>
    <div class="login-subtitle">Vælg ny adgangskode</div>

    <?php if (!$user): ?>
        <div class="login-error">
            Ugyldigt eller udløbet link.
        </div>
        <div class="login-footer">
            <a href="forgot_password.php">Bed om et nyt link</a>
        </div>

    <?php elseif ($success): ?>
        <div class="login-success">
            Din adgangskode er ændret.<br><br>
            <a href="index.php" style="font-weight: bold; color: inherit;">Log ind her</a>
        </div>

    <?php else: ?>
        <?php if ($error): ?>
            <div class="login-error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <div class="form-group">
                <label for="password">Ny adgangskode</label>
                <input type="password" name="password" id="password" minlength="6" autocomplete="new-password" required>
            </div>

            <button type="submit">Opdater adgangskode</button>
        </form>

        <div class="login-footer">
            <a href="index.php">← Tilbage til login</a>
        </div>
    <?php endif; ?>
</div>

</body>
</html>