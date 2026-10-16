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
mysqli_query($con, "DELETE FROM acc_transaction WHERE ref_no = $id AND tra_type = 'JOURNAL'");

UserLog("3", "Journal Cancelled", "Journal ID $id");
echo "Journal cancelled successfully.";
