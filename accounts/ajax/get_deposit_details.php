<?php
require_once '../../db.php';
require_once '../../auth.php';
header('Content-Type: application/json; charset=utf-8');

$sourceId = (int)($_GET['source_id'] ?? 0);
$clientKey = (string)($_COOKIE['client_cook'] ?? '');
$clientStmt = $con->prepare('SELECT c_id FROM client_registration WHERE md5_client = ? LIMIT 1');
$clientStmt->bind_param('s', $clientKey);
$clientStmt->execute();
$clientStmt->bind_result($locationId);
$hasClient = $clientStmt->fetch();
$clientStmt->close();
if (!$hasClient || $sourceId < 1) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid deposit or client.']);
    exit;
}

$stmt = $con->prepare("SELECT t.source_id, t.t_date, t.t_memo, t.debit AS amount, t.ca_id, a.ca_name
    FROM acc_transaction t
    LEFT JOIN acc_chart_of_accounts a ON a.ca_id = t.ca_id
    WHERE t.source = 'Deposit' AND t.source_id = ? AND t.location_id = ? AND t.status = 1 AND t.credit = 0 AND t.debit > 0
    LIMIT 1");
$stmt->bind_param('ii', $sourceId, $locationId);
$stmt->execute();
$result = $stmt->get_result();
$deposit = $result->fetch_assoc();
$stmt->close();
if (!$deposit) {
    http_response_code(404);
    echo json_encode(['error' => 'Deposit not found or already cancelled.']);
    exit;
}
echo json_encode(['deposit' => $deposit], JSON_INVALID_UTF8_SUBSTITUTE);
