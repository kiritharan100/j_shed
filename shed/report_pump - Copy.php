<?php
include 'header.php';

$day_end = isset($_GET['day_end']) ? $_GET['day_end'] : '';
$from_date = isset($_GET['from']) ? $_GET['from'] : date('Y-m-d');
$to_date = isset($_GET['to']) ? $_GET['to'] : date('Y-m-d');

$where = "sos.location_id = '$location_id' AND sos.status = 1";
if ($day_end !== '') {
    $where .= " AND sos.day_end = '$day_end'";
} else {
    $where .= " AND DATE(sos.end_time) BETWEEN '$from_date' AND '$to_date'";
}

$query = "
SELECT spa.*, sos.shift_no, sos.end_time, mp.pump_name, pm.p_name
FROM shed_pump_assign spa
JOIN shed_operator_shift sos ON spa.shift_id = sos.shift_id
JOIN manage_pump mp ON spa.pump_id = mp.id
JOIN product_master pm ON spa.product_id = pm.p_id
WHERE $where AND spa.status = 1 AND mp.status = 1 AND pm.status = 1
ORDER BY sos.end_time ASC, mp.pump_name
";

$result = mysqli_query($con, $query);
?>

<div class="content-wrapper">
  <div class="container-fluid">
    <div class="main-header">
      <h4>Pump Sales Report</h4>
    </div>

    <form method="get" class="form-inline mb-3">
      <div class="form-group mr-2">
        <label>Day End No: </label>
        <input type="text" name="day_end" class="form-control ml-2" value="<?= htmlspecialchars($day_end) ?>">
      </div>

      <div id="date-range" <?= $day_end !== '' ? 'style=\"display:none\"' : '' ?> >
        <label class="mr-2">From:</label>
        <input type="date" name="from" value="<?= $from_date ?>" class="form-control mr-3">
        <label class="mr-2">To:</label>
        <input type="date" name="to" value="<?= $to_date ?>" class="form-control mr-3">
      </div>

      <button type="submit" class="btn btn-primary">Load Report</button>
    </form>


 <div class="row">
      <div class="col-md-12">
        <div class="card">
          <div class="card-block">
    
    <table class="table table-bordered table-striped">
      <thead>
        <tr>
          <th>Shift No</th>
          <th>Date</th>
          <th>Pump Name</th>
          <th>Fuel</th>
          <th>Opening Reading</th>
          <th>Closing Reading</th>
          <th>Net Reading</th>
          <th>Test Fuel</th>
          <th>Sold Unit</th>
          <th>Price</th>
          <th>Total Value</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $total_sales = 0;
        $total_unit = 0;
        while ($row = mysqli_fetch_assoc($result)):
          $net_reading = $row['closing_reading'] - $row['opening_reading'];
          $sales_unit = $row['fuel_sales'];
          $total_value = $sales_unit * $row['sales_price'];
          $avg_price = $sales_unit > 0 ? $total_value / $sales_unit : 0;
          $total_sales += $total_value;
          $total_unit += $sales_unit;
        ?>
          <tr>
            <td><?= $row['shift_no'] ?></td>
            <td><?= date('Y-m-d', strtotime($row['end_time'])) ?></td>
            <td><?= $row['pump_name'] ?></td>
            <td><?= $row['p_name'] ?></td>
            <td class="text-right"><?= number_format($row['opening_reading'], 2) ?></td>
            <td class="text-right"><?= number_format($row['closing_reading'], 2) ?></td>
            <td class="text-right"><?= number_format($net_reading, 2) ?></td>
            <td class="text-right"><?= number_format($row['total_test_reading'], 2) ?></td>
            <td class="text-right"><?= number_format($sales_unit, 2) ?></td>
            <td class="text-right"><?= number_format($avg_price, 2) ?></td>
            <td class="text-right"><?= number_format($total_value, 2) ?></td>
          </tr>
        <?php endwhile; ?>
      </tbody>
      <tfoot>
        <tr class="font-weight-bold bg-info text-white">
          <td colspan="8" class="text-right">Total</td>
          <td class="text-right"><?= number_format($total_unit, 2) ?></td>
          <td></td>
          <td class="text-right"><?= number_format($total_sales, 2) ?></td>
        </tr>
      </tfoot>
    </table>

    <?php
    $detail_query = "SELECT shift_no, end_time, total_sales, cash_received, total_card_sales, total_credit_sales, exces_short 
                     FROM shed_operator_shift 
                     WHERE location_id = '$location_id' AND status = 1";

    if ($day_end !== '') {
        $detail_query .= " AND day_end = '$day_end'";
    } else {
        $detail_query .= " AND DATE(end_time) BETWEEN '$from_date' AND '$to_date'";
    }

    $detail_query .= " ORDER BY end_time ASC";

    $detail_result = mysqli_query($con, $detail_query);

    $total_sales = $cash_sales = $card_sales = $credit_sales = $excess_short = 0;
    ?>

    <h5 class="mt-4">Sales Summary by Shift</h5>
    <table class="table table-bordered table-striped">
      <thead>
        <tr class="table-secondary">
          <th>Shift No</th>
          <th>Date</th>
          <th>Total Sales</th>
          <th>Cash Sales</th>
          <th>Card Sales</th>
          <th>Credit Sales</th>
          <th>Excess / Short</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($row = mysqli_fetch_assoc($detail_result)):
          $total_sales += $row['total_sales'];
          $cash_sales += $row['cash_received'];
          $card_sales += $row['total_card_sales'];
          $credit_sales += $row['total_credit_sales'];
          $excess_short += $row['exces_short'];
        ?>
        <tr>
          <td><?= $row['shift_no'] ?></td>
          <td><?= date('Y-m-d', strtotime($row['end_time'])) ?></td>
          <td class="text-right"><?= number_format($row['total_sales'], 2) ?></td>
          <td class="text-right"><?= number_format($row['cash_received'], 2) ?></td>
          <td class="text-right"><?= number_format($row['total_card_sales'], 2) ?></td>
          <td class="text-right"><?= number_format($row['total_credit_sales'], 2) ?></td>
          <td class="text-right"><?= number_format($row['exces_short'], 2) ?></td>
        </tr>
        <?php endwhile; ?>
      </tbody>
      <tfoot>
        <tr class="bg-info text-white font-weight-bold">
          <td colspan="2" class="text-right">Total</td>
          <td class="text-right"><?= number_format($total_sales, 2) ?></td>
          <td class="text-right"><?= number_format($cash_sales, 2) ?></td>
          <td class="text-right"><?= number_format($card_sales, 2) ?></td>
          <td class="text-right"><?= number_format($credit_sales, 2) ?></td>
          <td class="text-right"><?= number_format($excess_short, 2) ?></td>
        </tr>
      </tfoot>
    </table>

  </div>
</div>
</div>
</div>
</div>
</div>

<script>
  document.querySelector('[name="day_end"]').addEventListener('input', function () {
    document.getElementById('date-range').style.display = this.value.trim() === '' ? 'block' : 'none';
  });
</script>

<?php include 'footer.php'; ?>