<?php
error_reporting(0);
ini_set('display_errors', 0);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json; charset=utf-8');

try {
    require_once '../config/config.php';
    
    $stmt = $pdo->query("SELECT * FROM modules");
    $moduler = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($moduler);
} catch (\Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
?>