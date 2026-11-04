<?php
// system/modules/eksempel/uninstall.php
// Køres, når modulet afinstalleres. Fjern kun modulets egne tabeller og indstillinger.
// $pdo er tilgængelig fra kernen.

// Filen må kun køres af kernen (via Modul Styring), aldrig åbnes direkte i browseren.
if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(403);
    exit('Adgang nægtet');
}

try {
    $pdo->exec("DROP TABLE IF EXISTS `eksempel_data`");
    $pdo->exec("DELETE FROM site_settings WHERE setting_key LIKE 'eksempel\\_%'");
    echo "SUCCESS: Eksempel-modulets data er fjernet.";
} catch (Exception $e) {
    echo "FEJL ved afinstallation: " . $e->getMessage();
}