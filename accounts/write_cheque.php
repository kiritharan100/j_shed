<?php
include("header.php");
require_once __DIR__ . '/transaction_date_cookie.php';
$transaction_date = get_shared_transaction_date();

$payees = mysqli_query($con, "SELECT DISTINCT supplier_name as ca_name,sup_id FROM manage_supplier WHERE status=1 ORDER BY supplier_name");



$banks = mysqli_query($con, "SELECT ca_id, ca_name FROM acc_chart_of_accounts WHERE ca_group='Cash in Hand' AND ca_id != 44  ORDER BY ca_name ASC");
// $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-1');
$period_result = mysqli_query($con, "SELECT id, perid_from, period_to FROM accounts_accounting_period WHERE location_id = " . (int)$location_id . " AND status = 1 ORDER BY perid_from DESC");
$periods = [];
if ($period_result) while ($period_row = mysqli_fetch_assoc($period_result)) $periods[] = $period_row;
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
$period_selection = $period_cookie['selection'] ?? 'custom';
if (!$period_cookie && $latest_period) $period_selection = 'current';
if (!in_array($period_selection, ['custom', 'current'], true) && !preg_match('/^period_\d+$/', $period_selection)) $period_selection = 'custom';

?>

<style>
  #chequeFilterBar { gap: 7px !important; padding: 9px 10px; background: #f7f8fa; border: 1px solid #dee2e6; border-radius: 4px; }
  #chequeFilterBar label { margin: 0; font-size: 13px; font-weight: 600; white-space: nowrap; }
  #chequeFilterBar .form-control { height: 32px; padding: 4px 8px; font-size: 13px; }
  #chequeFilterBar #chequePeriod { width: 205px !important; }
  #chequeFilterBar #chequeStartDate, #chequeFilterBar #chequeEndDate { width: 145px; }
  #chequeFilterBar .btn { height: 32px; padding: 4px 11px; font-size: 13px; line-height: 22px; }
  #example { width: 100% !important; margin-bottom: 0; font-size: 14px; }
  #example thead th { padding: 7px 8px; font-size: 13px; font-weight: 600; white-space: nowrap; vertical-align: middle; }
  #example tbody tr { height: 25px !important; }
  #example tbody td { padding: 1px 5px !important; height: 25px !important; font-size: 13px; line-height: 1 !important; vertical-align: middle; }
  #example select.cheque-action.form-control.form-control-sm { min-width: 100px; width: 100px; height: 21px !important; min-height: 21px !important; padding: 0 4px !important; color: #333; background-color: #fff !important; border: 1px solid #ced4da; border-radius: 3px; font-size: 11px; line-height: 1 !important; }
  #example .cheque-action:focus { background-color: #fff; border-color: #80bdff; outline: 0; box-shadow: 0 0 0 2px rgba(0,123,255,.15); }
  #chequeTotalPayments { font-size: 14px; }
  @media (max-width: 767px) {
    #chequeFilterBar #chequePeriod { width: 100% !important; }
    #chequeFilterBar #chequeStartDate, #chequeFilterBar #chequeEndDate { flex: 1 1 130px; }
  }
</style>

<div class="content-wrapper">
  <div class="container-fluid">
    <div class="main-header">
      <h5>ACCOUNTS | <?php echo $client_name; ?> | CHEQUES</h5>
    </div>

    <div class="row">
      <div class="col-md-12">
        <div class="card">
          <div class="card-block">
            <div id="chequeFilterBar" class="d-flex flex-wrap align-items-center mb-3">
                <button type="button" class="btn btn-success" onclick="openChequeModal()">+ Write Cheque</button>
                <label for="chequePeriod" class="mb-0">Period</label>
                <select id="chequePeriod" class="form-control" style="width:220px;">
                    <option value="custom" <?= $period_selection === 'custom' ? 'selected' : '' ?>>Custom</option>
                    <?php if ($latest_period): ?>
                    <option value="current" data-start="<?= htmlspecialchars($latest_period['perid_from']) ?>" data-end="<?= htmlspecialchars($latest_period['period_to']) ?>" <?= $period_selection === 'current' ? 'selected' : '' ?>>Current <?= date('d/m/Y', strtotime($latest_period['perid_from'])) ?> - <?= date('d/m/Y', strtotime($latest_period['period_to'])) ?></option>
                    <?php endif; ?>
                    <?php foreach ($periods as $period): if ($latest_period && (int)$period['id'] === (int)$latest_period['id']) continue; $period_value = 'period_' . (int)$period['id']; ?>
                    <option value="<?= $period_value ?>" data-start="<?= htmlspecialchars($period['perid_from']) ?>" data-end="<?= htmlspecialchars($period['period_to']) ?>" <?= $period_selection === $period_value ? 'selected' : '' ?>><?= date('d/m/Y', strtotime($period['perid_from'])) ?> - <?= date('d/m/Y', strtotime($period['period_to'])) ?></option>
                    <?php endforeach; ?>
                </select>
                <label for="chequeStartDate" class="mb-0">From</label>
                <input type="date" id="chequeStartDate" class="form-control" value="<?= htmlspecialchars($start_date) ?>">
                <label for="chequeEndDate" class="mb-0">To</label>
                <input type="date" id="chequeEndDate" class="form-control" value="<?= htmlspecialchars($end_date) ?>">
                <button type="button" id="chequeFilter" class="btn btn-primary">Filter</button>
                <button type='button' id="exportButton" filename='Cheque_Payment_<?= htmlspecialchars($start_date . '_' . $end_date) ?>.xlsx' class="btn btn-primary"><i class="ti-cloud-down"></i> Export</button>
            </div>
              <!-- <button class="btn btn-success" data-toggle="modal" data-target="#chequeModal" onclick="openChequeModal()">+ Write Cheque</button> -->
            <hr>
                <table id="example" class="table table-bordered table-striped table-sm">
              <thead class="thead-dark">
                <tr>
                  <th>#</th>
                  <th>Ref No</th>
                  <th>Date</th>
                  <th>Cheque No</th>
                  <th>Paid From Account</th>
                  <th>Paid To</th>
                  <th>Amount</th>
                  <th>Status</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>

            <div class="text-right font-weight-bold mt-2">
              Total Payments: <span id="chequeTotalPayments">0.00</span>
            </div>

          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal -->
<div class="modal fade" id="chequeModal" tabindex="-1" role="dialog" aria-labelledby="chequeModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document" style=' max-width: 1140px;'> <!-- Correct: modal-xl -->
     
    <form method="post" id="chequeForm" class="processing_form">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="chequeModalLabel">Write Cheque</h5>
          <!-- <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button> -->
        </div>

        <div class="modal-body">
          <div class="row">
            <div class="col-md-4">
              <div class="form-group">
                <label>Payee</label>
                <select name="payee" id="payee" class="form-control select2_single"  >
                  <option value="">-- Select Payee --</option>
                  <?php while ($p = mysqli_fetch_assoc($payees)) echo "<option value=\"{$p['sup_id']}\">{$p['ca_name']}</option>"; ?>
                </select>
                <input type="hidden" name="location_id" value="<?= $location_id ?>">
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label>Bank Account</label>
                <select name="bank_account" id="bank_account" class="form-control" required>
                  <option value="">-- Select Bank --</option>
                  <?php while ($b = mysqli_fetch_assoc($banks)) echo "<option value=\"{$b['ca_id']}\" data-account-group=\"Cash in Hand\">{$b['ca_name']}</option>"; ?>
                </select>
              </div>
            </div>
            <div class="col-md-2">
              <div class="form-group">
                <label id="cheque_number_label" for="cheque_number">Cheque No.</label>
                <input type="text" id="cheque_number" name="cheque_number" class="form-control" required>
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label>Date</label>
                <input type="date" id="payment_date" name="payment_date" class="form-control" value="<?= htmlspecialchars($transaction_date) ?>" required>
              </div>
            </div>
          </div>
<br>
          <table class="table table-bordered" id="cheque_lines">
            <thead>
              <tr>
                <th width="5%">#</th>
                <th width="25%">Category</th>
                <th>Description</th>
                <th width="15%">Amount</th>
                <th width="5%"></th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>1</td>
                <td><select name="category[]" class="form-control select2_account"><option value=''>-- Select Account --</option></select></td>
                <td><input type="text" name="description[]" class="form-control"></td>
                <td><input style='text-align:right;' type="number" step="0.01" name="amount[]" class="form-control"></td>
                <td><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">&times;</button></td>
              </tr>
            </tbody>
          </table>

          <button type="button" class="btn btn-secondary btn-sm" onclick="addChequeLine()">+ Add Line</button>

          <div class="form-group mt-3">
            <label>Memo</label>
            <textarea name="memo" class="form-control"></textarea>
          </div>

        </div>
        <div class="modal-footer">
             <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-success processing_button">Save Cheque</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
$(document).ready(function() {
  loadAccounts();
});

function loadAccounts() {
  $.ajax({
    url: 'ajax/fetch_account_list.php',
    method: 'GET',
    success: function(response) {
      $('.select2_account').each(function() {
        const current = $(this).val();
        $(this).html(response).val(current).trigger('change');
      });
    }
  });
}

 function openChequeModal() {
  $('#chequeForm input[name="ch_id"]').remove();
  $('#chequeForm input, #chequeForm select, #chequeForm textarea').prop('disabled', false);
  $('#chequeForm .processing_button').show();
  $('#chequeForm .modal-footer').show();
  $('#deleteBtn, #cancelledAlert').remove();
  $('#chequeModalLabel').text('Write Cheque');
  // Clear form fields manually instead of form.reset()
//   $('#chequeForm').find("input[type=text], input[type=date], input[type=number], textarea").val('');
$('#chequeForm').find("input[type=text], input[type=number], textarea").val('');
  $('#chequeForm input[name="payment_date"]').val(<?= json_encode($transaction_date) ?>);
  $('#chequeForm').find("select").val('').trigger('change');

  // Reset lines
  $('#cheque_lines tbody').html(`<tr>
    <td>1</td>
    <td><select style='width:300px;' name='category[]' class='form-control select2_account'><option value=''>-- Select Account --</option></select></td>
    <td><input type='text' name='description[]' class='form-control'></td>
    <td><input type='number' style='text-align:right;' step='0.01' name='amount[]' class='form-control'></td>
    <td><button type='button' class='btn btn-danger btn-sm' onclick='removeRow(this)'>&times;</button></td>
  </tr>`);

  // Load accounts AFTER DOM is updated
  setTimeout(() => {
    loadAccounts();
    $('.select2_account').select2();
  }, 100);
$('#chequeModal').modal('show');
//   $('#chequeModal').modal('show');
}


function addChequeLine() {
  const index = $('#cheque_lines tbody tr').length + 1;
  const row = `<tr>
    <td>${index}</td>
    <td><select name='category[]' class='form-control select2_account'><option value=''>-- Select Account --</option></select></td>
    <td><input type='text' name='description[]' class='form-control'></td>
    <td><input type='number' step='0.01' name='amount[]' class='form-control'></td>
    <td><button type='button' class='btn btn-danger btn-sm' onclick='removeRow(this)'>&times;</button></td>
  </tr>`;
  $('#cheque_lines tbody').append(row);
  loadAccounts();
  //$('.select2_account').last().select2();
      $('.select2_account').last().select2({
      dropdownParent: $('#chequeModal')
      });
}

function removeRow(btn) {
  $(btn).closest('tr').remove();
  $('#cheque_lines tbody tr').each((i, tr) => $(tr).find('td:first').text(i + 1));
}

// function editCheque(id) {
//   alert('Edit cheque ID: ' + id);
// }

$('#chequeForm').on('submit', function(e) {
  e.preventDefault(); // Prevent default submission
  const form = this;
  const formData = new FormData(form);

  fetch('ajax/save_cheque.php', {
    method: 'POST',
    body: formData
  })
  .then(res => res.text())
  .then(data => {
    if (data.trim().startsWith('ERROR:')) {
      notify('danger', 'Transaction date rejected', data.trim().replace(/^ERROR:\s*/, ''));
      return;
    }
    notify('success', 'Saved', data);
    setTimeout(() => location.reload(), 1000);
  })
  .catch(err => {
    notify('danger', 'Error', err);
  });
});
$('#chequeModal').on('shown.bs.modal', function () {
  $('.select2_account').select2({
    dropdownParent: $('#chequeModal')
  });
});

let chequeTable;
function saveChequePeriodCookie() {
  const cookieValue = [$('#chequePeriod').val() || 'custom', $('#chequeStartDate').val(), $('#chequeEndDate').val()]
    .map(value => encodeURIComponent(value || '')).join('|');
  document.cookie = <?= json_encode($period_cookie_key) ?> + '=' + cookieValue + '; path=/; max-age=' + (180 * 86400) + '; SameSite=Lax';
}

function applyChequeFilter() {
  saveChequePeriodCookie();
  if (chequeTable) chequeTable.ajax.reload();
}

$(document).ready(function() {
  chequeTable = $('#example').DataTable({
    processing: true,
    serverSide: true,
    pageLength: 50,
    order: [[2, 'desc']],
    ajax: {
      url: 'ajax/list_cheques.php',
      type: 'POST',
      data: function(request) {
        request.start_date = $('#chequeStartDate').val();
        request.end_date = $('#chequeEndDate').val();
      },
      dataSrc: function(response) {
        $('#chequeTotalPayments').text(response.totalPayments || '0.00');
        return response.data || [];
      }
    },
    columns: [
      { data: 'index', orderable: false, searchable: false },
      { data: 'ref_no' },
      { data: 'date' },
      { data: 'cheque_no' },
      { data: 'from_account' },
      { data: 'paid_to' },
      { data: 'amount', className: 'text-right' },
      { data: 'status', orderable: false },
      { data: 'action', orderable: false, searchable: false }
    ],
    createdRow: function(row, data) {
      if (Number(data.status_value) === 0) $(row).addClass('table-danger');
    }
  });

  saveChequePeriodCookie();
  $('#chequePeriod').on('change', function() {
    const option = $(this).find(':selected');
    if (this.value !== 'custom') {
      $('#chequeStartDate').val(option.data('start'));
      $('#chequeEndDate').val(option.data('end'));
    }
    applyChequeFilter();
  });
  $('#chequeFilter').on('click', function() {
    $('#chequePeriod').val('custom');
    applyChequeFilter();
  });
  $('#chequeStartDate, #chequeEndDate').on('change', function() {
    $('#chequePeriod').val('custom');
    applyChequeFilter();
  });
  $('#exportButton').on('click', function() {
    const rows = chequeTable.rows({ search: 'applied' }).data().toArray();
    const values = [['#', 'Ref No', 'Date', 'Cheque No', 'Paid From Account', 'Paid To', 'Amount', 'Status'], ...rows.map(row => [row.index, row.ref_no, row.date, row.cheque_no, row.from_account, row.paid_to, row.amount, row.status])];
    if (!window.XLSX) {
      notify('danger', 'Export unavailable', 'The spreadsheet library did not load.');
      return;
    }
    const worksheet = XLSX.utils.aoa_to_sheet(values);
    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, worksheet, 'Cheques');
    XLSX.writeFile(workbook, 'Cheque_Payment_' + $('#chequeStartDate').val() + '_' + $('#chequeEndDate').val() + '.xlsx');
  });
});

$(document).on('change', '.cheque-action', function() {
  const action = this.value;
  const id = Number($(this).data('id'));
  const status = Number($(this).data('status'));
  $(this).val('');
  if (action === 'view') editCheque(id, true);
  if (action === 'edit' && status === 1) editCheque(id, false);
  if (action === 'cancel' && status === 1) {
    const confirmCancel = window.Swal && typeof window.Swal.fire === 'function'
      ? window.Swal.fire({ title: 'Cancel cheque?', text: 'This will cancel the cheque.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes, cancel it' }).then(result => result.isConfirmed)
      : Promise.resolve(window.confirm('Cancel this cheque?'));
    confirmCancel.then(function(confirmed) {
      if (!confirmed) return;
      $.post('ajax/delete_cheque.php', { ch_id: id }, function(message) {
        notify('success', 'Cancelled', message);
        chequeTable.ajax.reload(null, false);
      }).fail(function() { notify('danger', 'Error', 'Could not cancel this cheque.'); });
    });
  }
});


$('#payee').on('change', function () {
  const payeeSelected = $(this).val();
  if (payeeSelected) {
    // Set first row's category to Trade Creditors (ca_id = 9)
    const firstCategory = $('#cheque_lines tbody select.select2_account').first();
    firstCategory.val('9').trigger('change');
  }
});

function updateChequeNumberLabel() {
  const isCashInHand = $('#bank_account option:selected').data('account-group') === 'Cash in Hand';
  $('#cheque_number_label').text(isCashInHand ? 'Voucher Number' : 'Cheque No.');
  $('#cheque_number').prop('required', !isCashInHand);
}

$('#bank_account').on('change', function () {
  const bankId = $(this).val();
  updateChequeNumberLabel();

  if (bankId) {
    $.ajax({
      url: 'ajax/get_next_cheque_number.php',
      method: 'GET',
      data: { bank_id: bankId },
      success: function (response) {
        $('#cheque_number').val(response);
      }
    });
  } else {
    $('#cheque_number').val('');
  }
});

</script>

<script>
  //edit cheque 

  function editCheque(ch_id, readOnly = false) {
  $('#chequeForm input[name="ch_id"]').remove();
  $('#chequeForm input, #chequeForm select, #chequeForm textarea').prop('disabled', false);
  $('#chequeForm .processing_button').show();
  $('#chequeForm .modal-footer').show();
  $('#deleteBtn, #cancelledAlert').remove();
  $.ajax({
    url: 'ajax/get_cheque_details.php',
    method: 'GET',
    dataType: 'json',
    data: { ch_id },
    success: function(response) {
      const data = response;

      if (!data || !data.cheque) {
        notify('danger', 'Error', 'Cheque not found.');
        return;
      }

      const cheque = data.cheque;
      const lines = data.details;
      const isCancelled = cheque.status == 0;
      $('#chequeModalLabel').text(readOnly ? 'View Cheque' : 'Edit Cheque');

      // Fill form fields
      $('#cheque_number').val(cheque.cheque_number);
      $('#payment_date').val(cheque.ch_date);
      $('#bank_account').val(cheque.cr_acc_id).trigger('change');
      $('#payee').val(cheque.supplier_id).trigger('change');
      $('textarea[name="memo"]').val(cheque.memo);

      // Render lines
      $('#cheque_lines tbody').html('');
      lines.forEach((line, i) => {
        const row = `<tr>
          <td>${i + 1}</td>
          <td><select name='category[]' class='form-control select2_account'><option value=''>-- Select Account --</option></select></td>
          <td><input type='text' name='description[]' class='form-control' value='${line.description}'></td>
          <td><input type='number' style='text-align:right;' step='0.01' name='amount[]' class='form-control' value='${line.amount}'></td>
          <td><button type='button' class='btn btn-danger btn-sm' onclick='removeRow(this)'>&times;</button></td>
        </tr>`;
        $('#cheque_lines tbody').append(row);
      });
      loadAccounts();
      setTimeout(() => {
        $('.select2_account').each((index, el) => {
          $(el).val(lines[index].dr_acc_id).trigger('change');
        });
      }, 150);

      $('<input>', { type: 'hidden', name: 'ch_id', value: cheque.ch_id }).appendTo('#chequeForm');

      if (isCancelled || readOnly) {
        $('#chequeForm input, #chequeForm select, #chequeForm textarea').prop('disabled', true);
        $('#chequeForm .processing_button').hide();
        if (isCancelled && !readOnly && !$('#cancelledAlert').length) {
          $('.modal-body').prepend('<div id="cancelledAlert" class="alert alert-danger">This cheque is cancelled and cannot be edited.</div>');
        }
      } else {
        $('#chequeForm input, #chequeForm select, #chequeForm textarea').prop('disabled', false);
        $('#chequeForm .processing_button').show();
      }

      $('#chequeModal').modal('show');
    },
    error: function(xhr) {
      const response = xhr.responseJSON || {};
      notify('danger', 'Error', response.error || 'Could not load cheque details.');
    }
  });
}


  </script>
 


<?php include("footer.php"); ?>
