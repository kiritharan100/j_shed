<?php
include 'header.php';

$day_end_id = isset($_REQUEST['serial']) ? (int)$_REQUEST['serial'] : null;

$user_id = $user_id;  // from header/session


    $query = "SELECT DATE_ADD(MAX(date_ended), INTERVAL 1 DAY) AS next_day_end_date 
          FROM day_end_process 
          WHERE status = 1 AND location_id = '$location_id'";
$result = mysqli_query($con, $query);
$row = mysqli_fetch_assoc($result);

$next_day_end_date = $row['next_day_end_date'];
$today_date = date('Y-m-d');
 

if ($next_day_end_date == $today_date || empty($next_day_end_date)) {
    $day_end = "Today";
     
} else {
     $day_end = "Last_date";
     $day_end_date = $next_day_end_date . ' 23:59:59'; // End of the next day 2025-05-21 23:59:59
}

$use_js_update = false;
if ($day_end === "Today") {
    $use_js_update = true;
    $end_time_value = date('Y-m-d\TH:i'); // Initial value (updated by JS later)
} else {
    $day_end_date = $next_day_end_date . ' 23:59:59';
    $end_time_value = date('Y-m-d\T23:59:00', strtotime($day_end_date));
}


// Keep the list date filters within the same day-end window used by the
// Open New Shift start date.
$filter_max_date = date('Y-m-d');
$filter_min_date = !empty($next_day_end_date) ? $next_day_end_date : $filter_max_date;
if ($filter_min_date > $filter_max_date) {
    $filter_min_date = $filter_max_date;
}
$is_valid_date = static function ($value) {
    $date = DateTime::createFromFormat('!Y-m-d', (string)$value);
    return $date && $date->format('Y-m-d') === $value;
};
$start_date = (isset($_GET['start_date']) && $is_valid_date($_GET['start_date'])) ? $_GET['start_date'] : $filter_min_date;
$end_date = (isset($_GET['end_date']) && $is_valid_date($_GET['end_date'])) ? $_GET['end_date'] : $start_date;
$start_date = max($filter_min_date, min($start_date, $filter_max_date));
$end_date = max($filter_min_date, min($end_date, $filter_max_date));
if ($start_date > $end_date) {
    $end_date = $start_date;
}
$start_date_sql = mysqli_real_escape_string($con, $start_date);
$end_date_sql = mysqli_real_escape_string($con, $end_date);



// Open Shift (POST)
if (isset($_POST['open_shift'])) {
    $location_id = $location_id;
    $operator_id = $_POST['operator_id'];
    $start_time_input = $_POST['start_time']; // HH:mm
     
     
        $startDate = $_POST['startDate'];
    // $start_time = date('Y-m-d') . ' ' . $start_time_input . ':00';
    $start_time = $startDate . ' ' . $start_time_input . ':00';
    
    
    
    $created_by = $user_id;

   $check_shift_q = "
        SELECT shift_id  FROM shed_operator_shift 
        WHERE operator_id = '$operator_id' 
          AND open_status = 1 
          AND status = 1 
          AND location_id = '$location_id'
        LIMIT 1
    ";
    $check_shift_res = mysqli_query($con, $check_shift_q);

    if (mysqli_num_rows($check_shift_res) > 0) {
        echo "<script> notify('error', 'This operator already has an open shift. Please close it before opening a new one.'); </script>";
        exit;  
    }
    $get_last_shift_no_query = "
    SELECT MAX(shift_no) AS last_shift_no 
    FROM shed_operator_shift 
    WHERE location_id = $location_id
";
$result = mysqli_query($con, $get_last_shift_no_query);
$row = mysqli_fetch_assoc($result);
$shift_no = ($row['last_shift_no'] ?? 0) + 1;

    // Insert new shift
    $insert_shift = "INSERT INTO shed_operator_shift (location_id, shift_no,operator_id, start_time, open_status, created_by, created_on, status)
                     VALUES ('$location_id','$shift_no','$operator_id', '$start_time', 1, '$created_by', NOW(), 1)";
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
            // $opening_reading = $last_closing_row ? $last_closing_row['closing_reading'] : 0;


if ($last_closing_row && $last_closing_row['closing_reading'] > 0) {
    $opening_reading = $last_closing_row['closing_reading'];
} else {
    // Step 3: Fallback to opening_meater_reading from manage_pump
    $pump_q = mysqli_query($con, "SELECT opening_meater_reading FROM manage_pump WHERE id = $pump_id LIMIT 1");
    $pump_row = mysqli_fetch_assoc($pump_q);
    $opening_reading = $pump_row ? $pump_row['opening_meater_reading'] : 0;
}

            // Fuel price at start_time
             $price_q = mysqli_query($con, "SELECT new_price FROM fuel_price_change WHERE p_id = $product_id AND status = 1 AND date_time <= '$start_time' ORDER BY date_time DESC LIMIT 1");
            $price_row = mysqli_fetch_assoc($price_q);
            $sales_price = $price_row ? $price_row['new_price'] : 0;


            $opening_stock= mysqli_query($con, "SELECT (SUM(debit) - SUM(credit)) as stock_balance FROM `fuel_stock_ledger` WHERE `p_id` ='$product_id' and location_id='$location_id' and status =1");
            $opening_stock_row = mysqli_fetch_assoc($opening_stock);
            $opening_stock = $opening_stock_row ? $opening_stock_row['stock_balance'] : 0;
            // if($opening_stock < 0.001){
            //   $opening_stock = 0;
            // }
            // Insert pump assign
            $insert_pump_assign = "INSERT INTO shed_pump_assign (shift_id, pump_id, product_id, sales_price, opening_reading, open_stats, status ,opening_stock)
                                  VALUES ($shift_id, $pump_id, $product_id, $sales_price, $opening_reading, 1, 1,$opening_stock)";
            mysqli_query($con, $insert_pump_assign);
            // $result = mysqli_query($con, $insert_pump_assign) or die("Query failed: " . mysqli_error($con));

        }
    }
    echo "<script> notify('success', 'Shift opened successfully'); </script>";
}

 

// Fetch shifts in date range for this location

if(isset($_REQUEST['serial'])){
  $shifts_q = mysqli_query($con, "
    SELECT s.*, o.op_name
    FROM shed_operator_shift s
    LEFT JOIN manage_pump_operator o ON s.operator_id = o.op_id
    WHERE s.location_id = $location_id
      AND  s.day_end = $day_end_id
    ORDER BY s.start_time DESC
");

 }else {

 
 

$shifts_q = mysqli_query($con, "
    SELECT s.*, o.op_name
    FROM shed_operator_shift s
    LEFT JOIN manage_pump_operator o ON s.operator_id = o.op_id
    WHERE s.location_id = $location_id
      AND DATE(s.start_time) BETWEEN '$start_date_sql' AND '$end_date_sql'
    ORDER BY s.start_time DESC
");

 }

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

<!--<div class="content-wrapper" style='margin-top:0px;'>-->

<style>
.shift-page-wrapper {
    box-sizing: border-box;
    width: auto;
    min-width: 0;
    padding: 18px 0 30px;
}

.shift-page-wrapper .card-block {
    padding: 16px;
}

.shift-page-wrapper .table-responsive {
    display: block;
    width: 100%;
    max-width: 100%;
    overflow-x: auto;
}

.shift-page-wrapper #example {
    width: 100% !important;
    table-layout: fixed;
    white-space: nowrap;
    margin-bottom: 0;
}

.shift-page-wrapper #example th,
.shift-page-wrapper #example td {
    padding: 3px 5px;
    vertical-align: middle;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.15;
}

.shift-page-wrapper #example th {
    padding: 5px;
    vertical-align: middle;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: normal;
    line-height: 1.15;
}

.shift-page-wrapper #example td {
    font-size: 13px;
}

.shift-page-wrapper #example tbody tr {
    height: 34px !important;
}

.shift-page-wrapper #example tbody td {
    height: 34px !important;
    padding-top: 1px !important;
    padding-bottom: 1px !important;
}

.shift-page-wrapper #example tbody .shift-action-select {
    display: block;
    box-sizing: border-box;
    width: 100%;
    min-width: 0;
    height: 24px !important;
    min-height: 24px !important;
    padding: 1px 4px !important;
    font-size: 12px;
    line-height: 20px !important;
    background-color: #fff !important;
}
</style>
<div class="content-wrapper shift-page-wrapper">
    <div class="main-header">

        <h4>Manage Pump Operator Shifts <?php if(isset($_REQUEST['serial'])){ echo $_REQUEST['date'];} ?></h4>



        <div class="card">
            <div class="card-block">
                <div class="container-fluid">

                    <form method="get" class="form-inline mb-3">
                        <?php if(isset($_REQUEST['serial'])){ echo " <a href='manage_operator_shift.php'> <button type='button' class='btn btn-success'>Show All Shift</button></a>";} else { ?>
                        <label for="start_date" style="margin-right:4px;">Start Date:</label>
                        <input type="date" name="start_date" id="start_date" class="form-control"
                            style="width:145px; margin-right:8px;" min="<?= htmlspecialchars($filter_min_date) ?>"
                            max="<?= htmlspecialchars($filter_max_date) ?>" value="<?= htmlspecialchars($start_date) ?>"
                            required>
                        <label for="end_date" style="margin-right:4px;">End Date:</label>
                        <input type="date" name="end_date" id="end_date" class="form-control"
                            style="width:145px; margin-right:8px;" min="<?= htmlspecialchars($filter_min_date) ?>"
                            max="<?= htmlspecialchars($filter_max_date) ?>" value="<?= htmlspecialchars($end_date) ?>"
                            required>
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter"></i> Filter</button>
                        <button class="btn btn-primary mt-3 mb-5" data-toggle="modal" data-target="#openShiftModal"
                            type='button'> <i class="fa-solid fa-folder-open"></i> Open New Shift</button>
                    </form><?php } ?>
                </div>
                <script>
                (function() {
                    var startDate = document.getElementById('start_date');
                    var endDate = document.getElementById('end_date');
                    if (!startDate || !endDate) return;

                    function syncDateLimits() {
                        startDate.max = endDate.value || startDate.getAttribute('max');
                        endDate.min = startDate.value || endDate.getAttribute('min');
                    }
                    startDate.addEventListener('change', syncDateLimits);
                    endDate.addEventListener('change', syncDateLimits);
                    syncDateLimits();
                })();
                </script>

                <hr>

                <div class="table-responsive">
                    <table id='example' class="table table-bordered table-hover">
                        <colgroup>
                            <col style="width:3%">
                            <col style="width:5%">
                            <col style="width:12%">
                            <col style="width:7%">
                            <col style="width:7%">
                            <col style="width:6%">
                            <col style="width:6%">
                            <col style="width:6%">
                            <col style="width:6%">
                            <col style="width:7%">
                            <col style="width:4%">
                            <col style="width:10%">
                            <col style="width:11%">
                        </colgroup>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Shift #</th>
                                <th>Operator</th>
                                <th>Start Time</th>
                                <th>End Time</th>
                                <th>Sales</th>
                                <th>Cash</th>
                                <th>Card</th>
                                <th>Credit</th>
                                <th>Excess/Short</th>
                                <th>Day</th>
                                <th>Status</th>

                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
            $total_sales = 0;
  $total_cash = 0;
  $total_card = 0;
  $total_credit = 0;
  $total_excess_short = 0;

$status_label = 0;
          $count = 1;
          while ($shift = mysqli_fetch_assoc($shifts_q)) {
           

              // if($shift['open_status'] == 1){$end_time = "";} else {$end_time= }
              // $row_color = $shift['open_status'] == 1 ? 'style="background-color:#f8d7da;"' : 'style="background-color:#d4edda;"';
              if ($shift['open_status'] == 1 && $shift['day_end'] == 0) {
    $row_color = 'style="background-color:#5cee3b;"'; // yellow-ish for open shift
     $status_label = 'Open';
} elseif ($shift['open_status'] == 0 && $shift['day_end'] == 0) {
    $row_color = 'style="background-color:#f79760;"'; // green-ish for closed shift
    $status_label = 'Pending Day end';
}  else {
    $row_color = ''; // default, no color
     $status_label = 'Closed';
}
               

                  $total_sales += $shift['total_sales'];
      $total_cash += $shift['cash_received'];
      $total_card += $shift['total_card_sales'];
      $total_credit += $shift['total_credit_sales'];
      $total_excess_short += $shift['exces_short'];

              echo "<tr $row_color>";
              echo "<td>{$count}</td>";
              echo "<td align='center'>" . htmlspecialchars($shift['shift_no']) . "</td>";
              echo "<td>" . htmlspecialchars($shift['op_name']) . "</td>";
              echo "<td style='white-space:nowrap;'>" . date('d/m H:i', strtotime($shift['start_time'])) . "</td>";
              echo "<td style='white-space:nowrap;'>" . ($shift['open_status'] == 0 ? date('d/m H:i', strtotime($shift['end_time'])) : 'Still Open') . "</td>";
              echo "<td align='right'>" . number_format($shift['total_sales'],2) . "</td>";
              echo "<td align='right'>" . number_format($shift['cash_received'],2) . "</td>";
              echo "<td align='right'>" . number_format($shift['total_card_sales'],2) . "</td>";
              echo "<td align='right'>" . number_format($shift['total_credit_sales'],2) . "</td>";
              echo "<td align='right'>" . number_format($shift['exces_short'],2) . "</td>";
              echo "<td align='center'>" . $shift['day_end'] . "</td>";
              echo "<td>$status_label </td>";
              echo "<td align='center'>";
              echo "<select class='form-control shift-action-select' aria-label='Shift actions'><option value=''>Select action</option><option value='view'>View</option>";
              if ($shift['open_status'] == 1) {
                  echo "<option value='close'>Close Shift</option>";
              }

              if ($shift['open_status'] == 0 && $shift['day_end'] == 0) {
                    echo "<option value='undo'>Undo Close</option>";
                }
              echo "</select>";
              echo "<button type='button' style='display:none' class='view-shift-btn' data-shift-id='{$shift['shift_id']}' data-toggle='modal' data-target='#viewShiftModal'>View</button>";
              if ($shift['open_status'] == 1) {
                  echo "<button type='button' style='display:none' class='close-shift-btn' data-start-time='" . htmlspecialchars($shift['start_time'], ENT_QUOTES) . "' data-shift-id='{$shift['shift_id']}'>Close Shift</button>";
              }
              if ($shift['open_status'] == 0 && $shift['day_end'] == 0) {
                  echo "<button type='button' style='display:none' class='undo-shift-btn' data-shift-id='{$shift['shift_id']}'>Undo</button>";
              }
              echo "</td>";
              echo "</tr>";
              $count++;
          }

            echo "<tr style='font-weight: bold; background: #e2e3e5;'>";
            echo "<td >100</td>";  echo "<td ></td>";  
            echo "<td ></td>";  
            echo "<td ></td>";  
  echo "<td   align='right'>Total</td>";
  echo "<td align='right'>" . number_format($total_sales, 2) . "</td>";
  echo "<td align='right'>" . number_format($total_cash, 2) . "</td>";
  echo "<td align='right'>" . number_format($total_card, 2) . "</td>";
  echo "<td align='right'>" . number_format($total_credit, 2) . "</td>";
  echo "<td align='right'>" . number_format($total_excess_short, 2) . "</td>";
  echo "<td> </td>";
  echo "<td ></td>";
  echo "<td ></td>";
  echo "</tr>";


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
        <div class="modal fade" id="viewShiftModal" tabindex="-1" role="dialog" aria-labelledby="viewShiftModalLabel"
            aria-hidden="true">
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
        <div class="modal fade" id="openShiftModal" tabindex="-1" role="dialog" aria-labelledby="openShiftModalLabel"
            aria-hidden="true">
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
                                <label class="col-sm-3 col-form-label">Assign Pumps <br> Hold <button type='button'>
                                        Ctrl </button> in the keyboard to select multiple pumps</label>

                                <div class="col-sm-9" style='padding-top:5px;'>
                                    <select multiple name="pumps[]" class="form-control" id="pumpsSelect" required
                                        style="height:300px; font-size: 20px;  overflow-y: auto;">
                                        <?php
                while ($pump = mysqli_fetch_assoc($available_pumps_q)) {
                    // Get last closing reading


                    $pump_id = $pump['id'];

// Step 1: Try to get the last closing reading
$last_closing_q = mysqli_query($con, "SELECT closing_reading FROM shed_pump_assign WHERE pump_id = $pump_id AND status = 1 ORDER BY id DESC LIMIT 1");
$last_closing_row = mysqli_fetch_assoc($last_closing_q);

// Step 2: Check if valid closing reading exists
if ($last_closing_row && $last_closing_row['closing_reading'] > 0) {
    $opening_reading = $last_closing_row['closing_reading'];
} else {
    // Step 3: Fallback to manage_pump.opening_meater_reading
    $pump_opening_q = mysqli_query($con, "SELECT opening_meater_reading FROM manage_pump WHERE id = $pump_id LIMIT 1");
    $pump_opening_row = mysqli_fetch_assoc($pump_opening_q);
    $opening_reading = $pump_opening_row ? $pump_opening_row['opening_meater_reading'] : 0;
}

// Get latest fuel price
$product_id = $pump['product_assigned'];

            $opening_stock= mysqli_query($con, "SELECT (SUM(debit) - SUM(credit)) as stock_balance FROM `fuel_stock_ledger` WHERE `p_id` ='$product_id' and location_id='$location_id' and status =1");
            $opening_stock_row = mysqli_fetch_assoc($opening_stock);
            $opening_stock = $opening_stock_row ? $opening_stock_row['stock_balance'] : 0;


$price_q = mysqli_query($con, "SELECT new_price FROM fuel_price_change WHERE p_id = $product_id AND date_time <= '$today' AND  status = 1 ORDER BY date_time DESC LIMIT 1");
$price_row = mysqli_fetch_assoc($price_q);
$sales_price = $price_row ? $price_row['new_price'] : 0;

$disable='';
if($opening_stock > 0){ $disable=' '; } else { $disable='Disabled'; }
// Build display string
$display = '⛽ ' . htmlspecialchars($pump['pump_name']) . 
           " > Reading " . $opening_reading . 
           " > " . htmlspecialchars($pump['p_name']) . 
           " @ Rs. " . number_format($sales_price, 2).
           " > Stock  " . $opening_stock;

// Output option
echo "<option value='{$pump_id}' $disable  title='$display'>$display</option>"; 




                    // $pump_id = $pump['id'];
                    // $last_closing_q = mysqli_query($con, "SELECT closing_reading FROM shed_pump_assign WHERE pump_id = $pump_id AND status = 1 ORDER BY id DESC LIMIT 1");
                    // $last_closing_row = mysqli_fetch_assoc($last_closing_q);
                    // $opening_reading = $last_closing_row ? $last_closing_row['closing_reading'] : 0;

                    // // Get latest fuel price
                    // $product_id = $pump['product_assigned'];
                    // $price_q = mysqli_query($con, "SELECT new_price FROM fuel_price_change WHERE p_id = $product_id AND status = 1 ORDER BY date_time DESC LIMIT 1");
                    // $price_row = mysqli_fetch_assoc($price_q);
                    // $sales_price = $price_row ? $price_row['new_price'] : 0;
                    // $display = '⛽ ' . htmlspecialchars($pump['pump_name']) . " > Reading " . $opening_reading . " > " . htmlspecialchars($pump['p_name']) . " @ Rs. " . number_format($sales_price, 2);


                    // // $display = htmlspecialchars($pump['pump_name']) . " > reading " . $opening_reading . " > " . htmlspecialchars($pump['p_name']) . " > Rs. " . number_format($sales_price,2);

                    // echo "<option value='{$pump_id}' title='$display'>$display</option>";
                }
                ?>
                                    </select>

                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-sm-3 col-form-label">Shift Start Time</label>
                                <!--<div class="col-sm-9">-->

                                <!--   <input type="time" name="start_time" id="startTime" class="form-control" required value="<?= date('H:i') ?>">-->

                                <!--</div>-->
                                <div class="col-sm-6 d-flex gap-2 align-items-center" style='padding-top:5px;'>
                                    <input type="date" min='<?= $next_day_end_date?>' max='<?= date('Y-m-d') ?>'
                                        name="startDate" id="startDate" class="form-control" required
                                        value='<?= $next_day_end_date ?>'>
                                    <input type="time" name="start_time" id="startTime" class="form-control" required
                                        value="<?= date('H:i') ?>">
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

        <div class="modal fade" id="closeShiftModal" tabindex="-1" role="dialog" aria-labelledby="closeShiftModalLabel"
            aria-hidden="true">
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
                                    <input type="datetime-local" name="end_time" id="shiftEndTime" class="form-control"
                                        required value="<?= $end_time_value ?>"
                                        style="width: 220px; border: 3px solid black !important;">
                                </div>
                            </div>
                            <hr>

                            <div class="form-group row mt-3">
                                <div class="col-sm-3" align='Right' style="font-size: 1.5rem; font-weight: bold;">Card
                                    Sales</div>
                                <div class="col-sm-3" id="cardSales" style="font-size: 1.5rem; font-weight: bold;">0.00
                                </div>

                                <div class="col-sm-3" align='Right' style="font-size: 1.5rem; font-weight: bold;">Order
                                    Sales</div>
                                <div class="col-sm-3" id="creditSales" style="font-size: 1.5rem; font-weight: bold;">
                                    0.00</div>
                            </div>

                            <div class="form-group row mt-3">
                                <div class="col-sm-3" align='Right' style="font-size: 1.5rem; font-weight: bold;">Total
                                    Cash Collection</div>
                                <div class="col-sm-3">
                                    <input type="number" name="Cash_collection" step='0.01'
                                        style='border:3px solid black !important; text-align:right; width : 180px;'
                                        id="Cash_collection" class="form-control" placeholder='Cash' required value=""
                                        oninput="calculateExcessShort()">
                                    <input type="hidden" name="card_collection" id="card_collection"
                                        class="form-control" placeholder='Cash Collection' required value="0.00"
                                        oninput="calculateExcessShort()">
                                    <input type="hidden" name="credit_collection" id="credit_collection"
                                        class="form-control" placeholder='Cash Collection' required value="0.00"
                                        oninput="calculateExcessShort()">
                                </div>

                                <div class="col-sm-3" align='Right' style="font-size: 1.5rem; font-weight: bold;">Excess
                                    / Short</div>

                                <div class="col-sm-3 font-weight-bold text-danger" id="excessShort"
                                    style="font-size: 1.5rem; font-weight: bold; cursor:pointer;">0.00</div>

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
        <?php if ($use_js_update): ?>

        function updateTimeInput() {
            const now = new Date();

            const year = now.getFullYear();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const day = String(now.getDate()).padStart(2, '0');
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');

            const dateTimeString = `${year}-${month}-${day}T${hours}:${minutes}`;
            const timeString = `${hours}:${minutes}`;

            const startInput = document.getElementById('startTime');
            const endInput = document.getElementById('shiftEndTime');

            if (startInput) startInput.value = timeString; // Only time
            if (endInput) endInput.value = dateTimeString; // Full datetime
        }

        $(document).ready(function() {
            updateTimeInput();
            setInterval(updateTimeInput, 330000);
        });

        <?php endif; ?>



        // function updateTimeInput() {
        //     const now = new Date();
        //     const hours = String(now.getHours()).padStart(2, '0');
        //     const minutes = String(now.getMinutes()).padStart(2, '0');
        //     const timeString = `${hours}:${minutes}`;

        //     document.getElementById('startTime').value = timeString;
        //     document.getElementById('shiftEndTime').value = timeString;
        // }

        // // Run once on load
        // updateTimeInput();

        // // Update every 30 seconds
        // setInterval(updateTimeInput, 30000);
        </script>

        <script>
        $(function() {
            // View shift summary modal
            $('.view-shift-btn').click(function() {
                let shiftId = $(this).data('shift-id');
                $('#viewShiftDetails').html('<div class="text-center">Loading...</div>');
                $.ajax({
                    url: 'ajax/ajax_get_shift_summary.php',
                    type: 'POST',
                    data: {
                        shift_id: shiftId
                    },
                    success: function(data) {
                        $('#viewShiftDetails').html(data);
                    },
                    error: function() {
                        $('#viewShiftDetails').html(
                            '<div class="alert alert-danger">Failed to load shift summary.</div>'
                        );
                    }
                });
            });

            $(document).ready(function() {
                $('#excessShort').on('click', function() {
                    let excessShort = parseFloat($(this).text() || '0');
                    if (!isNaN(excessShort) && excessShort !== 0) {
                        $('#Cash_collection').val((excessShort * -1).toFixed(2)).trigger(
                            'input');
                    }
                });
            });


            $(document).on('click', '.undo-shift-btn', function() {
                let shiftId = $(this).data('shift-id');
                Swal.fire({
                    title: 'Do you want to undo the Closed shift?',
                    text: 'This will revert the shift to open status. Are you sure?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, Undo',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: 'ajax/undo_shift.php',
                            type: 'POST',
                            dataType: 'json',
                            data: {
                                shift_id: shiftId
                            },
                            success: function(response) {
                                if (response.status === 'success') {
                                    notify('success', response.message ||
                                        'Shift undone successfully');
                                    setTimeout(function() {
                                        window.location.reload();
                                    }, 2000);
                                } else {
                                    notify('danger', response.message ||
                                        'Failed to undo shift');
                                }
                            },
                            error: function() {
                                notify('danger',
                                    'Server error: Could not undo shift');
                            }
                        });
                    }
                });
            });











            // Load pump details for open shift modal (on pump select)
            $('#pumpsSelect').change(function() {
                let pumps = $(this).val() || [];
                let operatorId = $('select[name="operator_id"]').val();
                let startTime = $('input[name="start_time"]').val();

                if (!operatorId) {
                    $('#pumpDetailsContainer').html(
                        '<div class="alert alert-warning">Please select an operator first.</div>');
                    return;
                }

                if (pumps.length === 0) {
                    $('#pumpDetailsContainer').html(
                        '<em>Select pumps to see meter readings and sales prices.</em>');
                    return;
                }

                $.ajax({
                    url: 'ajax/ajax_get_pump_shift_details.php',
                    type: 'POST',
                    data: {
                        pumps: pumps,
                        start_time: startTime
                    },
                    success: function(data) {
                        $('#pumpDetailsContainer').html(data);
                    },
                    error: function() {
                        $('#pumpDetailsContainer').html(
                            '<div class="alert alert-danger">Failed to load pump details.</div>'
                        );
                    }
                });
            });

            // Open close shift modal & load pump details
            // When the close shift button is clicked
            $('.close-shift-btn').click(function() {
                let shiftId = $(this).data('shift-id');
                let startTime = $(this).data('start-time');
                $('#closeShiftId').val(shiftId);

                $('#closeShiftModal').attr('data-start-time', startTime);

                const [date, time] = startTime.split(' ');
                const minVal = `${date}T${time.slice(0,5)}`; // keep only HH:MM
                const maxVal = `${date}T23:59`;

                const shiftEndInput = document.getElementById('shiftEndTime');
                shiftEndInput.min = minVal;
                shiftEndInput.max = maxVal;




                $('#closeShiftPumpDetails').html('<div class="text-center">Loading...</div>');
                $('#closeShiftModal').modal('show');

                // Fetch shift details (card sales, credit sales, and pump details)
                $.ajax({
                    url: 'ajax/ajax_get_close_shift_details.php',
                    type: 'POST',
                    data: {
                        shift_id: shiftId
                    },
                    success: function(data) {
                        $('#closeShiftPumpDetails').html(data);

                        // Fetch and display card sales and credit sales for this shift
                        $.ajax({
                            url: 'ajax/ajax_get_sales_details.php',
                            type: 'POST',
                            data: {
                                shift_id: shiftId
                            },
                            success: function(salesData) {

                                let sales = JSON.parse(salesData);
                                $('#cardSales').text(sales.card_sales.toFixed(
                                    2));
                                $('#card_collection').val(sales.card_sales);
                                $('#creditSales').text(sales.credit_sales
                                    .toFixed(2));
                                $('#credit_collection').val(sales.credit_sales);

                                calculateExcessShort();
                                // calculateTotals();
                                bindClosingInputEvents();
                                // Call to recalculate excess/short if cash collection is available


                            }
                        });
                    },
                    error: function() {
                        $('#closeShiftPumpDetails').html(
                            '<div class="alert alert-danger">Failed to load shift details.</div>'
                        );
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
            $('#closeShiftForm').submit(function(e) {
                e.preventDefault();

                let shiftId = $('#closeShiftId').val();
                let endTime = $('#shiftEndTime').val();
                let total_sales_calculated = $('#total_sales_calculated').val();
                let cashCollection = parseFloat($('#Cash_collection').val() || '0.00');
                let card_collection = parseFloat($('#card_collection').val() || '0.00');
                let credit_collection = parseFloat($('#credit_collection').val() || '0.00');
                let excessShort = parseFloat($('#excessShort').text() || '0.00');
                let pumpIds = [];
                let closingReadings = [];
                let locationId = <?php echo intval($location_id); ?>;

                // Gather pump details
                $('#closeShiftPumpDetails input[name="pump_ids[]"]').each(function() {
                    pumpIds.push($(this).val());
                    closingReadings.push($(this).closest('tr').find(
                        'input[name="closing_readings[]"]').val());
                });

                function sendAjaxRequest() {
                    $.ajax({
                        url: 'ajax/ajax_close_shift_update.php',
                        type: 'POST',
                        data: {
                            close_shift: 1,
                            shift_id: shiftId,
                            end_time: endTime,
                            cash_collection: cashCollection,
                            card_collection: card_collection,
                            credit_collection: credit_collection,
                            total_sales_calculated: total_sales_calculated,
                            excess_short: excessShort,
                            pump_ids: pumpIds,
                            closing_readings: closingReadings,
                            location_id: locationId
                        },

                        success: function(response) {
                            if (response.status === 'success' || response.status ===
                                'already_closed') {
                                $('#closeShiftModal').modal('hide');
                                notify('success', response.status === 'already_closed' ?
                                    'Shift was already closed' :
                                    'Shift closed successfully');
                                setTimeout(function() {
                                    window.location.href =
                                        'manage_operator_shift.php';
                                }, 1000);
                            } else {
                                // ✅ This should trigger if PHP returned status: 'error'
                                notify('danger', 'Shift not closed', response.message ||
                                    'An unknown error occurred.');
                            }
                        },
                        error: function() {
                            alert('Failed to close the shift due to a server error.');
                        }


                        // success: function (response) {
                        //     $('#closeShiftModal').modal('hide');
                        //     notify('success', 'Shift closed successfully');
                        //     setTimeout(function () {
                        //         window.location.href = 'manage_operator_shift.php';
                        //     }, 1000);
                        // },
                        // error: function () {
                        //     alert('Failed to close the shift');
                        // }
                    });
                }

                // Check for excess or short
                if (Math.abs(excessShort) > 0) {
                    Swal.fire({
                        title: (excessShort >= 0 ? 'Excess' : 'Short') +
                            ` of Rs. ${Math.abs(excessShort).toFixed(2)}`,
                        text: 'Do you want to close the shift?',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Yes, close it!',
                        cancelButtonText: 'Cancel'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            sendAjaxRequest();
                        }
                    });
                } else {
                    sendAjaxRequest();
                }
            });


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

        $(function() {
            // Check if operator already has open shift
            $('select[name="operator_id"]').change(function() {
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
                    data: {
                        operator_id: operatorId,
                        location_id: locationId
                    },
                    success: function(response) {
                        $('#operatorWarning').remove();
                        if (response.has_open_shift) {
                            // Show warning message
                            var warningHtml =
                                '<div id="operatorWarning" class="alert alert-danger mt-2">This operator already has an open shift!  </div>';
                            $('select[name="operator_id"]').closest('.form-group').after(
                                warningHtml);
                            // Disable submit button
                            $('#openShiftForm button[type="submit"]').prop('disabled',
                                true); // Desable if Multiple Shift need open for a person
                        } else {
                            // Enable submit button
                            $('#openShiftForm button[type="submit"]').prop('disabled',
                                false); // Desable if Multiple Shift need open for a person
                        }
                    },
                    error: function() {
                        $('#operatorWarning').remove();
                        $('#openShiftForm button[type="submit"]').prop('disabled', false);
                    }
                });
            });
        });
        </script>

        <script>
        $(document).ready(function() {
            $(document).on('change', '.shift-action-select', function() {
                const action = this.value;
                this.value = '';
                if (!action) return;
                const button = $(this).closest('tr').find('.' + action + '-shift-btn, .view-shift-btn')
                    .filter(function() {
                        return action === 'view' ? $(this).hasClass('view-shift-btn') : $(this)
                            .hasClass(action + '-shift-btn');
                    }).first();
                if (button.length) button.trigger('click');
            });
            $('#example').DataTable({
                "pageLength": 50,
                "autoWidth": false
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
        document.addEventListener('DOMContentLoaded', function() {
            function calculateExcessShort() {

                let totalSales = parseFloat($('#total_sales_calculated').val() || '0.00');
                // alert(totalSales);
                let cardSales = parseFloat($('#cardSales').text() || '0.00');
                let creditSales = parseFloat($('#creditSales').text() || '0.00');
                let cashCollection = parseFloat($('#Cash_collection').val() || '0.00');

                let excessShort = (cardSales + creditSales + cashCollection) - totalSales;
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

                    console.log('Row values:', {
                        opening,
                        testFuel,
                        salesPrice,
                        closing
                    });

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
