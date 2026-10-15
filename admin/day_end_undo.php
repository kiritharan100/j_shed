<?php
include 'header.php';

// Ensure $location_id is set in the header/session
$location_id = $location_id ?? 0;

// Default date range values
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d'); // today
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-31 days')); // 29 days ago (so total 30 days including today)

$query = "SELECT DATE_ADD(MAX(date_ended), INTERVAL 0 DAY) AS last_end_date 
          FROM day_end_process 
          WHERE status = 1 AND location_id = '$location_id'";
$result = mysqli_query($con, $query);
$row = mysqli_fetch_assoc($result);
$last_day_ended = $row['last_end_date'];
?>

<div class="content-wrapper">
    <div class="container-fluid">
        <div class="main-header">
            <h4>Day End Process</h4>
        </div>

  
  
      <div class="card">
            <div class="card-block">
                
            <!-- Date Range Filter -->
        <form method="get" class="form-inline mb-3">
            <label for="start_date">Start Date:&nbsp;</label>
            <input type="date" name="start_date" id="start_date" class="form-control mr-3" value="<?= htmlspecialchars($start_date) ?>" required>

            <label for="end_date">End Date:&nbsp;</label>
            <input type="date" name="end_date" id="end_date" class="form-control mr-3" value="<?= htmlspecialchars($end_date) ?>" required>

            <button type="submit" class="btn btn-primary mr-3">Filter</button>
             <button type='button' id="exportButton" filename='<?php echo "DAY_END_".$start_date."_".$end_date; ?>.xlsx' class="btn btn-primary"><i class="ti-cloud-down"></i> Export</button>

            <button type="button" class="btn btn-primary"  id="todayShiftsBtn" >Day End Process</button>
            <button type="button" 
        class="btn btn-success" 
        id="printRangeBtn"
        data-start="<?= $start_date ?>" 
        data-end="<?= $end_date ?>" data-location_id="<?= $location_id ?>">
           <i class="fa fa-print"></i> Print Report
        </button>

        <script>
         
$(document).on('click', '#printRangeBtn', function () {
    var start = $(this).data('start');
    var end   = $(this).data('end');
    var location_id   = $(this).data('location_id');
    window.open('day_end_print_range.php?start_date=' + encodeURIComponent(start) + '&end_date=' + encodeURIComponent(end) + '&location_id=' + encodeURIComponent(location_id), '_blank');
});

         </script>
        </form>
        <hr>

        <!-- Bootstrap Table -->
   <?php
 
$count = 1;
// Fetch all day end records (you may want to filter by location or date)
$sql = "    SELECT id, location_id, serial_no, date_ended, created_by, created_on, status, 
           total_cash_settled, total_card_settled, total_credit_settle, 
           fuel_purchase, oil_purchase, fuel_sales, oil_sales, 
           fuel_short_excess, oil_short_excess 
    FROM day_end_process
    WHERE date_ended BETWEEN '$start_date' AND '$end_date' and location_id='$location_id'
    ORDER BY date_ended DESC";

$result = mysqli_query($con, $sql);
?>

<div class="table-responsive">
    <table id='example' class="table table-bordered table-hover" style="font-size:13px;">
        <thead class="thead-light">
            <tr>
                <th>#</th>
                <th>No</th>
                <th>Date Ended</th>
                <th>Fuel Sales</th>
                <th>Oil Sales</th>
                <th>Cash Settled</th>
                <th>Card Settled</th>
                <th>Credit Settled</th>
                <th>Fuel Sh/Ex</th>
                <th>Oil Sh/Ex</th>
                <th>Fuel Purchase</th>
                <th>Oil Purchase</th>
                <th>Created On</th>
                <th>Reports</th>
                <th>Action</th>
                <th>Undo</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($result && mysqli_num_rows($result) > 0): ?>
            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <tr>
                    <td class="text-center"><?= htmlspecialchars($count) ?></td>
                    <td class="text-center"><?= htmlspecialchars($row['serial_no']) ?></td>
                    <td class="text-center"><?= htmlspecialchars($row['date_ended']); if($count ==1){ $pick_last_date = $row['date_ended']; } ?></td>
                    <td class="text-right"><?= number_format($row['fuel_sales'], 2) ?></td>
                    <td class="text-right"><?= number_format($row['oil_sales'], 2) ?></td>
                    <td class="text-right"><?= number_format($row['total_cash_settled'], 2) ?></td>
                    <td class="text-right"><?= number_format($row['total_card_settled'], 2) ?></td>
                    <td class="text-right"><?= number_format($row['total_credit_settle'], 2) ?></td>
                    <td class="text-right"><?= number_format($row['fuel_short_excess'], 2) ?></td>
                    <td class="text-right"><?= number_format($row['oil_short_excess'], 2) ?></td>
                    <td class="text-right"><?= number_format($row['fuel_purchase'], 2) ?></td>
                    <td class="text-right"><?= number_format($row['oil_purchase'], 2) ?></td>
                    <td class="text-center"><?= date('n-j g:ia', strtotime($row['created_on'])) ?></td>
                         <td>
                        <select class="form-control report-select" style='height:20px; width:100px;' data-day_no='<?= htmlspecialchars($row['serial_no']) ?>' data-date="<?= htmlspecialchars($row['date_ended']); ?>">
                        <option value="">Select</option>
                          <option value="cash_f9a">Cash F 9A</option>
                        <option value="sr">Sales Reconciliation</option>
                        <option value="sales_f15">Sales F15</option>
                        <option value="f21c">Sales F21C</option>
                        <option value="9c">Card Sales - 9C</option>
                        <option value="f9c">Credit Sales F9c</option>
                        <option value="f16b">Purchase F16B</option>
                        </select>
                        
                        
                    </td>

                    <td class="text-center">
<i class="fa fa-eye text-info view-dayend-btn" data-id="<?= $row['serial_no'] ?>" style="cursor: pointer;" title="View"></i> ||
  
  <a href='report_pump.php?day_end=<?= $row['serial_no'] ?>' title="View detail report"> 
<i class="fa fa-print"></i></a>
   
</td>

                    <td class="text-center">
                        <?php if ($last_day_ended == $row['date_ended']): // Only show undo for the last (first in DESC order) day end ?>
                            <i class="fa fa-undo text-warning undo-dayend-btn" 
                               data-serial="<?= $row['serial_no'] ?>" 
                               data-date="<?= $row['date_ended'] ?>"
                               data-location="<?= $location_id ?>"
                               style="cursor: pointer; font-size: 16px;" 
                               title="Undo Day End"></i>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php $count++; endwhile; ?>
        <?php else: ?>
            <tr><td colspan="15" class="text-center">No records found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>


<script>
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.report-select').forEach(function(select) {
    select.addEventListener('change', function() {
      var option = this.value;
      var date = this.dataset.date;
      var day_no = this.dataset.day_no;
      
         if (option === 'cash_f9a') {
        var url = 'cash_settlement_report.php?date=' + encodeURIComponent(date);
        window.open(url, '_blank');
      }

      if (option === 'sales_f15') {
        var url = 'report_sales_monthly_total.php?date=' + encodeURIComponent(date);
        window.open(url, '_blank');
      }
      if (option === 'f21c') {
        var url = 'report_sales_monthly.php?date=' + encodeURIComponent(date);
        window.open(url, '_blank');
      }
            if (option === '9c') {
        var url = 'report_credit_card.php?from_date=' + encodeURIComponent(date) + '&to_date=' + encodeURIComponent(date) ;
        window.open(url, '_blank');
      }
      
       if (option === 'f9c') {
        var url = 'report_credit_sales.php?from_date=' + encodeURIComponent(date) + '&to_date=' + encodeURIComponent(date) ;
        window.open(url, '_blank');
      }
             if (option === 'f16b') {
        var url = 'report_purchase_detail.php?from_date=' + encodeURIComponent(date) + '&to_date=' + encodeURIComponent(date) ;
        window.open(url, '_blank');
      }
      
             if (option === 'sr') {
        var url = 'report_pump.php?day_end=' + encodeURIComponent(day_no) ;
        window.open(url, '_blank');
      }
      // Reset back to default
      this.value = '';
    });
  });
});
</script>



    </div>
</div>
 

</div>
</div>

<style>
.undo-dayend-btn {
    transition: all 0.3s ease;
}

.undo-dayend-btn:hover {
    color: #e08e0b !important;
    transform: scale(1.2);
}

.swal-wide-content {
    text-align: left !important;
}

.swal-wide-content ul {
    padding-left: 20px;
}

.swal-wide-content li {
    margin-bottom: 5px;
}
</style>

<div class="modal fade" id="viewDayEndModal" tabindex="-1" role="dialog" aria-labelledby="viewDayEndLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
         <form id="AccSubmitForm" method="POST" autocomplete="off">
      <div class="modal-header">
        <h5 class="modal-title">Day End Details</h5>
        <!-- <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span>&times;</span>
        </button> -->
      </div>
      <div class="modal-body" id="viewDayEndContent">
        <p>Loading...</p>
      </div>
  
        <div class="modal-footer">
           <!-- <label style="display: flex; align-items: center; font-size: 1.3rem; font-weight: bold; color: green; cursor: pointer; ">
  <input type="checkbox" id='accounts_checkbox' style="width: 20px; height: 20px; margin-right: 10px;"  >
  I have verified all the transaction and ready to post to accouts
</label> -->
          <!-- <button type="submit" class="btn btn-primary processingxyz" id='record_accounts' disabled >Post To account</button> -->
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
        </div>
        </form>
    </div>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const checkbox = document.getElementById('accounts_checkbox');
    const submitBtn = document.getElementById('record_accounts');

    checkbox.addEventListener('change', function () {
      submitBtn.disabled = !this.checked;
    });
  });
</script>

 <script>
  document.getElementById('AccSubmitForm').addEventListener('submit', function(event) {
    const form = this;

    // Validate form
    if (!form.checkValidity()) {
      event.preventDefault();
      event.stopPropagation();
      form.classList.add('was-validated');
      return;
    }

    // Get the submit button by class
    const button = form.querySelector('button.processingxyz');
    if (button) {
      button.disabled = true;
      button.innerHTML = 'Please wait <i class="fa fa-gear fa-spin" style="font-size:24px"></i>';
    }
  });


  
</script>


<script>
document.getElementById('AccSubmitForm').addEventListener('submit', function (event) {
  event.preventDefault();
  const form = this;

  const submitBtn = document.getElementById('record_accounts');
  submitBtn.disabled = true;
  submitBtn.innerHTML = 'Posting... <i class="fa fa-gear fa-spin"></i>';

  const serialNo = document.getElementById('serial_no').value;

  $.ajax({
    url: 'ajax_day_end_undo/ajax_post_day_end.php',
    type: 'POST',
    data: { serial_no: serialNo },
    dataType: 'json',
    success: function (response) {
      if (response.success) {
        notify('success', 'Posted to accounts!');
        $('#viewDayEndModal').modal('hide');
        setTimeout(() => location.reload(), 1500);
      } else {
        notify('danger', 'Error', response.message || 'Failed to update.');
      }
    },
    error: function (xhr, status, error) {
      notify('danger', 'Error', 'AJAX failed: ' + error);
    },
    complete: function () {
      submitBtn.disabled = false;
      submitBtn.innerHTML = 'Post To account';
    }
  });
});
</script>





<div class="modal fade" id="todayShiftsModal" ...>
  <div class="modal-dialog modal-lg" ...>
    <div class="modal-content">
      <form id="addDayEndForm" method="POST" autocomplete="off">
        <div class="modal-header">
          <h5 class="modal-title" id="todayShiftsLabel">Day End Process</h5>
          <!-- form tag starts here -->
        </div>
        <div class="modal-body">
          <div class="col-md-4">
            <label for="end_date" class="form-label">Process Day End For</label>

            <?php
$next_date = isset($pick_last_date) && !empty($pick_last_date)
    ? date('Y-m-d', strtotime($pick_last_date . ' +1 day'))
    : date('Y-m-d', strtotime('-1 day'));
    
    
 
            $today = date('Y-m-d');
            $minDate = date('Y-m-d', strtotime('-3 days'));
            ?>
            <input type="date" name="end_date" class="form-control"
       value="<?= htmlspecialchars($next_date) ?>"
       min="<?= $next_date ?>"
       max="<?= $today ?>"
       style="width:150px;" required> <br>
          </div>

          <div id="todayShiftsContent">Loading...</div>
          <!-- Make sure the hidden inputs get injected inside the form, for example in #todayShiftsContent -->


          <label style="display: flex; align-items: center; font-size: 1.3rem; font-weight: bold; color: green; cursor: pointer; ">
  <input type="checkbox" id='day_end_checkbox' style="width: 20px; height: 20px; margin-right: 10px;"  >
  I confirm that all transactions are verified and  ready for day end
</label>
        </div>
       
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary processingx"  id="record_day_end_btn" disabled >Record Day End</button>
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const checkbox = document.getElementById('day_end_checkbox');
    const submitBtn = document.getElementById('record_day_end_btn');

    checkbox.addEventListener('change', function () {
      submitBtn.disabled = !this.checked;
    });
  });
</script>

 


 <script>
 
document.getElementById('todayShiftsBtn').addEventListener('click', function() {
    $('#todayShiftsModal').modal('show');
    $('#todayShiftsContent').html('Loading...');

   $.ajax({
    url: 'ajax_day_end_undo/ajax_get_today_shifts.php?location_id=<?php echo $location_id; ?>',
    type: 'POST',
    dataType: 'json',
    success: function(response) {
        if (response.success) {
           
       


            $('#todayShiftsContent').html(response.html);
             bindDayEndCashListener(); 
        } else {
            $('#todayShiftsContent').html('<p>Error loading data.</p>');
        }
    },
    error: function() {
        $('#todayShiftsContent').html('<p>Error connecting to server.</p>');
    }
});

});

function bindDayEndCashListener() {
    const totalCashInput = document.getElementById('total_cash');
    const dayEndCashInput = document.getElementById('day_end_cash');
    const dayEndShortInput = document.getElementById('day_end_short');

    if (!totalCashInput || !dayEndCashInput || !dayEndShortInput) return;

    function calculateShortExcess() {
        const systemCash = parseFloat(totalCashInput.value) || 0;
        const userCash = parseFloat(dayEndCashInput.value) || 0;
        const difference = (userCash - systemCash).toFixed(2);
        dayEndShortInput.value = difference;
    }

    // Remove any existing listener before adding (to prevent duplicates)
    dayEndCashInput.removeEventListener('input', calculateShortExcess);
    dayEndCashInput.addEventListener('input', calculateShortExcess);

    calculateShortExcess();  // initialize
}
    
$(document).ready(function() {
    $('#addDayEndForm').on('submit', function(e) {
        e.preventDefault();

        // Optional: disable submit button to prevent double submits
        $('.processing').prop('disabled', true).text('Processing...');

        // Collect form data, including hidden inputs
        var formData = $(this).serialize();

        $.ajax({
            url: 'ajax_day_end_undo/save_day_end.php?location_id=<?php echo $location_id; ?>', // Your PHP endpoint to handle save
            type: 'POST',
             data: { location_id: <?php echo $location_id; ?> }, 
            data: formData,
            dataType: 'json',
           success: function(response) {
    $('.processing').prop('disabled', false).text('Record Day End');
    if (response.success) {
        notify('success', 'Day end processed successfully!');
        setTimeout(function() {
            $('#todayShiftsModal').modal('hide');
            location.reload(); // refresh page after 2 seconds
        }, 2000);
    } else {
        alert('Error: ' + response.message);
    }
},
            error: function(xhr, status, error) {
                $('.processing').prop('disabled', false).text('Record Day End');
                alert('AJAX Error: ' + error);
            }
        });
    });
$.fn.dataTable.ext.errMode = 'none';
    $('#example').DataTable({
        "pageLength": 50
    });


});

 

$(document).on('click', '.view-dayend-btn', function () {
    var serialNo = $(this).data('id');
    $('#viewDayEndModal').modal('show');
    $('#viewDayEndContent').html('<p>Loading...</p>');

    $.ajax({
        url: 'ajax_day_end_undo/ajax_view_day_end.php',
        type: 'GET',
        data: { serial_no: serialNo },
        success: function (res) {
            $('#viewDayEndContent').html(res);
        },
        error: function () {
            $('#viewDayEndContent').html('<p class="text-danger">Error loading details.</p>');
        }
    });
});

// Undo Day End functionality
$(document).on('click', '.undo-dayend-btn', function () {
    var serialNo = $(this).data('serial');
    var date = $(this).data('date');
    var locationId = $(this).data('location');
    
    // First, get preview of what will be affected
    $.ajax({
        url: 'ajax_day_end_undo/ajax_undo_preview.php',
        type: 'GET',
        data: { 
            serial_no: serialNo, 
            location_id: locationId 
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showUndoConfirmation(response, serialNo, locationId);
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: response.message,
                    confirmButtonColor: '#3085d6'
                });
            }
        },
        error: function(xhr, status, error) {
            console.log('AJAX Error:', xhr.responseText);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to load undo preview. Error: ' + error,
                confirmButtonColor: '#3085d6'
            });
        }
    });
});

function showUndoConfirmation(data, serialNo, locationId) {
    var affectedTransactions = data.affected_transactions;
    var futureTransactions = data.future_transactions;
    var dayEndInfo = data.day_end_info;
    
    var affectedList = `
        <div style="text-align: left; margin: 15px 0;">
            <h5 style="color: #d33; margin-bottom: 10px;">⚠️ Transactions that will be reset to open Day:</h5>
            <ul style="margin-left: 20px;">
                <li><strong>Shifts:</strong> ${affectedTransactions.shifts} records</li>
                <li><strong>Pump Records:</strong> ${affectedTransactions.pump_records} records</li>
                <li><strong>Pump Assignments:</strong> ${affectedTransactions.pump_assign} records</li>
                <li><strong>Fuel Pump Tests:</strong> ${affectedTransactions.fuel_pump_test} records</li>
                <li><strong>Fuel Stock Ledger:</strong> ${affectedTransactions.fuel_stock_ledger} records</li>
                <li><strong>Card Sales:</strong> ${affectedTransactions.card_sales} records</li>
                <li><strong>Credit Sales:</strong> ${affectedTransactions.credit_sales} records</li>
                <li><strong>Fuel Purchase:</strong> ${affectedTransactions.fuel_purchase} records</li>
                <li><strong>Oil Purchase:</strong> ${affectedTransactions.oil_purchase} records</li>
                <li><strong>Oil Sales:</strong> ${affectedTransactions.oil_sales} records</li>
            </ul>
        </div>
    `;
    
    var futureWarning = '';
    var totalFuture = futureTransactions.shifts + futureTransactions.pump_assign + 
                     futureTransactions.fuel_pump_test + futureTransactions.fuel_stock_ledger +
                     futureTransactions.fuel_purchase + futureTransactions.oil_purchase + 
                     futureTransactions.oil_sales + futureTransactions.card_sales + 
                     futureTransactions.credit_sales;
    
    if (totalFuture > 0) {
        futureWarning = `
            <div style="text-align: left; margin: 15px 0; padding: 10px; background-color: #fff3cd; border: 1px solid #ffeaa7; border-radius: 5px;">
                <h5 style="color: #856404; margin-bottom: 10px;">⚠️ Future transactions found (will be DELETED):</h5>
                <ul style="margin-left: 20px; color: #856404;">
                    <li><strong>Future Shifts:</strong> ${futureTransactions.shifts} records</li>
                    <li><strong>Future Pump Assignments:</strong> ${futureTransactions.pump_assign} records</li>
                    <li><strong>Future Fuel Pump Tests:</strong> ${futureTransactions.fuel_pump_test} records</li>
                    <li><strong>Future Fuel Stock Ledger:</strong> ${futureTransactions.fuel_stock_ledger} records</li>
                    <li><strong>Future Fuel Purchase:</strong> ${futureTransactions.fuel_purchase} records</li>
                    <li><strong>Future Oil Purchase:</strong> ${futureTransactions.oil_purchase} records</li>
                    <li><strong>Future Oil Sales:</strong> ${futureTransactions.oil_sales} records (stock will be reversed)</li>
                    <li><strong>Future Card Sales:</strong> ${futureTransactions.card_sales} records</li>
                    <li><strong>Future Credit Sales:</strong> ${futureTransactions.credit_sales} records</li>
                </ul>
                <p style="margin-top: 10px; color: #721c24; font-weight: bold;"><strong>⚠️ WARNING: These transactions are dated after ${dayEndInfo.date_ended} and will be PERMANENTLY DELETED!</strong></p>
            </div>
        `;
    }
    
    Swal.fire({
        title: `Undo Day End #${dayEndInfo.serial_no}?`,
        html: `
            <div style="text-align: left;">
                <p><strong>Day End Date:</strong> ${dayEndInfo.date_ended}</p>
                <p><strong>Fuel Sales:</strong> ${parseFloat(dayEndInfo.fuel_sales).toLocaleString()}</p>
                <p><strong>Oil Sales:</strong> ${parseFloat(dayEndInfo.oil_sales).toLocaleString()}</p>
                <p><strong>Cash Settled:</strong> ${parseFloat(dayEndInfo.total_cash_settled).toLocaleString()}</p>
                <p><strong>Card Settled:</strong> ${parseFloat(dayEndInfo.total_card_settled).toLocaleString()}</p>
                <p><strong>Credit Settled:</strong> ${parseFloat(dayEndInfo.total_credit_settle).toLocaleString()}</p>
            </div>
             ${futureWarning}
             ${affectedList}
           
            <div style="background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; border-radius: 5px; margin-top: 15px;">
                <p style="color: #721c24; margin: 0;"><strong>Warning:</strong> This action cannot be undone. All the above transactions will be reset and available for future day end processing.</p>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, Undo Day End',
        cancelButtonText: 'Cancel',
        width: '600px',
        customClass: {
            content: 'swal-wide-content'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            // Proceed with undo
            performUndo(serialNo, locationId);
        }
    });
}

function performUndo(serialNo, locationId) {
    // Show loading
    Swal.fire({
        title: 'Processing...',
        text: 'Undoing day end process, please wait...',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    $.ajax({
        url: 'ajax_day_end_undo/ajax_undo_day_end.php',
        type: 'POST',
        data: { 
            serial_no: serialNo, 
            location_id: locationId 
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: response.message,
                    confirmButtonColor: '#28a745',
                    timer: 3000,
                    timerProgressBar: true
                }).then(() => {
                    // Refresh the page
                    location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: response.message,
                    confirmButtonColor: '#3085d6'
                });
            }
        },
        error: function() {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to undo day end. Please try again.',
                confirmButtonColor: '#3085d6'
            });
        }
    });
}

</script>

<?php include 'footer.php'; ?>


<script>
  document.getElementById('addDayEndForm').addEventListener('submit', function(event) {
    const form = this;

    // Validate form
    if (!form.checkValidity()) {
      event.preventDefault();
      event.stopPropagation();
      form.classList.add('was-validated');
      return;
    }

    // Get the submit button by class
    const button = form.querySelector('button.processingx');
    if (button) {
      button.disabled = true;
      button.innerHTML = 'Please wait <i class="fa fa-gear fa-spin" style="font-size:24px"></i>';
    }
 
  });
</script>


 