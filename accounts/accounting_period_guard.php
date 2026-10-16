<?php
function accounting_period_location_id(mysqli $con): int
{
    $clientKey = (string)($_COOKIE['client_cook'] ?? '');
    if ($clientKey === '') return 0;
    $stmt = $con->prepare('SELECT c_id FROM client_registration WHERE md5_client = ? LIMIT 1');
    $stmt->bind_param('s', $clientKey);
    $stmt->execute();
    $stmt->bind_result($locationId);
    $found = $stmt->fetch();
    $stmt->close();
    return $found ? (int)$locationId : 0;
}

/** Return a user-facing error when a transaction date is not in an active, unlocked accounting period. */
function accounting_period_date_error(mysqli $con, int $locationId, string $transactionDate): ?string
{
    $date = DateTime::createFromFormat('!Y-m-d', $transactionDate);
    if (!$date || $date->format('Y-m-d') !== $transactionDate) {
        return 'Enter a valid transaction date.';
    }
    if ($transactionDate > date('Y-m-d')) {
        return 'Future transaction dates are not allowed. Choose today or an earlier date in an unlocked accounting period.';
    }

    try {
        $stmt = $con->prepare('SELECT perid_from, period_to, lock_period FROM accounts_accounting_period WHERE location_id = ? AND status = 1 AND perid_from <= ? AND period_to >= ? ORDER BY perid_from DESC LIMIT 1');
        $stmt->bind_param('iss', $locationId, $transactionDate, $transactionDate);
        $stmt->execute();
        $period = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    } catch (Throwable $error) {
        error_log('Accounting period validation failed: ' . $error->getMessage());
        return 'Could not verify the accounting period. Please configure accounting periods and try again.';
    }

    if (!$period) {
        return 'The transaction date ' . date('d/m/Y', strtotime($transactionDate)) . ' is outside all active accounting periods. Choose a date within an open accounting period.';
    }
    if ((int)$period['lock_period'] === 1) {
        return 'The accounting period ' . date('d/m/Y', strtotime($period['perid_from'])) . ' to ' . date('d/m/Y', strtotime($period['period_to'])) . ' is locked. Choose a date in an unlocked accounting period.';
    }
    return null;
}
