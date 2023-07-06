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

/** @var AppMenuService $appMenuService */
$appMenuService = ContainerRegistry::get(AppMenuService::class);

$_SESSION['modules'] = $_SESSION['modules'] ?? [];

$arr = $general->getGlobalConfig();
$sarr = $general->getSystemConfig();

$skin = "skin-blue";

$logoName = "<img src='/assets/img/flask.png' style='margin-top:-5px;max-width:22px;'> <span style=''>LIS</span>";
$smallLogoName = "<img src='/assets/img/flask.png'>";
$systemType = _("Lab Sample Management Module");
$shortName = _("Sample Management");
$shortCode = 'LIS';
if (!empty($_SESSION['instanceType']) && $_SESSION['instanceType'] == 'remoteuser') {
	$skin = "skin-red";
	$systemType = _("Remote Sample Tracking Module");
	$logoName = "<span class='fa fa-medkit'></span> STS";
	$smallLogoName = "<span class='fa fa-medkit'></span>";
	$shortName = _("Sample Tracking");
	$shortCode = 'STS';
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

$_SESSION['menuItems'] = $_SESSION['menuItems'] ?? $appMenuService->getMenu();

?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['APP_LOCALE'] ?? 'en_US'; ?>">

<head>
	<meta charset="utf-8" />
	<meta http-equiv="X-UA-Compatible" content="IE=edge" />
	<title>
		<?= $shortName . " | " . ($title ?? $shortCode); ?>
	</title>
	<meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
	<meta name="viewport" content="width=1024">

	<?php
	$iconType = !empty($_SESSION['instanceType']) && $_SESSION['instanceType'] == 'remoteuser' ? 'vlsts' : 'vlsm';
	?>

	<link rel="apple-touch-icon" sizes="180x180" href="/assets/<?= $iconType; ?>-icons/apple-touch-icon.png">
	<link rel="icon" type="image/png" sizes="32x32" href="/assets/<?= $iconType; ?>-icons/favicon-32x32.png">
	<link rel="icon" type="image/png" sizes="16x16" href="/assets/<?= $iconType; ?>-icons/favicon-16x16.png">
	<link rel="manifest" href="/assets/<?= $iconType; ?>-icons/site.webmanifest">

	<link rel="stylesheet" media="all" type="text/css" href="/assets/css/fonts.css" />
	<link rel="stylesheet" media="all" type="text/css" href="/assets/css/jquery-ui.min.css" />
	<link rel="stylesheet" media="all" type="text/css" href="/assets/css/jquery-ui-timepicker-addon.css" />
	<link rel="stylesheet" media="all" type="text/css" href="/assets/css/bootstrap.min.css">
	<link rel="stylesheet" media="all" type="text/css" href="/assets/css/font-awesome.min.css">
	<link rel="stylesheet" media="all" type="text/css" href="/assets/plugins/datatables/dataTables.bootstrap.css">
	<link rel="stylesheet" media="all" type="text/css" href="/assets/css/AdminLTE.min.css">
	<link rel="stylesheet" media="all" type="text/css" href="/assets/css/skins/_all-skins.min.css">
	<link rel="stylesheet" media="all" type="text/css" href="/assets/plugins/daterangepicker/daterangepicker.css" />
	<link rel="stylesheet" media="all" type="text/css" href="/assets/css/select2.min.css" />
	<link rel="stylesheet" media="all" type="text/css" href="/assets/css/deforayModal.css" />
	<link rel="stylesheet" media="all" type="text/css" href="/assets/css/jquery.fastconfirm.css" />
	<link rel="stylesheet" media="all" type="text/css" href="/assets/css/components-rounded.min.css">
	<link rel="stylesheet" media="all" type="text/css" href="/assets/css/select2.live.min.css" />
	<link rel="stylesheet" media="all" type="text/css"
		href="/assets/css/style.css?v=<?= filemtime(WEB_ROOT . "/assets/css/style.css") ?>" />
	<script type="text/javascript" src="/assets/js/jquery.min.js"></script>
	<script type="text/javascript" src="/assets/js/jquery-ui.min.js"></script>
	<script type="text/javascript" src="/assets/js/deforayModal.js"></script>
	<script type="text/javascript" src="/assets/js/jquery.fastconfirm.js"></script>
	<style>
		.select2-selection--multiple {
			max-height: 100px;
			width: auto;
			overflow-y: scroll !important;
		}
	</style>
</head>

<body class="hold-transition <?= $skin; ?> sidebar-mini" id="lis-body" style="margin-top:50px !important;">
	<div class="wrapper">
		<header class="main-header">
			<a href="<?= $_SESSION['landingPage']; ?>" class="logo" style="position: fixed;top: 0;">
				<span class="logo-mini"><strong>
						<?= $smallLogoName; ?>
					</strong></span>
				<span class="logo-lg" style="font-weight:bold;">
					<?= $logoName; ?>
				</span>
			</a>
			<nav class="navbar navbar-fixed-top">
				<!-- Sidebar toggle button-->
				<a href="#" class="sidebar-toggle" data-toggle="offcanvas" role="button">
					<span class="sr-only">Toggle navigation</span>
				</a>
				<ul class="nav navbar-nav">
					<li>
						<a href="javascript:void(0);return false;">
							<span style="text-transform: uppercase;font-weight:600;">
								<?= $systemType; ?>
							</span>
						</a>
					</li>
				</ul>
				<div class="navbar-custom-menu">
					<ul class="nav navbar-nav">
						<?php if (!empty(SYSTEM_CONFIG['recency']['crosslogin']) && SYSTEM_CONFIG['recency']['crosslogin'] === true && !empty(SYSTEM_CONFIG['recency']['url'])) {
							?>
							<li class="user-menu">
								<a onclick="setCrossLogin();"
									href="<?= rtrim(SYSTEM_CONFIG['recency']['url'], "/") . '/login?u=' . base64_encode($_SESSION['loginId']) . '&t=' . ($_SESSION['crossLoginPass']) . '&name=' . base64_encode($_SESSION['userName']); ?>"
									class="btn btn-link"><span class="fa-solid fa-arrow-up-right-from-square"></span>
									Recency</a>
							</li>
						<?php } ?>

						<li class="dropdown user user-menu">
							<a href="#" class="dropdown-toggle" data-toggle="dropdown">
								<span class="fa-solid fa-hospital-user"></span>
								<span class="hidden-xs">
									<?= $_SESSION['userName'] ?? ''; ?>
								</span>
								<span class="fa-solid fa-circle is-remote-server-reachable"
									style="font-size:1em;display:none;"></span>
							</a>
							<ul class="dropdown-menu">
								<?php
								if (!empty($arr['edit_profile']) && $arr['edit_profile'] == 'yes') {
									?>
									<li class="user-footer">
										<a href="/users/editProfile.php" class="">
											<?= _("Edit Profile"); ?>
										</a>
									</li>
								<?php } ?>
								<li class="user-footer">
									<a href="/login/logout.php">
										<?= _("Sign out"); ?>
									</a>
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
							<img src="/uploads/logo/<?= $arr['logo']; ?>" alt="Logo Image" style="max-width:120px;">
						</div>
					</div>
				<?php } ?>
				<ul class="sidebar-menu">
					<?php
					foreach ($_SESSION['menuItems'] as $menu) {
						if ($menu['has_children'] == 'yes' && empty($menu['children'])) {
							// Supposed to have children but does not have?
							// Continue to next menu. We dont need this one
							continue;
						}
						$classNames = $menu['additional_class_names'] . ($menu['has_children'] == "yes" ? ' treeview manage' : '');

						if ($menu['is_header'] == 'yes') {
							echo '<li class="header">' . $menu['display_text'];
						} else {
							?>

							<li class="<?= $classNames; ?>">
								<a href="<?= $menu['link'] ?>">
									<span class="<?= $menu['icon'] ?>"></span> <span>
										<?= _($menu['display_text']); ?>
									</span>

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
										<?php if ($subMenu['has_children'] == 'yes' && !empty($subMenu['children'])) { ?>
											<li class="sub-menu-li <?= $subMenu['additional_class_names'] ?> ">
												<a href="<?= $subMenu['link']; ?>">
													<span class="<?= $subMenu['icon'] ?>"></span>
													<span>
														<?= _($subMenu['display_text']); ?>
													</span>
													<span class="pull-right-container">
														<span class="fa-solid fa-angle-left pull-right"></span>
													</span>
												</a>
												<?php if (!empty($subMenu['children'])) { ?>
													<ul class="sub-menu-li-ul treeview-menu">
														<?php
														foreach ($subMenu['children'] as $childMenu) {
															$innerPages = '';
															if (!empty($childMenu['inner_pages'])) {
																$dataInnerPages = explode(',', $childMenu['inner_pages']);
																$dataInnerPages = implode(';', array_map('base64_encode', $dataInnerPages));
																$innerPages = 'data-inner-pages="' . $dataInnerPages . '"';
															}
															?>
															<li class="sub-menu-li-ul-li <?= $childMenu['additional_class_names'] ?>">
																<a class="menu-item" href="<?= $childMenu['link'] ?>" <?= $innerPages; ?>>
																	<span class="<?= $childMenu['icon'] ?>"></span>
																	<span class="inner-menu-item-text">
																		<?= _($childMenu['display_text']); ?>
																	</span>
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
								</ul>
							<?php } ?>
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
			<div style="text-align:center">
				<span onclick="closeModal();" style="float:right;clear:both;" class="closeModal"></span>
			</div>
			<iframe id="dFrame" src="" title="LIS Content" style="border:none;" scrolling="yes" marginwidth="0"
				marginheight="0" frameborder="0" vspace="0" hspace="0">
				<?= _("Unable to load this page or resource"); ?>
			</iframe>
		</div>
