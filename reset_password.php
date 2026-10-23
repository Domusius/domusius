<?php
require 'config/config.php';
$token = $_GET['token'] ?? '';
$error = '';
$success = false;

$stmt = $pdo->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW() LIMIT 1");
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user) { die("Ugyldigt eller udløbet link."); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
    <title>Vælg ny adgangskode</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-page">
    <div class="login-card">
        <h2>Vælg ny adgangskode</h2>
        <?php if ($success): ?>
            <p>✅ Din kode er ændret! <a href="login.php">Log ind her</a></p>
        <?php else: ?>
            <?php if ($error): ?><p style="color:red;"><?= $error ?></p><?php endif; ?>
            <form method="post">
                <input type="password" name="password" placeholder="Ny adgangskode" required style="width:100%; padding:10px; margin:10px 0;">
                <button type="submit" class="btn-primary" style="width:100%;">Opdater adgangskode</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>