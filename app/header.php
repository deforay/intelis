<?php

use App\Services\UsersService;
use App\Services\CommonService;
use App\Exceptions\SystemException;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Services\GenericTestsService;
use App\Services\AppMenuService;



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

/** @var AppMenuService $UiService */
$uiService = ContainerRegistry::get(AppMenuService::class);

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

$_SESSION['menuItems'] = $_SESSION['menuItems'] ?? $uiService->getAllActiveMenus();

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
			<section class="sidebar">
				<?php if (isset($arr['logo']) && trim($arr['logo']) != "" && file_exists('uploads' . DIRECTORY_SEPARATOR . "logo" . DIRECTORY_SEPARATOR . $arr['logo'])) { ?>
					<div class="user-panel">
						<div>
							<img src="/uploads/logo/<?php echo $arr['logo']; ?>" alt="Logo Image" style="max-width:120px;">
						</div>
					</div>
				<?php } ?>
				<ul class="sidebar-menu">
					<?php
					foreach ($_SESSION['menuItems'] as $menu) {
						if ($menu['has_children'] == 'yes' && (empty($menu['children']) || count($menu['children']) == 0)) {
							continue;
						}
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
								</a>
							<?php } ?>
							<?php if ($menu['has_children'] == "yes") {
								if ($menu['is_header'] == 'no') { ?>
									<ul class="treeview-menu">
									<?php
								}

								foreach ($menu['children'] as $subMenu) {
									?>
										<?php if ($subMenu['has_children'] == 'yes' && !empty($subMenu['children']) && count($subMenu['children']) != 0) { ?>
											<li class="<?= $subMenu['additional_class_names'] ?> ">
												<a href="<?php echo $subMenu['link']; ?>">
													<span class="<?php echo $subMenu['icon'] ?>"></span>
													<span><?php echo _($subMenu['display_text']); ?></span>
													<span class="pull-right-container">
														<span class="fa-solid fa-angle-left pull-right"></span>
													</span>
												</a>
												<?php if (!empty($subMenu['children']) && count($subMenu['children']) != 0) { ?>
													<ul class="treeview-menu">
														<?php
														foreach ($subMenu['children'] as $childMenu) {
															$dataInnerPages = explode(',', $childMenu['inner_pages']);
															$innerPages = implode(';', array_map('base64_encode', $dataInnerPages));

															if (!empty($childMenu['link'])) {
																$privilegeArr = explode('/', $childMenu['link']);
																$privilege = end($privilegeArr);
															}

														?>
															<li class="<?php echo $childMenu['additional_class_names'] ?>">
																<a href="<?php echo $childMenu['link'] ?>" data-inner-pages="<?php echo $innerPages; ?>">
																	<span class="<?php echo $childMenu['icon'] ?>"></span> <?php echo _($childMenu['display_text']); ?>
																</a>
															</li>
														<?php
														}
														?>
													</ul>
												<?php } ?>
											</li>
									<?php
										}
									} ?>
									<?php if ($menu['is_header'] == 'no') { ?>
									</ul><?php } ?>
							<?php } ?>

							</li>

						<?php }

						?>
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
