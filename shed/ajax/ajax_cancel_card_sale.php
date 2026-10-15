 
 <?php
include '../../db.php';
include '../../auth.php';

if (!isset($_POST['sale_id']) || empty($_POST['sale_id'])) {
    echo json_encode(['error' => 'Invalid sale ID']);
    exit;
}

$sale_id = intval($_POST['sale_id']);
$user_id = $user_id; // Assuming it's set in the header

// Fetch the shift ID and details (serial number, amount) for the given card sale
$sale_query = "SELECT shift_id, serial_number, amount FROM shed_card_sales WHERE id = $sale_id";
$sale_result = mysqli_query($con, $sale_query);

if (mysqli_num_rows($sale_result) == 0) {
    echo json_encode(['error' => 'Sale not found']);
    exit;
}

$sale_row = mysqli_fetch_assoc($sale_result);
$shift_id = $sale_row['shift_id'];
$serial_number = $sale_row['serial_number'];
$amount = $sale_row['amount'];

// Check if the shift is open (open_status = 1)
$shift_check_query = "SELECT open_status  FROM shed_operator_shift WHERE shift_id = $shift_id";
$shift_check_result = mysqli_query($con, $shift_check_query);
$shift_row = mysqli_fetch_assoc($shift_check_result);

if ($shift_row['open_status'] == 0 ) {
    // If the shift is closed, prevent cancellation and return custom error message
    echo json_encode(['error' => 'The shift has been closed, and the card sale cannot be cancelled.']);
    exit;
}

// Proceed with cancelling the card sale if the shift is open
$cancel_sale = "UPDATE shed_card_sales SET status = 0 WHERE id = $sale_id";
$cancel_result = mysqli_query($con, $cancel_sale);

if ($cancel_result) {
    // Log the cancellation in the user log
    $log_message = "Card receipt cancelled. Serial Number: $serial_number, Amount: " . number_format($amount, 2);
    UserLog(2, 'Cancelled Card Sale', $log_message);

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['error' => 'Failed to cancel the sale.']);
}
?>
