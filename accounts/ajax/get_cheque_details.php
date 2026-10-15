<?php
include("../../db.php");
include("../../auth.php");
header('Content-Type: application/json; charset=utf-8');

$ch_id = isset($_GET['ch_id']) ? intval($_GET['ch_id']) : 0;

if (!$ch_id) {
    echo json_encode(['error' => 'Invalid cheque ID']);
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
    echo json_encode(['error' => 'Select a client before viewing cheques.']);
    exit;
}

// Fetch cheque header for the selected client only.
$stmt = $con->prepare('SELECT c.*, s.supplier_name FROM acc_cheque c LEFT JOIN manage_supplier s ON c.supplier_id = s.sup_id WHERE c.ch_id = ? AND c.location_id = ? LIMIT 1');
$stmt->bind_param('ii', $ch_id, $location_id);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || mysqli_num_rows($result) === 0) {
    echo json_encode(['error' => 'Cheque not found']);
    exit;
}

$cheque = mysqli_fetch_assoc($result);

// Fetch cheque line details
$detailStmt = $con->prepare('SELECT d.*, coa.ca_name FROM acc_cheque_detail d LEFT JOIN acc_chart_of_accounts coa ON d.dr_acc_id = coa.ca_id WHERE d.ch_id = ?');
$detailStmt->bind_param('i', $ch_id);
$detailStmt->execute();
$detailsResult = $detailStmt->get_result();

$details = [];
while ($row = mysqli_fetch_assoc($detailsResult)) {
    $details[] = [
        'dr_acc_id'   => $row['dr_acc_id'],
        'description' => $row['description'],
        'amount'      => $row['amount'],
        'ca_name'     => $row['ca_name']
    ];
}
$detailStmt->close();
$stmt->close();

// Return JSON response
echo json_encode([
    'cheque' => $cheque,
    'details' => $details
]);
