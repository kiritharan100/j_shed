<?php include("header.php");

$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date   = $_GET['end_date'] ?? date('Y-m-d');
$start_datetime = $start_date . ' 00:00:00';
$end_datetime = $end_date . ' 23:59:59';

$journals = mysqli_query($con, "
  SELECT * FROM acc_journal 
  WHERE journal_date BETWEEN '$start_datetime' AND '$end_datetime'  AND location_id = '$location_id'
  ORDER BY id DESC LIMIT 100
");

?>

<div class="content-wrapper">
  <div class="container-fluid">
    <div class="main-header"><h5>Accounts | Journal Entries</h5></div>

    <div class="row">
      <div class="col-md-12">
        <div class="card card-block">
          <div class="form-inline mb-3">
            <label>Start Date</label>&nbsp;
            <input type="date" class="form-control" name="start_date" value="<?= $start_date ?>">&nbsp;&nbsp;
            <label>End Date</label>&nbsp;
            <input type="date" class="form-control" name="end_date" value="<?= $end_date ?>">&nbsp;
            <button type="submit" class="btn btn-primary">Filter</button>
            <button class="btn btn-success ml-auto" onclick="openJournalModal()">+ Add Journal</button>
                              <button type='button' id="exportButton" filename='<?php echo "Journal_list_".$start_date."_".$end_date; ?>.xlsx' class="btn btn-primary"><i class="ti-cloud-down"></i> Export</button>

          </div> <hr>
          <table id="example" class="table table-bordered table-sm">
            <thead class="thead-dark">
              <tr>
                <th>#</th>
                 <th>Ref No</th>
                 <th>Journal Date</th><th>Memo</th><th>Debit</th><th>Credit</th><th>Status</th><th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php 
              $count = 1;
              while ($j = mysqli_fetch_assoc($journals)) {
                $style = $j['status'] == 0 ? "class='table-danger cancelled-row'" : "";
                echo "<tr $style>
                  <td>{$count}</td>
                  <td align='center'>J{$j['loc_no']}</td>
                  <td>{$j['journal_date']}</td>
                  <td>{$j['memo']}</td>
                  <td align='right'>".number_format($j['total_debit'], 2)."</td>
                  <td align='right'>".number_format($j['total_credit'], 2)."</td>
                  <td align='center'>".($j['status'] ? 'Posted' : 'Cancelled')."</td>
                  <td align='center'><button class='btn btn-primary btn-sm' style='padding: 2px 8px; font-size: 12px;' onclick='editJournal({$j['id']})'>Edit</button></td>
                </tr>";
                $count++;
              }
              ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Journal Modal -->
<div class="modal fade" id="journalModal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-xl" role="document" style=' max-width: 1140px;'> 
    <form id="journalForm" class="processing_form">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Journal Entry</h5></div>
        <div class="modal-body">
          <div class="row mb-2">
            <div class="col-md-3"><label>Date</label><input type="date" name="journal_date" class="form-control" value="<?=date('Y-m-d')?>"></div>
            <div class="col-md-9"><label>Memo</label><input type="text" required name="memo" class="form-control">
        <input type="hidden" name="location_id" value="<?= $location_id ?>"></div>
          </div><br>
          <table class="table table-bordered" id="journal_lines">
            <thead>
              <tr><th>#</th><th>Category</th><th style='width:400px;' >Description</th><th style='width:150px;'>Debit</th><th style='width:150px;'>Credit</th><th></th></tr>
            </thead>
            <tbody></tbody>
            <tfoot>
              <tr>
                <td colspan="3" align="right"><b>Total</b></td>
                <td><input type="text" readonly class="form-control text-right" id="total_debit"></td>
                <td><input type="text" readonly class="form-control text-right" id="total_credit"></td>
                <td></td>
              </tr>
            </tfoot>
          </table>
          <button type="button" class="btn btn-secondary btn-sm" onclick="addJournalLine()">+ Add Line</button>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-success processing_button">Save Journal</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
    let accountOptions = "";
// function openJournalModal() {
//   $('#journalForm')[0].reset();
//   $('#journalForm input[name=id]').remove();
//   $('#journal_lines tbody').html('');
//   addJournalLine(); addJournalLine();
//   loadAccounts();
//   $('#journalModal').modal('show');
// }
function openJournalModal() {
  $('#journalForm')[0].reset();
  $('#journalForm input[name=id]').remove();
  $('#journal_lines tbody').html('');
  loadAccountsOnce(); // <-- call the new function here
  
  $('#journalModal').modal('show');
}


 function addJournalLine(selectedAccountId = '') {
  const index = $('#journal_lines tbody tr').length + 1;

  let options = '';
  if (accountOptions.trim() === '') {
    options = `<option value="">-- No accounts loaded --</option>`;
  } else {
    // inject selected attribute into options
    const $temp = $('<select>' + accountOptions + '</select>');
    $temp.find('option').each(function () {
      if ($(this).val() == selectedAccountId) {
        $(this).attr('selected', true);
      }
    });
    options = $temp.html();
  }

  const row = `<tr>
    <td>${index}</td>
    <td>
      <select name="category[]" class="form-control select2_account" style="width:300px;">
        ${options}
      </select>
    </td>
    <td><input type="text" name="description[]" class="form-control"></td>
    <td><input type="number" step="0.01" name="debit[]" class="form-control text-right" oninput="updateTotals()"></td>
    <td><input type="number" step="0.01" name="credit[]" class="form-control text-right" oninput="updateTotals()"></td>
    <td><button type="button" class="btn btn-danger btn-sm" onclick="$(this).closest('tr').remove();updateTotals();">&times;</button></td>
  </tr>`;

  $('#journal_lines tbody').append(row);

  // Now initialize select2 on this row only
  $('#journal_lines tbody tr:last .select2_account').select2({
    dropdownParent: $('#journalModal')
  });
}



function addJournalLine() {
  const index = $('#journal_lines tbody tr').length + 1;

  const row = `<tr>
    <td>${index}</td>
    <td><select  style='width:300px;' name="category[]" class="form-control select2_account">${accountOptions}</select></td>
    <td><input type="text" name="description[]" class="form-control"></td>
<td>
  <input type="number" step="0.01" name="debit[]" class="form-control text-right" 
         oninput="clearOpposite(this, 'credit'); updateTotals();">
</td>
<td>
  <input type="number" step="0.01" name="credit[]" class="form-control text-right" 
         oninput="clearOpposite(this, 'debit'); updateTotals();">
</td>

    <td><button type="button" class="btn btn-danger btn-sm" onclick="$(this).closest('tr').remove();updateTotals();">&times;</button></td>
  </tr>`;

  $('#journal_lines tbody').append(row);

  // Initialize only the new row's select2, not all
  $('#journal_lines tbody tr:last .select2_account').select2({
    dropdownParent: $('#journalModal')
  });
}


// function addJournalLine() {
//   const index = $('#journal_lines tbody tr').length + 1;
//   const row = `<tr>
//     <td>${index}</td>
//     <td><select style='width:300px;' name="category[]" class="form-control select2_account"><option value="">-- Account --</option></select></td>
//     <td><input type="text" name="description[]" class="form-control"></td>
//     <td><input type="number" step="0.01" name="debit[]" class="form-control text-right" oninput="updateTotals()"></td>
//     <td><input type="number" step="0.01" name="credit[]" class="form-control text-right" oninput="updateTotals()"></td>
//     <td><button type="button" class="btn btn-danger btn-sm" onclick="$(this).closest('tr').remove();updateTotals();">&times;</button></td>
//   </tr>`;
//   $('#journal_lines tbody').append(row);
//   loadAccounts();
// }

function updateTotals() {
  let totalDebit = 0, totalCredit = 0;
  $('input[name="debit[]"]').each((i, el) => totalDebit += parseFloat(el.value) || 0);
  $('input[name="credit[]"]').each((i, el) => totalCredit += parseFloat(el.value) || 0);
  $('#total_debit').val(totalDebit.toFixed(2));
  $('#total_credit').val(totalCredit.toFixed(2));
}

// function loadAccounts() {
//   $.get('ajax/fetch_account_list.php', function(resp) {
//     $('.select2_account').html(resp).select2({ dropdownParent: $('#journalModal') });
//   });
// }

function loadAccountsOnce() {
  $.get('ajax/fetch_account_list.php', function(resp) {
    accountOptions = resp;

    // Add 2 default lines only after accounts are loaded
    addJournalLine();
    addJournalLine();
    
  });
}

$('#journalForm').on('submit', function(e) {
  e.preventDefault();
  if ($('#total_debit').val() !== $('#total_credit').val()) {
    notify('danger', 'Mismatch', 'Debit and Credit must balance.');
    return;
  }
  const formData = new FormData(this);


$.ajax({
  url: 'ajax/save_journal.php',
  method: 'POST',
  data: formData,
  processData: false,
  contentType: false,
  success: function(resp) {
    if (resp.toLowerCase().includes("saved")) {
      notify('success', 'Saved', resp);
      $('#journalModal').modal('hide');
      setTimeout(() => location.reload(), 800);
    } else {
      notify('danger', 'Error', resp);
      $('.processing_button').prop('disabled', false).html('Save Journal');
    }
  },
  error: function(xhr) {
    notify('danger', 'Error', 'Server error occurred.');
    $('.processing_button').prop('disabled', false).html('Save Journal');
  }
});




//   $.post('ajax/save_journal.php', formData, function(resp) {
//     notify('success', 'Saved', resp);
//     setTimeout(() => location.reload(), 1000);
//   });
});


function clearOpposite(currentInput, targetName) {
  const row = $(currentInput).closest('tr');
  const target = row.find(`input[name="${targetName}[]"]`);
  if (parseFloat(currentInput.value) > 0) {
    target.val('');
  }
}



 function editJournal(id) {
  $.get('ajax/get_journal_details.php', { id }, function(res) {
    const data = JSON.parse(res);
    if (!data.journal) {
      notify('danger', 'Error', 'Journal not found.');
      return;
    }

    const j = data.journal;
    const lines = data.details;

    $('input[name="journal_date"]').val(j.journal_date);
    $('input[name="memo"]').val(j.memo);
    $('#journal_lines tbody').html('');
    $('#total_debit').val(j.total_debit);
    $('#total_credit').val(j.total_credit);

    lines.forEach((line, i) => {
      addJournalLine(line.ca_id);
      const $row = $('#journal_lines tbody tr').last();
      $row.find('select[name="category[]"]').val(line.ca_id).trigger('change');
      $row.find('input[name="description[]"]').val(line.description);
      $row.find('input[name="debit[]"]').val(line.debit);
      $row.find('input[name="credit[]"]').val(line.credit);
    });

    if ($('input[name="id"]').length === 0) {
      $('#journalForm').append(`<input type="hidden" name="id" value="${j.id}">`);
    } else {
      $('input[name="id"]').val(j.id);
    }

    // Enable form if journal is active
    if (j.status == 0) {
      $('#journalForm input, #journalForm select, #journalForm textarea').prop('disabled', true);
      $('#journalForm .modal-footer').hide();
      if (!$('#cancelledAlert').length) {
        $('.modal-body').prepend('<div id="cancelledAlert" class="alert alert-danger">This journal is cancelled and cannot be edited.</div>');
      }
    } else {
      $('#journalForm input, #journalForm select, #journalForm textarea').prop('disabled', false);
      $('#journalForm .modal-footer').show();
      $('#cancelledAlert').remove();
      if (!$('#deleteJournalBtn').length) {
        $('.modal-footer').prepend(`<button type="button" id="deleteJournalBtn" class="btn btn-danger mr-auto">Delete</button>`);
        $('#deleteJournalBtn').on('click', function() {
          Swal.fire({
            title: 'Are you sure?',
            text: "This will cancel the journal.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Yes, cancel it!'
          }).then((result) => {
            if (result.isConfirmed) {
              $.post('ajax/delete_journal.php', { id: j.id }, function(resp) {
                notify('success', 'Cancelled', resp);
                setTimeout(() => location.reload(), 1200);
              });
            }
          });
        });
      }
    }

    $('#journalModal').modal('show');
    
     
  });
 
}

$(document).ready(function() {
  openJournalModal();
  
    });
</script>

<?php include("footer.php"); ?>



