<?php
require_once '../../db.php';
require_once '../../auth.php';
require_once '../../ensure_business_start_date.php';
header('Content-Type: application/json');
require_once __DIR__ . '/location.php';

try {
    $location = accounting_period_location_id($con);
    ensure_business_start_date_column($con);
} catch (Throwable $error) {
    error_log('Accounting period setup failed: ' . $error->getMessage());
    echo json_encode(['success' => false, 'msg' => 'Accounting period setup failed: ' . $error->getMessage()]);
    exit;
}

function parse_period_date($value)
{
    $value = trim((string)$value);
    foreach (['!d/m/Y', '!Y-m-d'] as $format) {
        $date = DateTime::createFromFormat($format, $value);
        $errors = DateTime::getLastErrors();
        if ($date && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
            return $date->format('Y-m-d');
        }
    }
    return false;
}

$action = $_POST['action'] ?? '';
$id = (int)($_POST['id'] ?? 0);
$period_from = parse_period_date($_POST['perid_from'] ?? '');
$period_to = parse_period_date($_POST['period_to'] ?? '');
$due_date = parse_period_date($_POST['due_date'] ?? '');
if (!$period_from || !$period_to || !$due_date || $period_from > $period_to) {
    echo json_encode(['success' => false, 'msg' => 'Enter valid dates and ensure Period To is not before Period From.']);
    exit;
}

try {
    $dates = mysqli_query($con, "SELECT business_start_date FROM client_registration WHERE c_id = $location LIMIT 1");
    if ($dates && ($client = mysqli_fetch_assoc($dates))) {
        $min_start = $client['business_start_date'] ?? '';
        if ($min_start && $period_from < $min_start) {
            echo json_encode(['success' => false, 'msg' => 'Period From cannot be before the business or bookkeeping start date (' . $min_start . ').']);
            exit;
        }
    }
} catch (Throwable $error) {
    error_log('Accounting period validation failed: ' . $error->getMessage());
    echo json_encode(['success' => false, 'msg' => 'Accounting period validation failed: ' . $error->getMessage()]);
    exit;
}

if ($action === 'add') {
    try {
        mysqli_begin_transaction($con);
        $lock = $con->prepare('UPDATE accounts_accounting_period SET lock_period = 1 WHERE location_id = ? AND status = 1');
        $lock->bind_param('i', $location);
        $lock->execute();
        $lock->close();

        $stmt = $con->prepare('INSERT INTO accounts_accounting_period (location_id, perid_from, period_to, due_date, lock_period, status) VALUES (?, ?, ?, ?, 0, 1)');
        $stmt->bind_param('isss', $location, $period_from, $period_to, $due_date);
        $stmt->execute();
        $stmt->close();

        mysqli_commit($con);
        echo json_encode(['success' => true, 'msg' => 'Accounting period added.']);
    } catch (Throwable $error) {
        if ($con->errno || $con->thread_id) {
            try { mysqli_rollback($con); } catch (Throwable $rollback_error) {}
        }
        error_log('Accounting period save failed: ' . $error->getMessage());
        echo json_encode(['success' => false, 'msg' => 'Could not add accounting period: ' . $error->getMessage()]);
    }
    exit;
}

if ($action === 'edit' && $id > 0) {
    try {
        $stmt = $con->prepare('UPDATE accounts_accounting_period SET perid_from = ?, period_to = ?, due_date = ? WHERE id = ? AND location_id = ? AND status = 1');
        $stmt->bind_param('sssii', $period_from, $period_to, $due_date, $id, $location);
        $ok = $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => $ok, 'msg' => $ok ? 'Accounting period updated.' : 'Could not update accounting period.']);
    } catch (Throwable $error) {
        error_log('Accounting period update failed: ' . $error->getMessage());
        echo json_encode(['success' => false, 'msg' => 'Could not update accounting period: ' . $error->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'msg' => 'Invalid request.']);
