<?php


use App\Registries\ContainerRegistry;
use App\Services\CommonService;

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$supportEmail = trim($general->getGlobalConfig('support_email'));

?>

<footer class="main-footer">


	<small>This project is supported by the U.S. President's Emergency Plan for AIDS Relief (PEPFAR) through the U.S. Centers for Disease Control and Prevention (CDC).</small>
	<?php if (!empty($supportEmail)) { ?>
		<small><a href="javascript:void(0);" onclick="showModal('/support/index.php?fUrl=<?php echo $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; ?>', 900, 520);">Support</a></small>
	<?php } ?>
	<small class="pull-right" style="font-weight:bold;">&nbsp;&nbsp;<?php echo "v" . VERSION; ?></small>
	<?php

	if (!empty(SYSTEM_CONFIG['remoteURL']) && isset($_SESSION['userName']) && isset($_SESSION['instanceType']) && ($_SESSION['instanceType'] == 'vluser')) { ?>
		<div class="pull-right">
			<small><a href="javascript:syncRemoteData('<?= SYSTEM_CONFIG['remoteURL']; ?>');">Force Remote Sync</a>&nbsp;&nbsp;</small>
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
	<div class="syncHistoryDiv" style="float:right;font-size:x-small;<?= $syncHistoryDisplay ?>" class="pull-right"><a href="<?= $syncHistory; ?>" class="text-muted">Last Synced at <span class="sync-time"><?= $syncLatestTime; ?></a></span></div>
</footer>
</div>

<script type="text/javascript" src="/assets/js/jquery-ui-timepicker-addon.js"></script>
<script type="text/javascript" src="/assets/js/js.cookie.js"></script>
<script type="text/javascript" src="/assets/js/select2.min.js"></script>
<!-- Bootstrap 3.3.6 -->
<script type="text/javascript" src="/assets/js/bootstrap.min.js"></script>
<!-- DataTables -->
<script type="text/javascript" src="/assets/plugins/datatables/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="/assets/plugins/datatables/dataTables.bootstrap.min.js"></script>

<script type="text/javascript" src='/assets/js/main.js?v=<?= filemtime(WEB_ROOT . "/assets/js/main.js") ?>'></script>
<script type="text/javascript" src="/assets/js/app.min.js"></script>
<script type="text/javascript" src="/assets/js/deforayValidation.js"></script>
<script type="text/javascript" src="/assets/js/jquery.maskedinput.js"></script>
<script type="text/javascript" src="/assets/js/jquery.blockUI.js"></script>
<script type="text/javascript" src="/assets/js/moment.min.js"></script>

<script type="text/javascript">
	window.additionalXHRParams = {
		layout: 0,
		'X-CSRF-Token': '<?php echo $_SESSION['csrf_token'] = $_SESSION['csrf_token'] ?? $general->generateUUID(); ?>'
	};

	$.ajaxSetup({
		headers: window.additionalXHRParams
	});

	function setCrossLogin() {
		if (typeof(Storage) !== "undefined") {
			sessionStorage.setItem("crosslogin", "true");
		} else {
			alert("Your browser doesn't support this session!");
			sessionStorage.setItem("crosslogin", "false");
		}
	}
	<?php if (isset($_SESSION['instanceType']) && $_SESSION['instanceType'] == 'vluser') { ?>
		var remoteSync = true;

		function syncRemoteData(remoteUrl) {
			if (!navigator.onLine) {
				alert("<?= _("Please connect to internet to sync with VLSTS"); ?>");
				return false;
			}

			if (remoteSync && remoteUrl != null && remoteUrl != '') {
				$.blockUI({
					message: "<h3><?= _("Preparing for VLSTS Remote sync."); ?><br><?= _("Please wait..."); ?></h3>"
				});
				var jqxhr = $.ajax({
						url: "/scheduled-jobs/remote/commonDataSync.php",
					})
					.done(function(data) {
						//console.log(data);
						//alert( "success" );
					})
					.fail(function() {
						$.unblockUI();
						alert("<?= _("Unable to do VLSTS Remote Sync. Please contact technical team for assistance."); ?>");
					})
					.always(function() {
						//alert( "complete" );
						$.unblockUI();
						syncResults(remoteUrl);
					});
			}
		}

		function syncRequests(remoteUrl) {
			$.blockUI({
				message: "<h3><?= _("Trying to sync Test Requests"); ?><br><?= _("Please wait..."); ?></h3>"
			});

			if (remoteSync && remoteUrl != null && remoteUrl != '') {
				var jqxhr = $.ajax({
						url: "/scheduled-jobs/remote/requestsSync.php",
					})
					.done(function(data) {
						//console.log(data);
						//alert( "success" );
					})
					.fail(function() {
						$.unblockUI();
						alert("<?= _("Unable to do VLSTS Remote Sync. Please contact technical team for assistance."); ?>");
					})
					.always(function() {
						$.unblockUI();
						//syncResults(remoteUrl);
					});
			}
		}


		function syncResults(remoteUrl) {

			$.blockUI({
				message: "<h3><?= _("Trying to sync Test Results"); ?><br><?= _("Please wait..."); ?></h3>"
			});

			if (remoteSync && remoteUrl != null && remoteUrl != '') {
				var jqxhr = $.ajax({
						url: "/scheduled-jobs/remote/resultsSync.php",
					})
					.done(function(data) {
						//console.log(data);
						//alert( "success" );
					})
					.fail(function() {
						$.unblockUI();
						alert("<?= _("Unable to do VLSTS Remote Sync. Please contact technical team for assistance."); ?>");
					})
					.always(function() {
						$.unblockUI();
						syncRequests(remoteUrl);
					});
			}
		}


	<?php } ?>
	let syncInterval = 60 * 60 * 1000 * 2 // 2 hours in ms
	$(document).ready(function() {
		<?php if (isset($_SESSION['instanceType']) && $_SESSION['instanceType'] == 'vluser' && !empty(SYSTEM_CONFIG['remoteURL'])) { ?>

				(function getLastSyncDateTime() {
					let currentDateTime = new Date();
					$.ajax({
						url: '/scheduled-jobs/remote/getLastSyncTime.php',
						cache: false,
						success: function(lastSyncDateString) {
							if (lastSyncDateString != null && lastSyncDateString != undefined) {
								$('.sync-time').html(lastSyncDateString);
								$('.syncHistoryDiv').show();
							}
						},
						error: function(data) {}
					});
					setTimeout(getLastSyncDateTime, 15 * 60 * 1000);
				})();
		<?php } ?>



		<?php
		// Every 5 mins check connection if this is a local installation of VLSM and there is a remote server configured
		if (!empty(SYSTEM_CONFIG['remoteURL']) && $_SESSION['instanceType'] == 'vluser') { ?>

				(function checkNetworkConnection() {
					$.ajax({
						url: '<?php echo rtrim(SYSTEM_CONFIG['remoteURL'], "/"); ?>/api/version.php',
						cache: false,
						success: function(data) {
							$('.is-remote-server-reachable').fadeIn(1000);
							$('.is-remote-server-reachable').css('color', '#4dbc3c');
						},
						error: function() {
							$('.is-remote-server-reachable').fadeIn(1000);
							$('.is-remote-server-reachable').css('color', 'red');
						}
					});
					setTimeout(checkNetworkConnection, 15 * 60 * 1000);
				})();
		<?php } ?>

		<?php if (isset($_SESSION['alertMsg']) && trim($_SESSION['alertMsg']) != "") { ?>
			alert("<?php echo $_SESSION['alertMsg']; ?>");
		<?php
			$_SESSION['alertMsg'] = '';
			unset($_SESSION['alertMsg']);
		}
		if (isset($_SESSION['logged']) && $_SESSION['logged']) { ?> setCrossLogin();
		<?php }

		// if instance facility name is not set, let us show the modal

		if (empty($_SESSION['instanceFacilityName'])) {
		?>
			showModal('/addInstanceDetails.php', 900, 420);
		<?php } ?>

		$('.daterange,#daterange,#sampleCollectionDate,#sampleTestDate,#printSampleCollectionDate,#printSampleTestDate,#vlSampleCollectionDate,#eidSampleCollectionDate,#covid19SampleCollectionDate,#recencySampleCollectionDate,#hepatitisSampleCollectionDate,#hvlSampleTestDate,#printDate,#hvlSampleTestDate').on('cancel.daterangepicker', function(ev, picker) {
			$(this).val('');
		});
	});
	str = $(location).attr('pathname');
	splitsUrl = str.substr(str.lastIndexOf('/') + 1);
	splitsUrlCheck = str.split("/", 5);
	// console.log(splitsUrl);
	if (splitsUrl == 'users.php' || splitsUrl == 'addUser.php' || splitsUrl == 'editUser.php') {
		$(".access-control-menu,.manage").addClass('active');
		$(".allMenu").removeClass('active');
		$(".userMenu").addClass('active');
	} else if (splitsUrl == 'roles.php' || splitsUrl == 'editRole.php' || splitsUrl == 'addRole.php') {
		$(".access-control-menu,.manage").addClass('active');
		$(".allMenu").removeClass('active');
		$(".roleMenu").addClass('active');
	} else if (splitsUrl == 'facilities.php' || splitsUrl == 'addFacility.php' || splitsUrl == 'editFacility.php' || splitsUrl == 'maptest-type.php') {
		$(".facility-config-menu,.manage").addClass('active');
		$(".allMenu").removeClass('active');
		$(".facilityMenu").addClass('active');
	} else if (splitsUrl == 'audit-trail.php') {
		$(".allMenu").removeClass('active');
		$(".audit-trail-menu, .manage, .monitoring-menu").addClass('active');
	} else if (splitsUrl == 'activity-log.php') {
		$(".allMenu").removeClass('active');
		$(".activity-log-menu, .manage, .monitoring-menu").addClass('active');
	} else if (splitsUrl == 'sources-of-requests.php') {
		$(".allMenu").removeClass('active');
		$(".sources-of-requests-report-menu, .manage, .monitoring-menu").addClass('active');
	} else if (splitsUrl == 'sync-status.php' || splitsUrl == 'lab-sync-details.php') {
		$(".allMenu").removeClass('active');
		$(".sync-status-menu, .manage, .monitoring-menu").addClass('active');
	} else if (splitsUrl == 'api-sync-history.php') {
		$(".allMenu").removeClass('active');
		$(".api-sync-history-menu, .manage, .monitoring-menu").addClass('active');
	} else if (splitsUrl == 'facilityMap.php' || splitsUrl == 'addFacilityMap.php' || splitsUrl == 'editFacilityMap.php') {
		$(".manage").addClass('active');
		$(".allMenu").removeClass('active');
	} else if (splitsUrl == 'globalConfig.php' || splitsUrl == 'editGlobalConfig.php') {
		$(".system-config-menu,.manage").addClass('active');
		$(".allMenu").removeClass('active');
		$(".globalConfigMenu").addClass('active');
	} else if (splitsUrl == 'importConfig.php' || splitsUrl == 'addImportConfig.php' || splitsUrl == 'editImportConfig.php') {
		$(".system-config-menu,.manage").addClass('active');
		$(".allMenu").removeClass('active');
		$(".importConfigMenu").addClass('active');
	} else if (splitsUrl == 'otherConfig.php' || splitsUrl == 'editOtherConfig.php' || splitsUrl == 'editRequestEmailConfig.php' || splitsUrl == 'editResultEmailConfig.php') {
		$(".system-config-menu").addClass('active');
		$(".allMenu").removeClass('active');
		$(".otherConfigMenu").addClass('active');
	} else if (splitsUrl == 'testRequestEmailConfig.php' || splitsUrl == 'editTestRequestEmailConfig.php') {
		$(".system-config-menu,.manage").addClass('active');
		$(".allMenu").removeClass('active');
		$(".requestEmailConfigMenu").addClass('active');
	} else if (splitsUrl == 'testResultEmailConfig.php' || splitsUrl == 'editTestResultEmailConfig.php') {
		$(".system-config-menu,.manage").addClass('active');
		$(".allMenu").removeClass('active');
		$(".resultEmailConfigMenu").addClass('active');
	} else if (splitsUrl == 'geographical-divisions-details.php' || splitsUrl == 'add-geographical-divisions.php' || splitsUrl == 'edit-geographical-divisions.php') {
		$(".system-config-menu,.manage").addClass('active');
		$(".allMenu").removeClass('active');
		$(".geographicalMenu").addClass('active');
	} else if (splitsUrl == 'vlRequest.php' || splitsUrl == 'editVlRequest.php' || splitsUrl == 'viewVlRequest.php') {
		$(".request").addClass('active');
		$(".allMenu").removeClass('active');
		$(".vlRequestMenu").addClass('active');
	} else if (splitsUrl == 'addVlRequest.php') {
		$(".request").addClass('active');
		$(".allMenu").removeClass('active');
		$(".addVlRequestMenu").addClass('active');
	} else if (splitsUrl == 'addSamplesFromManifest.php' && splitsUrlCheck[1] != "eid" && splitsUrlCheck[1] != "covid-19" && splitsUrlCheck[1] != "tb") {
		$(".request").addClass('active');
		$(".allMenu").removeClass('active');
		$(".addSamplesFromManifestMenu").addClass('active');
	} else if (splitsUrl == 'addVlRequestZm.php' || splitsUrl == 'editVlRequestZm.php') {
		$(".request").addClass('active');
		$(".allMenu").removeClass('active');
		$(".addVlRequestZmMenu").addClass('active');
	} else if (splitsUrlCheck[1] != 'generic-tests' && (splitsUrl == 'batchcode.php' || splitsUrl == 'addBatch.php' || splitsUrl == 'editBatch.php' || splitsUrl == 'addBatchControlsPosition.php' || splitsUrl == 'editBatchControlsPosition.php')) {
		$(".request").addClass('active');
		$(".allMenu").removeClass('active');
		$(".batchCodeMenu").addClass('active');
	} else if (splitsUrl == 'vlRequestMail.php' || splitsUrl == 'vlRequestMailConfirm.php') {
		$(".request").addClass('active');
		$(".allMenu").removeClass('active');
		$(".vlRequestMailMenu").addClass('active');
	} else if (splitsUrl == 'specimenReferralManifestList.php' || splitsUrl == 'addSpecimenReferralManifest.php' || splitsUrl == 'editSpecimenReferralManifest.php' || splitsUrl == 'move-manifest.php') {
		searchStr = $(location).attr('search');
		if (searchStr == '?t=' + btoa('vl')) {
			$(".allMenu").removeClass('active');
			$(".request").addClass('active');
			$(".specimenReferralManifestListVLMenu").addClass('active');
		} else if (searchStr == '?t=' + btoa('eid')) {
			$(".allMenu").removeClass('active');
			$(".eidRequest").addClass('active');
			$(".specimenReferralManifestListEIDMenu").addClass('active');
		} else if (searchStr == '?t=' + btoa('covid19')) {
			$(".allMenu").removeClass('active');
			$(".covid19Request").addClass('active');
			$(".specimenReferralManifestListC19Menu").addClass('active');
		} else if (searchStr == '?t=' + btoa('hepatitis')) {
			$(".allMenu").removeClass('active');
			$(".hepatitisRequest").addClass('active');
			$(".specimenReferralManifestListHepMenu").addClass('active');
		} else if (searchStr == '?t=' + btoa('generic-tests')) {
			$(".allMenu").removeClass('active');
			$(".generic-test-request-menu, .specimenGenericReferralManifestListMenu").addClass('active');
		}
	} else if (splitsUrl == 'vlResultMail.php' || splitsUrl == 'vlResultMailConfirm.php') {
		$(".test").addClass('active');
		$(".allMenu").removeClass('active');
		$(".vlResultMailMenu").addClass('active');
	} else if (splitsUrl == 'addImportResult.php' || splitsUrl == 'imported-results.php' || splitsUrl == 'importedStatistics.php') {
		searchStr = $(location).attr('search');
		if (searchStr == '?t=' + btoa('vl')) {
			$(".test").addClass('active');
			$(".allMenu").removeClass('active');
			$(".importResultMenu").addClass('active');
		} else if (searchStr == '?t=' + btoa('covid19')) {
			$(".allMenu").removeClass('active');
			$(".covid19Results").addClass('active');
			$(".covid19ImportResultMenu").addClass('active');
		} else if (searchStr == '?t=' + btoa('eid')) {
			$(".eidResults").addClass('active');
			$(".allMenu").removeClass('active');
			$(".eidImportResultMenu").addClass('active');
		} else if (searchStr == '?t=' + btoa('hepatitis')) {
			$(".hepatitisResults").addClass('active');
			$(".allMenu").removeClass('active');
			$(".hepatitisImportResultMenu").addClass('active');
		}
	} else if (splitsUrl == 'vlPrintResult.php') {
		$(".program").addClass('active');
		$(".allMenu").removeClass('active');
		$(".vlPrintResultMenu").addClass('active');
	} else if (splitsUrl == 'vlTestResult.php' || splitsUrl == 'updateVlTestResult.php') {
		$(".test").addClass('active');
		$(".allMenu").removeClass('active');
		$(".vlTestResultMenu").addClass('active');
	} else if (splitsUrl == 'vl-failed-results.php') {
		$(".test").addClass('active');
		$(".allMenu").removeClass('active');
		$(".vlFailedResultMenu").addClass('active');
	} else if (splitsUrl == 'vlResultApproval.php') {
		$(".test").addClass('active');
		$(".allMenu").removeClass('active');
		$(".vlResultApprovalMenu").addClass('active');
	} else if (splitsUrl == 'vl-sample-status.php') {
		$(".program").addClass('active');
		$(".allMenu").removeClass('active');
		$(".missingResultMenu").addClass('active');
	} else if (splitsUrl == 'vlControlReport.php') {
		$(".program").addClass('active');
		$(".allMenu").removeClass('active');
		$(".vlControlReport").addClass('active');
	} else if (splitsUrl == 'vl-export-data.php') {
		$(".program").addClass('active');
		$(".allMenu").removeClass('active');
		$(".vlResultMenu").addClass('active');
	} else if (splitsUrl == 'highViralLoad.php') {
		$(".program").addClass('active');
		$(".allMenu").removeClass('active');
		$(".vlHighMenu").addClass('active');
	} else if (splitsUrl == 'patientList.php') {
		$(".program").addClass('active');
		$(".allMenu").removeClass('active');
		$(".patientList").addClass('active');
	} else if (splitsUrl == 'vlWeeklyReport.php') {
		$(".program").addClass('active');
		$(".allMenu").removeClass('active');
		$(".vlWeeklyReport").addClass('active');
	} else if (splitsUrl == 'sampleRejectionReport.php') {
		$(".program").addClass('active');
		$(".allMenu").removeClass('active');
		$(".sampleRejectionReport").addClass('active');
	} else if (splitsUrl == 'vlMonitoringReport.php') {
		$(".program").addClass('active');
		$(".allMenu").removeClass('active');
		$(".vlMonitoringReport").addClass('active');
	} else if (splitsUrl == 'vlTestingTargetReport.php') {
		$(".program").addClass('active');
		$(".allMenu").removeClass('active');
		$(".vlMonthlyThresholdReport").addClass('active');
	} else if (splitsUrl == 'vlSuppressedTargetReport.php') {
		$(".program").addClass('active');
		$(".allMenu").removeClass('active');
		$(".vlSuppressedMonthlyThresholdReport").addClass('active');
	} else if (splitsUrl == 'generate.php') {
		$(".qr").addClass('active');
		$(".allMenu").removeClass('active');
		$(".generateQRCode").addClass('active');
	} else if (splitsUrl == 'readQRCode.php' || splitsUrl == 'vlRequestRwdForm.php') {
		$(".qr").addClass('active');
		$(".allMenu").removeClass('active');
		$(".readQRCode").addClass('active');
	} else if (splitsUrl == 'eid-requests.php') {
		$(".allMenu").removeClass('active');
		$(".eidRequest").addClass('active');
		$(".eidRequestMenu").addClass('active');
	} else if (splitsUrl == 'eid-add-request.php') {
		$(".allMenu").removeClass('active');
		$(".eidRequest").addClass('active');
		$(".addEidRequestMenu").addClass('active');
	} else if (splitsUrl == 'eid-batches.php' || splitsUrl == 'eid-add-batch.php' || splitsUrl == 'eid-edit-batch.php' || splitsUrl == 'eid-add-batch-position.php' || splitsUrl == 'eid-edit-batch-position.php') {
		$(".allMenu").removeClass('active');
		$(".eidRequest").addClass('active');
		$(".eidBatchCodeMenu").addClass('active');
	} else if (splitsUrl == 'addSamplesFromManifest.php' && splitsUrlCheck[1] == "eid") {
		$(".allMenu").removeClass('active');
		$(".eidRequest").addClass('active');
		$(".addSamplesFromManifestEidMenu").addClass('active');
	} else if (splitsUrl == 'eid-manual-results.php' || splitsUrl == 'eid-update-result.php') {
		$(".allMenu").removeClass('active');
		$(".eidResults").addClass('active');
		$(".eidResultsMenu").addClass('active');
	} else if (splitsUrl == 'eid-failed-results.php') {
		$(".allMenu").removeClass('active');
		$(".eidResults").addClass('active');
		$(".eidFailedResultsMenu").addClass('active');
	} else if (splitsUrl == 'eid-result-status.php') {
		$(".allMenu").removeClass('active');
		$(".eidResults").addClass('active');
		$(".eidResultStatus").addClass('active');
	} else if (splitsUrl == 'eid-sample-status.php') {
		$(".allMenu").removeClass('active');
		$(".eidProgramMenu").addClass('active');
		$(".eidSampleStatus").addClass('active');
	} else if (splitsUrl == 'eid-print-results.php') {
		$(".allMenu").removeClass('active');
		$(".eidProgramMenu").addClass('active');
		$(".eidPrintResults").addClass('active');
	} else if (splitsUrl == 'eid-print-results.php') {
		$(".allMenu").removeClass('active');
		$(".eidProgramMenu").addClass('active');
		$(".eidPrintResults").addClass('active');
	} else if (splitsUrl == 'eid-export-data.php') {
		$(".allMenu").removeClass('active');
		$(".eidProgramMenu").addClass('active');
		$(".eidExportResult").addClass('active');
	} else if (splitsUrl == 'eid-sample-rejection-report.php') {
		$(".allMenu").removeClass('active');
		$(".eidProgramMenu").addClass('active');
		$(".eidSampleRejectionReport").addClass('active');
	} else if (splitsUrl == 'eid-clinic-report.php') {
		$(".allMenu").removeClass('active');
		$(".eidProgramMenu").addClass('active');
		$(".eidClinicReport").addClass('active');
	} else if (splitsUrl == 'eidTestingTargetReport.php') {
		$(".allMenu").removeClass('active');
		$(".eidProgramMenu").addClass('active');
		$(".eidMonthlyThresholdReport").addClass('active');
	} else if (splitsUrl == 'covid-19-requests.php' || splitsUrl == 'covid-19-edit-request.php') {
		$(".allMenu").removeClass('active');
		$(".covid19Request").addClass('active');
		$(".covid19RequestMenu").addClass('active');
	} else if (splitsUrl == 'covid-19-add-request.php') {
		$(".allMenu").removeClass('active');
		$(".covid19Request").addClass('active');
		$(".addCovid19RequestMenu").addClass('active');
	} else if (splitsUrl == 'covid-19-batches.php' || splitsUrl == 'covid-19-add-batch.php' || splitsUrl == 'covid-19-edit-batch.php' || splitsUrl == 'covid-19-add-batch-position.php' || splitsUrl == 'covid-19-edit-batch-position.php') {
		$(".allMenu").removeClass('active');
		$(".covid19Request").addClass('active');
		$(".covid19BatchCodeMenu").addClass('active');
	} else if (splitsUrl == 'addSamplesFromManifest.php' && splitsUrlCheck[1] == "covid-19") {
		$(".allMenu").removeClass('active');
		$(".covid19Request").addClass('active');
		$(".addSamplesFromManifestCovid19Menu").addClass('active');
	} else if (splitsUrl == 'can-record-confirmatory-tests.php' && splitsUrlCheck[1] == "covid-19") {
		$(".allMenu").removeClass('active');
		$(".covid19Results").addClass('active');
		$(".canRecordConfirmatoryTestsCovid19Menu").addClass('active');
	} else if (splitsUrl == 'covid-19-manual-results.php' || splitsUrl == 'covid-19-update-result.php') {
		$(".allMenu").removeClass('active');
		$(".covid19Results").addClass('active');
		$(".covid19ResultsMenu").addClass('active');
	} else if (splitsUrl == 'covid-19-failed-results.php') {
		$(".allMenu").removeClass('active');
		$(".covid19Results").addClass('active');
		$(".covid19FailedResultsMenu").addClass('active');
	} else if (splitsUrl == 'covid-19-confirmation-manifest.php' || splitsUrl == 'covid-19-add-confirmation-manifest.php') {
		$(".allMenu").removeClass('active');
		$(".covid19Results").addClass('active');
		$(".covid19ResultsConfirmationMenu").addClass('active');
	} else if (splitsUrl == 'covid-19-result-status.php') {
		$(".allMenu").removeClass('active');
		$(".covid19Results").addClass('active');
		$(".covid19ResultStatus").addClass('active');
	} else if (splitsUrl == 'mail-covid-19-results.php') {
		$(".allMenu").removeClass('active');
		$(".covid19Results").addClass('active');
		$(".covid19ResultMailMenu").addClass('active');
	} else if (splitsUrl == 'covid-19-qc-data.php' || splitsUrl == 'add-covid-19-qc-data.php' || splitsUrl == 'edit-covid-19-qc-data.php') {
		$(".allMenu").removeClass('active');
		$(".covid19Results").addClass('active');
		$(".covid19QcDataMenu").addClass('active');
	} else if (splitsUrl == 'covid-19-sample-status.php') {
		$(".allMenu").removeClass('active');
		$(".covid19ProgramMenu").addClass('active');
		$(".covid19SampleStatus").addClass('active');
	} else if (splitsUrl == 'covid-19-print-results.php') {
		$(".allMenu").removeClass('active');
		$(".covid19ProgramMenu").addClass('active');
		$(".covid19PrintResults").addClass('active');
	} else if (splitsUrl == 'covid-19-export-data.php') {
		$(".allMenu").removeClass('active');
		$(".covid19ProgramMenu").addClass('active');
		$(".covid19ExportResult").addClass('active');
	} else if (splitsUrl == 'covid-19-sample-rejection-report.php') {
		$(".allMenu").removeClass('active');
		$(".covid19ProgramMenu").addClass('active');
		$(".covid19SampleRejectionReport").addClass('active');
	} else if (splitsUrl == 'covid-19-clinic-report.php') {
		$(".allMenu").removeClass('active');
		$(".covid19ProgramMenu").addClass('active');
		$(".covid19ClinicReportMenu").addClass('active');
	} else if (splitsUrl == 'covid19TestingTargetReport.php') {
		$(".allMenu").removeClass('active');
		$(".covid19ProgramMenu").addClass('active');
		$(".covid19MonthlyThresholdReport").addClass('active');
	} else if (splitsUrl == 'province-details.php' || splitsUrl == 'add-province.php' || splitsUrl == 'edit-province.php') {
		$(".manage").addClass('active');
		$(".common-reference-manage").addClass('active');
		$(".allMenu").removeClass('active');
		$(".common-reference-province").addClass('active');
	} else if (splitsUrl == 'implementation-partners.php' || splitsUrl == 'add-implementation-partners.php' || splitsUrl == 'edit-implementation-partners.php') {
		$(".system-config-menu,.manage").addClass('active');
		$(".common-reference-manage").addClass('active');
		$(".allMenu").removeClass('active');
		$(".common-reference-implementation-partners").addClass('active');
	} else if (splitsUrl == 'funding-sources.php' || splitsUrl == 'add-funding-sources.php' || splitsUrl == 'edit-funding-sources.php') {
		$(".system-config-menu,.manage").addClass('active');
		$(".common-reference-manage").addClass('active');
		$(".allMenu").removeClass('active');
		$(".common-reference-funding-sources").addClass('active');
	} else if (splitsUrl == 'vl-art-code-details.php' || splitsUrl == 'add-vl-art-code-details.php' || splitsUrl == 'edit-vl-art-code-details.php') {
		$(".manage").addClass('active');
		$(".vl-reference-manage").addClass('active');
		$(".allMenu").removeClass('active');
		$(".vl-art-code-details").addClass('active');
	} else if (splitsUrl == 'vl-sample-rejection-reasons.php' || splitsUrl == 'add-vl-sample-rejection-reasons.php' || splitsUrl == 'edit-vl-sample-rejection-reasons.php') {
		$(".manage").addClass('active');
		$(".vl-reference-manage").addClass('active');
		$(".allMenu").removeClass('active');
		$(".vl-sample-rejection-reasons").addClass('active');
	} else if (splitsUrl == 'vl-sample-type.php' || splitsUrl == 'add-vl-sample-type.php' || splitsUrl == 'edit-vl-sample-type.php') {
		$(".manage").addClass('active');
		$(".vl-reference-manage").addClass('active');
		$(".allMenu").removeClass('active');
		$(".vl-sample-type").addClass('active');
	} else if (splitsUrl == 'vl-test-reasons.php' || splitsUrl == 'add-vl-test-reasons.php' || splitsUrl == 'edit-vl-test-reasons.php') {
		$(".manage").addClass('active');
		$(".vl-reference-manage").addClass('active');
		$(".allMenu").removeClass('active');
		$(".vl-test-reasons").addClass('active');
	} else if (splitsUrl == 'vl-test-failure-reasons.php' || splitsUrl == 'add-vl-test-failure-reason.php' || splitsUrl == 'edit-vl-test-failure-reason.php') {
		$(".manage").addClass('active');
		$(".vl-reference-manage").addClass('active');
		$(".allMenu").removeClass('active');
		$(".vl-test-failure-reasons").addClass('active');
	} else if (splitsUrl == 'eid-sample-rejection-reasons.php' || splitsUrl == 'add-eid-sample-rejection-reasons.php' || splitsUrl == 'edit-eid-sample-rejection-reasons.php') {
		$(".manage").addClass('active');
		$(".eid-reference-manage").addClass('active');
		$(".allMenu").removeClass('active');
		$(".eid-sample-rejection-reasons").addClass('active');
	} else if (splitsUrl == 'eid-sample-type.php' || splitsUrl == 'add-eid-sample-type.php' || splitsUrl == 'edit-eid-sample-type.php') {
		$(".manage").addClass('active');
		$(".eid-reference-manage").addClass('active');
		$(".allMenu").removeClass('active');
		$(".eid-sample-type").addClass('active');
	} else if (splitsUrl == 'eid-test-reasons.php' || splitsUrl == 'add-eid-test-reasons.php' || splitsUrl == 'edit-eid-test-reasons.php') {
		$(".manage").addClass('active');
		$(".eid-reference-manage").addClass('active');
		$(".allMenu").removeClass('active');
		$(".eid-test-reasons").addClass('active');
	} else if (splitsUrl == 'eid-results.php' || splitsUrl == 'add-eid-results.php' || splitsUrl == 'edit-eid-results.php') {
		$(".manage").addClass('active');
		$(".eid-reference-manage").addClass('active');
		$(".allMenu").removeClass('active');
		$(".eid-results").addClass('active');
	} else if (splitsUrl == 'covid19-comorbidities.php' || splitsUrl == 'add-covid19-comorbidities.php' || splitsUrl == 'edit-covid19-comorbidities.php') {
		$(".manage").addClass('active');
		$(".covid19-reference-manage").addClass('active');
		$(".allMenu").removeClass('active');
		$(".covid19-comorbidities").addClass('active');
	} else if (splitsUrl == 'covid19-sample-rejection-reasons.php' || splitsUrl == 'add-covid19-sample-rejection-reason.php' || splitsUrl == 'edit-covid19-sample-rejection-reason.php') {
		$(".manage").addClass('active');
		$(".covid19-reference-manage").addClass('active');
		$(".allMenu").removeClass('active');
		$(".covid19-sample-rejection-reasons").addClass('active');
	} else if (splitsUrl == 'covid19-sample-type.php' || splitsUrl == 'add-covid19-sample-type.php' || splitsUrl == 'edit-covid19-sample-type.php') {
		$(".manage").addClass('active');
		$(".covid19-reference-manage").addClass('active');
		$(".allMenu").removeClass('active');
		$(".covid19-sample-type").addClass('active');
	} else if (splitsUrl == 'covid19-symptoms.php' || splitsUrl == 'add-covid19-symptoms.php' || splitsUrl == 'edit-covid19-symptoms.php') {
		$(".manage").addClass('active');
		$(".covid19-reference-manage").addClass('active');
		$(".allMenu").removeClass('active');
		$(".covid19-symptoms").addClass('active');
	} else if (splitsUrl == 'covid19-test-reasons.php' || splitsUrl == 'add-covid19-test-reasons.php' || splitsUrl == 'edit-covid19-test-reasons.php') {
		$(".manage").addClass('active');
		$(".covid19-reference-manage").addClass('active');
		$(".allMenu").removeClass('active');
		$(".covid19-test-reasons").addClass('active');
	} else if (splitsUrl == 'covid19-results.php') {
		$(".manage").addClass('active');
		$(".covid19-reference-manage").addClass('active');
		$(".allMenu").removeClass('active');
		$(".covid19-results").addClass('active');
	} else if (splitsUrl == 'covid19-qc-test-kits.php' || splitsUrl == 'add-covid19-qc-test-kit.php' || splitsUrl == 'edit-covid19-qc-test-kit.php') {
		$(".manage").addClass('active');
		$(".covid19-reference-manage").addClass('active');
		$(".allMenu").removeClass('active');
		$(".covid19-qc-test-kits").addClass('active');
	} else if (splitsUrl == 'hepatitis-requests.php' || splitsUrl == 'hepatitis-edit-request') {
		$(".hepatitisRequest").addClass('active');
		$(".allMenu").removeClass('active');
		$(".hepatitisRequestMenu").addClass('active');
	} else if (splitsUrl == 'hepatitis-add-request.php') {
		$(".hepatitisRequest").addClass('active');
		$(".allMenu").removeClass('active');
		$(".addHepatitisRequestMenu").addClass('active');
	} else if (splitsUrl == 'add-samples-from-manifest.php' && splitsUrlCheck[1] != 'generic-tests') {
		$(".hepatitisRequest").addClass('active');
		$(".allMenu").removeClass('active');
		$(".addSamplesFromManifestHepatitisMenu").addClass('active');
	} else if (splitsUrl == 'hepatitis-batches.php' || splitsUrl == 'hepatitis-add-batch.php' || splitsUrl == 'hepatitis-edit-batch.php') {
		$(".hepatitisRequest").addClass('active');
		$(".allMenu").removeClass('active');
		$(".hepatitisBatchCodeMenu").addClass('active');
	} else if (splitsUrl == 'add-samples-from-manifest.php' && splitsUrlCheck[1] != 'generic-tests') {
		$(".hepatitisRequest").addClass('active');
		$(".allMenu").removeClass('active');
		$(".addSamplesFromManifestHepatitisMenu").addClass('active');
	} else if (splitsUrl == 'hepatitis-manual-results.php' || splitsUrl == 'hepatitis-update-result.php') {
		$(".allMenu").removeClass('active');
		$(".hepatitisResults").addClass('active');
		$(".hepatitisResultsMenu").addClass('active');
	} else if (splitsUrl == 'hepatitis-failed-results.php') {
		$(".allMenu").removeClass('active');
		$(".hepatitisResults").addClass('active');
		$(".hepatitisFailedResultsMenu").addClass('active');
	} else if (splitsUrl == 'hepatitis-print-results.php') {
		$(".allMenu").removeClass('active');
		$(".hepatitisProgramMenu").addClass('active');
		$(".hepatitisPrintResults").addClass('active');
	} else if (splitsUrl == 'mail-hepatitis-results.php') {
		$(".allMenu").removeClass('active');
		$(".hepatitisResults").addClass('active');
		$(".hepatitisResultMailMenu").addClass('active');
	} else if (splitsUrl == 'hepatitis-result-status.php') {
		$(".allMenu").removeClass('active');
		$(".hepatitisResults").addClass('active');
		$(".hepatitisResultStatus").addClass('active');
	} else if (splitsUrl == 'hepatitis-comorbidities.php' || splitsUrl == 'add-hepatitis-comorbidities.php' || splitsUrl == 'edit-hepatitis-comorbidities.php') {
		$(".manage").addClass('active');
		$(".hepatitis-reference-manage").addClass('active');
		$(".allMenu").removeClass('active');
		$(".hepatitis-comorbidities").addClass('active');
	} else if (splitsUrl == 'hepatitis-sample-rejection-reasons.php' || splitsUrl == 'add-hepatitis-sample-rejection-reasons.php' || splitsUrl == 'edit-hepatitis-sample-rejection-reasons.php') {
		$(".manage").addClass('active');
		$(".hepatitis-reference-manage").addClass('active');
		$(".allMenu").removeClass('active');
		$(".hepatitis-sample-rejection-reasons").addClass('active');
	} else if (splitsUrl == 'hepatitis-sample-type.php' || splitsUrl == 'add-hepatitis-sample-type.php' || splitsUrl == 'edit-hepatitis-sample-type.php') {
		$(".manage").addClass('active');
		$(".hepatitis-reference-manage").addClass('active');
		$(".allMenu").removeClass('active');
		$(".hepatitis-sample-type").addClass('active');
	} else if (splitsUrl == 'hepatitis-test-reasons.php' || splitsUrl == 'add-hepatitis-test-reasons.php' || splitsUrl == 'edit-hepatitis-test-reasons.php') {
		$(".manage").addClass('active');
		$(".hepatitis-reference-manage").addClass('active');
		$(".allMenu").removeClass('active');
		$(".hepatitis-test-reasons").addClass('active');
	} else if (splitsUrl == 'hepatitis-risk-factors.php' || splitsUrl == 'add-hepatitis-risk-factors.php' || splitsUrl == 'edit-hepatitis-risk-factors.php') {
		$(".manage").addClass('active');
		$(".hepatitis-reference-manage").addClass('active');
		$(".allMenu").removeClass('active');
		$(".hepatitis-risk-factors").addClass('active');
	} else if (splitsUrl == 'hepatitis-results.php' || splitsUrl == 'add-hepatitis-results.php' || splitsUrl == 'edit-hepatitis-results.php') {
		$(".manage").addClass('active');
		$(".hepatitis-reference-manage").addClass('active');
		$(".allMenu").removeClass('active');
		$(".hepatitis-results").addClass('active');
	} else if (splitsUrl == 'hepatitis-sample-status.php') {
		$(".manage").removeClass('active');
		$(".hepatitisProgramMenu").addClass('active');
		$(".allMenu").removeClass('active');
		$(".hepatitisSampleStatus").addClass('active');
	} else if (splitsUrl == 'hepatitis-export-data.php') {
		$(".manage").removeClass('active');
		$(".hepatitisProgramMenu").addClass('active');
		$(".allMenu").removeClass('active');
		$(".hepatitisExportResult").addClass('active');
	} else if (splitsUrl == 'hepatitis-print-results.php') {
		$(".manage").removeClass('active');
		$(".hepatitisProgramMenu").addClass('active');
		$(".allMenu").removeClass('active');
		$(".hepatitisPrintResults").addClass('active');
	} else if (splitsUrl == 'hepatitis-sample-rejection-report.php') {
		$(".manage").removeClass('active');
		$(".hepatitisProgramMenu").addClass('active');
		$(".allMenu").removeClass('active');
		$(".hepatitisSampleRejectionReport").addClass('active');
	} else if (splitsUrl == 'hepatitis-clinic-report.php') {
		$(".manage").removeClass('active');
		$(".hepatitisProgramMenu").addClass('active');
		$(".allMenu").removeClass('active');
		$(".hepatitisClinicReportMenu").addClass('active');
	} else if (splitsUrl == 'hepatitis-testing-target-report.php') {
		$(".manage").removeClass('active');
		$(".hepatitisProgramMenu").addClass('active');
		$(".allMenu").removeClass('active');
		$(".hepatitisMonthlyThresholdReport").addClass('active');
	} else if (splitsUrl == 'tb-requests.php' || splitsUrl == 'tb-edit-request.php') {
		$(".allMenu").removeClass('active');
		$(".tbRequest").addClass('active');
		$(".tbRequestMenu").addClass('active');
	} else if (splitsUrl == 'tb-add-request.php') {
		$(".allMenu").removeClass('active');
		$(".tbRequest").addClass('active');
		$(".addTbRequestMenu").addClass('active');
	} else if (splitsUrl == 'tb-batches.php' || splitsUrl == 'tb-add-batch.php' || splitsUrl == 'tb-edit-batch.php' || splitsUrl == 'tb-add-batch-position.php' || splitsUrl == 'tb-edit-batch-position.php') {
		$(".allMenu").removeClass('active');
		$(".tbRequest").addClass('active');
		$(".tbBatchCodeMenu").addClass('active');
	} else if (splitsUrl == 'tb-manual-results.php' || splitsUrl == 'tb-update-result.php') {
		$(".allMenu").removeClass('active');
		$(".tbResults").addClass('active');
		$(".tbResultsMenu").addClass('active');
	} else if (splitsUrl == 'tb-result-status.php') {
		$(".allMenu").removeClass('active');
		$(".tbResults").addClass('active');
		$(".tbResultStatus").addClass('active');
	} else if (splitsUrl == 'tb-failed-results.php') {
		$(".allMenu").removeClass('active');
		$(".tbResults").addClass('active');
		$(".tbFailedResultsMenu").addClass('active');
	} else if (splitsUrl == 'addSamplesFromManifest.php' && splitsUrlCheck[1] == "tb") {
		$(".allMenu").removeClass('active');
		$(".tbRequest").addClass('active');
		$(".addSamplesFromManifestTbMenu").addClass('active');
	} else if (splitsUrl == 'tb-print-results.php') {
		$(".manage").removeClass('active');
		$(".tbProgramMenu").addClass('active');
		$(".allMenu").removeClass('active');
		$(".tbPrintResults").addClass('active');
	} else if (splitsUrl == 'tb-sample-status.php') {
		$(".manage").removeClass('active');
		$(".tbProgramMenu").addClass('active');
		$(".allMenu").removeClass('active');
		$(".tbSampleStatus").addClass('active');
	} else if (splitsUrl == 'tb-export-data.php') {
		$(".manage").removeClass('active');
		$(".tbProgramMenu").addClass('active');
		$(".allMenu").removeClass('active');
		$(".tbExportResult").addClass('active');
	} else if (splitsUrl == 'tb-sample-rejection-report.php') {
		$(".manage").removeClass('active');
		$(".tbProgramMenu").addClass('active');
		$(".allMenu").removeClass('active');
		$(".tbSampleRejectionReport").addClass('active');
	} else if (splitsUrl == 'tb-clinic-report.php') {
		$(".manage").removeClass('active');
		$(".tbProgramMenu").addClass('active');
		$(".allMenu").removeClass('active');
		$(".tbClinicReport").addClass('active');
	} else if (splitsUrl == 'tb-sample-rejection-reasons.php' || splitsUrl == 'add-tb-sample-rejection-reason.php' || splitsUrl == 'edit-tb-sample-rejection-reason.php') {
		$(".manage").addClass('active');
		$(".tb-reference-manage").addClass('active');
		$(".allMenu").removeClass('active');
		$(".tb-sample-rejection-reasons").addClass('active');
	} else if (splitsUrl == 'tb-sample-type.php' || splitsUrl == 'add-tb-sample-type.php' || splitsUrl == 'edit-tb-sample-type.php') {
		$(".manage").addClass('active');
		$(".tb-reference-manage").addClass('active');
		$(".allMenu").removeClass('active');
		$(".tb-reference").addClass('active');
	} else if (splitsUrl == 'tb-test-reasons.php' || splitsUrl == 'add-tb-test-reasons.php' || splitsUrl == 'edit-tb-test-reasons.php') {
		$(".manage").addClass('active');
		$(".tb-reference-manage").addClass('active');
		$(".allMenu").removeClass('active');
		$(".tb-test-reasons").addClass('active');
	} else if (splitsUrl == 'tb-results.php' || splitsUrl == 'add-tb-results.php' || splitsUrl == 'edit-tb-results.php') {
		$(".manage").addClass('active');
		$(".tb-reference-manage").addClass('active');
		$(".allMenu").removeClass('active');
		$(".tb-results").addClass('active');
	} else if (splitsUrl == 'test-type.php' || splitsUrl == 'add-test-type.php' || splitsUrl == 'edit-test-type.php') {
		$(".generic-reference-manage,.manage").addClass('active');
		$(".allMenu").removeClass('active');
		$(".testTypeConfigurationMenu").addClass('active');
	} else if (splitsUrl == 'generic-sample-type.php' || splitsUrl == 'generic-add-sample-type.php' || splitsUrl == 'generic-edit-sample-type.php') {
		$(".generic-reference-manage,.manage").addClass('active');
		$(".allMenu").removeClass('active');
		$(".genericSampleTypeMenu").addClass('active');
	} else if (splitsUrl == 'generic-testing-reason.php' || splitsUrl == 'generic-add-testing-reason.php' || splitsUrl == 'generic-edit-testing-reason.php') {
		$(".generic-reference-manage,.manage").addClass('active');
		$(".allMenu").removeClass('active');
		$(".genericTestingReasonMenu").addClass('active');
	} else if (splitsUrl == 'generic-symptoms.php' || splitsUrl == 'generic-add-symptoms.php' || splitsUrl == 'generic-edit-symptoms.php') {
		$(".generic-reference-manage,.manage").addClass('active');
		$(".allMenu").removeClass('active');
		$(".genericSymptomsMenu").addClass('active');
	} else if (splitsUrl == 'generic-sample-rejection-reasons.php' || splitsUrl == 'generic-add-rejection-reasons.php' || splitsUrl == 'generic-edit-rejection-reasons.php') {
		$(".generic-reference-manage,.manage").addClass('active');
		$(".allMenu").removeClass('active');
		$(".genericSampleRejectionReasonsMenu").addClass('active');
	} else if (splitsUrlCheck[1] == 'generic-tests' && (splitsUrl == 'view-requests.php' || splitsUrl == 'edit-request.php')) {
		$(".allMenu").removeClass('active');
		$(".generic-test-request-menu, .genericRequestMenu").addClass('active');
	} else if (splitsUrlCheck[1] == 'generic-tests' && splitsUrl == 'add-request.php') {
		$(".allMenu").removeClass('active');
		$(".generic-test-request-menu, .addGenericRequestMenu").addClass('active');
	} else if (splitsUrlCheck[1] == 'generic-tests' && splitsUrl == 'addSamplesFromManifest.php') {
		$(".allMenu").removeClass('active');
		$(".generic-test-request-menu, .addGenericSamplesFromManifestMenu").addClass('active');
	} else if (splitsUrlCheck[1] == 'generic-tests' && (splitsUrl == 'batch-code.php'  || splitsUrl == 'addBatch.php' || splitsUrl == 'editBatch.php' || splitsUrl == 'addBatchControlsPosition.php' || splitsUrl == 'editBatchControlsPosition.php')) {
		$(".allMenu").removeClass('active');
		$(".generic-test-request-menu, .batchGenericCodeMenu").addClass('active');
	} else if (splitsUrlCheck[1] == 'generic-tests' && splitsUrl == 'add-samples-from-manifest.php') {
		$(".allMenu").removeClass('active');
		$(".generic-test-request-menu, .addGenericSamplesFromManifestMenu").addClass('active');
	} else if (splitsUrlCheck[1] == 'generic-tests' && (splitsUrl == 'generic-test-results.php' || splitsUrl == 'update-generic-test-result.php')) {
		$(".allMenu").removeClass('active');
		$(".generic-test-results-menu, .genericTestResultMenu").addClass('active');
	} else if (splitsUrlCheck[1] == 'generic-tests' && splitsUrl == 'generic-failed-results.php') {
		$(".allMenu").removeClass('active');
		$(".generic-test-results-menu, .genericFailedResultMenu").addClass('active');
	} else if (splitsUrlCheck[1] == 'generic-tests' && splitsUrl == 'generic-result-approval.php') {
		$(".allMenu").removeClass('active');
		$(".generic-test-results-menu, .genericResultApprovalMenu").addClass('active');
	} else if (splitsUrlCheck[1] == 'generic-tests' && splitsUrl == 'generic-sample-status.php') {
		$(".allMenu").removeClass('active');
		$(".generic-program-menu, .genericStatusReportMenu").addClass('active');
	} else if (splitsUrlCheck[1] == 'generic-tests' && splitsUrl == 'generic-control-report.php') {
		$(".allMenu").removeClass('active');
		$(".generic-program-menu, .genericControlReport").addClass('active');
	} else if (splitsUrlCheck[1] == 'generic-tests' && splitsUrl == 'generic-export-data.php') {
		$(".allMenu").removeClass('active');
		$(".generic-program-menu, .genericExportMenu").addClass('active');
	} else if (splitsUrlCheck[1] == 'generic-tests' && splitsUrl == 'generic-print-result.php') {
		$(".allMenu").removeClass('active');
		$(".generic-program-menu, .genericPrintResultMenu").addClass('active');
	} else if (splitsUrlCheck[1] == 'generic-tests' && splitsUrl == 'generic-weekly-report.php') {
		$(".allMenu").removeClass('active');
		$(".generic-program-menu, .genericWeeklyReport").addClass('active');
	} else if (splitsUrlCheck[1] == 'generic-tests' && splitsUrl == 'sample-rejection-report.php') {
		$(".allMenu").removeClass('active');
		$(".generic-program-menu, .genericSampleRejectionReport").addClass('active');
	} else if (splitsUrlCheck[1] == 'generic-tests' && splitsUrl == 'generic-monitoring-report.php') {
		$(".allMenu").removeClass('active');
		$(".generic-program-menu, .genericMonitoringReport").addClass('active');
	} else if (splitsUrlCheck[1] == 'generic-tests' && splitsUrl == 'generic-monthly-threshold-report.php') {
		$(".allMenu").removeClass('active');
		$(".generic-program-menu, .genericMonthlyThresholdReport").addClass('active');
	} else {
		$(".allMenu").removeClass('active');
	}
	
	function screenshot(supportId, attached) {
		if (supportId != "" && attached == 'yes') {
			closeModal();
			html2canvas(document.querySelector("#capture")).then(canvas => {
				dataURL = canvas.toDataURL();
				$.blockUI();
				$.post("/support/saveScreenshot.php", {
						image: dataURL,
						supportId: supportId
					},
					function(data) {
						$.unblockUI();
						alert("<?= _("Thank you. Your message has been submitted."); ?>");
					});
			});
		} else {
			closeModal();
			$.blockUI();
			$.post("/support/saveScreenshot.php", {
					supportId: supportId
				},
				function(data) {
					$.unblockUI();
					alert("<?= _("Thank you. Your message has been submitted."); ?>");
				});
		}
	}
</script>
</body>

</html>