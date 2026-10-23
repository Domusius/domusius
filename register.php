<?php
session_start();

/* -------------------------------------------------
    DB
------------------------------------------------- */
require __DIR__ . '/config/config.php';

/* -------------------------------------------------
    MAIL til admin 
------------------------------------------------- */
require_once __DIR__ . '/system/mail_functions.php';

/* -------------------------------------------------
    Hent site-indstillinger
------------------------------------------------- */
$siteName = 'Domusius';
$registrationOpen = false;
$requireApproval  = true;

try {
    $stmt = $pdo->query("
        SELECT setting_key, setting_value 
        FROM site_settings 
        WHERE setting_key IN ('site_name', 'registration_open', 'require_user_approval')
    ");
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $siteName = $settings['site_name'] ?? 'Domusius';
    $registrationOpen = ($settings['registration_open'] ?? '0') === '1';
    $requireApproval = ($settings['require_user_approval'] ?? '1') === '1';

} catch (Throwable $e) {
    // Fallback hvis tabellen mangler
}

/* -------------------------------------------------
    Tjek adgang
------------------------------------------------- */
if (!$registrationOpen) {
    header('Location: login.php');
    exit;
}

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

/* -------------------------------------------------
    Opret bruger logik
------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $email === '' || $password === '') {
        $error = 'Alle felter skal udfyldes';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Ugyldig emailadresse';
    } else {

        // Tjek om bruger findes
        $check = $pdo->prepare("SELECT id FROM users WHERE username = :u OR email = :e LIMIT 1");
        $check->execute([':u' => $username, ':e' => $email]);

        if ($check->fetch()) {
            $error = 'Brugernavn eller email findes allerede';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $status = $requireApproval ? 'pending' : 'active';

            $insert = $pdo->prepare("
                INSERT INTO users (username, email, password, role, status, created_at) 
                VALUES (:u, :e, :p, 'user', :s, NOW())
            ");

            if ($insert->execute([':u' => $username, ':e' => $email, ':p' => $hash, ':s' => $status])) {

                // NYT: Send besked til admin
                sendAdminNotification($pdo, $username);

                if ($requireApproval) {
                    $success = '✅ Konto oprettet. Afventer admin-godkendelse.';

                } else {
                    $success = '✅ Konto oprettet. Du kan nu logge ind.';
                }
            } else {
                $error = 'Der opstod en databasefejl. Prøv igen senere.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <title>Opret konto – <?= htmlspecialchars($siteName) ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-page">

<div class="login-card">
    <h1><?= htmlspecialchars($siteName) ?></h1>
    <div class="login-subtitle">Opret ny konto</div>

    <?php if ($error): ?>
        <div class="login-error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="login-success">
            <?= htmlspecialchars($success) ?><br><br>
            <a href="login.php" style="font-weight: bold; color: inherit;">Gå til login</a>
        </div>
    <?php else: ?>

        <form method="post" autocomplete="off">
            <div class="form-group">
                <label for="username">Brugernavn</label>
                <input type="text" name="username" id="username" required>
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" name="email" id="email" required>
            </div>

            <div class="form-group">
                <label for="password">Adgangskode</label>
                <input type="password" name="password" id="password" required>
            </div>

            <button type="submit">Opret konto</button>
        </form>

        <div class="login-footer">
            <a href="login.php">← Tilbage til login</a>
        </div>
    <?php endif; ?>
</div>

</body>
</html>