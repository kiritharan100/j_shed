<?php
require_once '../../db.php';
require_once '../../auth.php';
header('Content-Type: application/json; charset=utf-8');

$paymentId = (int)($_GET['id'] ?? 0);
$clientKey = (string)($_COOKIE['client_cook'] ?? '');
if ($paymentId < 1 || $clientKey === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payment or client.']);
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
    echo json_encode(['error' => 'Select a client before loading this payment.']);
    exit;
}
$locationId = (int)$locationId;

$paymentStmt = $con->prepare('SELECT cp.*, c.customer_name FROM acc_customer_payment cp LEFT JOIN mange_customer c ON c.c_id = cp.c_id WHERE cp.cp_id = ? AND cp.location_id = ? LIMIT 1');
$paymentStmt->bind_param('ii', $paymentId, $locationId);
$paymentStmt->execute();
$payment = $paymentStmt->get_result()->fetch_assoc();
$paymentStmt->close();
if (!$payment) {
    http_response_code(404);
    echo json_encode(['error' => 'Payment not found for the selected client.']);
    exit;
}

$detailStmt = $con->prepare('SELECT id, inv_id, inv_type, amount, rec_status FROM acc_customer_payment_detail WHERE rec_id = ? ORDER BY id');
$detailStmt->bind_param('i', $paymentId);
$detailStmt->execute();
$detailResult = $detailStmt->get_result();
$details = [];
while ($detail = $detailResult->fetch_assoc()) {
    $invoice = null;
    $invoiceId = (int)$detail['inv_id'];
    if ($detail['inv_type'] === 'Fuel') {
        $stmt = $con->prepare('SELECT s.invoice_no, DATE(s.date_time) AS invoice_date, s.total_sales AS org_amount, (s.total_sales - s.paid_amount + ?) AS amount_due, s.location_id AS loc_id, cr.client_id AS supply_by, s.vehicle_no, s.ref_no AS order_no FROM shed_credit_sales s LEFT JOIN client_registration cr ON cr.c_id = s.location_id WHERE s.cs_id = ? LIMIT 1');
        $paid = (float)$detail['amount'];
        $stmt->bind_param('di', $paid, $invoiceId);
        $stmt->execute();
        $invoice = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    } elseif ($detail['inv_type'] === 'Oil') {
        $stmt = $con->prepare('SELECT o.invoice_no, DATE(o.issue_date) AS invoice_date, CAST(o.issue_total AS DECIMAL(12,2)) AS org_amount, (CAST(o.issue_total AS DECIMAL(12,2)) - o.paid_amount + ?) AS amount_due, o.location AS loc_id, cr.client_id AS supply_by, "" AS vehicle_no, COALESCE(o.ref_no, "") AS order_no FROM oil_sales_master o LEFT JOIN client_registration cr ON cr.c_id = o.location WHERE o.iss_id = ? LIMIT 1');
        $paid = (float)$detail['amount'];
        $stmt->bind_param('di', $paid, $invoiceId);
        $stmt->execute();
        $invoice = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
    $details[] = array_merge($detail, $invoice ?: []);
}
$detailStmt->close();

echo json_encode(['payment' => $payment, 'details' => $details], JSON_INVALID_UTF8_SUBSTITUTE);
