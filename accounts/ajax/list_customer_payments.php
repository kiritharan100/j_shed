<?php
require_once '../../db.php';
require_once '../../auth.php';
header('Content-Type: application/json; charset=utf-8');

function bind_payment_list_params(mysqli_stmt $stmt, string $types, array &$params): void
{
    $args = [$types];
    foreach ($params as &$param) $args[] = &$param;
    call_user_func_array([$stmt, 'bind_param'], $args);
}

$draw = (int)($_POST['draw'] ?? 1);
$offset = max(0, (int)($_POST['start'] ?? 0));
$limit = (int)($_POST['length'] ?? 50);
$limit = $limit < 1 ? 50 : min($limit, 500);
$startDate = trim((string)($_POST['start_date'] ?? ''));
$endDate = trim((string)($_POST['end_date'] ?? ''));
$search = trim((string)($_POST['search']['value'] ?? ''));
$showCancelled = (string)($_POST['show_cancelled'] ?? '0') === '1';
$orderIndex = (int)($_POST['order'][0]['column'] ?? 2);
$direction = strtolower((string)($_POST['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
$orderColumns = [0 => 'cp.cp_id', 1 => 'cp.receipt_no', 2 => 'cp.rec_date', 3 => 'c.customer_name', 4 => 'cp.cheque_no', 5 => 'cp.rec_amount', 6 => 'cp.rec_status'];
$orderBy = $orderColumns[$orderIndex] ?? 'cp.rec_date';

$clientKey = (string)($_COOKIE['client_cook'] ?? '');
$clientStmt = $con->prepare('SELECT c_id FROM client_registration WHERE md5_client = ? LIMIT 1');
$clientStmt->bind_param('s', $clientKey);
$clientStmt->execute();
$clientStmt->bind_result($locationId);
$hasClient = $clientStmt->fetch();
$clientStmt->close();
if (!$hasClient || (int)$locationId < 1) {
    http_response_code(400);
    echo json_encode(['draw' => $draw, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'error' => 'Select a client before loading customer payments.']);
    exit;
}
$locationId = (int)$locationId;
$from = DateTime::createFromFormat('!Y-m-d', $startDate);
$to = DateTime::createFromFormat('!Y-m-d', $endDate);
if (!$from || !$to || $from->format('Y-m-d') !== $startDate || $to->format('Y-m-d') !== $endDate) {
    echo json_encode(['draw' => $draw, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'totalPayments' => '0.00']);
    exit;
}

$where = 'cp.location_id = ? AND cp.rec_date >= ? AND cp.rec_date < DATE_ADD(?, INTERVAL 1 DAY)';
$types = 'iss';
$params = [$locationId, $startDate, $endDate];
if (!$showCancelled) $where .= ' AND cp.rec_status = 1';
$joins = ' FROM acc_customer_payment cp LEFT JOIN mange_customer c ON c.c_id = cp.c_id';
$searchSql = '';
$searchTypes = '';
$searchParams = [];
if ($search !== '') {
    $searchSql = " AND (CAST(cp.receipt_no AS CHAR) LIKE ? OR COALESCE(c.customer_name, '') LIKE ? OR cp.cheque_no LIKE ? OR cp.Memo LIKE ? OR CAST(cp.rec_amount AS CHAR) LIKE ?)";
    $like = '%' . $search . '%';
    $searchTypes = 'sssss';
    $searchParams = [$like, $like, $like, $like, $like];
}

$countParams = array_merge($params, $searchParams);
$countStmt = $con->prepare('SELECT COUNT(*)' . $joins . ' WHERE ' . $where . $searchSql);
bind_payment_list_params($countStmt, $types . $searchTypes, $countParams);
$countStmt->execute();
$countStmt->bind_result($filteredCount);
$countStmt->fetch();
$countStmt->close();

$allStmt = $con->prepare('SELECT COUNT(*) FROM acc_customer_payment WHERE location_id = ?');
$allStmt->bind_param('i', $locationId);
$allStmt->execute();
$allStmt->bind_result($totalCount);
$allStmt->fetch();
$allStmt->close();

$sumStmt = $con->prepare('SELECT COALESCE(SUM(rec_amount), 0) FROM acc_customer_payment WHERE location_id = ? AND rec_date >= ? AND rec_date < DATE_ADD(?, INTERVAL 1 DAY) AND rec_status = 1');
$sumStmt->bind_param('iss', $locationId, $startDate, $endDate);
$sumStmt->execute();
$sumStmt->bind_result($totalPayments);
$sumStmt->fetch();
$sumStmt->close();

$dataParams = array_merge($params, $searchParams, [$limit, $offset]);
$dataTypes = $types . $searchTypes . 'ii';
$sql = 'SELECT cp.cp_id, cp.receipt_no, cp.rec_date, cp.cheque_no, cp.rec_amount, cp.Memo, cp.rec_status, c.customer_name'
     . $joins . ' WHERE ' . $where . $searchSql . ' ORDER BY ' . $orderBy . ' ' . $direction . ', cp.cp_id DESC LIMIT ? OFFSET ?';
$dataStmt = $con->prepare($sql);
bind_payment_list_params($dataStmt, $dataTypes, $dataParams);
$dataStmt->execute();
$result = $dataStmt->get_result();
$rows = [];
$index = $offset + 1;
while ($payment = $result->fetch_assoc()) {
    $statusValue = (int)$payment['rec_status'];
    $options = '<option value="">Select action</option><option value="view">View</option>';
    if ($statusValue === 1) $options .= '<option value="edit">Edit</option><option value="cancel">Cancel</option>';
    $rows[] = [
        'index' => $index++,
        'receipt_no' => htmlspecialchars((string)$payment['receipt_no'], ENT_QUOTES, 'UTF-8'),
        'date' => date('d-m-Y', strtotime($payment['rec_date'])),
        'customer' => htmlspecialchars((string)$payment['customer_name'], ENT_QUOTES, 'UTF-8'),
        'cheque_no' => htmlspecialchars((string)$payment['cheque_no'], ENT_QUOTES, 'UTF-8'),
        'amount' => number_format((float)$payment['rec_amount'], 2),
        'status' => $statusValue === 1 ? 'Posted' : 'Cancelled',
        'status_value' => $statusValue,
        'action' => '<select class="form-control form-control-sm payment-action" data-id="' . (int)$payment['cp_id'] . '" data-status="' . $statusValue . '">' . $options . '</select>'
    ];
}
$dataStmt->close();

echo json_encode(['draw' => $draw, 'recordsTotal' => (int)$totalCount, 'recordsFiltered' => (int)$filteredCount, 'data' => $rows, 'totalPayments' => number_format((float)$totalPayments, 2, '.', '')], JSON_INVALID_UTF8_SUBSTITUTE);
