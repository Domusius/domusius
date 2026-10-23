<?php
/**
 * inc/login_view.php
 * Dette er kun visningen (HTML). Logikken kører i index.php.
 * Vi genbruger variablerne $site_name, $error og $registrationOpen herfra.
 */
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log ind - <?= htmlspecialchars($site_name) ?></title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        /* Loading Overlay Styling - Bevaret fra din originale fil */
        #loading-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(255, 255, 255, 0.85);
            z-index: 9999;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            font-family: sans-serif;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #edf2f7;
            border-top: 5px solid #3182ce;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .loading-text {
            margin-top: 15px;
            font-weight: bold;
            color: #2d3748;
        }
    </style>
</head>
<body class="login-page">

    <div id="loading-overlay">
        <div class="spinner"></div>
        <div class="loading-text">Logger ind og synkroniserer data...</div>
    </div>

    <div class="login-card">
        <div class="login-header">
            <h2>Log ind</h2>
            <p><?= htmlspecialchars($site_name) ?></p>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="alert error" style="color:red; margin-bottom:10px; border:1px solid red; padding:10px; background:#fff5f5;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="post" id="loginForm">
            <div class="form-group">
                <label>Brugernavn</label>
                <input type="text" name="username" placeholder="Indtast brugernavn" required autofocus style="width:100%; padding:8px; margin-bottom:10px; border:1px solid #ccc; border-radius:4px;">
            </div>
            
            <div class="form-group">
                <label>Adgangskode</label>
                <input type="password" name="password" placeholder="Indtast adgangskode" required style="width:100%; padding:8px; margin-bottom:10px; border:1px solid #ccc; border-radius:4px;">
            </div>

            <button type="submit" class="btn-primary" style="width: 100%; padding:10px; background:#007bff; color:white; border:none; border-radius:4px; cursor:pointer;">Log ind</button>
        </form>

        <div class="login-footer" style="margin-top:20px; text-align:center;">
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