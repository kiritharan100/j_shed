

<?php
include '../../db.php';
include '../../auth.php';

header('Content-Type: application/json');

if (!isset($_POST['pur_id']) || empty($_POST['pur_id'])) {
    echo json_encode(['success' => false, 'error' => 'Missing purchase ID']);
    exit;
}

$pur_id = intval($_POST['pur_id']);

// Fetch purchase details
$query = mysqli_query($con, "
    SELECT pur_id, created_at, Invoice_no, total_invoice, location_id, p_id, quantity,purchase_date
    FROM fuel_purchase_master
    WHERE pur_id = $pur_id AND status = 1 AND day_end = 0
");

if (!$query || mysqli_num_rows($query) == 0) {
    echo json_encode(['success' => false, 'error' => 'Day end processed or Purchase not found or already cancelled']);
    exit;
}

$purchase = mysqli_fetch_assoc($query);

$created_at = strtotime($purchase['created_at']);
$current_time = time();

if (($current_time - $created_at) > 43200) { // 12 hours
    echo json_encode(['success' => false, 'error' => 'Cannot cancel after 12 hours']);
    exit;
}

// Begin soft delete
$update = mysqli_query($con, "
    UPDATE fuel_purchase_master
    SET status = 0
    WHERE pur_id = $pur_id AND day_end = 0
");

if (!$update) {
    echo json_encode(['success' => false, 'error' => 'Database update failed']);
    exit;
}



// Cancel acc_transaction records linked to this purchase
$cancel_acc = mysqli_query($con, "
    UPDATE acc_transaction
    SET status = 0
    WHERE source = 'Fuel Purchase' AND source_id = $pur_id
");

if (!$cancel_acc) {
    echo json_encode(['success' => false, 'error' => 'Failed to cancel accounting entries: ' . mysqli_error($con)]);
    exit;
}

// Insert reversal into fuel_stock_ledger
$location_id = intval($purchase['location_id']);
$p_id = intval($purchase['p_id']);
$quantity = floatval($purchase['quantity']);
$invoice_no = $purchase['Invoice_no'];
$credit = $quantity;
$debit = 0;
$shift_id = 0; // since this is not shift-related
$status = 1;
$date_time = $purchase['purchase_date']." 23:59:50";  
$description = "Purchase Reversal: Inv:$invoice_no";
$description = mysqli_real_escape_string($con, $description);

$ledger_insert = mysqli_query($con, "
    INSERT INTO fuel_stock_ledger
    (location_id, p_id, date_time, description, shift_id, debit, credit, user_id, status)
    VALUES
    ($location_id, $p_id, '$date_time', '$description', $shift_id, $debit, $credit, $user_id, $status)
");

if (!$ledger_insert) {
    echo json_encode(['success' => false, 'error' => 'Ledger insert failed: ' . mysqli_error($con)]);
    exit;
}

// Log the cancellation
$amount = floatval($purchase['total_invoice']);
$log_message = "Fuel purchase cancelled. Invoice No: $invoice_no, Qty: $quantity, Amount: " . number_format($amount, 2);
UserLog(2, 'Cancelled Fuel Purchase', $log_message); // Log type 2 = critical/transactional

echo json_encode(['success' => true, 'message' => 'Purchase cancelled and ledger updated successfully']);
?>
