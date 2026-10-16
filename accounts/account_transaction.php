<?php
 if (isset($_GET['module']) && $_GET['module'] === 'report') {
        include '../reports/header.php';
    } else {
        include 'header.php';
    }

$from_date = isset($_GET['from']) ? $_GET['from'] : date('Y-m-01');
$to_date   = isset($_GET['to']) ? $_GET['to'] : date('Y-m-t');
?>

<div class="content-wrapper">
    <div class="container-fluid">
        <div class="main-header">
            <h4>Account Transactions</h4>
        </div>

        <div class="card">
            <div class="card-block">
                <form method="get" class="form-inline mb-3">
                    <label class="mr-2">From:</label>
                     <?php   if (isset($_GET['module']) && $_GET['module'] === 'report') {
                                        echo "<input type='hidden' name='module' value='report'>";  }?>
                    <input type="date" name="from" class="form-control mr-3" value="<?= $from_date ?>" required>
                    <label class="mr-2">To:</label>
                    <input type="date" name="to" class="form-control mr-3" value="<?= $to_date ?>" required>
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <button type="button" id="exportButton" filename="ACCOUNT_TRANSACTIONS.xlsx" class="btn btn-primary ml-2">
                        <i class="ti-cloud-down"></i> Export
                    </button>
                </form>
                <hr>

                <div class="table-responsive">
                    <table id="example" class="table table-bordered table-hover">
                        <thead class="thead-dark">
                            <tr>
                                <th style='width:60px;'>Date</th>
                                <th style='width:180px;'>Account</th>
                                <!-- <th>Group</th> -->
                                <th>Transaction Type</th>
                                <th>Reference</th>
                                <th>Memo</th>
                                <th>Party</th>
                                <th class="text-right">Debit</th>
                                <th class="text-right">Credit</th>
                                <th class="text-right">Input VAT</th>
                                <th class="text-right">Output VAT</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
<div align ='right'>
                    <table > 
                        <tr>  <tr style="background-color:#d3dace;">
                                <td colspan="7" class="text-right"><strong>Total</strong></td>
                                <td class="text-right"><strong>Dr: <span id="accountTransactionDebitTotal">0.00</span> ---- </strong></td>
                                <td class="text-right"><strong>Cr: <span id="accountTransactionCreditTotal">0.00</span></strong></td>
                            </tr>
                        </tr>
                    </table> </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    $('#example').DataTable({
        processing: true,
        serverSide: true,
        pageLength: 100,
        order: [[0, 'asc']],
        ajax: {
            url: 'ajax/list_account_transactions.php',
            type: 'POST',
            data: function (d) {
                d.from = <?= json_encode($from_date) ?>;
                d.to = <?= json_encode($to_date) ?>;
            },
            dataSrc: function (json) {
                $('#accountTransactionDebitTotal').text(json.totalDebit || '0.00');
                $('#accountTransactionCreditTotal').text(json.totalCredit || '0.00');
                return json.data || [];
            },
            error: function (xhr) { console.error('Account transaction load failed:', xhr.responseText); }
        },
        columns: [
            {data:'date'}, {data:'account'}, {data:'type'}, {data:'reference'}, {data:'memo'}, {data:'party'},
            {data:'debit', className:'text-right'}, {data:'credit', className:'text-right'},
            {data:'input_vat', className:'text-right'}, {data:'output_vat', className:'text-right'}
        ]
    });
});
</script>

<?php include 'footer.php'; ?>
