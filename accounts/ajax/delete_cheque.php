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

$master_stmt = $con->prepare('SELECT c.ch_date, c.cheque_number, c.sub_total, c.memo, c.supplier_id, a.ca_name AS bank_name, s.supplier_name FROM acc_cheque c LEFT JOIN acc_chart_of_accounts a ON a.ca_id = c.cr_acc_id LEFT JOIN manage_supplier s ON s.sup_id = c.supplier_id WHERE c.ch_id = ? AND c.location_id = ? AND c.status = 1 LIMIT 1');
$master_stmt->bind_param('ii', $ch_id, $location_id);
$master_stmt->execute();
$cheque = $master_stmt->get_result()->fetch_assoc();
$master_stmt->close();
if (!$cheque) {
    http_response_code(404);
    echo 'Cheque not found or already cancelled.';
    exit;
}
$lines_stmt = $con->prepare('SELECT a.ca_name, d.description, d.amount FROM acc_cheque_detail d LEFT JOIN acc_chart_of_accounts a ON a.ca_id = d.dr_acc_id WHERE d.ch_id = ? AND d.status = 1');
$lines_stmt->bind_param('i', $ch_id);
$lines_stmt->execute();
$line_result = $lines_stmt->get_result();
$line_logs = [];
while ($line = $line_result->fetch_assoc()) $line_logs[] = trim($line['ca_name'] . ' ' . $line['description'] . ' ' . number_format((float)$line['amount'], 2));
$lines_stmt->close();

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
$update_transaction = "UPDATE acc_transaction SET status = 0 WHERE ref_no = '$ch_id' AND tra_type = 'CHEQUE' AND location_id = '$location_id'";
mysqli_query($con, $update_transaction);

// Log the action
$action = 'Cheque cancelled';
$detail = 'Cheque ID ' . $ch_id . '; date ' . $cheque['ch_date'] . '; cheque #' . $cheque['cheque_number'] . '; payee ' . ($cheque['supplier_name'] ?: $cheque['supplier_id']) . '; bank ' . $cheque['bank_name'] . '; total ' . number_format((float)$cheque['sub_total'], 2) . '; lines: ' . implode('; ', $line_logs) . '; memo ' . $cheque['memo'];
UserLog("3", $action, $detail);

echo "Cheque cancelled successfully.";
