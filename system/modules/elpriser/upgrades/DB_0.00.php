<?php
/**
 * Database Upgrade 0.00 - Den endelige tabelstruktur baseret på fungerende DB
 */

// Filen må kun køres af kernen (via Modul Styring), aldrig åbnes direkte i browseren.
if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(403);
    exit('Adgang nægtet');
}

try {
    // 1. Opret hovedtabellen med ALLE nødvendige kolonner
    $sqlPrices = "CREATE TABLE IF NOT EXISTS `electricity_prices` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `dato` date NOT NULL,
      `price_gns` decimal(10,4) DEFAULT 0.0000,
      `price_max` decimal(10,4) DEFAULT 0.0000,
      `price_min` decimal(10,4) DEFAULT 0.0000,
      `spot_price_raw` decimal(10,5) DEFAULT 0.00000,
      `region` varchar(10) DEFAULT 'DK1',
      `nettarif` decimal(10,4) DEFAULT 0.0000,
      `afgift` decimal(10,4) DEFAULT 0.0000,
      `abonnement_kwh` decimal(10,5) DEFAULT 0.00000,
      `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `dato_region` (`dato`, `region`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $pdo->exec($sqlPrices);

    // SIKKERHEDSTJEK: Tilføj manglende kolonner hvis tabellen allerede findes
    // (rækkefølgen er vigtig: en kolonne placeres EFTER en, der allerede skal findes)
    $columnsToEnsure = [
        'nettarif'       => "decimal(10,4) DEFAULT 0.0000 AFTER region",
        'afgift'         => "decimal(10,4) DEFAULT 0.0000 AFTER nettarif",
        'abonnement_kwh' => "decimal(10,5) DEFAULT 0.00000 AFTER afgift",
        'spot_price_raw' => "decimal(10,5) DEFAULT 0.00000 AFTER price_min"
    ];

    foreach ($columnsToEnsure as $colName => $definition) {
        $check = $pdo->query("SHOW COLUMNS FROM `electricity_prices` LIKE '$colName'");
        if (!$check->fetch()) {
            $pdo->exec("ALTER TABLE `electricity_prices` ADD COLUMN `$colName` $definition");
        }
    }

    // 2. Opret historik-tabellen
    $sqlHistory = "CREATE TABLE IF NOT EXISTS `electricity_rates_history` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `start_dato` date NOT NULL,
      `elafgift` decimal(10,5) NOT NULL,
      `transport_samlet` decimal(10,5) NOT NULL,
      `abonnement_kwh` decimal(10,5) NOT NULL,
      PRIMARY KEY (`id`),
      UNIQUE KEY `unique_date` (`start_dato`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $pdo->exec($sqlHistory);

    // 3. Standardindstillinger (nøglerne har modulets præfiks: elpriser_)
    $settings = [
        'elpriser_region' => 'DK1',
        'elpriser_auto_fetch' => '0',
        'elpriser_fetch_method' => 'energinet',
        'elpriser_sheets_url' => '',
        'elpriser_vat_percent' => '25',
        'elpriser_start_date' => ''
    ];

    foreach ($settings as $key => $val) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO site_settings (setting_key, setting_value) VALUES (?, ?)");
        $stmt->execute([$key, $val]);
    }

} catch (PDOException $e) {
    error_log("Fejl ved oprettelse af elpris tabeller: " . $e->getMessage());
}
