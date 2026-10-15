<?php
include("../../db.php");

$location_id = isset($_GET['location_id']) ? intval($_GET['location_id']) : 0;

$next_id = 1; // default
$sql = "SELECT MAX(inv_id) as last_id FROM shed_credit_sales WHERE location_id = '$location_id'";
$res = mysqli_query($con, $sql);

if ($res) {
    $row = mysqli_fetch_assoc($res);
    $last_id = intval($row['last_id']);
    if ($last_id > 0) {
        $next_id = $last_id + 1;  // for testing
    }
} else {
    echo json_encode(['error' => 'SQL failed', 'query' => $sql]);
    exit;
}

echo json_encode([
    'next_invoice_no' => $next_id,
    'inv_id' => $next_id
]);
