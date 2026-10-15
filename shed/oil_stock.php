<?php include 'header.php'; ?>

<?php

 function formatNumber($number, $decimals = 2) {
  // Handle zero cases (consider both positive and negative)
  if ($number == 0) { // Use == to consider both 0 and -0 as equal to 0
    return ''; // Format zero with the specified number of decimals
  }

  // Use number_format for non-zero values
  $formatted = number_format(abs($number), $decimals);

  // Check if formatted string ends with ".00" (no significant decimals)
  if (substr($formatted, -3) === ".00") {
    // Remove trailing ".00" if no decimals are significant
    $formatted = substr($formatted, 0, -3);
  }

  // Prepend the sign for non-zero values
  return ($number < 0 ? '-' : '') . $formatted;
}

 
//  if (isset($_POST['submit_status'])) {
 
//       $batch_status = $_POST['batch_status'];
//       $batch_id = $_POST['batch_id'];
//       $user = $_SESSION['username']." ".date('Y-m-d');
    
//       $ref1 = $_POST['ref1'];
      
      

//    $update="UPDATE oil_stock set batch_status = '$batch_status' ,batch_status_for = '$ref1',  batch_status_user='$user'   where  batch_id = '$batch_id'";
//    mysqli_query($con, $update);
   
//   if($batch_status ==0){ 
//       $status_text ='Unserviceable';
//   } else{
//      $status_text ='Serviceable';  
//   }
   
//      $detail = "Changed   Status Batch ID : $batch_id Status changed as : $status_text ";
//     UserLog('3','Stocl Status',$detail);
    
//  echo "<script> notify('success', 'Batch Status changed successfully'); </script>";
// }

if (isset($_POST['submit_other'])) {
   $qty1 = $_POST['qty1'];
   $method = $_POST['method'];
   $batch_id = $_POST['batch_id'];
   $adj_qty = $qty1 * -1;
   $user = $_SESSION['username'];
   $ref1 = $_POST['ref1'];
   
   $update="INSERT oil_stock_adjustment (batch_id,adj_qty,adj_user,adj_referance,method) VALUES ('$batch_id','$adj_qty','$user','$ref1','$method')";
mysqli_query($con, $update);


$update="UPDATE oil_stock set adjusted_qty = adjusted_qty + '$adj_qty'  where  batch_id = '$batch_id'";
mysqli_query($con, $update);




// ========================================================== update to accounts ============================

// 1. Fetch item name and purchase price
  $item_sql = "
    SELECT oil_stock.s_id, product_master.p_name, oil_stock.price AS purchase_price 
    FROM oil_stock 
    INNER JOIN product_master ON product_master.p_id = oil_stock.s_id 
    WHERE oil_stock.batch_id = '$batch_id' 
    LIMIT 1
";
$item_result = mysqli_query($con, $item_sql);
$item = mysqli_fetch_assoc($item_result);

if (!$item) {
    echo "<script> notify('danger', 'Failed to fetch item info for accounting'); </script>";
    exit;
}

$p_name = $item['p_name'];
$purchase_price = floatval($item['purchase_price']);
$value = abs($adj_qty) * $purchase_price;

// 2. Get last inserted adjustment ID
$source_id = mysqli_insert_id($con); // oil_stock_adjustment.id
$today = date('Y-m-d H:i:s');
$memo = "Oil Stock adjusted for $method $p_name  Qty: $adj_qty @ " . number_format($purchase_price, 2);
$location_id = $location_id; // If not available, set a default or join from oil_stock
$status = 1;
$source = 'Oil Stock Adjustment';
$tra_type = 'Oil Stock Adjustment';
$ref_no = $batch_id;
$created_by = $user_id; // from auth.php

// Defaults
$vat_rate = 0;
$debit_vat = 0;
$credit_vat = 0;
$cus_id = 0;
$sup_id = 0;
$other_branch = 0;

// 3. Insert Debit (Inventory Adjustment - ca_id = 24)
echo $debit_sql = "
    INSERT INTO acc_transaction (
        location_id, ca_id, t_date, tra_type, source, source_id, ref_no,
        debit, credit, vat_rate, debit_Vat, credit_vat,
        t_memo, credted_by, created_on, cus_id, sup_id, status, other_branch
    ) VALUES (
        $location_id, 24, '$today', '$tra_type', '$source', $source_id, '$ref_no',
        $value, 0, $vat_rate, $debit_vat, $credit_vat,
        '" . mysqli_real_escape_string($con, $memo) . "', $created_by, '$today', $cus_id, $sup_id, $status, $other_branch
    )
";
mysqli_query($con, $debit_sql);

// 4. Insert Credit (Inventory - ca_id = 22)
$credit_sql = "
    INSERT INTO acc_transaction (
        location_id, ca_id, t_date, tra_type, source, source_id, ref_no,
        debit, credit, vat_rate, debit_Vat, credit_vat,
        t_memo, credted_by, created_on, cus_id, sup_id, status, other_branch
    ) VALUES (
        $location_id, 22, '$today', '$tra_type', '$source', $source_id, '$ref_no',
        0, $value, $vat_rate, $debit_vat, $credit_vat,
        '" . mysqli_real_escape_string($con, $memo) . "', $created_by, '$today', $cus_id, $sup_id, $status, $other_branch
    )
";
mysqli_query($con, $credit_sql);

// =============================================================end of  updat











$detail = " Adjusted Batch ID : $batch_id Adjusted Qty : $qty1 reason:$method";
    UserLog('3','stock Adjustment',$detail);
echo "<script> notify('success', 'Stock Adjusted successfully'); </script>";
}




?>
<style>
        /* Basic styling for the modal */
       
    </style>
<div class="content-wrapper">
   <!-- Container-fluid starts -->
   <div class="container-fluid">
      <!-- Header Starts -->
      <div class="row">
         <div class="col-sm-12 p-0">
            
            <div class="main-header">
               <h4>Oil Stock  / <?php echo $client_name ?></h4> 
             
            </div>
         </div>
      </div>
      <!-- Header end -->

      <div class="row">
         <div class="col-sm-12">
 
            <div class="card">
            
          

               <div class="card-block">
                  <div class="row">
                    
                     <div class="col-sm-12 table-responsive"> 

                        <button type='button' id="exportButton" filename='<?php echo "OIL_STOCK_".date('Y-m-d_H-i-s'); ?>.xlsx' class="btn btn-primary"><i class="ti-cloud-down"></i> Export</button>

                        <hr>

<style>
  .table td, .table th {
        
        font-size: 13px;}
  </style>
    <?php 
     if(isset($_REQUEST['short_expiry'])){
     $month = $_REQUEST['short_expiry'];
$current_date = date('Y-m'); // Get the current year and month
$six_months_ahead = date('Y-m', strtotime('+'.$month.' months'));

 $sel_query = "SELECT oil_stock.*, product_master.*, oil_grn_master.grn_date 
              FROM oil_stock 
              INNER JOIN product_master ON product_master.p_id = oil_stock.s_id 
              LEFT JOIN oil_grn_master ON oil_grn_master.grn_id = oil_stock.grn_id 
              WHERE store_id = $location_id AND open_status = 1 
              AND exp_date BETWEEN '$current_date' AND '$six_months_ahead'
              ORDER BY p_name";
              echo "<h3>Filtered : Short Expiry in ". $month ." Month</h3>";
   
 }else{
      $sel_query="SELECT oil_stock.* ,product_master.* ,oil_grn_master.grn_date FROM `oil_stock` 
  INNER JOIN product_master on product_master.p_id = oil_stock.s_id
  LEFT JOIN oil_grn_master ON oil_grn_master.grn_id = oil_stock.grn_id
  
  WHERE `store_id` = $location_id  AND open_status = 1
  ORDER BY p_name"; 
 }
  
  ?>


                        <table id="example" class="table table-bordered " style="width:100%">
                           <thead>
                              <tr>
                                 <th >#</th> 
                                 <th>Stock Name</th>
                                 <th>Purchase Date</th>
                                 <th>Source</th>
                                 <th>Sales Price</th>
                               
                             
                                 <th>Pur Price</th>
                                 <th>Qty</th>
                               
                                 <th>Sold Qty </th>
                                 <th>Adjusted Qty</th>
                                 <th>Balance Qty </th>
                                  <th>Purchase Value </th>
                                   <th>Sales Value  </th>
                                
                              </tr>
                           </thead>
                           <tbody>
                           <?php
$count=1;
$total_purchase = 0;
$total_sales = 0;
  
  
$result = mysqli_query($con,$sel_query);
if ($result === false) {
    // Query failed, handle error
    echo "Database query failed: " . mysqli_error($con);
    exit;  // stop execution if needed
}
while($row = mysqli_fetch_assoc($result)) { 
?>
                              <tr data-id="<?php echo $row['batch_id'] ?>" <?php if($row['batch_status'] == 0) { echo "style='background-color:#FBBCBC; text-decoration: line-through;'"."data-toggle='tooltip' data-placement='left' title='Unserviceable : ".$row['batch_status_for']." Updated by ".$row['batch_status_user']."'";}?>     >
                                 <td align="center"><?php echo $count; $count++; ?></td> 
                                 <td  ><?php echo $row['p_name']; ?></td>
                                   <td align='center' ><?php echo $row['batch_date'] ?></td>
                                   <td align='center'  ><?php echo $row['source'] ?></td>
                               <?php  echo"<td align='center'  >".number_format($row['sales_price'],2)."</td>";   ?>
                               
                                 <td align='center'  ><?php echo number_format($row['price'],2); ?></td>
                                 <td align='center'  ><?php echo formatNumber($row['purchase_qty']); ?></td>
                            
                                 <td align='center'  ><?php echo formatNumber($row['transfered_qty']*-1); ?></td>
                                 <td align='center'  ><?php echo formatNumber($row['adjusted_qty']*-1);
                                 $stock_balance = $row['purchase_qty'] -$row['issued_qty'] - $row['transfered_qty'] - $row['adjusted_qty'] 
                                 ?></td>
                                  <td align='center'  ><?php echo formatNumber($stock_balance); ?></td>
                                 <td align='right'  ><?php $total_purchase += $purchase_value =  $stock_balance*$row['price']; echo number_format($purchase_value,2); ?></td>
                                 <td align='right'  ><?php $total_sales += $sales_value =  $stock_balance*$row['sales_price']; echo number_format($sales_value,2); ?></td>
                                   
                                  
                                  


                       
                              </tr>

                              <?php } ?>
                             
                           </tbody>
                        </table>

  <div align='right'>
    <h4>
      Stock at purchase Price = <?php echo number_format($total_purchase,2); ?><br>
      Stock at sales Price = <?php echo number_format($total_sales,2); ?>
    </h4>
                           </div>


                         <script>
                                                $(".dropdown_change").change(function() {
                                                     var row_id = $(this).attr("row_id"); 
                                                     var stock_balance = $(this).attr("stock_balance"); 
                                                     var value = $(this).find(":selected").val();  
                                                        var view_name = $(this).attr("view_name"); 
                                                    if(value == 'Purification' ){
                                                      document.getElementById("row_id_editz").value = row_id;
                                                      document.getElementById("qty_input1").setAttribute("min",stock_balance*-1);
                                                                          
                                                                            $('#purification_model').modal('show'); 
                                                               } 

                                                               
                                                    if(value == 'Adjust' ){
                                                        document.getElementById("demo123").innerHTML = view_name;
                                                      document.getElementById("row_id_edit1").value = row_id;
                                                       document.getElementById("qty_input1").setAttribute("min",stock_balance*-1);
                                                                          
                                                                            $('#other_model').modal('show'); 
                                                               } 
                                                    if(value == 'Status' ){
                                                      document.getElementById("row_id_edit2").value = row_id;
                                                      // document.getElementById("qty_input1").setAttribute("max",stock_balance);
                                                                          
                                                                            $('#status_model').modal('show'); 
                                                               } 
 
                                             
                                                    });
                                                </script> 
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
   $(document).ready(function() {
      $('#example').DataTable();
   });

   $(document).ready(function() {
    $('#mySelect1').select2({
              dropdownParent: $("#add_coa")
      });
  });
    $(document).ready(function() {
    $('#mySelect_search').select2();
  });
  $('#example').dataTable( {
"pageLength": 25
} );
</script>

<div class="modal"  id="status_model" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <!--<div class="modal-header">-->
      <div class="modal-body"> 
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">  <span aria-hidden="true">&times;</span>  </button>
       
        <h4 class="modal-title">Change the Batch Status As <br> Serviceable /Unserviceable
</h4><hr>
           <table width='100%'>
                 <form action="" method="POST" id="edit-form"> <input type="hidden" style='width:200px;' class="form-control" name='batch_id' id='row_id_edit2' value="">
                 <tr><td align="right" style="padding-right:30px;" width='80%'>
               </td></tr>

                 <tr><td align="center" style="padding-right:30px;" width='80%' colspan='2'>   <p id='p_name_show' Style='font-size:15px;'>  </p>  
               </td></tr>
               <tr><td align="right" style="padding-right:30px;" width='80%'> Change the batch status as  *: </td> <td style="padding-bottom:4px; "><select required class="form-control" name='batch_status' >

                                            <option value =''>select</option> 
                                            <option value='1'>Serviceable</option>  
                                            <option value='0'>Unserviceable</option>  
                                      
               </select> </td></tr>
               
             <tr><td align="right" style="padding-right:30px;" width='80%'> Referance: </td> <td style="padding-bottom:4px; "> <input type="text"  required  id='m_roq_edit' style='width:300px;' class="form-control" name='ref1' id='a' > </td></tr>
            

                <tr>
                <td> </td> <td style="padding:5px;"> <input type="submit" class="btn btn-success" name='submit_status' value='Submit'> </form></td>
            </tr> 
           </table>
      </div>
    </div>
  </div>
</div>


 
<div class="modal"  id="other_model" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <!--<div class="modal-header">-->
      <div class="modal-body"> 
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">  <span aria-hidden="true">&times;</span>  </button>
       
        <h4 class="modal-title">Update Stock Adjustment</h4>
    
         <hr>
            <div align='center' > <h6  id="demo123"></h6></div>
           <table width='100%'>
                 <form action="" method="POST" id="edit-form"> <input type="hidden" style='width:200px;' class="form-control" name='batch_id' id='row_id_edit1' value="">
                 <tr><td align="right" style="padding-right:30px;" width='80%'>
               </td></tr>

                 <tr><td align="center" style="padding-right:30px;" width='80%' colspan='2'>   <p id='p_name_show' Style='font-size:15px;'>  </p>  
               </td></tr>
               <tr><td align="right" style="padding-right:30px;" width='80%'> Reason for adjustment *: </td> <td style="padding-bottom:4px; "><select required class="form-control" name='method' >

                                            <option value =''>select</option>
                                            <!-- <option>Moisture Loss</option>
                                            <option>Processing Wastage</option>   -->
                                            <option>Losses</option>  
                                            <option>Damage</option>  
                                            <option>Board of Survey</option>  
                                             <!-- <option>Opening Stock Adjustment</option>   -->
               </select> </td></tr>
               <tr><td align="right" style="padding-right:30px;" width='80%'>Quantity of Adjustment: <br><font color='red'> Note : Use [ - ] in the input </font> </td> <td style="padding-bottom:4px; "> <input type="number" step="0.01" id='qty_input1' max='-0.01' required style='width:300px;' class="form-control" name='qty1'   > </td></tr>
             <tr><td align="right" style="padding-right:30px;" width='80%'> Referance: </td> <td style="padding-bottom:4px; "> <input type="text"    id='m_roq_edit' style='width:300px;' class="form-control" name='ref1' id='a' > </td></tr>
            

                <tr>
                <td> </td> <td style="padding:5px;"> <input type="submit" class="btn btn-success" name='submit_other' value='Submit'> </form></td>
            </tr> 
           </table>
      </div>
    </div>
  </div>
</div>


 
<!-- <div class="modal" id="purification_model"  tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      
      <div class="modal-body"> 
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">  <span aria-hidden="true">&times;</span>  </button>
        <h4 class="modal-title">Update Purification Detail</h4><hr>
           
           <table width='100%'>
                 <form action="" method="POST" id="edit-form"> <input type="hidden" style='width:200px;' class="form-control" name='batch_id' id='row_id_editz' value="">
                 <tr><td align="right" style="padding-right:30px;" width='80%'>
               </td></tr>

                 <tr><td align="center" style="padding-right:30px;" width='80%' colspan='2'>   <p id='p_name_show' Style='font-size:15px;'>  </p>  
               </td></tr>
               <tr><td align="right" style="padding-right:30px;" width='80%'>How much of unit used in the purification process? *: </td> <td style="padding-bottom:4px; "> <input type="number" step="0.01" id='qty_input' required style='width:300px;' class="form-control" name='qty1' max=''   > </td></tr>
            <tr><td align="right" style="padding-right:30px;" width='80%'> Output after process (unit) *: </td> <td style="padding-bottom:4px; "> <input type="number" step="0.01"  id='m_roq_edit' style='width:300px;' class="form-control" name='qty2' id='a' > </td></tr>
            <tr><td align="right" style="padding-right:30px;" width='80%'> Referance: </td> <td style="padding-bottom:4px; "> <input type="text"    id='m_roq_edit' style='width:300px;' class="form-control" name='ref1' id='a' > </td></tr>
            

                <tr>
                <td> </td> <td style="padding:5px;"> <input type="submit" class="btn btn-success" name='submit_purification' value='Submit'> </form></td>
            </tr> 
           </table>
      </div>
    </div>
  </div>
</div> -->

 
 



<script>

function updateShowMe(batch_id, isChecked) {
 
    var showMeStatus = isChecked ? 1 : 0;
    
    var xhr = new XMLHttpRequest();
    xhr.open("POST", "ajax/update_show_me.php", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    
    xhr.onreadystatechange = function () {
        if (xhr.readyState == 4 && xhr.status == 200) {
            if (xhr.responseText === 'Success') {
                notify('success', 'The Batch Show Request Updated');
            } else {
                toastr.error('Error: ' + xhr.responseText);
            }
        }
    };
    
    xhr.send("batch_id=" + batch_id + "&show_me=" + showMeStatus);
}


$(document).ready(function() {
    var $modal = $('#myModal');
    var $monthInput = $('#monthInput');
    var $saveBtn = $('#saveBtn');
    var $currentTd;

    $('.editable').on('click', function() {
        $currentTd = $(this);
        var originalValue = $currentTd.text();
        $monthInput.val(originalValue);
        $modal.show();
    });

    $('.close, #saveBtn').on('click', function() {
        $modal.hide();
    });

    $saveBtn.on('click', function() {
        var newValue = $monthInput.val();
        if (newValue) {
            var id = $currentTd.closest('tr').data('id');
            var field = $currentTd.data('field');
                                                      if (newValue < 0) {
														            notify('danger', 'Enter a valid expiry date / expiry date cannot be past');
                                                      $modal.show();
                                                            return;
                                                        }

            $.ajax({
                url: 'ajax/update_change_oil_price.php',
                type: 'POST',
                data: {
                    id: id,
                    field: field,
                    value: newValue
                },
                success: function(response) {
                    if (response.success) {
                        $currentTd.text(newValue);
                        notify('success', 'Expiry date changed as ' + newValue);
                    } else {
                        alert('Error updating data');
                    }
                },
                error: function() {
                    alert('AJAX error');
                }
            });
        }
        $modal.hide();
    });

    // Close the modal when clicking anywhere outside of the modal content
    $(window).on('click', function(event) {
        if ($(event.target).is($modal)) {
            $modal.hide();
        }
    });
});
</script>

 
<div id="myModal" class="modal1">
    <div class="modal1-content">
        <span class="close">&times;</span>
        <label  >Price change</label>
        <table><tr><td>
        <input type="number" class="form-control"  step='0.01' id="monthInput"></td><td>
        <button id="saveBtn" class="btn btn-success">Change</button></td></tr></table>
    </div>
</div>