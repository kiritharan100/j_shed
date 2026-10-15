<?php
require('../db.php');
require('../auth.php');
 
session_start();
if(empty($_SESSION['username'])) {
   session_unset();     
   session_destroy(); 
   header("Location: ../login.php?msg=true");
   exit;
}
$user =  $user_id;

$sel_query = "SELECT * FROM oil_sales_temp WHERE user = '$user'";
$result = mysqli_query($con, $sel_query);

while ($row = mysqli_fetch_assoc($result)) {
      $sales_price = $row['rate'];
      $quantity = $row['qty'];
      $bill_total += $sales_price * $quantity;
       }
       
// Find number of rows
$num_rows = mysqli_num_rows($result);
if($num_rows == 0){
    
    header("Location:oil_sales_master.php?error=1 ");
    exit;
}







// $store_id = $_SESSION['store_id'];
$today=date('Y-m-d');
 

if (isset($_POST['submit_oil_sales'])) {
    $ref_no = $_POST['ref_no'];
    $vehicle_no = $_POST['vehicle_no'];
    $invoice_no =$_POST['invoice_no'];
    $from_location = $_POST['location'];
    $date = $_POST['inv_date'];
    // $bill_total = $_POST['bill_total'];
    // $bill_no = $_POST['bill_no']; 
    $to_location = $_POST['customer'];

    $sql = "select COUNT(location) AS no_entry  FROM oil_sales_master WHERE location = '$from_location'";
    $rs = mysqli_query($con, $sql);
    $no = mysqli_fetch_assoc($rs);
    $record_no =  $no['no_entry'] + 1;

    echo $que = "INSERT into oil_sales_master (invoice_no,vehicle_no,ref_no,issue_date,location,loc_id,to_location,issue_total,record_by,record_date)VALUES('$invoice_no','$vehicle_no','$ref_no','$date','$from_location','$record_no','$to_location',$bill_total,'$user_id','$today')";
    
        if ($con->query($que) === TRUE) {
              $last_id = $con->insert_id; }
 

    $sel_query = "SELECT oil_sales_temp.*,oil_stock.price as purchase_price ,oil_stock.sales_price from oil_sales_temp
    INNER JOIN oil_stock on oil_stock.batch_id = oil_sales_temp.s_id
    
     where user = '$user'";
    $result = mysqli_query($con, $sel_query);
    $no_items = mysqli_num_rows($result);
    while ($row = mysqli_fetch_assoc($result)) {
      $sales_price = $row['sales_price'];
      $purchase_price = $row['purchase_price'];

      $que = "UPDATE oil_stock set transfered_qty = transfered_qty+'" . $row['qty'] . "' WHERE  batch_id = '" . $row['s_id'] . "' ";
        mysqli_query($con, $que);

      $que = "INSERT into oil_sales_detail (iss_id,batch_id,issue_qty,purchase_price,sales_price)VALUES('$last_id','" . $row['s_id'] . "','" . $row['qty'] . "','$purchase_price','$sales_price')";
        mysqli_query($con, $que);


    }
    
       $detail = "Oil sales recoded  id: $last_id TR NO:$record_no";
    UserLog('2','Oil sales ',$detail);

      

    $query = "DELETE FROM oil_sales_temp where user = '$user'";
    $result = mysqli_query($con, $query);
    $new_entry =  $last_id * 1852454545;
    header("Location:oil_sales_master.php?record=$new_entry ");
}


setcookie("entry_date", $date, time() + (86400 * 1), "/"); // 86400 = 1 day

?>