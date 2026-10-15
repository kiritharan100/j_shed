<?php
include '../../db.php';

if (isset($_POST['shift_id'])) {
    $shift_id = intval($_POST['shift_id']);

    // Fetch Card Sales for the shift
    $card_sales_query = "
        SELECT SUM(amount) AS card_sales
        FROM shed_card_sales
        WHERE shift_id = $shift_id
        AND status = 1
    ";
    $card_sales_result = mysqli_query($con, $card_sales_query);
    $card_sales = 0;
    if ($card_sales_result && $row = mysqli_fetch_assoc($card_sales_result)) {
        $card_sales = floatval($row['card_sales']);
    }

    // Fetch Credit Sales for the shift
    $credit_sales_query = "
        SELECT SUM(scd.line_total) AS credit_sales
        FROM shed_credit_sales sc
        JOIN shed_credit_sales_detail scd ON sc.cs_id = scd.cs_id
        WHERE sc.shift_id = $shift_id
        AND sc.status = 1
        AND scd.status = 1
    ";
    $credit_sales_result = mysqli_query($con, $credit_sales_query);
    $credit_sales = 0;
    if ($credit_sales_result && $row = mysqli_fetch_assoc($credit_sales_result)) {
        $credit_sales = floatval($row['credit_sales']);
    }

    // Return the results as JSON
    echo json_encode([
        'card_sales' => $card_sales,
        'credit_sales' => $credit_sales
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Shift ID is missing']);
}
?>
