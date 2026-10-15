<?php
require_once '../../db.php';
require_once '../../auth.php';
header('Content-Type: application/json');
require_once __DIR__ . '/location.php';

$id = (int)($_POST['id'] ?? 0);
$location = accounting_period_location_id($con);
$stmt = $con->prepare('SELECT id, perid_from, period_to, due_date, lock_period, status FROM accounts_accounting_period WHERE id = ? AND location_id = ? AND status = 1 LIMIT 1');
$stmt->bind_param('ii', $id, $location);
$stmt->execute();
$result = $stmt->get_result();
$period = $result->fetch_assoc();
$stmt->close();

echo $period
    ? json_encode(['success' => true, 'data' => $period])
    : json_encode(['success' => false, 'msg' => 'Accounting period not found.']);
