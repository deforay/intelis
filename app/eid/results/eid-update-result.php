<?php

use App\Registries\AppRegistry;
use App\Registries\ContainerRegistry;
use App\Services\EidService;
use App\Services\FacilitiesService;
use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Services\CommonService;


$title = "Enter EID Result";

require_once APPLICATION_PATH . '/header.php';

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());
$id = (isset($_GET['id'])) ? base64_decode((string) $_GET['id']) : null;




/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);
$healthFacilities = $facilitiesService->getHealthFacilities('eid');
$testingLabs = $facilitiesService->getTestingLabs('eid');

$userResult = $usersService->getActiveUsers($_SESSION['facilityMap']);
$userInfo = [];
foreach ($userResult as $user) {
	$userInfo[$user['user_id']] = ($user['user_name']);
}
// get instruments
$importQuery = "SELECT * FROM instruments WHERE status = 'active'";
$importResult = $db->query($importQuery);

$userQuery = "SELECT * FROM user_details WHERE `status` like 'active' ORDER BY user_name";
$userResult = $db->rawQuery($userQuery);

//sample rejection reason
$rejectionQuery = "SELECT * FROM r_eid_sample_rejection_reasons where rejection_reason_status = 'active'";
$rejectionResult = $db->rawQuery($rejectionQuery);
//rejection type
$rejectionTypeQuery = "SELECT DISTINCT rejection_type FROM r_eid_sample_rejection_reasons WHERE rejection_reason_status ='active'";
$rejectionTypeResult = $db->rawQuery($rejectionTypeQuery);
//sample status
$statusQuery = "SELECT * FROM r_sample_status where status = 'active' AND status_id NOT IN(9,8,6)";
$statusResult = $db->rawQuery($statusQuery);

$pdQuery = "SELECT * FROM geographical_divisions WHERE geo_parent = 0 and geo_status='active'";
$pdResult = $db->query($pdQuery);

$sQuery = "SELECT * from r_eid_sample_type where status='active'";
$sResult = $db->query($sQuery);

//get vl test reason list
$vlTestReasonQuery = "SELECT * from r_eid_test_reasons where test_reason_status = 'active'";
$vlTestReasonResult = $db->query($vlTestReasonQuery);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());
$id = (isset($_GET['id'])) ? base64_decode((string) $_GET['id']) : null;

$eidQuery = "SELECT * from form_eid where eid_id=?";
$eidInfo = $db->rawQueryOne($eidQuery, array($id));


/** @var EidService $eidService */
$eidService = ContainerRegistry::get(EidService::class);
$eidResults = $eidService->getEidResults();

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

//Funding source list
$fundingSourceList = $general->getFundingSources();

//Implementing partner list
$implementingPartnerList = $general->getImplementationPartners();

$disable = "disabled = 'disabled'";

$testPlatformResult = $general->getTestingPlatforms('eid');
foreach ($testPlatformResult as $row) {
	$testPlatformList[$row['machine_name']] = $row['machine_name'];
}

$iResultQuery = "SELECT * FROM instrument_machines";
$iResult = $db->rawQuery($iResultQuery);
$machine = [];
foreach ($iResult as $val) {
	$machine[$val['config_machine_id']] = $val['config_machine_name'];
}
if (isset($eidInfo['result_dispatched_datetime']) && trim((string) $eidInfo['result_dispatched_datetime']) != '' && $eidInfo['result_dispatched_datetime'] != '0000-00-00 00:00:00') {
	$expStr = explode(" ", (string) $eidInfo['result_dispatched_datetime']);
	$eidInfo['result_dispatched_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
	$eidInfo['result_dispatched_datetime'] = '';
}

//Recommended corrective actions
$condition = "status ='active' AND test_type='eid'";
$correctiveActions = $general->fetchDataFromTable('r_recommended_corrective_actions', $condition);

if (!empty($eidInfo['is_encrypted']) && $eidInfo['is_encrypted'] == 'yes') {
	$key = (string) $general->getGlobalConfig('key');
	$eidInfo['child_id'] = $general->crypto('decrypt', $eidInfo['child_id'], $key);
	$eidInfo['mother_id'] = $general->crypto('decrypt', $eidInfo['mother_id'], $key);

	if ($eidInfo['child_name'] != '') {
		$eidInfo['child_name'] = $general->crypto('decrypt', $eidInfo['child_name'], $key);
	}
	if ($eidInfo['mother_name'] != '') {
		$eidInfo['mother_name'] = $general->crypto('decrypt', $eidInfo['mother_name'], $key);
	}

	if ($eidInfo['child_surname'] != '') {
		$eidInfo['child_surname'] = $general->crypto('decrypt', $eidInfo['child_surname'], $key);
	}

	if ($eidInfo['mother_surname'] != '') {
		$eidInfo['mother_surname'] = $general->crypto('decrypt', $eidInfo['mother_surname'], $key);
	}
}

?>

<style>
	.disabledForm {
		background: #efefef;
	}

	:disabled,
	.disabledForm .input-group-addon {
		background: none !important;
		border: none !important;
	}

	.ui_tpicker_second_label {
		display: none !important;
	}

	.ui_tpicker_second_slider {
		display: none !important;
	}

	.ui_tpicker_millisec_label {
		display: none !important;
	}

	.ui_tpicker_millisec_slider {
		display: none !important;
	}

	.ui_tpicker_microsec_label {
		display: none !important;
	}

	.ui_tpicker_microsec_slider {
		display: none !important;
	}

	.ui_tpicker_timezone_label {
		display: none !important;
	}

	.ui_tpicker_timezone {
		display: none !important;
	}

	.ui_tpicker_time_input {
		width: 100%;
	}
</style>
<?php

$fileArray = array(
	COUNTRY\SOUTH_SUDAN => 'forms/update-southsudan-result.php',
	COUNTRY\SIERRA_LEONE => 'forms/update-sierraleone-result.php',
	COUNTRY\DRC => 'forms/update-drc-result.php',
	COUNTRY\CAMEROON => 'forms/update-cameroon-result.php',
	COUNTRY\PNG => 'forms/update-png-result.php',
	COUNTRY\WHO => 'forms/update-who-result.php',
	COUNTRY\RWANDA => 'forms/update-rwanda-result.php',
	COUNTRY\BURKINA_FASO => 'forms/update-burkina-faso-result.php'
);

require_once($fileArray[$arr['vl_form']]);


?>

<script>
	function updateSampleResult() {
		if ($('#isSampleRejected').val() == "yes") {
			$('.rejected').show();
			$('#sampleRejectionReason').addClass('isRequired');
			$('#rejectionDate').addClass('isRequired');
			$('#sampleTestedDateTime,#result').val('');
			$('#sampleTestedDateTime,#result').removeClass('isRequired');
			$(".result-optional").removeClass("isRequired");
			$('#reviewedBy').addClass('isRequired');
			$('#reviewedOn').addClass('isRequired');
			$('#approvedBy').addClass('isRequired');
			$('#approvedOnDateTime').addClass('isRequired');
		} else if ($('#isSampleRejected').val() == "no") {
			$('.rejected').hide();
			$('#sampleRejectionReason').removeClass('isRequired');
			$('#rejectionDate').removeClass('isRequired');
			$('#sampleTestedDateTime').addClass('isRequired');
			$('#result').addClass('isRequired');
			$('#testedBy').addClass('isRequired');
			$('#reviewedBy').addClass('isRequired');
			$('#reviewedOn').addClass('isRequired');
			$('#approvedBy').addClass('isRequired');
			$('#approvedOnDateTime').addClass('isRequired');
		} else {
			$('.rejected').hide();
			$('#sampleRejectionReason').removeClass('isRequired');
			$('#sampleTestedDateTime').removeClass('isRequired');
			$('#result').removeClass('isRequired');
			$('#testedBy').removeClass('isRequired');
			$(".result-optional").removeClass("isRequired");

			$('#reviewedBy').removeClass('isRequired');
			$('#reviewedOn').removeClass('isRequired');
			$('#approvedBy').removeClass('isRequired');
			$('#approvedOnDateTime').removeClass('isRequired');
		}

		if ($('#result').val() == "") {
			$('#sampleTestedDateTime').removeClass('isRequired');
			//$('#result').removeClass('isRequired');
		} else {
			$('#sampleTestedDateTime').addClass('isRequired');
			//$('#result').addClass('isRequired');
		}

	}

	$(document).ready(function() {
		$('#testedBy').select2({
			width: '100%',
			placeholder: "Select Tested By"
		});

		$('#approvedBy').select2({
			width: '100%',
			placeholder: "Select Approved By"
		});
		updateSampleResult();
		$("#isSampleRejected,#result").on("change", function() {
			updateSampleResult();
		});

		initDatePicker();
		initDateTimePicker();
		//$('.date').mask('<?= $_SESSION['jsDateFormatMask'] ?? '99-aaa-9999' ?>');
		//$('.dateTime').mask('<?= $_SESSION['jsDateFormatMask'] ?? '99-aaa-9999' ?> 99:99');

		$('.result-focus').change(function(e) {
			<?php if (isset($eidInfo['result']) && $eidInfo['result'] != "") { ?>
				var status = false;
				$(".result-focus").each(function(index) {
					if ($(this).val() != "") {
						status = true;
					}
				});
				if (status) {
					$('.change-reason').show();
					$('#reasonForChanging').addClass('isRequired');
				} else {
					$('.change-reason').hide();
					$('#reasonForChanging').removeClass('isRequired');
				}
			<?php } ?>
		});
	});
</script>


<?php
require_once APPLICATION_PATH . '/footer.php';
