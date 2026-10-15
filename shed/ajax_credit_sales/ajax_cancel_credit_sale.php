 <?php
 
include '../../db.php';
include '../../auth.php'; // To get the $user_id

 

// Check if sale_id is provided
if (!isset($_POST['sale_id']) || empty($_POST['sale_id'])) {
    echo json_encode(['error' => 'Invalid sale ID']);
    exit;
}

$sale_id = intval($_POST['sale_id']);
$user_id = $user_id; // Assuming it's set in the header

// Fetch the shift ID and details (vehicle_no, total_sales) for the given credit sale
$sale_query = "SELECT shift_id, vehicle_no, total_sales FROM shed_credit_sales WHERE cs_id = $sale_id and status = 1";
$sale_result = mysqli_query($con, $sale_query);

if (mysqli_num_rows($sale_result) == 0) {
    echo json_encode(['error' => 'Credit sale not found']);
    exit;
}

$sale_row = mysqli_fetch_assoc($sale_result);
$shift_id = $sale_row['shift_id'];
$vehicle_no = $sale_row['vehicle_no'];
$total_sales = $sale_row['total_sales'];

// Check if the shift is open (open_status = 1)
$shift_check_query = "SELECT open_status FROM shed_operator_shift WHERE shift_id = $shift_id";
$shift_check_result = mysqli_query($con, $shift_check_query);
$shift_row = mysqli_fetch_assoc($shift_check_result);

if ($shift_row['open_status'] == 0) {
    // If the shift is closed, prevent cancellation and return a custom error message
    echo json_encode(['error' => 'The shift has been closed, and the credit sale cannot be cancelled.']);
    exit;
}

// Proceed with cancelling the credit sale if the shift is open
$cancel_sale = "UPDATE shed_credit_sales SET status = 0 WHERE cs_id = $sale_id";
$cancel_result = mysqli_query($con, $cancel_sale);

$cancel_sale = "UPDATE shed_credit_sales_detail SET status = 0 WHERE cs_id = $sale_id";
$cancel_result = mysqli_query($con, $cancel_sale);



if ($cancel_result) {
    // Log the cancellation in the user log
    $log_message = "Credit sale cancelled. Vehicle No: $vehicle_no, Total Sales: " . number_format($total_sales, 2);
    UserLog(2, 'Cancelled Credit Sale', $log_message);

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['error' => 'Failed to cancel the credit sale.']);
}
?>
