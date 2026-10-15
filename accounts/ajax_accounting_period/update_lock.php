<?php
require_once '../../db.php';
require_once '../../auth.php';
header('Content-Type: application/json');
require_once __DIR__ . '/location.php';

$id = (int)($_POST['id'] ?? 0);
$state = (int)($_POST['state'] ?? 0) === 1 ? 1 : 0;
$location = accounting_period_location_id($con);
$stmt = $con->prepare('UPDATE accounts_accounting_period SET lock_period = ? WHERE id = ? AND location_id = ? AND status = 1');
$stmt->bind_param('iii', $state, $id, $location);
$ok = $id > 0 && $stmt->execute() && $stmt->affected_rows >= 0;
$stmt->close();
echo json_encode(['success' => $ok, 'msg' => $ok ? ($state ? 'Period locked.' : 'Period unlocked.') : 'Accounting period not found.']);
