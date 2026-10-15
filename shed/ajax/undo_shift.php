<?php
header('Content-Type: application/json');
require_once '../../db.php'; // adjust path if needed

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$shift_id = isset($_POST['shift_id']) ? intval($_POST['shift_id']) : 0;

if ($shift_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid shift ID']);
    exit;
}

// Check if shift exists and is closed but not day ended
$shift_q = mysqli_query($con, "SELECT * FROM shed_operator_shift WHERE shift_id = $shift_id");
$shift = mysqli_fetch_assoc($shift_q);

if (!$shift) {
    echo json_encode(['status' => 'error', 'message' => 'Shift not found']);
    exit;
}

if ($shift['open_status'] != 0 || $shift['day_end'] != 0) {
    echo json_encode(['status' => 'error', 'message' => 'Only closed shifts pending day end can be undone']);
    exit;
}

// Undo: set open_status=1, clear end_time, and update status if needed
$update = mysqli_query($con, "UPDATE shed_operator_shift SET open_status = 1, end_time = NULL WHERE shift_id = $shift_id");

// Set open_stats = 1 for all pump assignments of this shift
$update_pump_assign = mysqli_query($con, "UPDATE shed_pump_assign SET open_stats = 1 WHERE shift_id = $shift_id");

// Delete related acc_transaction and fuel_stock_ledger entries for this shift
// Adjust the WHERE clause if your schema uses a different reference
$delete_acc = mysqli_query($con, "DELETE FROM acc_transaction WHERE source='Shift' AND source_id = $shift_id");
$delete_fuel = mysqli_query($con, "DELETE FROM fuel_stock_ledger WHERE shift_id = $shift_id");

if ($update) {
    echo json_encode(['status' => 'success', 'message' => 'Shift successfully undone.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Database error. Could not undo shift.']);
}
?>