 
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../../db.php';

$p_id = intval($_POST['p_id']);
$location_id = intval($_POST['location_id']);
$end_date = $_POST['adjustment_date'];

// Get 1st and last day of the month
$start_datetime = date("Y-m-01 00:00:00", strtotime($end_date));
$end_datetime   = date("Y-m-t 23:59:59", strtotime($end_date));

$query = "
  SELECT 
    DATE(sos.start_time) AS sale_date,
    spa.sales_price,
    SUM(spa.fuel_sales) AS fuel_sales,
    SUM(spa.fuel_sales * spa.sales_price) AS sales_value
  FROM shed_operator_shift sos
  INNER JOIN shed_pump_assign spa ON sos.shift_id = spa.shift_id
  WHERE sos.location_id = '$location_id'
    AND spa.product_id = '$p_id'
    AND sos.end_time BETWEEN '$start_datetime' AND '$end_datetime'
    AND sos.status = '1'
  GROUP BY sale_date, spa.sales_price
  ORDER BY sale_date ASC
";

$result = mysqli_query($con, $query);

$total_litres = 0;
$total_sales = 0;

echo "<div class='table-responsive'><table class='table table-bordered table-striped'>
<thead>
  <tr class='table-info'>
    <th>Date</th>
    <th class='text-end'>Fuel (L)</th>
    <th class='text-end'>Sales Price</th>
    <th class='text-end'>Sales Value (Rs)</th>
  </tr>
</thead>
<tbody>";

if (mysqli_num_rows($result) > 0) {
  while ($row = mysqli_fetch_assoc($result)) {
    $fuel = floatval($row['fuel_sales']);
    $price = floatval($row['sales_price']);
    $value = floatval($row['sales_value']);

    $total_litres += $fuel;
    $total_sales += $value;

    echo "<tr>
            <td  align='Center'>" . htmlspecialchars($row['sale_date']) . "</td>
            <td class='text-end' align='Center'>" . number_format($fuel, 3) . "</td>
            <td class='text-end'  align='Center'>" . number_format($price, 2) . "</td>
            <td class='text-end'  align='right'>" . number_format($value, 2) . "</td>
          </tr>";
  }

  echo "<tr class='fw-bold table-secondary'>
          <td>Total</td>
          <td class='text-end'  align='Center'>" . number_format($total_litres, 3) . "</td>
          <td></td>
          <td class='text-end'  align='Right'>" . number_format($total_sales, 2) . "</td>
        </tr>";
} else {
  echo "<tr><td colspan='4' class='text-center text-danger'>No sales records found for this period.</td></tr>";
}

echo "</tbody></table></div>";
?>
