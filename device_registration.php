<?php
session_start();

if (empty($_SESSION['username'])) {
    session_unset();
    session_destroy();
    header('Location: login.php?msg=true');
    exit;
}

if (isset($_POST['submit_reg'])) {
    require 'db.php';
    $username = $_SESSION['username'];
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    $stmt = $con->prepare('SELECT dr_token, usr_id FROM user_license WHERE username = ? LIMIT 1');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row && hash_equals((string)$row['dr_token'], (string)($_POST['token_input'] ?? ''))) {
        $token = bin2hex(random_bytes(16));
        $device_stmt = $con->prepare('INSERT INTO user_device (pf_no, token, IP) VALUES (?, ?, ?)');
        $device_stmt->bind_param('sss', $username, $token, $ip);

        if ($device_stmt->execute()) {
            $device_stmt->close();
            $ciphering = 'AES-128-CTR';
            $encryption = openssl_encrypt($token, $ciphering, 'kiritharan100@gmail.com', 0, '1234567891011121');
            $cookie_name = 'DR' . $row['usr_id'];
            setcookie($cookie_name, $encryption, time() + (86400 * 665), '/');
            header('Location: index.php');
            exit;
        }
        $device_stmt->close();
        header('Location: login.php?reg_faild');
        exit;
    }

    $attempt_cookie = 'DR' . $username;
    $attempts = (int)($_COOKIE[$attempt_cookie] ?? 0) + 1;
    setcookie($attempt_cookie, (string)$attempts, time() + (86400 * 650), '/');
    if ($attempts >= 3) {
        $lock_stmt = $con->prepare("UPDATE user_license SET account_status = '10' WHERE username = ?");
        $lock_stmt->bind_param('s', $username);
        $lock_stmt->execute();
        $lock_stmt->close();
        header('Location: login.php?acc=10');
    } else {
        header('Location: login.php?device');
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
	<title>Device Registration</title>

	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1.0, user-scalable=no">
	<meta http-equiv="X-UA-Compatible" content="IE=edge" />
	<meta name="description" content="codedthemes">
	<meta name="keywords" content=", Responsive, Landing, Bootstrap, App, Template, Mobile, iOS, Android, apple, creative app">
	<meta name="author" content="codedthemes">

	<!-- Favicon icon -->
	<link rel="shortcut icon" href="assets/images/favicon.png" type="image/x-icon">
	<link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">

	<!-- Google font-->
	<link href="https://fonts.googleapis.com/css?family=Ubuntu:400,500,700" rel="stylesheet">

	<!-- iconfont -->
	<link rel="stylesheet" type="text/css" href="assets/icon/icofont/css/icofont.css">

	<!-- Required Fremwork -->
	<link rel="stylesheet" type="text/css" href="assets/plugins/bootstrap/css/bootstrap.min.css">

	<!-- waves css -->
	<link rel="stylesheet" type="text/css" href="assets/plugins/Waves/waves.min.css">

	<!-- Style.css -->
	<link rel="stylesheet" type="text/css" href="assets/css/main.css">

	<!-- Responsive.css-->
	<link rel="stylesheet" type="text/css" href="assets/css/responsive.css">
	<!--color css-->
	<link rel="stylesheet" type="text/css" href="assets/css/color/color-1.min.css" id="color" />

</head>

<body>

	<section class="login p-fixed d-flex text-center bg-primary common-img-bg">
		<div class="container-fluid">
			<div class="row">
				<div class="col-xs-12">
					<div class="login-card card-block">
						<form class="md-float-material" action="" method="POST">
							<div class="text-center">
								<img src="assets/images/logo-black.png" alt="logo">
							</div>
							<h3 class="text-primary text-center m-b-25">Device Registration 
							<?php // Authentication and registration handling run before page output. ?>
</h3>
							<div class="md-group" align='center'>
								<div class="md-input-wrapper">
									<input type="number" name='token_input' class="md-form-control" style="width:200px;" required />
									<label>Please enter the token that was sent to your Admin.</label>
								</div>
							</div>
							<div class="btn-forgot">
								<button type="submit" name='submit_reg' class="btn btn-primary btn-md waves-effect waves-light text-center">Register
								</button>
							</div>
							<div class="row">
								<div class="col-xs-12 text-center m-t-25">

									<!-- <a href="login1.html" class="f-w-600 p-l-5"> Sign In Here</a> -->

								</div>
							</div>
							<!-- end of btn-forgot class-->
						</form>
						<!-- end of form -->
					</div>
					<!-- end of login-card -->
				</div>
				<!-- end of col-sm-12 -->
			</div>
			<!-- end of row -->
		</div>
		<!-- end of container-fluid -->
	</section>


	<script src="assets/plugins/jquery/dist/jquery.min.js"></script>
	<script src="assets/plugins/jquery-ui/jquery-ui.min.js"></script>
	<script src="assets/plugins/tether/dist/js/tether.min.js"></script>

	<!-- Required Fremwork -->
	<script src="assets/plugins/bootstrap/js/bootstrap.min.js"></script>

	<!-- waves effects.js -->
	<script src="assets/plugins/Waves/waves.min.js"></script>

	<!-- Custom js -->
	<script type="text/javascript" src="assets/pages/elements.js"></script>

</body>

</html>
