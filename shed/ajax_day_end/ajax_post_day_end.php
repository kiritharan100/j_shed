<?php
include '../../db.php';
include '../../auth.php';

header('Content-Type: application/json');

$serial_no = $_POST['serial_no'] ?? null;

if (!$serial_no) {
    echo json_encode(['success' => false, 'message' => 'This Transactions already posted to accounts']);
    exit;
}

$sql = "UPDATE day_end_process 
        SET posted = 1, posted_by = '$user_id' 
        WHERE serial_no = '$serial_no'";

if (mysqli_query($con, $sql)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => mysqli_error($con)]);
}
