<?php
require_once '../../db.php';
require_once '../../auth.php';
header('Content-Type: application/json; charset=utf-8');

function bind_account_ledger_params(mysqli_stmt $stmt, string $types, array &$params): void
{
    $args = [$types];
    foreach ($params as &$param) $args[] = &$param;
    call_user_func_array([$stmt, 'bind_param'], $args);
}

function account_ledger_valid_date(string $value): bool
{
    $date = DateTime::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}

$draw = (int)($_POST['draw'] ?? 1);
$offset = max(0, (int)($_POST['start'] ?? 0));
$length = (int)($_POST['length'] ?? 100);
$length = $length < 1 ? 100 : min($length, 500);
$accountId = (int)($_POST['ca_id'] ?? 0);
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

if ($accountId < 1 || $locationId < 1 || !account_ledger_valid_date($from) || !account_ledger_valid_date($to) || $from > $to) {
    echo json_encode(['draw'=>$draw, 'recordsTotal'=>0, 'recordsFiltered'=>0, 'data'=>[], 'openingBalance'=>'0.00', 'error'=>'Choose an account and a valid date range.']);
    exit;
}

$accountStmt = $con->prepare('SELECT ca_type FROM acc_chart_of_accounts WHERE ca_id = ? AND status = 1 LIMIT 1');
$accountStmt->bind_param('i', $accountId);
$accountStmt->execute();
$accountStmt->bind_result($accountType);
$hasAccount = $accountStmt->fetch();
$accountStmt->close();
if (!$hasAccount) {
    echo json_encode(['draw'=>$draw, 'recordsTotal'=>0, 'recordsFiltered'=>0, 'data'=>[], 'openingBalance'=>'0.00', 'error'=>'The selected account is unavailable.']);
    exit;
}

$isBalanceSheet = in_array($accountType, ['Assets', 'Liabilities', 'Equity'], true);
$openingBalance = 0.0;
if ($isBalanceSheet) {
    $openingStmt = $con->prepare('SELECT COALESCE(SUM(debit - credit), 0) FROM acc_transaction WHERE ca_id = ? AND location_id = ? AND t_date < ? AND status = 1');
    $openingStmt->bind_param('iis', $accountId, $locationId, $from);
    $openingStmt->execute();
    $openingStmt->bind_result($openingBalance);
    $openingStmt->fetch();
    $openingStmt->close();
    $openingBalance = (float)$openingBalance;
}

$baseWhere = 't.ca_id = ? AND t.status = 1 AND t.t_date >= ? AND t.t_date < DATE_ADD(?, INTERVAL 1 DAY) AND t.location_id = ?';
$baseTypes = 'issi';
$baseParams = [$accountId, $from, $to, $locationId];
$joins = ' FROM acc_transaction t
    LEFT JOIN manage_supplier s ON t.sup_id = s.sup_id
    LEFT JOIN mange_customer c ON t.cus_id = c.c_id';

$countStmt = $con->prepare('SELECT COUNT(*)' . $joins . ' WHERE ' . $baseWhere);
$countParams = $baseParams;
bind_account_ledger_params($countStmt, $baseTypes, $countParams);
$countStmt->execute();
$countStmt->bind_result($recordsTotal);
$countStmt->fetch();
$countStmt->close();

$searchSql = '';
$searchTypes = '';
$searchParams = [];
if ($search !== '') {
    $searchSql = " AND (DATE_FORMAT(t.t_date, '%Y-%m-%d %H:%i:%s') LIKE ? OR COALESCE(t.tra_type, '') LIKE ? OR CAST(t.ref_no AS CHAR) LIKE ? OR COALESCE(t.t_memo, '') LIKE ? OR COALESCE(s.supplier_name, c.customer_name, '') LIKE ? OR CAST(t.debit AS CHAR) LIKE ? OR CAST(t.credit AS CHAR) LIKE ?)";
    $like = '%' . $search . '%';
    $searchTypes = str_repeat('s', 7);
    $searchParams = array_fill(0, 7, $like);
}
$filteredParams = array_merge($baseParams, $searchParams);
$filteredStmt = $con->prepare('SELECT COUNT(*)' . $joins . ' WHERE ' . $baseWhere . $searchSql);
bind_account_ledger_params($filteredStmt, $baseTypes . $searchTypes, $filteredParams);
$filteredStmt->execute();
$filteredStmt->bind_result($recordsFiltered);
$filteredStmt->fetch();
$filteredStmt->close();

$sql = "SELECT t.t_date, t.tra_type, t.ref_no, t.t_memo, t.debit, t.credit,
               ? + (SELECT COALESCE(SUM(x.debit - x.credit), 0)
                    FROM acc_transaction x
                    WHERE x.ca_id = t.ca_id AND x.location_id = t.location_id
                      AND x.status = 1 AND x.t_date >= ?
                      AND (x.t_date < t.t_date OR (x.t_date = t.t_date AND x.t_id <= t.t_id))) AS balance,
               s.supplier_name, c.customer_name" . $joins . ' WHERE ' . $baseWhere . $searchSql .
       ' ORDER BY t.t_date ASC, t.t_id ASC LIMIT ? OFFSET ?';
$dataParams = array_merge([$openingBalance, $from], $baseParams, $searchParams, [$length, $offset]);
$dataTypes = 'ds' . $baseTypes . $searchTypes . 'ii';
$dataStmt = $con->prepare($sql);
bind_account_ledger_params($dataStmt, $dataTypes, $dataParams);
$dataStmt->execute();
$result = $dataStmt->get_result();
$rows = [];
while ($row = $result->fetch_assoc()) {
    $party = $row['supplier_name'] ?: ($row['customer_name'] ?: '');
    $rows[] = [
        'date' => htmlspecialchars((string)$row['t_date'], ENT_QUOTES, 'UTF-8'),
        'type' => htmlspecialchars((string)$row['tra_type'], ENT_QUOTES, 'UTF-8'),
        'reference' => htmlspecialchars((string)$row['ref_no'], ENT_QUOTES, 'UTF-8'),
        'memo' => htmlspecialchars((string)$row['t_memo'], ENT_QUOTES, 'UTF-8'),
        'party' => htmlspecialchars((string)$party, ENT_QUOTES, 'UTF-8'),
        'debit' => (float)$row['debit'] > 0 ? number_format((float)$row['debit'], 2) : '',
        'credit' => (float)$row['credit'] > 0 ? number_format((float)$row['credit'], 2) : '',
        'balance' => number_format((float)$row['balance'], 2)
    ];
}
$dataStmt->close();

echo json_encode(['draw'=>$draw, 'recordsTotal'=>(int)$recordsTotal, 'recordsFiltered'=>(int)$recordsFiltered, 'data'=>$rows, 'openingBalance'=>number_format($openingBalance, 2, '.', '')], JSON_INVALID_UTF8_SUBSTITUTE);
