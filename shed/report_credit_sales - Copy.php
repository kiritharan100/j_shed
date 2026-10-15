<?php include 'header.php'; ?>
<style>
  @media print {
 
     

    .no-print {
      display: none !important;
    }
  }
  .no-scroll-on-print {
      overflow: hidden !important;
    }

     @media print {
    .print-only {
      display: block !important;
    }
  }

  @media screen {
    .print-only {
      display: none !important;
    }
  }
</style>
 


<?php
if (isset($_REQUEST['from_date'])) {
    $from_date = $_REQUEST['from_date'] . ' 00:00:00';
    $to_date   = $_REQUEST['to_date']   . ' 23:59:59';
} else {
    $from_date = date('Y-m-d') . ' 00:00:00';
    $to_date   = date('Y-m-d') . ' 23:59:59';
}
 
$sales_q = mysqli_query($con, "
    SELECT 
        cs.cs_id,
        cs.date_time,
        cs.ref_no,
        cs.vehicle_no,
        cs.total_sales,
        cs.status,
        c.customer_name,
        cs.inv_id,
        cs.invoice_no,
        cs.day_end,
        GROUP_CONCAT(pm.p_name ORDER BY sd.id SEPARATOR ', ') AS items_sold,
        GROUP_CONCAT(sd.qty ORDER BY sd.id SEPARATOR ', ') AS quantities
    FROM shed_credit_sales cs
    JOIN mange_customer c ON cs.c_id = c.c_id
    LEFT JOIN shed_credit_sales_detail sd ON cs.cs_id = sd.cs_id
    LEFT JOIN product_master pm ON sd.p_id = pm.p_id
    WHERE cs.location_id = $location_id
      AND cs.date_time BETWEEN '$from_date' AND '$to_date'AND cs.status = 1
    GROUP BY cs.cs_id
    ORDER BY cs.date_time DESC
");
 

?>



<div class="content-wrapper">
   <div class="container-fluid">

      <div class="row  no-print">
         <div class="col-sm-12">
            <div class="main-header" >
               <h4>Credit Card Sales - 9C / <?= $client_name ?></h4>
            </div>
         </div>
      </div>

      <div class="row">
         <div class="col-sm-12">
            <div class="card">
               <div class="card-header no-print" align="right">
                  <form method="GET">
                     From:
                     <input type="date" name="from_date" value="<?= isset($_REQUEST['from_date']) ? $_REQUEST['from_date'] : date('Y-m-d') ?>" required>
                     To:
                     <input type="date" name="to_date" value="<?= isset($_REQUEST['to_date']) ? $_REQUEST['to_date'] : date('Y-m-d') ?>" required>
                     <button type="submit" class="btn btn-success">Search</button>
                     <!-- <button type="button" id="exportButton" filename="VAT_Report.xlsx" class="btn btn-primary">
                        <i class="ti-cloud-down"></i> Export
                     </button> -->

                  <button type="button" class="btn btn-primary"  onclick="window.print()">Print</button>

                     
                  </form>
               </div>

               <div class="card-block">
                  <div class="row">

                  

                  <div class="col-sm-12 table-responsive no-scroll-on-print">

                        <div align='center'>
                           <h4>
                              <?php echo $_SESSION['company']; ?> <br>
                              <?php echo $client_name." - ".$reg_no; ?>
                           </h4>
                        </div>
                        <div align='right' style='position: relative; left: -50px;'> <h5>Card - 9C</h5>
                           <h6 style='position: relative; left: -50px;'>
                               Date:
<?php 

$from_date_only = date('Y-m-d', strtotime($from_date));
$to_date_only = date('Y-m-d', strtotime($to_date));

if ($from_date_only === $to_date_only) {
    echo $date_show = date('d-m-Y', strtotime($from_date));
} else {
   echo $date_show = date('d-m-Y', strtotime($from_date)) . ' - ' . date('d-m-Y', strtotime($to_date));
}
?>
                           
                           </h6>
                        </div>



                          <table   class="table table-bordered table-hover" style="width:100%">
        <thead>
          <tr>
            <th>#</th>
            <!-- <th>ID</th> -->
       
            <th>Date & Time</th>
              <th>Order No</th>
              <th>Invoice No</th>
              <th>Vehicle No</th>
            <th>Customer</th>
            <th>Item Sold</th>
          <th>Quantity</th>
            <th>Total Sales</th>
          
            
          
          </tr>
        </thead>
        <tbody>
          <?php
          $count = 1;
           
          while($sale = mysqli_fetch_assoc($sales_q)) {
    $is_cancelled = $sale['status'] == 0;
    $row_style = $is_cancelled ? 'class="table-danger cancelled-row"' : '';
    $cancel_button = $is_cancelled
        ? '<span  >Cancelled</span>'
        : "<button class='btn btn-danger btn-sm py-0 cancel-sale-btn' style='padding: 2px 8px; font-size: 12px;' data-sale-id='{$sale['cs_id']}'>Cancel</button>";
  $amount_display = $is_cancelled
    ? '<span style="text-decoration: line-through; color: #a94442;">' . number_format($sale['total_sales'], 2) . '</span>'
    : '<span class="sale-amount" data-value="' . $sale['total_sales'] . '">' . number_format($sale['total_sales'], 2) . '</span>';




            $status_text = $sale['status'] == 1 ? 'Open' : 'Closed';
            echo "<tr $row_style >";
            echo "<td align='center'>{$count}</td>";
            echo "<td align='center'>" . date('Y-m-d', strtotime($sale['date_time'])) . "</td>";
            echo "<td align='center'>" . htmlspecialchars($sale['ref_no']) . "</td>";
            echo "<td align='center'>" . htmlspecialchars($sale['invoice_no']) . "</td>";
            echo "<td align='center'>" . htmlspecialchars($sale['vehicle_no']) . "</td>";
             echo "<td style='padding-left:5px;'>" . htmlspecialchars($sale['customer_name']) . "</td>";
             echo "<td style='padding-left:5px; text-align:center;'>" . htmlspecialchars($sale['items_sold']) . "</td>";
                echo "<td style='text-align:center;'>" . format_qty($sale['quantities']) . "</td>";
                echo "<td align='right' style='padding-right:5px;'>{$amount_display}</td>";
            echo "</tr>";
            $count++;
          }
          ?>
        </tbody>
      </table>


                        

                      
    
   


</div>

                              <table width='100%'>
                                 <tr>
                                    <td   >


                                     <div class="print-only"> 
                                          <table>
                                          
                                           <tr>
                                             <!-- <td align='center' width =200px;><br>---------------------------- <br> Prepared By </td> -->
                                              <td align='center' width =200px;><br>---------------------------- <br>  Checked By </td> 
                                               
                                          </tr>
                                          <tr>
                                            <td align='center' width =200px;><br>---------------------------- <br> Branch Manager </td>
                                            
                                          </tr>
                                           
                                       </table>
                              </div>



                                    </td>
                                    <td>
                                       <table>
                                           <tr>
                                               <td width =100px;>   </td>
                                             <td align='right'> </td>
                                          </tr>

                                          <tr>
                                               <td width =100px;> </td>
                                             <td align='right'> </td>
                                          </tr>
                                                 </table>
                                       



                                     
                                 </td>
                              </table>




                     </div>
                  </div>
               </div>
            </div>
         </div>
      </div>

   </div>
</div>

 
    


<?php include 'footer.php'; ?>

 <script>
   
  $('#example').dataTable( {
"pageLength": 50
} );
</script>