 <?php
//  ini_set('display_errors', 1);
// error_reporting(E_ALL);
// header('Content-Type: application/json');
// include '../../db.php';
// include '../../auth.php';  // To get $user_id

// header('Content-Type: application/json');

// // Sanitize input helper
// function clean_input($data) {
//     return htmlspecialchars(strip_tags(trim($data)));
// }

// // Check required POST fields
// if (
//     !isset($_POST['customer_id'], $_POST['shift_id'], $_POST['vehicle_no'], 
//     $_POST['products']['p_id'], $_POST['products']['qty'], $_POST['products']['rate'], $_POST['products']['line_total'])
// ) {
//     echo json_encode(['error' => 'Missing required fields']);
//     exit;
// }

// $c_id = intval($_POST['customer_id']);
// $shift_id = intval($_POST['shift_id']);
// $vehicle_no = clean_input($_POST['vehicle_no']);
// $location_id = intval($_POST['location_id']);
// $created_by = $user_id;

// $p_ids = $_POST['products']['p_id'];
// $qtys = $_POST['products']['qty'];
// $rates = $_POST['products']['rate'];
// $line_totals = $_POST['products']['line_total'];

// if (!is_array($p_ids) || count($p_ids) === 0) {
//     echo json_encode(['error' => 'At least one product is required']);
//     exit;
// }

// // Calculate total sales
// $total_sales = 0;
// foreach ($line_totals as $lt) {
//     $total_sales += floatval($lt);
// }

// if ($total_sales <= 0) {
//     echo json_encode(['error' => 'Total sales must be greater than zero']);
//     exit;
// }

// // Begin transaction
// mysqli_begin_transaction($con);

// try {
//     // Prepare insert into shed_credit_sales
//     $stmt = $con->prepare("INSERT INTO shed_credit_sales (c_id, shift_id, date_time, vehicle_no, total_sales, status, record_by, location_id) VALUES (?, ?, NOW(), ?, ?, 1, ?, ?)");
//     if (!$stmt) {
//         throw new Exception("Prepare failed: " . $con->error);
//     }
//     if (!$stmt->bind_param("iisdii", $c_id, $shift_id, $vehicle_no, $total_sales, $created_by, $location_id)) {
//         throw new Exception("Bind failed: " . $stmt->error);
//     }
//     if (!$stmt->execute()) {
//         throw new Exception("Execute failed: " . $stmt->error);
//     }

//     $cs_id = $stmt->insert_id;
//     $stmt->close();

//     // Prepare insert for shed_credit_sales_detail
//     $stmt_detail = $con->prepare("INSERT INTO shed_credit_sales_detail (cs_id, p_id, qty, rate, line_total, status) VALUES (?, ?, ?, ?, ?, 1)");
//     if (!$stmt_detail) {
//         throw new Exception("Prepare detail failed: " . $con->error);
//     }

//     // Insert each product line
//     for ($i = 0; $i < count($p_ids); $i++) {
//         $p_id = intval($p_ids[$i]);
//         $qty = floatval($qtys[$i]);
//         $rate = floatval($rates[$i]);
//         $line_total = floatval($line_totals[$i]);

//         if ( $qty <= 0 || $rate <= 0 || $line_total <= 0) {
//             throw new Exception("Invalid product details at line " . ($i + 1));
//         }

//         if (!$stmt_detail->bind_param("iiidd", $cs_id, $p_id, $qty, $rate, $line_total)) {
//             throw new Exception("Bind detail failed at line " . ($i + 1) . ": " . $stmt_detail->error);
//         }

//         if (!$stmt_detail->execute()) {
//             throw new Exception("Execute detail failed at line " . ($i + 1) . ": " . $stmt_detail->error);
//         }
//     }
//     $stmt_detail->close();

//     // Commit transaction
//     if (!mysqli_commit($con)) {
//         throw new Exception("Commit failed: " . mysqli_error($con));
//     }

//     echo json_encode(['success' => true]);

// } catch (Exception $e) {
//     mysqli_rollback($con);
//     echo json_encode(['error' => $e->getMessage()]);
// }


 
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
include '../../db.php';
include '../../auth.php'; // To get $user_id

// Sanitize input helper
function clean_input($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Check required POST fields
if (
    !isset($_POST['customer_id'], $_POST['shift_id'], $_POST['vehicle_no'], 
    $_POST['products']['p_id'], $_POST['products']['qty'], $_POST['products']['rate'], $_POST['products']['line_total'])
) {
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

$c_id = intval($_POST['customer_id']);
$shift_id = intval($_POST['shift_id']);
$vehicle_no = clean_input($_POST['vehicle_no']);
$order_no = clean_input($_POST['order_no']);
$invoice_no = clean_input($_POST['invoice_no']);
$location_id = intval($_POST['location_id']);
$created_by = $user_id;


 $query = "SELECT DATE_ADD(MAX(date_ended), INTERVAL 1 DAY) AS next_day_end_date 
          FROM day_end_process 
          WHERE status = 1 AND location_id = '$location_id'";
$result = mysqli_query($con, $query);
$row = mysqli_fetch_assoc($result);

$next_day_end_date = $row['next_day_end_date'];
$today_date = date('Y-m-d');
$current_datetime = date('Y-m-d H:i:s');

if ($next_day_end_date == $today_date || empty($next_day_end_date)) {
   $created_on = $current_datetime; // Use current datetime
    
} else {
    $created_on = $next_day_end_date . ' 23:59:59'; // End of the next day
     

}

$get_last_shift_no_query = "
    SELECT MAX(inv_id) AS last_inv_id
    FROM shed_credit_sales
    WHERE location_id = $location_id
";
$result = mysqli_query($con, $get_last_shift_no_query);
$row = mysqli_fetch_assoc($result);

// Step 2: Determine next shift number
$inv_id = ($row['last_inv_id'] ?? 0) + 1;





$p_ids = $_POST['products']['p_id'];
$qtys = $_POST['products']['qty'];
$rates = $_POST['products']['rate'];
$line_totals = $_POST['products']['line_total'];

if (!is_array($p_ids) || count($p_ids) === 0) {
    echo json_encode(['error' => 'At least one product is required']);
    exit;
}

// Calculate total sales
$total_sales = 0;
foreach ($line_totals as $lt) {
    $total_sales += floatval($lt);
}

if ($total_sales <= 0) {
    echo json_encode(['error' => 'Total sales must be greater than zero']);
    exit;
}

// Begin transaction
mysqli_begin_transaction($con);

try {
    // Check if the shift is open
    $shift_check_query = "SELECT open_status FROM shed_operator_shift WHERE shift_id = ? ";
    $shift_stmt = $con->prepare($shift_check_query);
    $shift_stmt->bind_param("i", $shift_id);
    $shift_stmt->execute();
    $shift_result = $shift_stmt->get_result();
    $shift_stmt->close();

    if ($shift_result->num_rows == 0) {
        throw new Exception("Invalid shift or shift not found.");
    }

    $shift_row = $shift_result->fetch_assoc();
    if ($shift_row['open_status'] == 0) {
        // If the shift is closed, prevent cancellation and return custom error message
        echo json_encode(['error' => 'The selected shift is closed. Cannot proceed with credit sale.']);
        exit;
    }

    // Proceed with saving the credit sale
    // Insert into shed_credit_sales
    
      $stmt = $con->prepare("INSERT INTO shed_credit_sales 
    (inv_id, invoice_no, c_id, shift_id, ref_no, date_time, vehicle_no, total_sales, status, record_by, location_id) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)");

if (!$stmt) {
    throw new Exception("Prepare failed: " . $con->error);
}

if (!$stmt->bind_param("isiissssii", 
    $inv_id, $invoice_no, $c_id, $shift_id, $order_no, $created_on, 
    $vehicle_no, $total_sales, $created_by, $location_id)) {
    throw new Exception("Bind failed: " . $stmt->error);
}

if (!$stmt->execute()) {
    throw new Exception("Execute failed: " . $stmt->error);
}

    // $stmt = $con->prepare("INSERT INTO shed_credit_sales (inv_id,invoice_no,c_id, shift_id,ref_no, date_time, vehicle_no, total_sales, status, record_by, location_id) VALUES (?, ?, ?, ?, ?,NOW(), ?, ?, 1, ?, ?)");
    // if (!$stmt) {
    //     throw new Exception("Prepare failed: " . $con->error);
    // }
    // if (!$stmt->bind_param("isiissdii",$inv_id,$invoice_no, $c_id, $shift_id,$order_no, $vehicle_no, $total_sales, $created_by, $location_id)) {
    //     throw new Exception("Bind failed: " . $stmt->error);
    // }
    // if (!$stmt->execute()) {
    //     throw new Exception("Execute failed: " . $stmt->error);
    // }

    $cs_id = $stmt->insert_id;
    $stmt->close();

    // Insert into shed_credit_sales_detail
    $stmt_detail = $con->prepare("INSERT INTO shed_credit_sales_detail (cs_id, p_id, qty, rate, line_total, status) VALUES (?, ?, ?, ?, ?, 1)");
    if (!$stmt_detail) {
        throw new Exception("Prepare detail failed: " . $con->error);
    }

    // Insert each product line
    for ($i = 0; $i < count($p_ids); $i++) {
        $p_id = intval($p_ids[$i]);
        $qty = floatval($qtys[$i]);
        $rate = floatval($rates[$i]);
        $line_total = floatval($line_totals[$i]);

        if ($qty <= 0 || $rate <= 0 || $line_total <= 0) {
            throw new Exception("Invalid product details at line " . ($i + 1));
        }

if (!$stmt_detail->bind_param("iiddd", $cs_id, $p_id, $qty, $rate, $line_total)) {
    throw new Exception("Bind detail failed at line " . ($i + 1) . ": " . $stmt_detail->error);
}

        if (!$stmt_detail->execute()) {
            throw new Exception("Execute detail failed at line " . ($i + 1) . ": " . $stmt_detail->error);
        }
    }
    $stmt_detail->close();


    // Commit transaction
    if (!mysqli_commit($con)) {
        throw new Exception("Commit failed: " . mysqli_error($con));
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    mysqli_rollback($con);
    echo json_encode(['error' => $e->getMessage()]);
}

?>
