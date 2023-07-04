<?php

use App\Services\CommonService;
use App\Registries\ContainerRegistry;

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$supportEmail = trim($general->getGlobalConfig('support_email'));

?>

<footer class="main-footer">

	<small>
		<?= _("This project is supported by the U.S. President's Emergency Plan for AIDS Relief (PEPFAR) through the U.S.
		Centers for Disease Control and Prevention (CDC)."); ?>
	</small>
	<?php if (!empty($supportEmail)) { ?>
		<small><a href="javascript:void(0);"
				onclick="showModal('/support/index.php?fUrl=<?php echo htmlspecialchars($_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>', 900, 520);">Support</a></small>
	<?php } ?>
	<small class="pull-right" style="font-weight:bold;">&nbsp;&nbsp;
		<?php echo "v" . VERSION; ?>
	</small>
	<?php

	if (!empty(SYSTEM_CONFIG['remoteURL']) && isset($_SESSION['userName']) && isset($_SESSION['instanceType']) && ($_SESSION['instanceType'] == 'vluser')) { ?>
		<div class="pull-right">
			<small>
				<a href="javascript:syncRemoteData();">
					<?= _("Force Remote Sync"); ?>
				</a>&nbsp;&nbsp;
			</small>
		</div>
		<?php
	}
	$lastSync = '';

	if (isset($_SESSION['privileges']) && in_array("sync-history.php", $_SESSION['privileges'])) {
		$syncHistory = "/common/reference/sync-history.php";
	} else {
		$syncHistory = "javascript:void(0);";
	}

	$syncLatestTime = $general->getLastSyncDateTime();

	if (empty($syncLatestTime)) {
		$syncHistoryDisplay = "display:none;";
	} else {
		$syncHistoryDisplay = "display:inline;";
	}

	?>
	<br>
	<div class="syncHistoryDiv" style="float:right;font-size:x-small;<?= $syncHistoryDisplay ?>" class="pull-right">
		<a href="<?= $syncHistory; ?>" class="text-muted">
			<?= _("Last synced at"); ?>
			<span class="lastSyncDateTime">
				<?= $syncLatestTime; ?>
			</span>
		</a>
	</div>
</footer>
</div>

<script type="text/javascript" src="/assets/js/jquery-ui-timepicker-addon.js"></script>
<script type="text/javascript" src="/assets/js/js.cookie.js"></script>
<script type="text/javascript" src="/assets/js/select2.min.js"></script>
<script type="text/javascript" src="/assets/js/bootstrap.min.js"></script>
<script type="text/javascript" src="/assets/plugins/datatables/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="/assets/plugins/datatables/dataTables.bootstrap.min.js"></script>
<script type="text/javascript" src="/assets/js/moment.min.js"></script>
<script type="text/javascript" src="/assets/js/dayjs.min.js"></script>
<script type="text/javascript" src='/assets/js/main.js?v=<?= filemtime(WEB_ROOT . "/assets/js/main.js") ?>'></script>
<script type="text/javascript" src="/assets/js/app.min.js"></script>
<script type="text/javascript" src="/assets/js/deforayValidation.js"></script>
<script type="text/javascript" src="/assets/js/jquery.maskedinput.js"></script>
<script type="text/javascript" src="/assets/js/jquery.blockUI.js"></script>


<script type="text/javascript">
	let remoteSync = false;

	window.additionalXHRParams = {
		layout: 0,
		'X-CSRF-Token': '<?php echo $_SESSION['csrf_token'] = $_SESSION['csrf_token'] ?? $general->generateUUID(); ?>'
	};

	$.ajaxSetup({
		headers: window.additionalXHRParams
	});

	function setCrossLogin() {
		StorageHelper.storeInSessionStorage('crosslogin', 'true');
	}
	<?php if (isset($_SESSION['instanceType']) && $_SESSION['instanceType'] == 'vluser') { ?>
		remoteSync = true;

		function syncRemoteData() {
			if (!navigator.onLine) {
				alert("<?= _("Please connect to internet to sync with STS"); ?>");
				return false;
			}

			if (remoteSync) {
				$.blockUI({
					message: "<h3><?= _("Preparing for STS sync."); ?><br><?= _("Please wait..."); ?></h3>"
				});
				var jqxhr = $.ajax({
					url: "/scheduled-jobs/remote/commonDataSync.php",
				})
					.done(function (data) {
						//console.log(data);
						//alert( "success" );
					})
					.fail(function () {
						$.unblockUI();
						alert("<?= _("Unable to do STS Sync. Please contact technical team for assistance."); ?>");
					})
					.always(function () {
						$.unblockUI();
						syncResults();
					});
			}
		}

		function syncRequests() {
			$.blockUI({
				message: "<h3><?= _("Trying to sync Test Requests"); ?><br><?= _("Please wait..."); ?></h3>"
			});

			if (remoteSync) {
				var jqxhr = $.ajax({
					url: "/scheduled-jobs/remote/requestsSync.php",
				})
					.done(function (data) {
						//console.log(data);
						//alert( "success" );
					})
					.fail(function () {
						$.unblockUI();
						alert("<?= _("Unable to do STS Sync. Please contact technical team for assistance."); ?>");
					})
					.always(function () {
						$.unblockUI();
						//syncResults();
					});
			}
		}

		function syncResults() {

			$.blockUI({
				message: "<h3><?= _("Trying to sync Test Results"); ?><br><?= _("Please wait..."); ?></h3>"
			});

			if (remoteSync) {
				var jqxhr = $.ajax({
					url: "/scheduled-jobs/remote/resultsSync.php",
				})
					.done(function (data) {
						//console.log(data);
						//alert( "success" );
					})
					.fail(function () {
						$.unblockUI();
						alert("<?= _("Unable to do STS Sync. Please contact technical team for assistance."); ?>");
					})
					.always(function () {
						$.unblockUI();
						syncRequests();
					});
			}
		}
	<?php } ?>



		function screenshot(supportId, attached) {
			if (supportId != "" && attached == 'yes') {
				closeModal();
				html2canvas(document.querySelector("#lis-body")).then(canvas => {
					dataURL = canvas.toDataURL();
					$.blockUI();
					$.post("/support/saveScreenshot.php", {
						image: dataURL,
						supportId: supportId
					},
						function (data) {
							$.unblockUI();
							alert("<?= _("Thank you.Your message has been submitted."); ?>");
						});
				});
			} else {
				closeModal();
				$.blockUI();
				$.post("/support/saveScreenshot.php", {
					supportId: supportId
				},
					function (data) {
						$.unblockUI();
						alert("<?= _("Thank you.Your message has been submitted."); ?>");
					});
			}
		}

	$(document).ready(function () {

		$(".allMenu").removeClass('active');

		let url = window.location.pathname + window.location.search;
		let currentMenuItem = $('a[href="' + url + '"]');
		if (currentMenuItem.length == 0) {
			let currentPaths = Utilities.splitPath(url).map(path => btoa(path));
			currentMenuItem = $('a[data-inner-pages]').filter(function () {
				const innerPages = $(this).data('inner-pages').split(';');
				return currentPaths.some(path => innerPages.includes(path));
			});
		}

		if (currentMenuItem.length > 0) {
			currentMenuItem.parent().addClass('active');
			currentMenuItem.parents('li.treeview').addClass('active');
			if (currentMenuItem.parents('li.treeview')) {
				const treeview = currentMenuItem.parents('li.treeview')[0];
				const offset = treeview ? treeview.offsetTop : 0;
				if (offset > 200) {
					$('.main-sidebar').scrollTop(offset);
				}
			}
		}

		if (remoteSync) {
			(function getLastSyncDateTime() {
				let currentDateTime = new Date();
				$.ajax({
					url: '/scheduled-jobs/remote/getLastSyncTime.php',
					cache: false,
					success: function (lastSyncDateString) {
						if (lastSyncDateString != null && lastSyncDateString != undefined) {
							$('.lastSyncDateTime').html(lastSyncDateString);
							$('.syncHistoryDiv').show();
						}
					},
					error: function (data) { }
				});
				setTimeout(getLastSyncDateTime, 15 * 60 * 1000);
			})();

			// Every 5 mins check if STS is reachable
			(function checkSTSConnection() {
				$.ajax({
					url: '<?= SYSTEM_CONFIG['remoteURL'] ?? null; ?>' + '/api/version.php',
					cache: false,
					success: function (data) {
						$('.is-remote-server-reachable').fadeIn(1000);
						$('.is-remote-server-reachable').css('color', '#4dbc3c');
					},
					error: function () {
						$('.is-remote-server-reachable').fadeIn(1000);
						$('.is-remote-server-reachable').css('color', 'red');
					}
				});
				setTimeout(checkSTSConnection, 15 * 60 * 1000);
			})();
		}

		<?php
		$alertMsg = $_SESSION['alertMsg'] ?? '';
		if ($alertMsg !== '') {
			?>
				alert("<?php echo $alertMsg; ?>");
		<?php
		unset($_SESSION['alertMsg']);
		}
		unset($_SESSION['alertMsg']);

		$isLogged = $_SESSION['logged'] ?? '';
		if ($isLogged !== '') {
			?>
				setCrossLogin();
		<?php }
		// if instance facility name is not set, let us show the modal
		
		if (empty($_SESSION['instanceFacilityName'])) {
			?> showModal('/addInstanceDetails.php', 900, 420);
		<?php } ?>


	});
</script>
</body>

</html>
