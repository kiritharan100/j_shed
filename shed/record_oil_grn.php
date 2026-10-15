<?php
require('../db.php');
 
session_start();
if(empty($_SESSION['username'])) {
   session_unset();     
   session_destroy(); 
   header("Location: ../login.php?msg=true");
   exit;
}

$user = $_SESSION['username'];
// $store_id = $_SESSION['store_id'];
$today=date('Y-m-d');



 




if (isset($_POST['submit_grn'])) {
    
        $sel_query = "SELECT * FROM oil_grn_temp WHERE user = '$user'";
    $result = mysqli_query($con, $sel_query);
    $no_items = mysqli_num_rows($result);
    if ($no_items == 0) {
        header("Location: oil_grn_master.php?faild");
        exit();
    }
    
    
  
    $location = $_POST['location'];
    $date = $_POST['inv_date'];
    $bill_total = $_POST['bill_total'];
    $bill_no = $_POST['bill_no']; 
    $customer = $_POST['customer'];
                $total_query = "SELECT SUM(qty * rate) AS total_value 
                FROM oil_grn_temp 
                WHERE user = '$user'";
            $total_result = mysqli_query($con, $total_query);
            $row_total = mysqli_fetch_assoc($total_result);
            $bill_total = $row_total['total_value'] ?? 0;

    $sql = "select COUNT(location) AS no_entry  FROM oil_grn_master WHERE location = '$location'";
    $rs = mysqli_query($con, $sql);
    $no = mysqli_fetch_assoc($rs);
    $record_no =  $no['no_entry'] + 1;

      $que = "INSERT into oil_grn_master (grn_date,location,loc_id,sup_id,grn_total,record_by,invoice_no)VALUES('$date','$location','$record_no','$customer',$bill_total,'$user','$bill_no')";
     

        if ($con->query($que) === TRUE) {
              $last_id = $con->insert_id; }


              // Accounting entry for GRN
$memo = "Oil Purchase - GRN No: $record_no";
$tra_type = 'Oil Purchase';
$source = 'Oil Purchase';
$ref_no = $record_no;
$t_date = date('Y-m-d');
$created_on = date('Y-m-d H:i:s');
$status = 1;
$vat_rate = 0;
$debit_Vat = 0;
$credit_vat = 0;
$cus_id = 0;
$other_branch = 0;

// Debit Inventory (ca_id = 22)
$acc_query1 = "INSERT INTO acc_transaction
(location_id, ca_id, t_date, tra_type, source, source_id, ref_no,
 debit, credit, vat_rate, debit_Vat, credit_vat,
 t_memo, credted_by, created_on, cus_id, sup_id, status, other_branch)
VALUES
($location, 22, '$t_date', '$tra_type', '$source', $last_id, $ref_no,
 $bill_total, 0, $vat_rate, $debit_Vat, $credit_vat,
 '" . mysqli_real_escape_string($con, $memo) . "', '$user', '$created_on', $cus_id, $customer, $status, $other_branch)";
mysqli_query($con, $acc_query1);

// Credit Trade Creditors (ca_id = 9)
$acc_query2 = "INSERT INTO acc_transaction
(location_id, ca_id, t_date, tra_type, source, source_id, ref_no,
 debit, credit, vat_rate, debit_Vat, credit_vat,
 t_memo, credted_by, created_on, cus_id, sup_id, status, other_branch)
VALUES
($location, 9, '$t_date', '$tra_type', '$source', $last_id, $ref_no,
 0, $bill_total, $vat_rate, $debit_Vat, $credit_vat,
 '" . mysqli_real_escape_string($con, $memo) . "', '$user', '$created_on', $cus_id, $customer, $status, $other_branch)";
mysqli_query($con, $acc_query2);










 

    $sel_query = "SELECT oil_grn_temp.* from oil_grn_temp where user = '$user'";
    $result = mysqli_query($con, $sel_query);
    $no_items = mysqli_num_rows($result);
    while ($row = mysqli_fetch_assoc($result)) {
$sales_price = $row['sales_price'];
   
        $que = "INSERT into oil_stock (supplier,batch_date,store_id,purchase_qty,price,s_id,grn_id,purchase_value,source,exp_date,sales_price)VALUES('$customer','$date','$location','" . $row['qty'] . "','" . $row['rate'] . "','" . $row['s_id'] . "','$last_id','$line_value','GRN','" . $row['exp_date'] . "','$sales_price')";
        mysqli_query($con, $que);

        $que = "UPDATE product_master set last_price = '" . $row['rate'] . "' WHERE p_id = '" . $row['s_id'] . "'";
        mysqli_query($con, $que);

    }
    
    
    $detail = "Add new GRN id: $last_id GRN NO:$record_no";
    UserLog('2','New GRN',$detail);

    $query = "DELETE FROM oil_grn_temp where user = '$user'";
    $result = mysqli_query($con, $query);
    $new_entry =  $last_id * 1852454545;    
    header("Location:oil_grn_master.php?record=$new_entry ");
}

  










 

?>