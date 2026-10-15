<?php
include '../../db.php';

$search = mysqli_real_escape_string($con, $_POST['search'] ?? '');

$sql = "
    SELECT 
        mc.c_id,
        mc.customer_name,
        mc.customer_address,
        mc.max_limit,
        (mc.max_limit - IFNULL(SUM(at.debit - at.credit), 0)) AS available_limit
    FROM mange_customer mc
    LEFT JOIN acc_transaction at ON at.cus_id = mc.c_id AND at.status = 1
    WHERE mc.status = 1 
      AND mc.c_id > 0
      AND (mc.customer_name LIKE '%$search%' OR mc.customer_address LIKE '%$search%')
    GROUP BY mc.c_id
    ORDER BY mc.customer_name
    LIMIT 20
";

$result = mysqli_query($con, $sql);

$customers = [];
while ($row = mysqli_fetch_assoc($result)) {
    $customers[] = $row;
}

echo json_encode($customers);
