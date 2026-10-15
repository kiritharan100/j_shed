<?php
// include '../../db.php';

// header('Content-Type: application/json');
// $shift_id = isset($_GET['shift_id']) ? intval($_GET['shift_id']) : 0;
// // Fetch active fuel products
// $query = "SELECT p_id, p_code, p_name FROM product_master WHERE p_cat = 'Fuel' AND status = 1  ORDER BY p_name";
// $result = mysqli_query($con, $query);

// $products = [];
// if ($result) {
//     while ($row = mysqli_fetch_assoc($result)) {
//         $products[] = [
//             'p_id' => $row['p_id'],
//             'p_code' => $row['p_code'],
//             'p_name' => $row['p_name']
//         ];
//     }
// }

// echo json_encode($products);

 
include '../../db.php';
header('Content-Type: application/json');

$shift_id = isset($_GET['shift_id']) ? intval($_GET['shift_id']) : 0;
$products = [];

if ($shift_id > 0) {
    $query = "
        SELECT DISTINCT p.p_id, p.p_code, p.p_name 
        FROM shed_pump_assign spa
        INNER JOIN product_master p ON spa.product_id = p.p_id
        WHERE spa.shift_id = $shift_id 
          AND p.p_cat = 'Fuel' 
          AND p.status = 1
        ORDER BY p.p_name
    ";

    $result = mysqli_query($con, $query);

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $products[] = [
                'p_id' => $row['p_id'],
                'p_code' => $row['p_code'],
                'p_name' => $row['p_name']
            ];
        }
    }
}

echo json_encode($products);

