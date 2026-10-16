<?php
include("../../db.php");
include("../../auth.php");

$id = intval($_POST['id']);
if (!$id) {
  echo "Invalid journal ID";
  exit;
}

$client_key = (string)($_COOKIE['client_cook'] ?? '');
$client_stmt = $con->prepare('SELECT c_id FROM client_registration WHERE md5_client = ? LIMIT 1');
$client_stmt->bind_param('s', $client_key);
$client_stmt->execute();
$client_stmt->bind_result($location_id);
$has_client = $client_stmt->fetch();
$client_stmt->close();
if (!$has_client) {
  http_response_code(400);
  echo 'Select a client before cancelling journals.';
  exit;
}

$master_stmt = $con->prepare('SELECT journal_date, memo, total_debit, total_credit FROM acc_journal WHERE id = ? AND location_id = ? AND status = 1 LIMIT 1');
$master_stmt->bind_param('ii', $id, $location_id);
$master_stmt->execute();
$journal = $master_stmt->get_result()->fetch_assoc();
$master_stmt->close();
if (!$journal) {
  http_response_code(404);
  echo 'Journal not found or already cancelled.';
  exit;
}
$lines_stmt = $con->prepare('SELECT a.ca_name, d.description, d.debit, d.credit FROM acc_journal_detail d LEFT JOIN acc_chart_of_accounts a ON a.ca_id = d.ca_id WHERE d.journal_id = ? AND d.status = 1');
$lines_stmt->bind_param('i', $id);
$lines_stmt->execute();
$line_result = $lines_stmt->get_result();
$line_logs = [];
while ($line = $line_result->fetch_assoc()) $line_logs[] = trim($line['ca_name'] . ' ' . $line['description'] . ' Dr ' . number_format((float)$line['debit'], 2) . ' Cr ' . number_format((float)$line['credit'], 2));
$lines_stmt->close();

 $update = $con->prepare('UPDATE acc_journal SET status = 0 WHERE id = ? AND location_id = ? AND status = 1');
 $update->bind_param('ii', $id, $location_id);
 $update->execute();
 $changed = $update->affected_rows > 0;
 $update->close();
if (!$changed) {
  http_response_code(404);
  echo 'Journal not found or already cancelled.';
  exit;
}
mysqli_query($con, "UPDATE acc_journal_detail SET status = 0 WHERE journal_id = $id");
mysqli_query($con, "UPDATE acc_transaction SET status = 0 WHERE ref_no = $id AND tra_type = 'JOURNAL' AND location_id = '$location_id'");

UserLog('3', 'Journal Cancelled', 'Journal ID ' . $id . '; date ' . $journal['journal_date'] . '; debit ' . number_format((float)$journal['total_debit'], 2) . '; credit ' . number_format((float)$journal['total_credit'], 2) . '; memo ' . $journal['memo'] . '; lines: ' . implode('; ', $line_logs));
echo "Journal cancelled successfully.";
