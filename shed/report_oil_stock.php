<?php
 if (isset($_GET['module']) && $_GET['module'] === 'report') {
        include '../reports/header.php';
    } else {
        include 'header.php';
    }
$as_at_date = isset($_GET['as_at']) ? $_GET['as_at'] : date('Y-m-d');
$as_at_datetime = $as_at_date . ' 23:59:59';
$total_purchase_value = 0;
$total_sales_value = 0;
?>

<style>
 

   
    @media print {
  .card {
    margin-top: -100px; /* Adjust the value as needed */
    margin-left: -12px; /* Adjust the value as needed */
        }
      }
  @media print {
    .no-print { display: none !important; }
    .print-only { display: block !important; }
  }
  @media screen {
    .print-only { display: none !important; }
  }
  /* .table td, .table th {
    border: 1px solid #999;
    padding: 0px 8px;
    font-size: 13px;
  } */

  .table td, .table th {
    border: 1px solid #999;
    padding: 2px 6px;       /* Less padding for compact rows */
    font-size: 14px;        /* Smaller but still readable */
    line-height: 1.3;       /* Reduced vertical spacing */
    font-family: 'Arial Narrow', Arial, sans-serif;  /* Space-efficient font */
}


  .table th {
    border: 1px solid #999;
    text-align: center;
    background: #f4f4f4;
  }
  .text-right { text-align: right; }
  .text-center { text-align: center; }
  .font-weight-bold { font-weight: bold; }
</style>

<div class="content-wrapper">
  <div class="container-fluid">
    <div class="main-header">
     
    </div>
    <div class="card">
      <div class="card-block">
        <div class='no-print'>
          <form method="get" class="form-inline mb-3">
            <label class="mr-2">As at Date:</label>
            <?php   if (isset($_GET['module']) && $_GET['module'] === 'report') {
                                        echo "<input type='hidden' name='module' value='report'>";  }?>
            <input type="date" name="as_at" class="form-control mr-2" value="<?= $as_at_date ?>" required>
            <button type="submit" class="btn btn-primary">Generate</button>
           
            <button class="btn btn-primary" onclick="window.print()"><i class="fa fa-print"></i> Print</button>
          </form>
          <hr>
        </div>
        
     <div align='center'>
         <h4 class="mt-4"><?= $_SESSION['company'] ?> <br> <?= $client_name; ?> - <?= $reg_no; ?>  </h4>
           Other Stock Report as at <?= date('d-m-Y', strtotime($as_at_date)) ?> 
      </div>

        <div class="table-responsive">
          <table class="table table-bordered table-striped" id="oil_stock_table">
            <thead>
              <tr>
                  <th>#</th>
                <th  style='width:350px;'>Item Name</th>
                <th>Batch ID</th>
                <th>Qty</th>
                <th>Purchase Price</th>
                <th>Purchase Value</th>
                <th>Sales Price</th>
                <th>Sales Value</th>
                 <th style='width:100px;'>Physical Qty</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $count = 1;
              $sql = "
                SELECT 
                  s.batch_id, s.price, s.sales_price, s.s_id,
                  SUM(s.purchase_qty) AS purchase_qty,
                  p.p_name
                FROM oil_stock s
                JOIN oil_grn_master g ON s.grn_id = g.grn_id AND g.location='$location_id'
                JOIN product_master p ON s.s_id = p.p_id AND store_id ='$location_id'
                WHERE g.grn_date <= '$as_at_datetime' 
                  AND s.grn_status = 1 
                  AND g.grn_status = 1
                  AND s.store_id = '$location_id' 
                GROUP BY s.batch_id
                ORDER BY  p_name
              ";
              $res = mysqli_query($con, $sql);
              while ($row = mysqli_fetch_assoc($res)) {
                $batch_id = $row['batch_id'];
                $item_name = $row['p_name'];
                $purchase_price = floatval($row['price']);
                $sales_price = floatval($row['sales_price']);
                $initial_qty = floatval($row['purchase_qty']);

                // Total sales up to the selected date
                $sales_sql = "
                  SELECT SUM(d.issue_qty) AS total_sales
                  FROM oil_sales_detail d
                  JOIN oil_sales_master m ON d.iss_id = m.iss_id AND m.location = '$location_id'
                  WHERE d.batch_id = '$batch_id' 
                    AND m.issue_date <= '$as_at_datetime'
                    AND m.issue_status = 1 
                    AND d.status = 1
                ";
                $sales_result = mysqli_query($con, $sales_sql);
                $sales_row = mysqli_fetch_assoc($sales_result);
                $sales_qty = floatval($sales_row['total_sales']);

                // Total adjustment up to the selected date
                $adjust_sql = "
                  SELECT SUM(adj_qty) AS total_adjustment
                  FROM oil_stock_adjustment
                  WHERE batch_id = '$batch_id' 
                    AND adj_on <= '$as_at_datetime'
                ";
                $adjust_result = mysqli_query($con, $adjust_sql);
                $adjust_row = mysqli_fetch_assoc($adjust_result);
                $adj_qty = floatval($adjust_row['total_adjustment']);

                // Calculate final stock
                $final_qty = $initial_qty - $sales_qty - $adj_qty;
                if ($final_qty <= 0) continue;

                $purchase_value = $final_qty * $purchase_price;
                $sales_value = $final_qty * $sales_price;
                $total_purchase_value += $purchase_value;
                $total_sales_value += $sales_value;

                echo "<tr>
                       <td>{$count}</td>
                        <td>{$item_name}</td>
                        <td>{$batch_id}</td>
                        <td class='text-right'>" . number_format($final_qty, 2) . "</td>
                        <td class='text-right'>" . number_format($purchase_price, 2) . "</td>
                        <td class='text-right'>" . number_format($purchase_value, 2) . "</td>
                        <td class='text-right'>" . number_format($sales_price, 2) . "</td>
                        <td class='text-right'>" . number_format($sales_value, 2) . "</td>
                         <td class='text-right'> </td>
                      </tr>";
                      $count ++ ;
              } 
              ?>
            </tbody>
            <tfoot>
              <tr >
                <th style="background-color:#dfe5e5; font-weight: bold;" colspan="5" class="text-right">Total</th>
                <th  style="background-color:#dfe5e5; font-weight: bold; text-align:right;"  ><b><?= number_format($total_purchase_value, 2) ?></b></th>
                <th style="background-color:#dfe5e5" ></th>
                <th style="background-color:#dfe5e5; font-weight: bold; text-align:right;"  ><b><?= number_format($total_sales_value, 2) ?></b></th>
                <td style="background-color:#dfe5e5"  > </td>
              </tr>
            </tfoot>
          </table>
          
          
          
          <div id="footer_section" style="margin-top: 10px; font-size: 14px;">
   
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
    </table>
</div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include("footer.php"); ?>

 
