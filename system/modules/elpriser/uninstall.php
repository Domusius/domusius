<?php
// system/modules/elpriser/uninstall.php
// Køres, når modulet afinstalleres. Fjerner kun modulets egne tabeller og indstillinger.
// $pdo er tilgængelig fra kernen.

// Filen må kun køres af kernen (via Modul Styring), aldrig åbnes direkte i browseren.
if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(403);
    exit('Adgang nægtet');
}

try {
    $pdo->exec("DROP TABLE IF EXISTS `electricity_prices`");
    $pdo->exec("DROP TABLE IF EXISTS `electricity_rates_history`");
    $pdo->exec("DELETE FROM site_settings WHERE setting_key LIKE 'elpriser\\_%'");
    echo "SUCCESS: El-priser er fjernet.";
} catch (Exception $e) {
    echo "FEJL ved afinstallation: " . $e->getMessage();
}
