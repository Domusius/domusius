<?php
/**
 * admin/pages/log_admin_action.php
 * Funktion til at logge administrative hændelser.
 */

function log_admin_action($pdo, $action, $desc = '') {
    try {
        // Rettet til 'action' og 'details' så det matcher din SQL
        $stmt = $pdo->prepare("
            INSERT INTO admin_logs (admin_id, action, details, ip_address) 
            VALUES (:u, :a, :d, :ip)
        ");
        
        $user_id = $_SESSION['user_id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        $stmt->execute([
            ':u'  => $user_id,
            ':a'  => $action,
            ':d'  => $desc,
            ':ip' => $ip
        ]);
    } catch (PDOException $e) {
        error_log("Logningsfejl i DB: " . $e->getMessage());
    }
}