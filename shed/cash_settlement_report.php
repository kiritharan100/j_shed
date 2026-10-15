 <?php
// ====================== HEADER INCLUDE ======================
if (isset($_GET['module']) && $_GET['module'] === 'report') {
    include '../reports/header.php';
} else {
    include 'header.php';
}

     $query = "SELECT MAX(date_ended) AS day_end_date 
          FROM day_end_process 
          WHERE status = 1 AND location_id = '$location_id'";
$result = mysqli_query($con, $query);
$row = mysqli_fetch_assoc($result);

$day_end_date = $row['day_end_date'];

$selected_date = isset($_GET['date']) ? $_GET['date'] : $day_end_date;
$from_month_start = date('Y-m-01', strtotime($selected_date));
$yesterday = date('Y-m-d', strtotime($selected_date . ' -1 day'));



// ====================== Settlemetn======================
$report_start_date = '2025-10-01';

if($report_start_date <= $selected_date){

                $q = "
                    SELECT 
                        (SELECT SUM(amount) 
                         FROM shed_settlement 
                         WHERE location_id = '$location_id' 
                           AND set_date BETWEEN '$report_start_date1' AND '$yesterday'
                           AND status = 1
                        ) AS Total_settlement,
                        
                        (SELECT SUM(total_cash_settled) 
                         FROM day_end_process 
                         WHERE location_id = '$location_id' 
                           AND date_ended BETWEEN '$report_start_date' AND '$yesterday'
                           AND status = 1
                        ) AS TotalCashSettled
                ";
                
                $res = mysqli_query($con, $q);
                $row = mysqli_fetch_assoc($res);
                
                $Total_settlement = $row['Total_settlement'] ?? 0;
                $TotalCashSales   = $row['TotalCashSettled'] ?? 0;
                $balance_payable_today  = $TotalCashSales - $Total_settlement;
                
                

                $q = "
                    SELECT 
                        (SELECT SUM(amount) 
                         FROM shed_settlement 
                         WHERE location_id = '$location_id' 
                           AND set_date >= '$report_start_date1' AND set_date < '$from_month_start'
                           AND status = 1
                        ) AS Total_settlement,
                        
                        (SELECT SUM(total_cash_settled) 
                         FROM day_end_process 
                         WHERE location_id = '$location_id' 
                           AND date_ended >= '$report_start_date' AND date_ended< '$from_month_start'
                           AND status = 1
                        ) AS TotalCashSettled
                ";
                
                $res = mysqli_query($con, $q);
                $row = mysqli_fetch_assoc($res);
                
                $Total_settlement = $row['Total_settlement'] ?? 0;
                $TotalCashSales   = $row['TotalCashSettled'] ?? 0;
                $balance_payable_opening  = $TotalCashSales - $Total_settlement;


            $q1 = "SELECT SUM(amount) as Total_settlement
                    FROM shed_settlement 
                    WHERE location_id='$location_id' AND set_date BETWEEN '2025-08-01' AND '$selected_date' and status = 1
                    ORDER BY set_date ";
              $res1 = mysqli_query($con, $q1);
              
              
              $row1 = mysqli_fetch_assoc($res1);
              $q2 = "SELECT SUM(total_cash_settled) AS TotalCashSettled
                       FROM day_end_process
                       WHERE location_id = '$location_id' 
                         AND date_ended  BETWEEN '$report_start_date' AND '$selected_date'
                         AND status = 1";
                
                $res2 = mysqli_query($con, $q2);
                $row2 = mysqli_fetch_assoc($res2);
                
                 $TotalCashSales = $row2['TotalCashSettled'];
                 $Total_settlement = $row1['Total_settlement'];
                 $balance_payable = $TotalCashSales - $Total_settlement;
                 
            $q4 = "SELECT SUM(amount) as Total_settlement_this_month
                    FROM shed_settlement 
                    WHERE location_id='$location_id' AND set_date BETWEEN '$from_month_start' AND '$selected_date' and status = 1
                    ORDER BY set_date ";
              $res4 = mysqli_query($con, $q4);
              $row4 = mysqli_fetch_assoc($res4);
                $Total_settlement_this_month = $row4['Total_settlement_this_month'];
                
                            $q4 = "SELECT SUM(amount) as Total_settlement_this_month
                    FROM shed_settlement 
                    WHERE location_id='$location_id' AND set_date = '$selected_date' and status = 1
                    ORDER BY set_date ";
              $res4 = mysqli_query($con, $q4);
              $row4 = mysqli_fetch_assoc($res4);
                $Total_settlement_today = $row4['Total_settlement_this_month'];


}



// ====================== TODAY VALUES ======================
$q_today = mysqli_query($con, "
    SELECT 
        SUM(total_cash_settled) AS cash_sales_today,
        SUM(total_credit_settle) AS credit_sales_today,
        SUM(total_card_settled) AS card_sales_today
    FROM day_end_process
    WHERE status = 1
      AND location_id = '$location_id'
      AND DATE(date_ended) = '$selected_date'
");

$row_today = mysqli_fetch_assoc($q_today);
$cash_today   = floatval($row_today['cash_sales_today']);
$credit_today = floatval($row_today['credit_sales_today']);
$card_today   = floatval($row_today['card_sales_today']);
$total_today  = $cash_today + $credit_today + $card_today;

// ====================== UP TO YESTERDAY VALUES ======================
$q_yesterday = mysqli_query($con, "
    SELECT 
        SUM(total_cash_settled) AS cash_sales_yesterday,
        SUM(total_credit_settle) AS credit_sales_yesterday,
        SUM(total_card_settled) AS card_sales_yesterday
    FROM day_end_process
    WHERE status = 1
      AND location_id = '$location_id'
      AND DATE(date_ended) BETWEEN '$from_month_start' AND '$yesterday'
");

$row_yest = mysqli_fetch_assoc($q_yesterday);
$cash_yest   = floatval($row_yest['cash_sales_yesterday']);
$credit_yest = floatval($row_yest['credit_sales_yesterday']);
$card_yest   = floatval($row_yest['card_sales_yesterday']);
$total_yest  = $cash_yest + $credit_yest + $card_yest;

// ====================== TOTAL UP TO TODAY ======================
$cash_total   = $cash_today + $cash_yest;
$credit_total = $credit_today + $credit_yest;
$card_total   = $card_today + $card_yest;
$grand_total  = $cash_total + $credit_total + $card_total;

// ====================== CASH PAID / BALANCE ======================
// NOTE: Replace these placeholders with actual queries from your payment table if available
$cash_paid_today = $cash_today;
$cash_balance_today = $cash_today;
?>

<style>
     @media print {
         @page {
    size: landscape;
  }
  .card {
    margin-top: -145px; /* Adjust the value as needed */
    margin-left: -50px; /* Adjust the value as needed */
        }}
  @media print {
    .no-print { display: none !important; }
    .print-only { display: block !important; }
  }
  @media screen {
    .print-only { display: none !important; }
  }
  table {
    width: 100%;
    border-collapse: collapse;
    font-family: 'Arial Narrow', Arial, sans-serif;
    font-size: 16px;
  }
  th, td {
    border: 1px solid #999;
    padding: 2px 8px;
    text-align: left;
    text-align: right;
  }
  th {
    background: #f4f4f4;
    text-align: center;
  }
  .text-center { text-align: center; }
  .text-right { text-align: right; }
  .text-left { text-align: left; }
</style>

<style>
 
.text-left { text-align: left; }
</style>

<div class="content-wrapper">
    <div class="container-fluid">
        <div class="card" style='padding:50px;'>
        <div class="row no-print">
            <div class="col-sm-12">
                <form method="GET" class="form-inline float-right">
                    <label class="mr-2">Select Date:</label>
                    <?php if (isset($_GET['module']) && $_GET['module'] === 'report') { ?>
                        <input type="hidden" name="module" value="report">
                    <?php } ?>
                    <input type="date" name="date" value="<?= $selected_date ?>" max="<?= date('Y-m-d') ?>" required class="form-control mr-2">
                    <button type="submit" class="btn btn-success">Search</button>
                    <button type="button" class="btn btn-primary ml-2" onclick="window.print()">Print</button>
                </form>
                <hr>
            </div>
        </div>

        <!-- Report Title -->
         <div align='right'> <h4>F 9A</h4> </div>
        <div align="center">
            <h5><?= $_SESSION['company'] ?> | <?= $client_name; ?> </h5>
            <h5>Daily Cash and Transactions <?= date('d-m-Y', strtotime($selected_date)) ?></h5>
        </div>

        

        <!-- First Table -->
        <table>
            <tr>
                <th class="text-left">Description</th>
                <th>Cash Sales</th>
                <th>Credit Sales</th>
                <th>Card Sales</th>
                <th>Total</th>
            </tr>
            <tr>
                <td class="text-left">Cash Sales</td>
                <td><?= number_format($cash_today, 2) ?></td>
                <td></td>
                <td></td>
                <td><?= number_format($cash_today, 2) ?></td>
            </tr>
            <tr>
                <td class="text-left">Credit Sales</td>
                <td></td>
                <td><?= number_format($credit_today, 2) ?></td>
                <td></td>
                <td><?= number_format($credit_today, 2) ?></td>
            </tr>
            <tr>
                <td class="text-left">Card Sales</td>
                <td></td>
                <td></td>
                <td><?= number_format($card_today, 2) ?></td>
                <td><?= number_format($card_today, 2) ?></td>
            </tr>
            <tr>
                <td class="text-left"><b>Total</b></td>
                <td><?= number_format($cash_today, 2) ?></td>
                <td><?= number_format($credit_today, 2) ?></td>
                <td><?= number_format($card_today, 2) ?></td>
                <td><?= number_format($total_today, 2) ?></td>
            </tr>
            <tr>
                <td class="text-left">Total Up to Yesterday</td>
                <td><?= number_format($cash_yest, 2) ?></td>
                <td><?= number_format($credit_yest, 2) ?></td>
                <td><?= number_format($card_yest, 2) ?></td>
                <td><?= number_format($total_yest, 2) ?></td>
            </tr>
            <tr>
                <td class="text-left"><b>Grand Total</b></td>
                <td><?= number_format($cash_total, 2) ?></td>
                <td><?= number_format($credit_total, 2) ?></td>
                <td><?= number_format($card_total, 2) ?></td>
                <td><?= number_format($grand_total, 2) ?></td>
            </tr>
        </table>

        <br><br>

        <!-- Second Table -->
        <table>
            <tr>
                
                <th>Up to Yesterday</th>
                <th class="text-left">Description</th>
                <th>Today</th>
                <th>Total up to Today</th>
                 <th></th>
                <th>Today</th>
                <th>Up to Today</th>
            </tr>
            <tr>
                 <td><?= number_format($cash_yest, 2) ?></td>
                <td class="text-left">Cash Sales</td>
               
                <td><?= number_format($cash_today, 2) ?></td>
                <td><?= number_format($cash_total, 2) ?></td>
                <td class="text-left">Cash Paid</td>
                <td> <?=  number_format($Total_settlement_today,2); ?></td>
                <td><?=  number_format($Total_settlement_this_month,2); ?></td>
            </tr>
            <!-- <tr>
                <td class="text-left"><b>Total</b></td>
                <td><?= number_format($cash_yest, 2) ?></td>
                <td><?= number_format($cash_today, 2) ?></td>
                <td><?= number_format($cash_total, 2) ?></td>
                <td></td>
                <td></td>
                <td></td>
            </tr> -->
            <tr>
                <td> </td>
                <td class="text-left">Cash Up to Yesterday</td>
            
                <td><?= number_format($cash_yest, 2) ?></td>
                
                <td> </td>
                <td class="text-left">Cash Balance</td>
                <td> <?= number_format($balance_payable,2); ?></td>
                <td><?= number_format($balance_payable,2); ?> </td>
            </tr>
            <tr>
                 <td></td>
                <td class="text-left"><b>Grand Total</b></td>
                
                 <td><b><?= number_format($cash_total, 2) ?></b></td>
               
                <td></td>
                <td><b>Grand Total</b></td>
                <td><b><?= number_format($cash_today, 2) ?></b></td>
                <td><b><?= number_format($cash_total, 2) ?></b></td>
            </tr>
            <tr>
                <td><?= number_format($credit_yest, 2) ?></td>
                <td class="text-left">Credit Sales</td>
                
                <td><?= number_format($credit_today, 2) ?></td>
                <td><?= number_format($credit_total, 2) ?></td>
                <td>Opening Balance    </td>
                <td>Today: <?= number_format($balance_payable_today,2) ?></td>
                 <td>Month Start: <?= number_format($balance_payable_opening,2) ?></td>
            </tr>
            <tr>
                  <td><?= number_format($card_yest, 2) ?></td>
                <td class="text-left">Card Sales</td>
              
                <td><?= number_format($card_today, 2) ?></td>
                <td><?= number_format($card_total, 2) ?></td>
                <td></td>
                <td></td>
                 <td></td>
            </tr>
        </table>

           <div id="footer_section" style="margin-top: 10px; font-size: 14px;">
     <p style="margin-bottom: 10px;">
        <em>
        <b>    "I confirm that all the transactions in this statement are correct and the stock shown is under my control." </b>
        </em>
    </p>
    <table style="width:100%; border: none;">
        <tr>
            <td style="border:none;">
                <strong>Date:</strong> <?= date('d-m-Y') ?>
            </td>
            <td style="border:none; text-align: right;">
                  ____________________________
            </td>
        </tr>
        <tr>
            <td style="border:none;"></td>
            <td style="border:none; text-align: right;"><strong>Manager</strong></td>
        </tr>
          <tr>
            <td style="border:none;">
                <strong>I, _______________, hereby acknowledge the receipt of Rs. __________________ in cash.
            </td>
            <td style="border:none; text-align: right;">
                  ____________________________
            </td>
        </tr>
        <tr>
            <td style="border:none;"></td>
            <td style="border:none; text-align: right;"><strong>Cashier</strong></td>
        </tr>
    </table>
</div>





    </div>
</div>

<?php include 'footer.php'; ?>
