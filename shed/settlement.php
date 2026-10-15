 <?php 
include 'header.php'; 

// === Handle Add New Settlement ===
 
if (isset($_POST['add_settlement'])) {
    $set_date   = $_POST['set_date'];
    $source     = $_POST['source'];
    $amount     = floatval($_POST['amount']);
    $memo       = trim($_POST['memo']);
    $settlement_datetime = $_POST['settlement_datetime'] ?? date('Y-m-d H:i:s');
    $user_id    = $user_id;      // from header/session
    $location_id = $location_id; // from header/session
    $created_on = date('Y-m-d H:i:s');
    $status     = 1;

    $insert = "INSERT INTO shed_settlement (location_id, set_date, source, amount, memo, updated_date, user_id, status, created_on) 
               VALUES ('$location_id', '$set_date', '$source', '$amount', '$memo', '$settlement_datetime', '$user_id', '$status', '$created_on')";
    mysqli_query($con, $insert);

    $detail = "Settlement added: Date $set_date, Source $source, Amount $amount, Memo $memo";
    UserLog('2', 'Settlement Added', $detail);

   
     echo "<script>
            notify('success', 'Settlement recorded successfully');
            setTimeout(function() {
                window.location.href = 'settlement.php?success=1';
            }, 1000); // wait 1s so user sees the message
          </script>";
 
    
}

// === Handle Cancel Settlement (AJAX) ===
if (isset($_POST['cancel_settlement_id'])) {
    $id = intval($_POST['cancel_settlement_id']);

    $check = mysqli_query($con, "SELECT created_on, status FROM shed_settlement WHERE id='$id' LIMIT 1");
    $row   = mysqli_fetch_assoc($check);

    header('Content-Type: application/json');

    if ($row && $row['status'] == 1) {
        $created_on = strtotime($row['created_on']);
        $now        = time();

        if (($now - $created_on) <= 86400) { // 24 hours
            mysqli_query($con, "UPDATE shed_settlement SET status=0 WHERE id='$id'");
            $detail = "Settlement $id cancelled";
            UserLog('2', 'Settlement Cancelled', $detail);

            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Cancellation allowed only within 24 hours']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid settlement or already cancelled']);
    }
    exit;
}

// === Default Date Range = This Month ===
$today = date('Y-m-d');
$first_day = date('Y-m-01');
$last_day  = date('Y-m-t'); // last day of month

$from_date  = $_GET['from_date'] ?? $first_day;
$to_date    = $_GET['to_date'] ?? $last_day;

// Get last active settlement date
$last_settlement_q = "SELECT MAX(set_date) as last_date 
                      FROM shed_settlement 
                      WHERE location_id='$location_id' AND status=1";
$last_res = mysqli_query($con, $last_settlement_q);
$last_row = mysqli_fetch_assoc($last_res);
$last_settlement_date = $last_row['last_date'] ?? $first_day;
?>

<div class="content-wrapper">
  <div class="container-fluid">
    <div class="main-header">
      <h4>Settlement Records</h4>
    </div>

    <div class="card"  style='padding-left:20px; padding-right:20px;'>
      <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
        <!-- Filter -->
        <form class="form-inline mb-2" method="GET" style="gap:10px;">
          <label>From:</label>
          <input type="date" name="from_date" class="form-control" value="<?=htmlspecialchars($from_date)?>" required>
          <label>To:</label>
          <input type="date" name="to_date" class="form-control" value="<?=htmlspecialchars($to_date)?>" required>
          <button type="submit" class="btn btn-info">Filter</button>
          <button type='button' id="exportButton" filename='<?php echo "Settlement_".$from_date."_".$to_date; ?>.xlsx' class="btn btn-info"><i class="ti-cloud-down"></i> Export</button>
        </form>
        &nbsp;<button class="btn btn-primary mb-2" data-toggle="modal" data-target="#add_settlement_modal">Add Settlement</button>
        
        <?php
              $q1 = "SELECT SUM(amount) as Total_settlement
                    FROM shed_settlement 
                    WHERE location_id='$location_id' AND set_date BETWEEN '2025-09-01' AND '$to_date' and status = 1
                    ORDER BY set_date ";
              $res1 = mysqli_query($con, $q1);
              $row1 = mysqli_fetch_assoc($res1);
              $q2 = "SELECT SUM(total_cash_settled) AS TotalCashSettled
                       FROM day_end_process
                       WHERE location_id = '$location_id' 
                         AND date_ended >= '2025-10-01'
                         AND status = 1";
                
                $res2 = mysqli_query($con, $q2);
                $row2 = mysqli_fetch_assoc($res2);
                $TotalCashSettled = $row2['TotalCashSettled'] ; $Total_settlement = $row1['Total_settlement'];
              $balance_payable = $TotalCashSettled - $Total_settlement;
              
              
              ?>
              <h1>Balance Cash <?php echo number_format($balance_payable,2); ?> </h1>(from 01/10/2025)
      </div>

      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-bordered table-striped" id="example">
            <thead class="thead-dark">
              <tr>
                <th>#</th>
                <th>Income Date</th>
                <th>Source</th>
                <th>Amount</th>
                <th>Cumulative</th>
                <th>Memo</th>
                <th>Settlement Date</th>
                <th>Updated Date</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $q = "SELECT id, set_date, source, amount, memo, created_on, updated_date, status 
                    FROM shed_settlement 
                    WHERE location_id='$location_id' AND set_date BETWEEN '$from_date' AND '$to_date'
                    ORDER BY set_date ";
              $res = mysqli_query($con, $q);
              $count=1;
              $total = 0;
              while($row = mysqli_fetch_assoc($res)){
                  $row_class = ($row['status']==0) ? 'class="table-danger cancelled-row"' : '';
                  echo "<tr $row_class>";
                  echo "<td>".$count++."</td>";
                  echo "<td>".htmlspecialchars($row['set_date'])."</td>";
                  echo "<td>".htmlspecialchars($row['source'])."</td>";
                  echo "<td align='right' >".number_format($row['amount'],2)."</td>";

                  if($row['status']==1){
                      $total += $row['amount'];
                      echo "<td align='right'>".number_format($total,2)."</td>";
                  } else {
                      echo "<td></td>";
                  }

                  echo "<td>".htmlspecialchars($row['memo'])."</td>";
                    echo "<td>".htmlspecialchars($row['updated_date'])."</td>";
                  echo "<td>".htmlspecialchars($row['created_on'])."</td>";
                

                  if($row['status']==1){
                      echo "<td align='center'><button class='btn btn-danger btn-sm cancel-settlement-btn' data-id='".$row['id']."'>Cancel</button></td>";
                  } else {
                      echo "<td align='center'>Cancelled</td>";
                  }
                  echo "</tr>";
              }
              ?>
            </tbody>
          </table>
        </div>
        <div class="mt-3">
          <label class="font-weight-bold">Total Settlement: <?= number_format($total, 2) ?></label>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Add Settlement Modal -->
<div class="modal" id="add_settlement_modal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-body">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="font-size:24px;">
          <span aria-hidden="true">&times;</span>
        </button>
        <h4 class="modal-title mb-3">Add Settlement</h4>
        <form method="POST" class="processing_form">
          <table width="100%">
            <tr>
              <td align="right">Income of :</td>
              <td>
                  <?php
// Define cutoff date
$cutoff_date = "2025-10-01";

// Take the later one between $last_settlement_date and cutoff
// $min_date = (strtotime($last_settlement_date) > strtotime($cutoff_date)) 
//               ? $last_settlement_date 
//               : $cutoff_date;
              
$min_date =  $cutoff_date;
?>


                <input type="date" name="set_date" class="form-control" 
                       value="<?=$last_settlement_date?>"
                       min="<?=$min_date?>"
                       max="<?=$today?>" required>
              </td>
            </tr>
            <tr>
              <td align="right">Settlement Date & Time:</td>
              <td>
                <input type="datetime-local" name="settlement_datetime" class="form-control" 
                       value="<?= date('Y-m-d\TH:i') ?>" 
                       required>
              </td>
            </tr>
            <tr>
              <td align="right">Source:</td>
              <td>
                <select name="source" class="form-control" required>
                  <option value="Cash" selected>Cash</option>
                  <option value="Cheque">Cheque</option>
                  <option value="Bills">Bills</option>
                </select>
              </td>
            </tr>
            <tr>
              <td align="right">Amount:</td>
              <td><input type="number" name="amount" class="form-control" min="0" step="0.01" required></td>
            </tr>
            <tr>
              <td align="right">Memo:</td>
              <td><textarea name="memo" class="form-control" rows="2" placeholder="Enter remarks if any..."></textarea></td>
            </tr>
            <tr>
              <td></td>
              <td>
                <input type="hidden" name="add_settlement" value="1">
                <button type="submit" class="btn btn-success mt-2 processing_button">Submit</button>
              </td>
            </tr>
          </table>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
$(document).ready(function(){
  $('.cancel-settlement-btn').on('click', function(){
    var id = $(this).data('id');
    var row = $(this).closest('tr');

    Swal.fire({
      title: 'Are you sure?',
      text: "Cancel this settlement?",
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, cancel it!',
      cancelButtonText: 'No'
    }).then((result)=>{
      if(result.isConfirmed){
        $.ajax({
          url: 'ajax/cancel_settlement.php',
          type: 'POST',
          data: { cancel_settlement_id: id },
          dataType: 'json',
          success: function(res){
            if(res.success){
              Swal.fire('Cancelled!','Settlement has been cancelled.','success').then(()=>{
                location.reload();  // refresh to update total
              });
            }else{
              Swal.fire('Error', res.message || 'Cancellation failed','error');
            }
          },
          error: function(xhr, status, error){
            Swal.fire('Error','Request failed: '+error,'error').then(()=>{
              location.reload(); // fallback refresh
            });
          }
        });
      }
    });
  });
});
</script>


 <script>
document.addEventListener("DOMContentLoaded", function () {
    let today    = new Date();               // current date
    let minDate  = new Date("2025-10-01");   // cutoff date

    if (today < minDate) {
        Swal.fire({
            icon: 'info',
            title: 'Notice',
            text: 'This window valid from 01/10/2025 onwards.',
            confirmButtonText: 'OK'
        });
    }
});
</script>

<script>
  $(document).ready(function() {
    $('#example').DataTable({
        "pageLength": 100
    });
});
</script>
<?php include 'footer.php'; ?>  
