<?php
include 'header.php';

// Ensure $location_id is set in the header/session
$location_id = $location_id ?? 0;

// Default date range values
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

?>

<div class="content-wrapper">
    <div class="container-fluid">
        <div class="main-header">
            <h4>Card Sales Management</h4>
        </div>

  
  
      <div class="card">
            <div class="card-block">
                
            <!-- Date Range Filter -->
        <form method="get" class="form-inline mb-3">
            <label for="start_date">Start Date:&nbsp;</label>
            <input type="date" name="start_date" id="start_date" class="form-control mr-3" value="<?= htmlspecialchars($start_date) ?>" required>

            <label for="end_date">End Date:&nbsp;</label>
            <input type="date" name="end_date" id="end_date" class="form-control mr-3" value="<?= htmlspecialchars($end_date) ?>" required>

            <button type="submit" class="btn btn-primary mr-3">Filter</button>

            <button type="button" class="btn btn-primary" id="dayEndProcessBtn">Day End Process</button>
        </form>
        <hr>

        <!-- Bootstrap Table -->
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="thead-light">
                    <tr>
                        <th>#</th>
                        <th>No</th>
                        <th>Date</th>
                        <th>Value</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Example static rows, replace with your dynamic data -->
                    <tr>
                        <td>1</td>
                        <td>1001</td>
                        <td>2025-05-30</td>
                        <td>150.00</td>
                    </tr>
                    <tr>
                        <td>2</td>
                        <td>1002</td>
                        <td>2025-05-31</td>
                        <td>200.00</td>
                    </tr>
                    <tr>
                        <td>3</td>
                        <td>1003</td>
                        <td>2025-06-01</td>
                        <td>175.50</td>
                    </tr>
                </tbody>
            </table>
        </div>

    </div>
</div>

<!-- Blank Modal for Day End Process -->
<div class="modal fade" id="dayEndProcessModal" tabindex="-1" role="dialog" aria-labelledby="dayEndProcessLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="dayEndProcessLabel">Day End Process</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <!-- Blank content -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>


</div>
</div>
<script>
    // Open blank modal on button click
    document.getElementById('dayEndProcessBtn').addEventListener('click', function () {
        $('#dayEndProcessModal').modal('show');
    });
</script>

<?php include 'footer.php'; ?>
