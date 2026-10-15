<?php
include 'header.php';

// Load banks first, so we can use in both modals
$banks_q = mysqli_query($con, "SELECT ca_id, ca_name FROM acc_chart_of_accounts WHERE ca_group='Cash in Hand' AND ca_id != 44 AND ca_id != 7 ORDER BY ca_name ASC");
$banks = [];
while ($b = mysqli_fetch_assoc($banks_q)) {
    $banks[] = $b;
}

// Add new card
if (isset($_POST['add_card'])) {
    $card_name = mysqli_real_escape_string($con, $_POST['card_name']);
    $status = (int)$_POST['status'];
    $ca_id = (int)$_POST['bank_account'];

    $insert = "INSERT INTO shed_card_type (card_name, status, ca_id, location_id) VALUES ('$card_name', '$status', '$ca_id', '$location_id')";
    mysqli_query($con, $insert);
    echo "<script> notify('success', 'Credit type added successfully'); </script>";
}

// Edit card
if (isset($_POST['edit_card'])) {
    $card_id = (int)$_POST['card_id'];
    $card_name = mysqli_real_escape_string($con, $_POST['card_name']);
    $status = (int)$_POST['status'];
    $ca_id = (int)$_POST['bank_account'];

    $update = "UPDATE shed_card_type SET card_name='$card_name', status='$status', ca_id='$ca_id' WHERE card_id='$card_id'";
    mysqli_query($con, $update);
    echo "<script> notify('success', 'Credit type updated successfully'); </script>";
}

// Fetch existing cards
$cards_q = mysqli_query($con, "SELECT card_id, card_name, shed_card_type.status, shed_card_type.ca_id, acc_chart_of_accounts.ca_name 
FROM shed_card_type
LEFT JOIN acc_chart_of_accounts ON acc_chart_of_accounts.ca_id = shed_card_type.ca_id
WHERE shed_card_type.location_id='$location_id'");
?>

<div class="content-wrapper">
<div class="container-fluid">
<div class="main-header"><h4>Manage Credit Types</h4></div>

<div class="card">
<div class="card-block">
<button class="btn btn-primary mb-3" data-toggle="modal" data-target="#addCardModal">Add Credit Type</button>

<div class="table-responsive">
<table id='example' class="table table-bordered table-hover">
<thead>
<tr>
<th>#</th>
<th>Card Name</th>
<th>Bank account</th>
<th>Status</th>
<th>Action</th>
</tr>
</thead>
<tbody>
<?php
$count = 1;
while ($card = mysqli_fetch_assoc($cards_q)) {
    echo "<tr>";
    echo "<td>" . $count++ . "</td>";
    echo "<td>" . htmlspecialchars($card['card_name']) . "</td>";
    echo "<td>" . htmlspecialchars($card['ca_name']) . "</td>";
    echo "<td>" . ($card['status'] == 1 ? 'Active' : 'Inactive') . "</td>";
    echo "<td>";
    echo "<button class='btn btn-success btn-sm edit-card-btn' 
            data-id='{$card['card_id']}'
            data-name=\"" . htmlspecialchars($card['card_name'], ENT_QUOTES) . "\"
            data-status='{$card['status']}'
            data-ca='{$card['ca_id']}'
            data-toggle='modal' data-target='#editCardModal'>Edit</button>";
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

<!-- Add Credit Type Modal -->
<div class="modal fade" id="addCardModal" tabindex="-1">
<div class="modal-dialog">
<form method="POST">
<input type="hidden" name="add_card" value="1">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title">Add Credit Type</h5>
<button type="button" class="close" data-dismiss="modal">&times;</button>
</div>
<div class="modal-body">

<div class="form-group row">
<label class="col-sm-3 col-form-label">Card Name</label>
<div class="col-sm-9">
<input type="text" class="form-control" name="card_name" required>
</div>
</div>

<div class="form-group row">
<label class="col-sm-3 col-form-label">Realise Bank account</label>
<div class="col-sm-9">
<select name="bank_account" class="form-control" required>
<option value="">-- Select Bank --</option>
<?php foreach ($banks as $b) echo "<option value=\"{$b['ca_id']}\">{$b['ca_name']}</option>"; ?>
</select>
</div>
</div>

<div class="form-group row">
<label class="col-sm-3 col-form-label">Status</label>
<div class="col-sm-9">
<select name="status" class="form-control" required>
<option value="1">Active</option>
<option value="0">Inactive</option>
</select>
</div>
</div>

</div>
<div class="modal-footer">
<button type="submit" class="btn btn-primary">Save</button>
<button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
</div>
</div>
</form>
</div>
</div>

<!-- Edit Credit Type Modal -->
<div class="modal fade" id="editCardModal" tabindex="-1">
<div class="modal-dialog">
<form method="POST">
<input type="hidden" name="edit_card" value="1">
<input type="hidden" name="card_id" id="edit_card_id">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title">Edit Credit Type</h5>
<button type="button" class="close" data-dismiss="modal">&times;</button>
</div>
<div class="modal-body">

<div class="form-group row">
<label class="col-sm-3 col-form-label">Card Name</label>
<div class="col-sm-9">
<input type="text" class="form-control" name="card_name" id="edit_card_name" required>
</div>
</div>

<div class="form-group row">
<label class="col-sm-3 col-form-label">Realise Bank account</label>
<div class="col-sm-9">
<select name="bank_account" class="form-control" id="edit_bank_account" required>
<option value="">-- Select Bank --</option>
<?php foreach ($banks as $b) echo "<option value=\"{$b['ca_id']}\">{$b['ca_name']}</option>"; ?>
</select>
</div>
</div>

<div class="form-group row">
<label class="col-sm-3 col-form-label">Status</label>
<div class="col-sm-9">
<select name="status" class="form-control" id="edit_status" required>
<option value="1">Active</option>
<option value="0">Inactive</option>
</select>
</div>
</div>

</div>
<div class="modal-footer">
<button type="submit" class="btn btn-primary">Save Changes</button>
<button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
</div>
</div>
</form>
</div>
</div>

<script>
$(document).ready(function(){
    $('.edit-card-btn').click(function(){
        $('#edit_card_id').val($(this).data('id'));
        $('#edit_card_name').val($(this).data('name'));
        $('#edit_status').val($(this).data('status'));
        $('#edit_bank_account').val($(this).data('ca'));
    });
});
</script>

<?php include 'footer.php'; ?>
