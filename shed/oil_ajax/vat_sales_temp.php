<?php
require('../../db.php');
require('../../auth.php');
// session_start();
// $user = $_SESSION['username'];

if (isset($_POST['grn'])) {
    $s_id = $_POST['x_id'];
    $rate = $_POST['rate'];
    $cost= $_POST['cost'];
    $qty= $_POST['qty'];
    $vat_rate= $_POST['vat_rate'];

  
     $totalRowCount=$_POST['totalRowCount'];

  
    mysqli_query($con, "INSERT INTO `vat_sales_temp`(s_id,qty,rate,cost,row_count,user,vat_rate) VALUES ('$s_id','$qty','$rate','$cost','$totalRowCount','$user_id','$vat_rate')");
} 
?>
