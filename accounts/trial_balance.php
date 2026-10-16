<?php
 if (isset($_GET['module']) && $_GET['module'] === 'report') {
        include '../reports/header.php';
    } else {
        include 'header.php';
    }

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
?>

<div class="content-wrapper">
    <div class="container-fluid">
        <div class="main-header">
            <h4>Trial Balance</h4>
        </div>

        <div class="card">
            <div class="card-block">
                <form method="get" id="trialBalanceFilter" class="d-flex flex-wrap align-items-center mb-3" style="gap:7px;">
                    <?php if (isset($_GET['module']) && $_GET['module'] === 'report'): ?><input type="hidden" name="module" value="report"><?php endif; ?>
                    <label for="trialBalancePeriod" class="mb-0">Period:</label>
                    <select id="trialBalancePeriod" class="form-control" style="width:205px;height:32px;padding:4px 8px;font-size:13px;">
                        <option value="custom" <?= $period_selection === 'custom' ? 'selected' : '' ?>>Custom</option>
                        <?php if ($latest_period): ?><option value="current" data-start="<?= htmlspecialchars($latest_period['perid_from']) ?>" data-end="<?= htmlspecialchars($latest_period['period_to']) ?>" <?= $period_selection === 'current' ? 'selected' : '' ?>>Current <?= date('d/m/Y', strtotime($latest_period['perid_from'])) ?> - <?= date('d/m/Y', strtotime($latest_period['period_to'])) ?></option><?php endif; ?>
                        <?php foreach ($periods as $period): if ($latest_period && (int)$period['id'] === (int)$latest_period['id']) continue; $period_value = 'period_' . (int)$period['id']; ?>
                        <option value="<?= $period_value ?>" data-start="<?= htmlspecialchars($period['perid_from']) ?>" data-end="<?= htmlspecialchars($period['period_to']) ?>" <?= $period_selection === $period_value ? 'selected' : '' ?>><?= date('d/m/Y', strtotime($period['perid_from'])) ?> - <?= date('d/m/Y', strtotime($period['period_to'])) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label for="trialBalanceFrom" class="mb-0">From:</label>
                    <input type="date" id="trialBalanceFrom" name="from" class="form-control" style="height:32px;padding:4px 8px;font-size:13px;" value="<?= htmlspecialchars($from_date) ?>" required>
                    <label for="trialBalanceTo" class="mb-0">To:</label>
                    <input type="date" id="trialBalanceTo" name="to" class="form-control" style="height:32px;padding:4px 8px;font-size:13px;" value="<?= htmlspecialchars($to_date) ?>" required>
                    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                    <button type='button' id="exportButton" filename='TRIAL_BALANCE.xlsx' class="btn btn-primary btn-sm"><i class="ti-cloud-down"></i> Export</button>
                </form>

                <hr>

                <div class="table-responsive">
                    <table id='example' class="table table-bordered table-hover">
                        <thead class="thead-dark">
                            <tr>
                                <th>Account</th><th>Group</th>
                                <th class="text-right">Debit</th>
                                <th class="text-right">Credit</th>
                            </tr>
                        </thead>
                        <tbody>
<?php
$total_debit = 0;
$total_credit = 0;
$net_profit_bf = 0;
$vat_payable = 0;

// --- VAT Payable Calculation ---
$vat_result = mysqli_fetch_assoc(mysqli_query($con, "
    SELECT SUM(credit_vat) AS credit_vat, SUM(debit_vat) AS debit_vat
    FROM acc_transaction 
    WHERE t_date < DATE_ADD('$to_date', INTERVAL 1 DAY) AND location_id = '$location_id'
"));
$vat_txn = floatval($vat_result['credit_vat']) - floatval($vat_result['debit_vat']);

// Less: Amount already paid in VAT Payable ledger (ca_id = 47)
$vat_paid = mysqli_fetch_assoc(mysqli_query($con, "
    SELECT SUM(debit)  AS paid 
    FROM acc_transaction 
    WHERE ca_id = 47 AND t_date < DATE_ADD('$to_date', INTERVAL 1 DAY) AND location_id = '$location_id'
"));
$vat_paid_amt = floatval($vat_paid['paid']);

$vat_payable_tb = $vat_txn - $vat_paid_amt;
$vat_payable_tb = round($vat_payable_tb, 2);
$vat_payable = $vat_txn ;

$vat_payable = round($vat_payable, 2); // Round to 2 decimal places

// Start Loop
$accounts = mysqli_query($con, "
    SELECT ca_id, ca_name, ca_type ,ca_group
    FROM acc_chart_of_accounts 
    WHERE status = 1 
    ORDER BY ca_type, ca_name
");

while ($acc = mysqli_fetch_assoc($accounts)) {
    $ca_id = $acc['ca_id'];
    if ($ca_id == 47) continue; // Skip VAT Payable ledger from normal display

    $ca_name = $acc['ca_name'];
    $ca_type = $acc['ca_type'];
    $ca_group = $acc['ca_group'];

    if (in_array($ca_type, ['Assets', 'Liabilities', 'Equity'])) {
        $res = mysqli_fetch_assoc(mysqli_query($con, "
            SELECT SUM(debit) AS debit, SUM(credit) AS credit 
            FROM acc_transaction 
            WHERE ca_id = '$ca_id' AND t_date < DATE_ADD('$to_date', INTERVAL 1 DAY) and location_id ='$location_id'
        "));
        $debit = floatval($res['debit']);
        $credit = floatval($res['credit']);
        $net = $debit - $credit;

        $has_txn = mysqli_fetch_assoc(mysqli_query($con, "
            SELECT COUNT(*) AS count 
            FROM acc_transaction 
            WHERE ca_id = '$ca_id' AND t_date < DATE_ADD('$to_date', INTERVAL 1 DAY) and location_id ='$location_id'
        "))['count'];

        if ($has_txn == 0) continue;

        if ($net > 0) {
            $total_debit += $net;
            echo "<tr><td>$ca_name</td><td>$ca_group</td><td class='text-right'>" . number_format($net, 2) . "</td><td></td></tr>";
        } elseif ($net < 0) {
            $total_credit += abs($net);
            echo "<tr><td>$ca_name</td><td>$ca_group</td><td></td><td class='text-right'>" . number_format(abs($net), 2) . "</td></tr>";
        } else {
            echo "<tr><td>$ca_name</td><td>$ca_group</td><td></td><td></td></tr>";
        }

    } elseif (in_array($ca_type, ['Income', 'Expenses'])) {
        // Calculate B/F Profit
        $res_bf = mysqli_fetch_assoc(mysqli_query($con, "
            SELECT SUM(debit) AS debit, SUM(credit) AS credit 
            FROM acc_transaction 
            WHERE ca_id = '$ca_id' AND t_date < '$from_date' and location_id ='$location_id'
        "));
        $net_profit_bf += floatval($res_bf['credit']) - floatval($res_bf['debit']);

        // Show only this period’s activity for the P&L accounts
        $res_period = mysqli_fetch_assoc(mysqli_query($con, "
            SELECT SUM(debit) AS debit, SUM(credit) AS credit 
            FROM acc_transaction 
            WHERE ca_id = '$ca_id' AND t_date >= '$from_date' AND t_date < DATE_ADD('$to_date', INTERVAL 1 DAY) and location_id ='$location_id'
        "));
        $debit = floatval($res_period['debit']);
        $credit = floatval($res_period['credit']);

        if ($debit == 0 && $credit == 0) continue;

        if ($debit > $credit) {
            $total_debit += $debit - $credit;
            echo "<tr><td>$ca_name</td><td>$ca_group</td><td class='text-right'>" . number_format($debit - $credit, 2) . "</td><td></td></tr>";
        } else {
            $total_credit += $credit - $debit;
            echo "<tr><td>$ca_name</td><td>$ca_group</td><td></td><td class='text-right'>" . number_format($credit - $debit, 2) . "</td></tr>";
        }
    }
}

// Show VAT Payable if any
if ($vat_payable != 0.00) {
    $total_credit += $vat_payable_tb;
    echo "<tr class='table-warning'><td><strong>VAT Payable</strong></td><td>Current Liability</td><td></td><td class='text-right'>" . number_format($vat_payable_tb, 2) . "</td></tr>";
}

// Show Adjusted Net Profit B/F
$adjusted_profit_bf = $net_profit_bf - $vat_payable;
if (round($adjusted_profit_bf, 2) != 0.00) {
    if ($adjusted_profit_bf > 0) {
        $total_credit += $adjusted_profit_bf;
        echo "<tr class='table-info'><td><strong>Net Profit B/F</strong></td><td></td><td></td><td class='text-right'>" . number_format($adjusted_profit_bf, 2) . "</td></tr>";
    } else {
        $total_debit += abs($adjusted_profit_bf);
        echo "<tr class='table-info'><td><strong>Net Loss B/F</strong></td><td></td><td class='text-right'>" . number_format(abs($adjusted_profit_bf), 2) . "</td><td></td></tr>";
    }
}


?>
                    
                      
                            <tr style='background-color:#d3dace;'>
                                <td>x  Total</td><td></td>
                                <td align='right'><b><?= number_format($total_debit, 2) ?></b></td>
                                <td  align='right'><b><?= number_format($total_credit, 2) ?></b></td>
                            </tr>
                          </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    $(document).ready(function() {
   const periodCookieName = 'acc_period_<?= (int)$location_id ?>';
   function saveTrialBalancePeriod() {
       const value = [$('#trialBalancePeriod').val(), $('#trialBalanceFrom').val(), $('#trialBalanceTo').val()].map(encodeURIComponent).join('|');
       document.cookie = periodCookieName + '=' + value + '; path=/; max-age=' + (60 * 60 * 24 * 365) + '; SameSite=Lax';
   }
   $('#trialBalancePeriod').on('change', function() {
       const option = $(this).find(':selected');
       if (this.value !== 'custom') {
           $('#trialBalanceFrom').val(option.data('start'));
           $('#trialBalanceTo').val(option.data('end'));
       }
       saveTrialBalancePeriod();
   });
   $('#trialBalanceFrom,#trialBalanceTo').on('change', function() {
       $('#trialBalancePeriod').val('custom');
       saveTrialBalancePeriod();
   });
   $('#trialBalanceFilter').on('submit', saveTrialBalancePeriod);
   $('#example').DataTable({
        "pageLength": 100
    });
});

    </script>
<?php include 'footer.php'; ?>
