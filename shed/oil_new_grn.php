<?php include 'header.php'; 


  $query = "SELECT DATE_ADD(MAX(date_ended), INTERVAL 1 DAY) AS next_day_end_date 
          FROM day_end_process 
          WHERE status = 1 AND location_id = '$location_id'";
$result = mysqli_query($con, $query);
$row = mysqli_fetch_assoc($result);

$next_day_end_date = $row['next_day_end_date'];
$today_date = date('Y-m-d');
$current_datetime = date('Y-m-d H:i:s');

if ($next_day_end_date == $today_date || empty($next_day_end_date)) {
   $created_on = $current_datetime; // Use current datetime
    $date_pick = $today_date;
} else {
    $created_on = $next_day_end_date . ' 23:59:59'; // End of the next day
    $date_pick = $next_day_end_date;

}


?>
 
<div class="content-wrapper"   >
   <!-- Container-fluid starts -->
   <div class="container-fluid" >
      <!-- Header Starts -->
      <div class="row">
         <div class="col-sm-12 p-0">
            
            <div class="main-header">
               <h4>Good Received Note / <?php echo $client_name; ?></h4>
              
            </div>
         </div>
      </div>
      <!-- Header end -->


      <div class="col-lg-10 col-md-12 col-sm-12 col-xs-12" >
		<div class="widget radius-bordered">
			 
				<div class="widget-body">
					 
				<div class="panel panel-default">

<div class="panel-body" style=' z-index: 100; <?php if (isset($_REQUEST['return'])) {  echo "background:#9ede8c;";  } ?> '>                              
		 <div class="table-responsive" style='display: contents;'>

		<?php
		$user = $_SESSION['username'];
		if (isset($_POST['delete'])) {
			$temp_id = $_POST['temp_id'];

			$query = "DELETE FROM oil_grn_temp where row_count = '$temp_id' AND  user = '$user'";
			$result = mysqli_query($con, $query);
			$qry = "SET @a:=0";
			$result = mysqli_query($con, $qry);

			$qry = "UPDATE oil_grn_temp SET row_count=@a:=@a+1 WHERE user = '$user' ORDER BY id ,user";
			$result = mysqli_query($con, $qry);
		}
		if (isset($_POST['delete_all'])) {
			$query = "DELETE FROM oil_grn_temp where user = '$user'";
			$result = mysqli_query($con, $query);
		}


		?>
		<?php if (isset($_REQUEST['record'])) { ?>
			<div class="alert alert-success" role="alert" align="center">
			<strong> Success ... </strong> <?php
											echo $_REQUEST['transection'] . " ";
											$no = $_REQUEST['record'];
											echo $req_id = $no / 1852454545;
											?> recorded Successfully
			<br>
			<a href="oil_grn_print.php?record=<?php echo $_REQUEST['record']; ?>&transection=<?php echo $_REQUEST['transection']; ?>" target="_blank"><button type="button" class="btn btn-info">Print Request</button></a>
			</div> <?php } ?>

		<table style="border: 4px" width=100%>
			<form action="record_oil_grn.php" autocomplete="off" method="POST" id="form1z">
				<form method='POST' action=''>
					


					<!-- <tr>
						<td width="145px" align="right">P ORDER NO:</td>
						<td colspan="2"> <input class="form-control" type="text" value="" required name="p_order"> </td> -->
					<tr>
						<td width="145px" align="right">INVOICE NO:</td>
						<td colspan="2"> <input class="form-control" type="text" value="" required name="bill_no">
						<input class="form-control" type="hidden" name='location' value="<?php echo $location_id;?>">
					 </td>

						<td rowspan="2" colspan="2">
							<div align="center">
								<input type="hidden" name="entry" value="Invoice">
								<h2> OIL GRN</h2>
							   

							</div>
						</td>
	</div>
	</td>


	</tr>
	<tr>
		<td width="145px" align="right">DATE :</td> <input type="hidden" name="bill_total" value="<?php echo $total_inv; ?>" id="total_bill1">
		<td width="115px"><input min='<?= $date_pick ?>' max="<?php echo $tomorrow = date("Y-m-d", strtotime("0 day")); ?>" type="date"   class="form-control" REQUIRED name="inv_date" value="<?php if (isset($_COOKIE['entry_date'])) {
																																											echo $_COOKIE['entry_date'];
																																										} else {
																																											echo date('Y-m-d');
																																										} ?>">
		</td>


	<tr>
		<td width="145px" align="right">SUPPLIER:</td>
		<td colspan="3" >
					<select style="max-width: 500px;" onchange="Change2(this)" required id="mySelectz" class="form-control" name="customer">
					<option value=''>Select</option>
					<?php
					$sel_query = "SELECT * FROM manage_supplier WHERE status = 1 ORDER BY supplier_name";
					$result = mysqli_query($con, $sel_query);
					while ($row = mysqli_fetch_assoc($result)) {
						$selected = ($row['sup_id'] == 1) ? 'selected' : '';
						echo "<option value='{$row['sup_id']}' $selected>" . htmlspecialchars($row['supplier_name']) . "</option>";
					}
					?>
					</select>
			
		</td>
		<td align='left' >
			<!-- <a href="manage_supplier.php?from_grn=1"><button type="button" class="btn btn-success" >New</button></a> -->
		<!-- CUSTOMER NAME : -->
		</td>
		<td>
		<!-- <input style='max-width: 50%;' type='text'  class='form-control'  name='cash_customer' > -->
		</td>
	   
	</tr>
	 
	</table>
	</form>



	<div class="table-responsive" style='display: contents;'>
		<table class="table  table-bordered  " id='table1'>
			<tbody>
				<tr bgcolor="#5499C7">

					<td width="30%" align="center"><b>Item</b></td>

					<td align="center" width=15%><b>Quantity</b></td>
					<td align="center" width=15%><b>Purchase price</b></td>
					<td align="center" width=15%><b>Value</b></td>
					<td align="center" width=15%><b>Sales Price</b></td>
					<td align="center" width=15%><b>Action</b></td>
				</tr>




				<?php
$total_inv = 0;
				$count = 1;
				$user = $_SESSION['username'];
				$sel_query = "SELECT oil_grn_temp.* , product_master.* from oil_grn_temp 
									INNER JOIN product_master
									ON 
									product_master.p_id = oil_grn_temp.s_id
									where user = '$user'";
				$result = mysqli_query($con, $sel_query);
				$no_items = mysqli_num_rows($result);
				while ($row = mysqli_fetch_assoc($result)) { ?>
					<tr>
						<td width="30%"> <?php //echo  "(".$row['m_code'].") ";
						 
						 echo $row['p_name']. " - (".$row['p_unit'].")";
						 ?> </td>

						<td align="center"><?php echo $row['qty']; ?></td>
						<td align="right"><?php echo number_format($row['rate'], 2, '.', ','); ?></td>
						<td align="right"><?php $row_value = $row['qty'] * $row['rate'];
											echo  number_format($row_value, 2, '.', ',');
											$total_inv = $total_inv + $row_value;
											?></td>
							<td align="right"><?php echo number_format($row['sales_price'], 2, '.', ','); ?></td>				 
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
				<select id="mySelect_search" name='group'   required  class="form-control input-sm" style="width: 300px;" >
                 <option value='0'>Select</option>
              
                  <?php
				   
				  
                     $sel_query="SELECT * from product_master where status = 1 and p_cat<>'Fuel' ";
                     $result = mysqli_query($con,$sel_query);
                     while($row = mysqli_fetch_assoc($result)) { 
                           echo "<option value='".$row['p_id']."'> ";
						    
						   echo $row['p_name']. " - (".$row['p_unit'].")  " ;
						   echo "</option>"; 
                     }?>
                  </select> 
                 
					<input type="hidden" id="selected_value">
					<input type="hidden" id="selected_item">
					<input type="hidden" id="cost_price">



				</td>



				<td bgcolor="#D4E6F1"  width=16%><input type="number" min="0" min="0.001" step="0.001" class="form-control" placeholder="Quantity" required class="items" name="item_qty" id="qty" onchange="priceCalculation();"></td>
				<td bgcolor="#D4E6F1" align="right"  width=16%>
				    <input type='hidden' value=''  class="form-control"  placeholder="Discount %" id='discount'>
				    
				    <input style="text-align:right;"  type="number" step='0.01'  class="form-control" placeholder="Purchase Price" required name="cost_price" id="pricez" onchange="priceCalculation();"></td>
			
				<td bgcolor="#D4E6F1"  width=19%><input type="text" readonly class="form-control" style="text-align:right;" placeholder="Line value" required id="line_total"></td>
				<td  width=15%> <input style="text-align:right;" type="number" step='0.01' class="form-control" placeholder="Sales Price" required name="sales_price" id="sales_price"></td>
				<td bgcolor="#D4E6F1"  style="display: none;"  align="right"  width=15%><input style="text-align:right;" type="hidden" class="form-control" placeholder="exp_date" data-toggle="tooltip" data-placement="top" title="Expiry date  Month ,Year"  required name="exp_date" id="exp_date"  ></td>
				<td bgcolor="#D4E6F1" align="left"  width=15%> <input class="btn btn-primary" id="button" type="submit" name="add_item" value="Add line">
				</td>
				<script>
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
							     
							var table = document.getElementById("table1");
						    var totalRowCount = table.rows.length; // 5
							var lablex =  $('#mySelect_search option:selected').text();
				// 			var rate = document.getElementById('pricez').value
				// 			var sales_price = document.getElementById('sales_price').value
							var rate = parseFloat(document.getElementById('pricez').value) || 0;
                            var sales_price = parseFloat(document.getElementById('sales_price').value) || 0;


							
							var exp_date = document.getElementById('exp_date').value
							var cost = document.getElementById('cost_price').value
                            var valuez = rate * qty;


							var total_value = document.getElementById('total_bill').value

			

							var valuex = valuez.toLocaleString(
                                                        undefined, {
                                                            minimumFractionDigits: 2
                                                        }
                                                    );
						 
													 if (rate < 0.01) {
														notify('danger', 'Please enter valid Rate before add product.');
                                                            return;
                                                        }


														if (rate >= sales_price) {
														notify('danger', 'Please add correct selling Price');
                                                            return;
                                                        }
													 
document.getElementById("submit_button").disabled = false;
							var new_total =  +total_value + +valuez; 
							total_bill.value = new_total;
							total_bill1.value = new_total;
														document.getElementById('total_lable').innerHTML = new_total;	
													$("#qty").val("");
                                                    line_total.value = '';
													pricez.value = '';
													// sales_price.value = '';
													document.getElementById("sales_price").value = "";
													$("#mySelect_search").val("0").trigger("change")
													exp_date.value='';
                                                   var discount = document.getElementById('discount').value
                                                   var rate = rate * (100-discount)/100;
                                                   

													 let template = `
                                                                    <tr>
                                                                        <td>${lablex}</td>
                                                      
                                                                        <td align="center">${qty}</td>
                                                                        <td align="right">${rate}</td>
                                                                        <td align="right">${valuex}</td>
                                                                        <td align="right">${sales_price}</td>
                                                                        
                                                                        <td align="center"><form method="POST" action="">
                                                                        <input type="hidden" name="temp_id" value="${totalRowCount}">
                                                                        <input type="submit" name='delete' class="btn btn-danger" value=" X ">
                                                                        </form></td>
                                                                    </tr>`;

                                                    table.innerHTML += template;

													var bill = "GRN";
                                                    $.ajax({
                                                        type: "POST",
                                                        url: "oil_ajax/grn_temp.php",
                                                        data: {
                                                            x_id: s_id,
                                                            grn:bill,
                                                            qty: qty,
                                                            rate: rate,
                                                            cost: cost,
                                                            exp_date: exp_date,
															sales_price:sales_price,
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

						<b>Total -<h3  id="total_lable"  ><?php 
						if (!isset($total_inv)) {
    $total_inv = 0;
}
echo number_format($total_inv, 2, '.', ','); ?></h3>
						</b>
					</td><td>&nbsp;&nbsp;&nbsp;</td>
					<td>
					     <input name="submit_grn" type='hidden' form="form1z"  value='1'>
						<button class="btn btn-primary"     type="submit" form="form1z" id="submit_button" value="Submit">Save & New</button>


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

  <!--<script>-->
  <!--  document.getElementById('submit_button').addEventListener('click', function(event) {-->
  <!--    var button = event.target;-->
  <!--    button.disabled = true;-->
  <!--    button.innerHTML = 'Please wait <i class="fa fa-gear fa-spin" style="font-size:24px"></i>';-->
  <!--  });-->
  <!--</script>-->


<script>
   
    $(document).ready(function() {
    $('#mySelect_search').select2();
  });
</script>

<?php 
if(isset($_REQUEST['Selct_supplier'])){
   echo "<script>
   $( document ).ready(function() {
	notify('info', 'Please select New supplier form the supplier list ');
      });
   </script>";
}
?>