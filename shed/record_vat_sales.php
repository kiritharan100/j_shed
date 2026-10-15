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
// $store_id = $_SESSION['store_id'];
$today=date('Y-m-d');
 

if (isset($_POST['submit_vat_sales'])) {
  
    $from_location = $_POST['location'];
    $date = $_POST['inv_date'];
    $bill_total = $_POST['bill_total'];
   
    $to_location = $_POST['customer'];
      $invoice_period = $_POST['invoice_period'];
        $tin_no = $_POST['tin_no'];
        
         

    $sql = "select COUNT(location) AS no_entry  FROM vat_sales_master WHERE location = '$from_location' and to_location <> 'Others'";
    $rs = mysqli_query($con, $sql);
    $no = mysqli_fetch_assoc($rs);
    $record_no =  $no['no_entry'] + 1;

  $bill_no   = $_SESSION['vat_invpice_serial'].$year = date("Y", strtotime($date))."/".$record_no;

    echo $que = "INSERT into vat_sales_master (issue_date,location,loc_id,to_location,issue_total,record_by,record_date,invoice_no,tin_number,invoice_date_range)VALUES('$date','$from_location','$record_no','$to_location',$bill_total,'$user_id','$today','$bill_no','$tin_no','$invoice_period')";
    
        if ($con->query($que) === TRUE) {
              $last_id = $con->insert_id; }
 

    $sel_query = "SELECT vat_sales_temp.* from vat_sales_temp
    
    
     where user = '$user'";
    $result = mysqli_query($con, $sel_query);
    $no_items = mysqli_num_rows($result);
    while ($row = mysqli_fetch_assoc($result)) {
      $sales_price = $row['rate'];
      $vat_rate = $row['vat_rate'];
      $purchase_price = 0;

      // $que = "UPDATE oil_stock set transfered_qty = transfered_qty+'" . $row['qty'] . "' WHERE  batch_id = '" . $row['s_id'] . "' ";
      //   mysqli_query($con, $que);

      $que = "INSERT into vat_sales_detail (iss_id,batch_id,issue_qty,purchase_price,sales_price,vat_rate)VALUES('$last_id','" . $row['s_id'] . "','" . $row['qty'] . "','$purchase_price','$sales_price','$vat_rate')";
        mysqli_query($con, $que);

      

    }
    $que = "UPDATE vat_sales_master set vat_rate = '$vat_rate' WHERE  iss_id = '$last_id' ";
        mysqli_query($con, $que);






    
       $detail = "VAT sales recoded  id: $last_id TR NO:$record_no";
    UserLog('2','VAT sales ',$detail);

      

    $query = "DELETE FROM vat_sales_temp where user = '$user'";
    $result = mysqli_query($con, $query);
    $new_entry =  $last_id * 1852454545;
    header("Location:vat_sales_master.php?record=$new_entry ");
}


setcookie("entry_date", $date, time() + (86400 * 1), "/"); // 86400 = 1 day

?>