<?php
function get_shared_transaction_date(): string
{
    $value = $_COOKIE['transaction_date'] ?? '';
    $date = DateTime::createFromFormat('!Y-m-d', (string)$value);

    if (!$date || $date->format('Y-m-d') !== $value) {
        return date('Y-m-d');
    }

    return $value;
}

function remember_shared_transaction_date($value): void
{
    $value = (string)$value;
    $date = DateTime::createFromFormat('!Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) {
        return;
    }

    setcookie('transaction_date', $value, time() + (86400 * 365), '/');
    $_COOKIE['transaction_date'] = $value;
}
