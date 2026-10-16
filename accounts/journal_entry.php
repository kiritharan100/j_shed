<?php include("header.php");
require_once __DIR__ . '/transaction_date_cookie.php';
$transaction_date = get_shared_transaction_date();
$period_query = mysqli_query($con, "SELECT id, perid_from, period_to FROM accounts_accounting_period WHERE location_id = " . (int)$location_id . " AND status = 1 ORDER BY perid_from DESC");
$periods = [];
if ($period_query) while ($period_row = mysqli_fetch_assoc($period_query)) $periods[] = $period_row;
$latest_period = $periods[0] ?? null;
$period_cookie_key = 'acc_period_' . (int)$location_id;
$period_cookie = null;
if (!empty($_COOKIE[$period_cookie_key])) {
  $parts = explode('|', (string)$_COOKIE[$period_cookie_key]);
  if (count($parts) >= 3) $period_cookie = ['selection' => urldecode($parts[0]), 'start' => urldecode($parts[1]), 'end' => urldecode($parts[2])];
}
$start_date = $_GET['start_date'] ?? ($period_cookie['start'] ?? ($latest_period['perid_from'] ?? date('Y-m-d', strtotime('-30 days'))));
$end_date = $_GET['end_date'] ?? ($period_cookie['end'] ?? ($latest_period['period_to'] ?? date('Y-m-d')));
$start_date = date('Y-m-d', strtotime($start_date));
$end_date = date('Y-m-d', strtotime($end_date));
$period_selection = $period_cookie['selection'] ?? ($latest_period ? 'current' : 'custom');
if (!in_array($period_selection, ['custom', 'current'], true) && !preg_match('/^period_\d+$/', $period_selection)) $period_selection = 'custom';

?>

<style>
  #example tbody tr { height: 25px !important; }
  #example tbody td { padding: 1px 5px !important; height: 25px !important; font-size: 13px; line-height: 1 !important; vertical-align: middle; }
  #example select.journal-action.form-control.form-control-sm { min-width: 100px; width: 100px; height: 21px !important; min-height: 21px !important; padding: 0 4px !important; color: #333; background-color: #fff !important; border: 1px solid #ced4da; border-radius: 3px; font-size: 11px; line-height: 1 !important; }
</style>

<div class="content-wrapper">
  <div class="container-fluid">
    <div class="main-header"><h5>Accounts | Journal Entries</h5></div>

    <div class="row">
      <div class="col-md-12">
        <div class="card card-block">
          <form id="journalFilterForm" class="form-inline mb-3" onsubmit="return false;" style="gap:7px;">
            <button type="button" class="btn btn-success btn-sm" onclick="openJournalModal()">+ Add Journal</button>
            <button type="button" id="journalExportButton" class="btn btn-primary btn-sm"><i class="ti-cloud-down"></i> Export</button>
            <label for="accountingPeriodSelect" class="mb-0">Period</label>
            <select id="accountingPeriodSelect" class="form-control form-control-sm" style="width:220px;">
              <option value="custom" <?= $period_selection === 'custom' ? 'selected' : '' ?>>Custom</option>
              <?php if ($latest_period): ?>
              <option value="current" data-start="<?= htmlspecialchars($latest_period['perid_from']) ?>" data-end="<?= htmlspecialchars($latest_period['period_to']) ?>" <?= $period_selection === 'current' ? 'selected' : '' ?>>Current <?= date('d/m/Y', strtotime($latest_period['perid_from'])) ?> - <?= date('d/m/Y', strtotime($latest_period['period_to'])) ?></option>
              <?php endif; ?>
              <?php foreach ($periods as $period): if ($latest_period && (int)$period['id'] === (int)$latest_period['id']) continue; $period_value = 'period_' . (int)$period['id']; ?>
              <option value="<?= $period_value ?>" data-start="<?= htmlspecialchars($period['perid_from']) ?>" data-end="<?= htmlspecialchars($period['period_to']) ?>" <?= $period_selection === $period_value ? 'selected' : '' ?>><?= date('d/m/Y', strtotime($period['perid_from'])) ?> - <?= date('d/m/Y', strtotime($period['period_to'])) ?></option>
              <?php endforeach; ?>
            </select>
            <label for="journalStartDate" class="mb-0">From</label>
            <input type="date" id="journalStartDate" class="form-control form-control-sm" value="<?= htmlspecialchars($start_date) ?>">
            <label for="journalEndDate" class="mb-0">To</label>
            <input type="date" id="journalEndDate" class="form-control form-control-sm" value="<?= htmlspecialchars($end_date) ?>">
            <button type="button" id="journalFilterButton" class="btn btn-primary btn-sm">Filter</button>
            <label class="mb-0 ml-2"><input type="checkbox" id="showDeletedJournals"> Show Cancelled</label>
          </form><hr>
          <table id="example" class="table table-bordered table-sm">
            <thead class="thead-dark">
              <tr>
                <th>#</th><th>Journal Date</th><th>Ref No</th><th>Memo</th><th>Amount</th><th>Status</th><th>Action</th>
              </tr>
            </thead>
            <tbody></tbody>
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
        <div class="modal-header"><h5 class="modal-title" id="journalModalTitle">Journal Entry</h5></div>
        <div class="modal-body">
          <div class="row mb-2">
            <div class="col-md-3"><label>Date</label><input type="date" name="journal_date" class="form-control" value="<?= htmlspecialchars($transaction_date) ?>"></div>
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
  $('#journalForm input, #journalForm select, #journalForm textarea').prop('disabled', false);
  $('#journalForm .processing_button').show();
  $('#journalForm .modal-footer').show();
  $('#deleteJournalBtn, #cancelledAlert').remove();
  $('#journalModalTitle').text('Journal Entry');
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



function ensureJournalAccounts(callback) {
  if (accountOptions.trim() !== '') {
    callback();
    return;
  }
  $.get('ajax/fetch_account_list.php', function(response) {
    accountOptions = response || '';
    callback();
  }).fail(function() {
    notify('danger', 'Error', 'Could not load accounts for this journal.');
  });
}

function editJournal(id, readOnly = false) {
  ensureJournalAccounts(function() {
    $.getJSON('ajax/get_journal_details.php', { id }, function(data) {
    if (!data.journal) {
      notify('danger', 'Error', 'Journal not found.');
      return;
    }

    const j = data.journal;
    const lines = data.details;
    $('#journalModalTitle').text(readOnly ? 'View Journal' : 'Edit Journal');
    $('#journalForm input, #journalForm select, #journalForm textarea').prop('disabled', false);
    $('#journalForm .processing_button').show();
    $('#journalForm .modal-footer').show();
    $('#deleteJournalBtn, #cancelledAlert').remove();

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

    // Cancelled entries and view mode are read-only.
    if (j.status == 0 || readOnly) {
      $('#journalForm input, #journalForm select, #journalForm textarea').prop('disabled', true);
      $('#journalForm .processing_button').hide();
      if (j.status == 0 && !readOnly && !$('#cancelledAlert').length) {
        $('.modal-body').prepend('<div id="cancelledAlert" class="alert alert-danger">This journal is cancelled and cannot be edited.</div>');
      }
    } else {
      $('#journalForm input, #journalForm select, #journalForm textarea').prop('disabled', false);
      $('#journalForm .processing_button').show();
    }

    $('#journalModal').modal('show');
    }).fail(function() {
      notify('danger', 'Error', 'Could not load journal details.');
    });
  });
}

let journalTable;
function saveJournalPeriodCookie() {
  const values = [$('#accountingPeriodSelect').val() || 'custom', $('#journalStartDate').val(), $('#journalEndDate').val()]
    .map(value => encodeURIComponent(value || '')).join('|');
  document.cookie = <?= json_encode($period_cookie_key) ?> + '=' + values + '; path=/; max-age=' + (180 * 86400) + '; SameSite=Lax';
}

function reloadJournalTable() {
  saveJournalPeriodCookie();
  if (journalTable) journalTable.ajax.reload();
}

$(function() {
  journalTable = $('#example').DataTable({
    processing: true,
    serverSide: true,
    searching: true,
    pageLength: 25,
    lengthMenu: [[25, 50, 100, 500], [25, 50, 100, 500]],
    order: [[1, 'desc']],
    ajax: {
      url: 'ajax/list_journals.php',
      type: 'POST',
      data: function(request) {
        request.start_date = $('#journalStartDate').val();
        request.end_date = $('#journalEndDate').val();
        request.show_deleted = $('#showDeletedJournals').is(':checked') ? 1 : 0;
      },
      error: function(xhr) {
        const response = xhr.responseJSON || {};
        notify('danger', 'Journal list error', response.error || 'Could not load journal entries.');
      }
    },
    columns: [
      { data: 'index', orderable: false, searchable: false },
      { data: 'journal_date' },
      { data: 'ref_no' },
      { data: 'memo' },
      { data: 'amount', className: 'text-right' },
      { data: 'status', orderable: false },
      { data: 'action', orderable: false, searchable: false }
    ],
    createdRow: function(row, data) {
      if (Number(data.status_value) === 0) $(row).addClass('table-danger');
    }
  });

  saveJournalPeriodCookie();
  $('#accountingPeriodSelect').on('change', function() {
    const option = $(this).find(':selected');
    if (this.value !== 'custom') {
      $('#journalStartDate').val(option.data('start'));
      $('#journalEndDate').val(option.data('end'));
    }
    reloadJournalTable();
  });
  $('#journalFilterButton').on('click', function() {
    $('#accountingPeriodSelect').val('custom');
    reloadJournalTable();
  });
  $('#journalStartDate, #journalEndDate').on('change', function() {
    $('#accountingPeriodSelect').val('custom');
    reloadJournalTable();
  });
  $('#showDeletedJournals').on('change', reloadJournalTable);
  $('#journalExportButton').on('click', function() {
    const rows = journalTable.rows({ search: 'applied' }).data().toArray();
    const values = [['#', 'Journal Date', 'Ref No', 'Memo', 'Amount', 'Status'], ...rows.map(row => [row.index, row.journal_date, row.ref_no, row.memo, row.amount, row.status])];
    if (!window.XLSX) { notify('danger', 'Export unavailable', 'The spreadsheet library did not load.'); return; }
    const sheet = XLSX.utils.aoa_to_sheet(values);
    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, sheet, 'Journals');
    XLSX.writeFile(workbook, 'Journal_list_' + $('#journalStartDate').val() + '_' + $('#journalEndDate').val() + '.xlsx');
  });
});

$(document).on('change', '.journal-action', function() {
  const action = this.value;
  const id = Number($(this).data('id'));
  const status = Number($(this).data('status'));
  $(this).val('');
  if (action === 'view') editJournal(id, true);
  if (action === 'edit' && status === 1) editJournal(id, false);
  if (action === 'cancel' && status === 1) {
    const confirmation = window.Swal && typeof window.Swal.fire === 'function'
      ? window.Swal.fire({ title: 'Cancel journal?', text: 'This will cancel the journal entry.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes, cancel it' }).then(result => result.isConfirmed)
      : Promise.resolve(window.confirm('Cancel this journal entry?'));
    confirmation.then(function(confirmed) {
      if (!confirmed) return;
      $.post('ajax/delete_journal.php', { id: id }, function(message) {
        notify('success', 'Cancelled', message);
        journalTable.ajax.reload(null, false);
      }).fail(function() { notify('danger', 'Error', 'Could not cancel this journal.'); });
    });
  }
});
</script>

<?php include("footer.php"); ?>



