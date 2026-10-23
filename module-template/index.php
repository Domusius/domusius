<?php
// system/modules/eksempel/index.php
// Modulets side: vises på ?side=eksempel
// $pdo, $user og $site_name er tilgængelige fra kernen.

// Indstillinger gemmes i site_settings med modulets nøgle som præfiks
$stmt = $pdo->prepare("SELECT setting_key, setting_value FROM site_settings WHERE setting_key LIKE 'eksempel\_%'");
$stmt->execute();
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<h2>Eksempel-modul</h2>
<p>Hej <?= htmlspecialchars($user['username']) ?>! Her kommer modulets indhold.</p>
