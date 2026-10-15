<?php include 'header.php'; 
$total_inv = 0;

$query = "SELECT DATE_ADD(MAX(date_ended), INTERVAL 1 DAY) AS next_day_end_date 
          FROM day_end_process 
          WHERE status = 1 AND location_id = '$location_id'";
$result = mysqli_query($con, $query);
$row = mysqli_fetch_assoc($result);

$next_day_end_date = $row['next_day_end_date'];
$today = date('Y-m-d');

if ($next_day_end_date == $today || empty($next_day_end_date)) {
    $final_date = $today;
} else {
    $final_date = $next_day_end_date;
}


?>
 
<div class="content-wrapper"   >
   <!-- Container-fluid starts -->
   <div class="container-fluid" >
      <!-- Header Starts -->
      <div class="row">
         <div class="col-sm-12 p-0">
            
            <div class="main-header">
               <h4><i class="fas fa-tint"></i> Oil Sales Invoice / <?php echo $client_name?></h4>
              
            </div>
         </div>
      </div>
      <!-- Header end -->


      <div class="col-lg-9 col-md-12 col-sm-12 col-xs-12" >
		<div class="widget radius-bordered">
			 
				<div class="widget-body" style='background-color: #cce0fc;'>
					 
				<div class="panel panel-default" style='background-color:#deeafc;'>

<div class="panel-body" style=' z-index: 100; <?php if (isset($_REQUEST['return'])) {  echo "background:#9ede8c;";  } ?> '>                              
		 <div class="table-responsive" style='display: contents;'>

		<?php
		$user = $user_id;
		if (isset($_POST['delete'])) {
			$temp_id = $_POST['temp_id'];

			$query = "DELETE FROM oil_sales_temp where row_count = '$temp_id' AND  user = '$user'";
			$result = mysqli_query($con, $query);
			$qry = "SET @a:=0";
			$result = mysqli_query($con, $qry);

			$qry = "UPDATE oil_sales_temp SET row_count=@a:=@a+1 WHERE user = '$user' ORDER BY id ,user";
			$result = mysqli_query($con, $qry);
		}
		if (isset($_POST['delete_all'])) {
			$query = "DELETE FROM oil_sales_temp where user = '$user'";
			$result = mysqli_query($con, $query);
		}


		?>
	 
		<table style="border: 4px" width=100%>
			<form action="record_oil_sales.php" autocomplete="off" method="POST" id="form1z">
				<form method='POST' action=''>
					

 
					<!-- <tr>
						<td width="145px" align="right">P ORDER NO:</td>
						<td colspan="2"> <input class="form-control" type="text" value="" required name="p_order"> </td> -->
					<tr>
						<td width="145px" align="right"><br> </td>
						<td colspan="2"> 
							<!-- <input class="form-control" type="text" value="" required name="bill_no"> -->
						<input class="form-control" type="hidden" name='location' value="<?php echo $location_id;?>">
					 </td>

						<td rowspan="2" colspan="2">
							<div align="center">
								<input type="hidden" name="entry" value="Invoice">
								<h2> <i class="fas fa-tint"></i> Oil Sales</h2>
							   

							</div>
						</td>
	</div>
	</td>


	</tr>
	<tr>
		<td width="145px" align="right">DATE :</td> <input type="hidden" name="bill_total" value="<?php echo $total_inv; ?>" id="total_bill1">
		<td width="115px"><input max="<?php echo $tomorrow = date("Y-m-d", strtotime("0 day")); ?>" type="date" class="form-control" REQUIRED readonly name="inv_date" value="<?php echo $final_date; ?>">
		</td>


	<tr>
		<td width="145px" align="right">Select Customer:</td>
		<td colspan="3" >
			<select style="max-width: 500px;"    required  id="mySelect_search2"  required class="form-control" name="customer">

				<option value='0'>Cash sales</option>
				<!-- <option value="Cash Bill">Cash Purchase</option> -->
				<?php

				$sel_query = "SELECT *  from mange_customer where   status = 1 order by c_id ";
				$result = mysqli_query($con, $sel_query);
				while ($row = mysqli_fetch_assoc($result)) {
					echo " <option value=".$row['c_id'].">" . $row['customer_name'] . "</option>";
				} ?>
			</select>
			
		</td>
		 	
	   
	</tr>
	<td width="145px" align="right">Order No</td>
		<td>
		    <input type='text' class="form-control" value='' name='ref_no'>
		  
              <p style='font-size:20px;' id="allocation_info"></p>
		</td>
		
		<td width="145px" align="right">vehicle No:</td>
			<td>
		    <input type='text' class="form-control" value='' name='vehicle_no'> </td>
	 		<td width="145px" align="right">Invoice No:</td>
			<td>
		    <input type='text' class="form-control" value='' name='invoice_no'> </td>
	</table>
	
	<script>
  $(function () {
    const $customer = $('#mySelect_search2');
    const $refRow   = $('input[name="ref_no"]').closest('td').parent();
    const $vehRow   = $('input[name="vehicle_no"]').closest('td').parent();
    const $invRow   = $('input[name="invoice_no"]').closest('td').parent();

    const $ref = $('input[name="ref_no"]');
    const $veh = $('input[name="vehicle_no"]');
    const $inv = $('input[name="invoice_no"]');

    function toggleFields() {
      const isCash = $customer.val() === '0';
      
      if (isCash) {
        // Hide and remove required
        $refRow.hide();
        $vehRow.hide();
        $invRow.hide();
        $ref.prop('required', false).val('');
        $veh.prop('required', false).val('');
        $inv.prop('required', false).val('');
      } else {
        // Show and set required
        $refRow.show();
        $vehRow.show();
        $invRow.show();
        $ref.prop('required', true);
        $veh.prop('required', true);
        $inv.prop('required', true);
      }
    }

    $customer.on('change', toggleFields);
    toggleFields(); // Run on page load
  });
</script>

	</form>
 


	<div class="table-responsive" style='display: contents;'>
		<table class="table  table-bordered  " id='table1'>
			<tbody>
				<tr bgcolor="#5499C7">

					<td width="30%" align="center"><b>Item</b></td>

					<td align="center" width=15%><b>Quantity</b></td>
					<td align="center" width=15%><b>Rate</b></td>
					<td align="center" width=15%><b>Value</b></td>
					<td align="center" width=15%><b>Action</b></td>
				</tr>




				<?php

				$count = 1;
				 
				$sel_query = "SELECT oil_sales_temp.* , product_master.* from oil_sales_temp 
									INNER JOIN oil_stock
									ON oil_stock.batch_id = oil_sales_temp.s_id

									INNER JOIN product_master
									ON 
									product_master.p_id = oil_stock.s_id

									where user = '$user'";
				$result = mysqli_query($con, $sel_query);
				$no_items = mysqli_num_rows($result);
				while ($row = mysqli_fetch_assoc($result)) { ?>
					<tr>
						<td width="30%"> <?php echo $row['p_name']; 
						 
						 ?> </td>

						<td align="center"><?php echo $row['qty']; ?></td>
						<td align="right"><?php echo number_format($row['rate'], 2, '.', ','); ?></td>
						<td align="right"><?php $row_value = $row['qty'] * $row['rate'];
											echo  number_format($row_value, 2, '.', ',');
											$total_inv = $total_inv + $row_value;
											?></td>
						<td align="center">
							<form method="POST" action="">
								<input type="hidden" name="temp_id" value="<?php echo $row['row_count']; ?>">
								<input type="submit" name='delete' class="btn btn-danger" value=" X ">
							</form>
						</td>
					</tr> 

				<?php } ?>
		</table>
		<table>
			<tr>
				<td bgcolor="#D4E6F1" width="30%">
				<select id="mySelect_search" name='group' onchange="Change(this)"  required  class="form-control input-sm" style="width: 100%;" >
                 <option value='0'>Select</option>
              
                  <?php
                     $sel_query="SELECT oil_stock.*,product_master.* FROM `oil_stock` 
					 LEFT JOIN product_master ON product_master.p_id = oil_stock.s_id 
					 WHERE oil_stock.store_id = '$location_id' and oil_stock.open_status = 1 AND batch_status = 1
					 ORDER BY p_name ,exp_date ";
                     $result = mysqli_query($con,$sel_query);
                     while($row = mysqli_fetch_assoc($result)) { 

						$stock_balance = $row['purchase_qty']-$row['return_qty']-$row['transfered_qty']-$row['issued_qty']-$row['adjusted_qty'];

                           echo "<option value='".$row['batch_id']."' data-type2='".$row['sales_price']."' data-type1='".$stock_balance."'>".$row['p_name'];
						 

						   echo " ( Stock: ".$stock_balance." @ Rs. ".$row['sales_price'].")";
						   echo "</option>"; 
                     }?>
                  </select> 
                 
					<input type="hidden" id="selected_value">
					<input type="hidden" id="selected_item">
					<input type="hidden" id="cost_price">



				</td>



				<td bgcolor="#D4E6F1" width="15%"><input type="number" min="0" min="0.01" step="0.01" class="form-control" placeholder="Quantity" required class="items" name="item_qty" id="qty" onchange="priceCalculation();"></td>
				<td bgcolor="#D4E6F1" width="15%" align="right"><input style="text-align:right;" type="text" class="form-control" placeholder="Price" required name="cost_price" id="pricez"  readonly ></td>
				<td bgcolor="#D4E6F1" width="15%"><input type="text" readonly class="form-control" style="text-align:right;" placeholder="Line value" required id="line_total"></td>

				<td bgcolor="#D4E6F1" width="15%" align="center"> <input class="btn btn-primary" style='width:80%' id="button" type="submit" name="add_item" value="Add Line">
				</td>
				<script>
					function Change(level) {

						var select_value = $(level).val();
						var price = $('#mySelect_search option:selected').attr("data-type2")
						var pricey = + price * +1;
                         pricez.value = pricey;
					 

					}


					function priceCalculation() {
						var price = document.getElementById('pricez').value;
						var qty = document.getElementById('qty').value;

						var line_totalx = +qty * +price;
						document.getElementById('line_total').value = line_totalx;
						document.getElementById("submit_button").disabled = true; 
					}

 
					let btnAdd = document.querySelector('#button');
					let table = document.querySelector('#table1');
					let qtyInput = document.querySelector('#qty');

					btnAdd.addEventListener('click', () => {

						let qty = qtyInput.value;
						var s_id = document.getElementById('mySelect_search').value
 
						if (qty < 0.01 || s_id < 1) { 
							if (qty < 0.01) { notify('danger', 'Please enter valid quantity before add product.'); }
							if (s_id < 1) { notify('danger', 'Please Select a product');  }
						} else {
							document.getElementById("submit_button").disabled = false;    
							var table = document.getElementById("table1");
						    var totalRowCount = table.rows.length; // 5
							var lablex =  $('#mySelect_search option:selected').text();
							var rate = document.getElementById('pricez').value
							var cost = document.getElementById('cost_price').value
                            var valuez = rate * qty;

							var qoh = $('#mySelect_search option:selected').attr("data-type1")
 
												if (qty*1 > qoh*1) {
														notify('danger', 'available stock on this batch is : ' + qoh);
                                                            return;
                                                        }


							var total_value = document.getElementById('total_bill').value
							var new_total =  +total_value + +valuez; 
							total_bill.value = new_total;
							total_bill1.value = new_total;
							document.getElementById('total_lable').innerHTML = new_total;

							var valuex = valuez.toLocaleString(
                                                        undefined, {
                                                            minimumFractionDigits: 2
                                                        }
                                                    );
						 
													 if (rate < 0.01) {
														notify('danger', 'Please enter valid Rate before add product.');
                                                            return;
                                                        }
                                                    
													$("#qty").val("");
                                                    line_total.value = '';
													pricez.value = '';
													$("#mySelect_search").val("0").trigger("change")
													 
                                                   

													 let template = `
                                                                    <tr>
                                                                        <td>${lablex}</td>
                                                      
                                                                        <td align="center">${qty}</td>
                                                                        <td align="right">${rate}</td>
                                                                        <td align="right">${valuex}</td>
                                                                        <td align="center"><form method="POST" action="">
                                                                        <input type="hidden" name="temp_id" value="${totalRowCount}">
                                                                        <input type="submit" name='delete' class="btn btn-danger" value=" X ">
                                                                        </form></td>
                                                                    </tr>`;

                                                    table.innerHTML += template;

													var bill = "GRN";
                                                    $.ajax({
                                                        type: "POST",
                                                        url: "oil_ajax/oil_sales_temp.php",
                                                        data: {
                                                            x_id: s_id,
                                                            grn:bill,
                                                            qty: qty,
                                                            rate: rate,
                                                            cost: cost,
                                                            totalRowCount: totalRowCount
                                                        },
                                                        success: function(data, textStatus, jqXHR) {}
                                                    });

						}

					});
				</script>


			</tr>

			</tbody>
		</table>
		<div align="right">
			</form>

			<table>
				<tr>
					<td>
						<input type="hidden" name="bill_total" value="<?php echo $total_inv; ?>" id="total_bill">

						<b>Total -<h3  id="total_lable"  ><?php echo number_format($total_inv, 2, '.', ','); ?></h3>
						</b>
					</td><td>&nbsp;&nbsp;&nbsp;</td>
					<td>
					    <input name="submit_oil_sales" type='hidden' form="form1z"  value='1'>
						<button class="btn btn-primary"    type="submit" form="form1z" id="submit_button" value="Submit">Save</button>


					</td>
					<td>

						<form method="POST" action="">

							<input type="submit" name='delete_all' class="btn btn-danger" value=" Clear All Items">
						</form>
					</td>
				</tr>
			</table>


		</div>
		<span id="txtHint"></span> 
			<script>
$( document ).ready(function() {
var total_value = document.getElementById('total_bill').value
// alert(total_value);
$("#total_bill1").val(total_value);
});

</script>
	</div>


</div>




</div>		 
				 



            
				</div>
			</form>
		</div>
	</div>


       
     
   </div>

</div>


<?php include 'footer.php'; ?>


<script>
   
    $(document).ready(function() {
    $('#mySelect_search').select2();
  });
</script>
<script>
   
    $(document).ready(function() {
    $('#mySelect_search2').select2();
  });
</script>
<?php 
 