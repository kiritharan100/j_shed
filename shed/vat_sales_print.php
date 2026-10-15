<?php
include('../db.php');
include('../auth.php');

if (!isset($_GET['iss_id'])) {
    die("Invalid request");
}

$iss_id = intval($_GET['iss_id']);

// Fetch master record
$master_sql = "SELECT `iss_id`, `location`, `to_location`, `invoice_no`, `tin_number`, `invoice_date_range`, `issue_date`, `issue_total`, `issue_status`, `day_end` 
               FROM `vat_sales_master` WHERE `iss_id` = $iss_id";
$master_result = mysqli_query($conn, $master_sql);
$master = mysqli_fetch_assoc($master_result);

// Fetch detail records
$details_sql = "SELECT d.*, p.p_name 
                FROM `vat_sales_detail` d 
                JOIN `product_master` p ON d.batch_id = p.p_id 
                WHERE d.iss_id = $iss_id";
$details_result = mysqli_query($conn, $details_sql);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Tax Invoice</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding: 20px; font-size: 14px; }
        @media print {
            .no-print { display: none; }
        }
        .invoice-box {
            /* border: 1px solid #000; */
            padding: 20px;
        }
        .signature-space {
            height: 80px;
            border-top: 1px solid #000;
            margin-top: 40px;
        }
    </style>
</head>
<body>

<div class="invoice-box">

<div class="text-center mb-4">



<?php 
$sql = "SELECT * FROM letter_head Where id = 1";
$_result = mysqli_query($conn, $sql);
$head = mysqli_fetch_assoc($_result);

?>



<div  align='right' class=" no-print" >
    <button onclick="window.print()" class="btn btn-primary">Print Invoice</button>
</div>


        <h5><strong><?php echo $head['entity']; ?></strong></h5>
        <h6><strong><?php echo $head['address']; ?></strong></h6>
        <h6><strong><?php echo $head['email']; ?></strong></h6>
        <h6> <?php echo $head['telephone']; ?> </h6>
     <div class="text-start">
        <h6>VAT Registration Number: <?php echo $head['vat_no']; ?></h6>
    </div>
    </div>


<hr>
    <div class="text-center mb-4">
        <h4><strong>TAX INVOICE</strong></h4>
    </div>

    <div class="row mb-3">
        <div class="col-sm-6">
            <strong>Invoice No:</strong> <?= $master['invoice_no'] ?><br>
            <strong>Invoice Date:</strong> <?= $master['issue_date'] ?><br>
            <strong>Invoice Period:</strong> <?= $master['invoice_date_range'] ?>
        </div>
        <div class="col-sm-6">
            <strong>Customer Name:</strong> <?= $master['to_location'] ?><br>
            <strong>Customer VAT No:</strong> <?= $master['tin_number'] ?><br>
        </div>
    </div>

    <table class="table table-bordered table-sm">
        <thead class="table-light">
    <tr>
        <th class="text-center">#</th>
        <th class="text-center">Item Name</th>
        <th class="text-center">VAT Rate (%)</th>
        <th class="text-center">Leter</th>
        <th class="text-center">Price</th>
        <th class="text-center">Value (excl. VAT)</th>
        <th class="text-center">VAT</th>
        <th class="text-center">Value (incl. VAT)</th>
    </tr>
</thead>
        <tbody>
            <?php
            $count = 1;
            $total_incl_vat = 0;
            $total_excl_vat = 0;
            $total_vat = 0;

            while ($row = mysqli_fetch_assoc($details_result)) {
                $qty = floatval($row['issue_qty']);
                $rate = floatval($row['sales_price']);
                $vat_rate = floatval($row['vat_rate']);

                $value_incl = $qty * $rate;
                $value_excl = $value_incl / (1 + $vat_rate / 100);
                $vat_value = $value_incl - $value_excl;

                $total_incl_vat += $value_incl;
                $total_excl_vat += $value_excl;
                $total_vat += $vat_value;

                echo "<tr>
                        <td>{$count}</td>
                        <td>{$row['p_name']}</td>
                        <td style='text-align:center;' >{$vat_rate}%</td>
                        <td style='text-align:center;'>{$qty}</td>
                        <td align='right'>" . number_format($rate, 2) . "</td>
                          <td align='right'>" . number_format($value_excl, 2) . "</td>
                          <td align='right'>" . number_format($vat_value, 2) . "</td>
                        <td align='right'>" . number_format($value_incl, 2) . "</td>
                      
                    </tr>";
                $count++;
            }
            ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="5" class="text-end">SUB Total </th>
                <th colspan="1" style='text-align:right;'><?= number_format($total_excl_vat, 2) ?></th>
                <th colspan="1"> </th>
                <th colspan="1" style='text-align:right;'><?= number_format($total_incl_vat, 2) ?></th>
               
            </tr>
            <tr>
                <th colspan="5" class="text-end">VAT Total</th>
                <th colspan="1" style='text-align:right;'><?= number_format($total_vat, 2) ?></th>
                 <th colspan="1"> </th>
                  <th colspan="1"> </th>
            </tr>
            <tr>
                <th colspan="5" class="text-end">Total With VAT  </th>
                <th colspan="1" style='text-align:right;'><?= number_format($total_incl_vat, 2) ?></th>
                <th colspan="1"> </th>
                <th colspan="1"> </th>
            </tr>
        </tfoot>
    </table>

    <div class="row mt-5">
        <div class="col-6">
            <p><strong> Manager</strong></p><br>
            <div class="signature-space"></div>
        </div>
    </div>
</div>



</body>
</html>
