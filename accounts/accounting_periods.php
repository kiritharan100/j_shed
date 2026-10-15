<?php
include 'header.php';
require_once '../ensure_business_start_date.php';
ensure_business_start_date_column($con);

$create_table_sql = "CREATE TABLE IF NOT EXISTS accounts_accounting_period (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    location_id INT NOT NULL,
    perid_from DATE NOT NULL,
    period_to DATE NOT NULL,
    due_date DATE NOT NULL,
    lock_period TINYINT(1) NOT NULL DEFAULT 0,
    status TINYINT(1) NOT NULL DEFAULT 1,
    INDEX idx_accounting_period_location_status (location_id, status),
    INDEX idx_accounting_period_dates (perid_from, period_to)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if (!mysqli_query($con, $create_table_sql)) {
    die('Could not prepare the accounting periods table.');
}

$client_dates = ['business_start_date' => null];
$dates_result = mysqli_query($con, "SELECT business_start_date FROM client_registration WHERE c_id = " . (int)$location_id . " LIMIT 1");
if ($dates_result && mysqli_num_rows($dates_result) > 0) {
    $client_dates = mysqli_fetch_assoc($dates_result);
}
$min_start_date = '';
$business_start = $client_dates['business_start_date'] ?? '';
$min_start_date = $business_start;

$periods = [];
$period_result = mysqli_query($con, "SELECT id, perid_from, period_to, due_date, lock_period, status FROM accounts_accounting_period WHERE location_id = " . (int)$location_id . " AND status = 1 ORDER BY perid_from DESC");
if ($period_result) {
    while ($period = mysqli_fetch_assoc($period_result)) {
        $periods[] = $period;
    }
}

$default_start = $min_start_date ?: date('Y-m-d');
if ($periods) {
    $default_start = date('Y-m-d', strtotime($periods[0]['period_to'] . ' +1 day'));
}
$start_year = (int)date('Y', strtotime($default_start));
$start_month = (int)date('n', strtotime($default_start));
$default_end_year = $start_month > 3 ? $start_year + 1 : $start_year;
$default_end = $default_end_year . '-03-31';
$default_due = date('Y-m-d', strtotime($default_end . ' +6 months'));
?>

<div class="content-wrapper">
    <div class="container-fluid">
        <div class="main-header"><h5>ACCOUNTS | <?= htmlspecialchars($client_name) ?> | ACCOUNTING PERIODS</h5></div>
        <div class="row">
            <div class="col-md-9 col-lg-8">
                <div class="card"><div class="card-block">
                    <div class="d-flex justify-content-end">
                        <button id="exportButton" filename="Accounting_periods.xlsx" class="btn btn-primary mr-2"><i class="ti-cloud-down"></i> Export</button>
                        <button class="btn btn-success" type="button" onclick="addNew()">+ Add Accounting Period</button>
                    </div>
                    <hr>
                    <div class="table-responsive">
                        <table id="example" class="table table-bordered table-striped table-hover table-sm">
                            <thead><tr><th>ID</th><th>Period From</th><th>Period To</th><th>Due Date</th><th>Log Period</th><th>Actions</th></tr></thead>
                            <tbody>
                            <?php foreach ($periods as $index => $period): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><?= date('d-m-Y', strtotime($period['perid_from'])) ?></td>
                                    <td><?= date('d-m-Y', strtotime($period['period_to'])) ?></td>
                                    <td><?= date('d-m-Y', strtotime($period['due_date'])) ?></td>
                                    <td class="text-center">
                                        <?php if ((int)$period['lock_period'] === 1): ?>
                                            <i class="fas fa-lock text-danger lock-icon" role="button" title="Unlock period" data-id="<?= (int)$period['id'] ?>" data-state="1"></i>
                                        <?php else: ?>
                                            <i class="fas fa-unlock text-success lock-icon" role="button" title="Lock period" data-id="<?= (int)$period['id'] ?>" data-state="0"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-nowrap">
                                        <button type="button" class="btn btn-sm btn-primary" onclick="loadEdit(<?= (int)$period['id'] ?>)">Edit</button>
                                        <button type="button" class="btn btn-sm btn-danger" onclick="deletePeriod(<?= (int)$period['id'] ?>)">Delete</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div></div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="periodModal" tabindex="-1" role="dialog" aria-labelledby="periodModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document"><div class="modal-content">
        <form id="period_form" onsubmit="return savePeriod();">
            <div class="modal-header"><h5 class="modal-title" id="periodModalLabel">Add Accounting Period</h5><button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button></div>
            <div class="modal-body">
                <input type="hidden" name="id" id="period_id">
                <input type="hidden" name="action" id="period_action" value="add">
                <div class="form-group"><label>Period From</label><input type="text" name="perid_from" class="form-control date_input" placeholder="dd/mm/yyyy" value="<?= date('d/m/Y', strtotime($default_start)) ?>" required></div>
                <div class="form-group"><label>Period To</label><input type="text" name="period_to" class="form-control date_input" placeholder="dd/mm/yyyy" value="<?= date('d/m/Y', strtotime($default_end)) ?>" required></div>
                <div class="form-group"><label>Due Date</label><input type="text" name="due_date" class="form-control date_input" placeholder="dd/mm/yyyy" value="<?= date('d/m/Y', strtotime($default_due)) ?>" required></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button><button type="submit" class="btn btn-success">Save</button></div>
        </form>
    </div></div>
</div>

<script>
function addNew() {
    $('#period_form')[0].reset();
    $('#period_id').val('');
    $('#period_action').val('add');
    $('#periodModalLabel').text('Add Accounting Period');
    $('#periodModal').modal('show');
}

function formatPeriodDate(value) {
    const parts = value.split('-');
    return parts.length === 3 ? parts[2] + '/' + parts[1] + '/' + parts[0] : value;
}

function loadEdit(id) {
    $.post('ajax_accounting_period/load_single.php', { id: id }, function(response) {
        if (!response.success) {
            notify('danger', 'Error', response.msg || 'Could not load the accounting period.');
            return;
        }
        $('#period_id').val(response.data.id);
        $('#period_action').val('edit');
        $('[name="perid_from"]').val(formatPeriodDate(response.data.perid_from));
        $('[name="period_to"]').val(formatPeriodDate(response.data.period_to));
        $('[name="due_date"]').val(formatPeriodDate(response.data.due_date));
        $('#periodModalLabel').text('Edit Accounting Period');
        $('#periodModal').modal('show');
    }, 'json');
}

function savePeriod() {
    $.post('ajax_accounting_period/save_period.php', $('#period_form').serialize(), function(response) {
        if (response.success) {
            notify('success', 'Saved', response.msg);
            setTimeout(function() { location.reload(); }, 700);
        } else {
            notify('danger', 'Error', response.msg || 'Could not save the accounting period.');
        }
    }, 'json').fail(function(xhr) {
        var response = xhr.responseJSON || {};
        var detail = response.msg || (xhr.responseText || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
        notify('danger', 'Error', detail || ('Request failed (HTTP ' + xhr.status + ').'));
    });
    return false;
}

function deletePeriod(id) {
    Swal.fire({ title: 'Delete accounting period?', text: 'This will deactivate the period.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Delete' }).then(function(result) {
        if (!result.isConfirmed) return;
        $.post('ajax_accounting_period/delete_period.php', { id: id }, function(response) {
            if (response.success) { location.reload(); }
            else { notify('danger', 'Error', response.msg || 'Could not delete the period.'); }
        }, 'json');
    });
}

$(document).on('click', '.lock-icon', function(event) {
    event.preventDefault();
    event.stopPropagation();
    const icon = $(this);
    const newState = Number(icon.data('state')) === 1 ? 0 : 1;
    const action = newState ? 'Lock' : 'Unlock';
    const confirmation = window.Swal && typeof window.Swal.fire === 'function'
        ? window.Swal.fire({
            icon: 'warning',
            title: action + ' Period?',
            text: 'Do you want to ' + action.toLowerCase() + ' this accounting period?',
            showCancelButton: true,
            confirmButtonText: 'Yes, ' + action,
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#7066e0',
            cancelButtonColor: '#6c757d'
        }).then(function(result) { return result.isConfirmed; })
        : Promise.resolve(window.confirm('Do you want to ' + action.toLowerCase() + ' this accounting period?'));

    confirmation.then(function(confirmed) {
        if (!confirmed) return;
        $.post('ajax_accounting_period/update_lock.php', { id: icon.data('id'), state: newState }, function(response) {
            if (response.success) { location.reload(); }
            else { notify('danger', 'Error', response.msg || 'Could not update the period.'); }
        }, 'json').fail(function(xhr) {
            const response = xhr.responseJSON || {};
            notify('danger', 'Error', response.msg || 'Could not update the accounting period.');
        });
    });
});

$(function() {
    if ($.fn.DataTable && !$.fn.DataTable.isDataTable('#example')) {
        $('#example').DataTable({ pageLength: 25, ordering: false });
    }
});
</script>

<?php include 'footer.php'; ?>
