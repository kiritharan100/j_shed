<!--
need to create OIL fro id 0 -->
<?php include 'header.php'; ?>
<?php
if (isset($_POST['edit_product'])) {
    $p_id = $_POST['p_id'];
    $p_code = $_POST['p_code'];
    $p_name = $_POST['p_name'];
    $vat_rate = $_POST['vat_rate'];
    $p_cat = $_POST['p_cat'];
    $p_unit = $_POST['p_unit'];
    $last_price = $_POST['last_price'];
    $status = $_POST['status'];

    $update = "UPDATE product_master SET 
        p_code = '$p_code', 
        p_name = '$p_name',
        p_cat = '$p_cat', 
        p_unit = '$p_unit',
        vat_rate = '$vat_rate',
        last_price = '$last_price',
        status = '$status'
        WHERE p_id = '$p_id'";
    mysqli_query($con, $update);

     $detail = "Product  $p_name edited";
        UserLog('1','Edit Product',$detail);


    echo "<script> notify('success', 'Product $p_name updated successfully'); </script>";
}

if (isset($_POST['add_product'])) {
    $p_code = $_POST['p_code'];
    $p_name = $_POST['p_name'];
    $p_cat = $_POST['p_cat'];
    $p_unit = $_POST['p_unit'];
    $last_price = $_POST['last_price'];

    $insert = "INSERT INTO product_master (p_code, p_name, p_cat, p_unit, last_price, status) 
               VALUES ('$p_code', '$p_name', '$p_cat', '$p_unit', '$last_price', 1)";
    mysqli_query($con, $insert);

         $detail = "New Item added $p_name ";
        UserLog('1','New Product added',$detail);

    echo "<script> notify('success', 'New product $p_name added successfully'); </script>";
}
?>

<div class="content-wrapper">
    <div class="container-fluid">
        <div class="main-header">
            <h4>Product List</h4>
        </div>

        <div class="card">
            <div class="card-header">
                <button class="btn btn-primary" data-toggle="modal" data-target="#add_product">Add Product</button>
            </div>
            <div class="card-block">
                <div class="table-responsive">
                    <table id="example" class="table ss table-bordered">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Code</th>
                                <th>Name</th>
                                <th style="text-align:center;">Category</th>
                                <th>Unit</th>
                                 <th>VAT Rate</th>
                                <th style="text-align:right;">Last Price</th>
                                  
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $count = 1;
                        $query = "SELECT * FROM product_master where p_id > 0 ORDER BY p_id";
                        $result = mysqli_query($con, $query);
                        while ($row = mysqli_fetch_assoc($result)) {
                          if($row['status'] == 1){ echo "<tr>";} else {  echo "<tr style='background-color:#f9c9ba;'>";}
                            
                            echo "<td>" . $count++ . "</td>";
                            echo "<td>" . strtoupper($row['p_code']) . "</td>";
                            echo "<td>" . $row['p_name'] . "</td>";
                            echo "<td style='text-align:center;'>" . $row['p_cat'] . "</td>";
                            echo "<td  style='text-align:center;'>" . $row['p_unit'] . "</td>";
                            $last_price = is_numeric($row['last_price']) ? number_format($row['last_price'], 2) : '0.00';

  echo "<td  style='text-align:center;'>" . $row['vat_rate'] . "</td>";
  echo "<td style='text-align:center;'>$last_price</td>";
                            echo "<td  style='text-align:center;'><button class='btn btn-sm btn-info edit-btn' data-id='" . $row['p_id'] . "' data-toggle='modal' data-target='#edit_product'>Edit</button></td>";
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

<!-- Add Product Modal -->
<div class="modal" id="add_product" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-body"> 
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">  <span aria-hidden="true">&times;</span>  </button>
        <h4 class="modal-title">Add Product</h4><hr>
        <form method="POST" id='my-form'>
          <table width="100%">
            <tr><td align="right" style="padding-right:20px;">Code *:</td><td><input type="text" name="p_code" class="form-control" required></td></tr>
            <tr><td align="right" style="padding-right:20px;">Name *:</td><td><input type="text" name="p_name" class="form-control" required></td></tr>
            <tr><td align="right" style="padding-right:20px;">Category *:</td><td>
              <select name="p_cat" class="form-control" required>
                <option value="Fuel">Fuel</option>
                <option value="Oil">Oil</option>
                <option value="Other">Other</option>
              </select></td></tr>
            <tr><td align="right" style="padding-right:20px;">Unit *:</td><td><select name="p_unit" class="form-control" required>
      <option value="">Select Unit</option>
      <option value="Nos">Nos</option>
      <option value="L">L</option>
    </select></td></tr>
            <tr><td align="right" style="padding-right:20px;">Last Price *:</td><td><input type="number" step="0.01" name="last_price" class="form-control" required></td></tr>
            <tr><td></td><td style="padding-top:10px;">
              <input type='hidden' name="add_product" >
              <button type="submit"  class="btn btn-success processing">Submit</button>
            </td></tr>
          </table>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Edit Product Modal -->
<div class="modal" id="edit_product" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-body"> 
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">  <span aria-hidden="true">&times;</span>  </button>
        <h4 class="modal-title">Edit Product</h4><hr>
        <form method="POST">
          <input type="hidden" name="p_id" id="edit_p_id">
          <table width="100%">
            <tr><td align="right" style="padding-right:20px;">Code *:</td><td><input type="text" name="p_code" id="edit_p_code" class="form-control" required></td></tr>
            <tr><td align="right" style="padding-right:20px;">Name *:</td><td><input type="text" name="p_name" id="edit_p_name" class="form-control" readonly></td></tr>
            <tr><td align="right" style="padding-right:20px;">Category *:</td><td>
              <select name="p_cat" id="edit_p_cat" class="form-control" required>
                <option value="Fuel">Fuel</option>
                <option value="Oil">Oil</option>
                <option value="Other">Other</option>
              </select></td></tr>

<tr>
  <td align="right" style="padding-right:20px;">Unit *:</td>
  <td>
    <select name="p_unit" id="edit_p_unit" class="form-control" required>
      <option value="">Select Unit</option>
      <option value="Nos">Nos</option>
      <option value="L">L</option>
      <option value="ML">ML</option>
    </select>
  </td>
</tr>

<tr><td align="right" style="padding-right:20px;">VAT Rate (%) :</td><td><input type="number" step="0.1" max='50'min='0' name="vat_rate" id="edit_vat_rate" class="form-control" required></td></tr>
            <tr><td align="right" style="padding-right:20px;">Last Price *:</td><td><input type="number" step="0.01" name="last_price" id="edit_last_price" class="form-control" required></td></tr>
            <tr><td align="right" style="padding-right:20px;">Status:</td><td>
              <select name="status" id="edit_status" class="form-control">
                <option value="1">Active</option>
                <option value="0">Inactive</option>
              </select></td></tr>
            <tr><td></td><td style="padding-top:10px;"><button type="submit" name="edit_product" class="btn btn-success">Submit</button></td></tr>
          </table>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
$(document).ready(function() {
    $('#example').DataTable({
        pageLength: 200 // Show 200 records by default
    });

    $('.edit-btn').click(function() {
        var row = $(this).closest('tr');
        $('#edit_p_id').val($(this).data('id'));
        $('#edit_p_code').val(row.find('td:eq(1)').text());
        $('#edit_p_name').val(row.find('td:eq(2)').text());
        $('#edit_p_cat').val(row.find('td:eq(3)').text());
        $('#edit_p_unit').val(row.find('td:eq(4)').text());
        
        $('#edit_vat_rate').val(row.find('td:eq(5)').text());
        $('#edit_last_price').val(row.find('td:eq(6)').text().replace(/,/g, ''));
    });
});

 

</script>

<?php include 'footer.php'; ?>
