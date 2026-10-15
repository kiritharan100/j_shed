<?php
include 'header.php'; // This file must set $user_id, $location_id if needed

date_default_timezone_set('Asia/Colombo'); // Set as needed

// Default date range current year start and today
$start_date = $_GET['start_date'] ?? date('Y-01-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Fetch client locations for dropdown
$clients_q = mysqli_query($con, "SELECT c_id, client_name FROM client_registration ORDER BY client_name");

// Fetch reasons for dropdown
$reasons = [
    // "Evaporation",
    "Stock Count",
    // "Tank Leakage",
    // "Delivery Shortage",
    // "Calibration Loss",
    // "Pump Meter Error",
    "System Adjustment"
    // "Tank Cleaning",
    // "Unauthorized Dispense",
    // "Contamination Disposal"
];
 $reason_list = "'" . implode("','", array_map('mysqli_real_escape_string', array_fill(0, count($reasons), $con), $reasons)) . "'";

 

?>
<br>
<div class="content-wrapper">
    <div class="container-fluid">
        <h3>Manage Fuel Stock <?= $client_name ?></h3><br>

           <div class="card">
            <div class="card-header">

<!-- 
    <div class="alert alert-warning" role="alert">
  <p class="mb-0"><strong>Allowed cancellation duration:</strong> 1 hours from creation</p>
</div> -->


        <form method="GET" class="form-inline mb-3">
            <label for="start_date">Start Date:&nbsp;</label>
            <input type="date" name="start_date" id="start_date" class="form-control mr-2" value="<?= htmlspecialchars($start_date) ?>" required>
            <label for="end_date">End Date:&nbsp;</label>
            <input type="date" name="end_date" id="end_date" class="form-control mr-2" value="<?= htmlspecialchars($end_date) ?>" required>
            <button type="submit" class="btn btn-primary">Filter</button>
            <button type="button" class="btn btn-success ml-auto" data-toggle="modal" data-target="#addAdjustmentModal">Add Adjustment</button>
                     <button type='button' id="exportButton" filename='<?php echo "FUEL_ADJUSTMENT_".$start_date."_".$end_date; ?>.xlsx' class="btn btn-primary"><i class="ti-cloud-down"></i> Export</button>
<button type="button" class="btn btn-info" data-toggle="modal" data-target="#adjustmentInfoModal">
  <i class="ti-info-alt"></i> Information
</button>
        </form>
<hr>
        <div class="table-responsive">
     



            <?php
  $query = "
SELECT fsa.id, fsa.location_id, cr.client_name, fsa.date_time, fsa.adjustment_date, fsa.l_sales,fsa.purchase_price, fsa.sales_price,
       fsa.p_id, pm.p_name, fsa.adj_qty, fsa.l_sales, fsa.e_allowance, fsa.short, 
       fsa.purchase_price, fsa.sales_price, fsa.status 
FROM fuel_stock_adjustment fsa
JOIN client_registration cr ON fsa.location_id = cr.c_id
JOIN product_master pm ON fsa.p_id = pm.p_id
 
WHERE fsa.date_time BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59' AND fsa.location_id = '$location_id'
   
ORDER BY fsa.date_time DESC
";

$result = mysqli_query($con, $query);
?>

 
 
<table id='example' class="table table-bordered table-hover">
    <thead>
        <tr>
            <th>#</th>
            <th width='150 px'>Date/Time</th>
             <th>Adjusted Date</th>
           
            <th>Product</th>
            <th>Adjusted <br> Qty </th>
            <th>Last Month <br> Sales (L)</th>
            <th>Ev Allowance <br> Max (L) </th>
            <th>Evaporation <br> Allowance (L)</th>
          
            
            <th>Evaporation<br> loss (Rs) </th>
            
              <th>Short (L)</th>
               <th>Cost Price </th>
            <th>Sales Price</th>
            <th>Short Value (Rs)</th>
            <th>action</th>
            
        </tr>
    </thead>
    <tbody>
    <?php if(!$result || mysqli_num_rows($result) === 0): ?>
        <tr><td colspan="11" class="text-center">No records found.</td></tr>
    <?php else:
        $count = 1;
        while ($row = mysqli_fetch_assoc($result)) {
            $can_cancel = false;
            if ($row['status'] == 1) {
                $created_time = strtotime($row['date_time']);
                $now = time();
                if (($now - $created_time) <= 3 * 3600) { // within 3 hours
                    $can_cancel = true;
                }
            }
            $row_class = ($row['status'] == 0) ? 'table-danger cancelled-row' : '';

            // Calculate losses
            $loss_value = floatval($row['e_allowance']) * floatval($row['purchase_price']);
            
           if($row['adj_qty'] < 0 ){ 
               $short = $row['adj_qty'] + $row['e_allowance'];
            $sales_loss = floatval($short) * floatval($row['sales_price']);
               
           } else{ $short = 0;  $sales_loss = 0;
               
           }
            ?>
            <tr class="<?= $row_class ?>">
                <td><?= $count++ ?></td>
                <td><?= htmlspecialchars($row['date_time']) ?></td>
                <td><?= htmlspecialchars($row['adjustment_date']) ?></td>
                <td><?= htmlspecialchars($row['p_name']) ?></td>
                <td class="text-right"><?= number_format($row['adj_qty'], 3) ?></td>
                <td class="text-right"><?= number_format($row['l_sales'], 2) ?></td>
                 <td class="text-right"><?= number_format($row['l_sales']/6600*32.5, 2) ?></td>
                <td class="text-right"><?= number_format($row['e_allowance'], 3) ?></td>
                <td class="text-right"><?= number_format($loss_value, 2) ?></td>
                 <td class="text-right"><?= number_format($short, 3) ?></td>
                     <td class="text-right"><?= number_format($row['purchase_price'], 2) ?></td>
                 <td class="text-right"><?= number_format($row['sales_price'], 2) ?></td>
                <td class="text-right"><?= number_format($sales_loss, 2) ?></td>
                <td class="text-center">
    <?php if (floatval($row['l_sales']) > 0): ?>
        <button 
            class="btn btn-sm btn-primary view-sales-btn" 
            data-pid="<?= $row['p_id'] ?>" 
            data-location="<?= $row['location_id'] ?>" 
            data-date="<?= $row['adjustment_date'] ?>">
            View Sales
        </button>
    <?php endif; ?>
</td>
            </tr>
        <?php }
    endif; ?>
    </tbody>
</table>

        </div>
        </div>
    </div>
</div>

<div class="modal fade" id="salesModal" tabindex="-1" role="dialog" aria-labelledby="salesModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header bg-info text-white">
        <h5 class="modal-title" id="salesModalLabel">Sales Details</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
          <span>&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div id="sales-details">
          <!-- AJAX will inject data here -->
        </div>
      </div>
    </div>
  </div>
</div>



<!-- Modal for Add Adjustment -->
<div class="modal fade" id="addAdjustmentModal" tabindex="-1" role="dialog" aria-labelledby="addAdjustmentModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <form id="addAdjustmentForm">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="addAdjustmentModalLabel">Add Fuel Stock Adjustment</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button>
        </div>
        <div class="modal-body">

        <?php $last_day_prev_month = date("Y-m-t", strtotime("first day of previous month")); ?>
        <div class="form-group">
            <label for="adjustment_date">Adjustment Date</label>
            <input type="date" class="form-control" id="adjustment_date" name="adjustment_date"
                  value="<?= $last_day_prev_month ?>" required>
          </div>


     

          <div class="form-group">
            <label for="p_id">Product</label>
           <input type="hidden" id="location_id" name='location_id' value="<?php echo $location_id; ?>">
            <select name="p_id" id="p_id" class="form-control" required>
              <option value="">Select Product</option>
              <!-- Loaded dynamically by AJAX -->
            </select>
          </div>

          <div class="form-group">
            <label for="description">Reason</label>
            <select name="description" id="description" class="form-control" required>
              <option value="">Select Reason</option>
              <?php foreach ($reasons as $reason): ?>
                <option value="<?= $reason ?>"><?= $reason ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label>Exces Quantity ( liters)</label>
            <input type="number" step="0.001" min="0" name="debit" id="debit" class="form-control" placeholder="Exces Quantity  (if applicable)">
          </div>

          <div class="form-group">
            <label for="purchase_price">Purchase Price (With VAT)</label>
            <input type="number" step="0.01" min="0" name="purchase_price" id="purchase_price" class="form-control" placeholder="Enter purchase price">
          </div>

          <div class="form-group">
            <label for="sales_price">Sales Price (With VAT)</label>
            <input type="number" step="0.01" min="0" name="sales_price" id="sales_price" class="form-control" placeholder="Enter sales price">
          </div>

          <div class="form-group">
            <label>Short Quantity (Liters)</label>
            <input type="number" step="0.001" min="0" name="credit" id="credit" class="form-control" placeholder="Enter Short Qty  (if applicable)">
            
          </div>


          
<div id="evaporationFields" style="display:none;">
  <div class="form-group">
    <label>Fuel Sold Last Month (Liters)</label>
    <input type="number" step="0.001" class="form-control" id="fuel_sold_last_month" readonly>
    <input type="hidden" name="fuel_sold_last_month" id="fuel_sold_last_month_hidden">
  </div>

  <div class="form-group">
    <label>Evaporation Allowance ( >> sales /6600 *32.5 <<)</label>
    <input type="number" step="0.001" class="form-control" id="evaporation_allowance" readonly>
  </div>

  <div class="form-group">
    <label>Short Quantity (Liters)</label>
    <input type="number" step="0.001" class="form-control" id="short_qty" readonly>
  </div>
</div>

          <div class="alert alert-info" id="debitCreditAlert">
            Enter either debit or credit. One must be greater than zero.
          </div>

        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Save Adjustment</button>
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        </div>
      </div>
    </form>
  </div>
</div>


<script>
$(document).on('click', '.view-sales-btn', function () {
    let p_id = $(this).data('pid');
    let location = $(this).data('location');
    let adj_date = $(this).data('date');

    $.ajax({
        url: 'ajax/get_sales_by_date.php',
        method: 'POST',
        data: {
            p_id: p_id,
            location_id: location,
            adjustment_date: adj_date
        },
        success: function (response) {
            $('#sales-details').html(response);
            $('#salesModal').modal('show');
        },
        error: function () {
            $('#sales-details').html('<div class="alert alert-danger">Failed to load sales data.</div>');
        }
    });
});
</script>


<!-- Your existing HTML remains the same -->

 <script>

  function calculateEvaporationFields(fuelSales, credit) {
  const allowance = (fuelSales / 6600) * 32.5;
  $('#evaporation_allowance').val(allowance.toFixed(3));

  const shortQty = Math.max(0, credit - allowance);
  $('#short_qty').val(shortQty.toFixed(3));
}
 

function fetchFuelSales(productId, locationId, callback) {
  const date = $('#adjustment_date').val(); // use the selected date
 
  $.ajax({
    url: 'ajax/get_evaporation_sales.php',
    method: 'POST',
    data: { p_id: productId, location_id: locationId, end_date: date },
    dataType: 'json',
 success: function(response) {
  if (response.success) {
    const fuelSales = parseFloat(response.fuel_sales) || 0;
    $('#fuel_sold_last_month').val(fuelSales.toFixed(3));

 
$('#fuel_sold_last_month_hidden').val(fuelSales); // this sends to backend



    const credit = parseFloat($('#credit').val()) || 0;
    calculateEvaporationFields(fuelSales, credit);
  } else {
    $('#fuel_sold_last_month').val('');
    calculateEvaporationFields(0, 0);
  }
}
  });
}

$(document).ready(function() {
  
    $('#debit').on('input', function() {
       const p_id = $('#p_id').val();
        var debitVal = parseFloat($(this).val()) || 0;
        const description = $('#description').val();

        if (debitVal > 0) {
            $('#credit').val(''); // Clear credit
            $('#evaporationFields').hide();
        } else {
            $('#evaporationFields').hide();
        }
    });

    $('#credit').on('input', function() {
       const p_id = $('#p_id').val();
        var creditVal = parseFloat($(this).val()) || 0;
        const description = $('#description').val();

        if (creditVal > 0) {
            $('#debit').val(''); // Clear debit
            if ( description === 'Stock Count' && (p_id === '1' || p_id === '2')) {
            $('#evaporationFields').show();
            } else {
              $('#evaporationFields').hide();
              
            }
        } else {
            $('#evaporationFields').hide();
        }
    });
});



 $(document).ready(function () {
    
  $('#description, #p_id, #adjustment_date').on('change', function () {
    const description = $('#description').val();
    const p_id = $('#p_id').val();
    const location_id = $('#location_id').val();

    if ( description === 'Stock Count' && (p_id === '1' || p_id === '2')) {
      $('#evaporationFields').slideDown();

      fetchFuelSales(p_id, location_id, function (fuelSales) {
        const credit = parseFloat($('#credit').val()) || 0;
        $('#fuel_sold_last_month').val(fuelSales.toFixed(3));
        calculateEvaporationFields(fuelSales, credit);
      });

    } else {
      $('#evaporationFields').slideUp();
    }
  });

  $('#credit').on('input', function () {
    const credit = parseFloat($(this).val()) || 0;
    const fuelSales = parseFloat($('#fuel_sold_last_month').val()) || 0;
    calculateEvaporationFields(fuelSales, credit);
  });
});



 
</script>





<script>
  function togglePriceFields() {
    const debit = parseFloat($('#debit').val()) || 0;
    if (debit > 0) {
      $('#purchase_price').closest('.form-group').show();
      $('#sales_price').closest('.form-group').show();
    } else {
      $('#purchase_price').val('').closest('.form-group').hide();
      $('#sales_price').val('').closest('.form-group').hide();
    }
  }

  $('#debit, #credit').on('input', togglePriceFields);

  $(document).ready(function() {
    togglePriceFields(); // Initial state
  });
</script>

<script>
$(document).ready(function(){
    // Load fuel products when location selected
    $('#location_id').change(function(){
        var locId = $(this).val();
        if(!locId) {
            $('#p_id').html('<option value="">Select Product</option>');
            return;
        }
        $.ajax({
            url: 'ajax/ajax_get_fuel_products1.php',
            type: 'POST',
            data: { location_id: locId },
            success: function(res) {
                var options = '<option value="">Select Product</option>';
                $.each(res, function(i, product){
                    options += '<option value="'+product.p_id+'">'+product.p_name+'</option>';
                });
                $('#p_id').html(options);
            },
            error: function() {
                alert('Error loading products');
            }
        });
    });
       $('#location_id').trigger('change');

    // Validation: only one of debit or credit should be > 0
    $('#addAdjustmentForm').submit(function(e){
        e.preventDefault();
        var debit = parseFloat($('#debit').val()) || 0;
        var credit = parseFloat($('#credit').val()) || 0;
        if(debit <= 0 && credit <= 0) {
            alert('Please enter a  Short or exces Qty greater than zero.');
            return false;
        }
        if(debit > 0 && credit > 0) {
            alert('Please enter either debit or credit, not both.');
            return false;
        }
        // Submit form via ajax
    $.ajax({
    url: 'ajax/ajax_add_fuel_stock_adjustmentStock Count',
    type: 'POST',
    data: $(this).serialize(),
    success: function (response) {
        // Check if success
        if (response.success) {
            Swal.fire({
                icon: 'success',
                title: 'Adjustment Saved!',
                html: '<p>Adjustment saved successfully.</p><p><strong>Note:</strong> Cancellation is allowed only within 1 hour from creation.</p>',
                timer: 5000,
                timerProgressBar: true,
                showConfirmButton: false
            }).then(() => {
                location.reload();
            });
        }
        // Check if response has status === 'error'
        else if (response.status === 'error') {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: response.message || 'An unknown error occurred.'
            });
            console.error('Error detail:', response.message);
        }
        // Unknown response
        else {
            Swal.fire({
                icon: 'warning',
                title: 'Unexpected Response',
                text: 'The server returned an unexpected format.'
            });
            console.warn('Unexpected response:', response);
        }
    },
    error: function (xhr) {
        Swal.fire({
            icon: 'error',
            title: 'AJAX Error',
            text: xhr.statusText
        });
        console.error('XHR Error:', xhr.responseText);
    }
});




    });

    // Cancel adjustment button
    $('.cancel-adjustment-btn').click(function(){
        var id = $(this).data('id');
        if(confirm('Are you sure you want to cancel this adjustment?')) {
            $.ajax({
                url: 'ajax/ajax_cancel_fuel_stock_adjustment.php',
                type: 'POST',
                data: {id: id},
                success: function(res){
                    if(res.success){
                        alert('Adjustment cancelled');
                        location.reload();
                    } else if(res.error){
                        alert('Error: ' + res.error);
                    } else {
                        alert('Unexpected server response.');
                    }
                },
                error: function(){
                    alert('Failed to cancel adjustment.');
                }
            });
        }
    });
    
    $.fn.dataTable.ext.errMode = 'none';

    $('#example').DataTable({
        "pageLength": 25
    });
    

});
  $(document).ready(function () {
       $.fn.dataTable.ext.errMode = 'none';
  $('#example1').DataTable({
    pageLength: 10 // Change as needed
     
  
  });
});



$('#addAdjustmentForm').submit(function (e) {
    e.preventDefault();

    $.ajax({
        url: 'ajax/ajax_add_fuel_stock_adjustment.php',
        type: 'POST',
        data: $(this).serialize(),
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Adjustment Saved',
                    text: 'Fuel stock adjustment saved successfully!',
                    timer: 2500,
                    showConfirmButton: false
                }).then(() => location.reload());
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: response.message || response.error || 'Something went wrong.'
                });
            }
        },
        error: function (xhr) {
            Swal.fire({
                icon: 'error',
                title: 'AJAX Error',
                text: xhr.statusText
            });
            console.error(xhr.responseText);
        }
    });
});


</script>


<div class="modal fade" id="adjustmentInfoModal" tabindex="-1" role="dialog" aria-labelledby="adjustmentInfoModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xxl modal-dialog-scrollable" role="document" style="max-width: 85%;">
    <div class="modal-content">
      <div class="modal-header bg-info text-white">
        <h5 class="modal-title" id="adjustmentInfoModalLabel">Fuel Stock Adjustment – Journal Explanation</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <h5 class="text-primary">A. Standard Excess Adjustment (Debit)</h5>
       
        <table class="table table-bordered table-sm">
          <thead class="thead-light"><tr><th>Account</th><th>Dr / Cr</th><th>Description</th></tr></thead>
          <tbody>
            <tr><td>Inventory (<code>Cost Price</code>)</td><td>Dr</td><td>Fuel stock increased</td></tr>
            <tr><td>Inventory Adjustment (<code>Cost Price</code>)</td><td>Cr</td><td>Offset for system adjustment</td></tr>
          </tbody>
        </table>

        <h5 class="text-primary">B. Standard Shortage Adjustment (Credit)</h5>
      
        <table class="table table-bordered table-sm">
          <thead class="thead-light"><tr><th>Account</th><th>Dr / Cr</th><th>Description</th></tr></thead>
          <tbody>
            <tr><td>COGS – Fuel Shortage (<code>Cost Price</code>)</td><td>Dr</td><td>Value of shortage at cost</td></tr>
            <tr><td>Inventory (<code>Cost Price</code>)</td><td>CR</td><td>Reduce inventory</td></tr>
            <tr><td>Manager Short Receivable (<code>Sales Price</code>)</td><td>Dr</td><td>Expected recovery from manager</td></tr>
            <tr><td>Sales of Shortage Income (<code>Sales Price</code>)</td><td>Cr</td><td>Recognize sales revenue from short</td></tr>
          </tbody>
        </table>

        <h5 class="text-primary">C. Evaporation Adjustment (Petrol & Super Petrol Only)</h5>
        <!-- <p><strong>Triggered When:</strong> Product = Petrol/Super Petrol <code>(p_id = 1 or 2)</code> and Description = <code>Stock Count</code></p> -->
        <p><strong>Evaporation Allowance:</strong> Last Month Sales / 6600 × 32.5</p>
        <table class="table table-bordered table-sm">
          <thead class="thead-light"><tr><th>Account</th><th>Dr / Cr</th><th>Description</th></tr></thead>
          <tbody>
         
            <tr><td>Evaporation Allowance (<code>Cost Price</code>)</td><td>Dr</td><td>Allowable loss due to evaporation</td></tr>
            <tr><td>Inventory (<code>Cost Price</code>)</td><td>Cr</td><td>Total stock reduction</td></tr>
               <tr><td>  COGS (<code>Cost Price</code>)</td><td>Dr</td><td>Short quantity over allowance</td></tr>
            <tr><td>Manager Short Receivable (<code>Sales Price</code>)</td><td>Dr</td><td>Expected sales value from shortage</td></tr>
            <tr><td>Sales of Shortage Income (<code>Sales Price</code>)</td><td>Cr</td><td>Recognized income from short sale</td></tr>
          </tbody>
        </table>

     
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>



<?php include 'footer.php'; ?>


