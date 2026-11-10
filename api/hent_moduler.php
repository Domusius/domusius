<?php
error_reporting(0);
ini_set('display_errors', 0);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json; charset=utf-8');

try {
    require_once '../config/config.php';
    
    // 1. Hent alle moduler
    $stmt = $pdo->query("SELECT * FROM modules");
    $moduler = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 2. Hent sidetitel (site_name) fra site_settings tabellen
    $siteName = 'Solarius - Ærøvej -1'; // Standard fallback, hvis den ikke findes
    try {
        $stmtSite = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'site_name' LIMIT 1");
        $stmtSite->execute();
        $siteRow = $stmtSite->fetch(PDO::FETCH_ASSOC);
        if ($siteRow && !empty($siteRow['setting_value'])) {
            $siteName = $siteRow['setting_value'];
        }
    } catch (\Exception $ex) {
        // Ignorer evt. fejl hvis tabellen ikke findes endnu, og brug standardnavnet
    }

    // 3. Send et samlet JSON-objekt med både sidetitel og moduler
    echo json_encode([
        "site_title" => $siteName,
        "moduler" => $moduler
    ]);

} catch (\Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
?>