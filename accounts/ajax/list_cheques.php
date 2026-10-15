<?php
require_once '../../db.php';
require_once '../../auth.php';
header('Content-Type: application/json; charset=utf-8');

function bind_dynamic_params(mysqli_stmt $stmt, string $types, array &$params): void
{
    $arguments = [$types];
    foreach ($params as &$param) $arguments[] = &$param;
    call_user_func_array([$stmt, 'bind_param'], $arguments);
}

$draw = (int)($_POST['draw'] ?? 1);
$start = max(0, (int)($_POST['start'] ?? 0));
$length = (int)($_POST['length'] ?? 25);
$length = $length < 1 ? 25 : min($length, 100);
$search = trim((string)($_POST['search']['value'] ?? ''));
$orderIndex = (int)($_POST['order'][0]['column'] ?? 2);
$orderDirection = strtolower((string)($_POST['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
$orderColumns = [0 => 'c.loc_no', 1 => 'c.loc_no', 2 => 'c.ch_date', 3 => 'c.cheque_number', 4 => 'a.ca_name', 5 => 's.supplier_name', 6 => 'c.sub_total', 7 => 'c.status'];
$orderBy = $orderColumns[$orderIndex] ?? 'c.ch_date';
$startDate = trim((string)($_POST['start_date'] ?? ''));
$endDate = trim((string)($_POST['end_date'] ?? ''));
$status = (string)($_POST['status'] ?? 'all');

$selectedClient = trim((string)($_COOKIE['client_cook'] ?? ''));
$clientStmt = $con->prepare('SELECT c_id FROM client_registration WHERE md5_client = ? LIMIT 1');
$clientStmt->bind_param('s', $selectedClient);
$clientStmt->execute();
$clientStmt->bind_result($locationId);
$hasClient = $clientStmt->fetch();
$clientStmt->close();
if (!$hasClient || (int)$locationId < 1) {
    http_response_code(400);
    echo json_encode(['draw' => $draw, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'error' => 'Select a client before loading cheques.']);
    exit;
}
$locationId = (int)$locationId;

$dateFrom = DateTime::createFromFormat('!Y-m-d', $startDate);
$dateTo = DateTime::createFromFormat('!Y-m-d', $endDate);
if (!$dateFrom || !$dateTo || $dateFrom->format('Y-m-d') !== $startDate || $dateTo->format('Y-m-d') !== $endDate) {
    echo json_encode(['draw' => $draw, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'totalPayments' => '0.00']);
    exit;
}

$where = 'c.location_id = ? AND c.ch_date >= ? AND c.ch_date < DATE_ADD(?, INTERVAL 1 DAY)';
$types = 'iss';
$params = [$locationId, $startDate, $endDate];
if ($status === '0' || $status === '1') {
    $where .= ' AND c.status = ?';
    $types .= 'i';
    $params[] = (int)$status;
}
if ($search !== '') {
    $where .= " AND (CAST(c.loc_no AS CHAR) LIKE ? OR c.cheque_number LIKE ? OR COALESCE(s.supplier_name, '') LIKE ? OR COALESCE(a.ca_name, '') LIKE ? OR CAST(c.sub_total AS CHAR) LIKE ?)";
    $like = '%' . $search . '%';
    $types .= 'sssss';
    array_push($params, $like, $like, $like, $like, $like);
}

$baseJoins = ' FROM acc_cheque c
    LEFT JOIN acc_chart_of_accounts a ON a.ca_id = c.cr_acc_id
    LEFT JOIN manage_supplier s ON s.sup_id = c.supplier_id
    LEFT JOIN (SELECT d.ch_id, COUNT(DISTINCT d.dr_acc_id) AS account_count, GROUP_CONCAT(DISTINCT coa.ca_name ORDER BY coa.ca_name SEPARATOR ", ") AS account_names
               FROM acc_cheque_detail d LEFT JOIN acc_chart_of_accounts coa ON coa.ca_id = d.dr_acc_id GROUP BY d.ch_id) detail ON detail.ch_id = c.ch_id ';

$countStmt = $con->prepare('SELECT COUNT(*)' . $baseJoins . ' WHERE ' . $where);
bind_dynamic_params($countStmt, $types, $params);
$countStmt->execute();
$countStmt->bind_result($filteredCount);
$countStmt->fetch();
$countStmt->close();

$allStmt = $con->prepare('SELECT COUNT(*) FROM acc_cheque WHERE location_id = ? AND ch_date >= ? AND ch_date < DATE_ADD(?, INTERVAL 1 DAY)');
$allStmt->bind_param('iss', $locationId, $startDate, $endDate);
$allStmt->execute();
$allStmt->bind_result($totalCount);
$allStmt->fetch();
$allStmt->close();

$sumStmt = $con->prepare('SELECT COALESCE(SUM(sub_total), 0) FROM acc_cheque WHERE location_id = ? AND status = 1 AND ch_date >= ? AND ch_date < DATE_ADD(?, INTERVAL 1 DAY)');
$sumStmt->bind_param('iss', $locationId, $startDate, $endDate);
$sumStmt->execute();
$sumStmt->bind_result($totalPayments);
$sumStmt->fetch();
$sumStmt->close();

$sql = 'SELECT c.ch_id, c.loc_no, c.ch_date, c.cheque_number, c.sub_total, c.status, c.supplier_id,
        a.ca_name AS from_account, s.supplier_name,
        COALESCE(detail.account_count, 0) AS account_count, COALESCE(detail.account_names, "") AS account_names'
        . $baseJoins . ' WHERE ' . $where . ' ORDER BY ' . $orderBy . ' ' . $orderDirection . ', c.ch_id DESC LIMIT ? OFFSET ?';
$dataStmt = $con->prepare($sql);
$dataTypes = $types . 'ii';
$dataParams = array_merge($params, [$length, $start]);
bind_dynamic_params($dataStmt, $dataTypes, $dataParams);
$dataStmt->execute();
$result = $dataStmt->get_result();
$rows = [];
$index = $start + 1;
while ($row = $result->fetch_assoc()) {
    $id = (int)$row['ch_id'];
    $rowStatus = (int)$row['status'];
    $supplierNote = ((int)$row['supplier_id'] > 0 && $row['supplier_name']) ? ' : ' . $row['supplier_name'] : '';
    $postedTo = (int)$row['account_count'] === 1 ? $row['account_names'] . $supplierNote : '- Split -' . $supplierNote;
    $options = '<option value="">Select action</option><option value="view">View</option>';
    if ($rowStatus === 1) $options .= '<option value="edit">Edit</option><option value="cancel">Cancel</option>';
    $action = '<select class="form-control form-control-sm cheque-action" data-id="' . $id . '" data-status="' . $rowStatus . '">' . $options . '</select>';
    $rows[] = [
        'index' => $index++,
        'ref_no' => 'CH' . htmlspecialchars((string)$row['loc_no'], ENT_QUOTES, 'UTF-8'),
        'date' => date('d-m-Y', strtotime($row['ch_date'])),
        'cheque_no' => htmlspecialchars((string)$row['cheque_number'], ENT_QUOTES, 'UTF-8'),
        'from_account' => htmlspecialchars((string)$row['from_account'], ENT_QUOTES, 'UTF-8'),
        'paid_to' => htmlspecialchars((string)$postedTo, ENT_QUOTES, 'UTF-8'),
        'amount' => number_format((float)$row['sub_total'], 2),
        'status' => $rowStatus === 1 ? 'Posted' : 'Cancelled',
        'status_value' => $rowStatus,
        'action' => $action,
    ];
}
$dataStmt->close();

echo json_encode([
    'draw' => $draw,
    'recordsTotal' => (int)$totalCount,
    'recordsFiltered' => (int)$filteredCount,
    'data' => $rows,
    'totalPayments' => number_format((float)$totalPayments, 2, '.', '')
], JSON_INVALID_UTF8_SUBSTITUTE);
