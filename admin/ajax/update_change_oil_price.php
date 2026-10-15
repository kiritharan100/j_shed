 <?php  
include("../../auth.php");
require('../../db.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id']);
    $field = $_POST['field']; // kept for future extension
    $value = floatval($_POST['value']);

    // Get old price before update
    $old_query = "SELECT sales_price FROM oil_stock WHERE batch_id = $id";
    $old_result = mysqli_query($con, $old_query);
    $old_data = mysqli_fetch_assoc($old_result);
    $old_price = $old_data ? $old_data['sales_price'] : 0;

    // Update oil_stock table
    $update_query = "UPDATE oil_stock SET sales_price = '$value' WHERE batch_id = $id";
    $update_result = mysqli_query($con, $update_query);

    if ($update_result) {
        // Insert record into oil_price_change_log
        $log_query = "INSERT INTO oil_price_change_log 
                        (batch_id, old_price, new_price, change_by) 
                      VALUES 
                        ($id, '$old_price', '$value', '$user_id')";
        mysqli_query($con, $log_query);

        // Add system user log
        $detail = "Oil price updated | Batch ID: $id | Old Price: $old_price | New Price: $value";
        UserLog('1', 'Oil Price Change', $detail);

        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database update failed']);
    }

} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
