<?php
require_once '../../db.php';
require_once '../../auth.php';
header('Content-Type: application/json; charset=utf-8');

function bind_deposit_params(mysqli_stmt $stmt, string $types, array &$params): void
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
$orderIndex = (int)($_POST['order'][0]['column'] ?? 1);
$orderDirection = strtolower((string)($_POST['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
$orderColumns = [0 => 'd.source_id', 1 => 'd.t_date', 2 => 'd.source_id', 3 => 'a.ca_name', 4 => 'd.amount', 5 => 'd.t_memo', 6 => 'd.status'];
$orderBy = $orderColumns[$orderIndex] ?? 'd.t_date';

$clientKey = (string)($_COOKIE['client_cook'] ?? '');
$clientStmt = $con->prepare('SELECT c_id FROM client_registration WHERE md5_client = ? LIMIT 1');
$clientStmt->bind_param('s', $clientKey);
$clientStmt->execute();
$clientStmt->bind_result($locationId);
$hasClient = $clientStmt->fetch();
$clientStmt->close();
if (!$hasClient || (int)$locationId < 1) {
    http_response_code(400);
    echo json_encode(['draw' => $draw, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'error' => 'Select a client before loading deposits.']);
    exit;
}
$locationId = (int)$locationId;
$from = DateTime::createFromFormat('!Y-m-d', $startDate);
$to = DateTime::createFromFormat('!Y-m-d', $endDate);
if (!$from || !$to || $from->format('Y-m-d') !== $startDate || $to->format('Y-m-d') !== $endDate) {
    echo json_encode(['draw' => $draw, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => []]);
    exit;
}

$status = $showCancelled ? 0 : 1;
$depositSource = "(SELECT source_id, MAX(t_date) AS t_date, MAX(t_memo) AS t_memo, SUM(debit) AS amount,
                         MAX(CASE WHEN credit = 0 AND debit > 0 THEN ca_id END) AS ca_id, MIN(status) AS status
                  FROM acc_transaction
                  WHERE source = 'Deposit' AND location_id = ? AND t_date >= ? AND t_date < DATE_ADD(?, INTERVAL 1 DAY) AND status = ?
                  GROUP BY source_id) d LEFT JOIN acc_chart_of_accounts a ON a.ca_id = d.ca_id";
$types = 'issi';
$params = [$locationId, $startDate, $endDate, $status];
$filter = '';
if ($search !== '') {
    $filter = ' WHERE (CAST(d.source_id AS CHAR) LIKE ? OR d.t_memo LIKE ? OR a.ca_name LIKE ? OR CAST(d.amount AS CHAR) LIKE ?)';
    $like = '%' . $search . '%';
    $types .= 'ssss';
    array_push($params, $like, $like, $like, $like);
}

$countSql = 'SELECT COUNT(*) FROM ' . $depositSource . $filter;
$countStmt = $con->prepare($countSql);
bind_deposit_params($countStmt, $types, $params);
$countStmt->execute();
$countStmt->bind_result($filteredCount);
$countStmt->fetch();
$countStmt->close();

$allStmt = $con->prepare("SELECT COUNT(DISTINCT source_id) FROM acc_transaction WHERE source = 'Deposit' AND location_id = ?");
$allStmt->bind_param('i', $locationId);
$allStmt->execute();
$allStmt->bind_result($totalCount);
$allStmt->fetch();
$allStmt->close();

$dataParams = array_merge($params, [$limit, $offset]);
$dataTypes = $types . 'ii';
$dataSql = 'SELECT d.source_id, d.t_date, d.t_memo, d.amount, d.ca_id, d.status, a.ca_name FROM ' . $depositSource . $filter .
           ' ORDER BY ' . $orderBy . ' ' . $orderDirection . ', d.source_id DESC LIMIT ? OFFSET ?';
$dataStmt = $con->prepare($dataSql);
bind_deposit_params($dataStmt, $dataTypes, $dataParams);
$dataStmt->execute();
$result = $dataStmt->get_result();
$rows = [];
$index = $offset + 1;
while ($deposit = $result->fetch_assoc()) {
    $statusValue = (int)$deposit['status'];
    $options = '<option value="">Select action</option>';
    if ($statusValue === 1) $options .= '<option value="edit">Edit</option><option value="cancel">Cancel</option>';
    $rows[] = [
        'index' => $index++,
        'date' => date('d-m-Y', strtotime($deposit['t_date'])),
        'from_cash' => 'Cash in Hand',
        'to_account' => htmlspecialchars((string)$deposit['ca_name'], ENT_QUOTES, 'UTF-8'),
        'amount' => number_format((float)$deposit['amount'], 2),
        'memo' => htmlspecialchars((string)$deposit['t_memo'], ENT_QUOTES, 'UTF-8'),
        'status' => $statusValue === 1 ? 'Posted' : 'Cancelled',
        'status_value' => $statusValue,
        'action' => '<select class="form-control form-control-sm deposit-action" data-id="' . (int)$deposit['source_id'] . '" data-status="' . $statusValue . '">' . $options . '</select>'
    ];
}
$dataStmt->close();
echo json_encode(['draw' => $draw, 'recordsTotal' => (int)$totalCount, 'recordsFiltered' => (int)$filteredCount, 'data' => $rows], JSON_INVALID_UTF8_SUBSTITUTE);
