<?php
require_once '../../db.php';
require_once '../../auth.php';
header('Content-Type: application/json; charset=utf-8');

function bind_account_transaction_params(mysqli_stmt $stmt, string $types, array &$params): void
{
    $args = [$types];
    foreach ($params as &$param) $args[] = &$param;
    call_user_func_array([$stmt, 'bind_param'], $args);
}

function account_transaction_valid_date(string $value): bool
{
    $date = DateTime::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}

$draw = (int)($_POST['draw'] ?? 1);
$offset = max(0, (int)($_POST['start'] ?? 0));
$length = (int)($_POST['length'] ?? 100);
$length = $length < 1 ? 100 : min($length, 500);
$from = trim((string)($_POST['from'] ?? ''));
$to = trim((string)($_POST['to'] ?? ''));
$search = trim((string)($_POST['search']['value'] ?? ''));
$orderIndex = (int)($_POST['order'][0]['column'] ?? 0);
$direction = strtolower((string)($_POST['order'][0]['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
$orderColumns = [
    0 => 't.t_date', 1 => 'a.ca_name', 2 => 't.tra_type', 3 => 't.ref_no', 4 => 't.t_memo',
    5 => 'COALESCE(s.supplier_name, c.customer_name)', 6 => 't.debit', 7 => 't.credit', 8 => 't.debit_Vat', 9 => 't.credit_vat'
];
$orderBy = $orderColumns[$orderIndex] ?? 't.t_date';

$clientKey = (string)($_COOKIE['client_cook'] ?? '');
$clientStmt = $con->prepare('SELECT c_id FROM client_registration WHERE md5_client = ? LIMIT 1');
$clientStmt->bind_param('s', $clientKey);
$clientStmt->execute();
$clientStmt->bind_result($locationId);
$hasClient = $clientStmt->fetch();
$clientStmt->close();
$locationId = $hasClient ? (int)$locationId : 0;

if ($locationId < 1 || !account_transaction_valid_date($from) || !account_transaction_valid_date($to) || $from > $to) {
    echo json_encode(['draw'=>$draw, 'recordsTotal'=>0, 'recordsFiltered'=>0, 'data'=>[], 'totalDebit'=>'0.00', 'totalCredit'=>'0.00', 'error'=>'Select a valid date range.']);
    exit;
}

$fromCondition = 't.status = 1 AND t.t_date >= ? AND t.t_date < DATE_ADD(?, INTERVAL 1 DAY) AND t.location_id = ?';
$baseTypes = 'ssi';
$baseParams = [$from, $to, $locationId];
$joins = ' FROM acc_transaction t
    LEFT JOIN acc_chart_of_accounts a ON t.ca_id = a.ca_id
    LEFT JOIN manage_supplier s ON t.sup_id = s.sup_id AND t.sup_id > 0
    LEFT JOIN mange_customer c ON t.cus_id = c.c_id AND t.cus_id > 0';

$countStmt = $con->prepare('SELECT COUNT(*)' . $joins . ' WHERE ' . $fromCondition);
$countParams = $baseParams;
bind_account_transaction_params($countStmt, $baseTypes, $countParams);
$countStmt->execute();
$countStmt->bind_result($recordsTotal);
$countStmt->fetch();
$countStmt->close();

$totalStmt = $con->prepare('SELECT COALESCE(SUM(t.debit),0), COALESCE(SUM(t.credit),0)' . $joins . ' WHERE ' . $fromCondition);
$totalParams = $baseParams;
bind_account_transaction_params($totalStmt, $baseTypes, $totalParams);
$totalStmt->execute();
$totalStmt->bind_result($totalDebit, $totalCredit);
$totalStmt->fetch();
$totalStmt->close();

$searchSql = '';
$searchTypes = '';
$searchParams = [];
if ($search !== '') {
    $searchSql = " AND (DATE_FORMAT(t.t_date, '%Y-%m-%d %H:%i:%s') LIKE ? OR COALESCE(a.ca_name, '') LIKE ? OR COALESCE(t.tra_type, '') LIKE ? OR CAST(t.ref_no AS CHAR) LIKE ? OR COALESCE(t.t_memo, '') LIKE ? OR COALESCE(s.supplier_name, c.customer_name, '') LIKE ? OR CAST(t.debit AS CHAR) LIKE ? OR CAST(t.credit AS CHAR) LIKE ? OR CAST(t.debit_Vat AS CHAR) LIKE ? OR CAST(t.credit_vat AS CHAR) LIKE ?)";
    $like = '%' . $search . '%';
    $searchTypes = str_repeat('s', 10);
    $searchParams = array_fill(0, 10, $like);
}
$filteredParams = array_merge($baseParams, $searchParams);
$filteredStmt = $con->prepare('SELECT COUNT(*)' . $joins . ' WHERE ' . $fromCondition . $searchSql);
bind_account_transaction_params($filteredStmt, $baseTypes . $searchTypes, $filteredParams);
$filteredStmt->execute();
$filteredStmt->bind_result($recordsFiltered);
$filteredStmt->fetch();
$filteredStmt->close();

$sql = 'SELECT t.t_date, t.tra_type, t.ref_no, t.t_memo, t.debit, t.credit, t.debit_Vat, t.credit_vat,
               a.ca_name, s.supplier_name, c.customer_name' . $joins . ' WHERE ' . $fromCondition . $searchSql .
       ' ORDER BY ' . $orderBy . ' ' . $direction . ', t.t_id ASC LIMIT ? OFFSET ?';
$dataParams = array_merge($baseParams, $searchParams, [$length, $offset]);
$dataTypes = $baseTypes . $searchTypes . 'ii';
$dataStmt = $con->prepare($sql);
bind_account_transaction_params($dataStmt, $dataTypes, $dataParams);
$dataStmt->execute();
$result = $dataStmt->get_result();
$rows = [];
while ($row = $result->fetch_assoc()) {
    $party = $row['supplier_name'] ?: ($row['customer_name'] ?: '');
    $rows[] = [
        'date' => htmlspecialchars((string)$row['t_date'], ENT_QUOTES, 'UTF-8'),
        'account' => htmlspecialchars((string)$row['ca_name'], ENT_QUOTES, 'UTF-8'),
        'type' => htmlspecialchars((string)$row['tra_type'], ENT_QUOTES, 'UTF-8'),
        'reference' => htmlspecialchars((string)$row['ref_no'], ENT_QUOTES, 'UTF-8'),
        'memo' => htmlspecialchars((string)$row['t_memo'], ENT_QUOTES, 'UTF-8'),
        'party' => htmlspecialchars((string)$party, ENT_QUOTES, 'UTF-8'),
        'debit' => (float)$row['debit'] > 0 ? number_format((float)$row['debit'], 2) : '',
        'credit' => (float)$row['credit'] > 0 ? number_format((float)$row['credit'], 2) : '',
        'input_vat' => (float)$row['debit_Vat'] > 0 ? number_format((float)$row['debit_Vat'], 2) : '',
        'output_vat' => (float)$row['credit_vat'] > 0 ? number_format((float)$row['credit_vat'], 2) : ''
    ];
}
$dataStmt->close();

echo json_encode([
    'draw'=>$draw,
    'recordsTotal'=>(int)$recordsTotal,
    'recordsFiltered'=>(int)$recordsFiltered,
    'data'=>$rows,
    'totalDebit'=>number_format((float)$totalDebit, 2, '.', ''),
    'totalCredit'=>number_format((float)$totalCredit, 2, '.', '')
], JSON_INVALID_UTF8_SUBSTITUTE);
