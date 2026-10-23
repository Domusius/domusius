<?php
// system/modules/eksempel/upgrades/DB_0.01.php
// Køres automatisk én gang, når modulets version i databasen er lavere end 0.01.
// $pdo er tilgængelig fra kernen - hent IKKE config.php herfra.
// Tabeller får modulets præfiks: eksempel_

$pdo->exec("CREATE TABLE IF NOT EXISTS `eksempel_data` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `navn` VARCHAR(100) NOT NULL,
    `oprettet` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
