<?php
function ensure_business_start_date_column(mysqli $connection): void
{
    $result = $connection->query("SHOW COLUMNS FROM client_registration LIKE 'business_start_date'");
    if ($result && $result->num_rows === 0) {
        $connection->query('ALTER TABLE client_registration ADD COLUMN business_start_date DATE NULL DEFAULT NULL AFTER client_type');
    }
}
