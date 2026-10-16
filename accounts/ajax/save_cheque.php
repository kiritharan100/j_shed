<?php
 
 
include("../../db.php");
include("../../auth.php");
require_once __DIR__ . '/../transaction_date_cookie.php';
require_once __DIR__ . '/../accounting_period_guard.php';

$ch_id         = isset($_POST['ch_id']) ? intval($_POST['ch_id']) : 0;
$payee         = isset($_POST['payee']) && !empty($_POST['payee']) ? mysqli_real_escape_string($con, $_POST['payee']) : '';
$bank_account  = intval($_POST['bank_account']);
$cheque_number = mysqli_real_escape_string($con, $_POST['cheque_number']);
$payment_date  = mysqli_real_escape_string($con, $_POST['payment_date']);
$memo          = mysqli_real_escape_string($con, $_POST['memo']);
$current_date_time = date('Y-m-d H:i:s');
$location_id = accounting_period_location_id($con);
$location_id = $location_id > 0 ? $location_id : 0;
$category    = $_POST['category'];
$description = $_POST['description'];
$amount      = $_POST['amount'];
$is_edit = $ch_id > 0;

if ($location_id < 1) {
    echo 'ERROR: Select a client before saving the cheque.';
    exit;
}

$period_error = accounting_period_date_error($con, (int)$location_id, (string)$payment_date);
if ($period_error !== null) {
    echo 'ERROR: ' . $period_error;
    exit;
}

$get_max = mysqli_query($con, "SELECT MAX(loc_no) as max_no FROM acc_cheque WHERE location_id = '$location_id'");
$row = mysqli_fetch_assoc($get_max);
$next_loc_no = ($row['max_no'] ?? 0) + 1;


$sub_total = 0;
$log_lines = [];
foreach ($amount as $val) {
    $sub_total += floatval($val);
}

if ($ch_id > 0) {
    // UPDATE MODE

    // Clear old lines and transactions
    mysqli_query($con, "DELETE FROM acc_cheque_detail WHERE ch_id = '$ch_id'");
    mysqli_query($con, "DELETE FROM acc_transaction WHERE ref_no = '$ch_id' AND tra_type = 'CHEQUE'");

    $updateCheque = "UPDATE acc_cheque SET 
        cr_acc_id = '$bank_account',
        ch_date = '$payment_date',
        cheque_number = '$cheque_number',
        memo = '$memo',
        sub_total = '$sub_total',
        supplier_id = '$payee'
        WHERE ch_id = '$ch_id'";
    mysqli_query($con, $updateCheque);
} else {
    // INSERT MODE
    $insertCheque = "INSERT INTO acc_cheque 
  (cr_acc_id, ch_date, cheque_number, memo, status, sub_total, created_date, user_id, supplier_id, location_id, loc_no)
  VALUES 
  ('$bank_account', '$payment_date', '$cheque_number', '$memo', 1, '$sub_total', '$current_date_time', '$user_id', '$payee', '$location_id', '$next_loc_no')";


    // $insertCheque = "INSERT INTO acc_cheque (cr_acc_id, ch_date, cheque_number, memo, status, sub_total, created_date, user_id, supplier_id)
    //                  VALUES ('$bank_account', '$payment_date', '$cheque_number', '$memo', 1, '$sub_total', '$current_date_time', '$user_id', '$payee')";
    if (!mysqli_query($con, $insertCheque)) {
        echo "Error saving cheque: " . mysqli_error($con);
        exit;
    }
    $ch_id = mysqli_insert_id($con);
}

// Insert detail lines and transactions
foreach ($category as $i => $cat_id) {
    $acc_id = intval($cat_id);
    $desc   = mysqli_real_escape_string($con, $description[$i]);
    $amt    = floatval($amount[$i]);

    if ($acc_id <= 0 || $amt <= 0) continue;

    // Line
    $insertLine = "INSERT INTO acc_cheque_detail (ch_id, dr_acc_id, description, amount, status)
                   VALUES ('$ch_id', '$acc_id', '$desc', '$amt', 1)";
    mysqli_query($con, $insertLine);
    $account_name = '';
    $account_name_stmt = $con->prepare('SELECT ca_name FROM acc_chart_of_accounts WHERE ca_id = ? LIMIT 1');
    $account_name_stmt->bind_param('i', $acc_id);
    $account_name_stmt->execute();
    $account_name_stmt->bind_result($account_name);
    $account_name_stmt->fetch();
    $account_name_stmt->close();
    $log_lines[] = trim($account_name . ' ' . $desc . ' ' . number_format($amt, 2));

    // Debit entry
$insertDr = "INSERT INTO acc_transaction 
  (ca_id, t_date, tra_type, ref_no, debit, credit, vat_rate, debit_Vat, credit_vat, t_memo, credted_by, location_id, created_on,sup_id)
  VALUES 
  ('$acc_id', '$payment_date', 'CHEQUE', '$ch_id', '$amt', 0, 0, 0, 0, '$desc', '$user_id', '$location_id', NOW(),'$payee')";

    mysqli_query($con, $insertDr);
}

// Credit bank
$insertCr = "INSERT INTO acc_transaction 
  (ca_id, t_date, tra_type, ref_no, debit, credit, vat_rate, debit_Vat, credit_vat, t_memo, credted_by, location_id, created_on,sup_id)
  VALUES 
  ('$bank_account', '$payment_date', 'CHEQUE', '$ch_id', 0, '$sub_total', 0, 0, 0, '$memo', '$user_id', '$location_id', NOW(),'$payee')";

mysqli_query($con, $insertCr);

// Log
$supplier_name = '';
if ($payee !== '') {
    $supplier_stmt = $con->prepare('SELECT supplier_name FROM manage_supplier WHERE sup_id = ? LIMIT 1');
    $payee_id = (int)$payee;
    $supplier_stmt->bind_param('i', $payee_id);
    $supplier_stmt->execute();
    $supplier_stmt->bind_result($supplier_name);
    $supplier_stmt->fetch();
    $supplier_stmt->close();
}
$bank_name = '';
$bank_stmt = $con->prepare('SELECT ca_name FROM acc_chart_of_accounts WHERE ca_id = ? LIMIT 1');
$bank_stmt->bind_param('i', $bank_account);
$bank_stmt->execute();
$bank_stmt->bind_result($bank_name);
$bank_stmt->fetch();
$bank_stmt->close();
$action = $is_edit ? 'Cheque updated' : 'New cheque written';
$detail = 'Cheque ID ' . $ch_id . '; date ' . $payment_date . '; cheque #' . $cheque_number . '; payee ' . ($supplier_name ?: $payee) . '; bank ' . $bank_name . '; total ' . number_format($sub_total, 2) . '; lines: ' . implode('; ', $log_lines) . '; memo ' . trim((string)($_POST['memo'] ?? ''));
UserLog("3", $action, $detail);

remember_shared_transaction_date($payment_date);
echo "Cheque saved successfully.";
