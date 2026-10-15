<?php
  require('../db.php');
  session_start();
  if(empty($_SESSION['username'])) {
     session_unset();     
     session_destroy(); 
     header("Location: ../login.php?msg=true");
     exit;
  }

$data = json_decode(file_get_contents('php://input'), true);
$userId = $data['userId'];


$queryx = "SELECT system_email,company_name,domain FROM letter_head LIMIT 1";
$resultx = mysqli_query($con, $queryx);
$rowx = mysqli_fetch_assoc($resultx);
$system_email = $rowx['system_email'];
$company_name = $rowx['company_name'];
$domain = $rowx['domain'];

                              $query1 = "SELECT * from user_license where usr_id='$userId'";
								$result = mysqli_query($con, $query1) or die(mysqli_error());
						 
								$row = mysqli_fetch_assoc($result);
                                $username = $row['username'];
                                 
                            $token = substr(md5(mt_rand()), 0, 20);
                            $sql = "UPDATE user_license SET token='$token' WHERE usr_id ='$userId' ";
                            if ($con->query($sql) === TRUE) { }

    $to = $username;
        $name = $row['i_name'];
    $subject = "Invitation to Access eShed Software";
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= 'From: '.$company_name.' <'.$system_email.'>' . "\r\n";

    $message = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
            body {
                background-color: #32CD32; /* Green background */
                font-family: Arial, sans-serif;
                margin: 0;
                padding: 0;
            }
            .container {
                width: 100%;
                max-width: 600px;
                margin: 20px auto;
                background-color: #ffffff; /* White background */
                padding: 20px;
                border: 1px solid #32CD32; /* Green border */
                border-radius: 10px;
                box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            }
            .header {
                text-align: center;
                margin-bottom: 20px;
            }
            .header img {
                max-width: 150px;
            }
            .content {
                text-align: center;
                font-size: 16px;
            }
            .footer {
                text-align: center;
                font-size: 12px;
                color: #888;
                margin-top: 20px;
            }
            .footer a {
                color: #888;
                text-decoration: none;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                 <h2> E-Shed login  Invitation</h2>
            </div>
            <div class="content">
                <p>Dear ' . $name . ',</p>
                <p>You have been invited to access our software. Please click the button below to set up your password and confirm your account:</p>
                <p><a href="'.$domain.'/setup_password.php?token=' . $token . '" style="background-color: #32CD32; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Accept Invitation</a></p>
            </div>
            <div class="footer">
                <p>If this email is not relevant to the software or you did not request it, please <a href="unsubscribe-link">unsubscribe</a>.</p>
            </div>
        </div>
    </body>
    </html>';

    mail($to, $subject, $message, $headers);




































$sql = "UPDATE user_license SET account_status = 2 WHERE usr_id = ?";
$stmt = $con->prepare($sql);
$stmt->bind_param("i", $userId);

$response = array();
if ($stmt->execute()) {
    $response['success'] = true;
} else {
    $response['success'] = false;
}

echo json_encode($response);
?>