<?php 
include 'header.php'; 

// Handle Add New Fuel Pump Test
if (isset($_POST['add_fuel_test'])) {
    $pump_assign_id = $_POST['pump_assign_id'];
    $tested_fuel = $_POST['tested_fuel'];
    $user_id = $user_id;  // from header or session
    $location_id = $location_id; // from header or session
    $date_time = date('Y-m-d H:i:s');
    $status = 1;

    // Insert new fuel test record
    $insert = "INSERT INTO shed_fuel_pump_test (pump_assign_id, location_id, tested_fuel, date_time, usaer_id, status)
               VALUES ('$pump_assign_id', '$location_id', '$tested_fuel', '$date_time', '$user_id', '$status')";
    mysqli_query($con, $insert);

    // Optional: log user action
    $detail = "Fuel pump test recorded for pump_assign_id $pump_assign_id with tested fuel $tested_fuel";
    UserLog('2', 'Fuel Pump Test Added', $detail);

    echo "<script>notify('success', 'Fuel pump test recorded successfully');</script>";
}

// Handle Cancel Fuel Test Request (AJAX)
if (isset($_POST['cancel_test_id'])) {
    $test_id = $_POST['cancel_test_id'];

    // Check if the pump_assign is open (spa.open_stats = 1)
    $check_query = "SELECT spa.open_stats 
                    FROM shed_fuel_pump_test ft
                    JOIN shed_pump_assign spa ON ft.pump_assign_id = spa.id
                    WHERE ft.test_id = '$test_id' LIMIT 1";
    $check_result = mysqli_query($con, $check_query);
    $row = mysqli_fetch_assoc($check_result);


    header('Content-Type: application/json'); // add this here

    
    if ($row && $row['open_stats'] == 1) {
        // Allow cancellation by setting status = 0
        $cancel_update = "UPDATE shed_fuel_pump_test SET status = 0 WHERE test_id = '$test_id'";
        mysqli_query($con, $cancel_update);



        $detail = "Fuel pump test $test_id cancelled";
        UserLog('2', 'Fuel Pump Test Cancelled', $detail);

      
    } else {
        // Pump is not open, cannot cancel
        echo json_encode(['success' => false, 'message' => 'Cannot cancel test because pump is closed.']);
    }
    exit; // important to stop further output
}



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


$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : $date_pick;
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : $date_pick;


function validateDate($date, $format = 'Y-m-d')
{
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

if (!validateDate($from_date)) {
    $from_date = date('Y-m-d');
}
if (!validateDate($to_date)) {
    $to_date = date('Y-m-d');
}
?>

<div class="content-wrapper">
  <div class="container-fluid">
    <div class="main-header">
      <h4>Fuel Pump Test Records</h4>
    </div>

    <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
        
        
        <!-- Filter form -->
        <form class="form-inline mb-2" method="GET" style="gap:10px;">
          <label for="from_date">From:</label>
          <input type="date" id="from_date" name="from_date" class="form-control" value="<?php echo htmlspecialchars($from_date); ?>" required>
          
          <label for="to_date">To:</label>
          <input type="date" id="to_date" name="to_date" class="form-control" value="<?php echo htmlspecialchars($to_date); ?>" required>
          
          <button type="submit" class="btn btn-info">Filter</button>
          &nbsp;  <a href="<?php echo strtok($_SERVER["REQUEST_URI"], '?'); ?>" class="btn btn-secondary">Reset</a>
        </form>
          &nbsp; &nbsp; <button class="btn btn-primary mb-2" data-toggle="modal" data-target="#add_fuel_test_modal">Add New Fuel Pump Test</button>
      </div>

      <div class="card-block">
        <div class="table-responsive">
          <table class="table table-bordered" id="fuel_test_table">
            <thead>
              <tr>
                <th>#</th>
                 <th>Shift Date</th>
                <th>Pump Name</th>
                <th>Product Name</th>
                <th>Operator Name</th>
                <th>Test Quantity</th>
                <th>Date Time</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php
               $from_datetime = $from_date . ' 00:00:00';
              $to_datetime = $to_date . ' 23:59:59';
              // Fetch all fuel pump tests for this location where status = 1 (active)
              $test_query = "
                SELECT ft.test_id, 
                       m.pump_name, 
                       p.p_name, 
                       mpo.op_name,
                       ft.tested_fuel,
                       ft.date_time,
                       spa.open_stats,
                       ft.status,
                       sof.start_time
                FROM shed_fuel_pump_test ft
                JOIN shed_pump_assign spa ON ft.pump_assign_id = spa.id
                JOIN manage_pump m ON spa.pump_id = m.id
                JOIN product_master p ON spa.product_id = p.p_id
                JOIN shed_operator_shift sof ON sof.shift_id = spa.shift_id
                JOIN manage_pump_operator mpo ON mpo.op_id = sof.operator_id
                WHERE ft.location_id = '$location_id'  AND sof.start_time BETWEEN '$from_datetime' AND '$to_datetime'
                ORDER BY ft.date_time DESC

                -- AND ft.status = 1
              ";

              $result = mysqli_query($con, $test_query);
              $count = 1;
               
if (!$result) {
    die('Query error: ' . mysqli_error($con));
}


              while ($row = mysqli_fetch_assoc($result)) {
                 $row_class = ($row['status'] == 0) ? 'class="table-danger cancelled-row"' : '';

                echo "<tr  ".$row_class." >";
                echo "<td>" . $count++ . "</td>";
                 echo "<td>" . htmlspecialchars($row['start_time']) . "</td>";
                echo "<td>" . htmlspecialchars($row['pump_name']) . "</td>";
                echo "<td>" . htmlspecialchars($row['p_name']) . "</td>";
                echo "<td>" . htmlspecialchars($row['op_name']) . "</td>";
                echo "<td>" . htmlspecialchars($row['tested_fuel']) . "</td>";
                echo "<td>" . htmlspecialchars($row['date_time']) . "</td>";

                // Cancel button only if pump is open (open_stats = 1)
                if ($row['open_stats'] == 1) {
                    if($row['status'] == 1){
                        echo "<td align='center'><button class='btn btn-danger btn-sm cancel-test-btn' data-test-id='" . $row['test_id'] . "'>Cancel</button></td>";
                    } else { echo "<td align='center'> Cancelled</td>";}

                } else {

                       if($row['status'] == 1){
                      echo "<td align='center'><button class='btn btn-secondary btn-sm' disabled>Shift closed</button></td>";
                    } else { echo "<td align='center'> Cancelled</td>";}


                 
                }

                echo "</tr>";
              }
              ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Add Fuel Test Modal -->
<div class="modal" id="add_fuel_test_modal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-body">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="font-size:24px;">
          <span aria-hidden="true">&times;</span>
        </button>
        <h4 class="modal-title mb-3">Add Fuel Pump Test</h4>
        <form method="POST" id="add_fuel_test_form">
          <table width="100%">
            <tr>
              <td align="right">Select Open Pump:</td>
              <td>
                <select name="pump_assign_id" class="form-control" required>
                  <option value="">-- Select Pump --</option>
                  <?php
                  // Fetch pumps assigned and open (open_stats = 1)
                  $pump_assign_query = "
                    SELECT spa.id as pump_assign_id, m.pump_name, p.p_name, mpo.op_name
                    FROM shed_pump_assign spa
                    JOIN manage_pump m ON spa.pump_id = m.id
                    JOIN product_master p ON spa.product_id = p.p_id
                    JOIN shed_operator_shift sof ON sof.shift_id = spa.shift_id
                    JOIN manage_pump_operator mpo ON mpo.op_id = sof.operator_id
                    WHERE spa.open_stats = 1 AND spa.status = 1  AND sof.location_id = '$location_id'
                    ORDER BY m.pump_name, p.p_name, mpo.op_name
                  ";
                  $pa_result = mysqli_query($con, $pump_assign_query);
                  while ($pa = mysqli_fetch_assoc($pa_result)) {
                    $option_text = $pa['pump_name'] . " > " . $pa['p_name'] . " > " . $pa['op_name'];
                    echo "<option value='" . $pa['pump_assign_id'] . "'>" . htmlspecialchars($option_text) . "</option>";
                  }
                  ?>
                </select>
              </td>
            </tr>
            <tr>
              <td align="right">Tested Fuel Quantity:</td>
              <td><input type="number" name="tested_fuel" class="form-control" min="0" step="0.01" required></td>
            </tr>
            <tr>
              <td></td>
              <td>
                <input type="hidden" name="add_fuel_test" value="1">
                <button type="submit" class="btn btn-success mt-2">Submit</button>
              </td>
            </tr>
          </table>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Include jQuery and SweetAlert CDN -->
 

<script>
$(document).ready(function() {
  // Cancel test button click
  $('.cancel-test-btn').on('click', function() {
    var test_id = $(this).data('test-id');
    var row = $(this).closest('tr');

    Swal.fire({
      title: 'Are you sure?',
      text: "Do you want to cancel this fuel pump test?",
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, cancel it!',
      cancelButtonText: 'No, keep it'
    }).then((result) => {
      if (result.isConfirmed) {
        $.ajax({
          url: '',  // current PHP page
          type: 'POST',
          data: { cancel_test_id: test_id },
          dataType: 'json',
          success: function(response) {
            if (response.success) {
              Swal.fire('Cancelled!', 'Fuel pump test has been cancelled.', 'success');
              // Remove row or mark cancelled
              row.remove();
            } else {
              Swal.fire('Error', response.message || 'Cancellation failed.', 'error');
              
            }
          },
          error: function() {
         setTimeout(function() {
        window.location.href = "pump_test_usage.php";
    }, 2000); // 2000 milliseconds = 2 seconds
          }
        });
      }
    });
  });
});
</script>

<?php include 'footer.php'; ?>
