<?php
require_once '../../db.php';
require_once '../../auth.php';
header('Content-Type: application/json; charset=utf-8');

function payment_json_error(string $message, int $status = 400): void
{
    http_response_code($status);
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit;
}

$paymentId = (int)($_POST['cp_id'] ?? 0);
$clientKey = (string)($_COOKIE['client_cook'] ?? '');
if ($paymentId < 1 || $clientKey === '') payment_json_error('Invalid payment or client.');
$clientStmt = $con->prepare('SELECT c_id FROM client_registration WHERE md5_client = ? LIMIT 1');
$clientStmt->bind_param('s', $clientKey);
$clientStmt->execute();
$clientStmt->bind_result($locationId);
$hasClient = $clientStmt->fetch();
$clientStmt->close();
if (!$hasClient || (int)$locationId < 1) payment_json_error('Select a client before cancelling this payment.');
$locationId = (int)$locationId;

try {
    $con->begin_transaction();
    $paymentStmt = $con->prepare('SELECT receipt_no, c_id, ca_id, rec_date, cheque_no, Memo, rec_amount, rec_status FROM acc_customer_payment WHERE cp_id = ? AND location_id = ? FOR UPDATE');
    $paymentStmt->bind_param('ii', $paymentId, $locationId);
    $paymentStmt->execute();
    $payment = $paymentStmt->get_result()->fetch_assoc();
    $paymentStmt->close();
    if (!$payment) throw new RuntimeException('Payment not found for the selected client.');
    if ((int)$payment['rec_status'] !== 1) throw new RuntimeException('This payment is already cancelled.');

    $detailsStmt = $con->prepare('SELECT inv_id, inv_type, amount FROM acc_customer_payment_detail WHERE rec_id = ? AND rec_status = 1 FOR UPDATE');
    $detailsStmt->bind_param('i', $paymentId);
    $detailsStmt->execute();
    $details = $detailsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $detailsStmt->close();
    $allocationLog = array_map(static function (array $detail): string {
        return $detail['inv_type'] . ' invoice #' . (int)$detail['inv_id'] . ' ' . number_format((float)$detail['amount'], 2);
    }, $details);

    foreach ($details as $detail) {
        $invoiceId = (int)$detail['inv_id'];
        $amount = (float)$detail['amount'];
        if ($detail['inv_type'] === 'Fuel') {
            $stmt = $con->prepare('UPDATE shed_credit_sales SET paid_amount = GREATEST(0, paid_amount - ?) WHERE cs_id = ?');
            $stmt->bind_param('di', $amount, $invoiceId);
        } elseif ($detail['inv_type'] === 'Oil') {
            $stmt = $con->prepare('UPDATE oil_sales_master SET paid_amount = GREATEST(0, paid_amount - ?) WHERE iss_id = ?');
            $stmt->bind_param('di', $amount, $invoiceId);
        } else {
            continue;
        }
        $stmt->execute();
        $stmt->close();
    }

    $masterStmt = $con->prepare('UPDATE acc_customer_payment SET rec_status = 0 WHERE cp_id = ? AND location_id = ?');
    $masterStmt->bind_param('ii', $paymentId, $locationId);
    $masterStmt->execute();
    $masterStmt->close();
    $detailUpdate = $con->prepare('UPDATE acc_customer_payment_detail SET rec_status = 0 WHERE rec_id = ?');
    $detailUpdate->bind_param('i', $paymentId);
    $detailUpdate->execute();
    $detailUpdate->close();
    $transactionUpdate = $con->prepare("UPDATE acc_transaction SET status = 0 WHERE source = 'receipt' AND source_id = ?");
    $transactionUpdate->bind_param('i', $paymentId);
    $transactionUpdate->execute();
    $transactionUpdate->close();
    $con->commit();
} catch (Throwable $error) {
    $con->rollback();
    payment_json_error($error->getMessage(), 500);
}

$customerName = '';
$customerStmt = $con->prepare('SELECT customer_name FROM mange_customer WHERE c_id = ? LIMIT 1');
$customerStmt->bind_param('i', $payment['c_id']);
$customerStmt->execute();
$customerStmt->bind_result($customerName);
$customerStmt->fetch();
$customerStmt->close();
$accountName = '';
$accountStmt = $con->prepare('SELECT ca_name FROM acc_chart_of_accounts WHERE ca_id = ? LIMIT 1');
$accountStmt->bind_param('i', $payment['ca_id']);
$accountStmt->execute();
$accountStmt->bind_result($accountName);
$accountStmt->fetch();
$accountStmt->close();
UserLog('3', 'Customer Payment Cancelled', 'Receipt ' . $payment['receipt_no'] . ' (Payment ID ' . $paymentId . '); date ' . $payment['rec_date'] . '; customer ' . ($customerName ?: $payment['c_id']) . '; account ' . $accountName . '; amount ' . number_format((float)$payment['rec_amount'], 2) . '; cheque ' . $payment['cheque_no'] . '; memo ' . $payment['Memo'] . '; allocations: ' . implode('; ', $allocationLog));
echo json_encode(['status' => 'success', 'message' => 'Customer payment cancelled successfully.']);
