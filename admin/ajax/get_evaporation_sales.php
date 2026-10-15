<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../../db.php';

$p_id = intval($_POST['p_id']);
$location_id = intval($_POST['location_id']);
$end_date = $_POST['end_date'];

$start_datetime = date("Y-m-01 00:00:00", strtotime("first day of this month", strtotime($end_date)));
$end_datetime   = date("Y-m-t 23:59:59", strtotime("last day of this month", strtotime($end_date)));

$query = "
  SELECT SUM(fuel_sales) as fuel_sales
  FROM shed_pump_assign
  INNER JOIN shed_operator_shift ON shed_operator_shift.shift_id = shed_pump_assign.shift_id
  WHERE product_id = $p_id
    AND shed_operator_shift.location_id = '$location_id'
    AND shed_operator_shift.end_time BETWEEN '$start_datetime' AND '$end_datetime' and shed_operator_shift.status = '1'
";

$result = mysqli_query($con, $query);
$data = mysqli_fetch_assoc($result);

// echo "p_id: $p_id<br>";
// echo "location_id: $location_id<br>";
// echo "end_date: $end_date<br>";
// echo "start_datetime: $start_datetime<br>";
// echo "end_datetime: $end_datetime<br>";
// echo "SQL: $query<br>";

echo json_encode([
  'success' => true,
  'fuel_sales' => $data['fuel_sales'] ?? 0
]);
