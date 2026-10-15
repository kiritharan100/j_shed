<?php
// include '../../db.php';
// include '../../auth.php';

// header('Content-Type: application/json');

// if ($_SERVER['REQUEST_METHOD'] != 'POST') {
//     echo json_encode(['success' => false, 'error' => 'Invalid request']);
//     exit;
// }

// // Sanitize inputs
// $location_id    = intval($_POST['location_id']);
// $inv_id         = intval($_POST['inv_id']);
// $purchase_date  = $_POST['purchase_date'];
// $invoice_no     = mysqli_real_escape_string($con, $_POST['invoice_no']);
// $supplier_id    = intval($_POST['supplier_id']);
// $p_id           = intval($_POST['p_id']);
// $quantity       = floatval($_POST['quantity']);
// $rate           = floatval($_POST['rate']);
// $total_invoice  = floatval($_POST['total_invoice']);
// $vat_amount     = floatval($_POST['vat_amount'] ?? 0);
// $user_id        = intval($user_id); // From session
// $created_at     = date('Y-m-d H:i:s');

// // Date validation (only today or up to 2 days back)
// $min_date = date('Y-m-d', strtotime('-2 days'));
// $max_date = date('Y-m-d');
// if ($purchase_date < $min_date || $purchase_date > $max_date) {
//     echo json_encode(['success' => false, 'error' => 'Purchase date must be within the past 2 days or today.']);
//     exit;
// }

// // Check for duplicate invoice
// $dup_check = mysqli_query($con, "SELECT 1 FROM fuel_purchase_master WHERE location_id = $location_id AND Invoice_no = '$invoice_no'");
// if (mysqli_num_rows($dup_check) > 0) {
//     echo json_encode(['success' => false, 'error' => 'Invoice number already exists for this location.']);
//     exit;
// }

// // Insert into database
// $insert = mysqli_query($con, "
//     INSERT INTO fuel_purchase_master 
//     (location_id, inv_id, purchase_date, Invoice_no, supplier_id, p_id, quantity, rate, vat_amount, total_invoice, created_at)
//     VALUES
//     ($location_id, $inv_id, '$purchase_date', '$invoice_no', $supplier_id, $p_id, $quantity, $rate, $vat_amount, $total_invoice, '$created_at')
// ");

// if ($insert) {
//     // Optional: get product and supplier names for the log
//     $product_name = '';
//     $supplier_name = '';

//     $pq = mysqli_query($con, "SELECT p_name FROM product_master WHERE p_id = $p_id");
//     if ($pq && $pr = mysqli_fetch_assoc($pq)) {
//         $product_name = $pr['p_name'];
//     }

//     $sq = mysqli_query($con, "SELECT supplier_name FROM manage_supplier WHERE sup_id = $supplier_id");
//     if ($sq && $sr = mysqli_fetch_assoc($sq)) {
//         $supplier_name = $sr['supplier_name'];
//     }

//     // Log user action
//     $log_message = "Fuel purchase added. Invoice No: $invoice_no, Supplier: $supplier_name, Product: $product_name, Qty: $quantity, Total: " . number_format($total_invoice, 2);
//     UserLog(1, 'Fuel Purchase Added', $log_message); // 1 = general/insert log

//     echo json_encode(['success' => true]);
// } else {
//     echo json_encode(['success' => false, 'error' => 'Insert failed: ' . mysqli_error($con)]);
// }



?>
<?php
include '../../db.php';
include '../../auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

// Sanitize inputs
$location_id    = intval($_POST['location_id']);
$inv_id         = intval($_POST['inv_id']);
$purchase_date  = $_POST['purchase_date'];
$invoice_no     = mysqli_real_escape_string($con, $_POST['invoice_no']);
$vatRate    = mysqli_real_escape_string($con, $_POST['vatRate']);
$supplier_id    = intval($_POST['supplier_id']);
$p_id           = intval($_POST['p_id']);
$quantity       = floatval($_POST['quantity']);
$rate           = floatval($_POST['rate']);
$total_invoice  = floatval($_POST['total_invoice']);
$purchase_price = $total_invoice /$quantity;
$vat_amount     = floatval($_POST['vat_amount'] ?? 0);
$user_id        = intval($user_id); // From session
$created_at     = date('Y-m-d H:i:s');

// Validate date
$min_date = date('Y-m-d', strtotime('-100 days'));
$max_date = date('Y-m-d');
if ($purchase_date < $min_date || $purchase_date > $max_date) {
    echo json_encode(['success' => false, 'error' => 'Purchase date must be within the past 100 days or today.']);
    exit;
}

// Check for duplicate invoice
$dup_check = mysqli_query($con, "SELECT 1 FROM fuel_purchase_master WHERE location_id = $location_id AND Invoice_no = '$invoice_no'");
if (mysqli_num_rows($dup_check) > 0) {
    echo json_encode(['success' => false, 'error' => 'Invoice number already exists for this location.']);
    exit;
}

// Insert into fuel_purchase_master
$insert = mysqli_query($con, "
    INSERT INTO fuel_purchase_master 
    (location_id, inv_id, purchase_date, Invoice_no, supplier_id, p_id, quantity, rate, vat_amount, total_invoice, created_at,purchase_price)
    VALUES
    ($location_id, $inv_id, '$purchase_date', '$invoice_no', $supplier_id, $p_id, $quantity, $rate, $vat_amount, $total_invoice, '$created_at',$purchase_price)
");

if (!$insert) {
    echo json_encode(['success' => false, 'error' => 'Insert failed: ' . mysqli_error($con)]);
    exit;
}

// Insert into fuel_stock_ledger
$ledger_date_time = $purchase_date . ' 06:00:00';
$description = "Purchase: $invoice_no";
$description = mysqli_real_escape_string($con, $description);

$insert_ledger = mysqli_query($con, "
    INSERT INTO fuel_stock_ledger 
    (location_id, p_id, date_time, description, shift_id, debit, credit, user_id, status,sales_price,purchase_price)
    VALUES
    ($location_id, $p_id, '$ledger_date_time', '$description', '-1', $quantity, 0, $user_id, 1,$rate,$purchase_price)
");

if (!$insert_ledger) {
    echo json_encode(['success' => false, 'error' => 'Ledger update failed: ' . mysqli_error($con)]);
    exit;
}






// Accounting entries for the purchase
$amount = $total_invoice;
$t_date = $purchase_date;
$tra_type = 'Fuel Purchase';
$memo = "Fuel Purchase - $invoice_no";
$source = 'Fuel Purchase';
$source_id = $inv_id;
$status = 1;
$other_branch = 0;
$ref_no = $invoice_no;

// Defaults
$vat_rate = $vatRate;
$debit_vat = $vat_amount;
$credit_vat = 0;
$cus_id = 0;
$zero = 0;

// 🔁 Insert DEBIT to Fuel Inventory (ca_id = 22)
$debit_sql = "
    INSERT INTO acc_transaction 
    (location_id, ca_id, t_date, tra_type, source, source_id, ref_no,
     debit, credit, vat_rate, debit_Vat, credit_vat,
     t_memo, credted_by, created_on, cus_id, sup_id, status, other_branch)
    VALUES
    ($location_id, 22, '$t_date', '$tra_type', '$source', $source_id, '$ref_no',
     $amount, 0, $vat_rate, $debit_vat, $credit_vat,
     '" . mysqli_real_escape_string($con, $memo) . "', $user_id, '$created_at', $cus_id, $supplier_id, $status, $other_branch)
";
if (!mysqli_query($con, $debit_sql)) {
    echo json_encode(['success' => false, 'error' => 'Accounting DEBIT failed: ' . mysqli_error($con)]);
    exit;
}

// 🔁 Insert CREDIT to Trade Creditors (ca_id = 9)
$credit_sql = "
    INSERT INTO acc_transaction 
    (location_id, ca_id, t_date, tra_type, source, source_id, ref_no,
     debit, credit, vat_rate, debit_Vat, credit_vat,
     t_memo, credted_by, created_on, cus_id, sup_id, status, other_branch)
    VALUES
    ($location_id, 9, '$t_date', '$tra_type', '$source', $source_id, '$ref_no',
     0, $amount,$zero, $zero, $credit_vat,
     '" . mysqli_real_escape_string($con, $memo) . "', $user_id, '$created_at', $cus_id, $supplier_id, $status, $other_branch)
";
if (!mysqli_query($con, $credit_sql)) {
    echo json_encode(['success' => false, 'error' => 'Accounting CREDIT failed: ' . mysqli_error($con)]);
    exit;
}





// Log user action
$product_name = '';
$supplier_name = '';

$pq = mysqli_query($con, "SELECT p_name FROM product_master WHERE p_id = $p_id");
if ($pq && $pr = mysqli_fetch_assoc($pq)) {
    $product_name = $pr['p_name'];
}

$sq = mysqli_query($con, "SELECT supplier_name FROM manage_supplier WHERE sup_id = $supplier_id");
if ($sq && $sr = mysqli_fetch_assoc($sq)) {
    $supplier_name = $sr['supplier_name'];
}

$log_message = "Fuel purchase added. Invoice No: $invoice_no,  Total: " . number_format($total_invoice, 2);
UserLog(2, 'Fuel Purchase Added', $log_message);

echo json_encode(['success' => true]);
?>
