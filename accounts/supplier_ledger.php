 <?php
include 'header.php';

$sup_id = isset($_GET['sup_id']) ? (int)$_GET['sup_id'] : 0;
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

$supplier = $sup_id > 0 ? mysqli_fetch_assoc(mysqli_query($con, "SELECT supplier_name, tin_no FROM manage_supplier WHERE sup_id = $sup_id")) : ['supplier_name' => '', 'tin_no' => ''];
?>

<style>
  #supplierLedgerFilter label { font-size: 13px; font-weight: 600; white-space: nowrap; }
  #supplierLedgerFilter .form-control { height: 32px; padding: 4px 8px; font-size: 13px; }
  #example tbody td { padding: 2px 5px; font-size: 13px; }
</style>

<div class="content-wrapper">
    <div class="container-fluid">
        <div class="main-header">
            <h4>Supplier Ledger</h4>





            
        </div>

        <div class="card">
            <div class="card-block">
                <form method="get" id="supplierLedgerFilter" class="d-flex flex-wrap align-items-center mb-3" style="gap:7px;padding:9px 10px;background:#f7f8fa;border:1px solid #dee2e6;border-radius:4px;">
                    <label class="mb-0">Supplier:</label>
                    <select name="sup_id" class="form-control select2 mr-2" required style="min-width: 250px;">
                        <option value="">-- Select Supplier --</option>
                        <?php
                        $suppliers = mysqli_query($con, "SELECT sup_id, supplier_name FROM manage_supplier WHERE status = 1 ORDER BY supplier_name");
                        while ($row = mysqli_fetch_assoc($suppliers)) {
                            $selected = ($sup_id == $row['sup_id']) ? 'selected' : '';
                            echo "<option value='{$row['sup_id']}' $selected>" . htmlspecialchars($row['supplier_name']) . "</option>";
                        }
                        ?>
                    </select>

                    <label for="supplierPeriod" class="mb-0">Period</label>
                    <select id="supplierPeriod" class="form-control form-control-sm" style="width:205px;">
                        <option value="custom" <?= $period_selection === 'custom' ? 'selected' : '' ?>>Custom</option>
                        <?php if ($latest_period): ?><option value="current" data-start="<?= htmlspecialchars($latest_period['perid_from']) ?>" data-end="<?= htmlspecialchars($latest_period['period_to']) ?>" <?= $period_selection === 'current' ? 'selected' : '' ?>>Current <?= date('d/m/Y', strtotime($latest_period['perid_from'])) ?> - <?= date('d/m/Y', strtotime($latest_period['period_to'])) ?></option><?php endif; ?>
                        <?php foreach ($periods as $period): if ($latest_period && (int)$period['id'] === (int)$latest_period['id']) continue; $period_value = 'period_' . (int)$period['id']; ?>
                        <option value="<?= $period_value ?>" data-start="<?= htmlspecialchars($period['perid_from']) ?>" data-end="<?= htmlspecialchars($period['period_to']) ?>" <?= $period_selection === $period_value ? 'selected' : '' ?>><?= date('d/m/Y', strtotime($period['perid_from'])) ?> - <?= date('d/m/Y', strtotime($period['period_to'])) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label for="supplierFromDate" class="mb-0">From</label>
                    <input type="date" name="from" id="supplierFromDate" class="form-control form-control-sm" style="width:145px;" value="<?= htmlspecialchars($from_date) ?>" required>
                    <label for="supplierToDate" class="mb-0">To</label>
                    <input type="date" name="to" id="supplierToDate" class="form-control form-control-sm" style="width:145px;" value="<?= htmlspecialchars($to_date) ?>" required>
                    <button type="submit" class="btn btn-primary btn-sm" style="height:32px;padding:4px 11px;font-size:13px;">Filter</button>
                    <button type="button" id="exportButton" filename="SUPPLIER_STATEMENT_<?= htmlspecialchars($supplier['supplier_name'], ENT_QUOTES) ?>.xlsx" class="btn btn-primary btn-sm ml-1">
                        <i class="ti-cloud-down"></i> Export
                    </button>
                </form>
                <hr>

                <?php if ($sup_id > 0): ?>

                <h5>Supplier: <strong><?= htmlspecialchars($supplier['supplier_name']) ?></strong></h5>
                <h6>Date Range: <?= $from_date ?> to <?= $to_date ?></h6>
                <h6 id="supplierOpeningBalance" class="font-weight-bold">Opening Balance: Loading...</h6>

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
    function saveSupplierPeriodCookie() {
        const value = [$('#supplierPeriod').val(), $('#supplierFromDate').val(), $('#supplierToDate').val()].map(encodeURIComponent).join('|');
        document.cookie = periodCookieName + '=' + value + '; path=/; max-age=' + (60 * 60 * 24 * 365) + '; SameSite=Lax';
    }
    $('#supplierPeriod').on('change', function () {
        const option = $(this).find(':selected');
        if (this.value !== 'custom') {
            $('#supplierFromDate').val(option.data('start'));
            $('#supplierToDate').val(option.data('end'));
        }
        saveSupplierPeriodCookie();
    });
    $('#supplierFromDate,#supplierToDate').on('change', function () {
        $('#supplierPeriod').val('custom');
        saveSupplierPeriodCookie();
    });
    $('#supplierLedgerFilter').on('submit', saveSupplierPeriodCookie);
    if ($('#example').length) {
        $('#example').DataTable({
            processing: true, serverSide: true, pageLength: 100, ordering: false,
            ajax: {
                url: 'ajax/list_supplier_ledger.php', type: 'POST',
                data: function (d) { d.sup_id = <?= (int)$sup_id ?>; d.from = <?= json_encode($from_date) ?>; d.to = <?= json_encode($to_date) ?>; },
                dataSrc: function (json) { $('#supplierOpeningBalance').text('Opening Balance: ' + (json.openingBalance || '0.00')); return json.data || []; },
                error: function (xhr) { $('#supplierOpeningBalance').text('Opening Balance: unavailable'); console.error('Supplier ledger load failed:', xhr.responseText); }
            },
            columns: [
                {data:'date'}, {data:'type'}, {data:'reference'}, {data:'memo'},
                {data:'debit', className:'text-right'}, {data:'credit', className:'text-right'}, {data:'balance', className:'text-right'}
            ]
        });
    }
});
</script>

<?php include 'footer.php'; ?>
