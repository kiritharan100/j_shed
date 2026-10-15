<?php

// This file handles accounting logic for standard debit or credit adjustments

$tra_type = 'Fuel Stock Adjustment';
$memo = "$description - $item_name $adj_qty Ltr @ Purchase: " . number_format($purchase_price, 2) . ", Sales: " . number_format($sales_price, 2);
$today = $adjustment_datetime;
$source = 'Stock_Adjustment';
$zero = 0;



$acc_sql = "INSERT INTO acc_transaction 
    (t_date, tra_type, ca_id, debit, credit, t_memo, status, created_on, credted_by, location_id, source, source_id, ref_no)
    VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?)";

if ($debit > 0) {
    $amount = round(abs($adj_qty) * $purchase_price, 2);
    // ▶ Debit Inventory (ca_id = 22)
    $debit_ca = 22;
    $stmt_debit = $con->prepare($acc_sql);
    $stmt_debit->bind_param("ssiddssissii", $today, $tra_type, $debit_ca, $amount, $zero, $memo, $today, $user_id, $location_id, $source, $stock_id, $stock_id);
    $stmt_debit->execute();
    $stmt_debit->close();

    // ▶ Credit Inventory Adjustment (ca_id = 24)
    $credit_ca = 24;
    $stmt_credit = $con->prepare($acc_sql);
    $stmt_credit->bind_param("ssiddssissii", $today, $tra_type, $credit_ca, $zero, $amount, $memo, $today, $user_id, $location_id, $source, $stock_id, $stock_id);
    $stmt_credit->execute();
    $stmt_credit->close();

} elseif ($credit > 0) {
    $cost_value = round(abs($adj_qty) * $purchase_price, 2);
    $sales_value = round(abs($adj_qty) * $sales_price, 2);

   
        
     
    // Adjustment of Inventory  At cost 

    // ▶ Debit Adjustment Reason
    $debit_ca =25;  // Shortage of Inventory Account COGS
    $stmt_debit = $con->prepare($acc_sql);
    $stmt_debit->bind_param("ssiddssissii", $today, $tra_type, $debit_ca, $cost_value, $zero, $memo, $today, $user_id, $location_id, $source, $stock_id, $stock_id);
    $stmt_debit->execute();
    $stmt_debit->close();

    // ▶ Credit Inventory (ca_id = 22)
     $credit_ca = 22; // Inventory Account 
    $stmt_credit = $con->prepare($acc_sql);
    $stmt_credit->bind_param("ssiddssissii", $today, $tra_type, $credit_ca, $zero, $cost_value, $memo, $today, $user_id, $location_id, $source, $stock_id, $stock_id);
    $stmt_credit->execute();
    $stmt_credit->close();

        // Record shortage as sales at sales price

     
    $debit_ca =46;  // Manager Short Receivable
    $stmt_debit = $con->prepare($acc_sql);
    $stmt_debit->bind_param("ssiddssissii", $today, $tra_type, $debit_ca, $sales_value, $zero, $memo, $today, $user_id, $location_id, $source, $stock_id, $stock_id);
    $stmt_debit->execute();
    $stmt_debit->close();

 
     $credit_ca = 45; // Sales of Shortage Income account
    $stmt_credit = $con->prepare($acc_sql);
    $stmt_credit->bind_param("ssiddssissii", $today, $tra_type, $credit_ca, $zero, $sales_value, $memo, $today, $user_id, $location_id, $source, $stock_id, $stock_id);
    $stmt_credit->execute();
    $stmt_credit->close();




}
