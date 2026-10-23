<?php
// admin/pages/brugere.php

if (!isset($user) || $user['role'] !== 'admin') {
    http_response_code(403);
    exit('Adgang nægtet');
}

// Inkluder nødvendige funktioner
require_once __DIR__ . '/../../system/mail_functions.php';
require_once __DIR__ . '/log_admin_action.php';

// Hent sitets navn til brug i mails
$stmtSettings = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'site_name'");
$currentSiteName = $stmtSettings->fetchColumn() ?: 'Domusius';

/* -------------------------------------------------
    1. Håndtering af Bruger-handlinger
------------------------------------------------- */
if (isset($_GET['action']) && isset($_GET['user_id'])) {
    $targetId = (int)$_GET['user_id'];
    $action = $_GET['action'];

    if ($targetId === (int)$user['id']) {
        header("Location: index.php?side=admin&tab=brugere&error=self_action");
        exit;
    }

    // Hent brugerdata til log og mail
    $stmt = $pdo->prepare("SELECT username, email FROM users WHERE id = ?");
    $stmt->execute([$targetId]);
    $targetUser = $stmt->fetch();
    $targetName = $targetUser['username'] ?? "Ukendt (ID: $targetId)";
    $targetEmail = $targetUser['email'] ?? '';

    if ($action === 'approve' || $action === 'activate') {
        $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?")->execute([$targetId]);
        
        // Send Mail til bruger
        if (!empty($targetEmail)) {
            $subject = "Din konto er nu godkendt på $currentSiteName";
            $message = "Hej $targetName,\n\nDin konto er blevet godkendt af en administrator. Du kan nu logge ind og få adgang til siden.";
            sendSystemMail($pdo, $targetEmail, $subject, $message);
        }

        if (function_exists('log_admin_action')) {
            log_admin_action($pdo, 'USER_UPDATE', "Godkendte bruger: $targetName");
        }
    } 
    elseif ($action === 'deactivate') {
        $pdo->prepare("UPDATE users SET status = 'disabled' WHERE id = ?")->execute([$targetId]);
        if (function_exists('log_admin_action')) {
            log_admin_action($pdo, 'USER_UPDATE', "Deaktiverede bruger: $targetName");
        }
    } 
    elseif ($action === 'make_admin') {
        $pdo->prepare("UPDATE users SET role = 'admin' WHERE id = ?")->execute([$targetId]);
        if (function_exists('log_admin_action')) {
            log_admin_action($pdo, 'ROLE_CHANGE', "Gjorde $targetName til Admin");
        }
    } 
    elseif ($action === 'make_user') {
        $pdo->prepare("UPDATE users SET role = 'user' WHERE id = ?")->execute([$targetId]);
        if (function_exists('log_admin_action')) {
            log_admin_action($pdo, 'ROLE_CHANGE', "Ændrede $targetName til almindelig bruger");
        }
    }
    elseif ($action === 'delete') {
        // Send Mail om afslag inden sletning
        if (!empty($targetEmail)) {
            $subject = "Opdatering vedrørende din ansøgning på $currentSiteName";
            $message = "Hej $targetName,\n\nDin ansøgning om en konto er desværre ikke blevet godkendt i denne omgang, og din profil er slettet fra systemet.";
            sendSystemMail($pdo, $targetEmail, $subject, $message);
        }

        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$targetId]);
        if (function_exists('log_admin_action')) {
            log_admin_action($pdo, 'USER_DELETE', "Slettede bruger permanent: $targetName");
        }
    }

    header("Location: index.php?side=admin&tab=brugere&success=1");
    exit;
}

/* -------------------------------------------------
    2. Gem Regler (Indstillinger)
------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_user_settings'])) {
    $keys = ['registration_open', 'require_user_approval'];
    foreach ($keys as $key) {
        $val = isset($_POST[$key]) ? '1' : '0';
        $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (:k, :v) ON DUPLICATE KEY UPDATE setting_value = :v");
        $stmt->execute([':k' => $key, ':v' => $val]);
    }
    
    if (function_exists('log_admin_action')) {
        log_admin_action($pdo, 'SETTINGS_UPDATE', "Opdaterede regler for brugeroprettelse");
    }

    header("Location: index.php?side=admin&tab=brugere&success=1");
    exit;
}

/* -------------------------------------------------
    3. Hent Data til visning
------------------------------------------------- */
$settings = $pdo->query("SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ('registration_open','require_user_approval')")->fetchAll(PDO::FETCH_KEY_PAIR);
$users_list = $pdo->query("SELECT id, username, email, role, status FROM users ORDER BY id DESC")->fetchAll();
?>

<h1>👤 Brugeradministration</h1>

<?php if (isset($_GET['success'])): ?>
    <div style="background: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin-bottom: 20px;">
        ✅ Handling udført! (Brugeren har modtaget mail hvis relevant)
    </div>
<?php endif; ?>

<div class="content-card" style="margin-bottom: 30px; border-left: 5px solid #007bff;">
    <h3>Regler for nye brugere</h3>
    <form method="post">
        <label style="display: block; margin-bottom: 10px; cursor: pointer;">
            <input type="checkbox" name="registration_open" <?= ($settings['registration_open'] ?? '0') === '1' ? 'checked' : '' ?>>
            Tillad at nye brugere selv kan oprette en konto
        </label>
        <label style="display: block; margin-bottom: 15px; cursor: pointer;">
            <input type="checkbox" name="require_user_approval" <?= ($settings['require_user_approval'] ?? '1') === '1' ? 'checked' : '' ?>>
            Nye brugere skal godkendes før de kan logge ind
        </label>
        <button type="submit" name="save_user_settings" class="btn btn-primary">💾 Gem regler</button>
    </form>
</div>

<div class="content-card">
    <h3>Eksisterende brugere</h3>
    <table class="admin-table">
        <thead>
            <tr>
                <th>Bruger</th>
                <th>Rolle</th>
                <th>Status</th>
                <th>Handling</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users_list as $u): ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($u['username']) ?></strong><br>
                        <small style="color: #666;"><?= htmlspecialchars($u['email']) ?></small>
                    </td>
                    <td>
                        <span class="status-pill <?= $u['role'] === 'admin' ? 'active' : '' ?>">
                            <?= ucfirst($u['role']) ?>
                        </span>
                    </td>
                    <td>
                        <span class="status-pill <?= $u['status'] ?>">
                            <?= ucfirst($u['status']) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ((int)$u['id'] !== (int)$user['id']): ?>
                            <div class="action-buttons-inline">
                                
                                <?php if ($u['role'] === 'admin'): ?>
                                    <a href="?side=admin&tab=brugere&action=make_user&user_id=<?= $u['id'] ?>" class="btn">Gør til bruger</a>
                                <?php else: ?>
                                    <a href="?side=admin&tab=brugere&action=make_admin&user_id=<?= $u['id'] ?>" class="btn btn-admin">Gør til admin</a>
                                <?php endif; ?>

                                <?php if ($u['status'] === 'pending'): ?>
                                    <a href="?side=admin&tab=brugere&action=approve&user_id=<?= $u['id'] ?>" class="btn btn-approve">Godkend</a>
                                <?php elseif ($u['status'] === 'active'): ?>
                                    <a href="?side=admin&tab=brugere&action=deactivate&user_id=<?= $u['id'] ?>" class="btn btn-disable">Deaktiver</a>
                                <?php else: ?>
                                    <a href="?side=admin&tab=brugere&action=activate&user_id=<?= $u['id'] ?>" class="btn btn-enable">Aktiver</a>
                                <?php endif; ?>

                                <a href="?side=admin&tab=brugere&action=delete&user_id=<?= $u['id'] ?>" 
                                   class="btn btn-reject" 
                                   onclick="return confirm('Slet bruger permanent? Der sendes besked om afslag.')">Slet</a>
                            </div>
                        <?php else: ?>
                            <small><i>Dig selv</i></small>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>