<?php
include("../../db.php");
include("../../auth.php");

$ch_id = isset($_POST['ch_id']) ? intval($_POST['ch_id']) : 0;

if (!$ch_id) {
    echo "Invalid cheque ID.";
    exit;
}

$selected_client = (string)($_COOKIE['client_cook'] ?? '');
$client_stmt = $con->prepare('SELECT c_id FROM client_registration WHERE md5_client = ? LIMIT 1');
$client_stmt->bind_param('s', $selected_client);
$client_stmt->execute();
$client_stmt->bind_result($location_id);
$valid_client = $client_stmt->fetch();
$client_stmt->close();
if (!$valid_client) {
    http_response_code(400);
    echo 'Select a client before cancelling cheques.';
    exit;
}

// Soft delete from acc_cheque
$update_cheque = $con->prepare('UPDATE acc_cheque SET status = 0 WHERE ch_id = ? AND location_id = ? AND status = 1');
$update_cheque->bind_param('ii', $ch_id, $location_id);
$update_cheque->execute();
$updated = $update_cheque->affected_rows > 0;
$update_cheque->close();
if (!$updated) {
    http_response_code(404);
    echo 'Cheque not found or already cancelled.';
    exit;
}

// Soft delete from acc_cheque_detail
$update_details = "UPDATE acc_cheque_detail SET status = 0 WHERE ch_id = '$ch_id'";
mysqli_query($con, $update_details);

// Soft delete from acc_transaction
$update_transaction = "UPDATE acc_transaction SET status = 0 WHERE ref_no = '$ch_id' AND tra_type = 'CHEQUE'";
mysqli_query($con, $update_transaction);

// Log the action
$action = "Cheque deleted";
$detail = "Cheque #$ch_id marked as cancelled";
UserLog("3", $action, $detail);

echo "Cheque cancelled successfully.";
