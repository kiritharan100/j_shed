<?php
require_once '../../db.php';
require_once '../../auth.php';
header('Content-Type: application/json; charset=utf-8');

function bind_journal_params(mysqli_stmt $stmt, string $types, array &$params): void
{
    $args = [$types];
    foreach ($params as &$param) $args[] = &$param;
    call_user_func_array([$stmt, 'bind_param'], $args);
}

$draw = (int)($_POST['draw'] ?? 1);
$offset = max(0, (int)($_POST['start'] ?? 0));
$limit = (int)($_POST['length'] ?? 25);
$limit = $limit < 1 ? 25 : min($limit, 500);
$startDate = trim((string)($_POST['start_date'] ?? ''));
$endDate = trim((string)($_POST['end_date'] ?? ''));
$search = trim((string)($_POST['search']['value'] ?? ''));
$showCancelled = (string)($_POST['show_deleted'] ?? '0') === '1';
$orderIndex = (int)($_POST['order'][0]['column'] ?? 1);
$orderDirection = strtolower((string)($_POST['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
$orderColumns = [0 => 'j.id', 1 => 'j.journal_date', 2 => 'j.loc_no', 3 => 'j.memo', 4 => 'j.total_credit', 5 => 'j.status'];
$orderBy = $orderColumns[$orderIndex] ?? 'j.journal_date';

$clientKey = (string)($_COOKIE['client_cook'] ?? '');
$clientStmt = $con->prepare('SELECT c_id FROM client_registration WHERE md5_client = ? LIMIT 1');
$clientStmt->bind_param('s', $clientKey);
$clientStmt->execute();
$clientStmt->bind_result($locationId);
$hasClient = $clientStmt->fetch();
$clientStmt->close();
if (!$hasClient || (int)$locationId < 1) {
    http_response_code(400);
    echo json_encode(['draw' => $draw, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'error' => 'Select a client before loading journals.']);
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
$where = 'location_id = ? AND journal_date >= ? AND journal_date < DATE_ADD(?, INTERVAL 1 DAY) AND status = ?';
$types = 'issi';
$params = [$locationId, $startDate, $endDate, $status];
if ($search !== '') {
    $where .= " AND (memo LIKE ? OR CAST(loc_no AS CHAR) LIKE ? OR DATE_FORMAT(journal_date, '%d-%m-%Y') LIKE ? OR CAST(total_credit AS CHAR) LIKE ? OR CAST(total_debit AS CHAR) LIKE ?)";
    $like = '%' . $search . '%';
    $types .= 'sssss';
    array_push($params, $like, $like, $like, $like, $like);
}

$countStmt = $con->prepare('SELECT COUNT(*) FROM acc_journal WHERE ' . $where);
bind_journal_params($countStmt, $types, $params);
$countStmt->execute();
$countStmt->bind_result($filteredCount);
$countStmt->fetch();
$countStmt->close();

$allStmt = $con->prepare('SELECT COUNT(*) FROM acc_journal WHERE location_id = ?');
$allStmt->bind_param('i', $locationId);
$allStmt->execute();
$allStmt->bind_result($totalCount);
$allStmt->fetch();
$allStmt->close();

$sql = 'SELECT j.id, j.loc_no, j.journal_date, j.memo, j.total_debit, j.total_credit, j.status FROM acc_journal j WHERE ' . $where . ' ORDER BY ' . $orderBy . ' ' . $orderDirection . ', j.id DESC LIMIT ? OFFSET ?';
$dataParams = array_merge($params, [$limit, $offset]);
$dataTypes = $types . 'ii';
$dataStmt = $con->prepare($sql);
bind_journal_params($dataStmt, $dataTypes, $dataParams);
$dataStmt->execute();
$result = $dataStmt->get_result();
$data = [];
$rowNumber = $offset + 1;
while ($journal = $result->fetch_assoc()) {
    $id = (int)$journal['id'];
    $statusValue = (int)$journal['status'];
    $actions = '<option value="">Select action</option><option value="view">View</option>';
    if ($statusValue === 1) $actions .= '<option value="edit">Edit</option><option value="cancel">Cancel</option>';
    $data[] = [
        'index' => $rowNumber++,
        'journal_date' => date('d-m-Y', strtotime($journal['journal_date'])),
        'ref_no' => 'J' . htmlspecialchars((string)$journal['loc_no'], ENT_QUOTES, 'UTF-8'),
        'memo' => htmlspecialchars((string)$journal['memo'], ENT_QUOTES, 'UTF-8'),
        'amount' => number_format((float)$journal['total_credit'], 2),
        'status' => $statusValue ? 'Posted' : 'Cancelled',
        'status_value' => $statusValue,
        'action' => '<select class="form-control form-control-sm journal-action" data-id="' . $id . '" data-status="' . $statusValue . '">' . $actions . '</select>'
    ];
}
$dataStmt->close();
echo json_encode(['draw' => $draw, 'recordsTotal' => (int)$totalCount, 'recordsFiltered' => (int)$filteredCount, 'data' => $data], JSON_INVALID_UTF8_SUBSTITUTE);
