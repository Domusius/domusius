<?php
function databaseIsInstalled(PDO $pdo): bool
{
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'site_settings'");
        if ($stmt->rowCount() === 0) {
            return false;
        }

        $stmt = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key='installed_at'");
        return $stmt->rowCount() > 0;

    } catch (Throwable $e) {
        return false;
    }
}
