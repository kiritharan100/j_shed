<?php
function accounting_period_location_id(mysqli $connection): int
{
    $selectedClient = trim((string)($_COOKIE['client_cook'] ?? ''));
    if ($selectedClient === '') {
        throw new RuntimeException('Select a client before managing accounting periods.');
    }

    $stmt = $connection->prepare('SELECT c_id FROM client_registration WHERE md5_client = ? LIMIT 1');
    $stmt->bind_param('s', $selectedClient);
    $stmt->execute();
    $stmt->bind_result($locationId);
    $found = $stmt->fetch();
    $stmt->close();

    if (!$found || (int)$locationId <= 0) {
        throw new RuntimeException('The selected client could not be found. Please select the client again.');
    }

    return (int)$locationId;
}
