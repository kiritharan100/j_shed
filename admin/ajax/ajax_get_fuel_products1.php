<?php
include '../../db.php';
header('Content-Type: application/json');

if (!isset($_POST['location_id'])) {
    echo json_encode([]);
    exit;
}

$location_id = intval($_POST['location_id']);

$query = "
    SELECT 
        pm.p_id,
        pm.p_name,
        COALESCE(SUM(CASE 
            WHEN fsl.status = 1 AND fsl.location_id = '$location_id' THEN fsl.debit - fsl.credit 
            ELSE 0 
        END), 0) AS stock_liters
    FROM product_master pm
    LEFT JOIN fuel_stock_ledger fsl ON fsl.p_id = pm.p_id
    WHERE pm.p_cat = 'Fuel'
    GROUP BY pm.p_id
    ORDER BY pm.p_name
";

$result = mysqli_query($con, $query);

$stock_list = [];
while ($row = mysqli_fetch_assoc($result)) {
    $stock = round($row['stock_liters'], 2);
    $stock_list[] = [
        'p_id'   => $row['p_id'],
        'p_name' => $row['p_name'] . " : " . $stock . " L",
        'stock'  => $stock . ' L'
    ];
}

echo json_encode($stock_list);
