<?php
// system/modules/eksempel/admin/settings.php
// Modulets opsætning i admin. Kun administratorer.
if (!isset($user) || $user['role'] !== 'admin') {
    exit('Adgang nægtet');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $value = trim($_POST['eksempel_tekst'] ?? '');
    $stmt = $pdo->prepare(
        "INSERT INTO site_settings (setting_key, setting_value) VALUES ('eksempel_tekst', :v)
         ON DUPLICATE KEY UPDATE setting_value = :v"
    );
    $stmt->execute([':v' => $value]);
}

$stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'eksempel_tekst'");
$stmt->execute();
$tekst = (string)$stmt->fetchColumn();
?>
<h3>Eksempel Opsætning</h3>
<form method="post">
    <label>Tekst</label>
    <input type="text" name="eksempel_tekst" value="<?= htmlspecialchars($tekst) ?>">
    <button type="submit">Gem</button>
</form>
