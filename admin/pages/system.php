<?php
// admin/pages/system.php
if (!isset($user) || $user['role'] !== 'admin') {
    exit('Adgang nægtet');
}

$success = '';

// Håndtering af indstillinger
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $siteName = trim($_POST['site_name'] ?? 'Domusius');
    $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES ('site_name', :val) ON DUPLICATE KEY UPDATE setting_value = :val");
    $stmt->execute([':val' => $siteName]);
    
    if (function_exists('log_admin_action')) {
        log_admin_action($pdo, 'SETTINGS_UPDATE', "Ændrede sitenavn til: $siteName");
    }
    $success = "Systemindstillinger er gemt!";
    
    // Opdater den lokale variabel så den slår igennem med det samme
    $site_name = $siteName;
}

// Hent nuværende navn
$stmt = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'site_name'");
$currentName = $stmt->fetchColumn() ?: 'Domusius';
?>

<div class="admin-header">
    <h1>⚙️ Systemindstillinger</h1>
    <p>Her kan du styre de overordnede rammer for din platform.</p>
</div>

<?php if ($success): ?>
    <div class="alert success" style="margin-bottom: 20px;">✅ <?= $success ?></div>
<?php endif; ?>

<div class="grid-container" style="display: grid; grid-template-columns: 1fr; gap: 25px;">

    <div class="content-card">
        <h3><i class="fas fa-globe"></i> Generelle indstillinger</h3>
        <form method="post">
            <div class="form-group" style="margin-bottom: 20px;">
                <label>Sidens Navn (Site Name):</label>
                <input type="text" name="site_name" value="<?= htmlspecialchars($currentName) ?>" style="width: 100%; max-width: 400px; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                <small style="display: block; color: #888; margin-top: 5px;">Dette navn vises i titlen, på login-siden og i bunden af siden.</small>
            </div>
            <button type="submit" name="save_settings" class="btn btn-primary">
                ߒem systemindstillinger
            </button>
        </form>
    </div>

    <div class="content-card">
        <h3><i class="fas fa-database"></i> Database Værktøjer</h3>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 15px;">
            
            <div style="padding: 20px; border: 1px solid #eee; border-radius: 8px;">
                <h4 style="margin-top: 0;">Backup (Export)</h4>
                <p style="font-size: 0.9rem; color: #666; margin-bottom: 20px;">Download en komplet SQL-kopi af din database.</p>
                <a href="admin/system/export_db.php" class="btn btn-primary" style="text-decoration: none; display: inline-block; width: 100%; text-align: center;">
                    ߓ堄ownload SQL
                </a>
            </div>

            <div style="padding: 20px; border: 1px solid #eee; border-radius: 8px;">
                <h4 style="margin-top: 0;">Gendan (Import)</h4>
                <form action="admin/system/import_db.php" method="post" enctype="multipart/form-data">
                    <input type="file" name="db_file" style="margin-bottom: 15px; font-size: 0.8rem;">
                    <button type="submit" class="btn btn-reject" style="width: 100%;" onclick="return confirm('ADVARSEL: Dette overskriver din nuværende database! Er du helt sikker?')">
                        ߓ䠕pload & Gendan
                    </button>
                </form>
            </div>

        </div>
    </div>

    <div class="content-card">
        <h3><i class="fas fa-server"></i> Server Information</h3>
        <table class="admin-table" style="width: 100%;">
            <tr>
                <td style="padding: 12px; border-bottom: 1px solid #eee;"><strong>PHP Version:</strong></td>
                <td style="padding: 12px; border-bottom: 1px solid #eee;"><?= phpversion(); ?></td>
            </tr>
            <tr>
                <td style="padding: 12px;"><strong>Database:</strong></td>
                <td style="padding: 12px;">MariaDB / MySQL</td>
            </tr>
        </table>
    </div>

</div>