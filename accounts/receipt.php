<?php
include("header.php");
require_once __DIR__ . '/transaction_date_cookie.php';
$transaction_date = get_shared_transaction_date();

$payees = mysqli_query($con, "SELECT DISTINCT customer_name as ca_name,c_id FROM mange_customer WHERE status=1 AND c_id > 3 ORDER BY customer_name");



$banks = mysqli_query($con, "SELECT ca_id, ca_name FROM acc_chart_of_accounts WHERE ca_group='Cash in Hand' AND ca_id <> '44' ORDER BY ca_name ASC");
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
$is_customer_advance = isset($_REQUEST['customer_advance']);

?>

<style>
  #receiptFilterForm label { font-size: 13px; font-weight: 600; white-space: nowrap; }
  #receiptFilterForm .form-control { height: 32px; padding: 4px 8px; font-size: 13px; }
  #receiptFilterForm #receiptPeriod { width: 205px !important; }
  #receiptFilterForm #receiptStartDate, #receiptFilterForm #receiptEndDate { width: 145px; }
  #receiptFilterForm .btn { height: 32px; padding: 4px 11px; font-size: 13px; line-height: 22px; }
  #example tbody tr { height: 25px !important; }
  #example tbody td { padding: 1px 5px !important; height: 25px !important; font-size: 13px; line-height: 1 !important; vertical-align: middle; }
  #example select.receipt-action.form-control.form-control-sm { min-width: 100px; width: 100px; height: 21px !important; min-height: 21px !important; padding: 0 4px !important; color: #333; background: #fff !important; border: 1px solid #ced4da; border-radius: 3px; font-size: 11px; line-height: 1 !important; }
</style>

<div class="content-wrapper">
  <div class="container-fluid">
    <div class="main-header">
      <h5>ACCOUNTS | <?php echo $client_name; ?> | <?= $is_customer_advance ? 'CUSTOMER ADVANCES' : 'RECEIPTS' ?></h5>
    </div>

    <div class="row">
      <div class="col-md-12">
        <div class="card">
          <div class="card-block">
            <div id="receiptFilterForm" class="d-flex flex-wrap align-items-center mb-3" style="gap:7px;padding:9px 10px;background:#f7f8fa;border:1px solid #dee2e6;border-radius:4px;">
              <button type="button" class="btn btn-success btn-sm" onclick="openChequeModal()">+ Record <?= $is_customer_advance ? 'Customer Advance' : 'Receipt' ?></button>
              <label for="receiptPeriod" class="mb-0">Period</label>
              <select id="receiptPeriod" class="form-control form-control-sm">
                <option value="custom" <?= $period_selection === 'custom' ? 'selected' : '' ?>>Custom</option>
                <?php if ($latest_period): ?><option value="current" data-start="<?= htmlspecialchars($latest_period['perid_from']) ?>" data-end="<?= htmlspecialchars($latest_period['period_to']) ?>" <?= $period_selection === 'current' ? 'selected' : '' ?>>Current <?= date('d/m/Y', strtotime($latest_period['perid_from'])) ?> - <?= date('d/m/Y', strtotime($latest_period['period_to'])) ?></option><?php endif; ?>
                <?php foreach ($periods as $period): if ($latest_period && (int)$period['id'] === (int)$latest_period['id']) continue; $period_value = 'period_' . (int)$period['id']; ?>
                <option value="<?= $period_value ?>" data-start="<?= htmlspecialchars($period['perid_from']) ?>" data-end="<?= htmlspecialchars($period['period_to']) ?>" <?= $period_selection === $period_value ? 'selected' : '' ?>><?= date('d/m/Y', strtotime($period['perid_from'])) ?> - <?= date('d/m/Y', strtotime($period['period_to'])) ?></option>
                <?php endforeach; ?>
              </select>
              <label for="receiptStartDate" class="mb-0">From</label>
              <input type="date" id="receiptStartDate" class="form-control form-control-sm" value="<?= htmlspecialchars($start_date) ?>">
              <label for="receiptEndDate" class="mb-0">To</label>
              <input type="date" id="receiptEndDate" class="form-control form-control-sm" value="<?= htmlspecialchars($end_date) ?>">
              <button type="button" id="receiptFilterButton" class="btn btn-primary btn-sm">Filter</button>
              <label class="mb-0 ml-2"><input type="checkbox" id="showCancelledReceipts"> Show Cancelled</label>
              <button type="button" id="exportButton" filename="Receipts.xlsx" class="btn btn-primary btn-sm"><i class="ti-cloud-down"></i> Export</button>
            </div>
            <hr>
                <table id="example" class="table table-bordered table-striped table-sm">
              <thead class="thead-dark">
                <tr>
                  <th>#</th>
                  <th>Ref No</th>
                  <th>Date</th>
                  <th>Cheque No</th>
                  <th>Debit account</th>
                  <th>Credit Account</th>
                  <th>Customer</th>
                  <th>Amount</th>
                  <th>Status</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>

            <div class="text-right font-weight-bold mt-2" id="receiptTotal">
              Total <?= $is_customer_advance ? 'Customer Advances' : 'Receipts' ?>: 0.00
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
          <h5 class="modal-title" id="chequeModalLabel">Record   <?php if(isset($_REQUEST['customer_advance'])){ echo "Customer Advance";} else { echo "Receipt";} ?></h5>
          <!-- <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button> -->
        </div>

        <div class="modal-body">
          <div class="row">
            <div class="col-md-4" id="payeeGroup" <?= isset($_REQUEST['customer_advance']) ? '' : 'style="display:none"' ?>>
              <div class="form-group">
                <label>Payee</label>
                <select name="payee" id="payee" class="form-control select2_single" <?= isset($_REQUEST['customer_advance']) ? 'required' : '' ?>>
                  <option value="">-- Select Payee --</option>
                  <?php while ($p = mysqli_fetch_assoc($payees)) echo "<option value=\"{$p['c_id']}\">{$p['ca_name']}</option>"; ?>
                </select>
                <input type="hidden" name="location_id" value="<?= $location_id ?>">
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label>Bank Account (Debit Account)</label>
                <select name="bank_account" id="bank_account" class="form-control" required>
                  <option value="">-- Select Bank --</option>
                  <?php while ($b = mysqli_fetch_assoc($banks)) echo "<option value=\"{$b['ca_id']}\">{$b['ca_name']}</option>"; ?>
                </select>
              </div>
            </div>
            <div class="col-md-2">
              <div class="form-group">
                <label>Cheque No.</label>
                <input type="text" id="cheque_number" name="cheque_number" class="form-control"  >
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
                <th width="25%">Category (Credit Account)</th>
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
                <td><input style='text-align:right;' type="number" required step="0.01" name="amount[]" class="form-control"></td>
                <td><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">&times;</button></td>
              </tr>
            </tbody>
          </table>

          <button type="button" disabled class="btn btn-secondary btn-sm" onclick="addChequeLine()">+ Add Line</button>

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




  $(document).ready(function () {
  bindAccountChangeListener(); // For any pre-loaded dropdown
});

$(document).ready(function() {
  loadAccounts();
});




 
  var isCustomerAdvance = <?= isset($_REQUEST['customer_advance']) ? 'true' : 'false' ?>;
 

function loadAccounts() {
  $.ajax({
    url: 'ajax/fetch_account_list.php',
    method: 'GET',
    success: function (response) {
      $('.select2_account').each(function () {
        /* inject fresh options */
        $(this).html(response);

        /* --------------------------
           CUSTOMER-ADVANCE BEHAVIOUR
           -------------------------- */
        if (isCustomerAdvance) {
          /* FORCE 44 */
          $(this).val('44');

          /* -----------------------------------
             EITHER   A)  remove other options
             --------------------------------- */
          // $(this).find('option:not([value="44"])').remove();

          /* -----------------------------------
             OR       B)  keep but disable them
             --------------------------------- */
          $(this)
            .find('option:not([value="44"])')
            .prop('disabled', true);

          /* OPTIONAL: freeze the whole box
           * (prevents user opening the Select2 dropdown) */
          // $(this).prop('disabled', true);
        }

        /* tell Select2 to refresh itself */
        if ($(this).hasClass('select2-hidden-accessible')) {
          $(this).trigger('change.select2');   // quickest refresh
        } else {
          $(this).select2();                   // first-time init
        }

        /* fire standard change event so Payee logic runs */
        $(this).trigger('change');
      });

      bindAccountChangeListener(); // (your existing listener)
    }
  });
}


// function loadAccounts() {
//   $.ajax({
//     url: 'ajax/fetch_account_list.php',
//     method: 'GET',
//     success: function(response) {
//       $('.select2_account').each(function() {
//         let selectedValue = $(this).val();

//         // If customer advance and nothing already selected, force 44
//         if (isCustomerAdvance && (!selectedValue || selectedValue === '')) {
//           selectedValue = '44';
//         }

//         $(this).html(response).val(selectedValue).trigger('change');

//         // If you're using Select2 plugin, reinitialize it
//         if ($(this).hasClass('select2-hidden-accessible')) {
//           $(this).select2();
//         }
//       });

//       bindAccountChangeListener();
//     }
//   });
// }



// function loadAccounts() {
//   $.ajax({
//     url: 'ajax/fetch_account_list.php',
//     method: 'GET',
//     success: function(response) {
//       $('.select2_account').each(function() {
//         const current = $(this).val();
        
             
//         $(this).html(response).val(current).trigger('change');
//       });
//       bindAccountChangeListener();
//     }
//   });
// }

function bindAccountChangeListener() {
  if (isCustomerAdvance) {
    $('#payeeGroup').show();
    $('#payee').prop('disabled', false).attr('required', true);
    return;
  }
  $(document).off('change', '.select2_account').on('change', '.select2_account', function () {
    const customerRequired = $('.select2_account').toArray().some(el => $(el).val() == '44');
    $('#payeeGroup').toggle(customerRequired);
    $('#payee').prop('disabled', !customerRequired);
    if (customerRequired) $('#payee').attr('required', true);
    else $('#payee').val('').trigger('change').removeAttr('required');
  });
}


 function openChequeModal() {
  // Clear form fields manually instead of form.reset()
//   $('#chequeForm').find("input[type=text], input[type=date], input[type=number], textarea").val('');
$('#chequeForm').find("input[type=text], input[type=number], textarea").val('');
  $('#chequeForm input[name="payment_date"]').val(<?= json_encode($transaction_date) ?>);
  $('#chequeForm').find("select").val('').trigger('change');
  $('#chequeForm input[name="re_id"]').remove();
  $('#chequeForm input, #chequeForm select, #chequeForm textarea').prop('disabled', false);
  $('#chequeForm .modal-footer').show();
  $('#cancelledAlert').remove();
  $('#deleteBtn').remove();
  $('#chequeModalLabel').text('Record ' + (isCustomerAdvance ? 'Customer Advance' : 'Receipt'));
  if (isCustomerAdvance) $('#payee').prop('disabled', false).attr('required', true);
  else $('#payee').prop('disabled', true).removeAttr('required');

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
  $('.select2_account').last().select2();
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

  // Check if at least one amount input has a value greater than 0
  let valid = false;
  $('input[name="amount[]"]').each(function() {
    const val = parseFloat($(this).val());
    if (!isNaN(val) && val > 0) {
      valid = true;
      return false; // Break loop
    }
  });

  if (!valid) {
    notify('danger', 'Validation Error', 'Please enter at least one valid amount.');
    return;
  }

  // If valid, proceed with submission
  const form = this;
  const formData = new FormData(form);

  fetch('ajax/save_receipt.php', {
    method: 'POST',
    body: formData
  })
  .then(res => res.text())
  .then(data => {
    notify('success', 'Saved', data);
    setTimeout(() => location.reload(), 1000);
  })
  .catch(err => {
    notify('danger', 'Error', err);
  });
});



// $('#chequeForm').on('submit', function(e) {
//   e.preventDefault(); // Prevent default submission
//   const form = this;
//   const formData = new FormData(form);

//   fetch('ajax/save_receipt.php', {
//     method: 'POST',
//     body: formData
//   })
//   .then(res => res.text())
//   .then(data => {
//     notify('success', 'Saved', data);
//     setTimeout(() => location.reload(), 1000);
//   })
//   .catch(err => {
//     notify('danger', 'Error', err);
//   });
// });


$('#chequeModal').on('shown.bs.modal', function () {
  $('.select2_account').select2({
    dropdownParent: $('#chequeModal')
  });
});

$('#chequeModal').on('shown.bs.modal', function () {
  $('.select2_single').select2({
    dropdownParent: $('#chequeModal')
  });
});

let receiptTable;
function saveReceiptPeriodCookie() {
  const values = [$('#receiptPeriod').val() || 'custom', $('#receiptStartDate').val(), $('#receiptEndDate').val()]
    .map(value => encodeURIComponent(value || '')).join('|');
  document.cookie = <?= json_encode($period_cookie_key) ?> + '=' + values + '; path=/; max-age=' + (180 * 86400) + '; SameSite=Lax';
}
function reloadReceiptTable(resetPaging = true) {
  saveReceiptPeriodCookie();
  if (receiptTable) receiptTable.ajax.reload(null, resetPaging);
}

$(document).ready(function() {
  receiptTable = $('#example').DataTable({
    processing: true,
    serverSide: true,
    pageLength: 50,
    order: [[2, 'desc']],
    ajax: {
      url: 'ajax/list_receipts.php',
      type: 'POST',
      data: function(request) {
        request.start_date = $('#receiptStartDate').val();
        request.end_date = $('#receiptEndDate').val();
        request.show_cancelled = $('#showCancelledReceipts').is(':checked') ? 1 : 0;
        request.customer_advance = <?= $is_customer_advance ? '1' : '0' ?>;
      },
      dataSrc: function(response) {
        $('#receiptTotal').text('Total <?= $is_customer_advance ? 'Customer Advances' : 'Receipts' ?>: ' + (response.totalReceipts || '0.00'));
        return response.data || [];
      },
      error: function(xhr) {
        const response = xhr.responseJSON || {};
        notify('danger', 'Receipt list error', response.error || 'Could not load receipts.');
      }
    },
    columns: [
      { data: 'index', orderable: false, searchable: false },
      { data: 'ref_no' },
      { data: 'date' },
      { data: 'cheque_no' },
      { data: 'from_account' },
      { data: 'credit_account' },
      { data: 'customer' },
      { data: 'amount', className: 'text-right' },
      { data: 'status', orderable: false },
      { data: 'action', orderable: false, searchable: false }
    ],
    createdRow: function(row, data) {
      if (Number(data.status_value) === 0) $(row).addClass('table-danger');
    }
  });
  saveReceiptPeriodCookie();
  $('#receiptPeriod').on('change', function() {
    const option = $(this).find(':selected');
    if (this.value !== 'custom') {
      $('#receiptStartDate').val(option.data('start'));
      $('#receiptEndDate').val(option.data('end'));
    }
    reloadReceiptTable();
  });
  $('#receiptFilterButton').on('click', function() {
    $('#receiptPeriod').val('custom');
    reloadReceiptTable();
  });
  $('#receiptStartDate, #receiptEndDate').on('change', function() {
    $('#receiptPeriod').val('custom');
    reloadReceiptTable();
  });
  $('#showCancelledReceipts').on('change', function() { reloadReceiptTable(false); });
  $('#exportButton').on('click', function() {
    $(this).attr('filename', '<?= $is_customer_advance ? 'Customer_Advances' : 'Receipts' ?>_' + $('#receiptStartDate').val() + '_' + $('#receiptEndDate').val() + '.xlsx');
  });
  $('.select2_single').select2({ width: '100%' });
});


// $('#payee').on('change', function () {
//   const payeeSelected = $(this).val();
//   if (payeeSelected) {
//     // Set first row's category to Trade Creditors (ca_id = 9)
//     const firstCategory = $('#cheque_lines tbody select.select2_account').first();
//     firstCategory.val('9').trigger('change');
//   }
// });

 
</script>

<script>
  //edit cheque 

  function editCheque(re_id, readOnly = false) {
  $.ajax({
    url: 'ajax/get_receipt_details.php',
    method: 'GET',
    dataType: 'json',
    data: { re_id },
    success: function(response) {
      const data = response;

      if (!data || !data.cheque) {
        notify('danger', 'Error', 'Receipt not found');
        return;
      }

      const cheque = data.cheque;
      const lines = data.details;
      const isCancelled = cheque.status == 0;
      const readOnlyMode = readOnly || isCancelled;

      // Fill form fields
      $('#cheque_number').val(cheque.cheque_number);
      $('#payment_date').val(cheque.ch_date);
      $('#bank_account').val(cheque.dr_acc_id).trigger('change');
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

      $('#chequeForm input[name="re_id"]').remove();
      $('#chequeForm').append(`<input type='hidden' name='re_id' value='${cheque.re_id}'>`);

      if (readOnlyMode) {
        $('#chequeForm input, #chequeForm select, #chequeForm textarea').prop('disabled', true);
        $('#chequeForm .modal-footer').hide();
        $('#cancelledAlert').remove();
        $('.modal-body').prepend('<div id="cancelledAlert" class="alert alert-info">' + (isCancelled ? 'This receipt is cancelled and is read-only.' : 'Receipt details (view only).') + '</div>');
      } else {
        $('#chequeForm input, #chequeForm select, #chequeForm textarea').prop('disabled', false);
        $('#chequeForm .modal-footer').show();
        $('#cancelledAlert').remove();
        $('#chequeModalLabel').text('Edit Receipt');
        if (!$('#deleteBtn').length) {
          $('.modal-footer').prepend(`<button type="button" id="deleteBtn" class="btn btn-danger mr-auto">Delete</button>`);
          $('#deleteBtn').on('click', function() {
            Swal.fire({
              title: 'Are you sure?',
              text: "This will delete the receipt.",
              icon: 'warning',
              showCancelButton: true,
              confirmButtonColor: '#d33',
              confirmButtonText: 'Yes, delete it'
            }).then((result) => {
              if (result.isConfirmed) {
                $.post('ajax/delete_receipt.php', { re_id }, function(resp) {
                  notify('success', 'Deleted', resp);
                  if (receiptTable) receiptTable.ajax.reload(null, false);
                });
              }
            });
          });
        }
      }

      if (isCustomerAdvance) $('#payeeGroup').show();
      else $('#payeeGroup').toggle($('.select2_account').toArray().some(el => $(el).val() == '44'));
      $('#chequeModalLabel').text(readOnlyMode ? (isCancelled ? 'View Cancelled Receipt' : 'View Receipt') : 'Edit Receipt');

      $('#chequeModal').modal('show');
    },
    error: function(xhr) {
      const response = xhr.responseJSON || {};
      notify('danger', 'Error', response.error || 'Could not load receipt details.');
    }
  });
}

$(document).on('change', '.receipt-action', function() {
  const action = this.value;
  const id = Number(this.dataset.id || 0);
  this.value = '';
  if (!id) return;
  if (action === 'view') editCheque(id, true);
  if (action === 'edit' && Number(this.dataset.status) === 1) editCheque(id, false);
  if (action === 'delete' && Number(this.dataset.status) === 1) {
    const confirmDelete = window.Swal
      ? window.Swal.fire({ title: 'Delete receipt?', text: 'This will mark the receipt as deleted.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes, delete it' }).then(result => result.isConfirmed)
      : Promise.resolve(window.confirm('Delete this receipt?'));
    confirmDelete.then(confirmed => {
      if (!confirmed) return;
      $.post('ajax/delete_receipt.php', { re_id: id }, function(resp) {
        notify('success', 'Deleted', resp);
        if (receiptTable) receiptTable.ajax.reload(null, false);
      }).fail(() => notify('danger', 'Error', 'Could not delete the receipt.'));
    });
  }
});


  </script>
 


<?php include("footer.php"); ?>
