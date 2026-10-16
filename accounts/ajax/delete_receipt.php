<?php
include("../../db.php");
include("../../auth.php");

$re_id = isset($_POST['re_id']) ? intval($_POST['re_id']) : 0;

if (!$re_id) {
    echo "Invalid cheque ID.";
    exit;
}

$client_key = (string)($_COOKIE['client_cook'] ?? '');
$client_stmt = $con->prepare('SELECT c_id FROM client_registration WHERE md5_client = ? LIMIT 1');
$client_stmt->bind_param('s', $client_key);
$client_stmt->execute();
$client_stmt->bind_result($location_id);
$has_client = $client_stmt->fetch();
$client_stmt->close();
if (!$has_client || (int)$location_id < 1) {
    http_response_code(400);
    echo 'Select a client before cancelling receipts.';
    exit;
}

$master_stmt = $con->prepare('SELECT r.ch_date, r.cheque_number, r.sub_total, r.memo, r.supplier_id, a.ca_name AS account_name, c.customer_name FROM acc_receipt r LEFT JOIN acc_chart_of_accounts a ON a.ca_id = r.dr_acc_id LEFT JOIN mange_customer c ON c.c_id = r.supplier_id WHERE r.re_id = ? AND r.location_id = ? AND r.status = 1 LIMIT 1');
$master_stmt->bind_param('ii', $re_id, $location_id);
$master_stmt->execute();
$receipt = $master_stmt->get_result()->fetch_assoc();
$master_stmt->close();
if (!$receipt) {
    http_response_code(404);
    echo 'Receipt not found or already cancelled.';
    exit;
}
$lines_stmt = $con->prepare('SELECT a.ca_name, d.description, d.amount FROM acc_receipt_detail d LEFT JOIN acc_chart_of_accounts a ON a.ca_id = d.dr_acc_id WHERE d.re_id = ? AND d.status = 1');
$lines_stmt->bind_param('i', $re_id);
$lines_stmt->execute();
$line_result = $lines_stmt->get_result();
$line_logs = [];
while ($line = $line_result->fetch_assoc()) $line_logs[] = trim($line['ca_name'] . ' ' . $line['description'] . ' ' . number_format((float)$line['amount'], 2));
$lines_stmt->close();

// Soft delete from acc_receipt
$update_cheque = "UPDATE acc_receipt SET status = 0 WHERE re_id = '$re_id' AND location_id = '$location_id' AND status = 1";
mysqli_query($con, $update_cheque);

// Soft delete from acc_receipt_detail
$update_details = "UPDATE acc_receipt_detail SET status = 0 WHERE re_id = '$re_id'";
mysqli_query($con, $update_details);

// Soft delete from acc_transaction
$update_transaction = "UPDATE acc_transaction SET status = 0 WHERE ref_no = '$re_id' AND tra_type = 'Other Rec' AND location_id = '$location_id'";
mysqli_query($con, $update_transaction);

// Log the action
$action = 'Receipt cancelled';
$detail = 'Receipt ID ' . $re_id . '; date ' . $receipt['ch_date'] . '; cheque #' . $receipt['cheque_number'] . '; customer ' . ($receipt['customer_name'] ?: $receipt['supplier_id']) . '; account ' . $receipt['account_name'] . '; total ' . number_format((float)$receipt['sub_total'], 2) . '; lines: ' . implode('; ', $line_logs) . '; memo ' . $receipt['memo'];
UserLog("3", $action, $detail);

echo "Cheque cancelled successfully.";
