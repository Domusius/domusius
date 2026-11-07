<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *'); // Tillader at appen kan kalde fra andre klienter

echo json_encode([
    'status' => 'success',
    'message' => 'Forbindelse til Domusius Synology-server er oprettet!',
    'timestamp' => date('Y-m-d H:i:s')
]);
?>