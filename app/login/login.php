<?php

use App\Services\CommonService;

if (session_status() == PHP_SESSION_NONE) {
	session_start();
}
if (isset($_SESSION['userId'])) {
	header("Location:/dashboard/index.php");
}
$db = \MysqliDb::getInstance();
// If there are NO users, then we need to register the admin user
// This happens during first setup typically
$count = $db->getValue("user_details", "count(*)");
if ($count == 0) {
	header("Location:/setup/index.php");
}

$general = new CommonService();

$globalConfigResult = $general->getGlobalConfig();
$systemInfo = $general->getSystemConfig();

$_SESSION['instanceType'] = $systemInfo['sc_user_type'];

if (isset(SYSTEM_CONFIG['instanceName']) && !empty(SYSTEM_CONFIG['instanceName'])) {
	$systemType = SYSTEM_CONFIG['instanceName'];
} else {
	$systemType = _("Lab Sample Management Module");
}

$shortName = _('Sample Management System');

if (isset($_SESSION['instanceType']) && $_SESSION['instanceType'] == 'remoteuser') {
	$shortName = 'Sample Tracking';
	$systemType = "Remote Sample Tracking Module";
	$path = '/assets/img/remote-bg.jpg';
} else {
	$path = '/assets/img/bg.jpg';
}

if (file_exists(WEB_ROOT . DIRECTORY_SEPARATOR . "uploads/bg.jpg")) {
	$path = '/uploads/bg.jpg';
} else if (file_exists(WEB_ROOT . DIRECTORY_SEPARATOR . "uploads/bg.png")) {
	$path = '/uploads/bg.png';
}


?>

<!-- LOGIN PAGE -->
<?php $_SESSION['csrf_token'] = $_SESSION['csrf_token'] ?? $general->generateUUID(); ?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['APP_LOCALE']; ?>">

<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<title><?php echo $shortName; ?> | <?php echo _("Login"); ?></title>
	<!-- Tell the browser to be responsive to screen width -->
	<meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">

	<?php if (!empty($_SESSION['instanceType']) && $_SESSION['instanceType'] == 'remoteuser') { ?>
		<link rel="apple-touch-icon" sizes="180x180" href="/assets/vlsts-icons/apple-touch-icon.png">
		<link rel="icon" type="image/png" sizes="32x32" href="/assets/vlsts-icons/favicon-32x32.png">
		<link rel="icon" type="image/png" sizes="16x16" href="/assets/vlsts-icons/favicon-16x16.png">
		<link rel="manifest" href="/assets/vlsts-icons/site.webmanifest">
	<?php } else { ?>
		<link rel="apple-touch-icon" sizes="180x180" href="/assets/vlsm-icons/apple-touch-icon.png">
		<link rel="icon" type="image/png" sizes="32x32" href="/assets/vlsm-icons/favicon-32x32.png">
		<link rel="icon" type="image/png" sizes="16x16" href="/assets/vlsm-icons/favicon-16x16.png">
		<link rel="manifest" href="/assets/vlsm-icons/site.webmanifest">
	<?php } ?>


	<!-- Bootstrap 3.3.6 -->
	<link rel="stylesheet" href="/assets/css/fonts.css">
	<link rel="stylesheet" href="/assets/css/bootstrap.min.css">

	<link rel="stylesheet" href="/assets/css/font-awesome.min.css">

	<!-- Theme style -->
	<link rel="stylesheet" href="/assets/css/AdminLTE.min.css">
	<link href="/assets/css/deforayModal.css" rel="stylesheet" />
	<!-- iCheck -->
	<style>
		body {
			background: #F6F6F6;
			background: #000;

			background: url("<?php echo $path; ?>") center;
			background-size: cover;
			background-repeat: no-repeat;
		}

		a {
			cursor: pointer;
		}
	</style>

	<script type="text/javascript" src="/assets/js/jquery.min.js"></script>
</head>

<body class="">
	<div class="container-fluid">
		<?php
		$filePath = $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'login-logos';


		if (isset($globalConfigResult[0]['value']) && trim($globalConfigResult[0]['value']) != "" && file_exists('uploads' . DIRECTORY_SEPARATOR . "logo" . DIRECTORY_SEPARATOR . $globalConfigResult[0]['value'])) {
		?>
			<div style="margin-top:15px;float:left;">
				<img src="/uploads/logo/<?php echo $globalConfigResult[0]['value']; ?>" alt="Logo image" style="max-width:120px;">
			</div>
		<?php
		}

		if (is_dir($filePath) && count(scandir($filePath)) > 2) {
			$dir = scandir($filePath);
			$loginLogoFiles = [];
			foreach ($dir as $fileName) {
				if ($fileName != '.' && $fileName != '..') {
					$loginLogoFiles[] = $fileName;
				}
			}
		?>
			<div style="margin-top:15px;float:left;">
				<?php foreach ($loginLogoFiles as $fileName) { ?>
					&nbsp;<img src="/uploads/login-logos/<?php echo $fileName; ?>" alt="Logo image" style="max-width:80px;">
				<?php }  ?>
			</div>
		<?php
		}

		?>
		<div id="loginbox" style="margin-top:20px;margin-bottom:70px;float:right;margin-right:10px;" class="mainbox col-md-3 col-sm-8 ">
			<div class="panel panel-default" style="opacity: 0.93;">
				<div class="panel-heading">
					<div class="panel-title"><?php echo $systemType; ?></div>
				</div>

				<div style="padding-top:10px;" class="panel-body">
					<div style="display:none" id="login-alert" class="alert alert-danger col-sm-12"></div>
					<form id="loginForm" name="loginForm" class="form-horizontal" role="form" method="post" action="loginProcess.php" onsubmit="validateNow();return false;">
						<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>" />
						<div style="margin-bottom: 5px" class="input-group">
							<span class="input-group-addon"><em class="fa-solid fa-user"></em></span>
							<input id="username" type="text" class="form-control isRequired" name="username" value="" placeholder="<?php echo _('User Name'); ?>" title="<?php echo _('Please enter your user name'); ?>" onblur="checkLoginAttempts()">
						</div>

						<div style="margin-bottom: 5px" class="input-group">
							<span class="input-group-addon"><em class="fa-solid fa-lock"></em></span>
							<input id="password" type="password" class="form-control isRequired" name="password" placeholder="<?php echo _('Password'); ?>" title="<?php echo _('Please enter your password'); ?>">
						</div>
						<div style="margin-bottom: 5px;display:none" id="captcha">
							<div>
								<img id="capChaw" width="180px" alt="verification" src="/includes/captcha.php" />
								<a onclick="getCaptcha('capChaw');return false;" class="mandatory"><em class="fa-solid fa-arrows-rotate"></em> <?php echo _("Get New Image"); ?></a>
							</div>

							<div style="margin-bottom: 5px" class="input-group">
								<span class="input-group-addon"><em class="fa-solid fa-shield-halved"></em></span>
								<input type="text" style="" id="challengeResponse" name="captcha" placeholder="<?php echo _('Please enter the text from the image'); ?>" class="form-control" title="<?php echo _('Please enter the text from the image'); ?>." maxlength="40">
							</div>
						</div>

						<div style="margin-top:10px" class="form-group">
							<!-- Button -->
							<div class="col-sm-12 controls">
								<button class="btn btn-lg btn-success btn-block" onclick="validateNow();return false;"><?php echo _("Login"); ?></button>
							</div>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>
	<div style="padding:1% 2%;width:100%;position:absolute;bottom:1.5%;color:#fff;background:rgba(0,0,0,0);">
		<span class="pull-right" style="font-weight:bold;">v <?php echo VERSION; ?></span>
	</div>
	<script src="/assets/js/deforayValidation.js"></script>
	<script src="/assets/js/jquery.blockUI.js"></script>
	<script type="text/javascript">
		window.additionalXHRParams = {
			layout: 0,
			'X-CSRF-Token': '<?php echo $_SESSION['csrf_token'] = $_SESSION['csrf_token'] ?? $general->generateUUID(); ?>'
		};

		$.ajaxSetup({
			headers: window.additionalXHRParams
		});

		let captchaflag = false;

		function getCaptcha(captchaDivId) {
			//var d = new Date();
			//var randstr = d.getFullYear() + d.getSeconds() + d.getMilliseconds() + Math.random();
			$("#" + captchaDivId).attr("src", '/includes/captcha.php');
			$("#" + captchaDivId).load(function() {
				$.blockUI();
			});
		}

		function validateNow() {
			flag = deforayValidator.init({
				formId: 'loginForm'
			});

			if (flag) {
				challenge_field = document.getElementById("challengeResponse").value;
				if (captchaflag === true) {
					if (challenge_field !== "") {
						$.post('/includes/check-captcha-route.php', {
								challenge_field: challenge_field,
								format: "html"
							},
							function(data) {
								if (data == 'fail') {
									alert('<?php echo _("Text you entered from the image is incorrect. Please try again"); ?>');
									getCaptcha('capChaw');
									document.getElementById("challengeResponse").value = "";
									return false;
								} else {
									$.blockUI();
									document.getElementById('loginForm').submit();
								}
							});
					} else {
						alert('<?php echo _("Please enter the text from the image to proceed."); ?>');
						// $('.ppwd').focus();
						return false;
					}
				} else {
					document.getElementById('loginForm').submit();
				}
			}
		}

		$(document).ready(function() {
			<?php if (isset(SYSTEM_CONFIG['recency']) && SYSTEM_CONFIG['recency']['crosslogin']) { ?>
				if (sessionStorage.getItem("crosslogin") == "true") {
					<?php $_SESSION['logged'] = false; ?>
					sessionStorage.setItem("crosslogin", "false");
					$('<iframe src="<?php echo rtrim(SYSTEM_CONFIG['recency']['url'], "/") . '/logout'; ?>" frameborder="0" scrolling="no" id="myFrame" style="display:none;"></iframe>').appendTo('body');
				}
			<?php }
			if (isset($_SESSION['alertMsg']) && trim($_SESSION['alertMsg']) != "") { ?>
				alert("<?php echo $_SESSION['alertMsg']; ?>");
			<?php $_SESSION['alertMsg'] = '';
				unset($_SESSION['alertMsg']);
			} ?>
		});



		function checkLoginAttempts() {
			if ($.trim($("#username").val()) != '') {
				$.post("/login/check-login-attempts.php", {
						loginId: $("#username").val(),
						format: "html"
					},
					function(data) {
						if (data == 1) {
							captchaflag = true;
							$('#captcha').show();
							$("#challengeResponse").addClass("isRequired");
						}
					});
			}
		}
	</script>
</body>

</html>