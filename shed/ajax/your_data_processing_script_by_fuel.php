 <?php 
 
 function getOpeningStockValue($con, $location_id, $as_of_date ,$p_id) {
    $total_opening_value = 0;

    // -------------------------
    // FUEL OPENING VALUE
    // -------------------------
    $qty_sql = mysqli_query($con, "
        SELECT p_id, SUM(debit - credit) AS qty
        FROM fuel_stock_ledger
        WHERE status = 1
          AND location_id = '$location_id'
          AND DATE(date_time) < '$as_of_date'
          AND p_id = '$p_id'
        GROUP BY p_id
    ");

    while ($row = mysqli_fetch_assoc($qty_sql)) {
        $p_id = $row['p_id'];
        $rate_sql = mysqli_query($con, "
            SELECT new_price 
            FROM fuel_price_change
            WHERE p_id = '$p_id'
              AND status = 1 
              AND cancellation = 0
              AND date_time < '$as_of_date'
            ORDER BY date_time DESC 
            LIMIT 1
        ");
        $rate_row = mysqli_fetch_assoc($rate_sql);
        $price = isset($rate_row['new_price']) ? floatval($rate_row['new_price']) : 0;
        $total_opening_value += floatval($row['qty']) * $price;
    }

    // -------------------------
    // OIL OPENING VALUE
    // -------------------------
   

    return $total_opening_value;
}

 function getClosingStockValue($con, $location_id, $as_of_date,$p_id) {
    $total_closing_value = 0;

    // -----------------------------
    // FUEL CLOSING VALUE
    // -----------------------------
    $qty_sql = mysqli_query($con, "
        SELECT p_id, SUM(debit - credit) AS qty
        FROM fuel_stock_ledger
        WHERE status = 1
          AND location_id = '$location_id'
          AND DATE(date_time) <= '$as_of_date'
          AND p_id = '$p_id'
        GROUP BY p_id
    ");

    while ($row = mysqli_fetch_assoc($qty_sql)) {
        $p_id = $row['p_id'];
        $qty = floatval($row['qty']);

        $rate_sql = mysqli_query($con, "
            SELECT new_price 
            FROM fuel_price_change
            WHERE p_id = '$p_id'
              AND status = 1  
              AND date_time <= '$as_of_date'
            ORDER BY date_time DESC 
            LIMIT 1
        ");

        $rate_row = mysqli_fetch_assoc($rate_sql);
        $price = isset($rate_row['new_price']) ? floatval($rate_row['new_price']) : 0;

        $total_closing_value += $qty * $price;
    }

 
     

    return $total_closing_value;
}

//////////////////////// ========================================= End of  Opening Balacne Calculation 

 

 function getPurchaseValueByDate($con, $location_id, $date,$p_id) {
    $total_purchase_value = 0.0;

    // -------------------------
    // FUEL PURCHASE VALUE
    // -------------------------
    $qty_sql = mysqli_query($con, "
        SELECT p_id, SUM(quantity) AS qty
        FROM fuel_purchase_master
        WHERE status = 1
          AND location_id = '$location_id'
          AND DATE(purchase_date) = '$date'
          AND p_id = '$p_id'
        GROUP BY p_id
    ");

    while ($row = mysqli_fetch_assoc($qty_sql)) {
        $p_id = $row['p_id'];
        $qty  = floatval($row['qty']);

        $price_sql = mysqli_query($con, "
            SELECT new_price
            FROM fuel_price_change
            WHERE p_id = '$p_id'
              AND status = 1
              AND cancellation = 0
              AND DATE(date_time) <= '$date'
            ORDER BY date_time DESC
            LIMIT 1
        ");

        if ($price_row = mysqli_fetch_assoc($price_sql)) {
            $price = floatval($price_row['new_price']);
            $total_purchase_value += $qty * $price;
        }
    }

  

    return $total_purchase_value;
}


// ======================> Purchased up to yesterday 

// function getPurchaseValueUpToYesterday($con, $location_id, $from_date, $to_date) {
//     $total = 0;

//     $q = mysqli_query($con, "
//         SELECT SUM(quantity * rate) AS total_value
//         FROM fuel_purchase_master
//         WHERE location_id = '$location_id'
//           AND status = 1
//           AND DATE(purchase_date) BETWEEN '$from_date' AND '$to_date'
//     ");

//     if ($q && $row = mysqli_fetch_assoc($q)) {
//         $total = floatval($row['total_value']);
//     }

//     return $total;
// }
 function getPurchaseValueUpToYesterday($con, $location_id, $from_date, $to_date,$p_id) {
    $total_purchase_value = 0.0;

    // -----------------------------
    // FUEL PURCHASE VALUE
    // -----------------------------
    $sql = mysqli_query($con, "
        SELECT p_id, DATE(purchase_date) AS pur_date, SUM(quantity) AS qty
        FROM fuel_purchase_master
        WHERE location_id = '$location_id'
          AND status = 1
          AND DATE(purchase_date) BETWEEN '$from_date' AND '$to_date'
          AND p_id = '$p_id'
        GROUP BY p_id, pur_date
    ");

    while ($row = mysqli_fetch_assoc($sql)) {
        $p_id     = $row['p_id'];
        $qty      = floatval($row['qty']);
        $pur_date = $row['pur_date'];

        $rate_q = mysqli_query($con, "
            SELECT new_price
            FROM fuel_price_change
            WHERE p_id = '$p_id'
              AND status = 1
              AND cancellation = 0
              AND DATE(date_time) <= '$pur_date'
            ORDER BY date_time DESC
            LIMIT 1
        ");

        if ($rate_row = mysqli_fetch_assoc($rate_q)) {
            $price = floatval($rate_row['new_price']);
            $total_purchase_value += $qty * $price;
        }
    }

 

    return $total_purchase_value;
}


//=======================> Stock Adjustment Today 
 function getAdjustmentToday($con, $location_id, $selected_date,$p_id) {
    $total = 0;

    // -----------------------------
    // FUEL ADJUSTMENTS
    // -----------------------------
    $q_adjust = mysqli_query($con, "
        SELECT p_id, SUM(debit - credit) AS net_qty, MAX(sales_price) AS rate
        FROM fuel_stock_ledger
        WHERE shift_id = -2
          AND location_id = '$location_id'
          AND status = 1
          AND DATE(date_time) = '$selected_date'
          AND p_id = '$p_id'
        GROUP BY p_id
    ");

    while ($row = mysqli_fetch_assoc($q_adjust)) {
        $qty = floatval($row['net_qty']);
        $rate = floatval($row['rate']);
        $total += $qty * $rate;
    }

    

    return $total;
}


///============================= Stock Adjustmet up to yesterday 
 function getAdjustmentUpToYesterday($con, $location_id, $from_month_start, $yesterday,$p_id) {
    $total = 0;

    // -----------------------------
    // FUEL ADJUSTMENTS
    // -----------------------------
    $q_adjust = mysqli_query($con, "
        SELECT p_id, SUM(debit - credit) AS net_qty, MAX(sales_price) AS rate
        FROM fuel_stock_ledger
        WHERE shift_id = -2
          AND location_id = '$location_id'
          AND status = 1
          AND DATE(date_time) BETWEEN '$from_month_start' AND '$yesterday'
          AND p_id = '$p_id'
        GROUP BY p_id
    ");

    while ($row = mysqli_fetch_assoc($q_adjust)) {
        $qty = floatval($row['net_qty']);
        $rate = floatval($row['rate']);
        $total += $qty * $rate;
    }

     

    return $total;
}

//===================================================== Ptice change Today 
function getPriceChangeAdjustmentToday($con, $location_id, $selected_date,$p_id) {
    $total_adjustment = 0;

    $price_changes = mysqli_query($con, "
        SELECT p_id, new_price, date_time 
        FROM fuel_price_change 
        WHERE DATE(date_time) = '$selected_date'
          AND status = 1
          AND cancellation = 0
          AND p_id = '$p_id'
    ");

    while ($change = mysqli_fetch_assoc($price_changes)) {
        $pid = $change['p_id'];
        $new_price = floatval($change['new_price']);
        $change_date = $change['date_time'];

        // Get old price before this date
        $old_q = mysqli_query($con, "
            SELECT new_price 
            FROM fuel_price_change 
            WHERE p_id = '$pid'
              AND DATE(date_time) < '$change_date'
              AND status = 1
              AND cancellation = 0
            ORDER BY date_time DESC 
            LIMIT 1
        ");
        if (!$old_q || mysqli_num_rows($old_q) == 0) continue;
        $old_price = floatval(mysqli_fetch_assoc($old_q)['new_price']);

        if ($new_price == $old_price) continue;

        // Get stock before change
        $qty_q = mysqli_query($con, "
            SELECT SUM(debit - credit) AS qty 
            FROM fuel_stock_ledger 
            WHERE p_id = '$pid'
              AND location_id = '$location_id'
              AND status = 1 
              AND DATE(date_time) < '$change_date'
              AND p_id = '$p_id'
        ");
        $qty = 0;
        if ($qty_q && mysqli_num_rows($qty_q) > 0) {
            $qty = floatval(mysqli_fetch_assoc($qty_q)['qty']);
        }

        if ($qty == 0) continue;

        $adjustment = $qty * ($new_price - $old_price);
        $total_adjustment += $adjustment;
    }

    return $total_adjustment;
}
//================================================================== price changes Yesterday 
function getPriceChangeAdjustmentUpToYesterday($con, $location_id, $from_month_start, $yesterday,$p_id) {
    $total_adjustment = 0;

    $price_changes = mysqli_query($con, "
        SELECT p_id, new_price, date_time 
        FROM fuel_price_change 
        WHERE DATE(date_time) BETWEEN '$from_month_start' AND '$yesterday'
          AND status = 1
          AND cancellation = 0
          AND p_id = '$p_id'
    ");

    while ($change = mysqli_fetch_assoc($price_changes)) {
        $pid = $change['p_id'];
        $new_price = floatval($change['new_price']);
        $change_date = $change['date_time'];

        // Get old price before this date
        $old_q = mysqli_query($con, "
            SELECT new_price 
            FROM fuel_price_change 
            WHERE p_id = '$pid'
              AND DATE(date_time) < '$change_date'
              AND status = 1
              AND cancellation = 0
            ORDER BY date_time DESC 
            LIMIT 1
        ");
        if (!$old_q || mysqli_num_rows($old_q) == 0) continue;
        $old_price = floatval(mysqli_fetch_assoc($old_q)['new_price']);

        if ($new_price == $old_price) continue;

        // Get stock before change
        $qty_q = mysqli_query($con, "
            SELECT SUM(debit - credit) AS qty 
            FROM fuel_stock_ledger 
            WHERE p_id = '$pid'
              AND location_id = '$location_id'
              AND status = 1 
              AND DATE(date_time) < '$change_date'
        ");
        $qty = 0;
        if ($qty_q && mysqli_num_rows($qty_q) > 0) {
            $qty = floatval(mysqli_fetch_assoc($qty_q)['qty']);
        }

        if ($qty == 0) continue;

        $adjustment = $qty * ($new_price - $old_price);
        $total_adjustment += $adjustment;
    }

    return $total_adjustment;
}



//////////////////=============================================Credit card sales Today 
function getCardSalesToday($con, $location_id, $selected_date) {
    $total = 0;

    $q = mysqli_query($con, "
        SELECT SUM(amount) AS total 
        FROM shed_card_sales 
        WHERE location_id = '$location_id' 
          AND status = 1 
          AND DATE(created_on) = '$selected_date'
    ");

    if ($q && $row = mysqli_fetch_assoc($q)) {
        $total = floatval($row['total']);
    }

    return $total;
}

//==============================================================>>>>>> Credit card Sales up to yesterday 

function getCardSalesUpToYesterday($con, $location_id, $from_month_start, $yesterday) {
    $total = 0;

    $q = mysqli_query($con, "
        SELECT SUM(amount) AS total 
        FROM shed_card_sales 
        WHERE location_id = '$location_id' 
          AND status = 1 
          AND DATE(created_on) BETWEEN '$from_month_start' AND '$yesterday'
    ");

    if ($q && $row = mysqli_fetch_assoc($q)) {
        $total = floatval($row['total']);
    }

    return $total;
}


// ============================> credit sales Today 

function getCreditSalesTodayByCategory($con, $location_id, $selected_date, $ca_id,$p_id) {
    $total = 0;

    // --- FUEL CREDIT SALES ---
    $fuel_sql = mysqli_query($con, "
        SELECT SUM(d.line_total) AS total
        FROM shed_credit_sales s
        INNER JOIN mange_customer c ON s.c_id = c.c_id
        INNER JOIN shed_credit_sales_detail d ON d.cs_id = s.cs_id
        WHERE s.status = 1
          AND s.location_id = '$location_id'
          AND DATE(s.date_time) = '$selected_date'
          AND c.ca_id = '$ca_id'
          AND s.day_end <> '999999999'
          AND d.p_id = '$p_id'
    ");

    if ($fuel_sql && $row = mysqli_fetch_assoc($fuel_sql)) {
        $total += floatval($row['total']);
    }
 

    return $total;
}


//====================================== Credt sales up to yesteday 
function getCreditSalesUpToYesterdayByCategory($con, $location_id, $from_date, $to_date, $ca_id,$p_id) {
    $total = 0;

    // --- FUEL CREDIT SALES ---
    $fuel_sql = mysqli_query($con, "
        SELECT SUM(d.line_total) AS total
        FROM shed_credit_sales s
        INNER JOIN mange_customer c ON s.c_id = c.c_id
        INNER JOIN shed_credit_sales_detail d ON d.cs_id = s.cs_id
        WHERE s.status = 1
          AND s.location_id = '$location_id'
          AND DATE(s.date_time) BETWEEN '$from_date' AND '$to_date'
          AND c.ca_id = '$ca_id'
          AND s.day_end <> '999999999'
           AND d.p_id = '$p_id'
    ");

    if ($fuel_sql && $row = mysqli_fetch_assoc($fuel_sql)) {
        $total += floatval($row['total']);
    }

    

    return $total;
}

//============================ cash sales today 
function getCashSalesToday($con, $location_id, $selected_date) {
    $total = 0;

    $q = mysqli_query($con, "
        SELECT fuel_sales, oil_sales, total_card_settled, total_credit_settle
        FROM day_end_process
        WHERE location_id = '$location_id'
          AND status = 1
          AND DATE(date_ended) = '$selected_date'
    ");

    while ($row = mysqli_fetch_assoc($q)) {
        $fuel_sales  = floatval($row['fuel_sales']);
        $oil_sales   = floatval($row['oil_sales']);
        $card_sales  = floatval($row['total_card_settled']);
        $credit_sales = floatval($row['total_credit_settle']);

        $cash_sales = ($fuel_sales + $oil_sales) - $card_sales - $credit_sales;
        $total += $cash_sales;
    }

    return $total;
}

//============================ cash sales upto yesterday 
function getCashSalesUpToYesterday($con, $location_id, $from_date, $to_date) {
    $total = 0;

    $q = mysqli_query($con, "
        SELECT fuel_sales, oil_sales, total_card_settled, total_credit_settle
        FROM day_end_process
        WHERE location_id = '$location_id'
          AND status = 1
          AND DATE(date_ended) BETWEEN '$from_date' AND '$to_date'
    ");

    while ($row = mysqli_fetch_assoc($q)) {
        $fuel_sales  = floatval($row['fuel_sales']);
        $oil_sales   = floatval($row['oil_sales']);
        $card_sales  = floatval($row['total_card_settled']);
        $credit_sales = floatval($row['total_credit_settle']);

        $cash_sales = ($fuel_sales + $oil_sales) - $card_sales - $credit_sales;
        $total += $cash_sales;
    }

    return $total;
}

// ============================
// Total Fuel Sales - Today
// ============================
function getTotalSalesToday($con, $location_id, $selected_date,$p_id) {
    $total = 0;

    $sql = mysqli_query($con, "
        SELECT SUM(spa.fuel_sales * spa.sales_price) AS total_sales
        FROM shed_operator_shift sos
        INNER JOIN shed_pump_assign spa ON spa.shift_id = sos.shift_id
        INNER JOIN manage_pump ON manage_pump.id = spa.pump_id AND manage_pump.product_assigned = '$p_id'
        WHERE sos.status = 1
          AND sos.location_id = '$location_id'
          AND DATE(sos.end_time) = '$selected_date'
         
    ");

    if ($row = mysqli_fetch_assoc($sql)) {
        $total = floatval($row['total_sales']);
    }

    return $total;
}


// ============================
// Total Fuel Sales - Up to Yesterday
// ============================
function getTotalSalesUpToYesterday($con, $location_id, $from_date, $to_date,$p_id) {
    $total = 0;

    $sql = mysqli_query($con, "
        SELECT SUM(spa.fuel_sales * spa.sales_price) AS total_sales
        FROM shed_operator_shift sos
        INNER JOIN shed_pump_assign spa ON spa.shift_id = sos.shift_id
         INNER JOIN manage_pump ON manage_pump.id = spa.pump_id AND manage_pump.product_assigned = '$p_id'
        WHERE sos.status = 1
          AND sos.location_id = '$location_id'
          AND DATE(sos.end_time) BETWEEN '$from_date' AND '$to_date'
    ");

    if ($row = mysqli_fetch_assoc($sql)) {
        $total = floatval($row['total_sales']);
    }

    return $total;
}



?>