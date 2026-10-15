<?php
include '../../db.php';
include '../../auth.php'; 

header('Content-Type: application/json');

$serial_no = $_POST['serial_no'] ?? null;
$location_id = $_POST['location_id'] ?? 0;

if (!$serial_no) {
    echo json_encode(['success' => false, 'message' => 'Serial number is required']);
    exit;
}

// Verify this is the last day end
$sql_check_last = "SELECT serial_no FROM day_end_process WHERE location_id = '$location_id' ORDER BY serial_no DESC LIMIT 1";
$result_check = mysqli_query($con, $sql_check_last);
$last_record = mysqli_fetch_assoc($result_check);

if ($last_record['serial_no'] != $serial_no) {
    echo json_encode(['success' => false, 'message' => 'Only the last day end can be undone']);
    exit;
}

// Start transaction
mysqli_autocommit($con, false);

try {
    // Get day end date for future transaction deletions
    $sql_get_date = "SELECT date_ended FROM day_end_process WHERE serial_no = '$serial_no' AND location_id = '$location_id'";
    $result_date = mysqli_query($con, $sql_get_date);
    $day_end_record = mysqli_fetch_assoc($result_date);
    $day_end_date = $day_end_record['date_ended'];

    // 1. Delete future transactions (dated after day end date with day_end = 0)
    
    // 1.1 Get future shift_ids for cascading deletions
    $sql_future_shifts = "SELECT shift_id FROM shed_operator_shift 
                         WHERE location_id = '$location_id' 
                         AND DATE(start_time) > '$day_end_date' 
                         AND day_end = 0";
    $result_future_shifts = mysqli_query($con, $sql_future_shifts);
    $future_shift_ids = [];
    while ($row = mysqli_fetch_assoc($result_future_shifts)) {
        $future_shift_ids[] = $row['shift_id'];
    }

    // 1.2 Get pump_assign_ids for those future shifts
    $pump_assign_ids = [];
    if (!empty($future_shift_ids)) {
        $shift_ids_str = implode(',', $future_shift_ids);
        $sql_pump_assigns = "SELECT id FROM shed_pump_assign WHERE shift_id IN ($shift_ids_str)";
        $result_pump_assigns = mysqli_query($con, $sql_pump_assigns);
        while ($row = mysqli_fetch_assoc($result_pump_assigns)) {
            $pump_assign_ids[] = $row['id'];
        }
    }

    // 1.3 Delete shed_fuel_pump_test records for those pump_assign_ids
    if (!empty($pump_assign_ids)) {
        $pump_assign_ids_str = implode(',', $pump_assign_ids);
        $sql_delete_pump_tests = "DELETE FROM shed_fuel_pump_test WHERE pump_assign_id IN ($pump_assign_ids_str)";
        if (!mysqli_query($con, $sql_delete_pump_tests)) {
            throw new Exception('Failed to delete fuel pump tests: ' . mysqli_error($con));
        }
    }

    // 1.4 Delete shed_pump_assign records for future shifts
    if (!empty($future_shift_ids)) {
        $shift_ids_str = implode(',', $future_shift_ids);
        $sql_delete_pump_assigns = "DELETE FROM shed_pump_assign WHERE shift_id IN ($shift_ids_str)";
        if (!mysqli_query($con, $sql_delete_pump_assigns)) {
            throw new Exception('Failed to delete pump assignments: ' . mysqli_error($con));
        }
    }

    // 1.5 Delete fuel_stock_ledger records for future shifts
    if (!empty($future_shift_ids)) {
        $shift_ids_str = implode(',', $future_shift_ids);
        $sql_delete_fuel_ledger = "DELETE FROM fuel_stock_ledger WHERE shift_id IN ($shift_ids_str)";
        if (!mysqli_query($con, $sql_delete_fuel_ledger)) {
            throw new Exception('Failed to delete fuel stock ledger: ' . mysqli_error($con));
        }
    }

    // 1.6 Delete future shifts
    if (!empty($future_shift_ids)) {
        $shift_ids_str = implode(',', $future_shift_ids);
        $sql_delete_future_shifts = "DELETE FROM shed_operator_shift WHERE shift_id IN ($shift_ids_str)";
        if (!mysqli_query($con, $sql_delete_future_shifts)) {
            throw new Exception('Failed to delete future shifts: ' . mysqli_error($con));
        }
    }

    // 1.7 Delete future fuel purchases and their stock ledger entries
    $sql_get_future_fuel_purchases = "SELECT pur_id FROM fuel_purchase_master 
                                     WHERE location_id = '$location_id' 
                                     AND purchase_date > '$day_end_date' 
                                     AND day_end = 0";
    $result_future_fuel = mysqli_query($con, $sql_get_future_fuel_purchases);
    $future_fuel_purchase_ids = [];
    while ($row = mysqli_fetch_assoc($result_future_fuel)) {
        $future_fuel_purchase_ids[] = $row['pur_id'];
    }

    if (!empty($future_fuel_purchase_ids)) {
        $fuel_purchase_ids_str = implode(',', $future_fuel_purchase_ids);
        
        // Delete fuel stock ledger entries for these purchases
        $sql_delete_fuel_purchase_ledger = "DELETE FROM fuel_stock_ledger 
                                           WHERE description LIKE '%Purchase%' 
                                           AND location_id = '$location_id' 
                                           AND shift_id IN (SELECT pur_id FROM fuel_purchase_master WHERE pur_id IN ($fuel_purchase_ids_str))";
        if (!mysqli_query($con, $sql_delete_fuel_purchase_ledger)) {
            throw new Exception('Failed to delete fuel purchase ledger entries: ' . mysqli_error($con));
        }

        // Delete the fuel purchases
        $sql_delete_future_fuel_purchases = "DELETE FROM fuel_purchase_master WHERE pur_id IN ($fuel_purchase_ids_str)";
        if (!mysqli_query($con, $sql_delete_future_fuel_purchases)) {
            throw new Exception('Failed to delete future fuel purchases: ' . mysqli_error($con));
        }
    }

    // 1.8 Delete fuel_stock_ledger entries with shift_id = '-1' after day end date
    $sql_delete_fuel_ledger_neg1 = "DELETE FROM fuel_stock_ledger 
                                    WHERE location_id = '$location_id' 
                                    AND DATE(date_time) > '$day_end_date' 
                                    AND shift_id = '-1'";
    if (!mysqli_query($con, $sql_delete_fuel_ledger_neg1)) {
        throw new Exception('Failed to delete fuel stock ledger entries with shift_id = -1: ' . mysqli_error($con));
    }

    // 1.9 Reverse future oil sales (similar to delete_sales.php logic)
    $sql_get_future_oil_sales = "SELECT iss_id FROM oil_sales_master 
                                WHERE location = '$location_id' 
                                AND issue_date > '$day_end_date' 
                                AND day_end = 0 
                                AND issue_status = 1";
    $result_future_oil_sales = mysqli_query($con, $sql_get_future_oil_sales);
    
    while ($oil_row = mysqli_fetch_assoc($result_future_oil_sales)) {
        $iss_id = $oil_row['iss_id'];
        
        // Update the issue_status in oil_sales_master
        $update_oil_master = "UPDATE oil_sales_master SET issue_status = 0 WHERE iss_id = '$iss_id'";
        if (!mysqli_query($con, $update_oil_master)) {
            throw new Exception('Failed to cancel oil sales master: ' . mysqli_error($con));
        }

        // Update the status in oil_sales_detail
        $update_oil_detail = "UPDATE oil_sales_detail SET status = 0 WHERE iss_id = '$iss_id'";
        if (!mysqli_query($con, $update_oil_detail)) {
            throw new Exception('Failed to cancel oil sales detail: ' . mysqli_error($con));
        }

        // Reverse the oil stock
        $select_oil_detail = "SELECT s_id, batch_id, issue_qty FROM oil_sales_detail WHERE iss_id = '$iss_id'";
        $result_oil_detail = mysqli_query($con, $select_oil_detail);

        while ($oil_detail_row = mysqli_fetch_assoc($result_oil_detail)) {
            $s_id = $oil_detail_row['s_id'];
            $batch_id = $oil_detail_row['batch_id'];
            $issue_qty = $oil_detail_row['issue_qty'];

            $update_oil_stock = "UPDATE oil_stock SET 
                               transfered_qty = transfered_qty - '$issue_qty', 
                               open_status = 1 
                               WHERE batch_id = '$batch_id'";
            if (!mysqli_query($con, $update_oil_stock)) {
                throw new Exception('Failed to reverse oil stock: ' . mysqli_error($con));
            }
        }
    }

    // 1.10 Delete future oil purchases
    $sql_delete_future_oil_purchases = "DELETE FROM oil_grn_master 
                                       WHERE location = '$location_id' 
                                       AND grn_date > '$day_end_date' 
                                       AND day_end = 0";
    if (!mysqli_query($con, $sql_delete_future_oil_purchases)) {
        throw new Exception('Failed to delete future oil purchases: ' . mysqli_error($con));
    }

    // 1.11 Delete future card sales
    $sql_delete_future_card_sales = "DELETE FROM shed_card_sales 
                                    WHERE DATE(created_on) > '$day_end_date' 
                                    AND day_end = 0";
    if (!mysqli_query($con, $sql_delete_future_card_sales)) {
        throw new Exception('Failed to delete future card sales: ' . mysqli_error($con));
    }

    // 1.12 Delete future credit sales
    $sql_delete_future_credit_sales = "DELETE FROM shed_credit_sales 
                                      WHERE DATE(date_time) > '$day_end_date' 
                                      AND day_end = 0";
    if (!mysqli_query($con, $sql_delete_future_credit_sales)) {
        throw new Exception('Failed to delete future credit sales: ' . mysqli_error($con));
    }

    // 2. Now reset day_end = 0 for transactions that were part of this day end
    
    // 2.1 Reset shifts day_end to 0
    $sql_reset_shifts = "UPDATE shed_operator_shift SET day_end = 0 WHERE day_end = '$serial_no' AND location_id = '$location_id'";
    if (!mysqli_query($con, $sql_reset_shifts)) {
        throw new Exception('Failed to reset shifts: ' . mysqli_error($con));
    }

    // 2.2 Reset card sales day_end to 0
    $sql_reset_card_sales = "UPDATE shed_card_sales SET day_end = 0 WHERE day_end = '$serial_no'";
    if (!mysqli_query($con, $sql_reset_card_sales)) {
        throw new Exception('Failed to reset card sales: ' . mysqli_error($con));
    }

    // 2.3 Reset credit sales day_end to 0
    $sql_reset_credit_sales = "UPDATE shed_credit_sales SET day_end = 0 WHERE day_end = '$serial_no'";
    if (!mysqli_query($con, $sql_reset_credit_sales)) {
        throw new Exception('Failed to reset credit sales: ' . mysqli_error($con));
    }

    // 2.4 Reset fuel purchase day_end to 0 and remove their stock ledger entries
    $sql_get_fuel_purchases = "SELECT pur_id FROM fuel_purchase_master WHERE day_end = '$serial_no' AND location_id = '$location_id'";
    $result_fuel_purchases = mysqli_query($con, $sql_get_fuel_purchases);
    $fuel_purchase_ids = [];
    while ($row = mysqli_fetch_assoc($result_fuel_purchases)) {
        $fuel_purchase_ids[] = $row['pur_id'];
    }

    if (!empty($fuel_purchase_ids)) {
        $fuel_purchase_ids_str = implode(',', $fuel_purchase_ids);
        
        // Delete fuel stock ledger entries for these purchases
        $sql_delete_fuel_purchase_ledger_entries = "DELETE FROM fuel_stock_ledger 
                                          WHERE description LIKE '%Purchase%' 
                                          AND location_id = '$location_id' 
                                          AND shift_id IN ($fuel_purchase_ids_str)";
        if (!mysqli_query($con, $sql_delete_fuel_purchase_ledger_entries)) {
            throw new Exception('Failed to delete fuel purchase ledger entries: ' . mysqli_error($con));
        }
    }

    // 3.1 Delete fuel_stock_ledger entries with shift_id = '-1' for day end transactions
    $sql_delete_day_end_fuel_ledger_neg1 = "DELETE FROM fuel_stock_ledger 
                                            WHERE location_id = '$location_id' 
                                            AND shift_id = '-1'
                                            AND DATE(date_time) <= '$day_end_date'
                                            AND description LIKE '%Day End%'";
    if (!mysqli_query($con, $sql_delete_day_end_fuel_ledger_neg1)) {
        throw new Exception('Failed to delete day end fuel stock ledger entries with shift_id = -1: ' . mysqli_error($con));
    }

    $sql_reset_fuel_purchase = "UPDATE fuel_purchase_master SET day_end = 0 WHERE day_end = '$serial_no' AND location_id = '$location_id'";
    if (!mysqli_query($con, $sql_reset_fuel_purchase)) {
        throw new Exception('Failed to reset fuel purchase: ' . mysqli_error($con));
    }

    // 2.5 Reset oil purchase (GRN) day_end to 0
    $sql_reset_oil_purchase = "UPDATE oil_grn_master SET day_end = 0 WHERE day_end = '$serial_no' AND location = '$location_id'";
    if (!mysqli_query($con, $sql_reset_oil_purchase)) {
        throw new Exception('Failed to reset oil purchase: ' . mysqli_error($con));
    }

    // 2.6 Reset oil sales day_end to 0
    $sql_reset_oil_sales = "UPDATE oil_sales_master SET day_end = 0 WHERE day_end = '$serial_no' AND location = '$location_id'";
    if (!mysqli_query($con, $sql_reset_oil_sales)) {
        throw new Exception('Failed to reset oil sales: ' . mysqli_error($con));
    }

    // 3. Delete the day end process record
    $sql_delete_day_end = "DELETE FROM day_end_process WHERE serial_no = '$serial_no' AND location_id = '$location_id'";
    if (!mysqli_query($con, $sql_delete_day_end)) {
        throw new Exception('Failed to delete day end record: ' . mysqli_error($con));
    }

    // Commit transaction
    mysqli_commit($con);
    
    // Log the day end undo action
    $log_message = "Day end undone. Serial No: $serial_no, Location ID: $location_id, Date: $day_end_date";
    UserLog(2, 'Day End Undo', $log_message);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Day end has been successfully undone. All transactions have been reset.'
    ]);

} catch (Exception $e) {
    // Rollback transaction
    mysqli_rollback($con);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// Restore autocommit
mysqli_autocommit($con, true);
?>