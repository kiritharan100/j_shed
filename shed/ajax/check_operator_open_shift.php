<?php
include '../../db.php';

header('Content-Type: application/json');

if (!isset($_POST['operator_id']) || empty($_POST['operator_id'])) {
    echo json_encode(['error' => 'Invalid operator ID']);
    exit;
}

$operator_id = intval($_POST['operator_id']);
$location_id = intval($_POST['location_id']); // Pass this from frontend too for safety

$query = "SELECT COUNT(*) as open_count FROM shed_operator_shift 
          WHERE operator_id = $operator_id AND location_id = $location_id AND open_status = 1";

$result = mysqli_query($con, $query);
$row = mysqli_fetch_assoc($result);

echo json_encode(['has_open_shift' => $row['open_count'] > 0]);
