<?php
header('Content-Type: application/json');
include '../../db.php';
include '../../auth.php';


 function get_fifo_purchase_price($con, $p_id, $location_id, $sales_qty) {
    // Step 1: Calculate total available stock
    $stock_sql = "
        SELECT SUM(debit) - SUM(credit) AS available_stock
        FROM fuel_stock_ledger
        WHERE location_id = ? AND p_id = ? AND status = 1
    ";
    $stmt_stock = $con->prepare($stock_sql);
    $stmt_stock->bind_param("ii", $location_id, $p_id);
    $stmt_stock->execute();
    $result_stock = $stmt_stock->get_result();
    $available_row = $result_stock->fetch_assoc();
    $available_stock = floatval($available_row['available_stock']);
    $stmt_stock->close();

    if ($available_stock <= 0) return 0;

    if ($sales_qty > $available_stock) {
        $sales_qty = $available_stock;
    }

    // Step 2: Calculate total credited (sold) quantity
    $credit_sql = "
        SELECT SUM(credit) AS total_credit
        FROM fuel_stock_ledger
        WHERE location_id = ? AND p_id = ? AND status = 1 AND credit > 0
    ";
    $stmt_credit = $con->prepare($credit_sql);
    $stmt_credit->bind_param("ii", $location_id, $p_id);
    $stmt_credit->execute();
    $result_credit = $stmt_credit->get_result();
    $credit_row = $result_credit->fetch_assoc();
    $total_credits_remaining = floatval($credit_row['total_credit']);
    $stmt_credit->close();

    // Step 3: Get FIFO debits
    $fifo_sql = "
        SELECT id, debit, purchase_price
        FROM fuel_stock_ledger
        WHERE location_id = ? AND p_id = ? AND status = 1 AND debit > 0
        ORDER BY id ASC
    ";
    $stmt_fifo = $con->prepare($fifo_sql);
    $stmt_fifo->bind_param("ii", $location_id, $p_id);
    $stmt_fifo->execute();
    $result_fifo = $stmt_fifo->get_result();

    $remaining_qty = $sales_qty;
    $total_cost = 0;
    $total_used = 0;

    while ($row = $result_fifo->fetch_assoc()) {
        if ($remaining_qty <= 0) break;

        $debit_qty = floatval($row['debit']);
        $price = floatval($row['purchase_price']);

        // Simulate how much of this row is already used up
        if ($total_credits_remaining >= $debit_qty) {
            $total_credits_remaining -= $debit_qty;
            continue; // fully consumed
        }

        $available_in_row = $debit_qty - $total_credits_remaining;
        $total_credits_remaining = 0;

        $use_qty = min($available_in_row, $remaining_qty);
        $total_cost += $use_qty * $price;
        $total_used += $use_qty;
        $remaining_qty -= $use_qty;
    }

    $stmt_fifo->close();

    if ($total_used == 0) return 0;

    return round($total_cost / $total_used, 4);
}


if (isset($_POST['close_shift']) && $_POST['close_shift'] == 1) {
    $shift_id = intval($_POST['shift_id']);
    
    $check = mysqli_query($con, "SELECT open_status FROM shed_operator_shift WHERE shift_id = $shift_id");
    $row = mysqli_fetch_assoc($check);
    if ($row && (int)$row['open_status'] === 0) {
        echo json_encode([
            'status' => 'already_closed',
            'message' => 'This shift was already closed.'
        ]);
        exit;
    }
    
    // $end_time = $_POST['end_time'];
    //     $end_time_input = $_POST['end_time']; // HH:mm
    // $end_time = date('Y-m-d') . ' ' . $end_time_input . ':00';
    $end_time_input = $_POST['end_time']; // e.g., "2025-05-31T18:45"
    $end_time = str_replace('T', ' ', $end_time_input) . ':00'; // => "2025-05-31 18:45:00"
    $end_date = date('Y-m-d', strtotime($end_time));
    $cash_collection = floatval($_POST['cash_collection']);
    $total_card_sales = floatval($_POST['card_collection']);
    $total_credit_sales = floatval($_POST['credit_collection']);
    $excess_short = floatval($_POST['excess_short']);
    $pump_ids = $_POST['pump_ids'];
    $closing_readings = $_POST['closing_readings'];
$location_id = floatval($_POST['location_id']);
   $vat_rate = 0;
                        $debit_vat = 0;
                        $credit_vat = 0;
                        $cus_id = 0;
                        $sup_id = 0;
                        $status = 1;
                        $other_branch = 0;
                        $tra_type = 'Shift';
                        $source = 'Shift';
                        $ref_no = $shift_id;
                        
    // Update shift end time and open status
 $errors = [];
$product_sales = []; // accumulate fuel sales by p_id
$total_vat = 0;

foreach ($pump_ids as $index => $pump_assign_id) {
    $query_opening = "SELECT * FROM shed_pump_assign WHERE id = $pump_assign_id";
    $result = mysqli_query($con, $query_opening);
    
    if (!$result || mysqli_num_rows($result) == 0) {
        $errors[] = "Pump assign ID $pump_assign_id not found";
        continue;
    }

    $rowx = mysqli_fetch_assoc($result);
    $p_id = intval($rowx['product_id']); // product id
    $closing_reading = floatval($closing_readings[$index]);

    // Get tested fuel
    $query_tested_fuel = "
        SELECT IFNULL(SUM(tested_fuel), 0) AS total_tested_fuel
        FROM shed_fuel_pump_test
        WHERE pump_assign_id = $pump_assign_id AND status = 1
    ";
    $result_tested = mysqli_query($con, $query_tested_fuel);
    $row_tested = mysqli_fetch_assoc($result_tested);
    $total_tested_fuel = floatval($row_tested['total_tested_fuel']);

    // Calculate sales for this pump
    $opening_reading = floatval($rowx['opening_reading']);
    $fuel_sales = $closing_reading - $opening_reading - $total_tested_fuel;
    if ($fuel_sales < 0) $fuel_sales = 0;

    // Add to product-wise total
    if (!isset($product_sales[$p_id])) {
        $product_sales[$p_id] = 0;
    }
    $product_sales[$p_id] += $fuel_sales;
}

// ✅ After loop: check stock for each product
foreach ($product_sales as $p_id => $total_sales) {
    $stock_sql = "SELECT SUM(debit) - SUM(credit) AS available_stock
                  FROM fuel_stock_ledger
                  WHERE location_id = $location_id AND p_id = $p_id AND status = 1";
    $stock_res = mysqli_query($con, $stock_sql);
    $stock_row = mysqli_fetch_assoc($stock_res);
    $available_stock = floatval($stock_row['available_stock']);

    if ($total_sales > $available_stock) {
        $item_res = mysqli_query($con, "SELECT p_name FROM product_master WHERE p_id = $p_id LIMIT 1");
        $item = mysqli_fetch_assoc($item_res);
        $item_name = $item ? $item['p_name'] : 'Unknown Item';

        $errors[] = "Insufficient stock for $item_name. Only " .
                    number_format($available_stock, 2) .
                    "L available, trying to record " .
                    number_format($total_sales, 2) . "L.";
    }
}

// ✅ If errors found, stop
if (!empty($errors)) {
    echo json_encode([
        'status'  => 'error',
        'message' => $errors
    ]);
    exit;
}


    //  sssssssssssssssss
foreach ($pump_ids as $index => $pump_assign_id) {
    $closing_reading = floatval($closing_readings[$index]);

    // Get the opening reading for this pump assignment
    $query_opening = "SELECT * FROM shed_pump_assign WHERE id = $pump_assign_id";
    $result = mysqli_query($con, $query_opening);
    if (!$result || mysqli_num_rows($result) == 0) {
        echo json_encode(['status' => 'error', 'message' => "Pump assign ID $pump_assign_id not found"]);
        exit;
    }
    $rowx = mysqli_fetch_assoc($result);
    $opening_reading = floatval($rowx['opening_reading']);
    
    $sales_price = floatval($rowx['sales_price']);

    // Get the total tested fuel for this pump assignment
    $query_tested_fuel = "
        SELECT IFNULL(SUM(tested_fuel), 0) AS total_tested_fuel
        FROM shed_fuel_pump_test
        WHERE pump_assign_id = $pump_assign_id
          AND status = 1
    ";
    $result_tested = mysqli_query($con, $query_tested_fuel);
    if (!$result_tested) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to fetch tested fuel']);
        exit;
    }
    $row_tested = mysqli_fetch_assoc($result_tested);
    $total_tested_fuel = floatval($row_tested['total_tested_fuel']);

    // Calculate fuel sales
    $fuel_sales = $closing_reading - $opening_reading - $total_tested_fuel;
    if ($fuel_sales < 0) {
        $fuel_sales = 0; // Prevent negative values
    }





               


        $p_id = floatval($rowx['product_id']);
        
        $date_time = $end_time;   
        $description = "";  // $end_time should be defined earlier
        $shift_id = $shift_id;   // make sure this is defined earlier
        $debit = 0;  // assuming debit is 0 for this insert
        $credit = $fuel_sales;  // should be defined earlier
        $user_id = $user_id;    // should be defined earlier
        $status = 1;  // assuming active status
        $description_escaped = mysqli_real_escape_string($con, $description);
        $date_time_escaped = mysqli_real_escape_string($con, $date_time);


 // Check available stock
    $stock_sql = "SELECT SUM(debit) - SUM(credit) AS available_stock
                  FROM fuel_stock_ledger
                  WHERE location_id = $location_id AND p_id = $p_id AND status = 1";
    $stock_res = mysqli_query($con, $stock_sql);
    $stock_row = mysqli_fetch_assoc($stock_res);
    $available_stock = floatval($stock_row['available_stock']);

    if ($fuel_sales > $available_stock) {
        $item_res = mysqli_query($con, "SELECT p_name FROM product_master WHERE p_id = $p_id LIMIT 1");
        $item = mysqli_fetch_assoc($item_res);
        $item_name = $item ? $item['p_name'] : 'Unknown Item';

        echo json_encode([
            'status' => 'error',
            'message' => "Insufficient stock for $item_name. Only " . number_format($available_stock, 2) . "L available, trying to record $fuel_sales L."
        ]);
        exit;
    }



            $opening_stock= mysqli_query($con, "SELECT (SUM(debit) - SUM(credit)) as stock_balance FROM `fuel_stock_ledger` WHERE `p_id` ='$p_id' and location_id='$location_id' and status =1");
            $opening_stock_row = mysqli_fetch_assoc($opening_stock);
            $closing_stock = $opening_stock_row ? $opening_stock_row['stock_balance'] -  $fuel_sales : 0;


        $purchase_price = get_fifo_purchase_price($con, $p_id, $location_id, $credit);


        $insert_query = "
            INSERT INTO fuel_stock_ledger 
            (location_id, p_id, date_time, description, shift_id, debit, credit, user_id, status,sales_price,purchase_price,pump_assign) 
            VALUES 
            ($location_id, $p_id, '$date_time_escaped', '$description_escaped', $shift_id, $debit, $credit, $user_id, $status,$sales_price,$purchase_price,$pump_assign_id)
        ";

            if (mysqli_query($con, $insert_query)) {
            // echo json_encode(['status' => 'success', 'message' => 'Record inserted successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Insert failed: ' . mysqli_error($con)]);
        }


///  =================================== record entry to


    // Update the pump assignment record
    



                    // 1. Get item name
                    $item_res = mysqli_query($con, "SELECT p_name,vat_rate FROM product_master WHERE p_id = $p_id LIMIT 1");
                    $item = mysqli_fetch_assoc($item_res);
                    $item_name = $item ? $item['p_name'] : 'Unknown Item';
                    $vat_rate = $item['vat_rate'];
                  

                    // 3. Build journal entry
                    if ($credit > 0 && $purchase_price > 0) {
                        $amount = round($credit * $purchase_price, 2);
                        $sales_vale = round($credit * $sales_price, 2);


                        $credit_vat = round($sales_vale * ($vat_rate / (100 + $vat_rate)), 2);
                        $total_vat += $credit_vat;
                        $memo = "Shift closed: $item_name " . number_format($credit, 2) . " Ltr. Purchase price " . number_format($purchase_price, 2) . ", sales price " . number_format($sales_price, 2);
                        $today = $end_time;

                      
                        // ================================
                        // 4. Insert DEBIT (COGS - ca_id 21)
                        // ================================
                        $query1 = "
                        INSERT INTO acc_transaction
                        (`location_id`, `ca_id`, `t_date`, `tra_type`, `source`, `source_id`, `ref_no`,
                        `debit`, `credit`, `vat_rate`, `debit_Vat`, `credit_vat`,
                        `t_memo`, `credted_by`, `created_on`, `cus_id`, `sup_id`, `status`, `other_branch`)
                        VALUES 
                        ($location_id, 21, '$today', '$tra_type', '$source', $shift_id, $ref_no,
                        $amount, 0, 0, 0, 0,
                        '" . mysqli_real_escape_string($con, $memo) . "', $user_id, '$today', $cus_id, $sup_id, $status, $other_branch)
                        ";
                        if (!mysqli_query($con, $query1)) {
                            echo json_encode(['error' => 'COGS insert failed: ' . mysqli_error($con)]);
                            exit;
                        }

                        // ===================================
                        // 5. Insert CREDIT (Inventory - ca_id 22)
                        // ===================================
                        $query2 = "
                        INSERT INTO acc_transaction
                        (`location_id`, `ca_id`, `t_date`, `tra_type`, `source`, `source_id`, `ref_no`,
                        `debit`, `credit`, `vat_rate`, `debit_Vat`, `credit_vat`,
                        `t_memo`, `credted_by`, `created_on`, `cus_id`, `sup_id`, `status`, `other_branch`)
                        VALUES 
                        ($location_id, 22, '$today', '$tra_type', '$source', $shift_id, $ref_no,
                        0, $amount, $vat_rate, $debit_vat, $credit_vat,
                        '" . mysqli_real_escape_string($con, $memo) . "', $user_id, '$today', $cus_id, $sup_id, $status, $other_branch)
                        ";
                        if (!mysqli_query($con, $query2)) {
                            echo json_encode(['error' => 'Inventory insert failed: ' . mysqli_error($con)]);
                            exit;
                        }
                    }


    $update_pump_query = "
            UPDATE shed_pump_assign
            SET 
                closing_reading = $closing_reading,
                total_test_reading = $total_tested_fuel,
                fuel_sales = $fuel_sales,
                closing_stock = $closing_stock,
                vat_rate = '$vat_rate',
                vat_amount = '$credit_vat',
                open_stats = 0
            WHERE id = $pump_assign_id
        ";
    mysqli_query($con, $update_pump_query);







}
 

   
 $total_sales = floatval($_POST['total_sales_calculated']); 
    // Update shift financials (total sales, card sales, credit sales, cash received, excess/short)
    
    
    
    
    $total_sales         = floatval($total_sales);
$total_card_sales    = floatval($total_card_sales);
$total_credit_sales  = floatval($total_credit_sales);
$cash_collection     = floatval($cash_collection);
$total_vat           = floatval($total_vat);
$excess_short        = floatval($excess_short);
$end_time            = mysqli_real_escape_string($con, $end_time);


$update_financials_query = "
    UPDATE shed_operator_shift
    SET 
        total_sales = $total_sales,
        total_card_sales = $total_card_sales,
        total_credit_sales = $total_credit_sales,
        cash_received = $cash_collection,
        sales_vat = $total_vat,
        exces_short = $excess_short,
        end_time = '$end_time',
        open_status = 0
    WHERE shift_id = $shift_id
";

 if (!mysqli_query($con, $update_financials_query)) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update shift financials']);
        exit;
    }

    

    // $update_financials_query = "
    //     UPDATE shed_operator_shift
    //     SET 
    //         total_sales = $total_sales,
    //         total_card_sales = $total_card_sales,
    //         total_credit_sales = $total_credit_sales,
    //         cash_received = $cash_collection,
    //         sales_vat = $total_vat,
    //         exces_short = $excess_short,
    //          end_time = '$end_time',
    //           open_status = 0
    //     WHERE shift_id = $shift_id
    // ";
    // if (!mysqli_query($con, $update_financials_query)) {
    //     echo json_encode(['status' => 'error', 'message' => 'Failed to update shift financials']);
    //     exit;
    // }






    // Success
    echo json_encode(['status' => 'success', 'message' => 'Shift closed successfully']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
}
?>
