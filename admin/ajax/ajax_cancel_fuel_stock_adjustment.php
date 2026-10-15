<?php
// include '../../db.php';
// include '../../auth.php'; // $user_id

// header('Content-Type: application/json');

// if (!isset($_POST['id'])) {
//     echo json_encode(['error' => 'Invalid adjustment ID']);
//     exit;
// }

// $id = intval($_POST['id']);

// // Check if record exists and cancellation allowed (within 3 hours)
// $query = "SELECT date_time FROM fuel_stock_ledger WHERE id = ? AND status = 1";
// $stmt = $con->prepare($query);
// $stmt->bind_param("i", $id);
// $stmt->execute();
// $result = $stmt->get_result();

// if ($result->num_rows == 0) {
//     echo json_encode(['error' => 'Adjustment not found or already cancelled']);
//     exit;
// }

// $row = $result->fetch_assoc();
// $created_time = strtotime($row['date_time']);
// if (time() - $created_time > 1 * 3600) {
//     echo json_encode(['error' => 'Cancellation period expired (more than 1 hours)']);
//     exit;
// }

// // Cancel adjustment
// $update = "UPDATE fuel_stock_ledger SET status = 0 WHERE id = ?";
// $update_stmt = $con->prepare($update);
// $update_stmt->bind_param("i", $id);
// if ($update_stmt->execute()) {
//     // Optionally log cancellation here (UserLog or similar)

//     $log_message = "Fuel stock adjustment cancelled id $id ";
// UserLog(1, 'Cancelled Stock Adjustment', $log_message);


//     echo json_encode(['success' => true]);
// } else {
//     echo json_encode(['error' => 'Failed to cancel adjustment']);
// }

// $update_stmt->close();
// $stmt->close();?>
<?php
include '../../db.php';
include '../../auth.php'; // $user_id

header('Content-Type: application/json');

if (!isset($_POST['id'])) {
    echo json_encode(['error' => 'Invalid adjustment ID']);
    exit;
}

$id = intval($_POST['id']);

// Check if record exists and cancellation is allowed (within 1 hour)
$query = "SELECT date_time FROM fuel_stock_ledger WHERE id = ? AND status = 1";
$stmt = $con->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo json_encode(['error' => 'Adjustment not found or already cancelled']);
    exit;
}

$row = $result->fetch_assoc();
$created_time = strtotime($row['date_time']);
if (time() - $created_time > 1 * 3600) {
    echo json_encode(['error' => 'Cancellation period expired (more than 1 hour)']);
    exit;
}

// Cancel fuel_stock_ledger record
$update = "UPDATE fuel_stock_ledger SET status = 0 WHERE id = ?";
$update_stmt = $con->prepare($update);
$update_stmt->bind_param("i", $id);
if (!$update_stmt->execute()) {
    echo json_encode(['error' => 'Failed to cancel stock adjustment']);
    exit;
}
$update_stmt->close();

// Cancel acc_transaction records linked to this adjustment
$acc_update = "UPDATE acc_transaction SET status = 0 WHERE source = 'Stock_Adjustment' AND source_id = ?";
$acc_stmt = $con->prepare($acc_update);
$acc_stmt->bind_param("i", $id);
if (!$acc_stmt->execute()) {
    echo json_encode(['error' => 'Failed to cancel accounting entries']);
    exit;
}
$acc_stmt->close();

// Log action
$log_message = "Fuel stock adjustment cancelled (ID: $id)";
UserLog(1, 'Cancelled Stock Adjustment', $log_message);

// Done
echo json_encode(['success' => true]);
