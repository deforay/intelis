<?php
// header.php
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\AppMenuService;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;
use App\Utilities\JsonUtility;
use App\Utilities\MemoUtility;

// Reset query counters on page reload
unset($_SESSION['queryCounters']);

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

if ($db->isConnected() === false) {
	throw new SystemException("Database connection failed. Please check your database settings", 500);
}

// Page usage tracking. Only real pages include header.php, so this counts
// pages and not AJAX round trips. The call never throws.
(ContainerRegistry::get(App\Services\PageUsageService::class))->recordPageOpen();
$_SESSION['modules'] ??= [];
$arr = $general->getGlobalConfig();
$sarr = $general->getSystemConfig();

$skin = "skin-blue";

$trainingMode = isset($arr['training_mode']) && trim((string) $arr['training_mode']) === 'yes';

$shortCode = 'LIS';
$logoName = "<img src='/assets/img/flask.png' style='margin-top:-5px;max-width:22px;'> <span style=''>$shortCode</span>";
$smallLogoName = "<img src='/assets/img/flask.png'>";
$systemDisplayName = _translate("Lab Sample Management Module");
$shortName = _translate("Sample Management");
if ($general->isSTSInstance()) {
	$skin = "skin-red";
	$shortCode = 'STS';
	$systemDisplayName = _translate("Sample Tracking System");
	$logoName = "<span class='fa fa-medkit'></span> $shortCode";
	$smallLogoName = "<span class='fa fa-medkit'></span>";
	$shortName = _translate("Sample Tracking");

	// A testing-lab user works the lab (LIS) side of the STS. Give it a
	// distinct skin so it reads apart from both the red STS-admin view and
	// the blue standalone LIS.
	if (($_SESSION['accessType'] ?? '') === 'testing-lab') {
		$skin = "skin-purple";
		$shortCode = "Cloud LIS";
		$logoName = "<img src='/assets/img/flask.png' style='margin-top:-5px;max-width:22px;'> <span style=''>$shortCode</span>";
		$smallLogoName = "<img src='/assets/img/flask.png'>";
		$systemDisplayName = _translate("Lab Sample Management Module");
		$shortName = _translate("Sample Management");
	}
}

$systemDisplayName = (SYSTEM_CONFIG['instance-name'] != null || SYSTEM_CONFIG['instance-name'] != '') ? SYSTEM_CONFIG['instance-name'] : $systemDisplayName;


/** @var Psr\Http\Message\ServerRequestInterface $request */
$request = AppRegistry::get('request');

$countryCode = $arr['default_phone_prefix'] ?? '';
$maxPhoneLength = $_SESSION['maxPhoneLength'] ??= strlen((string) $countryCode) + (_castVariable($arr['max_phone_length'] ?? null, 'int') ?? 15);

$_SESSION['menuItems'] ??= (ContainerRegistry::get(AppMenuService::class))->getMenu();

$instrumentsCount = $general->getInstrumentsCount();
$nonAdminUserCount = $general->getNonAdminUsersCount();

$displayTopBar = $instrumentsCount == 0 || $nonAdminUserCount == 0;

$margin = $displayTopBar ? 'style="margin-top:50px !important;"' : '';
$topSide = $displayTopBar ? 'style="top:50px !important;"' : 'style="top:0 !important;"';


$locale = $_SESSION['APP_LOCALE'] ?? 'en_US';
$langCode = explode('_', (string) $locale)[0]; // Gets 'en' from 'en_US'
?>
<!DOCTYPE html>
<html lang="<?= $langCode; ?>" translate="no">

<head>
	<meta charset="utf-8" />
	<meta http-equiv="X-UA-Compatible" content="IE=edge" />
	<title>
		<?= _escapeRequestValue($title ?? $shortCode) . " | " . "InteLIS | " . _escapeRequestValue($shortName); ?>
	</title>
	<meta name="google" content="notranslate">
	<meta name="google-translate-customization" content="0">
	<meta http-equiv="Content-Language" content="<?= $langCode; ?>">
	<meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
	<meta name="viewport" content="width=1024">

	<?php
	$iconType = $general->isSTSInstance() ? 'sts' : 'lis';
	?>

	<link rel="apple-touch-icon" sizes="180x180" href="<?= _asset('/assets/' . $iconType . '-icons/apple-touch-icon.png') ?>">
	<link rel="icon" type="image/png" sizes="32x32" href="<?= _asset('/assets/' . $iconType . '-icons/favicon-32x32.png') ?>">
	<link rel="icon" type="image/png" sizes="16x16" href="<?= _asset('/assets/' . $iconType . '-icons/favicon-16x16.png') ?>">
	<link rel="manifest" href="<?= _asset('/assets/' . $iconType . '-icons/site.webmanifest') ?>">

	<link rel="stylesheet" media="all" type="text/css" href="<?= _asset('/assets/css/fonts.css') ?>" />
	<link rel="stylesheet" media="all" type="text/css" href="<?= _asset('/assets/css/jquery-ui.min.css') ?>" />
	<link rel="stylesheet" media="all" type="text/css" href="<?= _asset('/assets/css/jquery-ui-timepicker-addon.css') ?>" />
	<link rel="stylesheet" media="all" type="text/css" href="<?= _asset('/assets/css/bootstrap.min.css') ?>">
	<link rel="stylesheet" media="all" type="text/css" href="<?= _asset('/assets/css/font-awesome.min.css') ?>">
	<link rel="stylesheet" media="all" type="text/css" href="<?= _asset('/assets/plugins/datatables/dataTables.bootstrap.css') ?>">
	<link rel="stylesheet" media="all" type="text/css" href="<?= _asset('/assets/css/AdminLTE.min.css') ?>">
	<link rel="stylesheet" media="all" type="text/css" href="<?= _asset('/assets/css/skins/_all-skins.min.css') ?>">
	<link rel="stylesheet" media="all" type="text/css" href="<?= _asset('/assets/plugins/daterangepicker/daterangepicker.css') ?>" />
	<link rel="stylesheet" media="all" type="text/css" href="<?= _asset('/assets/css/select2.min.css') ?>" />
	<link rel="stylesheet" media="all" type="text/css" href="<?= _asset('/assets/css/deforayModal.css') ?>" />
	<link rel="stylesheet" media="all" type="text/css" href="<?= _asset('/assets/css/jquery.fastconfirm.css') ?>" />
	<link rel="stylesheet" media="all" type="text/css" href="<?= _asset('/assets/css/components-rounded.min.css') ?>">
	<link rel="stylesheet" media="all" type="text/css" href="<?= _asset('/assets/css/select2.live.min.css') ?>" />
	<link rel="stylesheet" media="all" type="text/css"
		href="<?= _asset('/assets/css/style.css') ?>" />
	<link rel="stylesheet" type="text/css"
		href="<?= _asset('/assets/css/toastify.min.css') ?>">
	<link rel="stylesheet" type="text/css" href="<?= _asset('/assets/css/summernote.min.css') ?>">
	<link rel="stylesheet" media="all" type="text/css" href="<?= _asset('/assets/css/selectize.css') ?>" />

	<script type="text/javascript" src="<?= _asset('/assets/js/jquery.min.js') ?>"></script>
	<script type="text/javascript" src="<?= _asset('/assets/js/jquery-ui.min.js') ?>"></script>
	<script type="text/javascript"
		src="<?= _asset('/assets/js/deforayModal.js') ?>"></script>
	<script type="text/javascript" src="<?= _asset('/assets/js/jquery.fastconfirm.js') ?>"></script>
	<script type="text/javascript"
		src="<?= _asset('/assets/js/utils.js') ?>"></script>
	<script type="text/javascript"
		src="<?= _asset('/assets/js/error-logger.js') ?>"></script>
	<script type="text/javascript">
		// Prefix for anything a page keeps in localStorage that belongs to the
		// signed-in user (dismissals, saved filters), so the next user on a shared
		// browser starts clean.
		window.IntelisUserKey = <?= json_encode(empty($_SESSION['userId']) ? 'anonymous' : hash('sha256', 'browser-storage|' . $_SESSION['userId'])) ?>;
	</script>

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

	input.checkNum:invalid {
		border: 1px solid red;
	}

	/* The theme draws a dropdown's pointer at its left edge unless the menu is
	   "pull-right"; the Exports menu opens to the left, so point at its icon. */
	#exportJobsMenu > .dropdown-menu:before {
		left: auto;
		right: 15px;
	}

	#exportJobsMenu > .dropdown-menu:after {
		left: auto;
		right: 16px;
	}

/* ================================================================
   INTELIS COLLAPSED MODULE SIDEBAR
   ================================================================ */
@media (min-width: 768px) {
.sidebar-mini.sidebar-collapse .main-sidebar {
width: 120px !important;
/*overflow: visible !important;*/
}
.sidebar-mini.sidebar-collapse .content-wrapper,
.sidebar-mini.sidebar-collapse .right-side,
.sidebar-mini.sidebar-collapse .main-footer {
margin-left: 120px !important;
}
.sidebar-mini.sidebar-collapse .main-header .logo {
width: 120px !important;
}
.sidebar-mini.sidebar-collapse .main-header .navbar {
margin-left: 120px !important;
}
.sidebar-mini.sidebar-collapse .sidebar,
.sidebar-mini.sidebar-collapse .sidebar-menu {
overflow: visible !important;
}
/* LEFT COLUMN - vertical module name */
.sidebar-mini.sidebar-collapse.sidebar-menu > li.module-header {
display: block !important;
position: relative;
float: left;
width: 55px;
min-height: 135px;
padding: 0 !important;
background: #2f4148;
border-right: 1px solid #17252b;
border-bottom: 1px solid #17252b;
overflow: hidden !important;
}
.sidebar-mini.sidebar-collapse
.sidebar-menu > li.module-header .module-title {
display: block !important;
position: absolute;
top: 50%;
left: 50%;
width: 125px;
transform: translate(-50%, -50%) rotate(-90deg);
transform-origin: center;
color: #fff;
font-size: 13px;
font-weight: 700;
letter-spacing: 1px;
text-align: center;
white-space: nowrap;
}
/* RIGHT COLUMN - menu icons */
.sidebar-mini.sidebar-collapse
.sidebar-menu > li:not(.module-header) {
position: relative;
margin-left: 55px;
width: 65px;
background: #1f3035;
border-bottom: 1px solid #17252b;
}
.sidebar-mini.sidebar-collapse
.sidebar-menu > li:not(.module-header) > a {
width: 65px;
min-height: 45px;
padding: 12px 0 !important;
text-align: center;
}
.sidebar-mini.sidebar-collapse
.sidebar-menu > li:not(.module-header) > a > i:first-child {
width: 65px !important;
margin: 0 !important;
font-size: 20px;
text-align: center;
}
/* Hide normal menu labels and arrows in collapsed mode */
.sidebar-mini.sidebar-collapse
.sidebar-menu > li:not(.module-header) >
a > span:not(.pull-right-container) {
display: none !important;
}
.sidebar-mini.sidebar-collapse
.sidebar-menu > li > a > .pull-right-container {
display: none !important;
}
/* Active icon */
.sidebar-mini.sidebar-collapse .sidebar-menu > li.active > a,
.sidebar-mini.sidebar-collapse .sidebar-menu > li.menu-open > a {
background: #625fb2 !important;
color: #fff !important;
}
/* Submenu flyout */
.sidebar-mini.sidebar-collapse
.sidebar-menu > li.treeview > .treeview-menu {
display: none !important;
position: absolute !important;top: 0 !important;
left: 65px !important;
width: 320px !important;
margin: 0 !important;
padding: 0 !important;
background: #304248;
z-index: 99999;
box-shadow: 4px 4px 12px rgba(0,0,0,.25);
}
.sidebar-mini.sidebar-collapse
.sidebar-menu > li.treeview.menu-open > .treeview-menu {
display: block !important;
}
.sidebar-mini.sidebar-collapse .treeview-menu > li {
width: 100% !important;
margin: 0 !important;
}
.sidebar-mini.sidebar-collapse .treeview-menu > li > a {
display: block !important;
width: 100% !important;
padding: 13px 20px !important;
color: #e6ecef !important;
white-space: nowrap;
text-align: left !important;
}
/*.sidebar-mini.sidebar-collapse .treeview-menu > li > a:hover {
background: #26383e !important;
color: #fff !important;
}*/
.sidebar-mini.sidebar-collapse
.treeview-menu > li > a > span {
display: inline !important;
position: static !important;
width: auto !important;
margin: 0 !important;
padding: 0 !important;
transform: none !important;
background: transparent !important;
}
/* Disable AdminLTE's default hover flyout */
.sidebar-mini:not(.sidebar-mini-expand-feature)
.sidebar-collapse
.sidebar-menu > li:hover > .treeview-menu {
display: none !important;
}
.sidebar-mini:not(.sidebar-mini-expand-feature)
.sidebar-collapse
.sidebar-menu > li.menu-open:hover > .treeview-menu {
display: block !important;
}

}
    .sidebar-mini.sidebar-collapse .sidebar-menu > li.custom-vertical-header-item {
        display: block !important; /* Overrides default script asset blocks */
        float: left !important;
        clear: left !important;
        width: 55px !important;
        height: 180px !important;  /* Locks structural height directly to match 3 sub-items rows */
        background: #2f4148 !important;
        border-right: 1px solid #17252b !important;
        border-bottom: 1px solid #17252b !important;
        padding: 0 !important;
        margin: 0 !important;
        position: relative !important;
    }

    /* Format inner parent anchor spacing inside the header box block */
    .sidebar-mini.sidebar-collapse .sidebar-menu > li.custom-vertical-header-item > a.custom-header-anchor-link {
        display: block !important;
        width: 55px !important;
        height: 135px !important;
        padding: 0 !important;
        margin: 0 !important;
        cursor: default !important;
    }

    /* 4. Text Rotation Framework Alignment Rules */
    .sidebar-mini.sidebar-collapse .sidebar-menu > li.custom-vertical-header-item .vertical-title-text {
        display: block !important;
        position: absolute !important;
        top: 50% !important;
        left: 50% !important;
        width: 125px !important;
        transform: translate(-50%, -50%) rotate(-90deg) !important;
        transform-origin: center center !important;
        color: #fff !important;
        font-size: 11px !important;
        font-weight: 700 !important;
        letter-spacing: 1px !important;
        text-align: center !important;
        white-space: nowrap !important;
    }

    /* 5. RIGHT COLUMN MENU ICON ALIGNMENTS */
    .sidebar-mini.sidebar-collapse .sidebar-menu > li:not(.custom-vertical-header-item) {
        display: block !important;
        float: left !important;
        clear: none !important;
        width: 65px !important;
        margin: 0 !important;
        background: #1f3035;
        border-bottom: 1px solid #17252b;
        position: relative !important;
    }

    .sidebar-mini.sidebar-collapse .sidebar-menu > li:not(.custom-vertical-header-item) > a {
        display: block !important;
        width: 65px !important;
        min-height: 45px;
        padding: 12px 0 !important;
        text-align: center;
    }

    .sidebar-mini.sidebar-collapse .sidebar-menu > li:not(.custom-vertical-header-item) > a > i:first-child {
        width: 65px !important;
        margin: 0 !important;
        font-size: 20px;
        text-align: center;
    }

    /* Strip native text values inside standard icons items in collapsed mode */
    .sidebar-mini.sidebar-collapse .sidebar-menu > li:not(.custom-vertical-header-item) > a > span {
        display: none !important;
    }
    .sidebar-mini.sidebar-collapse .sidebar-menu > li > a > .pull-right-container {
        display: none !important;
    }

    /* Active Highlight Element Configurations */
    .sidebar-mini.sidebar-collapse .sidebar-menu > li.active > a,
    .sidebar-mini.sidebar-collapse .sidebar-menu > li.menu-open > a {
        background: #625fb2 !important;
        color: #fff !important;
    }
</style>

<body class="hold-transition <?= $skin; ?> sidebar-mini" id="lis-body" <?= $margin; ?> translate="no"
	class="notranslate">
	

	<?php if (
		($general->isLisInstance() && $instrumentsCount == 0) ||
		$nonAdminUserCount == 0
	) { ?>
		<div class="topBar">
			<p class="white text-center">
				<?php if ($nonAdminUserCount == 0) { ?>
					<a href="/users/addUser.php"
						style="font-weight:bold; color: black;"><?= _translate("Please click here to add one or more non-admin users before you can start using the system"); ?>
					</a>
				<?php } ?>
				<?php if ($general->isLisInstance() && $instrumentsCount == 0) { ?>
					&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <a href="/instruments/add-instrument.php"
						style="font-weight:bold; color: black;"><?= _translate("Please click here to add one or more instruments before you can start using the LIS"); ?>
					</a>
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

			<nav class="navbar">
				<!-- Sidebar toggle button-->
				<a href="#" class="sidebar-toggle" data-toggle="push-menu" role="button">
					<span class="sr-only">Toggle navigation</span>
				</a>

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
								<a onclick="setCrossLogin();"
									href="<?= rtrim((string) SYSTEM_CONFIG['recency']['url'], "/") . '/login?u=' . base64_encode((string) $_SESSION['loginId']) . '&t=' . ($_SESSION['crossLoginPass']) . '&name=' . base64_encode((string) $_SESSION['userName']); ?>"
									class="btn btn-link"><i class="fa-solid fa-arrow-up-right-from-square"></i>
									<?= _translate('Recency'); ?>
								</a>
							</li>
						<?php } ?>

						<?php // Background export progress; filled in and shown by /assets/js/export-jobs.js. ?>
						<li class="dropdown" id="exportJobsMenu" style="display:none;">
							<a href="#" class="dropdown-toggle" data-toggle="dropdown" title="<?= _htmlTranslate('Exports'); ?>">
								<i class="fa-solid fa-file-arrow-down" id="exportJobsIcon"></i>
								<span class="label label-warning" id="exportJobsCount"></span>
							</a>
							<ul class="dropdown-menu dropdown-menu-right" style="width:340px;max-width:calc(100vw - 32px);padding:0;">
								<li class="header" style="padding:8px 12px;font-weight:600;border-bottom:1px solid #f4f4f4;display:flex;justify-content:space-between;align-items:center;">
									<span><?= _htmlTranslate('Exports'); ?></span>
									<?php // Wrapped in a span so the dropdown's "li > a" menu-item styling does not make it a block button. ?>
									<span>
										<a href="javascript:void(0);" id="exportJobsClearAll" style="display:none;font-weight:normal;font-size:12px;padding:0;background:none;color:#3c8dbc;text-decoration:underline;">
											<?= _htmlTranslate('Clear all'); ?>
										</a>
									</span>
								</li>
								<li id="exportJobsList" style="padding:8px;max-height:60vh;overflow-y:auto;"></li>
							</ul>
						</li>

						<li class="dropdown user user-menu">
							<a href="#" class="dropdown-toggle" data-toggle="dropdown">
								<i class="fa-solid fa-hospital-user"></i>
								<span class="hidden-xs">
									<?= $_SESSION['userName'] ?? ''; ?>
								</span>
								<i class="fa-solid fa-circle is-remote-server-reachable"
									style="font-size:1em;display:none;"></i>
							</a>
							<ul class="dropdown-menu">
								<?php
								if (!empty($arr['edit_profile']) && $arr['edit_profile'] == 'yes') {
									?>
									<li class="user-footer">
										<a href="/users/edit-profile.php">
											<i class="fa-solid fa-user-pen" aria-hidden="true"></i>
											<?= _htmlTranslate("Edit Profile"); ?>
										</a>
									</li>
								<?php } ?>
								<li class="user-footer">
									<a href="/users/change-password.php">
										<i class="fa-solid fa-key" aria-hidden="true"></i>
										<?= _htmlTranslate("Change Password"); ?>
									</a>
								</li>
								<li class="user-footer">
									<a href="/login/logout.php">
										<i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
										<?= _htmlTranslate("Sign out"); ?>
									</a>
								</li>
							</ul>
						</li>
					</ul>
				</div>
			</nav>

		</header>

		<!-- Left side column. contains the logo and sidebar -->
	<!-- Left side column. contains the logo and sidebar -->
		<aside class="main-sidebar" <?= $topSide; ?>>
			<section class="sidebar">
				<?php if (isset($arr['logo']) && trim((string) $arr['logo']) !== "" && file_exists('uploads' . DIRECTORY_SEPARATOR . "logo" . DIRECTORY_SEPARATOR . $arr['logo'])) { ?>
					<div class="user-panel">
						<div>
							<img src="/uploads/logo/<?= $arr['logo']; ?>" alt="Logo" style="max-width:120px;">
						</div>
					</div>
				<?php } ?>
	<ul class="sidebar-menu vertical-module-menu" data-widget="tree">
					<?php
					foreach ($_SESSION['menuItems'] as $menu) {
						if ($menu['has_children'] == 'yes' && empty($menu['children'])) {
							// Supposed to have children but does not have?
							// Continue to next menu. We dont need this one
							continue;
						}
						$classNames = trim((string) ($menu['additional_class_names'] ?? '' . ($menu['has_children'] == "yes" ? ' treeview' : '')));

						if ($menu['is_header'] == 'yes') {
							?>
							<li class="header module-header custom-vertical-header-item">
							<span class="module-title">
							<?= _translate($menu['display_text']); ?>
							</span>
							<?php
						}
							else {
							?>

							<li class="<?= $classNames; ?>">
								<?php
								$href = ($menu['has_children'] === 'yes') ? '#' : $menu['link'];
								?>
								<a href="<?= $href ?>">
									<i class="<?= $menu['icon'] ?>"></i>
									<span><?= _translate($menu['display_text']); ?></span>
									<?php if ($menu['has_children'] === 'yes') { ?>
										<span class="pull-right-container">
											<i class="fa fa-angle-left pull-right"></i>
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
									} ?>
										<li class="<?= $subMenuHasChildren ? 'treeview' : ''; ?>">
											<?php $subHref = $subMenuHasChildren ? '#' : $subMenu['link']; ?>
											<a href="<?= $subHref; ?>" <?= $innerPages; ?>>
												<i class="<?= $subMenu['icon'] ?>"></i>&nbsp;
												<span>
													<?= _translate($subMenu['display_text']); ?>
												</span>
												<?php if ($subMenuHasChildren) { ?>
													<span class="pull-right-container">
														<i class="fa-solid fa-angle-left pull-right"></i>
													</span>
												<?php } ?>
											</a>
											<?php if ($subMenuHasChildren) { ?>
												<ul class="treeview-menu">
													<?php
													foreach ($subMenu['children'] as $childMenu) {
														$innerPages = '';
														if (!empty($childMenu['inner_pages'])) {
															$dataInnerPages = explode(',', (string) $childMenu['inner_pages']);
															$dataInnerPages = implode(';', array_map('base64_encode', $dataInnerPages));
															$innerPages = "data-inner-pages='$dataInnerPages'";
														}
														?>
														<li class="<?= $childMenu['additional_class_names'] ?>">
															<a href="<?= $childMenu['link'] ?>" <?= $innerPages; ?>>
																<i class="<?= $childMenu['icon'] ?>"></i>
																<?= _translate($childMenu['display_text']); ?>
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
		<div id="dDiv" class="dialog" hidden>
			<div class="dfy-modal" role="dialog" aria-modal="true">
				<button type="button" class="dfy-modal__close" aria-label="Close" onclick="closeModal()">×</button>
				<iframe id="dFrame" src="" title="LIS Content" class="dfy-modal__iframe" loading="lazy"
					referrerpolicy="no-referrer"></iframe>
				<div id="dfy-modal-fallback" class="dfy-modal__fallback" hidden>
					<?= _translate("Unable to load this page or resource"); ?>
				</div>
			</div>
		</div>