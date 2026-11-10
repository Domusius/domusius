<?php
error_reporting(0);
ini_set('display_errors', 0);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/config.php';

$input = json_decode(file_get_contents('php://input'), true);

$username = $input['username'] ?? '';
$password = $input['password'] ?? '';

if (empty($username) || empty($password)) {
    echo json_encode(["status" => "error", "message" => "Udfyld venligst både brugernavn og adgangskode"]);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND status = 'active'");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        // Login succesfuldt - returner brugerdata og rolle (f.eks. admin)
        echo json_encode([
            "status" => "success",
            "message" => "Login succesfuldt",
            "user" => [
                "id" => $user['id'],
                "username" => $user['username'],
                "email" => $user['email'],
                "role" => $user['role']
            ]
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "Ugyldigt brugernavn eller adgangskode"]);
    }
} catch (\Exception $e) {
    echo json_encode(["status" => "error", "message" => "Databasefejl: " . $e->getMessage()]);
}
?>