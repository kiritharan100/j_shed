<?php
include '../../db.php';

$location_id = $_POST['location_id'];
$start = $_POST['start'];
$end = $_POST['end'];

$query = "
    SELECT o.op_name, COUNT(*) AS total_cards, SUM(s.amount) AS total_amount
    FROM shed_card_sales s
    JOIN shed_operator_shift os ON s.shift_id = os.shift_id
    LEFT JOIN manage_pump_operator o ON os.operator_id = o.op_id
    WHERE s.status = 1
      AND s.location_id = $location_id
      AND s.created_on BETWEEN '$start' AND '$end'
    GROUP BY os.operator_id
    ORDER BY o.op_name ASC
";

$result = mysqli_query($con, $query);

if (mysqli_num_rows($result) > 0) {
    echo "<table class='table table-bordered table-striped'>";
    echo "<thead><tr><th>Operator</th><th>Total Cards</th><th>Total Amount</th></tr></thead><tbody>";
    while ($row = mysqli_fetch_assoc($result)) {
        $total += $row['total_amount'];
        $total_card += $row['total_cards'];
        echo "<tr>
                <td>" . htmlspecialchars($row['op_name']) . "</td>
                <td align='center'>" . $row['total_cards'] . "</td>
                <td align='right'>" . number_format($row['total_amount'], 2) . "</td>
              </tr>";
    }
     echo "<tr>
                <td align='center'><b> Total</b></td>
                <td align='center'><b>".$total_card."</b></td>
                <td align='right'><b>" . number_format($total, 2) . "</b></td>
              </tr>";
              
    echo "</tbody></table>";
} else {
    echo "<div class='alert alert-info'>No data found for the selected period.</div>";
}
?>
