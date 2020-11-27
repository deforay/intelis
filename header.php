<?php

if (!isset($_SESSION['userId'])) {
	header("location:/login.php");
}

$general = new \Vlsm\Models\General($db);

/* Crosss Login Block Start */
$crossLoginQuery = "SELECT `login_id`,`password`,`user_name` FROM `user_details` WHERE user_id = '" . $_SESSION['userId'] . "'";
$crossLoginResult = $db->rawQueryOne($crossLoginQuery);
/* Crosss Login Block End */

$arr = $general->getGlobalConfig();
$sarr = $general->getSystemConfig();


$skin = "skin-blue";

$logoName = "<img src='/assets/img/flask.png' style='margin-top:-5px;max-width:22px;'> <span style=''>VLSM</span>";
$smallLogoName = "<img src='/assets/img/flask.png'>";
$systemType = "Lab Sample Management Module";
$shortName = "Sample Management";
if (isset($sarr['user_type']) && $sarr['user_type'] == 'remoteuser') {
	$skin = "skin-red";
	$systemType = "Remote Sample Tracking Module";
	$logoName = "<i class='fa fa-medkit'></i> VLSTS";
	$smallLogoName = "<i class='fa fa-medkit'></i>";
	$shortName = "Sample Tracking";
}

if (isset($systemConfig['instanceName']) && !empty($systemConfig['instanceName'])) {
	$systemType = $systemConfig['instanceName'];
}
// print_r($systemConfig);die;
if (isset($arr['default_time_zone']) && $arr['default_time_zone'] != '') {
	date_default_timezone_set($arr['default_time_zone']);
} else {
	date_default_timezone_set(!empty(date_default_timezone_get()) ?  date_default_timezone_get() : "UTC");
}
$hideResult = '';
$hideRequest = '';
if (isset($arr['instance_type']) && $arr['instance_type'] != '') {
	if ($arr['instance_type'] == 'Clinic/Lab') {
		$hideResult = "display:none;";
	}
}


$link = $_SERVER['PHP_SELF'];
$link_array = explode('/', $link);

$currentFileName = end($link_array);

// These files don't need privileges check
$skipPrivilegeCheckFiles = array(
	'401.php',
	'404.php',
	'editProfile.php',
	'vlExportField.php'
);

// on the left put intermediate/inner file, on the right put the file
// which has entry in privileges table.
$sharedPrivileges = array(

	'updateVlTestResult.php'       					=> 'vlTestResult.php',
	'eid-add-batch-position.php'  					=> 'eid-add-batch.php',
	'eid-edit-batch-position.php' 					=> 'eid-edit-batch.php',
	'eid-update-result.php'       					=> 'eid-manual-results.php',
	'covid-19-add-batch-position.php'				=> 'covid-19-add-batch.php',
	'mail-covid-19-results.php'  					=> 'covid-19-print-results.php',
	'covid-19-result-mail-confirm.php'  			=> 'covid-19-print-results.php',
	'covid-19-edit-batch-position.php' 				=> 'covid-19-edit-batch.php',
	'covid-19-update-result.php'       				=> 'covid-19-manual-results.php',
	'imported-results.php'        					=> 'addImportResult.php',
	'importedStatistics.php'      					=> 'addImportResult.php',
	'eid-bulk-import-request.php'					=> 'eid-add-request.php',
	'covid-19-bulk-import-request.php'				=> 'covid-19-add-request.php',
	'covid-19-quick-add.php'						=> 'covid-19-add-request.php',
	'mapTestType.php'								=> 'addFacility.php',
	'covid19-sample-rejection-reasons.php'			=> 'covid19-sample-type.php',
	'add-covid19-sample-rejection-reason.php'		=> 'covid19-sample-type.php',
	'edit-covid19-sample-rejection-reason.php'		=> 'covid19-sample-type.php',
	'covid19-comorbidities.php'						=> 'covid19-sample-type.php',
	'add-covid19-comorbidities.php'					=> 'covid19-sample-type.php',
	'edit-covid19-comorbidities.php'				=> 'covid19-sample-type.php',
	'covid19-symptoms.php'							=> 'covid19-sample-type.php',
	'covid19-test-reasons.php'						=> 'covid19-sample-type.php',
	'add-covid19-sample-type.php'					=> 'covid19-sample-type.php',
	'edit-covid19-sample-type.php'					=> 'covid19-sample-type.php',
	'covid19-test-symptoms.php'						=> 'covid19-sample-type.php',
	'add-covid19-symptoms.php'						=> 'covid19-sample-type.php',
	'edit-covid19-symptoms.php'						=> 'covid19-sample-type.php',
	'covid19-test-reasons.php'						=> 'covid19-sample-type.php',
	'add-covid19-test-reasons.php'					=> 'covid19-sample-type.php',
	'edit-covid19-test-reasons.php'					=> 'covid19-sample-type.php',
	'add-vl-art-code-details.php'					=> 'vl-art-code-details.php',
	'edit-vl-art-code-details.php'					=> 'vl-art-code-details.php',
	'vl-sample-rejection-reasons.php'				=> 'vl-art-code-details.php',
	'add-vl-sample-rejection-reasons.php'			=> 'vl-art-code-details.php',
	'edit-vl-sample-rejection-reasons.php'			=> 'vl-art-code-details.php',
	'vl-sample-type.php'							=> 'vl-art-code-details.php',
	'edit-vl-sample-type.php'						=> 'vl-art-code-details.php',
	'add-vl-sample-type.php'						=> 'vl-art-code-details.php',
	'vl-test-reasons.php'							=> 'vl-art-code-details.php',
	'add-vl-test-reasons.php'						=> 'vl-art-code-details.php',
	'edit-vl-test-reasons.php'						=> 'vl-art-code-details.php',
	'eid-sample-rejection-reasons.php'				=> 'eid-sample-type.php',
	'add-eid-sample-rejection-reasons.php'			=> 'eid-sample-type.php',
	'edit-eid-sample-rejection-reasons.php'			=> 'eid-sample-type.php',
	'add-eid-sample-type.php'						=> 'eid-sample-type.php',
	'edit-eid-sample-type.php'						=> 'eid-sample-type.php',
	'eid-test-reasons.php'							=> 'eid-sample-type.php',
	'add-eid-test-reasons.php'						=> 'eid-sample-type.php',
	'edit-eid-test-reasons.php'						=> 'eid-sample-type.php',
	'add-province.php'								=> 'province-details.php',
	'edit-province.php'								=> 'province-details.php',
	'implementation-partners.php'					=> 'province-details.php',
	'add-implementation-partners.php'				=> 'province-details.php',
	'edit-implementation-partners.php'				=> 'province-details.php',
	'funding-sources.php'							=> 'province-details.php',
	'add-funding-sources.php'						=> 'province-details.php',
	'edit-funding-sources.php'						=> 'province-details.php',
	'hepatitis-update-result.php'       			=> 'hepatitis-manual-results.php',
	'hepatitis-sample-rejection-reasons.php'		=> 'hepatitis-sample-type.php',
	'add-hepatitis-sample-rejection-reasons.php'	=> 'hepatitis-sample-type.php',
	'edit-hepatitis-sample-rejection-reasons.php'	=> 'hepatitis-sample-type.php',
	'hepatitis-comorbidities.php'					=> 'hepatitis-sample-type.php',
	'add-hepatitis-comorbidities.php'				=> 'hepatitis-sample-type.php',
	'edit-hepatitis-comorbidities.php'				=> 'hepatitis-sample-type.php',
	'hepatitis-test-reasons.php'					=> 'hepatitis-sample-type.php',
	'add-hepatitis-sample-type.php'					=> 'hepatitis-sample-type.php',
	'edit-hepatitis-sample-type.php'				=> 'hepatitis-sample-type.php',
	'hepatitis-results.php'							=> 'hepatitis-sample-type.php',
	'add-hepatitis-results.php'						=> 'hepatitis-sample-type.php',
	'edit-hepatitis-results.php'					=> 'hepatitis-sample-type.php',
	'hepatitis-risk-factors.php'					=> 'hepatitis-sample-type.php',
	'add-hepatitis-risk-factors.php'				=> 'hepatitis-sample-type.php',
	'edit-hepatitis-risk-factors.php'				=> 'hepatitis-sample-type.php',
	'hepatitis-test-reasons.php'					=> 'hepatitis-sample-type.php',
	'add-hepatitis-test-reasons.php'				=> 'hepatitis-sample-type.php',
	'edit-hepatitis-test-reasons.php'				=> 'hepatitis-sample-type.php',
	
);
// Does the current file share privileges with another privilege ?
$currentFileName = isset($sharedPrivileges[$currentFileName]) ? $sharedPrivileges[$currentFileName] : $currentFileName;


if (!in_array($currentFileName, $skipPrivilegeCheckFiles)) {
	if (isset($_SESSION['privileges']) && !in_array($currentFileName, $_SESSION['privileges'])) {
		header("location:/error/401.php");
	}
}
// echo "<pre>";print_r($_SESSION['privileges']);die;
// if(isset($_SERVER['HTTP_REFERER'])){
//   $previousUrl = $_SERVER['HTTP_REFERER'];
//   $urlLast = explode('/',$previousUrl);
//   if(end($urlLast)=='importedStatistics.php'){
//       $db = $db->where('imported_by', $_SESSION['userId']);
//       $db->delete('temp_sample_import');
//       unset($_SESSION['controllertrack']);
//   }
// }

/* echo "<pre>";print_r($_SESSION['privileges']);
echo "<pre>";print_r($_SESSION['module']);die; */
if (isset($_SESSION['privileges']) && array_intersect($_SESSION['privileges'], array('roles.php', 'users.php', 'facilities.php', 'globalConfig.php', 'importConfig.php', 'otherConfig.php'))) {
	$allAdminMenuAccess = true;
} else {
	$allAdminMenuAccess = false;
}
if (isset($_SESSION['privileges']) && array_intersect($_SESSION['privileges'], array('vlRequest.php', 'addVlRequest.php', 'addSamplesFromManifest.php', 'batchcode.php', 'vlRequestMail.php', 'specimenReferralManifestList.php', 'sampleList.php'))) {
	$vlRequestMenuAccess = true;
} else {
	$vlRequestMenuAccess = false;
}
if (isset($_SESSION['privileges']) && array_intersect($_SESSION['privileges'], array('addImportResult.php', 'vlTestResult.php', 'vlResultApproval.php', 'vlResultMail.php'))) {
	$vlTestResultMenuAccess = true;
} else {
	$vlTestResultMenuAccess = false;
}
if (isset($_SESSION['privileges']) && array_intersect($_SESSION['privileges'], array('vl-sample-status.php', 'vlResult.php', 'highViralLoad.php', 'vlControlReport.php', 'vlWeeklyReport.php', 'sampleRejectionReport.php', 'vlMonitoringReport.php'))) {
	$vlManagementMenuAccess = true;
} else {
	$vlManagementMenuAccess = false;
}

// EID MENUS
if (isset($_SESSION['privileges']) && array_intersect($_SESSION['privileges'], array('eid-requests.php', 'eid-add-request.php', 'addSamplesFromManifest.php', 'eid-batches.php', 'specimenReferralManifestList.php'))) {
	$eidTestRequestMenuAccess = true;
} else {
	$eidTestRequestMenuAccess = false;
}

if (isset($_SESSION['privileges']) && array_intersect($_SESSION['privileges'], array('addImportResult.php', 'eid-manual-results.php', 'eid-result-status.php'))) {
	$eidTestResultMenuAccess = true;
} else {
	$eidTestResultMenuAccess = false;
}

if (isset($_SESSION['privileges']) && array_intersect($_SESSION['privileges'], array('eid-sample-status.php', 'eid-export-data.php', 'eid-print-results.php', 'eid-sample-rejection-report.php', 'eid-clinic-report.php'))) {
	$eidManagementMenuAccess = true;
} else {
	$eidManagementMenuAccess = false;
}

if (isset($_SESSION['privileges']) && array_intersect($_SESSION['privileges'], array('index.php'))) {
	$dashBoardMenuAccess = true;
} else {
	$dashBoardMenuAccess = false;
}

// COVID-19 Menu start
if (isset($_SESSION['privileges']) && array_intersect($_SESSION['privileges'], array("covid-19-requests.php", "covid-19-add-request.php", "covid-19-edit-request.php", "addSamplesFromManifest.php", "covid-19-batches.php", "specimenReferralManifestList.php"))) {
	$covid19TestRequestMenuAccess = true;
} else {
	$covid19TestRequestMenuAccess = false;
}

if (isset($_SESSION['privileges']) && array_intersect($_SESSION['privileges'], array('addImportResult.php', 'covid-19-manual-results.php', 'covid-19-confirmation-manifest.php', 'can-record-confirmatory-tests.php', 'covid-19-result-status.php', 'covid-19-print-results.php'))) {
	$covid19TestResultMenuAccess = true;
} else {
	$covid19TestResultMenuAccess = false;
}

if (isset($_SESSION['privileges']) && array_intersect($_SESSION['privileges'], array('covid-19-export-data.php', 'covid-19-sample-rejection-report.php', 'covid-19-sample-status.php', 'covid-19-print-results.php'))) {
	$covid19ManagementMenuAccess = true;
} else {
	$covid19ManagementMenuAccess = false;
}
// COVID-19 Menu end

// HEPATITIS Menu start
if (isset($_SESSION['privileges']) && array_intersect($_SESSION['privileges'], array("hepatitis-requests.php", "hepatitis-add-request.php", "hepatitis-edit-request.php", "add-Samples-from-manifest.php"))) {
	$hepatitisTestRequestMenuAccess = true;
} else {
	$hepatitisTestRequestMenuAccess = false;
}

if (isset($_SESSION['privileges']) && array_intersect($_SESSION['privileges'], array("addImportResult.php", "hepatitis-manual-results.php", "hepatitis-result-status.php", "hepatitis-print-results.php"))) {
	$hepatitisTestResultMenuAccess = true;
} else {
	$hepatitisTestResultMenuAccess = false;
}

if (isset($_SESSION['privileges']) && array_intersect($_SESSION['privileges'], array("hepatitis-sample-status.php", "hepatitis-export-data.php", "hepatitis-print-results.php", "hepatitis-sample-rejection-report.php", "hepatitis-clinic-report.php", "hepatitisMonthlyThresholdReport"))) {
	$hepatitisManagementMenuAccess = true;
} else {
	$hepatitisManagementMenuAccess = false;
}
// HEPATITIS Menu end

?>
<!DOCTYPE html>
<html lang="en-US">

<head>
	<meta charset="utf-8" />
	<meta http-equiv="X-UA-Compatible" content="IE=edge" />
	<title><?php echo $shortName . " | " . ((isset($title) && $title != null && $title != "") ? $title : "VLSM"); ?></title>
	<!-- Tell the browser to be responsive to screen width -->
	<meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">


	<?php if (isset($sarr['user_type']) && $sarr['user_type'] == 'remoteuser') { ?>
		<link rel="apple-touch-icon" sizes="180x180" href="/vlsts-icons/apple-touch-icon.png">
		<link rel="icon" type="image/png" sizes="32x32" href="/vlsts-icons/favicon-32x32.png">
		<link rel="icon" type="image/png" sizes="16x16" href="/vlsts-icons/favicon-16x16.png">
		<link rel="manifest" href="/vlsts-icons/site.webmanifest">
	<?php } else { ?>
		<link rel="apple-touch-icon" sizes="180x180" href="/vlsm-icons/apple-touch-icon.png">
		<link rel="icon" type="image/png" sizes="32x32" href="/vlsm-icons/favicon-32x32.png">
		<link rel="icon" type="image/png" sizes="16x16" href="/vlsm-icons/favicon-16x16.png">
		<link rel="manifest" href="/vlsm-icons/site.webmanifest">
	<?php } ?>


	<link rel="stylesheet" media="all" type="text/css" href="/assets/css/fonts.css" />

	<link rel="stylesheet" media="all" type="text/css" href="/assets/css/jquery-ui.1.11.0.css" />
	<link rel="stylesheet" media="all" type="text/css" href="/assets/css/jquery-ui-timepicker-addon.css" />

	<!-- Bootstrap 3.3.6 -->
	<link rel="stylesheet" href="/assets/css/bootstrap.min.css">
	<!-- Font Awesome -->
	<link rel="stylesheet" href="/assets/css/font-awesome.min.4.5.0.css">

	<!-- Ionicons -->
	<!--<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/ionicons/2.0.1/css/ionicons.min.css">-->
	<!-- DataTables -->
	<link rel="stylesheet" href="/assets/plugins/datatables/dataTables.bootstrap.css">
	<!-- Theme style -->
	<link rel="stylesheet" href="/assets/css/AdminLTE.min.css">
	<!-- AdminLTE Skins. Choose a skin from the css/skins
       folder instead of downloading all of them to reduce the load. -->
	<link rel="stylesheet" href="/assets/css/skins/_all-skins.min.css">
	<!-- iCheck -->

	<link href="/assets/plugins/daterangepicker/daterangepicker.css" rel="stylesheet" />

	<link href="/assets/css/select2.min.css" rel="stylesheet" />
	<link href="/assets/css/style.css" rel="stylesheet" />
	<link href="/assets/css/deforayModal.css" rel="stylesheet" />
	<link href="/assets/css/jquery.fastconfirm.css" rel="stylesheet" />


	<!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
	<!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
	<!--[if lt IE 9]>
  	<script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
  	<script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
  <![endif]-->
	<!-- jQuery 2.2.3 -->

	<script type="text/javascript" src="/assets/js/jquery.min.js"></script>

	<!-- Latest compiled and minified JavaScript -->

	<script type="text/javascript" src="/assets/js/jquery-ui.1.11.0.js"></script>
	<script src="/assets/js/deforayModal.js"></script>
	<script src="/assets/js/jquery.fastconfirm.js"></script>
	<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-beta.1/dist/css/select2.min.css" rel="stylesheet" />
	<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-beta.1/dist/js/select2.min.js"></script>
	<!--<script type="text/javascript" src="assets/js/jquery-ui-sliderAccess.js"></script>-->
	<style>
		.dataTables_empty {
			text-align: center;
		}

		.dataTables_wrapper {
			position: relative;
			clear: both;
			overflow-x: scroll !important;
			overflow-y: visible !important;
			padding: 15px 0 !important;
		}

		.select2-selection__choice__remove {
			color: red !important;
		}

		.select2-container--default .select2-selection--multiple .select2-selection__choice {
			/* background-color: #00c0ef;
			border-color: #00acd6; */
			color: #000 !important;
			font-family: helvetica, arial, sans-serif;
		}

		.skin-blue .sidebar-menu>li.header {
			background: #ddd;
			color: #333;
			font-weight: bold;
		}

		.skin-red .sidebar-menu>li.header {
			background: #ddd;
			color: #333;
			font-weight: bold;
		}

		.select2-container .select2-selection--single {
			height: auto !important;
		}

		.select2-container--default .select2-selection--single .select2-selection__arrow {
			top: 6px !important;
		}

		.select2-container--default .select2-selection--single .select2-selection__rendered {
			line-height: 22px !important;
		}

		.select2-container .select2-selection--single .select2-selection__rendered {
			margin-top: 0px !important;
		}
	</style>
</head>

<body class="hold-transition <?php echo $skin; ?> sidebar-mini">
	<div class="wrapper">
		<header class="main-header">
			<!-- Logo -->
			<a href="<?php echo ($dashBoardMenuAccess == true) ? '/dashboard/index.php' : '#'; ?>" class="logo">
				<!-- mini logo for sidebar mini 50x50 pixels -->
				<span class="logo-mini"><b><?php echo $smallLogoName; ?></b></span>
				<!-- logo for regular state and mobile devices -->
				<span class="logo-lg" style="font-weight:bold;"><?php echo $logoName; ?></span>
			</a>
			<!-- Header Navbar: style can be found in header.less -->
			<nav class="navbar navbar-static-top">
				<!-- Sidebar toggle button-->
				<a href="#" class="sidebar-toggle" data-toggle="offcanvas" role="button">
					<span class="sr-only">Toggle navigation</span>
				</a>
				<ul class="nav navbar-nav">
					<li>
						<a href="javascript:void(0);return false;"><span style="text-transform: uppercase;font-weight:600;"><?php echo $systemType; ?></span></a>
					</li>
				</ul>
				<div class="navbar-custom-menu">
					<ul class="nav navbar-nav">
						<?php if ($recencyConfig['crosslogin']) {
							$password = $crossLoginResult['password'] . $recencyConfig['crossloginSalt']; ?>
							<li class="user-menu">
								<a onclick="setCrossLogin();" href="<?php echo rtrim($recencyConfig['url'], "/") . '/login?u=' . base64_encode($crossLoginResult['login_id']) . '&t=' . hash('sha256', $password) . '&name=' . base64_encode($crossLoginResult['user_name']); ?>" class="btn btn-link"><i class="fa fa-fw fa-external-link"></i> Recency</a>
							</li>
						<?php } ?>
						<li class="dropdown user user-menu">
							<a href="#" class="dropdown-toggle" data-toggle="dropdown">
								<img src="/assets/img/default-user.png" class="user-image" alt="User Image">
								<span class="hidden-xs"><?php if (isset($_SESSION['userName'])) {
															echo $_SESSION['userName'];
														} ?></span>
							</a>
							<ul class="dropdown-menu">
								<!-- Menu Footer-->
								<?php $alignRight = '';
								$showProfileBtn = "style=display:none;";
								if ($arr['edit_profile'] != 'no') {
									$alignRight = "pull-right-xxxxx";
									$showProfileBtn = "style=display:block;";
								} ?>
								<li class="user-footer" <?php echo $showProfileBtn; ?>>
									<a href="/users/editProfile.php" class="">Edit Profile</a>
								</li>
								<li class="user-footer <?php echo $alignRight; ?>">
									<a href="/logout.php">Sign out</a>
								</li>

							</ul>
						</li>
					</ul>
				</div>
			</nav>
		</header>
		<!-- Left side column. contains the logo and sidebar -->
		<aside class="main-sidebar">
			<!-- sidebar: style can be found in sidebar.less -->
			<section class="sidebar">
				<!-- sidebar menu: : style can be found in sidebar.less -->
				<!-- Sidebar user panel -->
				<?php if (isset($arr['logo']) && trim($arr['logo']) != "" && file_exists('uploads' . DIRECTORY_SEPARATOR . "logo" . DIRECTORY_SEPARATOR . $arr['logo'])) { ?>
					<div class="user-panel">
						<div align="center">
							<img src="/uploads/logo/<?php echo $arr['logo']; ?>" alt="Logo Image" style="max-width:120px;">
						</div>
					</div>
				<?php } ?>
				<ul class="sidebar-menu">
					<?php if ($dashBoardMenuAccess == true) { ?>
						<li class="allMenu dashboardMenu active">
							<a href="/dashboard/index.php">
								<i class="fa fa-dashboard"></i> <span>Dashboard</span>
							</a>
						</li>
					<?php }
					if ($allAdminMenuAccess == true && array_intersect($_SESSION['module'], array('admin'))) { ?>
						<li class="treeview manage">
							<a href="#">
								<i class="fa fa-gears"></i>
								<span>Admin</span>
								<span class="pull-right-container">
									<i class="fa fa-angle-left pull-right"></i>
								</span>
							</a>
							<ul class="treeview-menu">

								<?php if (isset($_SESSION['privileges']) && in_array("roles.php", $_SESSION['privileges'])) { ?>
									<li class="allMenu roleMenu">
										<a href="/roles/roles.php"><i class="fa fa-circle-o"></i> Roles</a>
									</li>
								<?php }
								if (isset($_SESSION['privileges']) && in_array("users.php", $_SESSION['privileges'])) { ?>
									<li class="allMenu userMenu">
										<a href="/users/users.php"><i class="fa fa-circle-o"></i> Users</a>
									</li>
								<?php }
								if (isset($_SESSION['privileges']) && in_array("facilities.php", $_SESSION['privileges'])) { ?>
									<li class="allMenu facilityMenu">
										<a href="/facilities/facilities.php"><i class="fa fa-circle-o"></i> Facilities</a>
									</li>
								<?php }
								if (isset($_SESSION['privileges']) && in_array("facilityMap.php", $_SESSION['privileges']) && ($sarr['user_type'] == 'remoteuser')) { ?>
									<li class="allMenu facilityMapMenu">
										<a href="/facilities/facilityMap.php"><i class="fa fa-circle-o"></i>Facility Map</a>
									</li>
								<?php }
								if (isset($_SESSION['privileges']) && in_array("globalConfig.php", $_SESSION['privileges'])) { ?>
									<li class="allMenu globalConfigMenu">
										<a href="/global-config/globalConfig.php"><i class="fa fa-circle-o"></i> General Configuration</a>
									</li>
								<?php }
								if (isset($_SESSION['privileges']) && in_array("importConfig.php", $_SESSION['privileges'])) { ?>
									<li class="allMenu importConfigMenu">
										<a href="/import-configs/importConfig.php"><i class="fa fa-circle-o"></i> Import Configuration</a>
									</li>
								<?php }
								if (isset($_SESSION['privileges']) && in_array("testRequestEmailConfig.php", $_SESSION['privileges'])) { ?>
									<li class="allMenu requestEmailConfigMenu">
										<a href="/vl/request-mail/testRequestEmailConfig.php"><i class="fa fa-circle-o"></i>Test Request Email/SMS <br>Configuration</a>
									</li>
								<?php }
								if (isset($_SESSION['privileges']) && in_array("testResultEmailConfig.php", $_SESSION['privileges'])) { ?>
									<li class="allMenu resultEmailConfigMenu">
										<a href="/vl/result-mail/testResultEmailConfig.php"><i class="fa fa-circle-o"></i>Test Result Email/SMS <br>Configuration</a>
									</li>
								<?php }
								if (isset($_SESSION['privileges']) && in_array("province-details.php", $_SESSION['privileges'])) { ?>
									<li class="treeview common-reference-manage">
										<a href="#"><i class="fa fa-book"></i>Common Reference
											<span class="pull-right-container">
												<i class="fa fa-angle-left pull-right"></i>
											</span>
										</a>

										<ul class="treeview-menu">
											<li class="allMenu common-reference-province">
												<a href="/common/reference/province-details.php"><i class="fa fa-caret-right"></i>Provinces</a>
											</li>
											<li class="allMenu common-reference-implementation-partners">
												<a href="/common/reference/implementation-partners.php"><i class="fa fa-caret-right"></i>Implementation Partners</a>
											</li>
											<li class="allMenu common-reference-funding-sources">
												<a href="/common/reference/funding-sources.php"><i class="fa fa-caret-right"></i>Funding Sources</a>
											</li>
										</ul>
									</li>
								<?php }
								if (isset($systemConfig['modules']['vl']) && $systemConfig['modules']['vl'] == true && isset($_SESSION['privileges']) && in_array("vl-art-code-details.php", $_SESSION['privileges'])) { ?>
									<li class="treeview vl-reference-manage">
										<a href="#"><i class="fa fa-flask"></i>Viral Load
											<span class="pull-right-container">
												<i class="fa fa-angle-left pull-right"></i>
											</span>
										</a>

										<ul class="treeview-menu">
											<li class="allMenu vl-art-code-details">
												<a href="/vl/reference/vl-art-code-details.php"><i class="fa fa-caret-right"></i>ART Regimen</a>
											</li>
											<li class="allMenu vl-sample-rejection-reasons">
												<a href="/vl/reference/vl-sample-rejection-reasons.php"><i class="fa fa-caret-right"></i>Rejection Reasons</a>
											</li>
											<li class="allMenu vl-sample-type">
												<a href="/vl/reference/vl-sample-type.php"><i class="fa fa-caret-right"></i>Sample Type</a>
											</li>
											<li class="allMenu vl-test-reasons">
												<a href="/vl/reference/vl-test-reasons.php"><i class="fa fa-caret-right"></i>Test Reasons</a>
											</li>
										</ul>
									</li>
								<?php }
								if (isset($systemConfig['modules']['eid']) && $systemConfig['modules']['eid'] == true && isset($_SESSION['privileges']) && in_array("eid-sample-type.php", $_SESSION['privileges'])) { ?>
									<li class="treeview eid-reference-manage">
										<a href="#"><i class="fa fa-child"></i>EID
											<span class="pull-right-container">
												<i class="fa fa-angle-left pull-right"></i>
											</span>
										</a>

										<ul class="treeview-menu">
											<li class="allMenu eid-sample-rejection-reasons">
												<a href="/eid/reference/eid-sample-rejection-reasons.php"><i class="fa fa-caret-right"></i>Rejection Reasons</a>
											</li>
											<li class="allMenu eid-sample-type">
												<a href="/eid/reference/eid-sample-type.php"><i class="fa fa-caret-right"></i>Sample Type</a>
											</li>
											<li class="allMenu eid-test-reasons">
												<a href="/eid/reference/eid-test-reasons.php"><i class="fa fa-caret-right"></i>Test Reasons</a>
											</li>
										</ul>
									</li>
								<?php }
								if (isset($systemConfig['modules']['covid19']) && $systemConfig['modules']['covid19'] == true && isset($_SESSION['privileges']) && in_array("covid19-sample-type.php", $_SESSION['privileges'])) { ?>
									<li class="treeview covid19-reference-manage">
										<a href="#"><i><svg style=" width: 20px; " aria-hidden="true" focusable="false" data-prefix="fas" data-icon="viruses" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 512" class="svg-inline--fa fa-viruses fa-w-20">
													<path fill="currentColor" d="M624,352H611.88c-28.51,0-42.79-34.47-22.63-54.63l8.58-8.57a16,16,0,1,0-22.63-22.63l-8.57,8.58C546.47,294.91,512,280.63,512,252.12V240a16,16,0,0,0-32,0v12.12c0,28.51-34.47,42.79-54.63,22.63l-8.57-8.58a16,16,0,0,0-22.63,22.63l8.58,8.57c20.16,20.16,5.88,54.63-22.63,54.63H368a16,16,0,0,0,0,32h12.12c28.51,0,42.79,34.47,22.63,54.63l-8.58,8.57a16,16,0,1,0,22.63,22.63l8.57-8.58c20.16-20.16,54.63-5.88,54.63,22.63V496a16,16,0,0,0,32,0V483.88c0-28.51,34.47-42.79,54.63-22.63l8.57,8.58a16,16,0,1,0,22.63-22.63l-8.58-8.57C569.09,418.47,583.37,384,611.88,384H624a16,16,0,0,0,0-32ZM480,384a32,32,0,1,1,32-32A32,32,0,0,1,480,384ZM346.51,213.33h16.16a21.33,21.33,0,0,0,0-42.66H346.51c-38,0-57.05-46-30.17-72.84l11.43-11.44A21.33,21.33,0,0,0,297.6,56.23L286.17,67.66c-26.88,26.88-72.84,7.85-72.84-30.17V21.33a21.33,21.33,0,0,0-42.66,0V37.49c0,38-46,57.05-72.84,30.17L86.4,56.23A21.33,21.33,0,0,0,56.23,86.39L67.66,97.83c26.88,26.88,7.85,72.84-30.17,72.84H21.33a21.33,21.33,0,0,0,0,42.66H37.49c38,0,57.05,46,30.17,72.84L56.23,297.6A21.33,21.33,0,1,0,86.4,327.77l11.43-11.43c26.88-26.88,72.84-7.85,72.84,30.17v16.16a21.33,21.33,0,0,0,42.66,0V346.51c0-38,46-57.05,72.84-30.17l11.43,11.43a21.33,21.33,0,0,0,30.17-30.17l-11.43-11.43C289.46,259.29,308.49,213.33,346.51,213.33ZM160,192a32,32,0,1,1,32-32A32,32,0,0,1,160,192Zm80,32a16,16,0,1,1,16-16A16,16,0,0,1,240,224Z" class=""></path>
												</svg></i>
											Covid-19
											<span class="pull-right-container">
												<i class="fa fa-angle-left pull-right"></i>
											</span>
										</a>

										<ul class="treeview-menu">
											<li class="allMenu covid19-comorbidities">
												<a href="/covid-19/reference/covid19-comorbidities.php"><i class="fa fa-caret-right"></i>Co-morbidities</a>
											</li>
											<li class="allMenu covid19-sample-rejection-reasons">
												<a href="/covid-19/reference/covid19-sample-rejection-reasons.php"><i class="fa fa-caret-right"></i>Rejection Reasons</a>
											</li>
											<li class="allMenu covid19-sample-type">
												<a href="/covid-19/reference/covid19-sample-type.php"><i class="fa fa-caret-right"></i>Sample Type</a>
											</li>
											<li class="allMenu covid19-symptoms">
												<a href="/covid-19/reference/covid19-symptoms.php"><i class="fa fa-caret-right"></i>Symptom</a>
											</li>
											<li class="allMenu covid19-test-reasons">
												<a href="/covid-19/reference/covid19-test-reasons.php"><i class="fa fa-caret-right"></i>Test-Reasons</a>
											</li>
										</ul>
									</li>
								<?php } if (isset($systemConfig['modules']['hepatitis']) && $systemConfig['modules']['hepatitis'] == true && isset($_SESSION['privileges']) && in_array("hepatitis-sample-type.php", $_SESSION['privileges'])) { ?>
									<li class="treeview hepatitis-reference-manage">
										<a href="#"><i><svg style=" width: 20px; " aria-hidden="true" focusable="false" data-prefix="fas" data-icon="viruses" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 512" class="svg-inline--fa fa-viruses fa-w-20">
													<path fill="currentColor" d="M624,352H611.88c-28.51,0-42.79-34.47-22.63-54.63l8.58-8.57a16,16,0,1,0-22.63-22.63l-8.57,8.58C546.47,294.91,512,280.63,512,252.12V240a16,16,0,0,0-32,0v12.12c0,28.51-34.47,42.79-54.63,22.63l-8.57-8.58a16,16,0,0,0-22.63,22.63l8.58,8.57c20.16,20.16,5.88,54.63-22.63,54.63H368a16,16,0,0,0,0,32h12.12c28.51,0,42.79,34.47,22.63,54.63l-8.58,8.57a16,16,0,1,0,22.63,22.63l8.57-8.58c20.16-20.16,54.63-5.88,54.63,22.63V496a16,16,0,0,0,32,0V483.88c0-28.51,34.47-42.79,54.63-22.63l8.57,8.58a16,16,0,1,0,22.63-22.63l-8.58-8.57C569.09,418.47,583.37,384,611.88,384H624a16,16,0,0,0,0-32ZM480,384a32,32,0,1,1,32-32A32,32,0,0,1,480,384ZM346.51,213.33h16.16a21.33,21.33,0,0,0,0-42.66H346.51c-38,0-57.05-46-30.17-72.84l11.43-11.44A21.33,21.33,0,0,0,297.6,56.23L286.17,67.66c-26.88,26.88-72.84,7.85-72.84-30.17V21.33a21.33,21.33,0,0,0-42.66,0V37.49c0,38-46,57.05-72.84,30.17L86.4,56.23A21.33,21.33,0,0,0,56.23,86.39L67.66,97.83c26.88,26.88,7.85,72.84-30.17,72.84H21.33a21.33,21.33,0,0,0,0,42.66H37.49c38,0,57.05,46,30.17,72.84L56.23,297.6A21.33,21.33,0,1,0,86.4,327.77l11.43-11.43c26.88-26.88,72.84-7.85,72.84,30.17v16.16a21.33,21.33,0,0,0,42.66,0V346.51c0-38,46-57.05,72.84-30.17l11.43,11.43a21.33,21.33,0,0,0,30.17-30.17l-11.43-11.43C289.46,259.29,308.49,213.33,346.51,213.33ZM160,192a32,32,0,1,1,32-32A32,32,0,0,1,160,192Zm80,32a16,16,0,1,1,16-16A16,16,0,0,1,240,224Z" class=""></path>
												</svg></i>
											Hepatitis
											<span class="pull-right-container">
												<i class="fa fa-angle-left pull-right"></i>
											</span>
										</a>

										<ul class="treeview-menu">
											<li class="allMenu hepatitis-comorbidities">
												<a href="/hepatitis/reference/hepatitis-comorbidities.php"><i class="fa fa-caret-right"></i>Co-morbidities</a>
											</li>
											<li class="allMenu hepatitis-risk-factors">
												<a href="/hepatitis/reference/hepatitis-risk-factors.php"><i class="fa fa-caret-right"></i>Risk Factors</a>
											</li>
											<li class="allMenu hepatitis-sample-rejection-reasons">
												<a href="/hepatitis/reference/hepatitis-sample-rejection-reasons.php"><i class="fa fa-caret-right"></i>Rejection Reasons</a>
											</li>
											<li class="allMenu hepatitis-sample-type">
												<a href="/hepatitis/reference/hepatitis-sample-type.php"><i class="fa fa-caret-right"></i>Sample Type</a>
											</li>
											<li class="allMenu hepatitis-results">
												<a href="/hepatitis/reference/hepatitis-results.php"><i class="fa fa-caret-right"></i>Results</a>
											</li>
											<li class="allMenu hepatitis-test-reasons">
												<a href="/hepatitis/reference/hepatitis-test-reasons.php"><i class="fa fa-caret-right"></i>Test-Reasons</a>
											</li>
										</ul>
									</li>
								<?php } ?>
							</ul>
						</li>
					<?php } ?>

					<?php
					if (isset($systemConfig['modules']['vl']) && $systemConfig['modules']['vl'] == true && array_intersect($_SESSION['module'], array('vl'))) { ?>
						<li class="header">VIRAL LOAD</li>
						<?php if ($vlRequestMenuAccess == true) { ?>
							<li class="treeview request" style="<?php echo $hideRequest; ?>">
								<a href="#">
									<i class="fa fa-edit"></i>
									<span>Request Management</span>
									<span class="pull-right-container">
										<i class="fa fa-angle-left pull-right"></i>
									</span>
								</a>
								<ul class="treeview-menu">
									<?php
									if (isset($_SESSION['privileges']) && in_array("vlRequest.php", $_SESSION['privileges'])) { ?>
										<li class="allMenu vlRequestMenu">
											<a href="/vl/requests/vlRequest.php"><i class="fa fa-circle-o"></i> View Test Requests</a>
										</li>
									<?php }
									if (isset($_SESSION['privileges']) && in_array("addVlRequest.php", $_SESSION['privileges'])) { ?>
										<li class="allMenu addVlRequestMenu">
											<a href="/vl/requests/addVlRequest.php"><i class="fa fa-circle-o"></i> Add New Request</a>
										</li>
									<?php }
									if (isset($_SESSION['privileges']) && in_array("addSamplesFromManifest.php", $_SESSION['privileges']) && ($sarr['user_type'] != 'remoteuser')) { ?>
										<li class="allMenu addSamplesFromManifestMenu">
											<a href="/vl/requests/addSamplesFromManifest.php"><i class="fa fa-circle-o"></i> Add Samples from Manifest</a>
										</li>
									<?php }
									if (isset($_SESSION['privileges']) && in_array("batchcode.php", $_SESSION['privileges'])) { ?>
										<li class="allMenu batchCodeMenu">
											<a href="/vl/batch/batchcode.php"><i class="fa fa-circle-o"></i> Manage Batch</a>
										</li>
									<?php }
									if (isset($_SESSION['privileges']) && in_array("vlRequestMail.php", $_SESSION['privileges'])) { ?>
										<li class="allMenu vlRequestMailMenu">
											<a href="/mail/vlRequestMail.php"><i class="fa fa-circle-o"></i> E-mail Test Request</a>
										</li>
									<?php }
									if (isset($_SESSION['privileges']) && in_array("specimenReferralManifestList.php", $_SESSION['privileges']) && ($sarr['user_type'] == 'remoteuser')) { ?>
										<li class="allMenu specimenReferralManifestListMenu">
											<a href="/specimen-referral-manifest/specimenReferralManifestList.php?t=<?php echo base64_encode('vl'); ?>"><i class="fa fa-circle-o"></i> VL Specimen Manifest</a>
										</li>
									<?php }
									if (isset($_SESSION['privileges']) && in_array("sampleList.php", $_SESSION['privileges']) && ($sarr['user_type'] == 'remoteuser')) { ?>
										<!-- <li class="allMenu sampleListMenu">
                                            <a href="/move-samples/sampleList.php"><i class="fa fa-circle-o"></i> Move Samples</a>
                                          </li> -->
									<?php } ?>
								</ul>
							</li>
						<?php }
						if ($vlTestResultMenuAccess == true) { ?>
							<li class="treeview test" style="<?php echo $hideResult; ?>">
								<a href="#">
									<i class="fa fa-tasks"></i>
									<span>Test Result Management</span>
									<span class="pull-right-container">
										<i class="fa fa-angle-left pull-right"></i>
									</span>
								</a>
								<ul class="treeview-menu">
									<?php if (isset($_SESSION['privileges']) && in_array("addImportResult.php", $_SESSION['privileges'])) { ?>
										<li class="allMenu importResultMenu"><a href="/import-result/addImportResult.php?t=<?php echo base64_encode('vl'); ?>"><i class="fa fa-circle-o"></i> Import Result From File</a></li>
									<?php }
									if (isset($_SESSION['privileges']) && in_array("vlTestResult.php", $_SESSION['privileges'])) { ?>
										<li class="allMenu vlTestResultMenu"><a href="/vl/results/vlTestResult.php"><i class="fa fa-circle-o"></i> Enter Result Manually</a></li>
									<?php }
									if (isset($_SESSION['privileges']) && in_array("vlResultApproval.php", $_SESSION['privileges'])) { ?>
										<li class="allMenu vlResultApprovalMenu"><a href="/vl/results/vlResultApproval.php"><i class="fa fa-circle-o"></i> Approve Results</a></li>
									<?php }
									if (isset($_SESSION['privileges']) && in_array("vlResultMail.php", $_SESSION['privileges'])) { ?>
										<li class="allMenu vlResultMailMenu"><a href="/mail/vlResultMail.php"><i class="fa fa-circle-o"></i> E-mail Test Result</a></li>
									<?php }  ?>
								</ul>
							</li>
						<?php }
						if ($vlManagementMenuAccess == true) { ?>
							<li class="treeview program">
								<a href="#">
									<i class="fa fa-book"></i>
									<span>Management</span>
									<span class="pull-right-container">
										<i class="fa fa-angle-left pull-right"></i>
									</span>
								</a>
								<ul class="treeview-menu">
									<?php if (isset($_SESSION['privileges']) && in_array("vl-sample-status.php", $_SESSION['privileges'])) { ?>
										<li class="allMenu missingResultMenu"><a href="/vl/program-management/vl-sample-status.php"><i class="fa fa-circle-o"></i> Sample Status Report</a></li>
									<?php }
									if (isset($_SESSION['privileges']) && in_array("vlControlReport.php", $_SESSION['privileges'])) { ?>
										<li class="allMenu vlControlReport"><a href="/vl/program-management/vlControlReport.php"><i class="fa fa-circle-o"></i> Control Report</a></li>
									<?php } ?>
									<!--<li><a href="#"><i class="fa fa-circle-o"></i> TOT Report</a></li>
                                <li><a href="#"><i class="fa fa-circle-o"></i> VL Suppression Report</a></li>-->
									<?php if (isset($_SESSION['privileges']) && in_array("vlResult.php", $_SESSION['privileges'])) { ?>
										<li class="allMenu vlResultMenu"><a href="/vl/program-management/vlResult.php"><i class="fa fa-circle-o"></i> Export Results</a></li>
									<?php }
									if (isset($_SESSION['privileges']) && in_array("vlPrintResult.php", $_SESSION['privileges'])) { ?>
										<li class="allMenu vlPrintResultMenu"><a href="/vl/results/vlPrintResult.php"><i class="fa fa-circle-o"></i> Print Result</a></li>
									<?php }
									if (isset($_SESSION['privileges']) && in_array("highViralLoad.php", $_SESSION['privileges'])) { ?>
										<li class="allMenu vlHighMenu"><a href="/vl/program-management/highViralLoad.php"><i class="fa fa-circle-o"></i> Clinic Reports</a></li>
									<?php }
									if (isset($_SESSION['privileges']) && in_array("patientList.php", $_SESSION['privileges'])) { ?>
										<!--<li class="allMenu patientList"><a href="patientList.php"><i class="fa fa-circle-o"></i> Export Patient List</a></li>-->
									<?php }
									if (isset($_SESSION['privileges']) && in_array("vlWeeklyReport.php", $_SESSION['privileges'])) { ?>
										<li class="allMenu vlWeeklyReport"><a href="/vl/program-management/vlWeeklyReport.php"><i class="fa fa-circle-o"></i> VL Lab Weekly Report</a></li>
									<?php }
									if (isset($_SESSION['privileges']) && in_array("sampleRejectionReport.php", $_SESSION['privileges'])) { ?>
										<li class="allMenu sampleRejectionReport"><a href="/vl/program-management/sampleRejectionReport.php"><i class="fa fa-circle-o"></i> Sample Rejection Report</a></li>
									<?php }
									if (isset($_SESSION['privileges']) && in_array("vlMonitoringReport.php", $_SESSION['privileges'])) { ?>
										<li class="allMenu vlMonitoringReport"><a href="/vl/program-management/vlMonitoringReport.php"><i class="fa fa-circle-o"></i> Sample Monitoring Report</a></li>
									<?php }
									if (isset($_SESSION['privileges']) && in_array("vlMonthlyThresholdReport.php", $_SESSION['privileges'])) { ?>
										<li class="allMenu vlMonthlyThresholdReport"><a href="/vl/program-management/vlTestingTargetReport.php"><i class="fa fa-circle-o"></i>VL Testing Target Report</a></li>
									<?php } 
									if (isset($_SESSION['privileges']) && in_array("vlSuppressedTargetReport.php", $_SESSION['privileges'])) { ?>
									<li class="allMenu vlSuppressedMonthlyThresholdReport"><a href="/vl/program-management/vlSuppressedTargetReport.php"><i class="fa fa-circle-o"></i>VL Suppression Target Report</a></li>
									<?php } ?>
								</ul>
							</li>
						<?php
						}
					}

					if (isset($systemConfig['modules']['eid']) && $systemConfig['modules']['eid'] == true && array_intersect($_SESSION['module'], array('eid'))) {  ?>
						<li class="header">EARLY INFANT DIAGNOSIS (EID)</li>
						<?php if ($eidTestRequestMenuAccess == true) { ?>
							<li class="treeview eidRequest" style="<?php echo $hideRequest; ?>">
								<a href="#">
									<i class="fa fa-edit"></i>
									<span>Request Management</span>
									<span class="pull-right-container">
										<i class="fa fa-angle-left pull-right"></i>
									</span>
								</a>
								<ul class="treeview-menu">
									<?php if (isset($_SESSION['privileges']) && in_array("eid-requests.php", $_SESSION['privileges'])) { ?>
										<li class="allMenu eidRequestMenu">
											<a href="/eid/requests/eid-requests.php"><i class="fa fa-circle-o"></i> View Test Requests</a>
										</li>
									<?php }
									if (isset($_SESSION['privileges']) && in_array("eid-add-request.php", $_SESSION['privileges'])) { ?>
										<li class="allMenu addEidRequestMenu">
											<a href="/eid/requests/eid-add-request.php"><i class="fa fa-circle-o"></i> Add New Request</a>
										</li>
									<?php }
									if (isset($_SESSION['privileges']) && in_array("addSamplesFromManifest.php", $_SESSION['privileges']) && ($sarr['user_type'] != 'remoteuser')) { ?>
										<li class="allMenu addSamplesFromManifestEidMenu">
											<a href="/eid/requests/addSamplesFromManifest.php"><i class="fa fa-circle-o"></i> Add Samples from Manifest</a>
										</li>
									<?php }
									if (isset($_SESSION['privileges']) && in_array("eid-batches.php", $_SESSION['privileges']) && ($sarr['user_type'] != 'remoteuser')) { ?>
										<li class="allMenu eidBatchCodeMenu">
											<a href="/eid/batch/eid-batches.php"><i class="fa fa-circle-o"></i> Manage Batch</a>
										</li>
									<?php }
									if (isset($_SESSION['privileges']) && in_array("specimenReferralManifestList.php", $_SESSION['privileges']) && ($sarr['user_type'] == 'remoteuser')) { ?>
										<li class="allMenu specimenReferralManifestListMenu">
											<a href="/specimen-referral-manifest/specimenReferralManifestList.php?t=<?php echo base64_encode('eid'); ?>"><i class="fa fa-circle-o"></i> EID Specimen Manifest</a>
										</li>
									<?php } ?>
								</ul>
							</li>
						<?php }
						if (isset($systemConfig['modules']['eid']) && $systemConfig['modules']['eid'] == true && $eidTestResultMenuAccess == true) { ?>
							<li class="treeview eidResults" style="<?php echo $hideResult; ?>">
								<a href="#">
									<i class="fa fa-tasks"></i>
									<span>Test Result Management</span>
									<span class="pull-right-container">
										<i class="fa fa-angle-left pull-right"></i>
									</span>
								</a>
								<ul class="treeview-menu">
									<?php if (isset($_SESSION['privileges']) && in_array("addImportResult.php", $_SESSION['privileges'])) { ?>
										<li class="allMenu eidImportResultMenu"><a href="/import-result/addImportResult.php?t=<?php echo base64_encode('eid'); ?>"><i class="fa fa-circle-o"></i> Import Result From File</a></li>
									<?php }
									if (isset($_SESSION['privileges']) && in_array("eid-manual-results.php", $_SESSION['privileges'])) { ?>
										<li class="allMenu eidResultsMenu"><a href="/eid/results/eid-manual-results.php"><i class="fa fa-circle-o"></i> Enter Result Manually</a></li>
									<?php }
									if (isset($_SESSION['privileges']) && in_array("eid-result-status.php", $_SESSION['privileges'])) { ?>
										<li class="allMenu eidResultStatus"><a href="/eid/results/eid-result-status.php"><i class="fa fa-circle-o"></i> Manage Results Status</a></li>
									<?php } ?>
								</ul>
							</li>
						<?php }
						if (isset($systemConfig['modules']['eid']) && $systemConfig['modules']['eid'] == true && $eidManagementMenuAccess == true) { ?>
							<li class="treeview eidProgramMenu">
								<a href="#">
									<i class="fa fa-book"></i>
									<span>Management</span>
									<span class="pull-right-container">
										<i class="fa fa-angle-left pull-right"></i>
									</span>
								</a>
								<ul class="treeview-menu">
									<?php if (isset($_SESSION['privileges']) && in_array("eid-sample-status.php", $_SESSION['privileges'])) { ?>
										<li class="allMenu eidSampleStatus"><a href="/eid/management/eid-sample-status.php"><i class="fa fa-circle-o"></i> Sample Status Report</a></li>
									<?php }
									if (isset($_SESSION['privileges']) && in_array("eid-export-data.php", $_SESSION['privileges'])) { ?>
										<li class="allMenu eidExportResult"><a href="/eid/management/eid-export-data.php"><i class="fa fa-circle-o"></i> Export Results</a></li>
									<?php }
									if (isset($_SESSION['privileges']) && in_array("eid-print-results.php", $_SESSION['privileges'])) { ?>
										<li class="allMenu eidPrintResults"><a href="/eid/results/eid-print-results.php"><i class="fa fa-circle-o"></i> Print Result</a></li>
									<?php }
									if (isset($_SESSION['privileges']) && in_array("eid-sample-rejection-report.php", $_SESSION['privileges'])) { ?>
										<li class="allMenu eidSampleRejectionReport"><a href="/eid/management/eid-sample-rejection-report.php"><i class="fa fa-circle-o"></i> Sample Rejection Report</a></li>
									<?php }
									if (isset($_SESSION['privileges']) && in_array("eid-clinic-report.php", $_SESSION['privileges'])) { ?>
										<li class="allMenu eidClinicReport"><a href="/eid/management/eid-clinic-report.php"><i class="fa fa-circle-o"></i> Clinic Report</a></li>
									<?php } 
									if (isset($_SESSION['privileges']) && in_array("eidMonthlyThresholdReport.PHP", $_SESSION['privileges'])) { ?>
										<li class="allMenu eidMonthlyThresholdReport"><a href="/eid/management/eidTestingTargetReport.php"><i class="fa fa-circle-o"></i> EID Testing Target Report</a></li>
									<?php } ?>
								</ul>
							</li>
					<?php }
					} ?>

					<!-- COVID-19 START -->
					<?php if (isset($systemConfig['modules']['covid19']) && $systemConfig['modules']['covid19'] == true && array_intersect($_SESSION['module'], array('covid19'))) {  ?>
						<li class="header">COVID-19</li>
						<?php if ($covid19TestRequestMenuAccess == true) { ?>
							<li class="treeview covid19Request" style="<?php echo $hideRequest; ?>">
								<a href="#">
									<i class="fa fa-edit"></i>
									<span>Request Management</span>
									<span class="pull-right-container">
										<i class="fa fa-angle-left pull-right"></i>
									</span>
								</a>
								<ul class="treeview-menu">
									<?php if (isset($_SESSION['privileges']) && in_array("covid-19-requests.php", $_SESSION['privileges'])) { ?>
										<li class="allMenu covid19RequestMenu">
											<a href="/covid-19/requests/covid-19-requests.php"><i class="fa fa-circle-o"></i> View Test Requests</a>
										</li>
									<?php }
									if (isset($_SESSION['privileges']) && in_array("covid-19-add-request.php", $_SESSION['privileges'])) { ?>
										<li class="allMenu addCovid19RequestMenu">
											<a href="/covid-19/requests/covid-19-add-request.php"><i class="fa fa-circle-o"></i> Add New Request</a>
										</li>
									<?php }
									if (isset($_SESSION['privileges']) && in_array("addSamplesFromManifest.php", $_SESSION['privileges'])) { ?>
										<li class="allMenu addSamplesFromManifestCovid19Menu">
											<a href="/covid-19/requests/addSamplesFromManifest.php"><i class="fa fa-circle-o"></i> Add Samples from Manifest</a>
										</li>
									<?php }
									if (isset($_SESSION['privileges']) && in_array("covid-19-batches.php", $_SESSION['privileges'])) { ?>
										<li class="allMenu covid19BatchCodeMenu">
											<a href="/covid-19/batch/covid-19-batches.php"><i class="fa fa-circle-o"></i> Manage Batch</a>
										</li>
									<?php }
									if (isset($_SESSION['privileges']) && in_array("specimenReferralManifestList.php", $_SESSION['privileges']) && ($sarr['user_type'] == 'remoteuser')) { ?>
										<li class="allMenu specimenReferralManifestListMenu">
											<a href="/specimen-referral-manifest/specimenReferralManifestList.php?t=<?php echo base64_encode('covid19'); ?>"><i class="fa fa-circle-o"></i> Covid-19 Specimen Manifest</a>
										</li>
									<?php } ?>
								</ul>
							</li>
						<?php }
						if (isset($systemConfig['modules']['covid19']) && $systemConfig['modules']['covid19'] == true && $covid19TestResultMenuAccess == true) { ?>
							<li class="treeview covid19Results" style="<?php echo $hideResult; ?>">
								<a href="#">
									<i class="fa fa-tasks"></i>
									<span>Test Result Management</span>
									<span class="pull-right-container">
										<i class="fa fa-angle-left pull-right"></i>
									</span>
								</a>
								<ul class="treeview-menu">
									<?php if (isset($_SESSION['privileges']) && in_array("addImportResult.php", $_SESSION['privileges'])) { ?>
										<li class="allMenu covid19ImportResultMenu"><a href="/import-result/addImportResult.php?t=<?php echo base64_encode('covid19'); ?>"><i class="fa fa-circle-o"></i> Import Result From File</a></li>
									<?php }
									if (isset($_SESSION['privileges']) && in_array("covid-19-manual-results.php", $_SESSION['privileges'])) { ?>
										<li class="allMenu covid19ResultsMenu"><a href="/covid-19/results/covid-19-manual-results.php"><i class="fa fa-circle-o"></i> Enter Result Manually</a></li>
									<?php }
									if ($arr['covid19_positive_confirmatory_tests_required_by_central_lab'] == 'yes' && isset($_SESSION['privileges']) && in_array("covid-19-confirmation-manifest.php", $_SESSION['privileges'])) { ?>
										<li class="allMenu covid19ResultsConfirmationMenu"><a href="/covid-19/results/covid-19-confirmation-manifest.php"><i class="fa fa-circle-o"></i> Confirmation Manifest</a></li>
									<?php }
									if ($arr['covid19_positive_confirmatory_tests_required_by_central_lab'] == 'yes' && isset($_SESSION['privileges']) && in_array("can-record-confirmatory-tests.php", $_SESSION['privileges']) && ($sarr['user_type'] != 'remoteuser')) { ?>
										<li class="allMenu canRecordConfirmatoryTestsCovid19Menu"><a href="/covid-19/results/can-record-confirmatory-tests.php"><i class="fa fa-circle-o"></i> Record Confirmatory Tests</a></li>
									<?php }
									if (isset($_SESSION['privileges']) && in_array("covid-19-result-status.php", $_SESSION['privileges'])) { ?>
										<li class="allMenu covid19ResultStatus"><a href="/covid-19/results/covid-19-result-status.php"><i class="fa fa-circle-o"></i> Manage Results Status</a></li>
									<?php }
									if (isset($_SESSION['privileges']) && in_array("covid-19-print-results.php", $_SESSION['privileges'])) { ?>
										<li class="allMenu covid19ResultMailMenu"><a href="/covid-19/mail/mail-covid-19-results.php"><i class="fa fa-circle-o"></i> E-mail Test Result</a></li>
									<?php }  ?>
								</ul>
							</li>
						<?php }
						if (isset($systemConfig['modules']['covid19']) && $systemConfig['modules']['covid19'] == true && $covid19ManagementMenuAccess == true) { ?>
							<li class="treeview covid19ProgramMenu">
								<a href="#">
									<i class="fa fa-book"></i>
									<span>Management</span>
									<span class="pull-right-container">
										<i class="fa fa-angle-left pull-right"></i>
									</span>
								</a>
								<ul class="treeview-menu">
									<?php if (isset($_SESSION['privileges']) && in_array("covid-19-sample-status.php", $_SESSION['privileges'])) { ?>
										<li class="allMenu covid19SampleStatus"><a href="/covid-19/management/covid-19-sample-status.php"><i class="fa fa-circle-o"></i> Sample Status Report</a></li>
									<?php }
									if (isset($_SESSION['privileges']) && in_array("covid-19-export-data.php", $_SESSION['privileges'])) { ?>
										<li class="allMenu covid19ExportResult"><a href="/covid-19/management/covid-19-export-data.php"><i class="fa fa-circle-o"></i> Export Results</a></li>
									<?php }
									if (isset($_SESSION['privileges']) && in_array("covid-19-print-results.php", $_SESSION['privileges'])) { ?>
										<li class="allMenu covid19PrintResults"><a href="/covid-19/results/covid-19-print-results.php"><i class="fa fa-circle-o"></i> Print Result</a></li>
									<?php }
									if (isset($_SESSION['privileges']) && in_array("covid-19-sample-rejection-report.php", $_SESSION['privileges'])) { ?>
										<li class="allMenu covid19SampleRejectionReport"><a href="/covid-19/management/covid-19-sample-rejection-report.php"><i class="fa fa-circle-o"></i> Sample Rejection Report</a></li>
									<?php }
									if (isset($_SESSION['privileges']) && in_array("covid-19-clinic-report.php", $_SESSION['privileges'])) { ?>
										<li class="allMenu covid19ClinicReportMenu"><a href="/covid-19/management/covid-19-clinic-report.php"><i class="fa fa-circle-o"></i> Clinic Report</a></li>
									<?php } 
									if (isset($_SESSION['privileges']) && in_array("covid19MonthlyThresholdReport.PHP", $_SESSION['privileges'])) { ?>
										<li class="allMenu covid19MonthlyThresholdReport"><a href="/covid-19/management/covid19TestingTargetReport.php"><i class="fa fa-circle-o"></i>COVID-19 Testing Target Report</a></li>
									<?php } ?>
								</ul>
							</li>
					<?php }
					} ?>
					<!-- COVID-19 END -->

					<!-- HEPATITIS START -->
					<?php if (isset($systemConfig['modules']['hepatitis']) && $systemConfig['modules']['hepatitis'] == true && array_intersect($_SESSION['module'], array('hepatitis'))) {  ?>
						<li class="header">Hepatitis</li>
						<?php if ($hepatitisTestRequestMenuAccess == true) { ?>
							<li class="treeview hepatitisRequest" style="<?php echo $hideRequest; ?>">
								<a href="#">
									<i class="fa fa-edit"></i>
									<span>Request Management</span>
									<span class="pull-right-container">
										<i class="fa fa-angle-left pull-right"></i>
									</span>
								</a>
								<ul class="treeview-menu">
									<?php if (isset($_SESSION['privileges']) && in_array("hepatitis-requests.php", $_SESSION['privileges'])) { ?>
										<li class="allMenu hepatitisRequestMenu">
											<a href="/hepatitis/requests/hepatitis-requests.php"><i class="fa fa-circle-o"></i> View Test Requests</a>
										</li>
									<?php }
									if (isset($_SESSION['privileges']) && in_array("hepatitis-add-request.php", $_SESSION['privileges'])) { ?>
										<li class="allMenu addHepatitisRequestMenu">
											<a href="/hepatitis/requests/hepatitis-add-request.php"><i class="fa fa-circle-o"></i> Add New Request</a>
										</li>
									<?php }
									if (isset($_SESSION['privileges']) && in_array("add-samples-from-manifest.php", $_SESSION['privileges'])) { ?>
										<li class="allMenu addSamplesFromManifestHepatitisMenu">
											<a href="/hepatitis/requests/add-samples-from-manifest.php"><i class="fa fa-circle-o"></i> Add Samples from Manifest</a>
										</li>
									<?php }
									if (isset($_SESSION['privileges']) && in_array("hepatitis-batches.php", $_SESSION['privileges'])) { ?>
										<li class="allMenu hepatitisBatchCodeMenu">
											<a href="/hepatitis/batch/hepatitis-batches.php"><i class="fa fa-circle-o"></i> Manage Batch</a>
										</li>
									<?php }
									if (isset($_SESSION['privileges']) && in_array("specimenReferralManifestList.php", $_SESSION['privileges']) && ($sarr['user_type'] == 'remoteuser')) { ?>
										<li class="allMenu specimenReferralManifestListMenu">
											<a href="/specimen-referral-manifest/specimenReferralManifestList.php?t=<?php echo base64_encode('covid19'); ?>"><i class="fa fa-circle-o"></i> Hepatitis Specimen Manifest</a>
										</li>
									<?php } ?>
								</ul>
							</li>
						<?php }
						if (isset($systemConfig['modules']['hepatitis']) && $systemConfig['modules']['hepatitis'] == true && $hepatitisTestResultMenuAccess == true) { ?>
							<li class="treeview hepatitisResults" style="<?php echo $hideResult; ?>">
								<a href="#">
									<i class="fa fa-tasks"></i>
									<span>Test Result Management</span>
									<span class="pull-right-container">
										<i class="fa fa-angle-left pull-right"></i>
									</span>
								</a>
								<ul class="treeview-menu">
									<?php if (isset($_SESSION['privileges']) && in_array("addImportResult.php", $_SESSION['privileges'])) { ?>
										<li class="allMenu hepatitisImportResultMenu"><a href="/import-result/addImportResult.php?t=<?php echo base64_encode('covid19'); ?>"><i class="fa fa-circle-o"></i> Import Result From File</a></li>
									<?php }
									if (isset($_SESSION['privileges']) && in_array("hepatitis-manual-results.php", $_SESSION['privileges'])) { ?>
										<li class="allMenu hepatitisResultsMenu"><a href="/hepatitis/results/hepatitis-manual-results.php"><i class="fa fa-circle-o"></i> Enter Result Manually</a></li>
									<?php }
									if (isset($_SESSION['privileges']) && in_array("hepatitis-result-status.php", $_SESSION['privileges'])) { ?>
										<li class="allMenu hepatitisResultStatus"><a href="/hepatitis/results/hepatitis-result-status.php"><i class="fa fa-circle-o"></i> Manage Results Status</a></li>
									<?php }
									if (isset($_SESSION['privileges']) && in_array("hepatitis-mail-results.php", $_SESSION['privileges'])) { ?>
										<li class="allMenu hepatitisResultMailMenu"><a href="/hepatitis/mail/mail-hepatitis-results.php"><i class="fa fa-circle-o"></i> E-mail Test Result</a></li>
									<?php }  ?>
								</ul>
							</li>
						<?php }
						if (isset($systemConfig['modules']['hepatitis']) && $systemConfig['modules']['hepatitis'] == true && $hepatitisManagementMenuAccess == true) { ?>
							<li class="treeview hepatitisProgramMenu">
								<a href="#">
									<i class="fa fa-book"></i>
									<span>Management</span>
									<span class="pull-right-container">
										<i class="fa fa-angle-left pull-right"></i>
									</span>
								</a>
								<ul class="treeview-menu">
									<?php if (isset($_SESSION['privileges']) && in_array("hepatitis-sample-status.php", $_SESSION['privileges'])) { ?>
										<li class="allMenu hepatitisSampleStatus"><a href="/hepatitis/management/hepatitis-sample-status.php"><i class="fa fa-circle-o"></i> Sample Status Report</a></li>
									<?php }
									if (isset($_SESSION['privileges']) && in_array("hepatitis-export-data.php", $_SESSION['privileges'])) { ?>
										<li class="allMenu hepatitisExportResult"><a href="/hepatitis/management/hepatitis-export-data.php"><i class="fa fa-circle-o"></i> Export Results</a></li>
									<?php }
									if (isset($_SESSION['privileges']) && in_array("hepatitis-print-results.php", $_SESSION['privileges'])) { ?>
										<li class="allMenu hepatitisPrintResults"><a href="/hepatitis/results/hepatitis-print-results.php"><i class="fa fa-circle-o"></i> Print Result</a></li>
									<?php }
									if (isset($_SESSION['privileges']) && in_array("hepatitis-sample-rejection-report.php", $_SESSION['privileges'])) { ?>
										<li class="allMenu hepatitisSampleRejectionReport"><a href="/hepatitis/management/hepatitis-sample-rejection-report.php"><i class="fa fa-circle-o"></i> Sample Rejection Report</a></li>
									<?php }
									if (isset($_SESSION['privileges']) && in_array("hepatitis-clinic-report.php", $_SESSION['privileges'])) { ?>
										<li class="allMenu hepatitisClinicReportMenu"><a href="/hepatitis/management/hepatitis-clinic-report.php"><i class="fa fa-circle-o"></i> Clinic Report</a></li>
									<?php } 
									if (isset($_SESSION['privileges']) && in_array("hepatitisMonthlyThresholdReport.PHP", $_SESSION['privileges'])) { ?>
										<li class="allMenu hepatitisMonthlyThresholdReport"><a href="/hepatitis/management/hepatitisTestingTargetReport.php"><i class="fa fa-circle-o"></i>Hepatitis Testing Target Report</a></li>
									<?php } ?>
								</ul>
							</li>
					<?php }
					} ?>
					<!-- HEPATITIS END -->

					<!---->
				</ul>
			</section>
			<!-- /.sidebar -->
		</aside>
		<!-- content-wrapper -->
		<div id="dDiv" class="dialog">
			<div style="text-align:center"><span onclick="closeModal();" style="float:right;clear:both;" class="closeModal"></span></div>
			<iframe id="dFrame" src="" style="border:none;" scrolling="yes" marginwidth="0" marginheight="0" frameborder="0" vspace="0" hspace="0">some problem</iframe>
		</div>