<?php

// This file handles accounting for evaporation and short quantity (Stock Count for Petrol/Diesel)

$tra_type = 'Fuel Stock Adjustment';
$today = $adjustment_datetime;
$source = 'Stock_Adjustment';
$zero = 0;


$short_debit_amt     = round($short_qty * $purchase_price, 2);
$allowance_debit_amt = round($e_allowance * $purchase_price, 2);
$total_credit_amt    = round(($short_qty + $e_allowance) * $purchase_price, 2);
$short_sales_amt     = round($short_qty * $sales_price, 2);

$memo_short_debit    = "Manager Short {$short_qty} * " . number_format($purchase_price, 2);
$memo_evap_debit     = "Evaporation allwance {$e_allowance} * " . number_format($purchase_price, 2);
$memo_credit_total   = "Evaporation and Short ({$e_allowance} + {$short_qty}) * " . number_format($purchase_price, 2);
$memo_short_sales    = "Short Qty {$short_qty} * " . number_format($sales_price, 2);

$acc_sql = "INSERT INTO acc_transaction 
    (t_date, tra_type, ca_id, debit, credit, t_memo, status, created_on, credted_by, location_id, source, source_id, ref_no)
    VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?)";

 //Cost of godds sold only for Cost of short qty
 if($short_debit_amt > 0){
$ca21 = 21;
$stmt1 = $con->prepare($acc_sql);
$stmt1->bind_param("ssiddssissii", $today, $tra_type, $ca21, $short_debit_amt, $zero, $memo_short_debit, $today, $user_id, $location_id, $source, $stock_id, $stock_id);
$stmt1->execute();
$stmt1->close();
 }

if($allowance_debit_amt > 0){
// 2. Debit Evaporation expenses (ca_id = 23)
$ca23 = 23;
$stmt2 = $con->prepare($acc_sql);
$stmt2->bind_param("ssiddssissii", $today, $tra_type, $ca23, $allowance_debit_amt, $zero, $memo_evap_debit, $today, $user_id, $location_id, $source, $stock_id, $stock_id);
$stmt2->execute();
$stmt2->close();
}

if($total_credit_amt > 0){
// 3. Credit Inventory (ca_id = 22)
 $ca22 = 22;
$stmt3 = $con->prepare($acc_sql);
$stmt3->bind_param("ssiddssissii", $today, $tra_type, $ca22, $zero, $total_credit_amt, $memo_credit_total, $today, $user_id, $location_id, $source, $stock_id, $stock_id);
$stmt3->execute();
$stmt3->close();
}

if($short_sales_amt > 0){
// 4.   Manager Short Receivable
$ca46 = 46;
$stmt4 = $con->prepare($acc_sql);
$stmt4->bind_param("ssiddssissii", $today, $tra_type, $ca46, $short_sales_amt, $zero, $memo_short_sales, $today, $user_id, $location_id, $source, $stock_id, $stock_id);
$stmt4->execute();
$stmt4->close();

// 5. Credit Sales Recovery (ca_id = 45) Sales of Shortage (income account )
$ca45 = 45;
$stmt5 = $con->prepare($acc_sql);
$stmt5->bind_param("ssiddssissii", $today, $tra_type, $ca45, $zero, $short_sales_amt, $memo_short_sales, $today, $user_id, $location_id, $source, $stock_id, $stock_id);
$stmt5->execute();
$stmt5->close();

}



