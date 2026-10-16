<?php
require_once '../../db.php';
require_once '../../auth.php';
header('Content-Type: application/json; charset=utf-8');

function bind_ledger_params(mysqli_stmt $stmt, string $types, array &$params): void
{
    $args = [$types];
    foreach ($params as &$param) $args[] = &$param;
    call_user_func_array([$stmt, 'bind_param'], $args);
}

function ledger_date_is_valid(string $value): bool
{
    $date = DateTime::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}

$draw = (int)($_POST['draw'] ?? 1);
$offset = max(0, (int)($_POST['start'] ?? 0));
$length = (int)($_POST['length'] ?? 100);
$length = $length < 1 ? 100 : min($length, 500);
$customerId = (int)($_POST['c_id'] ?? 0);
$from = trim((string)($_POST['from'] ?? ''));
$to = trim((string)($_POST['to'] ?? ''));
$search = trim((string)($_POST['search']['value'] ?? ''));

$clientKey = (string)($_COOKIE['client_cook'] ?? '');
$clientStmt = $con->prepare('SELECT c_id FROM client_registration WHERE md5_client = ? LIMIT 1');
$clientStmt->bind_param('s', $clientKey);
$clientStmt->execute();
$clientStmt->bind_result($locationId);
$hasClient = $clientStmt->fetch();
$clientStmt->close();
$locationId = $hasClient ? (int)$locationId : 0;

if ($customerId < 1 || $locationId < 1 || !ledger_date_is_valid($from) || !ledger_date_is_valid($to) || $from > $to) {
    echo json_encode(['draw' => $draw, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'openingBalance' => '0.00', 'error' => 'Choose a customer and a valid date range.']);
    exit;
}

$openingStmt = $con->prepare('SELECT COALESCE(SUM(debit - credit), 0) FROM acc_transaction WHERE cus_id = ? AND location_id = ? AND t_date < ? AND status = 1 AND ca_id = 8');
$openingStmt->bind_param('iis', $customerId, $locationId, $from);
$openingStmt->execute();
$openingStmt->bind_result($openingBalance);
$openingStmt->fetch();
$openingStmt->close();
$openingBalance = (float)$openingBalance;

$baseWhere = 'cus_id = ? AND location_id = ? AND t_date >= ? AND t_date < DATE_ADD(?, INTERVAL 1 DAY) AND status = 1 AND ca_id = 8';
$baseTypes = 'iiss';
$baseParams = [$customerId, $locationId, $from, $to];
$countStmt = $con->prepare('SELECT COUNT(*) FROM acc_transaction WHERE ' . $baseWhere);
$countParams = $baseParams;
bind_ledger_params($countStmt, $baseTypes, $countParams);
$countStmt->execute();
$countStmt->bind_result($recordsTotal);
$countStmt->fetch();
$countStmt->close();

$searchSql = '';
$searchTypes = '';
$searchParams = [];
if ($search !== '') {
    $searchSql = " AND (CAST(t.tra_type AS CHAR) LIKE ? OR CAST(t.ref_no AS CHAR) LIKE ? OR COALESCE(t.t_memo, '') LIKE ? OR DATE_FORMAT(t.t_date, '%Y-%m-%d %H:%i:%s') LIKE ? OR CAST(t.debit AS CHAR) LIKE ? OR CAST(t.credit AS CHAR) LIKE ?)";
    $like = '%' . $search . '%';
    $searchTypes = 'ssssss';
    $searchParams = [$like, $like, $like, $like, $like, $like];
}
$filteredParams = array_merge($baseParams, $searchParams);
$filteredTypes = $baseTypes . $searchTypes;
$filteredStmt = $con->prepare('SELECT COUNT(*) FROM acc_transaction t WHERE ' . str_replace(['cus_id', 'location_id'], ['t.cus_id', 't.location_id'], $baseWhere) . $searchSql);
bind_ledger_params($filteredStmt, $filteredTypes, $filteredParams);
$filteredStmt->execute();
$filteredStmt->bind_result($recordsFiltered);
$filteredStmt->fetch();
$filteredStmt->close();

$sql = "SELECT t.t_date, t.tra_type, t.ref_no, t.t_memo, t.debit, t.credit,
               ? + (SELECT COALESCE(SUM(x.debit - x.credit), 0)
                    FROM acc_transaction x
                    WHERE x.cus_id = t.cus_id AND x.location_id = t.location_id
                      AND x.status = 1 AND x.ca_id = 8 AND x.t_date >= ?
                      AND (x.t_date < t.t_date OR (x.t_date = t.t_date AND x.t_id <= t.t_id))) AS balance
        FROM acc_transaction t
        WHERE " . str_replace(['cus_id', 'location_id'], ['t.cus_id', 't.location_id'], $baseWhere) . $searchSql . "
        ORDER BY t.t_date ASC, t.t_id ASC LIMIT ? OFFSET ?";
$dataParams = array_merge([$openingBalance, $from], $baseParams, $searchParams, [$length, $offset]);
$dataTypes = 'ds' . $baseTypes . $searchTypes . 'ii';
$dataStmt = $con->prepare($sql);
bind_ledger_params($dataStmt, $dataTypes, $dataParams);
$dataStmt->execute();
$result = $dataStmt->get_result();
$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = [
        'date' => htmlspecialchars((string)$row['t_date'], ENT_QUOTES, 'UTF-8'),
        'type' => htmlspecialchars((string)$row['tra_type'], ENT_QUOTES, 'UTF-8'),
        'reference' => htmlspecialchars((string)$row['ref_no'], ENT_QUOTES, 'UTF-8'),
        'memo' => htmlspecialchars((string)$row['t_memo'], ENT_QUOTES, 'UTF-8'),
        'debit' => (float)$row['debit'] > 0 ? number_format((float)$row['debit'], 2) : '',
        'credit' => (float)$row['credit'] > 0 ? number_format((float)$row['credit'], 2) : '',
        'balance' => number_format((float)$row['balance'], 2)
    ];
}
$dataStmt->close();

echo json_encode([
    'draw' => $draw,
    'recordsTotal' => (int)$recordsTotal,
    'recordsFiltered' => (int)$recordsFiltered,
    'data' => $rows,
    'openingBalance' => number_format($openingBalance, 2, '.', '')
], JSON_INVALID_UTF8_SUBSTITUTE);
