<?php
include '../db.php';
include '../auth.php';

$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-31 days'));
$end_date   = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$location_id = $_GET['location_id'] ?? 0;

// get client details
$sel_query="SELECT * FROM client_registration WHERE c_id = '$location_id'";
$result = mysqli_query($con,$sel_query);
$row = mysqli_fetch_assoc($result);
$client_name = $row['client_name'] ?? '';
$reg_no = $row['client_id'] ?? '';
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Day End Report (<?= htmlspecialchars($start_date) ?> to <?= htmlspecialchars($end_date) ?>)</title>
  <!-- ✅ Bootstrap 4 CDN -->
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
  <style>
    body { font-size: 13px; }
    h4 { margin-bottom: 10px; text-transform: capitalize; }
    .table th, .table td { padding: 5px; }
    .print-header { text-align: center; }
    @media print { .no-print { display:none; } }

      .table-bordered th,
.table-bordered td {
  border: 1px solid #000 !important; /* black border */
}

.table-bordered {
  border: 1px solid #000 !important; /* outer border */
}

  </style>
</head>
<body>
<div class="container-fluid">

  <!-- Print Button on Top -->
  <div class="no-print text-center mb-3">
    <button onclick="window.print()" class="btn btn-primary">
      <i class="fa fa-print"></i> Print
    </button>
  </div>

  <!-- Header -->
  <div class="print-header">
    <h4><?= htmlspecialchars($_SESSION['company']) ?></h4>
    <h4><?= htmlspecialchars($client_name) . " - " . htmlspecialchars($reg_no) ?></h4>
    <h4>Day end report</h4>
    <p>
      From <strong><?= htmlspecialchars($start_date) ?></strong> 
      To <strong><?= htmlspecialchars($end_date) ?></strong>  (Printed on: <?= date('Y-m-d H:i:s') ?>)
    </p>
   
  </div>

  <?php
  $sql = "SELECT id, location_id, serial_no, date_ended, created_by, created_on, status,
                 total_cash_settled, total_card_settled, total_credit_settle,
                 fuel_purchase, oil_purchase, fuel_sales, oil_sales,
                 fuel_short_excess, oil_short_excess 
          FROM day_end_process
          WHERE date_ended BETWEEN '$start_date' AND '$end_date'
            AND location_id='$location_id'
          ORDER BY date_ended ASC";
  $result = mysqli_query($con, $sql);
  ?>

  <!-- Table -->
   <!-- Table -->
  <table class="table table-bordered table-sm">
    <thead class="thead-light">
      <tr>
        <th>#</th>
        <th>No</th>
        <th>Date</th>
        <th>Fuel sales</th>
        <th>Oil sales</th>
        <th>Cash</th>
        <th>Card</th>
        <th>Credit</th>
        <th>Fuel sh/ex</th>
        <th>Oil sh/ex</th>
        <th>Fuel purchase</th>
        <th>Oil purchase</th>
        <th>Created on</th>
      </tr>
    </thead>
    <tbody>
    <?php
    if ($result && mysqli_num_rows($result) > 0) {
        $count = 1;

        // totals
        $t_fuel_sales = $t_oil_sales = $t_cash = $t_card = $t_credit = 0;
        $t_fuel_sh = $t_oil_sh = $t_fuel_purchase = $t_oil_purchase = 0;

        while ($row = mysqli_fetch_assoc($result)) {
            echo "<tr>";
            echo "<td class='text-center'>{$count}</td>";
            echo "<td class='text-center'>".htmlspecialchars($row['serial_no'])."</td>";
            echo "<td class='text-center'>".htmlspecialchars($row['date_ended'])."</td>";
            echo "<td class='text-right'>".number_format($row['fuel_sales'],2)."</td>";
            echo "<td class='text-right'>".number_format($row['oil_sales'],2)."</td>";
            echo "<td class='text-right'>".number_format($row['total_cash_settled'],2)."</td>";
            echo "<td class='text-right'>".number_format($row['total_card_settled'],2)."</td>";
            echo "<td class='text-right'>".number_format($row['total_credit_settle'],2)."</td>";
            echo "<td class='text-right'>".number_format($row['fuel_short_excess'],2)."</td>";
            echo "<td class='text-right'>".number_format($row['oil_short_excess'],2)."</td>";
            echo "<td class='text-right'>".number_format($row['fuel_purchase'],2)."</td>";
            echo "<td class='text-right'>".number_format($row['oil_purchase'],2)."</td>";
            echo "<td class='text-center'>".date('n-j g:ia', strtotime($row['created_on']))."</td>";
            echo "</tr>";

            // accumulate totals
            $t_fuel_sales     += $row['fuel_sales'];
            $t_oil_sales      += $row['oil_sales'];
            $t_cash           += $row['total_cash_settled'];
            $t_card           += $row['total_card_settled'];
            $t_credit         += $row['total_credit_settle'];
            $t_fuel_sh        += $row['fuel_short_excess'];
            $t_oil_sh         += $row['oil_short_excess'];
            $t_fuel_purchase  += $row['fuel_purchase'];
            $t_oil_purchase   += $row['oil_purchase'];

            $count++;
        }

        // totals row
        echo "<tr class='font-weight-bold bg-light'>";
        echo "<td colspan='3' class='text-right'>Total</td>";
        echo "<td class='text-right'>".number_format($t_fuel_sales,2)."</td>";
        echo "<td class='text-right'>".number_format($t_oil_sales,2)."</td>";
        echo "<td class='text-right'>".number_format($t_cash,2)."</td>";
        echo "<td class='text-right'>".number_format($t_card,2)."</td>";
        echo "<td class='text-right'>".number_format($t_credit,2)."</td>";
        echo "<td class='text-right'>".number_format($t_fuel_sh,2)."</td>";
        echo "<td class='text-right'>".number_format($t_oil_sh,2)."</td>";
        echo "<td class='text-right'>".number_format($t_fuel_purchase,2)."</td>";
        echo "<td class='text-right'>".number_format($t_oil_purchase,2)."</td>";
        echo "<td></td>";
        echo "</tr>";

    } else {
        echo "<tr><td colspan='13' class='text-center'>No records found.</td></tr>";
    }
    ?>
    </tbody>
  </table>

</div>
</body>
</html>
