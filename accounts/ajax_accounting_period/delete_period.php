<?php
require_once '../../db.php';
require_once '../../auth.php';
header('Content-Type: application/json');
require_once __DIR__ . '/location.php';

$id = (int)($_POST['id'] ?? 0);
$location = accounting_period_location_id($con);
$stmt = $con->prepare('UPDATE accounts_accounting_period SET status = 0 WHERE id = ? AND location_id = ? AND status = 1');
$stmt->bind_param('ii', $id, $location);
$ok = $id > 0 && $stmt->execute() && $stmt->affected_rows > 0;
$stmt->close();
echo json_encode(['success' => $ok, 'msg' => $ok ? 'Accounting period deleted.' : 'Accounting period not found.']);
