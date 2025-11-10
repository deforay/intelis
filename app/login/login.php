<?php

use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\SecurityService;
use App\Services\SystemService;
use App\Registries\ContainerRegistry;

/** @var CommonService|null $commonService */
$commonService = null;

if (isset($_SESSION['userId'])) {
	SecurityService::redirect("/dashboard/index.php", rotateCSRF: false);
} else {
	$alertMessage = null;
	if (!empty($_SESSION['alertMsg'])) {
		$alertMessage = $_SESSION['alertMsg'];
	}
	$currentLocale = $_SESSION['APP_LOCALE'] ?? 'en_US';

	/** @var CommonService $commonService */
	$commonService = ContainerRegistry::get(CommonService::class);
	$ipAddress = $commonService->getClientIpAddress();
	$ipSessionData = $_SESSION[$ipAddress] ?? null;

	SecurityService::resetSession();
	SecurityService::restartSession();

	$_SESSION['APP_LOCALE'] = $currentLocale;

	/** @var SystemService $systemService */
	$systemService = ContainerRegistry::get(SystemService::class);
	$systemService->setLocale($currentLocale);

	if ($ipSessionData !== null) {
		$_SESSION[$ipAddress] = $ipSessionData;
	}
	if (isset($alertMessage) && trim((string) $_SESSION['alertMsg']) != "") {
		$_SESSION['alertMsg'] = $alertMessage;
	}
}


// Get request parameters
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());

$logoutReason = $_GET['e'] ?? '';
if ($logoutReason === 'timeout') {
	// Message shown once; translate if you want
	$_SESSION['alertMsg'] = _translate('Your session expired due to inactivity. Please log in again.', true);
}

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

if (!($db instanceof DatabaseService) || $db->isConnected() === false) {
	$_SESSION['alertMsg'] = _translate("Database connection failed. Please check your database settings", true);
} else {

	// If there are NO users, then we need to register an admin user
	// This happens during first setup typically
	$db->where("role_id=1");
	$count = $db->getValue("user_details", "count(*)");
	if ($count == 0) {
		header("Location:/setup/index.php");
	}
}

SecurityService::rotateCSRF();


/** @var CommonService $general */
$general = $commonService ?? ContainerRegistry::get(CommonService::class);

$ipAddress = $general->getClientIpAddress();

if (!isset($_SESSION[$ipAddress])) {
	$_SESSION[$ipAddress] = [
		'failedAttempts' => 0,
		'lastFailedLogin' => null
	];
}

SecurityService::checkLoginAttempts($ipAddress);

$logo = $general->getGlobalConfig('logo');
$systemInfo = $general->getSystemConfig();

if (!empty(SYSTEM_CONFIG['instance-name']) && SYSTEM_CONFIG['instance-name'] != '') {
	$systemDisplayName = SYSTEM_CONFIG['instance-name'];
} else {
	$systemDisplayName = _translate("Lab Sample Management Module");
}

$shortName = _translate('Sample Management System');

if (isset($_SESSION['instance']['type']) && $general->isSTSInstance()) {
	$shortName = 'Sample Tracking';
	$systemDisplayName = "Sample Tracking System";
	$path = '/assets/img/remote-bg.jpg';
} else {
	$path = '/assets/img/bg.jpg';
}

if (file_exists(WEB_ROOT . DIRECTORY_SEPARATOR . "uploads/bg.jpg")) {
	$path = '/uploads/bg.jpg';
} elseif (file_exists(WEB_ROOT . DIRECTORY_SEPARATOR . "uploads/bg.png")) {
	$path = '/uploads/bg.png';
}


?>

<!-- LOGIN PAGE -->
<?php $_SESSION['csrf_token'] ??= MiscUtility::generateRandomString(); ?>
<!DOCTYPE html>
<html lang="<?= $currentLocale; ?>">

<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<title><?= $shortName; ?> | <?= _translate("Login"); ?></title>
	<!-- Tell the browser to be responsive to screen width -->
	<meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">


	<?php if (!empty($_SESSION['instance']['type']) && $general->isSTSInstance()) { ?>
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
	<link rel="stylesheet" href="/assets/css/deforayModal.css" />
	<!-- iCheck -->
	<style>
		body {
			background: #F6F6F6;
			background: #000;

			background: url("<?= $path; ?>") center;
			background-size: cover;
			background-repeat: no-repeat;
		}

		a {
			cursor: pointer;
		}

		.hpot {
			display: none;
		}
	</style>

	<script type="text/javascript" src="/assets/js/jquery.min.js"></script>
</head>

<body class="">
	<div class="container-fluid">
		<?php



		if (!empty($logo) && trim((string) $logo) != "" && MiscUtility::isImageValid(UPLOAD_PATH . DIRECTORY_SEPARATOR . "logo" . DIRECTORY_SEPARATOR . $logo)) {
		?>
			<div style="margin-top:15px;float:left;">
				<img src="/uploads/logo/<?= $logo; ?>" alt="Logo image" style="max-width:120px;">
			</div>
		<?php
		}

		$filePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'login-logos';
		if (is_dir($filePath) && count(scandir($filePath)) > 2) {
			$dir = scandir($filePath);
			$loginLogoFiles = [];
			foreach ($dir as $fileName) {
				if ($fileName != '.' && $fileName != '..' && MiscUtility::isImageValid($filePath . DIRECTORY_SEPARATOR . $fileName)) {
					$loginLogoFiles[] = $fileName;
				}
			}
		?>
			<div style="margin-top:15px;float:left;">
				<?php foreach ($loginLogoFiles as $fileName) { ?>
					&nbsp;<img src="/uploads/login-logos/<?= $fileName; ?>" alt="Logo image" style="max-width:80px;">
				<?php }  ?>
			</div>
		<?php
		}

		?>
		<div id="loginbox" style="margin-top:20px;margin-bottom:70px;float:right;margin-right:10px;" class="mainbox col-md-3 col-sm-8 ">
			<div class="panel panel-default" style="opacity: 0.93;">
				<div class="panel-heading">
					<div class="panel-title"><?= $systemDisplayName; ?></div>
				</div>

				<div style="padding-top:10px;" class="panel-body">
					<?php
					$initialAlert = '';
					if (!empty($_SESSION['alertMsg'])) {
						$initialAlert = trim((string) $_SESSION['alertMsg']);
						// Clear it so it doesn't persist across refreshes
						$_SESSION['alertMsg'] = '';
						unset($_SESSION['alertMsg']);
					}
					?>
					<div id="login-alert"
						class="alert <?= $initialAlert ? 'alert-warning' : 'alert-danger' ?> col-sm-12"
						style="<?= $initialAlert ? '' : 'display:none' ?>">
						<?= htmlspecialchars($initialAlert ?: '', ENT_QUOTES, 'UTF-8'); ?>
					</div>
					<form id="loginForm" name="loginForm" class="form-horizontal" method="post" action="/login/loginProcess.php" onsubmit="validateNow();return false;">
						<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>" />
						<div style="margin-bottom: 5px" class="input-group hpot">
							<span class="input-group-addon"><em class="fa-solid fa-x"></em></span>
							<input id="labname_<?= MiscUtility::generateRandomNumber(4) ?>" type="text" class="form-control" name="labname" value="" placeholder="<?= _translate('Lab Name'); ?>" title="<?= _translate('Please enter your lab name'); ?>" onchange="$('#captcha').show();">
						</div>
						<div style="margin-bottom: 5px" class="input-group">
							<span class="input-group-addon"><em class="fa-solid fa-user"></em></span>
							<input id="username" type="text" class="form-control isRequired" name="username" value="" placeholder="<?= _translate('User Name'); ?>" title="<?= _translate('Please enter your user name'); ?>" onblur="checkLoginAttempts()">
						</div>

						<div style="margin-bottom: 5px" class="input-group">
							<span class="input-group-addon"><em class="fa-solid fa-lock"></em></span>
							<input id="password" type="password" class="form-control isRequired" name="password" placeholder="<?= _translate('Password'); ?>" title="<?= _translate('Please enter your password'); ?>">
						</div>
						<div style="margin-bottom: 5px;display:none" id="captcha">
							<div>
								<img id="capChaw" width="180px" alt="verification" src="/includes/captcha.php" />
								<a onclick="getCaptcha('capChaw');return false;" class="mandatory"><em class="fa-solid fa-arrows-rotate"></em> <?= _translate("Get New Image"); ?></a>
							</div>

							<div style="margin-bottom: 5px" class="input-group">
								<span class="input-group-addon"><em class="fa-solid fa-shield-halved"></em></span>
								<input type="text" id="challengeResponse" name="captcha" placeholder="<?= _translate('Please enter the text from the image'); ?>" class="form-control" title="<?= _translate('Please enter the text from the image'); ?>." maxlength="40">
							</div>
						</div>

						<div style="margin-top:10px" class="form-group">
							<!-- Button -->
							<div class="col-sm-12 controls">
								<button class="btn btn-lg btn-success btn-block" onclick="validateNow();return false;"><?= _translate("Login"); ?></button>
							</div>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>
	<div style="padding:1% 2%;width:100%;position:absolute;bottom:1.5%;color:#fff;background:rgba(0,0,0,0.1);">
		<small class="pull-right" style="font-weight:bold;">v <?= VERSION; ?></small>
	</div>
	<script type="text/javascript" src="/assets/js/deforayValidation.js"></script>
	<script type="text/javascript" src="/assets/js/jquery.blockUI.js"></script>
	<script type="text/javascript">
		let idleTime = 0;

		function resetIdleTimer() {
			idleTime = 0;
		}

		function refreshIfIdle() {
			idleTime++;
			if (idleTime >= 30) { // 30 minutes
				location.reload();
			}
		}
		window.additionalXHRParams = {
			layout: 0,
			'X-CSRF-Token': '<?= $_SESSION['csrf_token'] ??= MiscUtility::generateRandomString(); ?>'
		};

		$.ajaxSetup({
			headers: window.additionalXHRParams
		});

		let captchaflag = false;

		function getCaptcha(captchaDivId) {
			$("#" + captchaDivId).attr("src", '/includes/captcha.php?x=' + Math.random());
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
						$.post('/login/check-captcha.php', {
								challenge_field: challenge_field,
								format: "html"
							},
							function(data) {
								if (data == 'fail') {
									alert("<?= _translate("Text you entered from the image is incorrect. Please try again", true); ?>");
									getCaptcha('capChaw');
									document.getElementById("challengeResponse").value = "";
									return false;
								} else {
									$.blockUI();
									document.getElementById('loginForm').submit();
								}
							});
					} else {
						alert("<?= _translate("Please enter the text from the image to proceed.", true); ?>");
						return false;
					}
				} else {
					document.getElementById('loginForm').submit();
				}
			}
		}

		$(document).ready(function() {
			// Increment the idle time counter every minute.
			setInterval(refreshIfIdle, 60000); // 1 minute

			// Zero the idle timer on mouse movement.
			$(this).mousemove(resetIdleTimer);
			$(this).keypress(resetIdleTimer);

			<?php if (isset(SYSTEM_CONFIG['recency']) && SYSTEM_CONFIG['recency']['crosslogin']) { ?>
				if (sessionStorage.getItem("crosslogin") == "true") {
					<?php $_SESSION['logged'] = false; ?>
					sessionStorage.setItem("crosslogin", "false");
					$('<iframe src="<?= rtrim((string) SYSTEM_CONFIG['recency']['url'], "/") . '/logout'; ?>" frameborder="0" scrolling="no" id="myFrame" style="display:none;"></iframe>').appendTo('body');
				}
			<?php }
			if (isset($_SESSION['alertMsg']) && trim((string) $_SESSION['alertMsg']) != "") { ?>
				alert("<?= $_SESSION['alertMsg']; ?>");
			<?php $_SESSION['alertMsg'] = '';
				unset($_SESSION['alertMsg']);
			} ?>

			checkLoginAttempts();
		});



		function checkLoginAttempts() {
			captchaflag = false;
			//$('#captcha').hide();
			$("#challengeResponse").removeClass("isRequired");

			if ($.trim($("#username").val()) != '') {
				$.post("/login/check-login-attempts.php", {
						loginId: $("#username").val(),
						format: "html"
					})
					.done(function(data) {
						try {
							// Parse the JSON response
							const response = JSON.parse(data);

							if (response.captchaRequired) {
								captchaflag = true;
								$('#captcha').show();
								getCaptcha('capChaw');
								$("#challengeResponse").addClass("isRequired");
							} else {
								//$('#captcha').hide(); // Hide CAPTCHA if not required
								$("#challengeResponse").removeClass("isRequired");
							}

							// Handle any error message in the response
							if (response.error) {
								alert(response.error); // Show an error message if any
							}

						} catch (e) {
							console.error("Invalid JSON response:", data); // Log invalid JSON for debugging
						}
					})
					.fail(function(jqXHR, textStatus, errorThrown) {
						// Handle AJAX errors
						console.error("Error details:", errorThrown);
					});
			}
		}
	</script>
</body>

</html>