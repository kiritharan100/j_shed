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
           ,spa.pump_id
    FROM shed_pump_assign spa
    JOIN manage_pump m ON spa.pump_id = m.id
    JOIN product_master p ON spa.product_id = p.p_id
    LEFT JOIN shed_fuel_pump_test sft ON spa.id = sft.pump_assign_id AND   sft.status = 1  
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

echo "<table id='sales-table' class='table table-bordered table-sm'>";
echo "<thead><tr>
        <th>Pump</th>
        <th>Product</th>
        <th>Opening Meter Reading</th>
        <th>Closing Meter Reading <small>(required)</small></th>
        <th>Test Fuel</th>
        <th>Fuel Sold</th>   
        <th>Sales Price</th>
        <th>Line total </th>
      </tr>
      </thead><tbody>"; 

while ($row = mysqli_fetch_assoc($result)) {
    $opening_reading = floatval($row['opening_reading']);
    $closing_reading = floatval($row['closing_reading']);
    $total_tested_fuel = floatval($row['total_tested_fuel']);
    $sales_price = floatval($row['sales_price']);
    
    $pump_id = intval($row['pump_id']);
                        $query1 = "SELECT id FROM shed_pump_assign WHERE pump_id = $pump_id AND status = 1  AND opening_reading = '$closing_reading'  AND  opening_reading <> '0.000' ";
                            $res = mysqli_query($con, $query1);
                            if ($res && mysqli_num_rows($res) > 0) {
                                $disable_status = 'readonly';
                                $xmessage = "<font color='red'>This pump's closing reading is used in another shift ,Cannot change it.</font>";
                            } else {
                                $disable_status = '';
                                $xmessage = '';
                            }
 
$closing_val = ($closing_reading > 0) ? $closing_reading : '';

    echo "<tr data-opening='{$opening_reading}' data-test-fuel='{$total_tested_fuel}' data-sales-price='{$sales_price}'>

            <td>" . htmlspecialchars($row['pump_name']) . "</td>
            <td>" . htmlspecialchars($row['p_name']) . "</td>
            <td align='center'><strong>" . implode(' ', str_split(htmlspecialchars($row['opening_reading']))) . "</strong></td>
            <td>
                <input type='hidden' name='pump_ids[]' value='" . intval($row['pump_assign_id']) . "'>   ".$xmessage."
                <input type='number'  ".$disable_status." placeholder='0000.000' style=' border:3px solid black !important;' step='0.001' min='{$opening_reading}' name='closing_readings[]' class='form-control closing-reading-input' required value='{$closing_val}'>
            </td>
            <td align='center' class='test-fuel-cell'>" . number_format($total_tested_fuel, 2) . "</td>
            <td align='center' class='fuel-sold-cell'>0.00</td> 
            <td align='center' class='sales-price-cell'>" . number_format($sales_price, 2) . "</td>
            <td align='right' style='padding-right:15px;'  class='line-total-cell'>0.00</td>
          </tr>";
}

echo "<tr><td colspan='7'> Total sales</td><td align='right'  class='total-cell'><input id='total_sales_calculated1' style='text-align:right; width:150px;' name='total_sales_calculated1' type='text' disabled class='form-control' value='0.00'> </td></tr>";

echo "</tbody></table>";
echo "<input id='total_sales_calculated' name='total_sales_calculated' type='hidden'  class='form-control' value='0.00'>";
 
?>
<script>
 $('#closeShiftModal').on('shown.bs.modal', function () {
  $("input[name='closing_readings[]']").eq(0).focus();
});
</script>

   

          