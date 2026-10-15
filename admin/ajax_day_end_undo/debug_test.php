<?php
include '../../db.php';
include '../../auth.php'; 

header('Content-Type: application/json');

// Simple debug test
if (!$con) {
    echo json_encode(['debug' => 'Database connection failed']);
    exit;
}

echo json_encode([
    'debug' => 'Connection successful',
    'location_id' => $_GET['location_id'] ?? 'not provided',
    'serial_no' => $_GET['serial_no'] ?? 'not provided',
    'user_id' => $user_id ?? 'not set'
]);
?>