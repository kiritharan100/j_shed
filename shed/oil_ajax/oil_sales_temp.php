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
  
     $totalRowCount=$_POST['totalRowCount'];

  
    mysqli_query($con, "INSERT INTO `oil_sales_temp`(s_id,qty,rate,cost,row_count,user) VALUES ('$s_id','$qty','$rate','$cost','$totalRowCount','$user_id')");
} 
?>
