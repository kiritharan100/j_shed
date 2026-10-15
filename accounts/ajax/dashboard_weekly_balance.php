<?php
include '../../db.php';
include '../../auth.php';
header('Content-Type: application/json');

$location = isset($_GET['location_id']) ? (int)$_GET['location_id'] : 0;
$today = new DateTimeImmutable('today');
$current_month = $today->modify('first day of this month');
$months = [];
for ($i = 11; $i >= 0; $i--) {
    $month_start = $current_month->modify("-$i months");
    $month_end = ($i === 0) ? $today : $month_start->modify('last day of this month');
    $months[] = [
        'label' => $month_start->format('M Y'),
        'end' => $month_end->format('Y-m-d')
    ];
}

$accounts = [
    ['ca_id' => 1, 'name' => 'Current Account'],
    ['ca_id' => 7, 'name' => 'Cash in Hand']
];

$series = [];
foreach ($accounts as $acc) {
    $points = [];
    foreach ($months as $month) {
        $sql = "SELECT 
                    IFNULL(SUM(debit),0) AS debit,
                    IFNULL(SUM(credit),0) AS credit 
                FROM acc_transaction 
                WHERE location_id = $location
                    AND ca_id = '{$acc['ca_id']}' 
                    AND t_date < DATE_ADD('{$month['end']}', INTERVAL 1 DAY)
                    AND status = 1";
        $res = mysqli_query($con, $sql);
        $row = mysqli_fetch_assoc($res);
        $balance = floatval($row['debit']) - floatval($row['credit']);
        $points[] = round($balance, 2);
    }
    $series[] = [
        'name' => $acc['name'],
        'data' => $points
    ];
}

$response = [
    'months' => array_column($months, 'label'),
    'series' => $series
];

echo json_encode($response);
exit;
