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

$_SESSION['modules'] ??= [];
$arr = $general->getGlobalConfig();
$sarr = $general->getSystemConfig();

$skin = "skin-blue";

$trainingMode = isset($arr['training_mode']) && trim((string) $arr['training_mode']) === 'yes';

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
		<?= ($title ?? $shortCode) . " | " . "InteLIS | " . $shortName; ?>
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
	<link rel="stylesheet" media="all" type="text/css"
		href="/assets/css/style.css?v=<?= filemtime(WEB_ROOT . "/assets/css/style.css") ?>" />
	<link rel="stylesheet" type="text/css"
		href="/assets/css/toastify.min.css?v=<?= filemtime(WEB_ROOT . "/assets/css/toastify.min.css") ?>">
	<link rel="stylesheet" type="text/css" href="/assets/css/summernote.min.css">
	<link rel="stylesheet" media="all" type="text/css" href="/assets/css/selectize.css" />
	<link rel="stylesheet" media="all" type="text/css"
		href="/assets/css/spotlight-search.css?v=<?= filemtime(WEB_ROOT . '/assets/css/spotlight-search.css'); ?>" />

	<script type="text/javascript" src="/assets/js/jquery.min.js"></script>
	<script type="text/javascript" src="/assets/js/jquery-ui.min.js"></script>
	<script type="text/javascript"
		src="/assets/js/deforayModal.js?v=<?= filemtime(WEB_ROOT . "/assets/js/deforayModal.js") ?>"></script>
	<script type="text/javascript" src="/assets/js/jquery.fastconfirm.js"></script>
	<script type="text/javascript"
		src="/assets/js/utils.js?v=<?= filemtime(WEB_ROOT . '/assets/js/utils.js') ?>"></script>

	<?php
	// Flatten menu for spotlight - includes parent menus with expandable children
	$flattenMenuForSpotlight = function (array $menuItems, array $parentPath = []) use (&$flattenMenuForSpotlight): array {
		$flatList = [];
		foreach ($menuItems as $menu) {
			$menuTitle = _translate($menu['display_text']);
			$currentPath = $parentPath;

			// Skip headers but process their children
			if (($menu['is_header'] ?? 'no') === 'yes') {
				$currentPath[] = $menuTitle;
				if (!empty($menu['children'])) {
					$flatList = [...$flatList, ...$flattenMenuForSpotlight($menu['children'], $currentPath)];
				}
				continue;
			}

			$link = $menu['link'] ?? '';
			$hasChildren = ($menu['has_children'] ?? 'no') === 'yes' && !empty($menu['children']);
			$hasValidLink = $link !== '' && $link !== '#' && !str_starts_with($link, '#');

			$category = !empty($parentPath) ? end($parentPath) : _translate('Navigation');
			$subcategory = count($parentPath) > 1 ? implode(' → ', array_slice($parentPath, 0, -1)) : '';

			// Parent menu with children - make it expandable
			if ($hasChildren) {
				$actions = [];
				foreach ($menu['children'] as $child) {
					$childLink = $child['link'] ?? '';
					if ($childLink !== '' && $childLink !== '#' && !str_starts_with($childLink, '#')) {
						$actions[] = [
							'label' => _translate($child['display_text']),
							'url' => $childLink,
							'icon' => $child['icon'] ?? 'fa-solid fa-arrow-right',
						];
					}
				}
				if (!empty($actions)) {
					$flatList[] = [
						'id' => 'menu-' . $menu['id'],
						'title' => $menuTitle,
						'url' => $actions[0]['url'], // Default to first child
						'icon' => $menu['icon'] ?? 'fa-solid fa-folder',
						'category' => $category,
						'subcategory' => $subcategory,
						'module' => $menu['module'] ?? '',
						'sortOrder' => (int) ($menu['sort_order'] ?? 0),
						'actions' => $actions,
						'isExpandable' => true,
					];
				}
				// Also process children recursively for deeper nesting
				$currentPath[] = $menuTitle;
				$flatList = [...$flatList, ...$flattenMenuForSpotlight($menu['children'], $currentPath)];
			} elseif ($hasValidLink) {
				// Regular menu item with direct link
				$flatList[] = [
					'id' => 'menu-' . $menu['id'],
					'title' => $menuTitle,
					'url' => $link,
					'icon' => $menu['icon'] ?? 'fa-solid fa-file',
					'category' => $category,
					'subcategory' => $subcategory,
					'module' => $menu['module'] ?? '',
					'sortOrder' => (int) ($menu['sort_order'] ?? 0),
				];
			}
		}
		return $flatList;
	};
	$spotlightCacheKey = 'spotlight_menu_' . ($_SESSION['userId'] ?? 'default');
	$spotlightData = MemoUtility::memo($spotlightCacheKey, fn() => $flattenMenuForSpotlight($_SESSION['menuItems'] ?? []), 300);
	?>
	<script>
		window.spotlightData = <?= JsonUtility::encodeUtf8Json($spotlightData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
		window.spotlightUserId = '<?= $_SESSION['userId'] ?? 'default'; ?>';
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

			<nav class="navbar" style="position:fixed;top:10;left:0;right:0;">
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
						<!-- Spotlight Search Trigger -->
						<li class="spotlight-trigger-wrapper">
							<a href="#" id="spotlightTrigger" title="<?= _translate('Quick Search'); ?> (Ctrl+K)">
								<i class="fa-solid fa-magnifying-glass"></i>
								<span class="hidden-xs kbd-hint">Ctrl+K</span>
							</a>
						</li>
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
				<?php if (isset($arr['logo']) && trim((string) $arr['logo']) !== "" && file_exists('uploads' . DIRECTORY_SEPARATOR . "logo" . DIRECTORY_SEPARATOR . $arr['logo'])) { ?>
					<div class="user-panel">
						<div>
							<img src="/uploads/logo/<?= $arr['logo']; ?>" alt="Logo" style="max-width:120px;">
						</div>
					</div>
				<?php } ?>
				<ul class="sidebar-menu" data-widget="tree">
					<?php
					foreach ($_SESSION['menuItems'] as $menu) {
						if ($menu['has_children'] == 'yes' && empty($menu['children'])) {
							// Supposed to have children but does not have?
							// Continue to next menu. We dont need this one
							continue;
						}
						$classNames = trim((string) ($menu['additional_class_names'] ?? '' . ($menu['has_children'] == "yes" ? ' treeview' : '')));

						if ($menu['is_header'] == 'yes') {
							echo '<li class="header">' . $menu['display_text'];
						} else {
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

		<!-- Spotlight Search Modal -->
		<div id="spotlightModal" class="spotlight-modal" style="display: none;">
			<div class="spotlight-backdrop"></div>
			<div class="spotlight-dialog">
				<div class="spotlight-search-wrapper">
					<i class="fa-solid fa-magnifying-glass spotlight-icon"></i>
					<input type="text" id="spotlightInput" class="spotlight-input"
						placeholder="<?= _translate('Search menus, actions...'); ?>" autocomplete="off"
						spellcheck="false">
					<span class="spotlight-shortcut">ESC</span>
				</div>
				<div id="spotlightResults" class="spotlight-results"></div>
				<div class="spotlight-footer">
					<span><kbd>↑</kbd><kbd>↓</kbd> <?= _translate('Navigate'); ?></span>
					<span><kbd>Enter</kbd> <?= _translate('Open'); ?></span>
					<span><kbd>Esc</kbd> <?= _translate('Close'); ?></span>
				</div>
			</div>
		</div>