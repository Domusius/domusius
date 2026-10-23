<?php
function upgrade(PDO $pdo)
{
    // users: godkendelse
    $pdo->exec("
        ALTER TABLE users
        ADD is_approved TINYINT(1) NOT NULL DEFAULT 0,
        ADD status ENUM('pending','active','disabled') NOT NULL DEFAULT 'pending'
    ");

    // site settings
    $pdo->exec("
        INSERT INTO site_settings (setting_key, setting_value) VALUES
        ('allow_self_register', '0'),
        ('require_user_approval', '1')
        ON DUPLICATE KEY UPDATE setting_value = setting_value
    ");
}
