<?php include 'header.php'; ?>
<?php
if (isset($_POST['add_price_change'])) {
    $p_id = $_POST['p_id'];
    $new_price = $_POST['new_price'];
    $date_only = $_POST['date_time'];
    $date_time = $date_only . " 00:01:00"; // auto-pick 12:01 AM
    $record_time = date('Y-m-d H:i:s');
    $recorded_by = $user_id;
    $status = 1;

    $insert = "INSERT INTO fuel_price_change (p_id, date_time, new_price, record_time, recorded_by, status) 
               VALUES ('$p_id', '$date_time', '$new_price', '$record_time', '$recorded_by', '$status')";
    mysqli_query($con, $insert);

          $detail = "Fuel Price changes added p_id $p_id , Price Rs. $new_price  time of effect  $date_time ";
        UserLog('1','Price Changes',$detail);

    echo "<script> notify('success', 'Price change scheduled successfully'); </script>";
}

if (isset($_POST['cancel_price_change'])) {
    $id = $_POST['change_id'];
    $cancel_time = date('Y-m-d H:i:s');
    $cancel_by = $user_id;
    $cancel_note = "User-$cancel_by | $cancel_time";

     $detail = " Fuel Price cancelled $cancel_note";
        UserLog('1','Price Cancellation',$detail);

    $update = "UPDATE fuel_price_change SET status = 0, cancellation = '$cancel_note' WHERE id = '$id'";
    mysqli_query($con, $update);
    echo "<script> notify('success', 'Price change canceled'); </script>";
}
?>

<div class="content-wrapper">
    <div class="container-fluid">
        <div class="main-header">
            <h4>Fuel Price Change Schedule  </h4>
        </div>

        <div class="card">
            <div class="card-header">
                <button class="btn btn-primary" data-toggle="modal" data-target="#add_price_modal">Schedule New Price Change</button>
            </div>
            <div class="card-block">
                <div class="table-responsive">
                    <table id='example' class="table table-bordered">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Product</th>
                                <th>Code</th>
                                <th>New Price</th>
                                <th>Scheduled Time</th>
                                <th>Recorded Time</th>
                                <th>Recorded By</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $query = "SELECT f.*, p.p_name, p.p_code, u.i_name FROM fuel_price_change f 
                                  JOIN product_master p ON f.p_id = p.p_id 
                                  LEFT JOIN user_license u ON f.recorded_by = u.usr_id
                                  WHERE p.p_cat = 'Fuel'
                                  ORDER BY f.date_time DESC";
                        $result = mysqli_query($con, $query);
                        $count = 1;
                        $now = date('Y-m-d H:i:s');
                        while ($row = mysqli_fetch_assoc($result)) {
                            $row_class = '';
                            if ($row['status'] == 0) {
                                $row_class = "style='background-color: #f8d7da;'"; // red for canceled
                            } elseif ($row['date_time'] > $now) {
                                $row_class = "style='background-color: #d4edda;'"; // green for future
                            }
                            echo "<tr $row_class>";
                            echo "<td>" . $count++ . "</td>";
                            echo "<td>" . $row['p_name'] . "</td>";
                            echo "<td>" . $row['p_code'] . "</td>";
                            echo "<td style='text-align:right;'>" . number_format($row['new_price'], 2) . "</td>";
                            echo "<td>" . $row['date_time'] . "</td>";
                            echo "<td>" . $row['record_time'] . "</td>";
                            echo "<td>" . $row['i_name'] . "</td>";
                            echo "<td>" . ($row['status'] == 1 ? 'Active' : 'Canceled') . "</td>";
                            echo "<td>";
                            if ($row['status'] == 1 && date('Y-m-d', strtotime($row['date_time'])) > date('Y-m-d')) {
                                echo "<button class='btn btn-danger btn-sm cancel-btn' data-id='" . $row['id'] . "'>Cancel</button>";
                            }
                            echo "</td>";
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

<!-- Add Price Change Modal -->
<div class="modal" id="add_price_modal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-body">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title">Schedule Fuel Price Change</h4><hr>
        <form method="POST" id='my-form'>
          <table width="100%">
            <tr>
              <td align="right" style="padding-right:20px;">Fuel Product *:</td>
              <td>
                <select name="p_id" class="form-control" required>
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
            <tr>
              <td align="right" style="padding-right:20px;">New Price *:</td>
              <td><input type="number" step="0.01" name="new_price" class="form-control" required></td>
            </tr>
            <tr>
            <td align="right" style="padding-right:20px;">Effective Date *:</td>
<td>
  <input type="datetime-local" name="date_time" class="form-control" required
         value="<?php echo date('Y-m-d', strtotime('+1 day')) . 'T00:00'; ?>">
</td>
            </tr>
            <tr><td></td><td style="padding-top:10px;">
                <input type='hidden' name='add_price_change' value='1'>
                <button type="submit"  class="btn btn-success processing">Submit</button>
            </td></tr>
          </table>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.querySelectorAll('.cancel-btn').forEach(function(button) {
    button.addEventListener('click', function () {
        const changeId = this.getAttribute('data-id');
        Swal.fire({
            title: 'Are you sure?',
            text: "This price change will be canceled!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, cancel it!'
        }).then((result) => {
            if (result.isConfirmed) {
                const form = document.createElement('form');
                form.method = 'POST';
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'change_id';
                input.value = changeId;
                const submit = document.createElement('input');
                submit.type = 'hidden';
                submit.name = 'cancel_price_change';
                form.appendChild(input);
                form.appendChild(submit);
                document.body.appendChild(form);
                form.submit();
            }
        });
    });
});
</script>

<?php include 'footer.php'; ?>
