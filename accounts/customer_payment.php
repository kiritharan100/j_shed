<?php
include("header.php");
require_once __DIR__ . '/transaction_date_cookie.php';
$transaction_date = get_shared_transaction_date();
$banks = mysqli_query($con, "SELECT ca_id, ca_name FROM acc_chart_of_accounts WHERE ca_group='Cash in Hand' ORDER BY ca_name ASC");
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
  #paymentFilterForm label { font-size: 13px; font-weight: 600; white-space: nowrap; }
  #paymentFilterForm .form-control { height: 32px; padding: 4px 8px; font-size: 13px; }
  #paymentFilterForm #paymentPeriod { width: 205px !important; }
  #paymentFilterForm #paymentStartDate, #paymentFilterForm #paymentEndDate { width: 145px; }
  #paymentFilterForm .btn { height: 32px; padding: 4px 11px; font-size: 13px; line-height: 22px; }
  #example tbody tr { height: 25px !important; }
  #example tbody td { padding: 1px 5px !important; height: 25px !important; font-size: 13px; line-height: 1 !important; vertical-align: middle; }
  #example select.payment-action.form-control.form-control-sm { width: 100px; height: 21px !important; min-height: 21px !important; padding: 0 4px !important; background: #fff !important; border: 1px solid #ced4da; border-radius: 3px; font-size: 11px; line-height: 1 !important; }
</style>

<div class="content-wrapper">
  <div class="container-fluid">
    <div class="main-header">
      <h4 class="mb-0">Customer Payment</h4>
    </div>

    <div class="card">
      <div class="card-block">
        <div id="paymentFilterForm" class="d-flex flex-wrap align-items-center mb-3" style="gap:7px;padding:9px 10px;background:#f7f8fa;border:1px solid #dee2e6;border-radius:4px;">
          <button type="button" class="btn btn-success btn-sm" onclick="openPaymentModal()">+ New Payment</button>
          <label for="paymentPeriod" class="mb-0">Period</label>
          <select id="paymentPeriod" class="form-control form-control-sm">
            <option value="custom" <?= $period_selection === 'custom' ? 'selected' : '' ?>>Custom</option>
            <?php if ($latest_period): ?><option value="current" data-start="<?= htmlspecialchars($latest_period['perid_from']) ?>" data-end="<?= htmlspecialchars($latest_period['period_to']) ?>" <?= $period_selection === 'current' ? 'selected' : '' ?>>Current <?= date('d/m/Y', strtotime($latest_period['perid_from'])) ?> - <?= date('d/m/Y', strtotime($latest_period['period_to'])) ?></option><?php endif; ?>
            <?php foreach ($periods as $period): if ($latest_period && (int)$period['id'] === (int)$latest_period['id']) continue; $period_value = 'period_' . (int)$period['id']; ?>
            <option value="<?= $period_value ?>" data-start="<?= htmlspecialchars($period['perid_from']) ?>" data-end="<?= htmlspecialchars($period['period_to']) ?>" <?= $period_selection === $period_value ? 'selected' : '' ?>><?= date('d/m/Y', strtotime($period['perid_from'])) ?> - <?= date('d/m/Y', strtotime($period['period_to'])) ?></option>
            <?php endforeach; ?>
          </select>
          <label for="paymentStartDate" class="mb-0">From</label>
          <input type="date" id="paymentStartDate" class="form-control form-control-sm" value="<?= htmlspecialchars($start_date) ?>">
          <label for="paymentEndDate" class="mb-0">To</label>
          <input type="date" id="paymentEndDate" class="form-control form-control-sm" value="<?= htmlspecialchars($end_date) ?>">
          <button type="button" id="paymentFilterButton" class="btn btn-primary btn-sm">Filter</button>
          <label class="mb-0 ml-2"><input type="checkbox" id="showCancelledPayments"> Show Cancelled</label>
        </div>
<hr>

         <table  id='example' class="table table-bordered table-hover">
          <thead>
            <tr>
              <th>#</th>
              <th>Receipt No</th>
              <th>Receipt Date</th>
              <th>Customer</th>
              <th>Cheque No</th>
              <th>Amount</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
        <div class="text-right font-weight-bold" id="paymentTotal">Total Payments: 0.00</div>
      </div>
    </div>
  </div>
</div>


<!-- View Modal -->

<div class="modal fade" id="viewModal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Customer Payment Details</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body" id="viewContent">
        <!-- Content loaded via AJAX -->
      </div>
    </div>
  </div>
</div>


<!-- Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-xl" role="document" style=' max-width: 900px;'>
    <div class="modal-content">
      <form id="paymentForm" class="processing_form">
        <input type="hidden" name="payment_id" id="payment_id" value="">
        <div class="modal-header">
        <h5 class="modal-title" id="paymentModalTitle">Record Customer Payment</h5>
          <!-- <button type="button" class="close" data-dismiss="modal">&times;</button> -->
        </div>
        <div class="modal-body">
          <div class="row">

           
            <div class="col-md-4">
              <label>Customer</label><br>
              <select name="customer_id" id="customer_id" class="form-control select2"  style='width:280px;'>
                <option value="">Select Customer</option>
              </select>
            </div>

            <div class="col-md-2">
            <label>Deposit To</label>
            <select name="ca_id" id="bank_account" class="form-control" disabled required>
                <option value="">-- Select Bank --</option>
                <?php
                while ($b = mysqli_fetch_assoc($banks)) {
                $selected = ($b['ca_id'] == 1) ? 'selected' : '';
                echo "<option value=\"{$b['ca_id']}\" $selected>{$b['ca_name']}</option>";
                }
                ?>
            </select>
            </div>

            <div class="col-md-2">
              <label>Receipt Date</label>
              <input type="date" name="rec_date" class="form-control" value="<?= htmlspecialchars($transaction_date) ?>">
            </div>

            <div class="col-md-2">
              <label>Cheque No</label>
              <input type="text" name="cheque_no" id="payment_cheque_no" class="form-control" value="" required>
              <input type="hidden" name="location_id" value="<?php echo $location_id;?>">
            </div>




          </div>

          <div class="row mt-3">
            <!-- <div class="col-md-6">
             ssssssssssssssss
            </div> -->
            <div class="col-md-6">
              <label>Memo</label>
              <input type="text" name="memo" class="form-control">
            </div>
          </div>

          <div class="row mt-4">
            <div class="col-md-12"> <hr>
              <table class="table table-bordered" id="invoiceTable">
        <thead>
  <tr>
    <th>#</th>
    <th>Invoice #</th>
    <th style='width:100px;'>Invoice Date</th>
    <th>Type</th>
    <th>Supply By</th> 
    <th>Order No</th> 
    <th>Vehicle No</th> 
 
    <th style='width:100px;'>Bill Amount</th>
    <th style='width:100px;'>Amount Due</th>
    <th style='width:150px;'>Pay Amount</th>
  </tr>
</thead>
                <tbody></tbody>
                <tfoot>
                  <tr>
                    <th colspan="9" class="text-right">Total</th>
                    <th><input type="text" name="total_paid" id="total_paid" class="form-control text-right" readonly></th>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-success processing_button1">Save</button>
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function viewReceipt(id) {
  $('#viewContent').html('<p class="text-center"><i class="fa fa-spinner fa-spin"></i> Loading...</p>');
  $('#viewModal').modal('show');
  $.getJSON('ajax/get_customer_payment_details.php', {id:id}).done(function(data) {
    const p=data.payment, esc=paymentEscape;
    let html=`<div class="table-responsive"><table class="table table-sm table-bordered"><tr><th>Receipt No</th><td>${esc(p.receipt_no)}</td><th>Date</th><td>${esc(p.rec_date)}</td></tr><tr><th>Customer</th><td>${esc(p.customer_name)}</td><th>Amount</th><td>${(parseFloat(p.rec_amount)||0).toFixed(2)}</td></tr><tr><th>Cheque No</th><td>${esc(p.cheque_no)}</td><th>Status</th><td>${parseInt(p.rec_status,10)===1?'Posted':'Cancelled'}</td></tr><tr><th>Memo</th><td colspan="3">${esc(p.Memo)}</td></tr></table><table class="table table-sm table-bordered"><thead><tr><th>Invoice</th><th>Type</th><th>Date</th><th>Bill</th><th>Paid</th></tr></thead><tbody>`;
    (data.details||[]).forEach(d=>{html+=`<tr><td>${esc(d.invoice_no)}</td><td>${esc(d.inv_type)}</td><td>${esc(d.invoice_date)}</td><td class="text-right">${(parseFloat(d.org_amount)||0).toFixed(2)}</td><td class="text-right">${(parseFloat(d.amount)||0).toFixed(2)}</td></tr>`;});
    html+='</tbody></table></div>'; $('#viewContent').html(html);
  }).fail(function(xhr) { $('#viewContent').html('<div class="alert alert-danger">' + paymentEscape(xhr.responseJSON?.error || 'Could not load payment details.') + '</div>'); });
}

function paymentEscape(value) { return $('<div>').text(value == null ? '' : String(value)).html(); }

function editPayment(id) {
  $.getJSON('ajax/get_customer_payment_details.php', {id: id}).done(function(data) {
    if (!data.payment) { notify('danger', 'Error', 'Payment details were not returned.'); return; }
    const p = data.payment;
    $('#paymentForm')[0].reset();
    $('#payment_id').val(p.cp_id);
    $('#paymentModalTitle').text('Edit Customer Payment');
    $('#customer_id').append(new Option(p.customer_name || 'Customer', p.c_id, true, true)).trigger('change.select2');
    $('#bank_account').val(p.ca_id).prop('disabled', false);
    updateChequeField();
    $('#paymentForm [name="rec_date"]').val(String(p.rec_date).substring(0, 10));
    $('#paymentForm [name="cheque_no"]').val(p.cheque_no || '');
    $('#paymentForm [name="memo"]').val(p.Memo || '');
    let html = '', total = 0;
    (data.details || []).forEach(function(d, i) {
      const amount = parseFloat(d.amount) || 0; total += amount;
      html += `<tr><td><input type="checkbox" class="pay_check" data-index="${i}" checked></td>
        <td>${paymentEscape(d.invoice_no)}<input type="hidden" name="inv_id[]" value="${parseInt(d.inv_id,10)}"><input type="hidden" name="loc_id[]" value="${parseInt(d.loc_id,10)||0}"><input type="hidden" name="inv_type[]" value="${paymentEscape(d.inv_type)}"></td>
        <td>${paymentEscape(d.invoice_date)}</td><td>${paymentEscape(d.inv_type)}</td><td>${paymentEscape(d.supply_by)}</td><td>${paymentEscape(d.order_no)}</td><td>${paymentEscape(d.vehicle_no)}</td>
        <td class="text-right">${(parseFloat(d.org_amount)||0).toFixed(2)}</td><td class="text-right">${(parseFloat(d.amount_due)||0).toFixed(2)}</td>
        <td><input type="text" name="amount[]" class="form-control text-right pay_input" data-index="${i}" value="${amount.toFixed(2)}"></td></tr>`;
    });
    $('#invoiceTable tbody').html(html); bindInvoiceInputs();
    $('#total_paid').val(total.toFixed(2)).removeAttr('max');
    if (String(p.ca_id) === '44') {
      $.post('cus_payment/get_customer_advance.php', {customer_id:p.c_id}, function(balance) {
        $('#total_paid').attr('max', ((parseFloat(balance)||0) + total).toFixed(2));
      });
    }
    $('.processing_button1').text('Update');
    $('#paymentModal').modal('show');
  }).fail(function(xhr) { notify('danger', 'Error', xhr.responseJSON?.error || 'Could not load payment for editing.'); });
}

function cancelPayment(id) {
  const proceed = function() {
    $.post('ajax/cancel_customer_payment.php', {cp_id: id}, function(res) {
      if (res.status === 'success') { notify('success', 'Cancelled', res.message); paymentTable.ajax.reload(null, false); }
      else notify('danger', 'Error', res.message || 'Could not cancel payment.');
    }, 'json').fail(function(xhr) { notify('danger', 'Error', xhr.responseJSON?.message || 'Could not cancel payment.'); });
  };
  if (window.Swal) Swal.fire({title:'Cancel payment?', text:'This will reverse the invoice allocations.', icon:'warning', showCancelButton:true, confirmButtonText:'Yes, cancel'}).then(r => { if (r.isConfirmed) proceed(); });
  else if (confirm('Cancel this payment and reverse its invoice allocations?')) proceed();
}
</script>

<script>
  function updateChequeField() {
    const accountName = ($('#bank_account option:selected').text() || '').trim().toLowerCase();
    const cashInHand = accountName.includes('cash in hand');
    $('#payment_cheque_no').prop('disabled', cashInHand).prop('required', !cashInHand);
    if (cashInHand) $('#payment_cheque_no').val('');
  }

  $('#bank_account').on('change', function () {
  updateChequeField();
  const selectedBank = $(this).val();
  const customerId = $('#customer_id').val();

  if (selectedBank == '44' && customerId) {
    // AJAX to get advance balance
    $.post('cus_payment/get_customer_advance.php', { customer_id: customerId }, function (advance) {
      $('#total_paid').val(parseFloat(advance).toFixed(2));
      $('#total_paid').prop('max', parseFloat(advance));
    });
  } else {
    $('#total_paid').val('').removeAttr('max');
    updateTotal();
  }
});





function openPaymentModal() {
  $('#paymentModal').modal('show');
  $('#paymentForm')[0].reset();
  $('#payment_id').val('');
  $('#paymentModalTitle').text('Record Customer Payment');
  $('.processing_button1').text('Save');
  $('#customer_id').val('').trigger('change');
  $('#invoiceTable tbody').html('');
  $('#total_paid').val('0.00');
}

// $('#customer_id').on('select2:select', function (e) {
//   $('#bank_account').prop('disabled', false); // Enable the bank field
// });



$('#customer_id').select2({
  ajax: {
    url: 'cus_payment/fetch_customers_with_outstanding.php',
    dataType: 'json',
    delay: 250,
    processResults: function(data) {
      return { results: data };
    },
    cache: true
  },
  placeholder: 'Select customer',
   dropdownParent: $('#paymentModal')
});


$('#customer_id').on('change', function () {
      $('#bank_account').prop('disabled', false); // Enable the bank field
  const customerId = $(this).val();
  const locationId = <?= json_encode($location_id) ?>;  
  
  
 $.get('cus_payment/fetch_customer_outstanding_bills.php', { c_id: customerId }, function (res) {
  if (res.error) {
    notify('danger', 'Error', res.error);
    $('#invoiceTable tbody').html('');
    return;
  }

  const data = res;
  let html = '', total = 0;

  data.forEach((bill, i) => {
    const rowClass = bill.loc_id != locationId ? 'style="background-color: #f8d7da;"' : '';
    html += `
      <tr ${rowClass}>
        <td><input type="checkbox" class="pay_check" data-index="${i}"></td>
        <td>${bill.invoice_no}<input type="hidden" name="inv_id[]" value="${bill.inv_id}">
        <input type="hidden" name="loc_id[]" value="${bill.loc_id}">
        <input type="hidden" name="inv_type[]" value="${bill.inv_type}"></td>
        <td>${bill.date}</td>
        <td>${bill.inv_type}</td>
        <td>${bill.supply_by || ''}</td>
        <td>${bill.order_no || ''}</td>
        <td>${bill.vehicle_no || ''}</td>
        <td class="text-right">${parseFloat(bill.org_amount).toFixed(2)}</td>
        <td class="text-right">${parseFloat(bill.amount_due).toFixed(2)}</td>
        <td><input type="text" name="amount[]" class="form-control text-right pay_input" data-index="${i}" value=""></td>
      </tr>`;
  });

  $('#invoiceTable tbody').html(html);
  bindInvoiceInputs();
}).fail(function(xhr) {
  notify('danger', 'Server Error', 'Something went wrong while loading customer bills.');
});

  
  
  
  
});





// $('#customer_id').on('change', function() {
//   const customerId = $(this).val();
//   $.get('cus_payment/fetch_customer_outstanding_bills.php', { c_id: customerId }, function(res) {
//     const data = JSON.parse(res);
//     let html = '', total = 0;
//     data.forEach((bill, i) => {
//       html += `
//         <tr>
//           <td><input type="checkbox" class="pay_check" data-index="${i}"></td>
//           <td>${bill.invoice_no}<input type="hidden" name="inv_id[]" value="${bill.inv_id}">
//           <input type="hidden" name="loc_id[]" value="${bill.loc_id}">
//           <input type="hidden" name="inv_type[]" value="${bill.inv_type}"></td>
//           <td>${bill.date}</td>
//           <td>${bill.inv_type}</td>
//           <td>${bill.supply_by || ''}</td>  
//           <td class="text-right">${parseFloat(bill.amount_due).toFixed(2)}</td>
//           <td><input type="text" name="amount[]" class="form-control text-right pay_input" data-index="${i}" value=""></td>
//         </tr>`;
//     });
//     $('#invoiceTable tbody').html(html);
//     bindInvoiceInputs();
//   });
// });

function bindInvoiceInputs() {
  $('.pay_check').off('change.payment').on('change.payment', function() {
    const idx = $(this).data('index');
    const amt = parseFloat($(this).closest('tr').find('td:eq(8)').text().replace(/,/g, ''));
    if ($(this).is(':checked')) {
      $(`input.pay_input[data-index='${idx}']`).val(amt.toFixed(2));
    } else {
      $(`input.pay_input[data-index='${idx}']`).val('');
    }
    updateTotal();
  });

  $('.pay_input').off('input.payment').on('input.payment', function() {
    updateTotal();
  });
}


function updateTotal() {
  let total = 0;
  $('.pay_input').each(function() {
    const val = parseFloat($(this).val());
    if (!isNaN(val)) total += val;
  });
  $('#total_paid').val(total.toFixed(2));
}
</script>

<script>
$('#paymentForm').on('submit', function(e) {
  e.preventDefault();
const selectedBank = $('#bank_account').val();  
    const totalPaidInput = $('#total_paid');
  const entered = parseFloat(totalPaidInput.val()) || 0;
  const max = parseFloat(totalPaidInput.prop('max')) || 0;
    if (selectedBank == '44' && entered > max) {
      
    notify('danger', 'Validation Error', `Entered amount (${entered.toFixed(2)}) exceeds available advance (${max.toFixed(2)}).`);
  $('.processing_button1').prop('disabled', false).html('Save');
    return;
  }
  
    var total_value = document.getElementById('total_paid').value;
if (total_value === '' || parseFloat(total_value) === 0) {
  notify('danger', 'Validation Error', 'Check advance amount or issue in total value. Close and resubmit this modal.');
  return;
}


  const form = $(this);
  const button = form.find('.processing_button1');
  button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

  $.ajax({
    type: 'POST',
    url: 'cus_payment/save_customer_payment.php',
    data: form.serialize(),
    dataType: 'json',
    success: function(res) {
      if (res.status === 'success') {
        notify('success', 'Success', res.message);
        $('#paymentModal').modal('hide');
        setTimeout(() => paymentTable.ajax.reload(null, false), 500);
      } else {
        notify('danger', 'Error', res.message);
      }
    },
    error: function(xhr) {
      const response = xhr.responseJSON || {};
      notify('danger', 'Could not save customer payment', response.message || 'Could not process the request.');
    },
    complete: function() {
      button.prop('disabled', false).html($('#payment_id').val() ? 'Update' : 'Save');
    }
  });
});
</script>

<script>
let paymentTable;
function setPaymentPeriodCookie() {
  const sel = $('#paymentPeriod').val();
  const value = [sel, $('#paymentStartDate').val(), $('#paymentEndDate').val()].map(encodeURIComponent).join('|');
  document.cookie = 'acc_period_<?= (int)$location_id ?>=' + value + '; path=/; max-age=' + (60*60*24*365) + '; SameSite=Lax';
}
$(function() {
  paymentTable = $('#example').DataTable({
    processing:true, serverSide:true, pageLength:50, order:[[2,'desc']],
    ajax:{url:'ajax/list_customer_payments.php', type:'POST', data:function(d) {
      d.start_date=$('#paymentStartDate').val(); d.end_date=$('#paymentEndDate').val();
      d.show_cancelled=$('#showCancelledPayments').is(':checked') ? 1 : 0;
    }, dataSrc:function(json) { $('#paymentTotal').text('Total Payments: ' + (json.totalPayments || '0.00')); return json.data || []; }},
    columns:[{data:'index'},{data:'receipt_no'},{data:'date'},{data:'customer'},{data:'cheque_no'},{data:'amount',className:'text-right'},{data:'status'},{data:'action',orderable:false,searchable:false}],
    autoWidth:false, createdRow:function(row) { $(row).find('td').css({padding:'2px 5px',height:'25px',fontSize:'13px',lineHeight:'1.1'}); }
  });
  $('#paymentFilterButton').on('click', function() { setPaymentPeriodCookie(); paymentTable.ajax.reload(); });
  $('#showCancelledPayments').on('change', function() { paymentTable.ajax.reload(); });
  $('#paymentPeriod').on('change', function() {
    const option=$(this).find(':selected');
    if ($(this).val() !== 'custom') { $('#paymentStartDate').val(option.data('start')); $('#paymentEndDate').val(option.data('end')); }
    setPaymentPeriodCookie();
  });
  $('#paymentStartDate,#paymentEndDate').on('change', function() { $('#paymentPeriod').val('custom'); setPaymentPeriodCookie(); });
  $('#example').on('change', '.payment-action', function() {
    const action=$(this).val(), id=$(this).data('id'); $(this).val('');
    if (action==='view') viewReceipt(id); else if (action==='edit') editPayment(id); else if (action==='cancel') cancelPayment(id);
  });
});
</script>


<?php include("footer.php"); ?>
