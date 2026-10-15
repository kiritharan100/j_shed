<?php include 'header.php'; ?>
<?php
if (isset($_POST['add_pump'])) {
    $pump_name = $_POST['pump_name'];
    $product_assigned = $_POST['product_assigned'];
    $status = 1;
    $location_id = $location_id; // from header

    $insert = "INSERT INTO manage_pump (location_id, pump_name, product_assigned, status) 
               VALUES ('$location_id', '$pump_name', '$product_assigned', '$status')";
    mysqli_query($con, $insert);


    $detail = "New pump $pump_name Added   ";
        UserLog('1','New pump added ',$detail);


    echo "<script> notify('success', 'New pump added successfully'); </script>";
}

if (isset($_POST['edit_pump'])) {
    $id = $_POST['pump_id'];
    $pump_name = $_POST['pump_name'];
    $product_assigned = $_POST['product_assigned'];
    $status = $_POST['status'];
    $opening_meater_reading = $_POST['opening_meater_reading'];

    $update = "UPDATE manage_pump SET pump_name = '$pump_name', product_assigned = '$product_assigned', status = '$status' , opening_meater_reading ='$opening_meater_reading'
               WHERE id = '$id'";
    mysqli_query($con, $update);

        $detail = " Pump Detail Edited  $pump_name   ";
        UserLog('1','Pump Detail Edited',$detail);

    echo "<script> notify('success', 'Pump details updated'); </script>";
}
?>

<div class="content-wrapper">
    <div class="container-fluid">
        <div class="main-header">
            <h4><i class='fas fa-gas-pump'></i> Manage Fuel Pumps - <?php echo $client_name; ?></h4>
        </div>

        <div class="card">
            <div class="card-header">
              
            <div class="alert alert-info" role="alert">
 <strong><i class="fa fa-question-circle" aria-hidden="true"></i> Help:</strong> Select the <strong>Fuel Station</strong> from the main bar to add a new pump.
</div>
     <button class="btn btn-primary" data-toggle="modal" data-target="#add_pump_modal"> <i class='fas fa-gas-pump'></i> Add New Pump to <?php echo $client_name; ?></button>

    </div>
 
 

            <div class="card-block">
                <div class="table-responsive">
                    <table id='example' class="table table-bordered">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Pump Name</th>
                                <th>Product Assigned</th>
                                <th>Opening Meter reading</th>

                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $query = "SELECT m.*, p.p_name, COUNT(spa.pump_id) as count_row FROM manage_pump m 
                                  LEFT JOIN product_master p ON m.product_assigned = p.p_id 
                                  LEFT JOIN shed_pump_assign spa on spa.pump_id = m.id
                                  WHERE m.location_id = '$location_id' 
                                  group by m.id
                                  ORDER BY m.pump_name ASC";
                        $result = mysqli_query($con, $query);
                        $count = 1;
                        while ($row = mysqli_fetch_assoc($result)) {
                            if($row['status'] == 1){ echo "<tr>";} else {  echo "<tr style='background-color:#f9c9ba;'>";}
                            echo "<td>" . $count++ . "</td>";
                            echo "<td>" . $row['pump_name'] . "</td>";
                            echo "<td>" . ($row['p_name'] ?? 'Unassigned') . "</td>";
                             echo "<td>" . $row['opening_meater_reading'] . "</td>";
                            echo "<td>" . ($row['status'] == 1 ? 'Active' : 'Inactive') . "</td>";
                          echo "<td align='center'><button class='btn btn-sm btn-info edit-pump' 
        data-id='" . $row['id'] . "' 
        data-name='" . $row['pump_name'] . "' 
        data-opening_meater_reading='" . $row['opening_meater_reading'] . "' 
        data-product='" . $row['product_assigned'] . "'
        data-status='" . $row['status'] . "' 
        data-count_row='" . $row['count_row'] . "'
        data-toggle='modal' 
        data-target='#edit_pump_modal'>Edit</button></td>";
                        }
                        ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Pump Modal -->
<div class="modal" id="add_pump_modal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-body">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title">Add Fuel Pump</h4><hr>
        <form method="POST">
          <table width="100%">
            <tr>
              <td align="right" style="padding-right:20px;">Pump Name *:</td>
              <td><input type="text" name="pump_name" class="form-control" required></td>
            </tr>
            <tr>
              <td align="right" style="padding-right:20px;">Assign Product *:</td>
              <td>
                <select name="product_assigned" class="form-control" required>
                  <option value="">Select Fuel Product</option>
                  <?php
                  $prod_query = "SELECT p_id, p_name FROM product_master WHERE p_cat = 'Fuel' AND status = 1";
                  $prod_result = mysqli_query($con, $prod_query);
                  while ($prod = mysqli_fetch_assoc($prod_result)) {
                      echo "<option value='" . $prod['p_id'] . "'>" . $prod['p_name'] . "</option>";
                  }
                  ?>
                </select>
              </td>
            </tr>
            <tr><td></td><td style="padding-top:10px;"><button type="submit" name="add_pump" class="btn btn-success">Submit</button></td></tr>
          </table>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Edit Pump Modal -->
<div class="modal" id="edit_pump_modal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-body">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title">Edit Fuel Pump</h4><hr>
        <form method="POST">
          <input type="hidden" name="pump_id" id="edit_pump_id">
          <table width="100%">
            <tr>
              <td align="right" style="padding-right:20px;">Pump Name *:</td>
              <td><input type="text" name="pump_name" id="edit_pump_name" class="form-control" readonly></td>
            </tr>
            <tr>
              <td align="right" style="padding-right:20px;">Assign Product *:</td>
              <td>
                <select name="product_assigned" id="edit_product_assigned"   class="form-control" required>
                  <option value="">Select Fuel Product</option>
                  <?php
                  $prod_query = "SELECT p_id, p_name FROM product_master WHERE p_cat = 'Fuel' AND status = 1";
                  $prod_result = mysqli_query($con, $prod_query);
                  while ($prod = mysqli_fetch_assoc($prod_result)) {
                      echo "<option value='" . $prod['p_id'] . "'>" . $prod['p_name'] . "</option>";
                  }
                  ?>
                </select>
              </td>
            </tr>
            <td align="right" style="padding-right:20px;">Opening Meter Reding *:</td>
              <td><input type="number" step='0.001' name="opening_meater_reading" id="edit_opening_meater_reading" class="form-control" ></td>


            <tr>
              <td align="right" style="padding-right:20px;">Status:</td>
              <td>
                <select name="status" id="edit_status" class="form-control">
                  <option value="1">Active</option>
                  <option value="0">Inactive</option>
                </select>
              </td>
            </tr>
            <tr><td></td><td style="padding-top:10px;"><button type="submit" name="edit_pump" class="btn btn-success">Update</button></td></tr>
          </table>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
$(document).ready(function() {
    $('.edit-pump').click(function() {
        $('#edit_pump_id').val($(this).data('id'));
        $('#edit_pump_name').val($(this).data('name')); 
        $('#edit_product_assigned').val($(this).data('product')); 
        $('#edit_opening_meater_reading').val($(this).data('opening_meater_reading'));
        $('#edit_status').val($(this).data('status'));

        // Disable opening meter reading if count_row > 0
        const countRow = parseInt($(this).data('count_row'));
        if (countRow > 0) {
            $('#edit_opening_meater_reading').prop('disabled', true);
        } else {
            $('#edit_opening_meater_reading').prop('disabled', false);
        }
    });
});

</script>

<?php include 'footer.php'; ?>
