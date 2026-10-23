<?php
// admin/pages/mail_system.php

if (!isset($user) || $user['role'] !== 'admin') {
    http_response_code(403);
    exit('Adgang nægtet');
}

/* -------------------------------------------------
   Gem Mail-indstillinger (Inkl. SMTP)
------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_mail_settings'])) {
    $settings_to_save = [
        'admin_notification_email' => $_POST['admin_notification_email'] ?? '',
        'admin_mail_mode'          => $_POST['admin_mail_mode'] ?? 'single',
        'mail_sender_name'         => $_POST['mail_sender_name'] ?? 'System Notifikation',
        'mail_sender_email'        => $_POST['mail_sender_email'] ?? '',
        // SMTP Indstillinger
        'smtp_host'                => $_POST['smtp_host'] ?? 'smtp.gmail.com',
        'smtp_port'                => $_POST['smtp_port'] ?? '465',
        'smtp_user'                => $_POST['smtp_user'] ?? '',
        'smtp_pass'                => $_POST['smtp_pass'] ?? '',
        'smtp_encryption'          => $_POST['smtp_encryption'] ?? 'ssl'
    ];
    
    foreach ($settings_to_save as $key => $val) {
        $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (:k, :v) ON DUPLICATE KEY UPDATE setting_value = :v");
        $stmt->execute([':k' => $key, ':v' => trim($val)]);
    }

    // LOG HANDLINGEN (Så du kan se det i din nye log-oversigt)
    if (function_exists('log_admin_action')) {
        log_admin_action($pdo, 'MAIL_SETTINGS_UPDATE', "Opdaterede SMTP og mail-indstillinger");
    }

    // RETTET HER: Vi sender brugeren tilbage til 'page=mail_system' i stedet for 'tab=...'
    header("Location: index.php?side=admin&page=mail_system&success=1");
    exit;
}

/* -------------------------------------------------
   Hent nuværende indstillinger
------------------------------------------------- */
// Vi bruger en TRY-CATCH her, så siden ikke dør, hvis site_settings mangler i DB endnu
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings");
    $s = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    $s = []; // Returner tom array hvis tabellen ikke findes endnu
}

$mailMode   = $s['admin_mail_mode'] ?? 'single';
$encryption = $s['smtp_encryption'] ?? 'ssl';
?>

<h1>📧 Mail System & SMTP</h1>

<?php if (isset($_GET['success'])): ?>
    <div style="background: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin-bottom: 20px;">✅ Mail-indstillinger gemt!</div>
<?php endif; ?>

<form method="post" action="?side=admin&page=mail_system">
    <div class="content-card" style="margin-bottom: 20px; background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
        <h3>1. Modtager Indstillinger</h3>
        <label>Hvem skal have besked om nye brugere?</label><br><br>
        <input type="radio" name="admin_mail_mode" value="all" <?= $mailMode === 'all' ? 'checked' : '' ?>> Alle Admins
        <input type="radio" name="admin_mail_mode" value="single" <?= $mailMode === 'single' ? 'checked' : '' ?>> Specifik mail:
        <input type="email" name="admin_notification_email" value="<?= htmlspecialchars($s['admin_notification_email'] ?? '') ?>" placeholder="admin@mail.dk" style="width:100%; max-width:300px; padding:8px; border: 1px solid #ddd; border-radius: 5px;">
    </div>

    <div class="content-card" style="margin-bottom: 20px; border-left: 5px solid #007bff; background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
        <h3>2. SMTP Konfiguration (Gmail)</h3>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
            <div>
                <label>SMTP Host:</label>
                <input type="text" name="smtp_host" value="<?= htmlspecialchars($s['smtp_host'] ?? 'smtp.gmail.com') ?>" style="width:100%; padding:8px; border: 1px solid #ddd; border-radius: 5px;">
            </div>
            <div>
                <label>SMTP Port:</label>
                <input type="text" name="smtp_port" value="<?= htmlspecialchars($s['smtp_port'] ?? '465') ?>" style="width:100%; padding:8px; border: 1px solid #ddd; border-radius: 5px;">
            </div>
            <div>
                <label>SMTP Bruger (Din Gmail):</label>
                <input type="text" name="smtp_user" value="<?= htmlspecialchars($s['smtp_user'] ?? '') ?>" style="width:100%; padding:8px; border: 1px solid #ddd; border-radius: 5px;">
            </div>
            <div>
                <label>SMTP App-Password:</label>
                <input type="password" name="smtp_pass" value="<?= htmlspecialchars($s['smtp_pass'] ?? '') ?>" style="width:100%; padding:8px; border: 1px solid #ddd; border-radius: 5px;">
            </div>
        </div>
        
        <div style="margin-top:15px;">
            <label>Kryptering:</label>
            <select name="smtp_encryption" style="padding:8px; border: 1px solid #ddd; border-radius: 5px;">
                <option value="ssl" <?= $encryption === 'ssl' ? 'selected' : '' ?>>SSL (Port 465)</option>
                <option value="tls" <?= $encryption === 'tls' ? 'selected' : '' ?>>TLS (Port 587)</option>
            </select>
        </div>
    </div>

    <button type="submit" name="save_mail_settings" class="btn-primary" style="padding: 10px 20px; cursor: pointer;">💾 Gem SMTP Indstillinger</button>
</form>