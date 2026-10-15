 <?php
include '../../db.php';

if (!isset($_POST['card_type']) || !isset($_POST['location_id'])) {
    echo json_encode(['error' => 'Invalid parameters']);
    exit;
}

$card_type = intval($_POST['card_type']);
$location_id = intval($_POST['location_id']);

// Get the last inserted serial_number for the card_type and location
$query = "SELECT serial_number 
          FROM shed_card_sales 
          WHERE card_type = $card_type AND location_id = $location_id 
          ORDER BY id DESC 
          LIMIT 1";

$result = mysqli_query($con, $query);

if ($result && mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);
    $next_serial = $row['serial_number'] + 1;
} else {
    $next_serial = 1; // Start from 1 if no records found
}

echo json_encode(['next_serial' => $next_serial]);
?>
