<?php
include '../../db.php';
include '../../auth.php';
header('Content-Type: application/json');

$from = isset($_GET['from']) ? $_GET['from'] : date('Y-01-01'); // Default to January 1st of the current year
$to   = isset($_GET['to']) ? $_GET['to'] : date('Y-m-d');
$location = $_REQUEST['location_id'];

$data = [];
$sql = "SELECT a.ca_name, SUM(t.debit) AS total
        FROM acc_transaction t
        JOIN acc_chart_of_accounts a ON t.ca_id = a.ca_id
        WHERE a.ca_type = 'Expenses'
          AND a.ca_group <> 'Cost of Goods Sold'
          AND t.status = 1
          AND t.location_id = '$location'
          AND t.t_date BETWEEN '$from' AND '$to'
        GROUP BY a.ca_name
        ORDER BY total DESC";

$res = mysqli_query($con, $sql);
while ($row = mysqli_fetch_assoc($res)) {
    $data[] = [
        'name' => $row['ca_name'],
        'y' => round(floatval($row['total']), 2)
    ];
}

echo json_encode($data);
exit;