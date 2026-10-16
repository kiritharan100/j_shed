<?php
 if (isset($_GET['module']) && $_GET['module'] === 'report') {
        include '../reports/header.php';
    } else {
        include 'header.php';
    }

$c_id      = isset($_GET['c_id']) ? intval($_GET['c_id']) : 0;
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
$from_date = $_GET['from'] ?? ($period_cookie['start'] ?? ($latest_period['perid_from'] ?? date('Y-m-01')));
$to_date = $_GET['to'] ?? ($period_cookie['end'] ?? ($latest_period['period_to'] ?? date('Y-m-d')));
$from_date = date('Y-m-d', strtotime($from_date));
$to_date = date('Y-m-d', strtotime($to_date));
$period_selection = $period_cookie['selection'] ?? ($latest_period ? 'current' : 'custom');
if (!in_array($period_selection, ['custom', 'current'], true) && !preg_match('/^period_\d+$/', $period_selection)) $period_selection = 'custom';

$customer = $c_id > 0 ? mysqli_fetch_assoc(mysqli_query($con, "SELECT customer_name, customer_address FROM mange_customer WHERE c_id = $c_id ")) : ['customer_name' => '', 'customer_address' => ''];
?>

<div class="content-wrapper">
    <div class="container-fluid">
        <div class="main-header">
            <h4>Customer Ledger</h4>
        </div>

        <div class="card">
            <div class="card-block">
                <form method="get" id="ledgerFilterForm" class="d-flex flex-wrap align-items-center mb-3" style="gap:7px;padding:9px 10px;background:#f7f8fa;border:1px solid #dee2e6;border-radius:4px;">
                    <label class="mr-2">Customer:</label>
                    <select name="c_id" class="form-control select2 mr-2" required style="min-width: 260px;">
                        <option value="">-- Select Customer --</option>
                        <?php
                        $customers = mysqli_query($con, "SELECT c_id, customer_name FROM mange_customer WHERE status = 1 and c_id > 0 ORDER BY customer_name");
                        while ($row = mysqli_fetch_assoc($customers)) {
                            $selected = ($c_id == $row['c_id']) ? 'selected' : '';
                            echo "<option value='{$row['c_id']}' $selected>" . htmlspecialchars($row['customer_name']) . "</option>";
                        }
                        ?>
                    </select>
                        
    

                                    <?php   if (isset($_GET['module']) && $_GET['module'] === 'report') {
                                        echo "<input type='hidden' name='module' value='report'>";  }?>

                    <label for="ledgerPeriod" class="mb-0">Period</label>
                    <select id="ledgerPeriod" class="form-control form-control-sm" style="width:205px;height:32px;padding:4px 8px;font-size:13px;">
                        <option value="custom" <?= $period_selection === 'custom' ? 'selected' : '' ?>>Custom</option>
                        <?php if ($latest_period): ?><option value="current" data-start="<?= htmlspecialchars($latest_period['perid_from']) ?>" data-end="<?= htmlspecialchars($latest_period['period_to']) ?>" <?= $period_selection === 'current' ? 'selected' : '' ?>>Current <?= date('d/m/Y', strtotime($latest_period['perid_from'])) ?> - <?= date('d/m/Y', strtotime($latest_period['period_to'])) ?></option><?php endif; ?>
                        <?php foreach ($periods as $period): if ($latest_period && (int)$period['id'] === (int)$latest_period['id']) continue; $period_value = 'period_' . (int)$period['id']; ?>
                        <option value="<?= $period_value ?>" data-start="<?= htmlspecialchars($period['perid_from']) ?>" data-end="<?= htmlspecialchars($period['period_to']) ?>" <?= $period_selection === $period_value ? 'selected' : '' ?>><?= date('d/m/Y', strtotime($period['perid_from'])) ?> - <?= date('d/m/Y', strtotime($period['period_to'])) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label for="ledgerFromDate" class="mb-0">From</label>
                    <input type="date" name="from" id="ledgerFromDate" class="form-control form-control-sm" style="width:145px;height:32px;padding:4px 8px;font-size:13px;" value="<?= htmlspecialchars($from_date) ?>" required>
                    <label for="ledgerToDate" class="mb-0">To</label>
                    <input type="date" name="to" id="ledgerToDate" class="form-control form-control-sm" style="width:145px;height:32px;padding:4px 8px;font-size:13px;" value="<?= htmlspecialchars($to_date) ?>" required>

                    <button type="submit" class="btn btn-primary btn-sm" style="height:32px;padding:4px 11px;font-size:13px;">Filter</button>
                    <button type="button" id="exportButton" filename="CUSTOMER_STATEMENT_<?php echo htmlspecialchars($customer['customer_name'], ENT_QUOTES); ?>.xlsx" class="btn btn-primary btn-sm ml-1" style="height:32px;padding:4px 11px;font-size:13px;">
                        <i class="ti-cloud-down"></i> Export
                    </button>
                </form>
                <hr>

                <?php if ($c_id > 0): ?>
                <h5>Customer: <strong><?= htmlspecialchars($customer['customer_name']) ?></strong></h5>
                <h6>Address: <?= nl2br(htmlspecialchars($customer['customer_address'])) ?></h6>
                <h6>Date Range: <?= $from_date ?> to <?= $to_date ?></h6>
                <h6 id="openingBalance" class="font-weight-bold">Opening Balance: Loading...</h6>

                <div class="table-responsive mt-3">
                    <table id="example" class="table table-bordered table-hover">
                        <thead class="thead-dark">
                            <tr>
                                <th>Date</th>
                                <th>Transaction Type</th>
                                <th>Reference</th>
                                <th>Memo</th>
                                <th class="text-right">Debit</th>
                                <th class="text-right">Credit</th>
                                <th class="text-right">Cumulative Balance</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>

                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    $('.select2').select2();
    const periodCookieName = 'acc_period_<?= (int)$location_id ?>';
    function saveLedgerPeriodCookie() {
        const cookieValue = [$('#ledgerPeriod').val(), $('#ledgerFromDate').val(), $('#ledgerToDate').val()].map(encodeURIComponent).join('|');
        document.cookie = periodCookieName + '=' + cookieValue + '; path=/; max-age=' + (60 * 60 * 24 * 365) + '; SameSite=Lax';
    }
    $('#ledgerPeriod').on('change', function () {
        const option = $(this).find(':selected');
        if (this.value !== 'custom') {
            $('#ledgerFromDate').val(option.data('start'));
            $('#ledgerToDate').val(option.data('end'));
        }
        saveLedgerPeriodCookie();
    });
    $('#ledgerFromDate,#ledgerToDate').on('change', function () {
        $('#ledgerPeriod').val('custom');
        saveLedgerPeriodCookie();
    });
    $('#ledgerFilterForm').on('submit', saveLedgerPeriodCookie);
    if ($('#example').length) {
        $('#example').DataTable({
            processing: true,
            serverSide: true,
            pageLength: 100,
            ordering: false,
            ajax: {
                url: 'ajax/list_customer_ledger.php',
                type: 'POST',
                data: function (d) {
                    d.c_id = <?= (int)$c_id ?>;
                    d.from = <?= json_encode($from_date) ?>;
                    d.to = <?= json_encode($to_date) ?>;
                },
                dataSrc: function (json) {
                    $('#openingBalance').text('Opening Balance: ' + (json.openingBalance || '0.00'));
                    return json.data || [];
                },
                error: function (xhr) {
                    $('#openingBalance').text('Opening Balance: unavailable');
                    console.error('Customer ledger load failed:', xhr.responseText);
                }
            },
            columns: [
                {data: 'date'}, {data: 'type'}, {data: 'reference'}, {data: 'memo'},
                {data: 'debit', className: 'text-right'}, {data: 'credit', className: 'text-right'},
                {data: 'balance', className: 'text-right'}
            ]
        });
    }
});
</script>

<?php include 'footer.php'; ?>
