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

$detail_stmt = $con->prepare("SELECT t_date, debit, ca_id, t_memo, (SELECT ca_name FROM acc_chart_of_accounts WHERE ca_id = t.ca_id LIMIT 1) AS account_name FROM acc_transaction t WHERE source = 'Deposit' AND source_id = ? AND location_id = ? AND status = 1 AND debit > 0 LIMIT 1");
$detail_stmt->bind_param('ii', $source_id, $location_id);
$detail_stmt->execute();
$deposit_detail = $detail_stmt->get_result()->fetch_assoc();
$detail_stmt->close();

$update = $con->prepare("UPDATE acc_transaction SET status = 0 WHERE source = 'Deposit' AND source_id = ? AND location_id = ? AND status = 1");
$update->bind_param('ii', $source_id, $location_id);
if ($update->execute() && $update->affected_rows > 0) {
    if ($deposit_detail) {
        UserLog('3', 'Deposit Cancelled', 'Deposit #' . (int)$source_id . '; date ' . $deposit_detail['t_date'] . '; amount ' . number_format((float)$deposit_detail['debit'], 2) . '; account ' . $deposit_detail['account_name'] . '; memo ' . $deposit_detail['t_memo']);
    }
    echo "Deposit cancelled successfully.";
} else {
    echo "Failed to cancel deposit.";
}
$update->close();
?>
