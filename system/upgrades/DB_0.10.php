<?php
function upgrade(PDO $pdo)
{
    $pdo->exec("
        ALTER TABLE users
        ADD last_login DATETIME NULL AFTER role
    ");
}
