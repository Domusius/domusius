<?php
// system/db_version.php

function getDbVersion(PDO $pdo): string
{
    return $pdo->query("
        SELECT setting_value
        FROM site_settings
        WHERE setting_key = 'db_version'
    ")->fetchColumn() ?: '0.00';
}

function setDbVersion(PDO $pdo, string $v): void
{
    $stmt = $pdo->prepare("
        UPDATE site_settings
        SET setting_value = :v
        WHERE setting_key = 'db_version'
    ");
    $stmt->execute([':v' => $v]);
}

function getPendingUpgrades(PDO $pdo): array
{
    $current = getDbVersion($pdo);
    $dir = __DIR__ . '/upgrades';

    $list = [];
    foreach (glob($dir . '/DB_*.php') as $file) {
        preg_match('/DB_([\d\.]+)\.php$/', $file, $m);
        if (!$m) continue;

        if (version_compare($m[1], $current, '>')) {
            $list[$m[1]] = $file;
        }
    }

    uksort($list, 'version_compare');
    return $list;
}

function runDbUpgrades(PDO $pdo): array
{
    $pending = getPendingUpgrades($pdo);
    $ran = [];

    foreach ($pending as $version => $file) {
        require $file;

        if (!function_exists('upgrade')) {
            throw new Exception("upgrade() mangler i $file");
        }

        $pdo->beginTransaction();
        try {
            upgrade($pdo);
            setDbVersion($pdo, $version);

            $pdo->commit();
            $ran[] = $version;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    return $ran;
}
