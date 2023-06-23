<?php

use App\Services\UsersService;
use App\Services\CommonService;
use App\Exceptions\SystemException;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Services\GenericTestsService;
use App\Services\UiService;



$applicationConfig = ContainerRegistry::get('applicationConfig');

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

/** @var GenericTestsService $genericTestsService */
$genericTestsService = ContainerRegistry::get(GenericTestsService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

/** @var UiService $UiService */
$uiService = ContainerRegistry::get(UiService::class);

$_SESSION['module'] = $_SESSION['module'] ?? [];

$syncLatestTime = $general->getLastSyncDateTime();

$arr = $general->getGlobalConfig();
$sarr = $general->getSystemConfig();

$skin = "skin-blue";

$logoName = "<img src='/assets/img/flask.png' style='margin-top:-5px;max-width:22px;'> <span style=''>LIS</span>";
$smallLogoName = "<img src='/assets/img/flask.png'>";
$systemType = _("Lab Sample Management Module");
$shortName = _("Sample Management");
if (!empty($_SESSION['instanceType']) && $_SESSION['instanceType'] == 'remoteuser') {
	$skin = "skin-red";
	$systemType = _("Remote Sample Tracking Module");
	$logoName = "<span class='fa fa-medkit'></span> STS";
	$smallLogoName = "<span class='fa fa-medkit'></span>";
	$shortName = _("Sample Tracking");
}

if (!empty($applicationConfig['instanceName'])) {
	$systemType = $applicationConfig['instanceName'];
}

/** @var Laminas\Diactoros\ServerRequest $request */
$request = $GLOBALS['request'];

if (!$usersService->isAllowed($request)) {
	http_response_code(401);
	throw new SystemException(_('Unauthorized access. You do not have permission to access this page.'), 401);
}

if (isset($_SESSION['privileges']) && array_intersect($_SESSION['privileges'], array('roles.php', 'users.php', 'facilities.php', 'globalConfig.php', 'importConfig.php', 'otherConfig.php'))) {
	$allAdminMenuAccess = true;
} else {
	$allAdminMenuAccess = false;
}
if (isset($_SESSION['privileges']) && array_intersect($_SESSION['privileges'], array('vlRequest.php', 'addVlRequest.php', 'addSamplesFromManifest.php', '/batch/batches.php?type=vl', 'specimenReferralManifestList.php'))) {
	$vlRequestMenuAccess = true;
} else {
	$vlRequestMenuAccess = false;
}
if (isset($_SESSION['privileges']) && array_intersect($_SESSION['privileges'], array('addImportResult.php', 'vlTestResult.php', 'vlResultApproval.php', 'vlResultMail.php'))) {
	$vlTestResultMenuAccess = true;
} else {
	$vlTestResultMenuAccess = false;
}
if (isset($_SESSION['privileges']) && array_intersect($_SESSION['privileges'], array('vl-sample-status.php', 'vl-export-data.php', 'highViralLoad.php', 'vlControlReport.php', 'vlWeeklyReport.php', 'sampleRejectionReport.php', 'vlMonitoringReport.php', 'vlPrintResult.php'))) {
	$vlManagementMenuAccess = true;
} else {
	$vlManagementMenuAccess = false;
}

// EID MENUS
if (isset($_SESSION['privileges']) && array_intersect($_SESSION['privileges'], array('eid-requests.php', 'eid-add-request.php', 'addSamplesFromManifest.php', '/batch/batches.php?type=eid', 'specimenReferralManifestList.php'))) {
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
if (isset($_SESSION['privileges']) && array_intersect($_SESSION['privileges'], array("covid-19-requests.php", "covid-19-add-request.php", "covid-19-edit-request.php", "addSamplesFromManifest.php", "/batch/batches.php?type=covid19", "specimenReferralManifestList.php"))) {
	$covid19TestRequestMenuAccess = true;
} else {
	$covid19TestRequestMenuAccess = false;
}

if (isset($_SESSION['privileges']) && array_intersect($_SESSION['privileges'], array('addImportResult.php', 'covid-19-manual-results.php', 'covid-19-confirmation-manifest.php', 'can-record-confirmatory-tests.php', 'covid-19-result-status.php'))) {
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

if (isset($_SESSION['privileges']) && array_intersect($_SESSION['privileges'], array("addImportResult.php", "hepatitis-manual-results.php", "hepatitis-result-status.php"))) {
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

// TB Menu start
if (isset($_SESSION['privileges']) && array_intersect($_SESSION['privileges'], array("tb-requests.php", "tb-add-request.php", "tb-edit-request.php", "add-Samples-from-manifest.php"))) {
	$tbTestRequestMenuAccess = true;
} else {
	$tbTestRequestMenuAccess = false;
}

if (isset($_SESSION['privileges']) && array_intersect($_SESSION['privileges'], array("tb-manual-results.php", "tb-result-status.php"))) {
	$tbTestResultMenuAccess = true;
} else {
	$tbTestResultMenuAccess = false;
}

if (isset($_SESSION['privileges']) && array_intersect($_SESSION['privileges'], array("tb-sample-status.php", "tb-export-data.php", "tb-print-results.php", "tb-sample-rejection-report.php", "tb-clinic-report.php"))) {
	$tbManagementMenuAccess = true;
} else {
	$tbManagementMenuAccess = false;
}
// TB Menu end


if (isset($_SESSION['privileges']) && array_intersect($_SESSION['privileges'], array("view-requests.php", "add-request.php", "add-samples-from-manifest.php", "batch-code.php", "specimenReferralManifestList.php"))) {
	$genericTestRequestMenuAccess = true;
} else {
	$genericTestRequestMenuAccess = false;
}
if (isset($_SESSION['privileges']) && array_intersect($_SESSION['privileges'], array("generic-test-results.php", "update-generic-test-result.php", "generic-failed-results.php", "generic-result-approval.php"))) {
	$genericTestResultMenuAccess = true;
} else {
	$genericTestResultMenuAccess = false;
}
if (isset($_SESSION['privileges']) && array_intersect($_SESSION['privileges'], array("generic-sample-status.php", "generic-export-data.php", "generic-print-result.php", "generic-weekly-report.php", "sample-rejection-report.php", "generic-monitoring-report.php", "generic-monthly-threshold-report.php"))) {
	$genericManagementMenuAccess = true;
} else {
	$genericManagementMenuAccess = false;
}

$menuItems = $uiService->getAllActiveMenus();
//echo '<pre>'; print_r($menuItems); die;
?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['APP_LOCALE'] ?? 'en_US'; ?>">

<head>
	<meta charset="utf-8" />
	<meta http-equiv="X-UA-Compatible" content="IE=edge" />
	<title><?php echo $shortName . " | " . ((isset($title) && $title != null && $title != "") ? $title : "VLSM"); ?></title>
	<!-- Tell the browser to be responsive to screen width -->
	<meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
	<meta name="viewport" content="width=1024">

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


	<link rel="stylesheet" media="all" type="text/css" href="/assets/css/fonts.css" />

	<link rel="stylesheet" media="all" type="text/css" href="/assets/css/jquery-ui.min.css" />
	<link rel="stylesheet" media="all" type="text/css" href="/assets/css/jquery-ui-timepicker-addon.css" />

	<!-- Bootstrap 3.3.6 -->
	<link rel="stylesheet" href="/assets/css/bootstrap.min.css">
	<!-- Font Awesome -->
	<link rel="stylesheet" href="/assets/css/font-awesome.min.css">

	<!-- Ionicons -->

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

	<link rel="stylesheet" href="/assets/css/components-rounded.min.css">

	<!-- jQuery 2.2.3 -->

	<script type="text/javascript" src="/assets/js/jquery.min.js"></script>

	<!-- Latest compiled and minified JavaScript -->

	<script type="text/javascript" src="/assets/js/jquery-ui.min.js"></script>
	<script src="/assets/js/deforayModal.js"></script>
	<script src="/assets/js/jquery.fastconfirm.js"></script>
	<link href="/assets/css/select2.live.min.css" rel="stylesheet" />
	<!--<script type="text/javascript" src="/assets/js/jquery-ui-sliderAccess.js"></script>-->
	<style>
		.select2-selection--multiple {
			max-height: 100px;
			width: auto;
			overflow-y: scroll !important;
		}
	</style>
</head>

<body class="hold-transition <?php echo $skin; ?> sidebar-mini" id="capture">
	<div class="wrapper">
		<header class="main-header">
			<!-- Logo -->
			<a href="<?php echo ($dashBoardMenuAccess === true) ? '/dashboard/index.php' : '#'; ?>" class="logo">
				<!-- mini logo for sidebar mini 50x50 pixels -->
				<span class="logo-mini"><strong><?php echo $smallLogoName; ?></strong></span>
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
						<?php if (!empty(SYSTEM_CONFIG['recency']['crosslogin']) && SYSTEM_CONFIG['recency']['crosslogin'] === true && !empty(SYSTEM_CONFIG['recency']['url'])) {
						?>
							<li class="user-menu">
								<a onclick="setCrossLogin();" href="<?php echo rtrim(SYSTEM_CONFIG['recency']['url'], "/") . '/login?u=' . base64_encode($_SESSION['loginId']) . '&t=' . ($_SESSION['crossLoginPass']) . '&name=' . base64_encode($_SESSION['userName']); ?>" class="btn btn-link"><span class="fa-solid fa-arrow-up-right-from-square"></span> Recency</a>
							</li>
						<?php } ?>

						<li class="dropdown user user-menu">
							<a href="#" class="dropdown-toggle" data-toggle="dropdown">

								<span class="fa-solid fa-hospital-user"></span>
								<span class="hidden-xs"><?php if (isset($_SESSION['userName'])) {
															echo $_SESSION['userName'];
														} ?></span>
								<?php if (!empty(SYSTEM_CONFIG['remoteURL']) && isset($_SESSION['userName']) && isset($_SESSION['instanceType']) && ($_SESSION['instanceType'] == 'vluser')) { ?>
									<span class="fa-solid fa-circle is-remote-server-reachable" style="font-size:1em;display:none;"></span>
								<?php } ?>
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
									<a href="/users/editProfile.php" class=""><?php echo _("Edit Profile"); ?></a>
								</li>
								<li class="user-footer <?php echo $alignRight; ?>">
									<a href="/login/logout.php"><?php echo _("Sign out"); ?></a>
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
					<?php
					foreach ($menuItems as $menu) {
						$classNames = $menu['additional_class_names'] . ($menu['has_children'] == "yes" ? ' treeview manage' : '');
						if ($menu['is_header'] == 'yes') {
							echo '<li class="header">' . $menu['display_text'];
						} else {

					?>
							<li class="<?php echo $classNames; ?>">
								<a href="<?php echo $menu['link'] ?>">
									<span class="<?php echo $menu['icon'] ?>"></span> <span><?php echo _($menu['display_text']); ?></span>

									<?php if ($menu['has_children'] == 'yes') { ?>
										<span class="pull-right-container">
											<span class="fa-solid fa-angle-left pull-right"></span>
										</span>
									<?php } ?>
								</a><?php } ?>
							<?php if ($menu['has_children'] == "yes") {
								if ($menu['is_header'] == 'no') { ?>
									<ul class="treeview-menu">
									<?php
								}
								$subMenuItems = $uiService->getAllActiveMenus(0, $menu['id']);
								foreach ($subMenuItems as $subMenu) {
									?>
										<li class="<?php echo $subMenu['additional_class_names'] ?>">
											<a href="<?php echo $subMenu['link']; ?>">
												<span class="<?php echo $subMenu['icon'] ?>"></span>
												<span><?php echo _($subMenu['display_text']); ?></span>
												<?php if ($subMenu['has_children'] == 'yes') { ?>
													<span class="pull-right-container">
														<span class="fa-solid fa-angle-left pull-right"></span>
													</span>
												<?php } ?>
											</a>
											<?php if ($subMenu['has_children'] == "yes") { ?>
												<ul class="treeview-menu">
													<?php
													$childMenuItems = $uiService->getAllActiveMenus(0, $subMenu['id']);
													foreach ($childMenuItems as $childMenu) {
														$dataInnerPages = explode(',', $childMenu['inner_pages']);
														$innerPages = implode(';', array_map('base64_encode', $dataInnerPages));

														if (!empty($childMenu['link'])) {
															$privilegeArr = explode('/', $childMenu['link']);
															$privilege = end($privilegeArr);
														}
														if ($usersService->isAllowed($privilege)) {
													?>
															<li class="<?php echo $childMenu['additional_class_names'] ?>">
																<a href="<?php echo $childMenu['link'] ?>" data-inner-pages="<?php echo $innerPages; ?>">
																	<span class="<?php echo $childMenu['icon'] ?>"></span> <?php echo _($childMenu['display_text']); ?>
																</a>
															</li>
													<?php }
													} ?>
												</ul>
											<?php } ?>
										</li>
									<?php  } ?>
									<?php if ($menu['is_header'] == 'no') { ?>
									</ul><?php } ?>
							<?php } ?>

							</li>

						<?php }   ?>
				</ul>

			</section>
			<!-- /.sidebar -->
		</aside>
		<!-- content-wrapper -->
		<div id="dDiv" class="dialog">
			<div style="text-align:center"><span onclick="closeModal();" style="float:right;clear:both;" class="closeModal"></span></div>
			<iframe id="dFrame" src="" title="LIS Content" style="border:none;" scrolling="yes" marginwidth="0" marginheight="0" frameborder="0" vspace="0" hspace="0">
				<?= _("Unable to load this page or resource"); ?>
			</iframe>
		</div>
