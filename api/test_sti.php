<?php
header("Access-Control-Allow-Origin: *");
header('Content-Type: application/json; charset=utf-8');

$sti_til_config = '../config/config.php';
$fil_findes = file_exists($sti_til_config) ? "JA" : "NEJ";

echo json_encode([
    "mappe_nuvaerende" => __DIR__,
    "sti_testet" => $sti_til_config,
    "config_eksisterer" => $fil_findes
]);
?>