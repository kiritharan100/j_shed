<?php
include 'header.php';



$user_id = $user_id;  // from header/session

// Handle Date Filter Inputs
$start_date = $_GET['start_date'] ?? date('Y-m-d');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Open Shift (POST)
if (isset($_POST['open_shift'])) {
    $location_id = $location_id;
    $operator_id = $_POST['operator_id'];
    $start_time_input = $_POST['start_time']; // HH:mm
    $start_time = date('Y-m-d') . ' ' . $start_time_input . ':00';
    $created_by = $user_id;

    // Insert new shift
    $insert_shift = "INSERT INTO shed_operator_shift (location_id, operator_id, start_time, open_status, created_by, created_on, status)
                     VALUES ('$location_id', '$operator_id', '$start_time', 1, '$created_by', NOW(), 1)";
    mysqli_query($con, $insert_shift);
    $shift_id = mysqli_insert_id($con);

    // Insert pump assignments
    if (!empty($_POST['pumps']) && is_array($_POST['pumps'])) {
        foreach ($_POST['pumps'] as $pump_id) {
            // Pump info
            $pump_q = mysqli_query($con, "SELECT product_assigned FROM manage_pump WHERE id = $pump_id");
            $pump_row = mysqli_fetch_assoc($pump_q);
            $product_id = $pump_row['product_assigned'];

            // Last closing reading for this pump
            $last_closing_q = mysqli_query($con, "SELECT closing_reading FROM shed_pump_assign WHERE pump_id = $pump_id AND status = 1 ORDER BY id DESC LIMIT 1");
            $last_closing_row = mysqli_fetch_assoc($last_closing_q);
            $opening_reading = $last_closing_row ? $last_closing_row['closing_reading'] : 0;

            // Fuel price at start_time
            $price_q = mysqli_query($con, "SELECT new_price FROM fuel_price_change WHERE p_id = $product_id AND status = 1 AND date_time <= '$start_time' ORDER BY date_time DESC LIMIT 1");
            $price_row = mysqli_fetch_assoc($price_q);
            $sales_price = $price_row ? $price_row['new_price'] : 0;

            // Insert pump assign
            $insert_pump_assign = "INSERT INTO shed_pump_assign (shift_id, pump_id, product_id, sales_price, opening_reading, open_stats, status)
                                  VALUES ($shift_id, $pump_id, $product_id, $sales_price, $opening_reading, 1, 1)";
            mysqli_query($con, $insert_pump_assign);
        }
    }
    echo "<script> notify('success', 'Shift opened successfully'); </script>";
}

// Close Shift (POST)
// if (isset($_POST['close_shift'])) {
//     $shift_id = intval($_POST['shift_id']);
//     $end_time_input = $_POST['end_time']; // HH:mm
//     $end_time = date('Y-m-d') . ' ' . $end_time_input . ':00';

//     // Update shift end time and status
//     $update_shift = "UPDATE shed_operator_shift SET end_time = '$end_time', open_status = 0 WHERE shift_id = $shift_id";
//     mysqli_query($con, $update_shift);

//     // Update pump assignments closing readings and open_stats
//     if (isset($_POST['pump_ids']) && isset($_POST['closing_readings'])) {
//         foreach ($_POST['pump_ids'] as $index => $pump_assign_id) {
//             $closing_reading = floatval($_POST['closing_readings'][$index]);
//             $update_pump = "UPDATE shed_pump_assign SET closing_reading = $closing_reading, open_stats = 0 WHERE id = $pump_assign_id";
//             mysqli_query($con, $update_pump);
//         }
//     }
//     echo "<script> notify('success', 'Shift closed successfully'); </script>";
// }

// Fetch shifts in date range for this location
$shifts_q = mysqli_query($con, "
    SELECT s.*, o.op_name
    FROM shed_operator_shift s
    LEFT JOIN manage_pump_operator o ON s.operator_id = o.op_id
    WHERE s.location_id = $location_id
      AND (
            DATE(s.start_time) = '$start_date'
         OR (
            DATE(s.start_time) < '$start_date'
            AND s.open_status = 1
         )
      )
    ORDER BY s.start_time DESC
");

// $shifts_q = mysqli_query($con, "
//     SELECT s.*, o.op_name
//     FROM shed_operator_shift s
//     LEFT JOIN manage_pump_operator o ON s.operator_id = o.op_id
//     WHERE s.location_id = $location_id
//       AND DATE(s.start_time) BETWEEN '$start_date' AND '$end_date'
//     ORDER BY s.start_time DESC
// ");

// Fetch pumps currently assigned to open shifts (to exclude in open shift modal)
$busy_pumps_q = mysqli_query($con, "
    SELECT spa.pump_id
    FROM shed_pump_assign spa
    JOIN shed_operator_shift s ON spa.shift_id = s.shift_id
    WHERE s.location_id = $location_id AND s.open_status = 1 AND spa.status = 1
");
$busy_pumps = [];
while ($row = mysqli_fetch_assoc($busy_pumps_q)) {
    $busy_pumps[] = $row['pump_id'];
}

// Fetch available pumps (not assigned in open shifts)
if (count($busy_pumps) > 0) {
    $busy_pumps_in = implode(',', $busy_pumps);
    $available_pumps_q = mysqli_query($con, "
        SELECT m.id, m.pump_name, m.product_assigned, p.p_name
        FROM manage_pump m
        LEFT JOIN product_master p ON m.product_assigned = p.p_id
        WHERE m.location_id = $location_id AND m.status = 1
          AND m.id NOT IN ($busy_pumps_in)
        ORDER BY m.pump_name
    ");
} else {
    $available_pumps_q = mysqli_query($con, "
        SELECT m.id, m.pump_name, m.product_assigned, p.p_name
        FROM manage_pump m
        LEFT JOIN product_master p ON m.product_assigned = p.p_id
        WHERE m.location_id = $location_id AND m.status = 1
        ORDER BY m.pump_name
    ");
}

?>
  
<div class="content-wrapper">
  <div class="container-fluid">
    <div class="main-header">
      <h4>Manage Pump Operator Shifts</h4>
      <hr>
      <form method="get" class="form-inline mb-3">
        <label for="start_date">Start Date:&nbsp;</label>
        <input type="date" name="start_date" id="start_date" class="form-control mr-3" value="<?= htmlspecialchars($start_date) ?>" required>
        <label for="end_date">End Date:&nbsp;</label>
        <input type="date" name="end_date" id="end_date" class="form-control mr-3" value="<?= htmlspecialchars($end_date) ?>" required>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter"></i> Filter</button>
         <button class="btn btn-primary mt-3 mb-5" data-toggle="modal" data-target="#openShiftModal" type='button'> <i class="fa-solid fa-folder-open"></i> Open New Shift</button>
      </form>
    </div>

     <div class="card">
     <div class="card-block">
         
    <div class="table-responsive">
      <table id='example' class="table table-bordered table-hover">
        <thead>
          <tr>
            <th>#</th>
            <th>Operator</th>
            <th>Start Time</th>
            <th>End Time</th>
            <th>Sales Rs.</th>
            <th>Card Sales</th>
            <th>Credit Sales</th> <th>Exces/Short</th>
            <th>Status</th>
           
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $count = 1;
          while ($shift = mysqli_fetch_assoc($shifts_q)) {
              $status_label = $shift['open_status'] == 1 ? 'Open' : 'Closed';
              // if($shift['open_status'] == 1){$end_time = "";} else {$end_time= }
              $row_color = $shift['open_status'] == 1 ? 'style="background-color:#f8d7da;"' : 'style="background-color:#d4edda;"';

              echo "<tr $row_color>";
              echo "<td>{$count}</td>";
              echo "<td>" . htmlspecialchars($shift['op_name']) . "</td>";
              echo "<td>" . date('Y-m-d H:i', strtotime($shift['start_time'])) . "</td>";
              echo "<td>" . ($shift['open_status'] == 0 ? date('Y-m-d H:i', strtotime($shift['end_time'])) : '????') . "</td>";
              echo "<td align='right'>" . number_format($shift['total_sales'],2) . "</td>";
              echo "<td align='right'>" . number_format($shift['total_card_sales'],2) . "</td>";
              echo "<td align='right'>" . number_format($shift['total_credit_sales'],2) . "</td>";
              echo "<td align='right'>" . number_format($shift['exces_short'],2) . "</td>";
              echo "<td>$status_label</td>";
              echo "<td align='center'>";
              echo "<button class='btn btn-info btn-sm view-shift-btn' data-shift-id='{$shift['shift_id']}' data-toggle='modal' data-target='#viewShiftModal'>View</button> ";
              if ($shift['open_status'] == 1) {
                  echo "<button class='btn btn-success btn-sm close-shift-btn' data-shift-id='{$shift['shift_id']}'>Close Shift</button>";
              }
              echo "</td>";
              echo "</tr>";
              $count++;
          }
          ?>
        </tbody>
      </table>
    </div>
        </div>
        <div>
    <!-- Open Shift Button -->
    

  </div>
</div>
        

<!-- View Shift Modal -->
<div class="modal fade" id="viewShiftModal" tabindex="-1" role="dialog" aria-labelledby="viewShiftModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="viewShiftModalLabel">Shift Summary</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button>
      </div>
      <div class="modal-body" id="viewShiftDetails">
        <!-- Shift summary will load here by AJAX -->
        <div class="text-center text-muted">Loading...</div>
      </div>
      <div class="modal-footer">
    
        <button class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Open Shift Modal -->
<div class="modal fade" id="openShiftModal" tabindex="-1" role="dialog" aria-labelledby="openShiftModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <form id="openShiftForm" method="POST" autocomplete="off">
      <input type="hidden" name="open_shift" value="1">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="openShiftModalLabel">Open New Shift</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button>
        </div>
        <div class="modal-body">
 <div class="form-group row">
  <label class="col-sm-3 col-form-label">Pump Operator</label>
  <div class="col-sm-9">
    <select name="operator_id" class="form-control" required>
      <option value="">Select Operator</option>
      <?php
      $ops_q = mysqli_query($con, "SELECT op_id, op_name FROM manage_pump_operator WHERE location_id = $location_id AND status = 1 ORDER BY op_name");
      while ($op = mysqli_fetch_assoc($ops_q)) {
          echo "<option value='{$op['op_id']}'>" . htmlspecialchars($op['op_name']) . "</option>";
      }
      ?>
    </select>
  </div>
</div>

          <div class="form-group row">
            <label class="col-sm-3 col-form-label">Assign Pumps <br> Hold <button type='button'> Ctrl  </button> in the keyboard   to select multiple pumps</label>
            
            <div class="col-sm-9">
              <select multiple name="pumps[]" class="form-control" id="pumpsSelect" required style="height:160px; font-size: 20px;">
                <?php
                while ($pump = mysqli_fetch_assoc($available_pumps_q)) {
                    // Get last closing reading
                    $pump_id = $pump['id'];
                    $last_closing_q = mysqli_query($con, "SELECT closing_reading FROM shed_pump_assign WHERE pump_id = $pump_id AND status = 1 ORDER BY id DESC LIMIT 1");
                    $last_closing_row = mysqli_fetch_assoc($last_closing_q);
                    $opening_reading = $last_closing_row ? $last_closing_row['closing_reading'] : 0;

                    // Get latest fuel price
                    $product_id = $pump['product_assigned'];
                    $price_q = mysqli_query($con, "SELECT new_price FROM fuel_price_change WHERE p_id = $product_id AND status = 1 ORDER BY date_time DESC LIMIT 1");
                    $price_row = mysqli_fetch_assoc($price_q);
                    $sales_price = $price_row ? $price_row['new_price'] : 0;
                    $display = '⛽ ' . htmlspecialchars($pump['pump_name']) . " > Reading " . $opening_reading . " > " . htmlspecialchars($pump['p_name']) . " @ Rs. " . number_format($sales_price, 2);


                    // $display = htmlspecialchars($pump['pump_name']) . " > reading " . $opening_reading . " > " . htmlspecialchars($pump['p_name']) . " > Rs. " . number_format($sales_price,2);

                    echo "<option value='{$pump_id}' title='$display'>$display</option>";
                }
                ?>
              </select>
               
            </div>
          </div>

          <div class="form-group row">
            <label class="col-sm-3 col-form-label">Shift Start Time</label>
            <div class="col-sm-9">
              <!-- <input type="time" name="start_time" class="form-control" required value="<?= date('H:i') ?>"> -->
               <input type="time" name="start_time" id="startTime" class="form-control" required value="<?= date('H:i') ?>">
             
            </div>
          </div>

        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary processing">Open Shift</button>
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Close Shift Modal (reuse your previous one) -->

<div class="modal fade" id="closeShiftModal" tabindex="-1" role="dialog" aria-labelledby="closeShiftModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <form id="closeShiftForm" method="POST" autocomplete="off">
      <input type="hidden" name="close_shift" value="1">
      <input type="hidden" name="shift_id" id="closeShiftId">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="closeShiftModalLabel">Close Shift</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button>
        </div>
        <div class="modal-body">

          <div id="closeShiftPumpDetails">
            <!-- AJAX loaded pump closing inputs -->
          </div>

          <div class="form-group row mt-3">
            <label class="col-sm-3 col-form-label">Shift End Time</label>
            <div class="col-sm-9">
              <input type="time" name="end_time" id="shiftEndTime" class="form-control " required value="<?= date('H:i') ?>" style='width:150px;  border: 3px solid black !important;'>
            </div>
          </div>
      <hr>
      Colection 
         <div class="form-group row mt-3">
            <div class="col-sm-3">Total Card Sales</div>
            <div class="col-sm-3" id="cardSales">0.00</div>

            <div class="col-sm-3">Total Credit Sales</div>
            <div class="col-sm-3" id="creditSales">0.00</div>
          </div>

          <div class="form-group row mt-3">
            <div class="col-sm-3">Total Cash Collection</div>
            <div class="col-sm-3">
              <input type="number" name="Cash_collection" style='border:3px solid black !important; text-align:right; width : 180px;'  id="Cash_collection" class="form-control" placeholder='Cash Collection' required value="0.00" oninput="calculateExcessShort()">
              <input type="hidden" name="card_collection"   id="card_collection" class="form-control" placeholder='Cash Collection' required value="0.00" oninput="calculateExcessShort()">
              <input type="hidden" name="credit_collection"    id="credit_collection" class="form-control" placeholder='Cash Collection' required value="0.00" oninput="calculateExcessShort()">
            </div>
                
            <div class="col-sm-3">Excess / Short</div>
            <div class="col-sm-3" id="excessShort">0.00</div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-success">Close Shift</button>
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        </div>
      </div>
    </form>
  </div>
</div>
<script>
function updateTimeInput() {
    const now = new Date();
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    const timeString = `${hours}:${minutes}`;
    
    document.getElementById('startTime').value = timeString;
}

// Run once on load
updateTimeInput();

// Update every 30 seconds
setInterval(updateTimeInput, 30000);
</script>

<script>
$(function () {
    // View shift summary modal
    $('.view-shift-btn').click(function () {
        let shiftId = $(this).data('shift-id');
        $('#viewShiftDetails').html('<div class="text-center">Loading...</div>');
        $.ajax({
            url: 'ajax/ajax_get_shift_summary.php',
            type: 'POST',
            data: { shift_id: shiftId },
            success: function (data) {
                $('#viewShiftDetails').html(data);
            },
            error: function () {
                $('#viewShiftDetails').html('<div class="alert alert-danger">Failed to load shift summary.</div>');
            }
        });
    });

    // Load pump details for open shift modal (on pump select)
    $('#pumpsSelect').change(function () {
        let pumps = $(this).val() || [];
        let operatorId = $('select[name="operator_id"]').val();
        let startTime = $('input[name="start_time"]').val();

        if (!operatorId) {
            $('#pumpDetailsContainer').html('<div class="alert alert-warning">Please select an operator first.</div>');
            return;
        }

        if (pumps.length === 0) {
            $('#pumpDetailsContainer').html('<em>Select pumps to see meter readings and sales prices.</em>');
            return;
        }

        $.ajax({
            url: 'ajax/ajax_get_pump_shift_details.php',
            type: 'POST',
            data: { pumps: pumps, start_time: startTime },
            success: function (data) {
                $('#pumpDetailsContainer').html(data);
            },
            error: function () {
                $('#pumpDetailsContainer').html('<div class="alert alert-danger">Failed to load pump details.</div>');
            }
        });
    });

    // Open close shift modal & load pump details
    // When the close shift button is clicked
$('.close-shift-btn').click(function () {
    let shiftId = $(this).data('shift-id');
    $('#closeShiftId').val(shiftId);
    $('#closeShiftPumpDetails').html('<div class="text-center">Loading...</div>');
    $('#closeShiftModal').modal('show');

    // Fetch shift details (card sales, credit sales, and pump details)
    $.ajax({
        url: 'ajax/ajax_get_close_shift_details.php',
        type: 'POST',
        data: { shift_id: shiftId },
        success: function (data) {
            $('#closeShiftPumpDetails').html(data);

            // Fetch and display card sales and credit sales for this shift
            $.ajax({
                url: 'ajax/ajax_get_sales_details.php',
                type: 'POST',
                data: { shift_id: shiftId },
                success: function (salesData) {
                 
                    let sales = JSON.parse(salesData);
                    $('#cardSales').text(sales.card_sales.toFixed(2));
                               $('#card_collection').val(sales.card_sales);
                    $('#creditSales').text(sales.credit_sales.toFixed(2));
                             $('#credit_collection').val(sales.credit_sales);

                      calculateExcessShort();
                        // calculateTotals();
                        bindClosingInputEvents();
                    // Call to recalculate excess/short if cash collection is available
                    
                    
                }
            });
        },
        error: function () {
            $('#closeShiftPumpDetails').html('<div class="alert alert-danger">Failed to load shift details.</div>');
        }
    });
});

// Calculate Excess / Short based on total sales, card sales, credit sales, and cash collection



// function calculateExcessShort() {
//   alert();
//     let totalSales = parseFloat($('#totalSales').text() || '0.00');
//     let cardSales = parseFloat($('#cardSales').text() || '0.00');
//     let creditSales = parseFloat($('#creditSales').text() || '0.00');
//     let cashCollection = parseFloat($('#Cash_collection').val() || '0.00');

//     // Calculate Excess / Short
//     let excessShort = totalSales - cardSales - creditSales - cashCollection;
//     $('#excessShort').text(excessShort.toFixed(2));
// }

// On form submission, gather all the data and submit
 

    // $('.close-shift-btn').click(function () {
    //     let shiftId = $(this).data('shift-id');
    //     $('#closeShiftId').val(shiftId);
    //     $('#closeShiftPumpDetails').html('<div class="text-center">Loading...</div>');
    //     $('#closeShiftModal').modal('show');

    //     $.ajax({
    //         url: 'ajax/ajax_get_close_shift_details.php',
    //         type: 'POST',
    //         data: { shift_id: shiftId },
    //         success: function (data) {
    //             $('#closeShiftPumpDetails').html(data);
    //         },
    //         error: function () {
    //             $('#closeShiftPumpDetails').html('<div class="alert alert-danger">Failed to load shift details.</div>');
    //         }
    //     });
    // });
});

$(function () {
    // Check if operator already has open shift
    $('select[name="operator_id"]').change(function () {
        var operatorId = $(this).val();
        var locationId = <?= json_encode($location_id) ?>;

        if (!operatorId) {
            $('#operatorWarning').remove();
            $('button[type="submit"]').prop('disabled', false);
            return;
        }

        $.ajax({
            url: 'ajax/check_operator_open_shift.php',
            type: 'POST',
            dataType: 'json',
            data: { operator_id: operatorId, location_id: locationId },
            success: function (response) {
                $('#operatorWarning').remove();
                if (response.has_open_shift) {
                    // Show warning message
                    var warningHtml = '<div id="operatorWarning" class="alert alert-danger mt-2">This operator already has an open shift!  </div>';
                    $('select[name="operator_id"]').closest('.form-group').after(warningHtml);
                    // Disable submit button
                    $('#openShiftForm button[type="submit"]').prop('disabled', true);   // Desable if Multiple Shift need open for a person
                } else {
                    // Enable submit button
                    $('#openShiftForm button[type="submit"]').prop('disabled', false);  // Desable if Multiple Shift need open for a person
                }
            },
            error: function () {
                $('#operatorWarning').remove();
                $('#openShiftForm button[type="submit"]').prop('disabled', false);
            }
        });
    });
});


</script>

<script>
    $(document).ready(function() {
    $('#example').DataTable({
        "pageLength": 50
    });
});
    </script>


<script>
  document.getElementById('openShiftForm').addEventListener('submit', function(event) {
    const form = this;

    // Validate form
    if (!form.checkValidity()) {
      event.preventDefault();
      event.stopPropagation();
      form.classList.add('was-validated');
      return;
    }

    // Get the submit button by class
    const button = form.querySelector('button.processing');
    if (button) {
      button.disabled = true;
      button.innerHTML = 'Please wait <i class="fa fa-gear fa-spin" style="font-size:24px"></i>';
    }
 
  });
</script>


<?php include 'footer.php'; ?>


<script>
  document.addEventListener('DOMContentLoaded', function () {
  function calculateExcessShort() {
   
    let totalSales = parseFloat($('#total_sales_calculated').val() || '0.00');
    // alert(totalSales);
    let cardSales = parseFloat($('#cardSales').text() || '0.00');
    let creditSales = parseFloat($('#creditSales').text() || '0.00');
    let cashCollection = parseFloat($('#Cash_collection').val() || '0.00');

    let excessShort = (cardSales + creditSales + cashCollection)-totalSales;
    $('#excessShort').text(excessShort.toFixed(2));
  }

  // Make sure to expose it globally
  window.calculateExcessShort = calculateExcessShort;
});
</script>





 <script>
          function bindClosingInputEvents() {
  const closingInputs = document.querySelectorAll('.closing-reading-input');

  const totalSalesInput = document.getElementById('total_sales_calculated');
  const totalSalesInput1 = document.getElementById('total_sales_calculated1');
  
 

  function calculateTotals() {
    
  let totalSales = 0;

  closingInputs.forEach(input => {
    const tr = input.closest('tr');
    const opening = parseFloat(tr.dataset.opening);
    const testFuel = parseFloat(tr.dataset.testFuel);
    const salesPrice = parseFloat(tr.dataset.salesPrice);
    const closing = parseFloat(input.value) || 0;

    console.log('Row values:', {opening, testFuel, salesPrice, closing});

    const fuelSold = closing - opening - testFuel;
    const lineTotal = fuelSold * salesPrice;

    console.log('Calculated fuelSold:', fuelSold, 'lineTotal:', lineTotal);

const fuelSoldCell = tr.querySelector('.fuel-sold-cell');
if (fuelSoldCell) {
    const displayValue = fuelSold < 0 ? 0 : fuelSold;
    fuelSoldCell.textContent = displayValue.toFixed(3);
}

    const lineTotalCell = tr.querySelector('.line-total-cell');
if (lineTotalCell) {
    const displayValue = lineTotal < 0 ? 0 : lineTotal;
    lineTotalCell.textContent = displayValue.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}


    if (lineTotal > 0) totalSales += lineTotal;
  });


     


  if (totalSalesInput) {
    totalSalesInput.value = totalSales.toFixed(2);
     calculateExcessShort();
    console.log('Total Sales updated:', totalSales.toFixed(2));
  }
if (totalSalesInput1) {
  totalSalesInput1.value = totalSales.toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  });
}


//   const totalSalesCell = document.querySelector('table tbody tr:last-child td:last-child');
//   if (totalSalesCell) totalSalesCell.textContent = totalSales.toFixed(2);
// }

  if (salesTable) {
      const totalSalesCell = salesTable.querySelector('tbody tr:last-child td.line-total-cell');
      if (totalSalesCell) totalSalesCell.textContent = totalSales.toFixed(2);
    }
  }



  closingInputs.forEach(input => {
    input.addEventListener('input', calculateTotals);
  });

  // Calculate once on bind
 
  calculateTotals();
 
}

  
   
 

// Call this function after your AJAX content is fully loaded into #viewShiftDetails
function onModalContentLoaded() {
  bindClosingInputEvents();
}

// Example: If you load content by jQuery AJAX:
// $('#viewShiftDetails').load('your-ajax-url', function() {
//   onModalContentLoaded();
// });

// Or, if you manually inject content via fetch/XHR, call onModalContentLoaded() after insertion

// Also bind modal show event to trigger loading
document.getElementById('viewShiftModal').addEventListener('show.bs.modal', function() {
  // Load the content dynamically here, e.g.:
  // Using jQuery for example:
  $('#viewShiftDetails').load('your-ajax-url', function() {
    onModalContentLoaded();
  });
});


          </script>

