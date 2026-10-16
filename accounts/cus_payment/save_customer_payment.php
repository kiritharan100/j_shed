<?php
require_once '../../db.php';
require_once '../../auth.php';
require_once __DIR__ . '/../transaction_date_cookie.php';
require_once __DIR__ . '/../accounting_period_guard.php';
header('Content-Type: application/json; charset=utf-8');

function payment_response(string $status, string $message, int $httpCode = 200): void
{
    http_response_code($httpCode);
    echo json_encode(['status' => $status, 'message' => $message]);
    exit;
}

function insert_payment_transaction(mysqli $con, int $locationId, int $accountId, string $date, int $sourceId, int $receiptNo, float $debit, float $credit, string $memo, int $userId, int $customerId, int $otherBranch = 0): void
{
    $stmt = $con->prepare("INSERT INTO acc_transaction (location_id, ca_id, t_date, tra_type, source, source_id, ref_no, debit, credit, vat_rate, debit_Vat, credit_vat, t_memo, credted_by, created_on, cus_id, sup_id, status, other_branch) VALUES (?, ?, ?, 'receipt', 'receipt', ?, ?, ?, ?, 0, 0, 0, ?, ?, NOW(), ?, 0, 1, ?)");
    $stmt->bind_param('iisiiddsiii', $locationId, $accountId, $date, $sourceId, $receiptNo, $debit, $credit, $memo, $userId, $customerId, $otherBranch);
    $stmt->execute();
    $stmt->close();
}

$paymentId = (int)($_POST['payment_id'] ?? 0);
$isEdit = $paymentId > 0;
$customerId = (int)($_POST['customer_id'] ?? 0);
$accountId = (int)($_POST['ca_id'] ?? 0);
$date = trim((string)($_POST['rec_date'] ?? ''));
$chequeNo = trim((string)($_POST['cheque_no'] ?? ''));
$memo = trim((string)($_POST['memo'] ?? ''));
$amounts = $_POST['amount'] ?? [];
$invoiceIds = $_POST['inv_id'] ?? [];
$invoiceTypes = $_POST['inv_type'] ?? [];
if ($customerId < 1 || $accountId < 1 || count($amounts) !== count($invoiceIds) || count($amounts) !== count($invoiceTypes)) payment_response('error', 'Select a customer and account, then check the invoice amounts.');
$parsedDate = DateTime::createFromFormat('!Y-m-d', $date);
if (!$parsedDate || $parsedDate->format('Y-m-d') !== $date) payment_response('error', 'Enter a valid payment date.');

$clientKey = (string)($_COOKIE['client_cook'] ?? '');
$clientStmt = $con->prepare('SELECT c_id FROM client_registration WHERE md5_client = ? LIMIT 1');
$clientStmt->bind_param('s', $clientKey);
$clientStmt->execute();
$clientStmt->bind_result($locationId);
$hasClient = $clientStmt->fetch();
$clientStmt->close();
if (!$hasClient || (int)$locationId < 1) payment_response('error', 'Select a client before saving a payment.');
$locationId = (int)$locationId;
$periodError = accounting_period_date_error($con, $locationId, $date);
if ($periodError !== null) payment_response('error', $periodError, 422);
$userId = (int)($_SESSION['user_id'] ?? 0);

try {
    $con->begin_transaction();
    $receiptNo = 0;
    if ($paymentId > 0) {
        $masterLock = $con->prepare('SELECT receipt_no, rec_status FROM acc_customer_payment WHERE cp_id = ? AND location_id = ? FOR UPDATE');
        $masterLock->bind_param('ii', $paymentId, $locationId);
        $masterLock->execute();
        $existing = $masterLock->get_result()->fetch_assoc();
        $masterLock->close();
        if (!$existing || (int)$existing['rec_status'] !== 1) throw new RuntimeException('Payment not found or already cancelled.');
        $receiptNo = (int)$existing['receipt_no'];

        $oldDetails = $con->prepare('SELECT inv_id, inv_type, amount FROM acc_customer_payment_detail WHERE rec_id = ? AND rec_status = 1 FOR UPDATE');
        $oldDetails->bind_param('i', $paymentId);
        $oldDetails->execute();
        $oldRows = $oldDetails->get_result()->fetch_all(MYSQLI_ASSOC);
        $oldDetails->close();
        foreach ($oldRows as $old) {
            $oldInvoiceId = (int)$old['inv_id'];
            $oldAmount = (float)$old['amount'];
            if ($old['inv_type'] === 'Fuel') $restore = $con->prepare('UPDATE shed_credit_sales SET paid_amount = GREATEST(0, paid_amount - ?) WHERE cs_id = ?');
            elseif ($old['inv_type'] === 'Oil') $restore = $con->prepare('UPDATE oil_sales_master SET paid_amount = GREATEST(0, paid_amount - ?) WHERE iss_id = ?');
            else continue;
            $restore->bind_param('di', $oldAmount, $oldInvoiceId);
            $restore->execute();
            $restore->close();
        }
        $detailsCancel = $con->prepare('UPDATE acc_customer_payment_detail SET rec_status = 0 WHERE rec_id = ? AND rec_status = 1');
        $detailsCancel->bind_param('i', $paymentId);
        $detailsCancel->execute();
        $detailsCancel->close();
        $transactionsCancel = $con->prepare("UPDATE acc_transaction SET status = 0 WHERE source = 'receipt' AND source_id = ?");
        $transactionsCancel->bind_param('i', $paymentId);
        $transactionsCancel->execute();
        $transactionsCancel->close();
    } else {
        $serial = $con->prepare('SELECT COALESCE(MAX(receipt_no), 0) + 1 FROM acc_customer_payment WHERE location_id = ? FOR UPDATE');
        $serial->bind_param('i', $locationId);
        $serial->execute();
        $serial->bind_result($receiptNo);
        $serial->fetch();
        $serial->close();
    }

    $total = 0.0;
    $items = [];
    $locationTotals = [];
    foreach ($amounts as $index => $rawAmount) {
        $amount = (float)$rawAmount;
        if ($amount <= 0) continue;
        $invoiceId = (int)$invoiceIds[$index];
        $invoiceType = (string)$invoiceTypes[$index];
        if ($invoiceId < 1 || !in_array($invoiceType, ['Fuel', 'Oil'], true)) throw new RuntimeException('An invoice selection is invalid.');

        if ($invoiceType === 'Fuel') {
            $invoiceStmt = $con->prepare('SELECT location_id, c_id, invoice_no, total_sales, paid_amount FROM shed_credit_sales WHERE cs_id = ? AND status = 1 FOR UPDATE');
            $invoiceStmt->bind_param('i', $invoiceId);
        } else {
            $invoiceStmt = $con->prepare('SELECT location AS location_id, to_location AS c_id, invoice_no, CAST(issue_total AS DECIMAL(12,2)) AS total_sales, paid_amount FROM oil_sales_master WHERE iss_id = ? AND issue_status = 1 FOR UPDATE');
            $invoiceStmt->bind_param('i', $invoiceId);
        }
        $invoiceStmt->execute();
        $invoice = $invoiceStmt->get_result()->fetch_assoc();
        $invoiceStmt->close();
        if (!$invoice || (int)$invoice['c_id'] !== $customerId) throw new RuntimeException('An invoice does not belong to the selected customer or is no longer available.');
        $outstanding = (float)$invoice['total_sales'] - (float)$invoice['paid_amount'];
        if ($amount > $outstanding + 0.005) throw new RuntimeException('An entered amount exceeds the invoice balance. Reload the customer invoices and try again.');

        if ($invoiceType === 'Fuel') $updateInvoice = $con->prepare('UPDATE shed_credit_sales SET paid_amount = paid_amount + ? WHERE cs_id = ?');
        else $updateInvoice = $con->prepare('UPDATE oil_sales_master SET paid_amount = paid_amount + ? WHERE iss_id = ?');
        $updateInvoice->bind_param('di', $amount, $invoiceId);
        $updateInvoice->execute();
        $updateInvoice->close();

        $items[] = ['invoice_id' => $invoiceId, 'invoice_no' => (string)$invoice['invoice_no'], 'type' => $invoiceType, 'amount' => $amount, 'location_id' => (int)$invoice['location_id']];
        $locationTotals[(int)$invoice['location_id']] = ($locationTotals[(int)$invoice['location_id']] ?? 0) + $amount;
        $total += $amount;
    }
    if ($total <= 0) throw new RuntimeException('Enter a payment amount for at least one invoice.');
    if (abs($total - (float)($_POST['total_paid'] ?? 0)) > 0.02) throw new RuntimeException('The payment total does not match the invoice amounts.');

    if ($paymentId > 0) {
        $masterUpdate = $con->prepare('UPDATE acc_customer_payment SET c_id = ?, ca_id = ?, rec_amount = ?, rec_date = ?, cheque_no = ?, Memo = ? WHERE cp_id = ? AND location_id = ?');
        $masterUpdate->bind_param('iidsssii', $customerId, $accountId, $total, $date, $chequeNo, $memo, $paymentId, $locationId);
        $masterUpdate->execute();
        $masterUpdate->close();
    } else {
        $masterInsert = $con->prepare("INSERT INTO acc_customer_payment (c_id, ca_id, location_id, receipt_no, rec_amount, rec_date, pay_type, cheque_no, Memo, rec_status, record_date, user_id) VALUES (?, ?, ?, ?, ?, ?, 'Cash', ?, ?, 1, NOW(), ?)");
        $masterInsert->bind_param('iiiidsssi', $customerId, $accountId, $locationId, $receiptNo, $total, $date, $chequeNo, $memo, $userId);
        $masterInsert->execute();
        $paymentId = (int)$con->insert_id;
        $masterInsert->close();
    }

    foreach ($items as $item) {
        $detailInsert = $con->prepare('INSERT INTO acc_customer_payment_detail (rec_id, inv_id, inv_type, amount, rec_status) VALUES (?, ?, ?, ?, 1)');
        $itemInvoiceId = (int)$item['invoice_id'];
        $itemType = (string)$item['type'];
        $itemAmount = (float)$item['amount'];
        $detailInsert->bind_param('iisd', $paymentId, $itemInvoiceId, $itemType, $itemAmount);
        $detailInsert->execute();
        $detailInsert->close();
    }

    $createdAt = date('Y-m-d H:i:s');
    $mainMemo = "Customer settlement $customerId CHQ:$chequeNo, RNo:$receiptNo" . ($memo !== '' ? " - $memo" : '');
    insert_payment_transaction($con, $locationId, $accountId, $date, $paymentId, $receiptNo, $total, 0, $mainMemo, $userId, $customerId);
    foreach ($locationTotals as $invoiceLocation => $locationTotal) {
        if ($invoiceLocation === $locationId) {
            insert_payment_transaction($con, $locationId, 8, $date, $paymentId, $receiptNo, 0, $locationTotal, $mainMemo, $userId, $customerId);
        } else {
            insert_payment_transaction($con, $locationId, 20, $date, $paymentId, $receiptNo, 0, $locationTotal, "Customer settlement other branch $invoiceLocation RNo:$receiptNo", $userId, $customerId, $invoiceLocation);
            insert_payment_transaction($con, $invoiceLocation, 8, $date, $paymentId, $receiptNo, 0, $locationTotal, "Customer settlement other branch $locationId RNo:$receiptNo", $userId, $customerId, $locationId);
            insert_payment_transaction($con, $invoiceLocation, 20, $date, $paymentId, $receiptNo, $locationTotal, 0, "Customer settlement other branch $locationId RNo:$receiptNo", $userId, $customerId, $locationId);
        }
    }
    $con->commit();
} catch (Throwable $error) {
    $con->rollback();
    payment_response('error', $error->getMessage(), 500);
}

remember_shared_transaction_date($date);
$action = $isEdit ? 'Customer Payment Edited' : 'Customer Payment Recorded';
$customerName = '';
$customerNameStmt = $con->prepare('SELECT customer_name FROM mange_customer WHERE c_id = ? LIMIT 1');
$customerNameStmt->bind_param('i', $customerId);
$customerNameStmt->execute();
$customerNameStmt->bind_result($customerName);
$customerNameStmt->fetch();
$customerNameStmt->close();
$accountName = '';
$accountNameStmt = $con->prepare('SELECT ca_name FROM acc_chart_of_accounts WHERE ca_id = ? LIMIT 1');
$accountNameStmt->bind_param('i', $accountId);
$accountNameStmt->execute();
$accountNameStmt->bind_result($accountName);
$accountNameStmt->fetch();
$accountNameStmt->close();
$invoiceLog = array_map(static function (array $item): string {
    return $item['type'] . ' invoice ' . $item['invoice_no'] . ' (ID ' . $item['invoice_id'] . ') ' . number_format((float)$item['amount'], 2);
}, $items);
UserLog('3', $action, 'Receipt ' . $receiptNo . '; date ' . $date . '; customer ' . ($customerName ?: $customerId) . '; account ' . $accountName . '; amount ' . number_format($total, 2) . '; cheque ' . $chequeNo . '; memo ' . $memo . '; allocations: ' . implode('; ', $invoiceLog));
payment_response('success', $isEdit ? 'Customer payment updated successfully.' : 'Payment recorded successfully.');
