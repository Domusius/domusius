<?php
require 'config/config.php';
require 'system/mail_functions.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?")
            ->execute([$token, $expires, $user['id']]);

        $resetLink = "http://" . $_SERVER['HTTP_HOST'] . "/reset_password.php?token=" . $token;
        
        $subject = "Gendan din adgangskode";
        $body = "Hej " . $user['username'] . ",\n\nKlik på linket herunder for at vælge en ny adgangskode. Linket virker i 1 time.\n\n" . $resetLink;
        
        if (sendSystemMail($pdo, $email, $subject, $body)) {
            $message = "✅ En mail er sendt med instruktioner.";
        } else {
            $message = "❌ Der skete en fejl ved afsendelse af mailen.";
        }
    } else {
        $message = "✅ Hvis emailen findes i vores system, er der sendt et link.";
    }
}
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <title>Glemt adgangskode</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-page">
    <div class="login-card">
        <h2>Glemt adgangskode</h2>
        <?php if ($message): ?><p><?= $message ?></p><?php endif; ?>
        <form method="post">
            <input type="email" name="email" placeholder="Din email" required style="width:100%; padding:10px; margin:10px 0;">
            <button type="submit" class="btn-primary" style="width:100%;">Send gendannelses-link</button>
        </form>
        <p><a href="login.php">Tilbage til login</a></p>
    </div>
</body>
</html>