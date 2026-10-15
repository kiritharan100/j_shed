 <?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

include '../../db.php';
include '../../auth.php'; // Ensure $user_id is set

$required = ['location_id', 'p_id', 'description'];
foreach ($required as $field) {
    if (!isset($_POST[$field])) {
        echo json_encode(['error' => "Missing field: $field"]);
        exit;
    }
}

$location_id = intval($_POST['location_id']);
$p_id = intval($_POST['p_id']);
$description = trim($_POST['description']);
$debit = floatval($_POST['debit'] ?? 0);
$credit = floatval($_POST['credit'] ?? 0);
$adjustment_date = $_POST['adjustment_date'] ?? date('Y-m-d');
// $adjustment_datetime = $adjustment_date . ' ' . date('H:i:s');
$adjustment_datetime = $adjustment_date . ' 23:59:55';

$stmt = $con->prepare("SELECT p_name FROM product_master WHERE p_id = ?");
$stmt->bind_param("i", $p_id); // "i" indicates integer
$stmt->execute();
$result = $stmt->get_result();

if ($row_product = $result->fetch_assoc()) {
    $item_name = $row_product['p_name'];
} else {
    $item_name = 'Product not found';
}





if (!isset($user_id)) {
    echo json_encode(['error' => 'User not authenticated']);
    exit;
}

if ($debit <= 0 && $credit <= 0) {
    echo json_encode(['error' => 'Either debit or credit must be greater than zero']);
    exit;
}
if ($debit > 0 && $credit > 0) {
    echo json_encode(['error' => 'Only one of debit or credit should be greater than zero']);
    exit;
}

// Validate and assign prices
if ($credit > 0) {
    $stock_check = mysqli_query($con, "SELECT SUM(debit) - SUM(credit) AS available_stock FROM fuel_stock_ledger WHERE location_id = $location_id AND p_id = $p_id AND status = 1");
    $available_stock = floatval(mysqli_fetch_assoc($stock_check)['available_stock'] ?? 0);
    if ($credit > $available_stock) {
        $pname = mysqli_fetch_assoc(mysqli_query($con, "SELECT p_name FROM product_master WHERE p_id = $p_id"))['p_name'] ?? 'Unknown';
        echo json_encode(['status' => 'error', 'message' => "Insufficient stock for $pname. Available: $available_stock L, Requested: $credit L"]);
        exit;
    }
    $price_data = mysqli_fetch_assoc(mysqli_query($con, "SELECT purchase_price, sales_price FROM fuel_stock_ledger WHERE location_id = $location_id AND p_id = $p_id AND debit > 0 ORDER BY id DESC LIMIT 1"));
    $purchase_price = floatval($price_data['purchase_price'] ?? 0);
    $sales_price = floatval($price_data['sales_price'] ?? 0);
} else {
    $purchase_price = floatval($_POST['purchase_price'] ?? 0);
    $sales_price = floatval($_POST['sales_price'] ?? 0);
}

if ($debit > 0 && ($purchase_price <= 0 || $sales_price <= 0)) {
    echo json_encode(['error' => 'Purchase and Sales prices are required for debit entries.']);
    exit;
}

// Insert into fuel_stock_ledger
$stmt = $con->prepare("INSERT INTO fuel_stock_ledger (location_id, p_id, shift_id, date_time, description, debit, credit, user_id, status, purchase_price, sales_price) VALUES (?, ?, -2, ?, ?, ?, ?, ?, 1, ?, ?)");
$stmt->bind_param("iissddidd", $location_id, $p_id, $adjustment_datetime, $description, $debit, $credit, $user_id, $purchase_price, $sales_price);
if (!$stmt->execute()) {
    echo json_encode(['error' => "Ledger insert failed: " . $stmt->error]);
    exit;
}
$stock_id = $stmt->insert_id;
$stmt->close();

// Special evaporation logic
if ($credit > 0 && in_array($p_id, [1, 2]) && strtolower($description) === 'stock count') {
    $l_sales = floatval($_POST['fuel_sold_last_month'] ?? 0);
    $e_allowance = round(($l_sales / 6600) * 32.5, 3);
    $short_qty = max(0, $credit - $e_allowance);
    if($short_qty == 0){
        $e_allowance =  $credit;
    }

    $adj_qty = $debit > 0 ? $debit : -1 * $credit;

    $stmt_adj = $con->prepare("INSERT INTO fuel_stock_adjustment (adjustment_date, location_id, p_id, adj_qty, l_sales, e_allowance, short, purchase_price, sales_price) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt_adj->bind_param("siidddddd", $adjustment_date, $location_id, $p_id, $adj_qty, $l_sales, $e_allowance, $short_qty, $purchase_price, $sales_price);
    $stmt_adj->execute();
    $stmt_adj->close();

    // Accounting for evaporation + short
    include 'accounting_evaporation.php';
}  else {
    // Insert a general adjustment row with 0 values for l_sales, e_allowance, and short
    $l_sales = 0;
    $e_allowance = 0;
    $short_qty = 0;
    $adj_qty = $debit > 0 ? $debit : -1 * $credit;
    $stmt_adj = $con->prepare("
        INSERT INTO fuel_stock_adjustment 
        (adjustment_date, location_id, p_id, adj_qty, l_sales, e_allowance, short, purchase_price, sales_price) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
        adj_qty = VALUES(adj_qty), 
        l_sales = VALUES(l_sales), 
        e_allowance = VALUES(e_allowance), 
        short = VALUES(short), 
        purchase_price = VALUES(purchase_price), 
        sales_price = VALUES(sales_price)
    ");

    if (!$stmt_adj) {
        echo json_encode(['error' => 'Adjustment insert/update prepare failed: ' . $con->error]);
        exit;
    }

    $stmt_adj->bind_param("siidddddd", $adjustment_date, $location_id, $p_id, $adj_qty, $l_sales, $e_allowance, $short_qty, $purchase_price, $sales_price);
    if (!$stmt_adj->execute()) {
        echo json_encode(['error' => 'Adjustment insert/update failed: ' . $stmt_adj->error]);
        exit;
    }
    $stmt_adj->close();

    // Perform general accounting logic
    include 'accounting_standard_adjustment.php';
}

$log_detail = "Fuel Stock Adjusted - Location: $location_id, Product: $item_name, ";
$log_detail .= $debit > 0 ? "Excess: " . number_format($debit, 3) . " Ltr" : "Short: " . number_format($credit, 3) . " Ltr";
UserLog("1", "Fuel Adjustment", $log_detail);


echo json_encode(['success' => true]);
