<?php
// admin/pages/system.php
if (!isset($user) || $user['role'] !== 'admin') {
    exit('Adgang nægtet');
}

require_once __DIR__ . '/../../inc/csrf.php';
require_once __DIR__ . '/../../inc/favicon.php';
$csrf = csrf_token();

$success = '';
$error = '';

/**
 * Gemmer én indstilling i site_settings.
 */
function system_save_setting(PDO $pdo, string $key, string $value): void
{
    $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (:k, :v) ON DUPLICATE KEY UPDATE setting_value = :v");
    $stmt->execute([':k' => $key, ':v' => $value]);
}

// Alle formularer på siden sender sikkerhedstokenet med.
// Uden et gyldigt token gemmes der ingenting.
$isPost  = $_SERVER['REQUEST_METHOD'] === 'POST';
$tokenOk = !$isPost || csrf_check($_POST['csrf'] ?? '');
if ($isPost && !$tokenOk) {
    $error = 'Handlingen blev afvist, fordi sikkerhedstokenet manglede eller var forkert. Prøv igen fra denne side.';
}

// 1. Generelle indstillinger
if ($isPost && $tokenOk && isset($_POST['save_settings'])) {
    $siteName = trim($_POST['site_name'] ?? 'Domusius');
    $siteUrl  = rtrim(trim($_POST['site_url'] ?? ''), '/');

    if ($siteUrl !== '' && !preg_match('#^https?://[A-Za-z0-9.-]+(:[0-9]{1,5})?(/[A-Za-z0-9._~/-]*)?$#', $siteUrl)) {
        $error = 'Sidens adresse skal begynde med http:// eller https:// og må ikke indeholde mellemrum.';
    } else {
        system_save_setting($pdo, 'site_name', $siteName);
        system_save_setting($pdo, 'site_url', $siteUrl);

        if (function_exists('log_admin_action')) {
            log_admin_action($pdo, 'SETTINGS_UPDATE', "Ændrede sitenavn til: $siteName");
        }
        $success = "Systemindstillinger er gemt!";

        // Opdater den lokale variabel så den slår igennem med det samme
        $site_name = $siteName;
    }
}

// 2. Vælg ikon på browserfanen
if ($isPost && $tokenOk && isset($_POST['save_favicon'])) {
    $choice  = $_POST['favicon'] ?? '';
    $allowed = array_merge(array_keys(favicon_defaults()), favicon_custom_files());
    if (is_string($choice) && in_array($choice, $allowed, true)) {
        system_save_setting($pdo, 'site_favicon', $choice);
        if (function_exists('log_admin_action')) {
            log_admin_action($pdo, 'SETTINGS_UPDATE', "Valgte ikon på browserfanen: $choice");
        }
        $success = 'Ikonet er valgt.';
    } else {
        $error = 'Ugyldigt valg af ikon.';
    }
}

// 3. Upload eget ikon
if ($isPost && $tokenOk && isset($_POST['upload_favicon'])) {
    $file = $_FILES['favicon_file'] ?? null;

    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        $error = 'Vælg en fil først.';
    } elseif ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        $error = 'Filen kunne ikke uploades.';
    } elseif (count(favicon_custom_files()) >= FAVICON_MAX_CUSTOM) {
        $error = 'Der kan højst gemmes ' . FAVICON_MAX_CUSTOM . ' egne ikoner. Slet et, før du uploader et nyt.';
    } else {
        // Filen kontrolleres ud fra indholdet, og den gemmes under et nyt navn med tidsstempel (aldrig brugerens eget)
        [$ext, $problem] = favicon_validate_upload($file['tmp_name'], (int)$file['size']);
        $dir = __DIR__ . '/../../assets/favicons/';

        if ($ext === null) {
            $error = $problem;
        } elseif (!is_dir($dir) || !is_writable($dir)) {
            $error = 'Mappen assets/favicons kan ikke skrives til. Tjek rettighederne på mappen.';
        } else {
            $newName = favicon_new_custom_name($ext);
            if (!move_uploaded_file($file['tmp_name'], $dir . $newName)) {
                $error = 'Filen kunne ikke gemmes.';
            } else {
                system_save_setting($pdo, 'site_favicon', $newName);
                if (function_exists('log_admin_action')) {
                    log_admin_action($pdo, 'SETTINGS_UPDATE', "Uploadede eget ikon til browserfanen: $newName");
                }
                $success = 'Dit eget ikon er gemt og valgt.';
            }
        }
    }
}

// 4. Slet et eget ikon
if ($isPost && $tokenOk && isset($_POST['delete_favicon'])) {
    $del = $_POST['delete_favicon'];
    if (!is_string($del) || !in_array($del, favicon_custom_files(), true)) {
        $error = 'Ugyldigt valg af ikon.';
    } elseif ($del === favicon_current($pdo)) {
        $error = 'Det ikon er i brug. Vælg et andet ikon først.';
    } else {
        @unlink(__DIR__ . '/../../assets/favicons/' . $del);
        if (function_exists('log_admin_action')) {
            log_admin_action($pdo, 'SETTINGS_UPDATE', "Slettede eget ikon: $del");
        }
        $success = 'Ikonet er slettet.';
    }
}

// Hent nuværende værdier
$stmt = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'site_name'");
$currentName = $stmt->fetchColumn() ?: 'Domusius';

$stmt = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'site_url'");
$currentUrl = (string)$stmt->fetchColumn();

$currentFavicon = favicon_current($pdo);
$customFiles    = favicon_custom_files();
$faviconChoices = favicon_defaults();
foreach ($customFiles as $customFile) {
    $faviconChoices[$customFile] = favicon_custom_label($customFile);
}
?>

<div class="admin-header">
    <h1><i class="fas fa-cog"></i> Systemindstillinger</h1>
    <p>Her kan du styre de overordnede rammer for din platform.</p>
</div>

<?php if ($error): ?>
    <div class="alert error" style="margin-bottom: 20px; background: #f8d7da; color: #721c24; padding: 12px 15px; border-radius: 8px;"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert success" style="margin-bottom: 20px;"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div class="grid-container" style="display: grid; grid-template-columns: 1fr; gap: 25px;">

    <div class="content-card">
        <h3><i class="fas fa-globe"></i> Generelle indstillinger</h3>
        <form method="post">
            <?= csrf_field() ?>
            <div class="form-group" style="margin-bottom: 20px;">
                <label>Sidens Navn (Site Name):</label>
                <input type="text" name="site_name" value="<?= htmlspecialchars($currentName) ?>" style="width: 100%; max-width: 400px; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                <small style="display: block; color: #888; margin-top: 5px;">Dette navn vises i titlen, på login-siden og i bunden af siden.</small>
            </div>
            <div class="form-group" style="margin-bottom: 20px;">
                <label>Sidens adresse (Site URL):</label>
                <input type="text" name="site_url" value="<?= htmlspecialchars($currentUrl) ?>" placeholder="https://dit-domæne.dk/domusius" style="width: 100%; max-width: 400px; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                <small style="display: block; color: #888; margin-top: 5px;">Hele adressen til siden, uden skråstreg til sidst. Bruges i linket i mails om glemt adgangskode. Er feltet tomt, bruges adressen fra browseren, som er mindre sikker.</small>
            </div>
            <button type="submit" name="save_settings" class="btn btn-primary">
                <i class="fas fa-save"></i> Gem systemindstillinger
            </button>
        </form>
    </div>

    <div class="content-card">
        <h3><i class="fas fa-image"></i> Ikon på browserfanen</h3>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 15px;">

            <div style="padding: 20px; border: 1px solid #eee; border-radius: 8px;">
                <h4 style="margin-top: 0;">Vælg ikon</h4>
                <p style="font-size: 0.9rem; color: #666; margin-bottom: 20px;">Det valgte ikon vises på browserfanen på alle sider.</p>
                <form method="post">
                    <?= csrf_field() ?>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px;">
                        <?php foreach ($faviconChoices as $fileName => $label): ?>
                            <label style="display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 10px 12px; min-width: 80px; cursor: pointer; border-radius: 10px; border: 2px solid <?= $fileName === $currentFavicon ? '#0d6efd' : '#e2e8f0' ?>;">
                                <img src="assets/favicons/<?= htmlspecialchars($fileName) ?>?v=<?= (int)@filemtime(__DIR__ . '/../../assets/favicons/' . $fileName) ?>" width="40" height="40" alt="">
                                <span style="font-size: 13px;"><?= htmlspecialchars($label) ?></span>
                                <input type="radio" name="favicon" value="<?= htmlspecialchars($fileName) ?>" <?= $fileName === $currentFavicon ? 'checked' : '' ?>>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <button type="submit" name="save_favicon" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-save"></i> Vælg ikon
                    </button>
                </form>
            </div>

            <div style="padding: 20px; border: 1px solid #eee; border-radius: 8px;">
                <h4 style="margin-top: 0;">Upload dit eget ikon</h4>
                <p style="font-size: 0.9rem; color: #666; margin-bottom: 20px;">PNG (16 til 512 pixel, helst kvadratisk) eller ICO, højst 200 KB. Hvert upload gemmes som et nyt ikon med dato og tid i filnavnet, så du kan skifte mellem dem. Der kan gemmes op til <?= FAVICON_MAX_CUSTOM ?> egne ikoner.</p>
                <form method="post" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="file" name="favicon_file" accept=".png,.ico" style="margin-bottom: 15px; font-size: 0.8rem;">
                    <button type="submit" name="upload_favicon" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-upload"></i> Upload og vælg
                    </button>
                </form>
            </div>

        </div>

        <?php if ($customFiles): ?>
            <div style="margin-top: 20px; padding: 20px; border: 1px solid #eee; border-radius: 8px;">
                <h4 style="margin-top: 0;">Dine uploadede ikoner</h4>
                <?php foreach ($customFiles as $customFile): ?>
                    <div style="display: flex; align-items: center; gap: 12px; padding: 8px 0; border-bottom: 1px solid #eee;">
                        <img src="assets/favicons/<?= htmlspecialchars($customFile) ?>?v=<?= (int)@filemtime(__DIR__ . '/../../assets/favicons/' . $customFile) ?>" width="24" height="24" alt="">
                        <span style="flex: 1; font-size: 0.9rem;"><?= htmlspecialchars(favicon_custom_label($customFile)) ?></span>
                        <?php if ($customFile === $currentFavicon): ?>
                            <span style="font-size: 0.8rem; color: #166534;">I brug</span>
                        <?php else: ?>
                            <form method="post" style="margin: 0;">
                                <?= csrf_field() ?>
                                <button type="submit" name="delete_favicon" value="<?= htmlspecialchars($customFile) ?>" class="btn btn-reject" style="width: auto; padding: 4px 12px;" onclick="return confirm('Slet dette ikon?')">
                                    <i class="fas fa-trash"></i> Slet
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="content-card">
        <h3><i class="fas fa-database"></i> Database Værktøjer</h3>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 15px;">
            
            <div style="padding: 20px; border: 1px solid #eee; border-radius: 8px;">
                <h4 style="margin-top: 0;">Backup (Export)</h4>
                <p style="font-size: 0.9rem; color: #666; margin-bottom: 20px;">Download en komplet SQL-kopi af din database.</p>
                <a href="admin/system/export_db.php?csrf=<?= $csrf ?>" class="btn btn-primary" style="text-decoration: none; display: inline-block; width: 100%; text-align: center;">
                    <i class="fas fa-download"></i> Download SQL
                </a>
            </div>

            <div style="padding: 20px; border: 1px solid #eee; border-radius: 8px;">
                <h4 style="margin-top: 0;">Gendan (Import)</h4>
                <form action="admin/system/import_db.php" method="post" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="file" name="db_file" style="margin-bottom: 15px; font-size: 0.8rem;">
                    <button type="submit" class="btn btn-reject" style="width: 100%;" onclick="return confirm('ADVARSEL: Dette overskriver din nuværende database! Er du helt sikker?')">
                        <i class="fas fa-upload"></i> Upload & Gendan
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