<?php
include("../../db.php");
include("../../auth.php");
require_once __DIR__ . '/../transaction_date_cookie.php';

header('Content-Type: application/json');

$selected_client = (string)($_COOKIE['client_cook'] ?? '');
$client_stmt = $con->prepare('SELECT c_id FROM client_registration WHERE md5_client = ? LIMIT 1');
$client_stmt->bind_param('s', $selected_client);
$client_stmt->execute();
$client_stmt->bind_result($location_id);
$has_client = $client_stmt->fetch();
$client_stmt->close();
if (!$has_client || (int)$location_id < 1) {
    echo json_encode(['status' => 'error', 'message' => 'Select a client before recording a deposit.']);
    exit;
}
$source_id = (int)($_POST['source_id'] ?? 0);
$t_date      = $_POST['deposit_date'] ?? date('Y-m-d');
$to_account  = $_POST['to_account'] ?? 0;
$amount      = floatval($_POST['amount'] ?? 0);
$memo        = mysqli_real_escape_string($con, $_POST['memo'] ?? '');
$user_id     = $_SESSION['user_id'];
$now         = date('Y-m-d H:i:s');

// Basic validation
if ($to_account <= 0 || $amount <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid input.']);
    exit;
}

if ($source_id > 0) {
    try {
        mysqli_begin_transaction($con);
        $check = $con->prepare("SELECT COUNT(*) FROM acc_transaction WHERE source = 'Deposit' AND source_id = ? AND location_id = ? AND status = 1 AND ((credit = 0 AND debit > 0) OR (ca_id = 7 AND debit = 0 AND credit > 0))");
        $check->bind_param('ii', $source_id, $location_id);
        $check->execute();
        $check->bind_result($row_count);
        $check->fetch();
        $check->close();
        if ((int)$row_count < 2) {
            mysqli_rollback($con);
            echo json_encode(['status' => 'error', 'message' => 'Deposit not found or already cancelled.']);
            exit;
        }

        $debit = $con->prepare("UPDATE acc_transaction SET ca_id = ?, t_date = ?, debit = ?, t_memo = ? WHERE source = 'Deposit' AND source_id = ? AND location_id = ? AND status = 1 AND credit = 0 AND debit > 0");
        $debit->bind_param('isdsii', $to_account, $t_date, $amount, $memo, $source_id, $location_id);
        $debit->execute();
        $debit->close();

        $credit = $con->prepare("UPDATE acc_transaction SET t_date = ?, credit = ?, t_memo = ? WHERE source = 'Deposit' AND source_id = ? AND location_id = ? AND status = 1 AND ca_id = 7 AND debit = 0 AND credit > 0");
        $credit->bind_param('sdsii', $t_date, $amount, $memo, $source_id, $location_id);
        $credit->execute();
        $credit->close();
        mysqli_commit($con);
        remember_shared_transaction_date($t_date);
        echo json_encode(['status' => 'success', 'message' => 'Deposit updated successfully.']);
    } catch (Throwable $error) {
        try { mysqli_rollback($con); } catch (Throwable $rollback_error) {}
        error_log('Deposit update failed: ' . $error->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Could not update deposit: ' . $error->getMessage()]);
    }
    exit;
}

// 🔢 Generate next unique source_id (Deposit ID) for location
$get_max = mysqli_query($con, "SELECT IFNULL(MAX(source_id), 0) AS last_id 
                               FROM acc_transaction 
                               WHERE source = 'Deposit' AND location_id = '$location_id'");
$row = mysqli_fetch_assoc($get_max);
$next_id = intval($row['last_id']) + 1;
$ref_no = $next_id;

// 📝 Create two transaction entries

$entries = [
    // Debit to selected account (Cash in Hand category, not ca_id 7)
    "INSERT INTO acc_transaction 
        (location_id, ca_id, t_date, tra_type, source, source_id, ref_no, debit, credit, t_memo, credted_by, created_on, status)
     VALUES 
        ('$location_id', '$to_account', '$t_date', 'Deposit', 'Deposit', '$next_id', '$ref_no', '$amount', 0, '$memo', '$user_id', '$now', 1)",

    // Credit from Cash in Hand (ca_id = 7)
    "INSERT INTO acc_transaction 
        (location_id, ca_id, t_date, tra_type, source, source_id, ref_no, debit, credit, t_memo, credted_by, created_on, status)
     VALUES 
        ('$location_id', 7, '$t_date', 'Deposit', 'Deposit', '$next_id', '$ref_no', 0, '$amount', '$memo', '$user_id', '$now', 1)"
];

// Execute both entries
foreach ($entries as $query) {
    if (!mysqli_query($con, $query)) {
        echo json_encode(['status' => 'error', 'message' => 'Error saving transaction.']);
        exit;
    }
}

remember_shared_transaction_date($t_date);
echo json_encode(['status' => 'success', 'message' => 'Deposit recorded successfully.']);
?>
