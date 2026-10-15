<?php  
include("../../auth.php");
 
require('../../db.php');

 
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $field = $_POST['field'];
    $value = $_POST['value'];

 
    // Update query
    $query = "UPDATE oil_stock SET sales_price = '$value' WHERE batch_id = $id";
    
    
    
    

    if ($con->query($query) === TRUE) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database update failed']);
    }


 $detail = "Price change updated batch id: $id new date: $value";
    UserLog('1','Expire Date',$detail);
    
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>