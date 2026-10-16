<?php
include("../../db.php");
include("../../auth.php");
header('Content-Type: application/json; charset=utf-8');

$id = (int)($_GET['id'] ?? 0);
$data = ['journal' => [], 'details' => []];

$client_key = (string)($_COOKIE['client_cook'] ?? '');
$client_stmt = $con->prepare('SELECT c_id FROM client_registration WHERE md5_client = ? LIMIT 1');
$client_stmt->bind_param('s', $client_key);
$client_stmt->execute();
$client_stmt->bind_result($location_id);
$has_client = $client_stmt->fetch();
$client_stmt->close();
if (!$has_client) {
    echo json_encode(['error' => 'Select a client before viewing journals.']);
    exit;
}

// Header
$stmt = $con->prepare('SELECT * FROM acc_journal WHERE id = ? AND location_id = ? LIMIT 1');
$stmt->bind_param('ii', $id, $location_id);
$stmt->execute();
$q = $stmt->get_result();
if ($q && mysqli_num_rows($q)) {
    $data['journal'] = mysqli_fetch_assoc($q);
} else {
    echo json_encode(['error' => 'Journal not found']);
    exit;
}

// Lines
$d_stmt = $con->prepare('SELECT * FROM acc_journal_detail WHERE journal_id = ?');
$d_stmt->bind_param('i', $id);
$d_stmt->execute();
$d = $d_stmt->get_result();
while ($row = mysqli_fetch_assoc($d)) {
    $data['details'][] = $row;
}
$d_stmt->close();
$stmt->close();

echo json_encode($data);
