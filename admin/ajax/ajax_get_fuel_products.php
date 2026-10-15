<?php
include '../../db.php';

header('Content-Type: application/json');

if (!isset($_POST['location_id'])) {
    echo json_encode([]);
    exit;
}

$location_id = intval($_POST['location_id']);

// Only active fuel products
$query = "SELECT p_id, p_name FROM product_master WHERE p_cat = 'Fuel' AND status = 1 and p_id > 0 ORDER BY p_name";
$result = mysqli_query($con, $query);

$products = [];
while ($row = mysqli_fetch_assoc($result)) {
    $products[] = $row;
}

echo json_encode($products);
