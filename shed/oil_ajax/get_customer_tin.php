<?php
include '../../db.php';

if (isset($_POST['customer'])) {
    $customer = mysqli_real_escape_string($con, $_POST['customer']);

    $query = "SELECT tin_number 
              FROM vat_sales_master 
              WHERE to_location = '$customer' AND issue_status = 1 
              ORDER BY iss_id DESC 
              LIMIT 1";

    $result = mysqli_query($con, $query);
    if ($row = mysqli_fetch_assoc($result)) {
        echo $row['tin_number'];
    } else {
        echo ''; // No TIN found
    }
}
?>
