<?php
/**
 * Modul Loader - Version 0.02
 * Scanner system/modules/ mappen baseret på databasen.
 */

function load_active_modules($pdo) {
    try {
        // Vi henter aktive moduler fra din tabel
        $stmt = $pdo->prepare("SELECT module_key, version FROM modules WHERE is_active = 1");
        $stmt->execute();
        $activeModules = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($activeModules as $m) {
            $key = $m['module_key'];
            $currentDbVersion = $m['version'];
            
            // Stien til modulets mappe
            $modulePath = __DIR__ . "/../system/modules/{$key}/";
            $jsonPath = $modulePath . 'module.json';

            if (file_exists($jsonPath)) {
                $config = json_decode(file_get_contents($jsonPath), true);
                
                // 1. Tjek for database opdateringer
                if (isset($config['db']) && $config['db'] === true) {
                    check_module_upgrades($pdo, $key, $currentDbVersion, $modulePath);
                }

                // 2. Auto-tilføj til menu (Bruger nu add_action som matcher dit hook-system)
                if (isset($config['admin_page']) && $config['admin_page'] === true) {
                    add_action('admin_sidebar_menu', function() use ($config, $key) {
                        // Rettet fra ?page= til ?side= så det matcher din index.php routing
                        echo '<a class="nav-item" href="?side=' . htmlspecialchars($key) . '">';
                        echo '<i class="fas fa-plug"></i> ' . htmlspecialchars($config['name']);
                        echo '</a>';
                    });
                }

                // 3. Indlæs modulets init.php hvis den findes
                if (file_exists($modulePath . 'init.php')) {
                    // Vi gør $pdo global, så modulet kan bruge den med det samme
                    global $pdo; 
                    include_once $modulePath . 'init.php';
                }
            }
        }
    } catch (Exception $e) {
        // Vi logger fejlen i stedet for at dræbe hele siden, hvis et modul fejler
        error_log("Fejl i Modul Loader: " . $e->getMessage());
    }
}

/**
 * Håndterer DB opgraderinger fra upgrades/ mappen
 */
function check_module_upgrades($pdo, $key, $currentVersion, $path) {
    $upgradeDir = $path . "upgrades/";
    if (!is_dir($upgradeDir)) return;

    $upgradeFiles = glob($upgradeDir . "DB_*.php");
    if (!$upgradeFiles) return;
    
    sort($upgradeFiles);

    foreach ($upgradeFiles as $file) {
        preg_match('/DB_(.*)\.php/', basename($file), $matches);
        $fileVersion = $matches[1];

        if (version_compare($fileVersion, $currentVersion, '>')) {
            // Gør $pdo tilgængelig for upgrade-filen
            global $pdo;
            include $file; 
            
            // Opdater versionen i DB
            $updateStmt = $pdo->prepare("UPDATE modules SET version = ? WHERE module_key = ?");
            $updateStmt->execute([$fileVersion, $key]);
        }
    }
}

// Start indlæsningen
load_active_modules($pdo);