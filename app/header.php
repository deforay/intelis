<?php

use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\AppMenuService;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;

// Reset query counters on page reload
unset($_SESSION['queryCounters']);

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

if ($db->isConnected() === false) {
	throw new SystemException("Database connection failed. Please check your database settings", 500);
}

$_SESSION['modules'] ??= [];
$arr = $general->getGlobalConfig();
$sarr = $general->getSystemConfig();

$skin = "skin-blue";

$trainingMode = isset($arr['training_mode']) && trim((string) $arr['training_mode']) == 'yes';

$logoName = "<img src='/assets/img/flask.png' style='margin-top:-5px;max-width:22px;'> <span style=''>LIS</span>";
$smallLogoName = "<img src='/assets/img/flask.png'>";
$systemDisplayName = _translate("Lab Sample Management Module");
$shortName = _translate("Sample Management");
$shortCode = 'LIS';
if ($general->isSTSInstance()) {
	$skin = "skin-red";
	$systemDisplayName = _translate("Sample Tracking System");
	$logoName = "<span class='fa fa-medkit'></span> STS";
	$smallLogoName = "<span class='fa fa-medkit'></span>";
	$shortName = _translate("Sample Tracking");
	$shortCode = 'STS';
}

$systemDisplayName = SYSTEM_CONFIG['instance-name'] ?? $systemDisplayName;

/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');

$countryCode = $arr['default_phone_prefix'] ?? '';
$minNumberOfDigits = _castVariable($arr['min_phone_length'] ?? null, 'int') ?? 15;
$maxNumberOfDigits = _castVariable($arr['max_phone_length'] ?? null, 'int') ?? 15;

$_SESSION['menuItems'] ??= (ContainerRegistry::get(AppMenuService::class))->getMenu();

$instrumentsCount = $general->getInstrumentsCount();
$nonAdminUserCount = $general->getNonAdminUsersCount();

$displayTopBar = ($instrumentsCount == 0 || $nonAdminUserCount == 0);

$margin = $displayTopBar ? 'style="margin-top:50px !important;"' : '';
$topSide = $displayTopBar ? 'style="top:50px !important;"' : 'style="top:0 !important;"';


$locale = $_SESSION['APP_LOCALE'] ?? 'en_US';
$langCode = explode('_', $locale)[0]; // Gets 'en' from 'en_US'
?>
<!DOCTYPE html>
<html lang="<?= $langCode; ?>" translate="no">

<head>
	<meta charset="utf-8" />
	<meta http-equiv="X-UA-Compatible" content="IE=edge" />
	<title>
		<?= ($title ?? $shortCode) . " | " . $shortName; ?>
	</title>
	<meta name="google" content="notranslate">
	<meta name="google-translate-customization" content="0">
	<meta http-equiv="Content-Language" content="<?= $langCode; ?>">
	<meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
	<meta name="viewport" content="width=1024">

	<?php
	$iconType = $general->isSTSInstance() ? 'vlsts' : 'vlsm';
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
	<link rel="stylesheet" media="all" type="text/css" href="/assets/css/style.css?v=<?= filemtime(WEB_ROOT . "/assets/css/style.css") ?>" />
	<link rel="stylesheet" type="text/css" href="/assets/css/toastify.min.css">
	<link rel="stylesheet" type="text/css" href="/assets/css/summernote.min.css">
	<link rel="stylesheet" media="all" type="text/css" href="/assets/css/selectize.css" />

	<script type="text/javascript" src="/assets/js/jquery.min.js"></script>
	<script type="text/javascript" src="/assets/js/jquery-ui.min.js"></script>
	<script type="text/javascript" src="/assets/js/deforayModal.js?v=<?= filemtime(WEB_ROOT . "/assets/js/deforayModal.js") ?>"></script>
	<script type="text/javascript" src="/assets/js/jquery.fastconfirm.js"></script>
</head>
<style>
	.topBar {
		margin: 0;
		padding: 0;
		border: 0;
		outline: 0;
		background: none no-repeat scroll 0 transparent;
		font-family: arial, helvetica, sans-serif;
		font-size: 100%;
		font-style: inherit;
		font-weight: inherit;
		letter-spacing: normal;
		line-height: 10px;
		display: inline-block !important;
		left: 0;
		width: 100%;
		margin-top: 0;
		padding-top: 0;
		clear: both;
		background-color: #f16e00;
		text-align: left;
		overflow: hidden;
		vertical-align: bottom;
		position: fixed;
		top: 0;
		z-index: 1031;
	}

	.content-header {
		margin-top: 50px;
	}
</style>

<body class="hold-transition <?= $skin; ?> sidebar-mini" id="lis-body" <?= $margin; ?> translate="no" class="notranslate">

	<?php if (
		($general->isLisInstance() && $instrumentsCount == 0) ||
		$nonAdminUserCount == 0
	) { ?>
		<div class="topBar">
			<p class="white text-center">
				<?php if ($nonAdminUserCount == 0) { ?>
					<a href="/users/addUser.php" style="font-weight:bold; color: black;"><?= _translate("Please click here to add one or more non-admin users before you can start using the system"); ?> </a>
				<?php } ?>
				<?php if ($general->isLisInstance() && $instrumentsCount == 0) { ?>
					&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <a href="/instruments/add-instrument.php" style="font-weight:bold; color: black;"><?= _translate("Please click here to add one or more instruments before you can start using the LIS"); ?> </a>
				<?php }
				?>
			</p>
		</div>
	<?php } ?>
	<div class="wrapper">

		<header class="main-header">

			<a href="<?= $_SESSION['landingPage']; ?>" class="logo" style="position:fixed;top:10;">
				<span class="logo-mini"><strong>
						<?= $smallLogoName; ?>
					</strong></span>
				<span class="logo-lg" style="font-weight:bold;">
					<?= $logoName; ?>
				</span>
			</a>

			<nav class="navbar" style="position:fixed;top:10;left:0;right:0;">
				<!-- Sidebar toggle button-->
				<button class="sidebar-toggle" data-toggle="offcanvas" onKeyDown="if(event.key === 'Enter' || event.key === ' ') { this.click(); }">
					<span class="sr-only">Toggle navigation</span>
				</button>

				<ul class="nav navbar-nav">
					<li>
						<a href="javascript:void(0);return false;">
							<span style="text-transform: uppercase;font-weight:600;">
								<?= $systemDisplayName; ?>
							</span>
						</a>
					</li>
					<li>
						<?php if ($trainingMode) { ?>
							<marquee class="trainingMarquee" behavior="scroll" scrollamount="5">
								<?= $arr['training_mode_text']; ?>
							</marquee>
						<?php } ?>
					</li>
				</ul>
				<div class="navbar-custom-menu">
					<ul class="nav navbar-nav">
						<?php if (!empty(SYSTEM_CONFIG['recency']['crosslogin']) && SYSTEM_CONFIG['recency']['crosslogin'] === true && !empty(SYSTEM_CONFIG['recency']['url'])) {
						?>
							<li class="user-menu">
								<a onclick="setCrossLogin();" href="<?= rtrim((string) SYSTEM_CONFIG['recency']['url'], "/") . '/login?u=' . base64_encode((string) $_SESSION['loginId']) . '&t=' . ($_SESSION['crossLoginPass']) . '&name=' . base64_encode((string) $_SESSION['userName']); ?>" class="btn btn-link"><span class="fa-solid fa-arrow-up-right-from-square"></span>
									<?= _translate('Recency'); ?>
								</a>
							</li>
						<?php } ?>

						<li class="dropdown user user-menu">
							<a href="#" class="dropdown-toggle" data-toggle="dropdown">
								<span class="fa-solid fa-hospital-user"></span>
								<span class="hidden-xs">
									<?= $_SESSION['userName'] ?? ''; ?>
								</span>
								<span class="fa-solid fa-circle is-remote-server-reachable" style="font-size:1em;display:none;"></span>
							</a>
							<ul class="dropdown-menu">
								<?php
								if (!empty($arr['edit_profile']) && $arr['edit_profile'] == 'yes') {
								?>
									<li class="user-footer">
										<a href="/users/edit-profile.php" class="">
											<?= _translate("Edit Profile"); ?>
										</a>
									</li>
								<?php } ?>
								<li class="user-footer">
									<a href="/login/logout.php">
										<?= _translate("Sign out"); ?>
									</a>
								</li>
							</ul>
						</li>
					</ul>
				</div>
			</nav>

		</header>

		<!-- Left side column. contains the logo and sidebar -->
		<aside class="main-sidebar" <?= $topSide; ?>>
			<section class="sidebar">
				<?php if (isset($arr['logo']) && trim((string) $arr['logo']) != "" && file_exists('uploads' . DIRECTORY_SEPARATOR . "logo" . DIRECTORY_SEPARATOR . $arr['logo'])) { ?>
					<div class="user-panel">
						<div>
							<img src="/uploads/logo/<?= $arr['logo']; ?>" alt="Logo" style="max-width:120px;">
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
										<?= _translate($menu['display_text']); ?>
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
									$subMenuHasChildren = false;
									if ($subMenu['has_children'] == 'yes' && !empty($subMenu['children'])) {
										$subMenuHasChildren = true;
									}
									$innerPages = '';
									if (!empty($subMenu['inner_pages'])) {
										$dataInnerPages = explode(',', (string) $subMenu['inner_pages']);
										$dataInnerPages = implode(';', array_map('base64_encode', $dataInnerPages));
										$innerPages = "data-inner-pages='$dataInnerPages'";
									}
									?>

										<li class="sub-menu-li <?= $subMenu['additional_class_names'] ?> ">
											<a href="<?= $subMenu['link']; ?>" <?= $innerPages; ?>>
												<span class="<?= $subMenu['icon'] ?>"></span>
												<span>
													<?= _translate($subMenu['display_text']); ?>
												</span>
												<?php if ($subMenuHasChildren) { ?>
													<span class="pull-right-container">
														<span class="fa-solid fa-angle-left pull-right"></span>
													</span>
												<?php } ?>
											</a>
											<?php if ($subMenuHasChildren) { ?>
												<ul class="sub-menu-li-ul treeview-menu">
													<?php
													foreach ($subMenu['children'] as $childMenu) {
														$innerPages = '';
														if (!empty($childMenu['inner_pages'])) {
															$dataInnerPages = explode(',', (string) $childMenu['inner_pages']);
															$dataInnerPages = implode(';', array_map('base64_encode', $dataInnerPages));
															$innerPages = "data-inner-pages='$dataInnerPages'";
														}
													?>
														<li class="sub-menu-li-ul-li <?= $childMenu['additional_class_names'] ?>">
															<a class="menu-item" href="<?= $childMenu['link'] ?>" <?= $innerPages; ?>>
																<span class="<?= $childMenu['icon'] ?>"></span>
																<span class="inner-menu-item-text">
																	<?= _translate($childMenu['display_text']); ?>
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
			<iframe id="dFrame" src="" title="LIS Content" style="border:none;" scrolling="yes" marginwidth="0" marginheight="0" frameborder="0" vspace="0" hspace="0"></iframe>
			<?= _translate("Unable to load this page or resource"); ?>
			</iframe>
		</div>
