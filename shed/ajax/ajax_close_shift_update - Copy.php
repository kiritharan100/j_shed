<?php
include '../../db.php';

if (isset($_POST['close_shift']) && $_POST['close_shift'] == 1) {
    $shift_id = intval($_POST['shift_id']);
    // $end_time = $_POST['end_time'];
        $end_time_input = $_POST['end_time']; // HH:mm
    $end_time = date('Y-m-d') . ' ' . $end_time_input . ':00';
    $cash_collection = floatval($_POST['cash_collection']);
    $total_card_sales = floatval($_POST['card_collection']);
    $total_credit_sales = floatval($_POST['credit_collection']);
    $excess_short = floatval($_POST['excess_short']);
    $pump_ids = $_POST['pump_ids'];
    $closing_readings = $_POST['closing_readings'];

    // Update shift end time and open status
    $update_shift_query = "
        UPDATE shed_operator_shift
        SET end_time = '$end_time', open_status = 0
        WHERE shift_id = $shift_id
    ";
    if (!mysqli_query($con, $update_shift_query)) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update shift']);
        exit;
    }

    // Update pump closing readings and open stats
    foreach ($pump_ids as $index => $pump_assign_id) {
        $closing_reading = floatval($closing_readings[$index]);
        $update_pump_query = "
            UPDATE shed_pump_assign
            SET closing_reading = $closing_reading, open_stats = 0
            WHERE id = $pump_assign_id
        ";
        if (!mysqli_query($con, $update_pump_query)) {
            echo json_encode(['status' => 'error', 'message' => 'Failed to update pump details']);
            exit;
        }
    }


 $total_sales = floatval($_POST['total_sales_calculated']); 
    // Update shift financials (total sales, card sales, credit sales, cash received, excess/short)
    

    $update_financials_query = "
        UPDATE shed_operator_shift
        SET 
            total_sales = $total_sales,
            total_card_sales = $total_card_sales,
            total_credit_sales = $total_credit_sales,
            cash_received = $cash_collection,
            exces_short = $excess_short
        WHERE shift_id = $shift_id
    ";
    if (!mysqli_query($con, $update_financials_query)) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update shift financials']);
        exit;
    }

    // Success
    echo json_encode(['status' => 'success', 'message' => 'Shift closed successfully']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
}
?>
