<?php
require_once '../../db.php';
require_once '../../auth.php';
header('Content-Type: application/json; charset=utf-8');

$reId = (int)($_GET['re_id'] ?? 0);
$clientKey = (string)($_COOKIE['client_cook'] ?? '');
if ($reId < 1 || $clientKey === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid receipt or client.']);
    exit;
}

$clientStmt = $con->prepare('SELECT c_id FROM client_registration WHERE md5_client = ? LIMIT 1');
$clientStmt->bind_param('s', $clientKey);
$clientStmt->execute();
$clientStmt->bind_result($locationId);
$hasClient = $clientStmt->fetch();
$clientStmt->close();
if (!$hasClient || (int)$locationId < 1) {
    http_response_code(400);
    echo json_encode(['error' => 'Select a client before loading this receipt.']);
    exit;
}
$locationId = (int)$locationId;

$receiptStmt = $con->prepare('SELECT r.*, c.customer_name AS supplier_name FROM acc_receipt r LEFT JOIN mange_customer c ON c.c_id = r.supplier_id WHERE r.re_id = ? AND r.location_id = ? LIMIT 1');
$receiptStmt->bind_param('ii', $reId, $locationId);
$receiptStmt->execute();
$receiptResult = $receiptStmt->get_result();
$receipt = $receiptResult->fetch_assoc();
$receiptStmt->close();
if (!$receipt) {
    http_response_code(404);
    echo json_encode(['error' => 'Receipt not found for the selected client.']);
    exit;
}

$detailStmt = $con->prepare('SELECT d.dr_acc_id, d.description, d.amount, coa.ca_name FROM acc_receipt_detail d LEFT JOIN acc_chart_of_accounts coa ON coa.ca_id = d.dr_acc_id WHERE d.re_id = ? ORDER BY d.id');
$detailStmt->bind_param('i', $reId);
$detailStmt->execute();
$detailResult = $detailStmt->get_result();
$details = [];
while ($line = $detailResult->fetch_assoc()) $details[] = $line;
$detailStmt->close();

echo json_encode(['cheque' => $receipt, 'details' => $details], JSON_INVALID_UTF8_SUBSTITUTE);
