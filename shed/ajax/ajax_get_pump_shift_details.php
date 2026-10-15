<?php
include '../../db.php';

if (!isset($_POST['pumps']) || !isset($_POST['start_time'])) {
    echo "<div class='alert alert-danger'>Invalid request parameters.</div>";
    exit;
}

$pumps = $_POST['pumps'];
$start_time_input = $_POST['start_time'];
$date_today = date('Y-m-d');
$start_datetime = $date_today . ' ' . $start_time_input . ':00';

// Sanitize pumps array to integers
$pumps = array_map('intval', $pumps);
$pumps_in = implode(',', $pumps);

if (empty($pumps_in)) {
    echo "<div class='alert alert-warning'>No pumps selected.</div>";
    exit;
}

// Query pumps with product info
$query = "
    SELECT
      m.id AS pump_id,
      m.pump_name,
      m.product_assigned,
      p.p_name
    FROM manage_pump m
    LEFT JOIN product_master p ON m.product_assigned = p.p_id
    WHERE m.id IN ($pumps_in)
    AND m.status = 1
";

$result = mysqli_query($con, $query);

if (!$result || mysqli_num_rows($result) == 0) {
    echo "<div class='alert alert-warning'>No pumps found or inactive.</div>";
    exit;
}

echo "<table class='table table-sm table-bordered'>";
echo "<thead><tr>
        <th>Pump</th>
        <th>Product</th>
        <th>Opening Meter Reading</th>
        <th>Sales Price</th>
      </tr></thead><tbody>";

while ($row = mysqli_fetch_assoc($result)) {
    $pump_id = $row['pump_id'];
    $product_id = $row['product_assigned'];

    // Get last closing reading for this pump (last shift)
    $last_closing_q = mysqli_query($con, "
        SELECT closing_reading 
        FROM shed_pump_assign 
        WHERE pump_id = $pump_id AND status = 1 
        ORDER BY id DESC LIMIT 1
    ");
    $last_closing = ($last_closing_q && mysqli_num_rows($last_closing_q) > 0) ? mysqli_fetch_assoc($last_closing_q)['closing_reading'] : 0;

    // Get fuel price effective at shift start time for product
    $price_q = mysqli_query($con, "
        SELECT new_price 
        FROM fuel_price_change 
        WHERE p_id = $product_id AND status = 1 AND date_time <= '$start_datetime' 
        ORDER BY date_time DESC LIMIT 1
    ");
    $sales_price = ($price_q && mysqli_num_rows($price_q) > 0) ? mysqli_fetch_assoc($price_q)['new_price'] : 0;

    echo "<tr>
            <td>" . htmlspecialchars($row['pump_name']) . "</td>
            <td>" . htmlspecialchars($row['p_name']) . "</td>
            <td>" . number_format($last_closing, 2) . "</td>
            <td>" . number_format($sales_price, 2) . "</td>
          </tr>";
}
echo "</tbody></table>";
?>
