<?php
// inc/profil.php
if (!isset($user)) {
    die("Adgang nægtet.");
}

$msg = '';

/* -------------------------------------------------
    Logik til opdatering af adgangskode
------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $currentPass = $_POST['current_password'] ?? '';
    $newPass     = $_POST['new_password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    if (!password_verify($currentPass, $user['password'])) {
        $msg = "<div class='alert error'>❌ Den nuværende adgangskode er forkert.</div>";
    } 
    elseif ($newPass !== $confirmPass) {
        $msg = "<div class='alert error'>❌ De nye adgangskoder matcher ikke.</div>";
    } 
    elseif (strlen($newPass) < 6) {
        $msg = "<div class='alert error'>❌ Koden skal være mindst 6 tegn.</div>";
    } 
    else {
        $newHash = password_hash($newPass, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        if ($stmt->execute([$newHash, $user['id']])) {
            $msg = "<div class='alert success'>✅ Adgangskode opdateret!</div>";
            $user['password'] = $newHash;
        }
    }
}
?>

<div class="profile-page">
    <div class="profile-header" style="margin-bottom: 30px;">
        <h2 style="margin: 0; font-size: 1.8rem;">Min Profil</h2>
        <p style="color: #666; margin-top: 5px;">Administrer dine oplysninger og sikkerhed herunder.</p>
    </div>

    <?= $msg ?>

    <div class="profile-grid" style="display: flex; gap: 40px; align-items: flex-start; flex-wrap: wrap;">
        
        <div class="info-section" style="flex: 1; min-width: 300px;">
            <h3 style="border-bottom: 2px solid #eee; padding-bottom: 10px; margin-bottom: 20px;">Brugeroplysninger</h3>
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 15px 0; border-bottom: 1px solid #f0f0f0; color: #666; width: 120px;"><strong>Brugernavn:</strong></td>
                    <td style="padding: 15px 0; border-bottom: 1px solid #f0f0f0;"><?= htmlspecialchars($user['username']) ?></td>
                </tr>
                <tr>
                    <td style="padding: 15px 0; border-bottom: 1px solid #f0f0f0; color: #666;"><strong>E-mail:</strong></td>
                    <td style="padding: 15px 0; border-bottom: 1px solid #f0f0f0;"><?= htmlspecialchars($user['email']) ?></td>
                </tr>
                <tr>
                    <td style="padding: 15px 0; border-bottom: 1px solid #f0f0f0; color: #666;"><strong>Rolle:</strong></td>
                    <td style="padding: 15px 0; border-bottom: 1px solid #f0f0f0;">
                        <span class="status-pill <?= $user['role'] === 'admin' ? 'active' : '' ?>">
                            <?= ucfirst($user['role']) ?>
                        </span>
                    </td>
                </tr>
            </table>
        </div>

        <div class="password-section" style="flex: 1; min-width: 300px; background: #fcfcfc; padding: 25px; border-radius: 10px; border: 1px solid #eaeaea; box-shadow: 0 2px 5px rgba(0,0,0,0.02);">
            <h3 style="margin-top: 0; margin-bottom: 20px;">Skift adgangskode</h3>
            <form method="post" action="?side=profil">
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 500;">Nuværende adgangskode</label>
                    <input type="password" name="current_password" required style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box;">
                </div>

                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 500;">Ny adgangskode</label>
                    <input type="password" name="new_password" required style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box;">
                </div>

                <div class="form-group" style="margin-bottom: 25px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 500;">Gentag ny adgangskode</label>
                    <input type="password" name="confirm_password" required style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box;">
                </div>

                <button type="submit" name="update_password" class="btn-primary" style="width: 100%; padding: 12px; cursor: pointer;">💾 Opdater kode</button>
            </form>
        </div>

    </div>
</div>