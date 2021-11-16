<footer class="main-footer">
	<small>This project is supported by the U.S. President’s Emergency Plan for AIDS Relief (PEPFAR) through the U.S. Centers for Disease Control and Prevention (CDC).</small>
	<small class="pull-right" style="font-weight:bold;">&nbsp;&nbsp;<?php echo "v" . VERSION; ?></small>
	<?php if (!empty($systemConfig['remoteURL']) && isset($_SESSION['userName']) && isset($_SESSION['instanceType']) && ($_SESSION['instanceType'] == 'vluser')) { ?>
		<div class="pull-right">
			<small><a href="javascript:forceRemoteSync();">Force Remote Sync</a>&nbsp;&nbsp;</small>
		</div>
	<?php
	}
	$lastSync = '';

	if (isset($_SESSION['privileges']) && in_array("sync-history.php", $_SESSION['privileges'])) {
		$syncHistory = "/common/reference/sync-history.php";
	} else {
		$syncHistory = "javascript:void(0);";
	}

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

<!--<script src="https://code.jquery.com/ui/1.11.4/jquery-ui.min.js"></script>-->

<script type="text/javascript" src="/assets/js/jquery-ui-timepicker-addon.js"></script>
<script type="text/javascript" src="/assets/js/js.cookie.js"></script>
<script src="/assets/js/select2.js"></script>
<!-- Bootstrap 3.3.6 -->
<script src="/assets/js/bootstrap.min.js"></script>
<!-- DataTables -->
<script src="/assets/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/assets/plugins/datatables/dataTables.bootstrap.min.js"></script>
<!-- AdminLTE App -->
<script src="/assets/js/app.min.js"></script>
<script src="/assets/js/deforayValidation.js"></script>
<script src="/assets/js/jquery.maskedinput.js"></script>
<script src="/assets/js/jquery.blockUI.js"></script>
<script src="/assets/js/moment.min.js"></script>

<script type="text/javascript">
	function setCrossLogin() {
		if (typeof(Storage) !== "undefined") {
			sessionStorage.setItem("crosslogin", "true");
		} else {
			alert("Your browser doesn't support the session!");
			sessionStorage.setItem("crosslogin", "false");
		}
	}
	<?php if (isset($_SESSION['instanceType']) && $_SESSION['instanceType'] == 'vluser') { ?>
		var remoteSync = true;
		var remoteUrl = '<?php echo $systemConfig['remoteURL']; ?>';

		function forceRemoteSync() {
			syncRemoteData();
		}

		function syncRemoteData() {
			if (!navigator.onLine) {
				alert('Please connect to internet to sync with VLSTS');
				return false;
			}

			if (remoteSync && remoteUrl != null && remoteUrl != '') {
				$.blockUI({
					message: '<h3>Preparing for VLSTS Remote sync.<br>Please wait...</h3>'
				});
				var jqxhr = $.ajax({
						url: "/remote/scheduled-jobs/syncCommonData.php",
					})
					.done(function(data) {
						//console.log(data);
						//alert( "success" );
					})
					.fail(function() {
						$.unblockUI();
						alert("Unable to do VLSTS Remote Sync. Please contact technical team for assistance.");
					})
					.always(function() {
						//alert( "complete" );
						$.unblockUI();
						syncRequests();
					});
			}
		}

		function syncRequests() {
			$.blockUI({
				message: '<h3>Trying to sync Test Requests<br>Please wait...</h3>'
			});

			if (remoteSync && remoteUrl != null && remoteUrl != '') {
				var jqxhr = $.ajax({
						url: "/remote/scheduled-jobs/syncRequests.php",
					})
					.done(function(data) {
						//console.log(data);
						//alert( "success" );
					})
					.fail(function() {
						$.unblockUI();
						alert("Unable to do VLSTS Remote Sync. Please contact technical team for assistance.");
					})
					.always(function() {
						$.unblockUI();
						syncResults();
					});
			}
		}


		function syncResults() {

			$.blockUI({
				message: '<h3>Trying to sync Test Results<br>Please wait...</h3>'
			});

			if (remoteSync && remoteUrl != null && remoteUrl != '') {
				var jqxhr = $.ajax({
						url: "/remote/scheduled-jobs/syncResults.php",
					})
					.done(function(data) {
						//console.log(data);
						//alert( "success" );
					})
					.fail(function() {
						$.unblockUI();
						alert("Unable to do VLSTS Remote Sync. Please contact technical team for assistance.");
					})
					.always(function() {
						$.unblockUI();
					});
			}
		}

		function syncCommon() {

			$.blockUI({
				message: '<h3>Trying to sync common data<br>Please wait...</h3>'
			});

			if (remoteSync && remoteUrl != null && remoteUrl != '') {
				var jqxhr = $.ajax({
						url: "/remote/scheduled-jobs/syncCommonData.php",
					})
					.done(function(data) {
						//console.log(data);
						//alert( "success" );
					})
					.fail(function() {
						$.unblockUI();
						alert("Unable to do VLSTS Remote Sync. Please contact technical team for assistance.");
					})
					.always(function() {
						$.unblockUI();
						syncRequests()
					});
			}
		}
	<?php } ?>
	let syncInterval = 60 * 60 * 1000 * 2 // 2 hours in ms
	$(document).ready(function() {
		<?php if (isset($_SESSION['instanceType']) && $_SESSION['instanceType'] == 'vluser' && !empty($systemConfig['remoteURL'])) { ?>




				(function getLastSyncDateTime() {
					let currentDateTime = new Date();
					$.ajax({
						url: '/remote/scheduled-jobs/getLastSyncTime.php',
						cache: false,
						success: function(lastSyncDateString) {
							if (lastSyncDateString != null && lastSyncDateString != undefined) {
								$('.sync-time').html(lastSyncDateString);
								$('.syncHistoryDiv').show();
								lastSyncDateString.replace("-", "/"); // We had to do this for Firefox 
								var lastSyncDate = new Date(lastSyncDateString);
								if ((currentDateTime - lastSyncDate) > syncInterval) {
									syncCommon();
								}
							} else {
								syncCommon();
							}
						},
						error: function(data) {}
					});
					setTimeout(getLastSyncDateTime, 30000);
				})();
		<?php } ?>



		<?php
		// Every 5 mins check connection if this is a local installation of VLSM and there is a remote server configured
		if (!empty($systemConfig['remoteURL']) && $_SESSION['instanceType'] == 'vluser') { ?>

				(function checkNetworkConnection() {
					$.ajax({
						url: '<?php echo rtrim($systemConfig['remoteURL'], "/"); ?>/vlsts-icons/favicon-16x16.png',
						cache: false,
						success: function(data) {
							$('.is-remote-server-reachable').css('background-color', '#4dbc3c');
						},
						error: function() {
							$('.is-remote-server-reachable').css('background-color', 'red');
						}
					});
					setTimeout(checkNetworkConnection, 3000);
				})();
		<?php } ?>

		<?php if (isset($_SESSION['alertMsg']) && trim($_SESSION['alertMsg']) != "") { ?> alert('<?php echo $_SESSION['alertMsg']; ?>');
		<?php $_SESSION['alertMsg'] = '';
			unset($_SESSION['alertMsg']);
		}
		if ($_SESSION['logged']) { ?> setCrossLogin();
		<?php }

		// if instance facility name is not set, let us show the modal

		if (empty($_SESSION['instanceFacilityName'])) { ?> showModal('/addInstanceDetails.php', 900, 420);
		<?php } ?>

		$('.daterange,#sampleCollectionDate,#sampleTestDate,#printSampleCollectionDate,#printSampleTestDate,#vlSampleCollectionDate,#eidSampleCollectionDate,#covid19SampleCollectionDate,#recencySampleCollectionDate,#hepatitisSampleCollectionDate,#hvlSampleTestDate,#rjtSampleTestDate,#noResultSampleTestDate,#printDate,#hvlSampleTestDate,#rjtSampleTestDate,#noResultSampleTestDate,#femaleSampleTestDate').on('cancel.daterangepicker', function(ev, picker) {
			$(this).val('');
		});
	});
	str = $(location).attr('pathname');
	splitsUrl = str.substr(str.lastIndexOf('/') + 1);
	splitsUrlCheck = str.split("/", 4);
	if (splitsUrl == 'users.php' || splitsUrl == 'addUser.php' || splitsUrl == 'editUser.php') {
		$(".access-control-menu,.manage").addClass('active');
		$(".allMenu").removeClass('active');
		$(".userMenu").addClass('active');
	} else if (splitsUrl == 'roles.php' || splitsUrl == 'editRole.php' || splitsUrl == 'addRole.php') {
		$(".access-control-menu,.manage").addClass('active');
		$(".allMenu").removeClass('active');
		$(".roleMenu").addClass('active');
	} else if (splitsUrl == 'facilities.php' || splitsUrl == 'addFacility.php' || splitsUrl == 'editFacility.php' || splitsUrl == 'mapTestType.php') {
		$(".facility-config-menu,.manage").addClass('active');
		$(".allMenu").removeClass('active');
		$(".facilityMenu").addClass('active');
	} else if (splitsUrl == 'facilityMap.php' || splitsUrl == 'addFacilityMap.php' || splitsUrl == 'editFacilityMap.php') {
		$(".manage").addClass('active');
		$(".allMenu").removeClass('active');
		$(".facilityMapMenu").addClass('active');
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
	} else if (splitsUrl == 'addSamplesFromManifest.php' && splitsUrlCheck[1] != "eid" && splitsUrlCheck[1] != "covid-19") {
		$(".request").addClass('active');
		$(".allMenu").removeClass('active');
		$(".addSamplesFromManifestMenu").addClass('active');
	} else if (splitsUrl == 'addVlRequestZm.php' || splitsUrl == 'editVlRequestZm.php') {
		$(".request").addClass('active');
		$(".allMenu").removeClass('active');
		$(".addVlRequestZmMenu").addClass('active');
	} else if (splitsUrl == 'batchcode.php' || splitsUrl == 'addBatch.php' || splitsUrl == 'editBatch.php' || splitsUrl == 'addBatchControlsPosition.php' || splitsUrl == 'editBatchControlsPosition.php') {
		$(".request").addClass('active');
		$(".allMenu").removeClass('active');
		$(".batchCodeMenu").addClass('active');
	} else if (splitsUrl == 'vlRequestMail.php' || splitsUrl == 'vlRequestMailConfirm.php') {
		$(".request").addClass('active');
		$(".allMenu").removeClass('active');
		$(".vlRequestMailMenu").addClass('active');
	} else if (splitsUrl == 'specimenReferralManifestList.php' || splitsUrl == 'addSpecimenReferralManifest.php' || splitsUrl == 'editSpecimenReferralManifest.php') {
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
	} else if (splitsUrl == 'vlResult.php') {
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
	} else if (splitsUrl == 'hepatitis-requests.php' || splitsUrl == 'hepatitis-edit-request') {
		$(".hepatitisRequest").addClass('active');
		$(".allMenu").removeClass('active');
		$(".hepatitisRequestMenu").addClass('active');
	} else if (splitsUrl == 'hepatitis-add-request.php') {
		$(".hepatitisRequest").addClass('active');
		$(".allMenu").removeClass('active');
		$(".addHepatitisRequestMenu").addClass('active');
	} else if (splitsUrl == 'add-samples-from-manifest.php') {
		$(".hepatitisRequest").addClass('active');
		$(".allMenu").removeClass('active');
		$(".addSamplesFromManifestHepatitisMenu").addClass('active');
	} else if (splitsUrl == 'hepatitis-batches.php' || splitsUrl == 'hepatitis-add-batch.php' || splitsUrl == 'hepatitis-edit-batch.php') {
		$(".hepatitisRequest").addClass('active');
		$(".allMenu").removeClass('active');
		$(".hepatitisBatchCodeMenu").addClass('active');
	} else if (splitsUrl == 'add-samples-from-manifest.php') {
		$(".hepatitisRequest").addClass('active');
		$(".allMenu").removeClass('active');
		$(".addSamplesFromManifestHepatitisMenu").addClass('active');
	} else if (splitsUrl == 'hepatitis-manual-results.php' || splitsUrl == 'hepatitis-update-result.php') {
		$(".allMenu").removeClass('active');
		$(".hepatitisResults").addClass('active');
		$(".hepatitisResultsMenu").addClass('active');
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
	} else if (splitsUrl == 'addSamplesFromManifest.php' && splitsUrlCheck[1] == "tb") {
		$(".allMenu").removeClass('active');
		$(".tbRequest").addClass('active');
		$(".addSamplesFromManifestTbMenu").addClass('active');
	} else {
		$(".allMenu").removeClass('active');
		$(".dashboardMenu").addClass('active');
	}

	function showModal(url, w, h) {
		showdefModal('dDiv', w, h);
		document.getElementById('dFrame').style.height = h + 'px';
		document.getElementById('dFrame').style.width = w + 'px';
		document.getElementById('dFrame').src = url;
	}

	function closeModal() {
		document.getElementById('dFrame').src = "";
		hidedefModal('dDiv');
	}
	jQuery(".checkNum").keydown(function(e) {
		// Allow: backspace, delete, tab, escape, enter and .
		if ($.inArray(e.keyCode, [46, 8, 9, 27, 13, 110, 190]) !== -1 ||
			// Allow: Ctrl+A
			(e.keyCode == 65 && e.ctrlKey === true) ||
			// Allow: home, end, left, right
			(e.keyCode >= 35 && e.keyCode <= 39)) {
			// let it happen, don't do anything
			return;
		}
		// Ensure that it is a number and stop the keypress
		if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
			e.preventDefault();
		}
	});
</script>
</body>

</html>