<?php
include '../../db.php';
include '../../auth.php'; 

header('Content-Type: application/json');

// Error handling
if (!$con) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$serial_no = $_GET['serial_no'] ?? null;
$location_id = $_GET['location_id'] ?? 0;

if (!$serial_no) {
    echo json_encode(['success' => false, 'message' => 'Serial number is required']);
    exit;
}

try {
    // Get day end details
    $sql_day_end = "SELECT * FROM day_end_process WHERE serial_no = '$serial_no' AND location_id = '$location_id' ORDER BY serial_no DESC LIMIT 1";
    $result_day_end = mysqli_query($con, $sql_day_end);

    if (!$result_day_end) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($con)]);
        exit;
    }

    if (mysqli_num_rows($result_day_end) == 0) {
        echo json_encode(['success' => false, 'message' => 'Day end record not found']);
        exit;
    }

    $day_end_record = mysqli_fetch_assoc($result_day_end);

    // Check if this is the last day end (only allow undo for the last one)
    $sql_check_last = "SELECT serial_no FROM day_end_process WHERE location_id = '$location_id' ORDER BY serial_no DESC LIMIT 1";
    $result_check = mysqli_query($con, $sql_check_last);
    
    if (!$result_check) {
        echo json_encode(['success' => false, 'message' => 'Database error checking last record: ' . mysqli_error($con)]);
        exit;
    }
    
    $last_record = mysqli_fetch_assoc($result_check);

    if ($last_record['serial_no'] != $serial_no) {
        echo json_encode(['success' => false, 'message' => 'Only the last day end can be undone']);
        exit;
    }

    // Count affected transactions

    // 1. Shifts with pump records
    $sql_shifts = "SELECT COUNT(*) as count FROM shed_operator_shift WHERE day_end = '$serial_no' AND location_id = '$location_id'";
    $result_shifts = mysqli_query($con, $sql_shifts);
    $shifts_count = ($result_shifts) ? mysqli_fetch_assoc($result_shifts)['count'] : 0;

    // Get pump records count for those shifts
    // $sql_pump_records = "
    //     SELECT COUNT(*) as count 
    //     FROM pump_records pr 
    //     INNER JOIN shed_operator_shift sos ON pr.shift_id = sos.shift_id 
    //     WHERE sos.day_end = '$serial_no' AND sos.location_id = '$location_id'
    // ";
      $sql_pump_records = "
        SELECT COUNT(*) as count 
        FROM shed_pump_assign pr 
        INNER JOIN shed_operator_shift sos ON pr.shift_id = sos.shift_id 
        WHERE sos.day_end = '$serial_no' AND sos.location_id = '$location_id'
    ";
    $result_pump = mysqli_query($con, $sql_pump_records);
    $pump_records_count = ($result_pump) ? mysqli_fetch_assoc($result_pump)['count'] : 0;

    // Get pump assign count for those shifts
    $sql_pump_assign = "
        SELECT COUNT(*) as count 
        FROM shed_pump_assign spa 
        INNER JOIN shed_operator_shift sos ON spa.shift_id = sos.shift_id 
        WHERE sos.day_end = '$serial_no' AND sos.location_id = '$location_id'
    ";
    $result_pump_assign = mysqli_query($con, $sql_pump_assign);
    $pump_assign_count = ($result_pump_assign) ? mysqli_fetch_assoc($result_pump_assign)['count'] : 0;

    // Get fuel pump test count for those pump assignments
    $sql_fuel_pump_test = "
        SELECT COUNT(*) as count 
        FROM shed_fuel_pump_test sfpt
        INNER JOIN shed_pump_assign spa ON sfpt.pump_assign_id = spa.id
        INNER JOIN shed_operator_shift sos ON spa.shift_id = sos.shift_id 
        WHERE sos.day_end = '$serial_no' AND sos.location_id = '$location_id'
    ";
    $result_fuel_pump_test = mysqli_query($con, $sql_fuel_pump_test);
    $fuel_pump_test_count = ($result_fuel_pump_test) ? mysqli_fetch_assoc($result_fuel_pump_test)['count'] : 0;

    // Get fuel stock ledger count for those shifts
    $sql_fuel_stock_ledger = "
        SELECT COUNT(*) as count 
        FROM fuel_stock_ledger 
        WHERE shift_id IN (SELECT shift_id FROM shed_operator_shift WHERE day_end = '$serial_no' AND location_id = '$location_id')
    ";
    $result_fuel_stock_ledger = mysqli_query($con, $sql_fuel_stock_ledger);
    $fuel_stock_ledger_count = ($result_fuel_stock_ledger) ? mysqli_fetch_assoc($result_fuel_stock_ledger)['count'] : 0;

    // 2. Card Sales
    $sql_card_sales = "SELECT COUNT(*) as count FROM shed_card_sales WHERE day_end = '$serial_no'";
    $result_card_sales = mysqli_query($con, $sql_card_sales);
    $card_sales_count = ($result_card_sales) ? mysqli_fetch_assoc($result_card_sales)['count'] : 0;

    // 3. Credit Sales
    $sql_credit_sales = "SELECT COUNT(*) as count FROM shed_credit_sales WHERE day_end = '$serial_no'";
    $result_credit_sales = mysqli_query($con, $sql_credit_sales);
    $credit_sales_count = ($result_credit_sales) ? mysqli_fetch_assoc($result_credit_sales)['count'] : 0;

    // 4. Fuel Purchase
    $sql_fuel_purchase = "SELECT COUNT(*) as count FROM fuel_purchase_master WHERE day_end = '$serial_no' AND location_id = '$location_id'";
    $result_fuel_purchase = mysqli_query($con, $sql_fuel_purchase);
    $fuel_purchase_count = ($result_fuel_purchase) ? mysqli_fetch_assoc($result_fuel_purchase)['count'] : 0;

    // 5. Oil Purchase (GRN)
    $sql_oil_purchase = "SELECT COUNT(*) as count FROM oil_grn_master WHERE day_end = '$serial_no' AND location = '$location_id'";
    $result_oil_purchase = mysqli_query($con, $sql_oil_purchase);
    $oil_purchase_count = ($result_oil_purchase) ? mysqli_fetch_assoc($result_oil_purchase)['count'] : 0;

    // 6. Oil Sales
    $sql_oil_sales = "SELECT COUNT(*) as count FROM oil_sales_master WHERE day_end = '$serial_no' AND location = '$location_id'";
    $result_oil_sales = mysqli_query($con, $sql_oil_sales);
    $oil_sales_count = ($result_oil_sales) ? mysqli_fetch_assoc($result_oil_sales)['count'] : 0;

    // Check for any transactions after this day end date
    $day_end_date = $day_end_record['date_ended'];

    // Count future transactions
    $sql_future_shifts = "
        SELECT COUNT(*) as count 
        FROM shed_operator_shift 
        WHERE location_id = '$location_id' 
        AND DATE(start_time) > '$day_end_date' 
        AND day_end = 0
    ";
    $result_future_shifts = mysqli_query($con, $sql_future_shifts);
    $future_shifts_count = ($result_future_shifts) ? mysqli_fetch_assoc($result_future_shifts)['count'] : 0;

    // Count future pump assigns that will be deleted
    $sql_future_pump_assign = "
        SELECT COUNT(*) as count 
        FROM shed_pump_assign spa
        INNER JOIN shed_operator_shift sos ON spa.shift_id = sos.shift_id
        WHERE sos.location_id = '$location_id' 
        AND DATE(sos.start_time) > '$day_end_date' 
        AND sos.day_end = 0
    ";
    $result_future_pump_assign = mysqli_query($con, $sql_future_pump_assign);
    $future_pump_assign_count = ($result_future_pump_assign) ? mysqli_fetch_assoc($result_future_pump_assign)['count'] : 0;

    // Count future fuel pump tests that will be deleted
    $sql_future_fuel_pump_test = "
        SELECT COUNT(*) as count 
        FROM shed_fuel_pump_test sfpt
        INNER JOIN shed_pump_assign spa ON sfpt.pump_assign_id = spa.id
        INNER JOIN shed_operator_shift sos ON spa.shift_id = sos.shift_id
        WHERE sos.location_id = '$location_id' 
        AND DATE(sos.start_time) > '$day_end_date' 
        AND sos.day_end = 0
    ";
    $result_future_fuel_pump_test = mysqli_query($con, $sql_future_fuel_pump_test);
    $future_fuel_pump_test_count = ($result_future_fuel_pump_test) ? mysqli_fetch_assoc($result_future_fuel_pump_test)['count'] : 0;

    // Count future fuel stock ledger entries that will be deleted
    $sql_future_fuel_stock_ledger = "
        SELECT COUNT(*) as count 
        FROM fuel_stock_ledger fsl
        INNER JOIN shed_operator_shift sos ON fsl.shift_id = sos.shift_id
        WHERE sos.location_id = '$location_id' 
        AND DATE(sos.start_time) > '$day_end_date' 
        AND sos.day_end = 0
    ";
    $result_future_fuel_stock_ledger = mysqli_query($con, $sql_future_fuel_stock_ledger);
    $future_fuel_stock_ledger_count = ($result_future_fuel_stock_ledger) ? mysqli_fetch_assoc($result_future_fuel_stock_ledger)['count'] : 0;

    // Count future fuel_stock_ledger entries with shift_id = '-1'
    $sql_future_fuel_ledger_neg1 = "
        SELECT COUNT(*) as count 
        FROM fuel_stock_ledger 
        WHERE location_id = '$location_id' 
        AND DATE(date_time) > '$day_end_date' 
        AND shift_id = '-1'
    ";
    $result_future_fuel_ledger_neg1 = mysqli_query($con, $sql_future_fuel_ledger_neg1);
    $future_fuel_ledger_neg1_count = ($result_future_fuel_ledger_neg1) ? mysqli_fetch_assoc($result_future_fuel_ledger_neg1)['count'] : 0;

    // Add to total future fuel stock ledger count
    $future_fuel_stock_ledger_count += $future_fuel_ledger_neg1_count;

    $sql_future_fuel_purchase = "
        SELECT COUNT(*) as count 
        FROM fuel_purchase_master 
        WHERE location_id = '$location_id' 
        AND purchase_date > '$day_end_date' 
        AND day_end = 0
    ";
    $result_future_fuel = mysqli_query($con, $sql_future_fuel_purchase);
    $future_fuel_count = ($result_future_fuel) ? mysqli_fetch_assoc($result_future_fuel)['count'] : 0;

    $sql_future_oil_purchase = "
        SELECT COUNT(*) as count 
        FROM oil_grn_master 
        WHERE location = '$location_id' 
        AND grn_date > '$day_end_date' 
        AND day_end = 0
    ";
    $result_future_oil_grn = mysqli_query($con, $sql_future_oil_purchase);
    $future_oil_grn_count = ($result_future_oil_grn) ? mysqli_fetch_assoc($result_future_oil_grn)['count'] : 0;

    $sql_future_oil_sales = "
        SELECT COUNT(*) as count 
        FROM oil_sales_master 
        WHERE location = '$location_id' 
        AND issue_date > '$day_end_date' 
        AND day_end = 0
    ";
    $result_future_oil_sales = mysqli_query($con, $sql_future_oil_sales);
    $future_oil_sales_count = ($result_future_oil_sales) ? mysqli_fetch_assoc($result_future_oil_sales)['count'] : 0;

    $sql_future_card_sales = "
        SELECT COUNT(*) as count 
        FROM shed_card_sales 
        WHERE DATE(created_on) > '$day_end_date' 
        AND day_end = 0
        AND status = 1
    ";
    $result_future_card_sales = mysqli_query($con, $sql_future_card_sales);
    $future_card_sales_count = ($result_future_card_sales) ? mysqli_fetch_assoc($result_future_card_sales)['count'] : 0;

    $sql_future_credit_sales = "
        SELECT COUNT(*) as count 
        FROM shed_credit_sales 
        WHERE DATE(date_time) > '$day_end_date' 
        AND day_end = 0
        AND status = 1
    ";
    $result_future_credit_sales = mysqli_query($con, $sql_future_credit_sales);
    $future_credit_sales_count = ($result_future_credit_sales) ? mysqli_fetch_assoc($result_future_credit_sales)['count'] : 0;

    $response = [
        'success' => true,
        'day_end_info' => [
            'serial_no' => $day_end_record['serial_no'],
            'date_ended' => $day_end_record['date_ended'],
            'fuel_sales' => $day_end_record['fuel_sales'],
            'oil_sales' => $day_end_record['oil_sales'],
            'total_cash_settled' => $day_end_record['total_cash_settled'],
            'total_card_settled' => $day_end_record['total_card_settled'],
            'total_credit_settle' => $day_end_record['total_credit_settle']
        ],
        'affected_transactions' => [
            'shifts' => $shifts_count,
            'pump_records' => $pump_records_count,
            'pump_assign' => $pump_assign_count,
            'fuel_pump_test' => $fuel_pump_test_count,
            'fuel_stock_ledger' => $fuel_stock_ledger_count,
            'card_sales' => $card_sales_count,
            'credit_sales' => $credit_sales_count,
            'fuel_purchase' => $fuel_purchase_count,
            'oil_purchase' => $oil_purchase_count,
            'oil_sales' => $oil_sales_count
        ],
        'future_transactions' => [
            'shifts' => $future_shifts_count,
            'pump_assign' => $future_pump_assign_count,
            'fuel_pump_test' => $future_fuel_pump_test_count,
            'fuel_stock_ledger' => $future_fuel_stock_ledger_count,
            'fuel_purchase' => $future_fuel_count,
            'oil_purchase' => $future_oil_grn_count,
            'oil_sales' => $future_oil_sales_count,
            'card_sales' => $future_card_sales_count,
            'credit_sales' => $future_credit_sales_count
        ]
    ];

    echo json_encode($response);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>