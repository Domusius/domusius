<?php
// system/modules/module_manager.php - Version 0.20 (MED DEBUG)

function checkModuleRequirements(PDO $pdo, string $moduleKey): array {
    $jsonPath = __DIR__ . "/$moduleKey/module.json";
    
    // Hvis filen ikke findes, stopper vi her
    if (!file_exists($jsonPath)) {
        return ['status' => true];
    }

    $config = json_decode(file_get_contents($jsonPath), true);
    
    if (isset($config['requires']) && is_array($config['requires'])) {
        foreach ($config['requires'] as $requiredKey) {
            // Tjekker module_settings tabellen
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM module_settings WHERE LOWER(module_key) = LOWER(:k)");
            $stmt->execute([':k' => $requiredKey]);
            $exists = $stmt->fetchColumn();

            // DEBUG: Fjern denne linje når det virker
            // echo "";

            if ($exists == 0) {
                return [
                    'status' => false, 
                    'message' => "⚠️ Kræver at '$requiredKey' er konfigureret."
                ];
            }
        }
    }
    return ['status' => true];
}

function installModule(PDO $pdo, string $moduleKey): ?string {
    $check = checkModuleRequirements($pdo, $moduleKey);
    if ($check['status'] === false) return $check['message'];

    $jsonPath = __DIR__ . "/$moduleKey/module.json";
    $config = file_exists($jsonPath) ? json_decode(file_get_contents($jsonPath), true) : [];
    $name = $config['name'] ?? ucfirst($moduleKey);

    // Registrer installation
    $pdo->prepare("INSERT INTO modules (module_key, name, is_installed, is_active, installed_at) 
                   VALUES (?, ?, 1, 1, NOW()) 
                   ON DUPLICATE KEY UPDATE is_installed = 1, is_active = 1")->execute([$moduleKey, $name]);

    // Opret i module_settings
    $pdo->prepare("INSERT IGNORE INTO module_settings (module_key, menu_title, admin_menu_title) 
                   VALUES (?, ?, ?)")->execute([
                       $moduleKey, 
                       $config['menu_title'] ?? $name, 
                       $config['admin_menu_title'] ?? $name . " Opsætning"
                   ]);
    return null;
}