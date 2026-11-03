<?php
// system/modules/eksempel/widgets/dashboard_view.php
// Vises som kort på dashboardet (rammen og overskriften laves af inc/dashboard.php).
// Kortet vises kun, hvis modulet er slået til under "Vis på dashboard" i modulets admin-side.
// $pdo er tilgængelig.
$stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'eksempel_tekst'");
$stmt->execute();
$tekst = (string)$stmt->fetchColumn();
?>
<div>
    <?= $tekst !== '' ? htmlspecialchars($tekst) : 'Indhold til dashboard-kortet. Skriv en tekst under modulets opsætning i admin.' ?>
</div>
