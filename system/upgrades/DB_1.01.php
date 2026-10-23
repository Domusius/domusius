<?php
$pdo->exec("ALTER TABLE users ADD last_login DATETIME NULL");
setDbVersion($pdo, '1.01');

?>