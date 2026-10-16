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

// Fetch deposit accounts (Cash in Hand group, excluding ca_id = 7)
$accounts = mysqli_query($con, "SELECT ca_id, ca_name FROM acc_chart_of_accounts 
                                  WHERE ca_group = 'Cash in Hand' AND ca_id != 7 AND ca_id != 44 AND status = 1 
                                ORDER BY ca_name");
?>

<style>
  #depositFilterForm label { font-size: 13px; font-weight: 600; white-space: nowrap; }
  #depositFilterForm .form-control { height: 32px; padding: 4px 8px; font-size: 13px; }
  #depositFilterForm #depositPeriod { width: 205px !important; }
  #depositFilterForm #depositStartDate, #depositFilterForm #depositEndDate { width: 145px; }
  #depositFilterForm .btn { height: 32px; padding: 4px 11px; font-size: 13px; line-height: 22px; }
  #example tbody tr { height: 25px !important; }
  #example tbody td { padding: 1px 5px !important; height: 25px !important; font-size: 13px; line-height: 1 !important; vertical-align: middle; }
  #example select.deposit-action.form-control.form-control-sm { min-width: 100px; width: 100px; height: 21px !important; min-height: 21px !important; padding: 0 4px !important; color: #333; background-color: #fff !important; border: 1px solid #ced4da; border-radius: 3px; font-size: 11px; line-height: 1 !important; }
</style>

<div class="content-wrapper">
  <div class="container-fluid">
    <div class="main-header">
      <h5>ACCOUNTS | <?= $client_name ?> | DEPOSITS</h5>
    </div>

    <div class="row">
      <div class="col-md-12">
        <div class="card">
          <div class="card-block">

            <div id="depositFilterForm" class="d-flex flex-wrap align-items-center mb-3" style="gap:7px;padding:9px 10px;background:#f7f8fa;border:1px solid #dee2e6;border-radius:4px;">
              <button type="button" class="btn btn-success btn-sm" onclick="openDepositModal()">+ Record Deposit</button>
              <label for="depositPeriod" class="mb-0">Period</label>
              <select id="depositPeriod" class="form-control form-control-sm" style="width:220px;">
                <option value="custom" <?= $period_selection === 'custom' ? 'selected' : '' ?>>Custom</option>
                <?php if ($latest_period): ?>
                <option value="current" data-start="<?= htmlspecialchars($latest_period['perid_from']) ?>" data-end="<?= htmlspecialchars($latest_period['period_to']) ?>" <?= $period_selection === 'current' ? 'selected' : '' ?>>Current <?= date('d/m/Y', strtotime($latest_period['perid_from'])) ?> - <?= date('d/m/Y', strtotime($latest_period['period_to'])) ?></option>
                <?php endif; ?>
                <?php foreach ($periods as $period): if ($latest_period && (int)$period['id'] === (int)$latest_period['id']) continue; $period_value = 'period_' . (int)$period['id']; ?>
                <option value="<?= $period_value ?>" data-start="<?= htmlspecialchars($period['perid_from']) ?>" data-end="<?= htmlspecialchars($period['period_to']) ?>" <?= $period_selection === $period_value ? 'selected' : '' ?>><?= date('d/m/Y', strtotime($period['perid_from'])) ?> - <?= date('d/m/Y', strtotime($period['period_to'])) ?></option>
                <?php endforeach; ?>
              </select>
              <label for="depositStartDate" class="mb-0">From</label>
              <input type="date" id="depositStartDate" class="form-control form-control-sm" value="<?= htmlspecialchars($start_date) ?>">
              <label for="depositEndDate" class="mb-0">To</label>
              <input type="date" id="depositEndDate" class="form-control form-control-sm" value="<?= htmlspecialchars($end_date) ?>">
              <button type="button" id="depositFilterButton" class="btn btn-primary btn-sm">Filter</button>
              <label class="mb-0 ml-2"><input type="checkbox" id="showCancelledDeposits"> Show Cancelled</label>
            </div>

            <table id="example" class="table table-bordered table-striped table-sm">
              <thead class="thead-dark">
                <tr>
                  <th>#</th>
                  <th>Date</th>
                  <th>From (Cash)</th>
                  <th>To Account</th>
                  <th>Amount</th>
                  <th>Memo</th>
                  <th>Status</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>

          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal -->
<div class="modal fade" id="depositModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <form method="post" id="depositForm" class="processing_form">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="depositModalTitle">Record Deposit</h5>
        </div>
        <div class="modal-body">
          <input type="hidden" name="source_id" value="">
          <div class="alert alert-info" id="cashBalance">Loading balance...</div>
          <div class="form-group">
            <label>Date</label>
            <input type="date" name="deposit_date" class="form-control" value="<?= htmlspecialchars($transaction_date) ?>" required>
          </div>
          <div class="form-group">
            <label>To Account</label>
            <select name="to_account" class="form-control select2_single" required>
              <option value="">-- Select Account --</option>
              <?php while ($a = mysqli_fetch_assoc($accounts)) {
                echo "<option value='{$a['ca_id']}'>{$a['ca_name']}</option>";
              } ?>
            </select>
          </div>
          <div class="form-group">
            <label>Amount</label>
            <input type="number" name="amount" class="form-control  " step="0.01" required>
          </div>
          <div class="form-group">
            <label>Memo</label>
            <textarea name="memo" class="form-control"></textarea>
          </div>
          <input type="hidden" name="location_id" value="<?= $location_id ?>">
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-success processing_button">Save Deposit</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
function openDepositModal() {
  $('#depositForm')[0].reset();
  $('#depositForm input[name="source_id"]').val('');
  $('#depositForm input, #depositForm select, #depositForm textarea').prop('disabled', false);
  $('#depositForm .processing_button').show();
  $('#depositModalTitle').text('Record Deposit');
  $('select[name="to_account"]').val('').trigger('change');
  $('#depositModal').modal('show');

  $.get('ajax/get_cash_balance.php', { location_id: <?= $location_id ?> }, function(balance) {
    // $('#cashBalance').html('Cash in Hand Balance: <strong>' + parseFloat(balance).toFixed(2) + '</strong>');
    $('#cashBalance').html('Cash in Hand Balance: <strong>' + parseFloat(balance).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</strong>');
  });
}

$('#depositForm').on('submit', function(e) {
  e.preventDefault();
  const formData = new FormData(this);
  fetch('ajax/save_deposit.php', {
    method: 'POST',
    body: formData
  }).then(res => res.json()).then(data => {
    if (data.status === 'success') {
      notify('success', 'Saved', data.message);
      setTimeout(() => location.reload(), 1000);
    } else {
      notify('danger', 'Error', data.message);
    }
  }).catch(() => {
    notify('danger', 'Error', 'Failed to save deposit.');
  });
});

function editDeposit(sourceId) {
  $.getJSON('ajax/get_deposit_details.php', { source_id: sourceId }, function(data) {
    if (!data.deposit) {
      notify('danger', 'Error', data.error || 'Deposit not found.');
      return;
    }
    $('#depositForm input[name="source_id"]').val(data.deposit.source_id);
    $('#depositForm input[name="deposit_date"]').val(data.deposit.t_date);
    $('#depositForm select[name="to_account"]').val(data.deposit.ca_id).trigger('change');
    $('#depositForm input[name="amount"]').val(data.deposit.amount);
    $('#depositForm textarea[name="memo"]').val(data.deposit.t_memo);
    $('#depositModalTitle').text('Edit Deposit');
    $('#depositForm input, #depositForm select, #depositForm textarea').prop('disabled', false);
    $('#depositForm .processing_button').show();
    $('#depositModal').modal('show');
    $.get('ajax/get_cash_balance.php', { location_id: <?= (int)$location_id ?> }, function(balance) {
      $('#cashBalance').html('Cash in Hand Balance: <strong>' + parseFloat(balance).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</strong>');
    });
  }).fail(function(xhr) {
    notify('danger', 'Error', (xhr.responseJSON && xhr.responseJSON.error) || 'Could not load deposit details.');
  });
}

let depositTable;
function saveDepositPeriodCookie() {
  const values = [$('#depositPeriod').val() || 'custom', $('#depositStartDate').val(), $('#depositEndDate').val()]
    .map(value => encodeURIComponent(value || '')).join('|');
  document.cookie = <?= json_encode($period_cookie_key) ?> + '=' + values + '; path=/; max-age=' + (180 * 86400) + '; SameSite=Lax';
}
function reloadDepositTable() {
  saveDepositPeriodCookie();
  if (depositTable) depositTable.ajax.reload();
}

$(document).ready(function() {
  depositTable = $('#example').DataTable({
    processing: true,
    serverSide: true,
    pageLength: 50,
    order: [[1, 'desc']],
    ajax: {
      url: 'ajax/list_deposits.php',
      type: 'POST',
      data: function(request) {
        request.start_date = $('#depositStartDate').val();
        request.end_date = $('#depositEndDate').val();
        request.show_cancelled = $('#showCancelledDeposits').is(':checked') ? 1 : 0;
      },
      error: function(xhr) {
        const response = xhr.responseJSON || {};
        notify('danger', 'Deposit list error', response.error || 'Could not load deposits.');
      }
    },
    columns: [
      { data: 'index', orderable: false, searchable: false },
      { data: 'date' },
      { data: 'from_cash' },
      { data: 'to_account' },
      { data: 'amount', className: 'text-right' },
      { data: 'memo' },
      { data: 'status', orderable: false },
      { data: 'action', orderable: false, searchable: false }
    ],
    createdRow: function(row, data) {
      if (Number(data.status_value) === 0) $(row).addClass('table-danger');
    }
  });
  saveDepositPeriodCookie();
  $('#depositPeriod').on('change', function() {
    const option = $(this).find(':selected');
    if (this.value !== 'custom') {
      $('#depositStartDate').val(option.data('start'));
      $('#depositEndDate').val(option.data('end'));
    }
    reloadDepositTable();
  });
  $('#depositFilterButton').on('click', function() {
    $('#depositPeriod').val('custom');
    reloadDepositTable();
  });
  $('#depositStartDate, #depositEndDate').on('change', function() {
    $('#depositPeriod').val('custom');
    reloadDepositTable();
  });
  $('#showCancelledDeposits').on('change', reloadDepositTable);
  $('.select2_single').select2({ width: '100%' });
});

$(document).on('change', '.deposit-action', function() {
  const action = this.value;
  const sourceId = Number($(this).data('id'));
  const status = Number($(this).data('status'));
  $(this).val('');
  if (action === 'edit' && status === 1) editDeposit(sourceId);
  if (action === 'cancel' && status === 1) {
    const confirmation = window.Swal && typeof window.Swal.fire === 'function'
      ? window.Swal.fire({ title: 'Cancel Deposit?', text: 'This will mark the deposit as cancelled.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes, cancel it' }).then(result => result.isConfirmed)
      : Promise.resolve(window.confirm('Cancel this deposit?'));
    confirmation.then(function(confirmed) {
      if (!confirmed) return;
      $.post('ajax/delete_deposit.php', { source_id: sourceId, location_id: <?= (int)$location_id ?> }, function(message) {
        notify('success', 'Cancelled', message);
        depositTable.ajax.reload(null, false);
      }).fail(function(xhr) {
        notify('danger', 'Error', xhr.responseText || 'Could not cancel this deposit.');
      });
    });
  }
});
</script>

<?php include("footer.php"); ?>
