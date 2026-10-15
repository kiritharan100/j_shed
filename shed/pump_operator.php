<?php include 'header.php'; ?>
<?php


if (isset($_POST['add_operator'])) {
    $op_name = $_POST['op_name'];
    $condact_number = $_POST['condact_number'];
    $nic_no = $_POST['nic_no'];
    $status = 1;

    $insert = "INSERT INTO manage_pump_operator (location_id, op_name, condact_number, nic_no, status) 
               VALUES ('$location_id', '$op_name', '$condact_number', '$nic_no', '$status')";
    mysqli_query($con, $insert);

    $detail = "New pump operator $op_name added with NIC $nic_no.";
    UserLog('2', 'New Pump Operator Added', $detail);
    echo "<script> notify('success', 'New pump operator added successfully'); </script>";
}

if (isset($_POST['edit_operator'])) {
    $op_id = $_POST['op_id'];
    $op_name = $_POST['op_name'];
    $condact_number = $_POST['condact_number'];
    $nic_no = $_POST['nic_no'];
    $status = $_POST['status'];

    $update = "UPDATE manage_pump_operator SET 
                op_name = '$op_name', 
                condact_number = '$condact_number', 
                nic_no = '$nic_no', 
                status = '$status' 
              WHERE op_id = '$op_id'";
    mysqli_query($con, $update);

    $detail = "Pump operator $op_name updated.";
    UserLog('2', 'Pump Operator Edited', $detail);
    echo "<script> notify('success', 'Pump operator details updated'); </script>";
}


 
if (isset($_POST['submit_cap'])) {
    $capacities = $_POST['capacity'];

    foreach ($capacities as $p_id => $cap_value) {
        $p_id = mysqli_real_escape_string($con, $p_id);
        $cap_value = mysqli_real_escape_string($con, $cap_value);

        $check = mysqli_query($con, "SELECT id FROM fuel_capacity WHERE p_id = '$p_id' AND location_id = '$location_id'");
        if (mysqli_num_rows($check) > 0) {
            mysqli_query($con, "UPDATE fuel_capacity SET capacity = '$cap_value' WHERE p_id = '$p_id' AND location_id = '$location_id'");
        } else {
            mysqli_query($con, "INSERT INTO fuel_capacity (p_id, location_id, capacity, status) VALUES ('$p_id', '$location_id', '$cap_value', 1)");
        }
    }

   
    echo "<script>
    window.location.replace('pump_operator.php?success');
    // 
    // </script>";
    exit();
}

// Later in the page (before any HTML output)
if (isset($_REQUEST['success'])) {
 
    echo "<script> notify('success', 'Capacities updated successfully!'); </script>";
    unset($_SESSION['cap_success']);
}
 



?>

<div class="content-wrapper">
  <div class="container-fluid">
    <div class="main-header">
      <h4>Manage Pump Operators</h4>
    </div>


    <div class="row">
  <!-- Left side: Operator card (50%) -->
  <div class="col-md-8">
    <div class="card">
      <div class="card-header">
        <button class="btn btn-primary" data-toggle="modal" data-target="#add_operator_modal">Add New Operator</button>
      </div>
      <div class="card-block">
        <div class="table-responsive">
          <table class="table table-bordered">
            <thead>
              <tr>
                <th>#</th>
                <th>Name</th>
                <th>Contact Number</th>
                <th>NIC</th>
                <th>Status</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $query = "SELECT * FROM manage_pump_operator WHERE location_id = '$location_id' ORDER BY op_name ASC";
              $result = mysqli_query($con, $query);
              $count = 1;
              while ($row = mysqli_fetch_assoc($result)) {
                echo ($row['status'] == 1) ? "<tr>" : "<tr style='background-color:#f9c9ba;'>";
                echo "<td>" . $count++ . "</td>";
                echo "<td>" . $row['op_name'] . "</td>";
                echo "<td>" . $row['condact_number'] . "</td>";
                echo "<td>" . $row['nic_no'] . "</td>";
                echo "<td>" . ($row['status'] == 1 ? 'Active' : 'Inactive') . "</td>";
                echo "<td><button class='btn btn-sm btn-info edit-operator' 
                            data-id='" . $row['op_id'] . "' 
                            data-name='" . $row['op_name'] . "' 
                            data-number='" . $row['condact_number'] . "' 
                            data-nic='" . $row['nic_no'] . "' 
                            data-status='" . $row['status'] . "' 
                            data-toggle='modal' 
                            data-target='#edit_operator_modal'>Edit</button></td>";
                echo "</tr>";
              }
              ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
     </div>
 
  <!-- Left side: Operator card (50%) -->
  <div class="col-md-4">

  <div class="card">
 <div class="card-block">
<h5>Record Total Fuel Capacity </h5>
<hr>
  <form method="POST">
  <table class="table table-bordered">
    <thead>
      <tr>
        <th>#</th>
        <th>Fuel Name</th>
        <th>Capacity (Litres)</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $query = "SELECT p_id, p_name FROM product_master WHERE p_cat = 'Fuel'";
      $result = mysqli_query($con, $query);
      $count = 1;

      while ($row = mysqli_fetch_assoc($result)) {
          $p_id = $row['p_id'];
          $p_name = $row['p_name'];

          // Check if this fuel already has a capacity for the location
          $cap_query = "SELECT capacity FROM fuel_capacity WHERE p_id = '$p_id' AND location_id = '$location_id'";
          $cap_result = mysqli_query($con, $cap_query);
          $capacity = '';
          if ($cap_row = mysqli_fetch_assoc($cap_result)) {
              $capacity = $cap_row['capacity'];
          }

          echo "<tr>";
          echo "<td>" . $count++ . "</td>";
          echo "<td>$p_name</td>";
          echo "<td>
                  <input type='number' step='0.01' class='form-control' name='capacity[$p_id]' value='" . htmlspecialchars($capacity) . "'>
                </td>";
          echo "</tr>";
      }
      ?>
    </tbody>
  </table>
  <button type="submit" name="submit_cap" class="btn btn-success">Save Capacities</button>
</form>

   



 </div> </div>
 </div>
 </div>



  </div>
</div>

<!-- Add Operator Modal -->
<div class="modal" id="add_operator_modal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-body">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
        <h4 class="modal-title">Add Pump Operator</h4>
        <hr>
        <form method="POST" id='my-form'>
          <table width="100%">
            <tr><td align="right">Name:</td><td><input type="text" name="op_name" class="form-control" required></td></tr>
            <tr><td align="right">Contact Number:</td><td><input type="text" name="condact_number" class="form-control" required></td></tr>
            <tr><td align="right">NIC No:</td><td><input type="text" name="nic_no" class="form-control" required></td></tr>
            <tr><td></td><td>
                <input type="Hidden" name="add_operator" value="1">
                <button type="submit"  class="btn btn-success mt-2 processing">Submit</button>
            </td></tr>
          </table>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Edit Operator Modal -->
<div class="modal" id="edit_operator_modal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-body">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
        <h4 class="modal-title">Edit Pump Operator</h4>
        <hr>
        <form method="POST">
          <input type="hidden" name="op_id" id="edit_op_id">
          <table width="100%">
            <tr><td align="right">Name:</td><td><input type="text" name="op_name" id="edit_op_name" class="form-control" readonly></td></tr>
            <tr><td align="right">Contact Number:</td><td><input type="text" name="condact_number" id="edit_condact_number" class="form-control" required></td></tr>
            <tr><td align="right">NIC No:</td><td><input type="text" name="nic_no" id="edit_nic_no" class="form-control" required></td></tr>
            <tr><td align="right">Status:</td>
              <td>
                <select name="status" id="edit_status" class="form-control">
                  <option value="1">Active</option>
                  <option value="0">Inactive</option>
                </select>
              </td></tr>
            <tr><td></td><td><button type="submit" name="edit_operator" class="btn btn-success mt-2">Update</button></td></tr>
          </table>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
$(document).ready(function() {
  $('.edit-operator').click(function() {
    $('#edit_op_id').val($(this).data('id'));
    $('#edit_op_name').val($(this).data('name'));
    $('#edit_condact_number').val($(this).data('number'));
    $('#edit_nic_no').val($(this).data('nic'));
    $('#edit_status').val($(this).data('status'));
  });
});
</script>

<?php include 'footer.php'; ?>
