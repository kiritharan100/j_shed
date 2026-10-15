<?php
include '../../db.php';
include '../../auth.php';

if (isset($_POST['cancel_settlement_id'])) {
    $id = intval($_POST['cancel_settlement_id']);

    $check = mysqli_query($con, "SELECT created_on, status FROM shed_settlement WHERE id='$id' LIMIT 1");
    $row   = mysqli_fetch_assoc($check);

    header('Content-Type: application/json');

    if ($row && $row['status'] == 1) {
        $created_on = strtotime($row['created_on']);
        $now        = time();

        if (($now - $created_on) <= 86400) { // 24 hours
            mysqli_query($con, "UPDATE shed_settlement SET status=0 WHERE id='$id'");
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Cancellation allowed only within 24 hours']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid settlement or already cancelled']);
    }
    exit;
}
