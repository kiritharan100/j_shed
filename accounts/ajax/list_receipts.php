<?php
require_once '../../db.php';
require_once '../../auth.php';
header('Content-Type: application/json; charset=utf-8');

function bind_receipt_params(mysqli_stmt $stmt, string $types, array &$params): void
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
$customerAdvance = (string)($_POST['customer_advance'] ?? '0') === '1';
$orderIndex = (int)($_POST['order'][0]['column'] ?? 2);
$orderDirection = strtolower((string)($_POST['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
$orderColumns = [0 => 'c.loc_no', 1 => 'c.loc_no', 2 => 'c.ch_date', 3 => 'c.cheque_number', 4 => 'bank.ca_name', 5 => 'detail.account_names', 6 => 'customer.customer_name', 7 => 'c.sub_total', 8 => 'c.status'];
$orderBy = $orderColumns[$orderIndex] ?? 'c.ch_date';

$clientKey = (string)($_COOKIE['client_cook'] ?? '');
$clientStmt = $con->prepare('SELECT c_id FROM client_registration WHERE md5_client = ? LIMIT 1');
$clientStmt->bind_param('s', $clientKey);
$clientStmt->execute();
$clientStmt->bind_result($locationId);
$hasClient = $clientStmt->fetch();
$clientStmt->close();
if (!$hasClient || (int)$locationId < 1) {
    http_response_code(400);
    echo json_encode(['draw' => $draw, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'error' => 'Select a client before loading receipts.']);
    exit;
}
$locationId = (int)$locationId;
$from = DateTime::createFromFormat('!Y-m-d', $startDate);
$to = DateTime::createFromFormat('!Y-m-d', $endDate);
if (!$from || !$to || $from->format('Y-m-d') !== $startDate || $to->format('Y-m-d') !== $endDate) {
    echo json_encode(['draw' => $draw, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'totalReceipts' => '0.00']);
    exit;
}

$status = $showCancelled ? 0 : 1;
$customerFilter = $customerAdvance ? 'c.supplier_id > 0' : 'COALESCE(c.supplier_id, 0) = 0';
$where = 'c.location_id = ? AND c.ch_date >= ? AND c.ch_date < DATE_ADD(?, INTERVAL 1 DAY) AND c.status = ? AND ' . $customerFilter;
$types = 'issi';
$params = [$locationId, $startDate, $endDate, $status];
$joins = ' FROM acc_receipt c
    LEFT JOIN acc_chart_of_accounts bank ON bank.ca_id = c.dr_acc_id
    LEFT JOIN mange_customer customer ON customer.c_id = c.supplier_id
    LEFT JOIN (SELECT d.re_id, COUNT(DISTINCT d.dr_acc_id) AS account_count,
                      GROUP_CONCAT(DISTINCT coa.ca_name ORDER BY coa.ca_name SEPARATOR ", ") AS account_names
               FROM acc_receipt_detail d
               LEFT JOIN acc_chart_of_accounts coa ON coa.ca_id = d.dr_acc_id
               GROUP BY d.re_id) detail ON detail.re_id = c.re_id ';

$allStmt = $con->prepare('SELECT COUNT(*) FROM acc_receipt c WHERE ' . $where);
bind_receipt_params($allStmt, $types, $params);
$allStmt->execute();
$allStmt->bind_result($totalCount);
$allStmt->fetch();
$allStmt->close();

$sumSql = 'SELECT COALESCE(SUM(c.sub_total), 0) FROM acc_receipt c WHERE c.location_id = ? AND c.ch_date >= ? AND c.ch_date < DATE_ADD(?, INTERVAL 1 DAY) AND c.status = 1 AND ' . $customerFilter;
$sumStmt = $con->prepare($sumSql);
$sumParams = [$locationId, $startDate, $endDate];
bind_receipt_params($sumStmt, 'iss', $sumParams);
$sumStmt->execute();
$sumStmt->bind_result($totalReceipts);
$sumStmt->fetch();
$sumStmt->close();

$searchSql = '';
$dataTypes = $types;
$dataParams = $params;
if ($search !== '') {
    $searchSql = " AND (CAST(c.loc_no AS CHAR) LIKE ? OR c.cheque_number LIKE ? OR COALESCE(customer.customer_name, '') LIKE ? OR COALESCE(bank.ca_name, '') LIKE ? OR COALESCE(detail.account_names, '') LIKE ? OR COALESCE(c.memo, '') LIKE ? OR CAST(c.sub_total AS CHAR) LIKE ?)";
    $like = '%' . $search . '%';
    $dataTypes .= 'sssssss';
    array_push($dataParams, $like, $like, $like, $like, $like, $like, $like);
}
$countTypes = $types . ($search !== '' ? 'sssssss' : '');
$countParams = $params;
if ($search !== '') array_push($countParams, $like, $like, $like, $like, $like, $like, $like);
$countStmt = $con->prepare('SELECT COUNT(*)' . $joins . ' WHERE ' . $where . $searchSql);
bind_receipt_params($countStmt, $countTypes, $countParams);
$countStmt->execute();
$countStmt->bind_result($filteredCount);
$countStmt->fetch();
$countStmt->close();

$dataSql = 'SELECT c.re_id, c.loc_no, c.ch_date, c.cheque_number, c.sub_total, c.status, c.supplier_id, c.memo,
                   bank.ca_name AS from_account, customer.customer_name,
                   COALESCE(detail.account_count, 0) AS account_count, COALESCE(detail.account_names, "") AS account_names'
    . $joins . ' WHERE ' . $where . $searchSql . ' ORDER BY ' . $orderBy . ' ' . $orderDirection . ', c.re_id DESC LIMIT ? OFFSET ?';
$dataTypes .= 'ii';
array_push($dataParams, $limit, $offset);
$dataStmt = $con->prepare($dataSql);
bind_receipt_params($dataStmt, $dataTypes, $dataParams);
$dataStmt->execute();
$result = $dataStmt->get_result();
$rows = [];
$index = $offset + 1;
while ($receipt = $result->fetch_assoc()) {
    $statusValue = (int)$receipt['status'];
    $customerNote = ((int)$receipt['supplier_id'] > 0 && $receipt['customer_name']) ? ' : ' . $receipt['customer_name'] : '';
    $creditAccount = (int)$receipt['account_count'] === 1 ? $receipt['account_names'] . $customerNote : '- Split -' . $customerNote;
    $options = '<option value="">Select action</option><option value="view">View</option>';
    if ($statusValue === 1) $options .= '<option value="edit">Edit</option><option value="delete">Delete</option>';
    $rows[] = [
        'index' => $index++,
        'ref_no' => 'REC-' . htmlspecialchars((string)$receipt['loc_no'], ENT_QUOTES, 'UTF-8'),
        'date' => date('d-m-Y', strtotime($receipt['ch_date'])),
        'cheque_no' => htmlspecialchars((string)$receipt['cheque_number'], ENT_QUOTES, 'UTF-8'),
        'from_account' => htmlspecialchars((string)$receipt['from_account'], ENT_QUOTES, 'UTF-8'),
        'credit_account' => htmlspecialchars((string)$creditAccount, ENT_QUOTES, 'UTF-8'),
        'customer' => htmlspecialchars((string)$receipt['customer_name'], ENT_QUOTES, 'UTF-8'),
        'amount' => number_format((float)$receipt['sub_total'], 2),
        'status' => $statusValue === 1 ? 'Posted' : 'Cancelled',
        'status_value' => $statusValue,
        'action' => '<select class="form-control form-control-sm receipt-action" data-id="' . (int)$receipt['re_id'] . '" data-status="' . $statusValue . '">' . $options . '</select>'
    ];
}
$dataStmt->close();

echo json_encode([
    'draw' => $draw,
    'recordsTotal' => (int)$totalCount,
    'recordsFiltered' => (int)$filteredCount,
    'data' => $rows,
    'totalReceipts' => number_format((float)$totalReceipts, 2, '.', '')
], JSON_INVALID_UTF8_SUBSTITUTE);
