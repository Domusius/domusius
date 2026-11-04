<?php
/**
 * inc/login_view.php
 * Dette er kun visningen (HTML). Logikken kører i index.php.
 * Vi genbruger variablerne $site_name, $error og $registrationOpen herfra.
 * Udseendet kommer fra assets/style.css (samme klasser som register.php og forgot_password.php).
 */
require_once __DIR__ . '/favicon.php';
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log ind - <?= htmlspecialchars($site_name) ?></title>
    <?= favicon_html($pdo) ?>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-page">

    <div id="loading-overlay">
        <div class="spinner"></div>
        <div class="loading-text">Logger ind og synkroniserer data...</div>
    </div>

    <div class="login-card">
        <h1><?= htmlspecialchars($site_name) ?></h1>
        <div class="login-subtitle">Log ind</div>

        <?php if (!empty($error)): ?>
            <div class="login-error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="post" id="loginForm">
            <div class="form-group">
                <label for="username">Brugernavn</label>
                <input type="text" name="username" id="username" required autofocus>
            </div>

            <div class="form-group">
                <label for="password">Adgangskode</label>
                <input type="password" name="password" id="password" required>
            </div>

            <button type="submit">Log ind</button>
        </form>

        <div class="login-footer">
            <p><a href="forgot_password.php">Glemt adgangskode?</a></p>
            <?php if ($registrationOpen): ?>
                <p>Har du ikke en konto? <a href="register.php">Opret dig her</a></p>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Vis loader når formen indsendes (kun hvis vi ikke er i debug mode)
        document.getElementById('loginForm').addEventListener('submit', function() {
            // Vi tjekker om overlayet findes før vi viser det
            const overlay = document.getElementById('loading-overlay');
            if(overlay) {
                overlay.style.display = 'flex';
            }
            
            // Deaktiver knappen for at undgå dobbelt-klik
            const btn = this.querySelector('button');
            if(btn) {
                btn.disabled = true;
                btn.style.opacity = '0.5';
                btn.style.cursor = 'not-allowed';
            }
        });
    </script>
</body>
</html>