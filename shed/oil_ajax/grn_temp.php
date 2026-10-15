<?php
require('../../db.php');
session_start();
$user = $_SESSION['username'];

if (isset($_POST['grn'])) {
    $s_id = $_POST['x_id'];
    $rate = $_POST['rate'];
    $cost= $_POST['cost'];
    $qty= $_POST['qty'];
    $exp_date= $_POST['exp_date'];
    $sales_price= $_POST['sales_price'];
  
     $totalRowCount=$_POST['totalRowCount'];

  
    mysqli_query($con, "INSERT INTO `oil_grn_temp`(s_id,qty,rate,cost,row_count,user,exp_date,sales_price) VALUES ('$s_id','$qty','$rate','$cost','$totalRowCount','$user','$exp_date','$sales_price')");
} 
?>
