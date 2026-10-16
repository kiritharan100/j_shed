<?php
include("../../db.php");
include("../../auth.php");

$source_id    = $_POST['source_id'] ?? 0;
$selected_client = (string)($_COOKIE['client_cook'] ?? '');
$client_stmt = $con->prepare('SELECT c_id FROM client_registration WHERE md5_client = ? LIMIT 1');
$client_stmt->bind_param('s', $selected_client);
$client_stmt->execute();
$client_stmt->bind_result($location_id);
$has_client = $client_stmt->fetch();
$client_stmt->close();

if (!$source_id || !$has_client || !$location_id) {
    echo "Invalid request.";
    exit;
}

$update = $con->prepare("UPDATE acc_transaction SET status = 0 WHERE source = 'Deposit' AND source_id = ? AND location_id = ? AND status = 1");
$update->bind_param('ii', $source_id, $location_id);
if ($update->execute() && $update->affected_rows > 0) {
    echo "Deposit cancelled successfully.";
} else {
    echo "Failed to cancel deposit.";
}
$update->close();
?>
