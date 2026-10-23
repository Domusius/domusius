<?php
// DB upgrade to 0.01

$pdo->exec("
    ALTER TABLE users
    ADD last_login DATETIME NULL AFTER role
");

return true;