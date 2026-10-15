<?php
session_start();
require('../../db.php');




header('Content-Type: application/json');

if(isset($_POST['iss_id'])) {
    $iss_id = $_POST['iss_id'];

    // Start a transaction
    mysqli_begin_transaction($con);

    try {
        // Update the issue_status in drugs_issue_master
        $update_master = "UPDATE oil_sales_master SET issue_status = 0 WHERE iss_id = '$iss_id'";
        mysqli_query($con, $update_master);

        // Update the status in oil_sales_drugs_detail
        $update_detail = "UPDATE oil_sales_detail SET status = 0 WHERE iss_id = '$iss_id'";
        mysqli_query($con, $update_detail);

        // Adjust the transferred_qty and open_status in oil_stock
        $select_detail = "SELECT s_id, batch_id, issue_qty FROM oil_sales_detail WHERE iss_id = '$iss_id'";
        $result = mysqli_query($con, $select_detail);

        while ($row = mysqli_fetch_assoc($result)) {
            $s_id = $row['s_id'];
            $batch_id = $row['batch_id'];
            $issue_qty = $row['issue_qty'];

            $update_stock = "UPDATE oil_stock SET 
                             transfered_qty = transfered_qty - '$issue_qty', 
                             open_status = 1 
                             WHERE batch_id = '$batch_id'";
            mysqli_query($con, $update_stock);
        }

        // Commit the transaction
        mysqli_commit($con);
        
        
         $detail = "Stock Transfer Cancelled : $iss_id ";
    UserLog('3','Cancelled Transfer',$detail);

        echo json_encode(['success' => true]);
        
        
    } catch (Exception $e) {
        // Rollback the transaction in case of error
        mysqli_rollback($con);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>
 
 
 

