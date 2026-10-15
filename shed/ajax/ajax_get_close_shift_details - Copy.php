<?php
include '../../db.php';

if (!isset($_POST['shift_id']) || empty($_POST['shift_id'])) {
    echo "<div class='alert alert-danger'>Invalid shift ID.</div>";
    exit;
}

$shift_id = intval($_POST['shift_id']);

// Fetch pumps assigned to this shift and still open, and sum of tested fuel for each pump assignment
$query = "
    SELECT spa.id AS pump_assign_id, 
           m.pump_name, 
           p.p_name, 
           spa.opening_reading, 
           spa.closing_reading,
           spa.sales_price,
           IFNULL(SUM(sft.tested_fuel), 0) AS total_tested_fuel
    FROM shed_pump_assign spa
    JOIN manage_pump m ON spa.pump_id = m.id
    JOIN product_master p ON spa.product_id = p.p_id
    LEFT JOIN shed_fuel_pump_test sft ON spa.id = sft.pump_assign_id
    WHERE spa.shift_id = $shift_id
      AND spa.open_stats = 1
      AND spa.status = 1
    GROUP BY spa.id, m.pump_name, p.p_name, spa.opening_reading, spa.closing_reading, spa.sales_price
";

$result = mysqli_query($con, $query);

if (!$result || mysqli_num_rows($result) == 0) {
    echo "<div class='alert alert-warning'>No open pump assignments found for this shift.</div>";
    exit;
}

echo "<table class='table table-bordered table-sm'>";
echo "<thead><tr>
        <th>Pump</th>
        <th>Product</th>
        <th>Opening Meter Reading</th>
        <th>Closing Meter Reading <small>(required)</small></th>
        <th>Test Fuel</th>
        <th>Fuel Sold</th>   
        <th>Sales Price</th>
      </tr></thead><tbody>"; 

while ($row = mysqli_fetch_assoc($result)) {
    // Ensure opening_reading and closing_reading are treated as floats
    $opening_reading = floatval($row['opening_reading']);
    $closing_reading = floatval($row['closing_reading']);
    $total_tested_fuel = floatval($row['total_tested_fuel']);
    
    // Ensure closing_val is set correctly for display
    $closing_val = $closing_reading !== 0 ? $closing_reading : '';

    // Calculate fuel sold: (Closing Reading - Opening Reading - Test Fuel)
    $fuel_sold = $closing_reading - $opening_reading - $total_tested_fuel;

    echo "<tr>
            <td>" . htmlspecialchars($row['pump_name']) . "</td>
            <td>" . htmlspecialchars($row['p_name']) . "</td>
            <td align='center'><strong>" . implode(' ', str_split(htmlspecialchars($row['opening_reading']))) . "</strong></td>
            <td>
                <input type='hidden' name='pump_ids[]' value='" . intval($row['pump_assign_id']) . "'>
                <input type='number' step='0.01' min='" . floatval($row['opening_reading']) . "' name='closing_readings[]' class='form-control' required value='" . htmlspecialchars($closing_val) . "'>
            </td>
            <td align='center'>" . number_format($total_tested_fuel, 2) . "</td> <!-- Display sum of tested fuel -->
            <td align='center'>" . number_format($fuel_sold, 2) . "</td> <!-- Fuel Sold calculation -->
            <td align='center'>" . number_format($row['sales_price'], 2) . "</td>
          </tr>";
}

echo "</tbody></table>";
?>
