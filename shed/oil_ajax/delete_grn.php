<?php 
 

require('../../db.php');
require('../../auth.php');
$request = $_REQUEST; 
$user = $user_id;
$grn_id = $request['grn_id']; 



if(isset($_REQUEST['grn_id'])){
$sel_query="SELECT  SUM(`return_qty`)+SUM(`issued_qty`)+SUM(`adjusted_qty`)+SUM(`transfered_qty`) as stock_adjusted FROM `oil_stock` where grn_id = '$grn_id' AND source = 'GRN'";
$result = mysqli_query($con,$sel_query);
$row = mysqli_fetch_assoc($result);
echo json_encode($row);
}




$acc_cancel = mysqli_query($con, "
    UPDATE acc_transaction
    SET status = 0
    WHERE source = 'Oil Purchase' AND source_id = $grn_id
");


if(isset($_REQUEST['delete_grn_id'])){
    $grn_id = $_REQUEST['delete_grn_id'];
    $sel_query="UPDATE oil_grn_master set grn_status = 0 ,deleted_by = '$user' where grn_id = '$grn_id'";
    $result = mysqli_query($con,$sel_query);
    $sel_query="UPDATE oil_stock set grn_status = 0 , open_status= 0 ,referance = 'deleted by $user' where grn_id = '$grn_id' AND source = 'GRN'";
    $result = mysqli_query($con,$sel_query);


    $detail = "GRN Deleted  id: $grn_id ";
    UserLog('2','Delete GRN',$detail);

    // $row = mysqli_fetch_assoc($result);
    // echo json_encode($row);
    }



?>