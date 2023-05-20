<?php

use App\Registries\ContainerRegistry;
use App\Services\FacilitiesService;
use App\Services\UsersService;
use App\Services\GenericTestsService;
use App\Utilities\DateUtility;



require_once APPLICATION_PATH . '/header.php';

$sCode = $labFieldDisabled = '';



/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);
$genericTestsService = ContainerRegistry::get(GenericTestsService::class);

$healthFacilities = $facilitiesService->getHealthFacilities('generic-tests');
$testingLabs = $facilitiesService->getTestingLabs('generic-tests');

$reasonForFailure = $genericTestsService->getReasonForFailure();
$genericResults = $genericTestsService->getGenericResults();
if ($_SESSION['instanceType'] == 'remoteuser') {
	$labFieldDisabled = 'disabled="disabled"';
}

// Sanitize values before using them below
$_GET = array_map('htmlspecialchars', $_GET);
$id = (isset($_GET['id'])) ? base64_decode($_GET['id']) : null;


//get import config
$importQuery = "SELECT * FROM instruments WHERE status = 'active'";
$importResult = $db->query($importQuery);

$userResult = $usersService->getActiveUsers($_SESSION['facilityMap']);
$userInfo = [];
foreach ($userResult as $user) {
	$userInfo[$user['user_id']] = ($user['user_name']);
}
/* To get testing platform names */
$testPlatformResult = $general->getTestingPlatforms('generic-tests');
foreach ($testPlatformResult as $row) {
	$testPlatformList[$row['machine_name']] = $row['machine_name'];
}

//sample rejection reason
$rejectionQuery = "SELECT * FROM r_generic_sample_rejection_reasons where rejection_reason_status = 'active'";
$rejectionResult = $db->rawQuery($rejectionQuery);
//rejection type
$rejectionTypeQuery = "SELECT DISTINCT rejection_type FROM r_generic_sample_rejection_reasons WHERE rejection_reason_status ='active'";
$rejectionTypeResult = $db->rawQuery($rejectionTypeQuery);
//sample status
$statusQuery = "SELECT * FROM r_sample_status WHERE `status` = 'active' AND status_id NOT IN(9,8)";
$statusResult = $db->rawQuery($statusQuery);

$pdQuery = "SELECT * FROM geographical_divisions WHERE geo_parent = 0 and geo_status='active'";
$pdResult = $db->query($pdQuery);

$sQuery = "SELECT * FROM r_generic_sample_types WHERE sample_type_status='active'";
$sResult = $db->query($sQuery);

//get vl test reason list
$vlTestReasonQuery = "SELECT * FROM r_generic_test_reasons WHERE test_reason_status = 'active'";
$testReason = $db->query($vlTestReasonQuery);

$genericTestQuery = "SELECT * from generic_test_results where generic_id=? ORDER BY test_id ASC";
$genericTestInfo = $db->rawQuery($genericTestQuery, array($id));

//get suspected treatment failure at
$vlQuery = "SELECT * FROM form_generic WHERE sample_id=?";
$vlQueryInfo = $db->rawQueryOne($vlQuery, array($id));

if (isset($vlQueryInfo['patient_dob']) && trim($vlQueryInfo['patient_dob']) != '' && $vlQueryInfo['patient_dob'] != '0000-00-00') {
	$vlQueryInfo['patient_dob'] = DateUtility::humanReadableDateFormat($vlQueryInfo['patient_dob']);
} else {
	$vlQueryInfo['patient_dob'] = '';
}
if (isset($vlQueryInfo['sample_collection_date']) && trim($vlQueryInfo['sample_collection_date']) != '' && $vlQueryInfo['sample_collection_date'] != '0000-00-00 00:00:00') {
	$sampleCollectionDate = $vlQueryInfo['sample_collection_date'];
	$expStr = explode(" ", $vlQueryInfo['sample_collection_date']);
	$vlQueryInfo['sample_collection_date'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
	$sampleCollectionDate = '';
	$vlQueryInfo['sample_collection_date'] = DateUtility::getCurrentDateTime();
}

if (isset($vlQueryInfo['sample_dispatched_datetime']) && trim($vlQueryInfo['sample_dispatched_datetime']) != '' && $vlQueryInfo['sample_dispatched_datetime'] != '0000-00-00 00:00:00') {
	$expStr = explode(" ", $vlQueryInfo['sample_dispatched_datetime']);
	$vlQueryInfo['sample_dispatched_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
	$vlQueryInfo['sample_dispatched_datetime'] = '';
}

if (isset($vlQueryInfo['result_approved_datetime']) && trim($vlQueryInfo['result_approved_datetime']) != '' && $vlQueryInfo['result_approved_datetime'] != '0000-00-00 00:00:00') {
	$sampleCollectionDate = $vlQueryInfo['result_approved_datetime'];
	$expStr = explode(" ", $vlQueryInfo['result_approved_datetime']);
	$vlQueryInfo['result_approved_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
	$sampleCollectionDate = '';
	$vlQueryInfo['result_approved_datetime'] = '';
}

if (isset($vlQueryInfo['treatment_initiated_date']) && trim($vlQueryInfo['treatment_initiated_date']) != '' && $vlQueryInfo['treatment_initiated_date'] != '0000-00-00') {
	$vlQueryInfo['treatment_initiated_date'] = DateUtility::humanReadableDateFormat($vlQueryInfo['treatment_initiated_date']);
} else {
	$vlQueryInfo['treatment_initiated_date'] = '';
}

if (isset($vlQueryInfo['date_of_initiation_of_current_regimen']) && trim($vlQueryInfo['date_of_initiation_of_current_regimen']) != '' && $vlQueryInfo['date_of_initiation_of_current_regimen'] != '0000-00-00') {
	$vlQueryInfo['date_of_initiation_of_current_regimen'] = DateUtility::humanReadableDateFormat($vlQueryInfo['date_of_initiation_of_current_regimen']);
} else {
	$vlQueryInfo['date_of_initiation_of_current_regimen'] = '';
}

if (isset($vlQueryInfo['test_requested_on']) && trim($vlQueryInfo['test_requested_on']) != '' && $vlQueryInfo['test_requested_on'] != '0000-00-00') {
	$vlQueryInfo['test_requested_on'] = DateUtility::humanReadableDateFormat($vlQueryInfo['test_requested_on']);
} else {
	$vlQueryInfo['test_requested_on'] = '';
}


if (isset($vlQueryInfo['sample_received_at_hub_datetime']) && trim($vlQueryInfo['sample_received_at_hub_datetime']) != '' && $vlQueryInfo['sample_received_at_hub_datetime'] != '0000-00-00 00:00:00') {
	$expStr = explode(" ", $vlQueryInfo['sample_received_at_hub_datetime']);
	$vlQueryInfo['sample_received_at_hub_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
	$vlQueryInfo['sample_received_at_hub_datetime'] = '';
}


if (isset($vlQueryInfo['sample_received_at_testing_lab_datetime']) && trim($vlQueryInfo['sample_received_at_testing_lab_datetime']) != '' && $vlQueryInfo['sample_received_at_testing_lab_datetime'] != '0000-00-00 00:00:00') {
	$expStr = explode(" ", $vlQueryInfo['sample_received_at_testing_lab_datetime']);
	$vlQueryInfo['sample_received_at_testing_lab_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
	$vlQueryInfo['sample_received_at_testing_lab_datetime'] = '';
}


if (isset($vlQueryInfo['sample_tested_datetime']) && trim($vlQueryInfo['sample_tested_datetime']) != '' && $vlQueryInfo['sample_tested_datetime'] != '0000-00-00 00:00:00') {
	$expStr = explode(" ", $vlQueryInfo['sample_tested_datetime']);
	$vlQueryInfo['sample_tested_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
	$vlQueryInfo['sample_tested_datetime'] = '';
}

if (isset($vlQueryInfo['result_dispatched_datetime']) && trim($vlQueryInfo['result_dispatched_datetime']) != '' && $vlQueryInfo['result_dispatched_datetime'] != '0000-00-00 00:00:00') {
	$expStr = explode(" ", $vlQueryInfo['result_dispatched_datetime']);
	$vlQueryInfo['result_dispatched_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
	$vlQueryInfo['result_dispatched_datetime'] = '';
}

//Set Date of demand
if (isset($vlQueryInfo['date_test_ordered_by_physician']) && trim($vlQueryInfo['date_test_ordered_by_physician']) != '' && $vlQueryInfo['date_test_ordered_by_physician'] != '0000-00-00') {
	$vlQueryInfo['date_test_ordered_by_physician'] = DateUtility::humanReadableDateFormat($vlQueryInfo['date_test_ordered_by_physician']);
} else {
	$vlQueryInfo['date_test_ordered_by_physician'] = '';
}

//Set Dispatched From Clinic To Lab Date
if (isset($vlQueryInfo['sample_dispatched_datetime']) && trim($vlQueryInfo['sample_dispatched_datetime']) != '' && $vlQueryInfo['sample_dispatched_datetime'] != '0000-00-00 00:00:00') {
	$expStr = explode(" ", $vlQueryInfo['sample_dispatched_datetime']);
	$vlQueryInfo['sample_dispatched_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
	$vlQueryInfo['sample_dispatched_datetime'] = '';
}
//Set Date of result printed datetime
if (isset($vlQueryInfo['result_printed_datetime']) && trim($vlQueryInfo['result_printed_datetime']) != "" && $vlQueryInfo['result_printed_datetime'] != '0000-00-00 00:00:00') {
	$expStr = explode(" ", $vlQueryInfo['result_printed_datetime']);
	$vlQueryInfo['result_printed_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
	$vlQueryInfo['result_printed_datetime'] = '';
}
//reviewed datetime
if (isset($vlQueryInfo['result_reviewed_datetime']) && trim($vlQueryInfo['result_reviewed_datetime']) != '' && $vlQueryInfo['result_reviewed_datetime'] != null && $vlQueryInfo['result_reviewed_datetime'] != '0000-00-00 00:00:00') {
	$expStr = explode(" ", $vlQueryInfo['result_reviewed_datetime']);
	$vlQueryInfo['result_reviewed_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
	$vlQueryInfo['result_reviewed_datetime'] = '';
}


if ($vlQueryInfo['patient_first_name'] != '') {
	$patientFirstName = $general->crypto('doNothing', $vlQueryInfo['patient_first_name'], $vlQueryInfo['patient_id']);
} else {
	$patientFirstName = '';
}
if ($vlQueryInfo['patient_middle_name'] != '') {
	$patientMiddleName = $general->crypto('doNothing', $vlQueryInfo['patient_middle_name'], $vlQueryInfo['patient_id']);
} else {
	$patientMiddleName = '';
}
if ($vlQueryInfo['patient_last_name'] != '') {
	$patientLastName = $general->crypto('doNothing', $vlQueryInfo['patient_last_name'], $vlQueryInfo['patient_id']);
} else {
	$patientLastName = '';
}
$patientFullName = [];
if (trim($patientFirstName) != '') {
	$patientFullName[] = trim($patientFirstName);
}
if (trim($patientMiddleName) != '') {
	$patientFullName[] = trim($patientMiddleName);
}
if (trim($patientLastName) != '') {
	$patientFullName[] = trim($patientLastName);
}

if (!empty($patientFullName)) {
	$patientFullName = implode(" ", $patientFullName);
} else {
	$patientFullName = '';
}


?>
<style>
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

/*
if ($arr['vl_form'] == 1) {
     require('forms/edit-southsudan.php');
} else if ($arr['vl_form'] == 2) {
     require('forms/edit-sierraleone.php');
} else if ($arr['vl_form'] == 3) {
     require('forms/edit-drc.php');
} else if ($arr['vl_form'] == 4) {
     //require('forms/edit-zambia.php');
} else if ($arr['vl_form'] == 5) {
     require('forms/edit-png.php');
} else if ($arr['vl_form'] == 6) {
     require('forms/edit-who.php');
} else if ($arr['vl_form'] == 7) {
     require('forms/edit-rwanda.php');
} else if ($arr['vl_form'] == 8) {
     require('forms/edit-angola.php');
}*/




//Funding source list
$fundingSourceQry = "SELECT * FROM r_funding_sources WHERE funding_source_status='active' ORDER BY funding_source_name ASC";
$fundingSourceList = $db->query($fundingSourceQry);
//Implementing partner list
$implementingPartnerQry = "SELECT * FROM r_implementation_partners WHERE i_partner_status='active' ORDER BY i_partner_name ASC";
$implementingPartnerList = $db->query($implementingPartnerQry);

$lResult = $facilitiesService->getTestingLabs('vl', true, true);

if ($arr['sample_code'] == 'auto' || $arr['sample_code'] == 'alphanumeric') {
	$sampleClass = '';
	$maxLength = '';
	if ($arr['max_length'] != '' && $arr['sample_code'] == 'alphanumeric') {
		$maxLength = $arr['max_length'];
		$maxLength = "maxlength=" . $maxLength;
	}
} else {
	$sampleClass = '';
	$maxLength = '';
	if ($arr['max_length'] != '') {
		$maxLength = $arr['max_length'];
		$maxLength = "maxlength=" . $maxLength;
	}
}
//check remote user
$pdQuery = "SELECT * FROM geographical_divisions WHERE geo_parent = 0 and geo_status='active'";
if ($_SESSION['instanceType'] == 'remoteuser') {
	$sampleCode = 'remote_sample_code';
	if (!empty($vlQueryInfo['remote_sample']) && $vlQueryInfo['remote_sample'] == 'yes') {
		$sampleCode = 'remote_sample_code';
	} else {
		$sampleCode = 'sample_code';
	}
} else {
	$sampleCode = 'sample_code';
}
//check user exists in user_facility_map table
$chkUserFcMapQry = "SELECT user_id FROM user_facility_map WHERE user_id='" . $_SESSION['userId'] . "'";
$chkUserFcMapResult = $db->query($chkUserFcMapQry);
if ($chkUserFcMapResult) {
	$pdQuery = "SELECT DISTINCT gd.geo_name,gd.geo_id,gd.geo_code FROM geographical_divisions as gd JOIN facility_details as fd ON fd.facility_state_id=gd.geo_id JOIN user_facility_map as vlfm ON vlfm.facility_id=fd.facility_id WHERE gd.geo_parent = 0 AND gd.geo_status='active' AND vlfm.user_id='" . $_SESSION['userId'] . "'";
}

$pdResult = $db->query($pdQuery);
$province = "<option value=''> -- Select -- </option>";
foreach ($pdResult as $provinceName) {
	$province .= "<option value='" . $provinceName['geo_name'] . "##" . $provinceName['geo_id'] . "'>" . ($provinceName['geo_name']) . "</option>";
}

$facility = $general->generateSelectOptions($healthFacilities, $vlQueryInfo['facility_id'], '-- Select --');

//regimen heading
$artRegimenQuery = "SELECT DISTINCT headings FROM r_vl_art_regimen";
$artRegimenResult = $db->rawQuery($artRegimenQuery);
$aQuery = "SELECT * from r_vl_art_regimen where art_status ='active'";
$aResult = $db->query($aQuery);
//facility details
if (isset($vlQueryInfo['facility_id']) && $vlQueryInfo['facility_id'] > 0) {
	$facilityQuery = "SELECT * FROM facility_details where facility_id= ? AND status='active'";
	$facilityResult = $db->rawQuery($facilityQuery, array($vlQueryInfo['facility_id']));
}
if (!isset($facilityResult[0]['facility_code'])) {
	$facilityResult[0]['facility_code'] = '';
}
if (!isset($facilityResult[0]['facility_mobile_numbers'])) {
	$facilityResult[0]['facility_mobile_numbers'] = '';
}
if (!isset($facilityResult[0]['contact_person'])) {
	$facilityResult[0]['contact_person'] = '';
}
if (!isset($facilityResult[0]['facility_emails'])) {
	$facilityResult[0]['facility_emails'] = '';
}
if (!isset($facilityResult[0]['facility_state'])) {
	$facilityResult[0]['facility_state'] = '';
}
if (!isset($facilityResult[0]['facility_district'])) {
	$facilityResult[0]['facility_district'] = '';
}
//set reason for changes history
$rch = '';
$allChange = [];
if (isset($vlQueryInfo['reason_for_vl_result_changes']) && $vlQueryInfo['reason_for_vl_result_changes'] != '' && $vlQueryInfo['reason_for_vl_result_changes'] != null) {
	$allChange = json_decode($vlQueryInfo['reason_for_vl_result_changes'], true);
	if (!empty($allChange)) {
		$rch .= '<h4>Result Changes History</h4>';
		$rch .= '<table style="width:100%;">';
		$rch .= '<thead><tr style="border-bottom:2px solid #d3d3d3;"><th style="width:20%;">USER</th><th style="width:60%;">MESSAGE</th><th style="width:20%;text-align:center;">DATE</th></tr></thead>';
		$rch .= '<tbody>';
		$allChange = array_reverse($allChange);
		foreach ($allChange as $change) {
			$usrQuery = "SELECT user_name FROM user_details where user_id='" . $change['usr'] . "'";
			$usrResult = $db->rawQuery($usrQuery);
			$name = '';
			if (isset($usrResult[0]['user_name'])) {
				$name = ($usrResult[0]['user_name']);
			}
			$expStr = explode(" ", $change['dtime']);
			$changedDate = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
			$rch .= '<tr><td>' . $name . '</td><td>' . ($change['msg']) . '</td><td style="text-align:center;">' . $changedDate . '</td></tr>';
		}
		$rch .= '</tbody>';
		$rch .= '</table>';
	}
}

//var_dump($vlQueryInfo['sample_received_at_hub_datetime']);die;
$isGeneXpert = !empty($vlQueryInfo['vl_test_platform']) && (strcasecmp($vlQueryInfo['vl_test_platform'], "genexpert") === 0);

if ($isGeneXpert === true && !empty($vlQueryInfo['result_value_hiv_detection']) && !empty($vlQueryInfo['result'])) {
	$vlQueryInfo['result'] = trim(str_ireplace($vlQueryInfo['result_value_hiv_detection'], "", $vlQueryInfo['result']));
} else if ($isGeneXpert === true && !empty($vlQueryInfo['result'])) {

	$vlQueryInfo['result_value_hiv_detection'] = null;

	$hivDetectedStringsToSearch = [
		'HIV-1 Detected',
		'HIV 1 Detected',
		'HIV1 Detected',
		'HIV 1Detected',
		'HIV1Detected',
		'HIV Detected',
		'HIVDetected',
	];

	$hivNotDetectedStringsToSearch = [
		'HIV-1 Not Detected',
		'HIV-1 NotDetected',
		'HIV-1Not Detected',
		'HIV 1 Not Detected',
		'HIV1 Not Detected',
		'HIV 1Not Detected',
		'HIV1Not Detected',
		'HIV1NotDetected',
		'HIV1 NotDetected',
		'HIV 1NotDetected',
		'HIV Not Detected',
		'HIVNotDetected',
	];

	$detectedMatching = $general->checkIfStringExists($vlQueryInfo['result'], $hivDetectedStringsToSearch);
	if ($detectedMatching !== false) {
		$vlQueryInfo['result'] = trim(str_ireplace($detectedMatching, "", $vlQueryInfo['result']));
		$vlQueryInfo['result_value_hiv_detection'] = "HIV-1 Detected";
	} else {
		$notDetectedMatching = $general->checkIfStringExists($vlQueryInfo['result'], $hivNotDetectedStringsToSearch);
		if ($notDetectedMatching !== false) {
			$vlQueryInfo['result'] = trim(str_ireplace($notDetectedMatching, "", $vlQueryInfo['result']));
			$vlQueryInfo['result_value_hiv_detection'] = "HIV-1 Not Detected";
		}
	}
}

$testTypeQuery = "SELECT * FROM r_test_types where test_status='active' ORDER BY test_standard_name ASC";
$testTypeResult = $db->rawQuery($testTypeQuery);

$testTypeForm = json_decode($vlQueryInfo['test_type_form'], true);
?>
<style>
	.table>tbody>tr>td {
		border-top: none;
	}

	.form-control {
		width: 100% !important;
	}

	.row {
		margin-top: 6px;
	}

	#sampleCode {
		background-color: #fff;
	}
</style>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><em class="fa-solid fa-pen-to-square"></em> LABORATORY REQUEST FORM </h1>
		<ol class="breadcrumb">
			<li><a href="/dashboard/index.php"><em class="fa-solid fa-chart-pie"></em> Home</a></li>
			<li class="active">Edit Request</li>
		</ol>
	</section>
	<?php
	//print_r(array_column($vlTestReasonResult, 'last_name')$oneDimensionalArray = array_map('current', $vlTestReasonResult));die;
	?>
	<!-- Main content -->
	<section class="content">
		<div class="box box-default">
			<div class="box-header with-border">
				<div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span> indicates required
					field &nbsp;</div>
			</div>
			<div class="box-body">
				<!-- form start -->
				<form class="form-inline" method="post" name="vlRequestFormRwd" id="vlRequestFormRwd" autocomplete="off" action="edit-request-helper.php">
					<div class="box-body">
						<div class="box box-primary">
							<div class="box-header with-border">
								<h3 class="box-title">Clinic Information: (To be filled by requesting Clinican/Nurse)
								</h3>
							</div>
							<div class="row">
								<div class="col-xs-4 col-md-4">
									<div class="form-group">
										<label for="testType">Test Type</label>
										<select class="form-control" name="testType" id="testType" title="Please choose test type" style="width:100%;" onchange="getTestTypeForm()">
											<option value=""> -- Select -- </option>
											<?php foreach ($testTypeResult as $testType) { ?>
												<option value="<?php echo $testType['test_type_id'] ?>" <?php echo ($vlQueryInfo['test_type'] == $testType['test_type_id']) ? "selected='selected'" : "" ?>>
													<?php echo $testType['test_standard_name'] . ' (' . $testType['test_loinc_code'] . ')' ?>
												</option>
											<?php } ?>
										</select>
									</div>
								</div>
							</div>
							<div class="box-body requestForm" style="display:none;">
								<div class="row">
									<div class="col-xs-4 col-md-4">
										<div class="form-group">
											<label for="sampleCode">Sample ID <span class="mandatory">*</span></label>
											<input type="text" class="form-control isRequired <?php echo $sampleClass; ?>" id="sampleCode" name="sampleCode" <?php echo $maxLength; ?> placeholder="Enter Sample ID" readonly="readonly" title="Please enter sample id" value="<?php echo ($sCode != '') ? $sCode : $vlQueryInfo[$sampleCode]; ?>" style="width:100%;" onchange="checkSampleNameValidation('form_generic','<?php echo $sampleCode; ?>',this.id,'<?php echo "sample_id##" . $vlQueryInfo["sample_id"]; ?>','This sample number already exists.Try another number',null)" />
											<input type="hidden" name="sampleCodeCol" value="<?= htmlspecialchars($vlQueryInfo['sample_code']); ?>" style="width:100%;">
										</div>
									</div>
									<div class="col-xs-4 col-md-4">
										<div class="form-group">
											<label for="sampleReordered">
												<input type="checkbox" class="" id="sampleReordered" name="sampleReordered" value="yes" <?php echo (trim($vlQueryInfo['sample_reordered']) == 'yes') ? 'checked="checked"' : '' ?> title="Please indicate if this is a reordered sample"> Sample
												Reordered
											</label>
										</div>
									</div>


								</div>
								<div class="row">
									<div class="col-xs-4 col-md-4">
										<div class="form-group">
											<label for="province">State/Province <span class="mandatory">*</span></label>
											<select class="form-control isRequired" name="province" id="province" title="Please choose state" style="width:100%;" onchange="getProvinceDistricts(this);">
												<?php echo $province; ?>
											</select>
										</div>
									</div>
									<div class="col-xs-4 col-md-4">
										<div class="form-group">
											<label for="district">District/County <span class="mandatory">*</span></label>
											<select class="form-control isRequired" name="district" id="district" title="Please choose county" style="width:100%;" onchange="getFacilities(this);">
												<option value=""> -- Select -- </option>
											</select>
										</div>
									</div>
									<div class="col-xs-4 col-md-4">
										<div class="form-group">
											<label for="fName">Clinic/Health Center <span class="mandatory">*</span></label>
											<select class="form-control isRequired" id="fName" name="fName" title="Please select clinic/health center name" style="width:100%;" onchange="fillFacilityDetails(this);">

												<?= $facility; ?>
											</select>
										</div>
									</div>
									<div class="col-xs-3 col-md-3" style="display:none;">
										<div class="form-group">
											<label for="fCode">Clinic/Health Center Code </label>
											<input type="text" class="form-control" style="width:100%;" name="fCode" id="fCode" placeholder="Clinic/Health Center Code" title="Please enter clinic/health center code" value="<?php echo $facilityResult[0]['facility_code']; ?>">
										</div>
									</div>
								</div>
								<div class="row facilityDetails" style="display:<?php echo (trim($facilityResult[0]['facility_emails']) != '' || trim($facilityResult[0]['facility_mobile_numbers']) != '' || trim($facilityResult[0]['contact_person']) != '') ? '' : 'none'; ?>;">
									<div class="col-xs-2 col-md-2 femails" style="display:<?php echo (trim($facilityResult[0]['facility_emails']) != '') ? '' : 'none'; ?>;">
										<strong>Clinic Email(s)</strong>
									</div>
									<div class="col-xs-2 col-md-2 femails facilityEmails" style="display:<?php echo (trim($facilityResult[0]['facility_emails']) != '') ? '' : 'none'; ?>;">
										<?php echo $facilityResult[0]['facility_emails']; ?></div>
									<div class="col-xs-2 col-md-2 fmobileNumbers" style="display:<?php echo (trim($facilityResult[0]['facility_mobile_numbers']) != '') ? '' : 'none'; ?>;">
										<strong>Clinic Mobile No.(s)</strong>
									</div>
									<div class="col-xs-2 col-md-2 fmobileNumbers facilityMobileNumbers" style="display:<?php echo (trim($facilityResult[0]['facility_mobile_numbers']) != '') ? '' : 'none'; ?>;">
										<?php echo $facilityResult[0]['facility_mobile_numbers']; ?></div>
									<div class="col-xs-2 col-md-2 fContactPerson" style="display:<?php echo (trim($facilityResult[0]['contact_person']) != '') ? '' : 'none'; ?>;">
										<strong>Clinic Contact Person -</strong>
									</div>
									<div class="col-xs-2 col-md-2 fContactPerson facilityContactPerson" style="display:<?php echo (trim($facilityResult[0]['contact_person']) != '') ? '' : 'none'; ?>;">
										<?php echo ($facilityResult[0]['contact_person']); ?></div>
								</div>


								<div class="row">
									<div class="col-xs-4 col-md-4">
										<div class="form-group">
											<label for="implementingPartner">Implementing Partner</label>
											<select class="form-control" name="implementingPartner" id="implementingPartner" title="Please choose implementing partner" style="width:100%;">
												<option value=""> -- Select -- </option>
												<?php
												foreach ($implementingPartnerList as $implementingPartner) {
												?>
													<option value="<?php echo base64_encode($implementingPartner['i_partner_id']); ?>" <?php echo ($implementingPartner['i_partner_id'] == $vlQueryInfo['implementing_partner']) ? 'selected="selected"' : ''; ?>>
														<?php echo ($implementingPartner['i_partner_name']); ?></option>
												<?php } ?>
											</select>
										</div>
									</div>
									<div class="col-xs-4 col-md-4">
										<div class="form-group">
											<label for="fundingSource">Funding Source</label>
											<select class="form-control" name="fundingSource" id="fundingSource" title="Please choose implementing partner" style="width:100%;">
												<option value=""> -- Select -- </option>
												<?php
												foreach ($fundingSourceList as $fundingSource) {
												?>
													<option value="<?php echo base64_encode($fundingSource['funding_source_id']); ?>" <?php echo ($fundingSource['funding_source_id'] == $vlQueryInfo['funding_source']) ? 'selected="selected"' : ''; ?>>
														<?php echo ($fundingSource['funding_source_name']); ?></option>
												<?php } ?>
											</select>
										</div>
									</div>

									<div class="col-md-4 col-md-4">
										<label for="labId">Testing Lab <span class="mandatory">*</span></label>
										<select name="labId" id="labId" class="form-control isRequired" title="Please choose lab" onchange="autoFillFocalDetails();" style="width:100%;">
											<option value="">-- Select --</option>
											<?php foreach ($lResult as $labName) { ?>
												<option data-focalperson="<?php echo $labName['contact_person']; ?>" data-focalphone="<?php echo $labName['facility_mobile_numbers']; ?>" value="<?php echo $labName['facility_id']; ?>" <?php echo (isset($vlQueryInfo['lab_id']) && $vlQueryInfo['lab_id'] == $labName['facility_id']) ? 'selected="selected"' : ''; ?>>
													<?php echo ($labName['facility_name']); ?></option>
											<?php } ?>
										</select>
									</div>
								</div>
								<div class="row" id="clinicDynamicForm"></div>
							</div>
						</div>
						<div class="box box-primary requestForm" style="display:none;">
							<div class="box-header with-border">
								<h3 class="box-title">Patient Information</h3>
							</div>
							<div class="box-body">
								<div class="row">
									<div class="col-xs-3 col-md-3">
										<div class="form-group">
											<label for="artNo">Patient ID <span class="mandatory">*</span></label>
											<input type="text" name="artNo" id="artNo" class="form-control isRequired" placeholder="Enter Patient ID" title="Enter patient id" value="<?= htmlspecialchars($vlQueryInfo['patient_id']); ?>" />
										</div>
									</div>
									<div class="col-xs-3 col-md-3">
										<div class="form-group">
											<label for="dob">Date of Birth </label>
											<input type="text" name="dob" id="dob" class="form-control date" placeholder="Enter DOB" title="Enter dob" value="<?= htmlspecialchars($vlQueryInfo['patient_dob']); ?>" onchange="getAge();checkARTInitiationDate();" />
										</div>
									</div>
									<div class="col-xs-3 col-md-3">
										<div class="form-group">
											<label for="ageInYears">If DOB unknown, Age in Years </label>
											<input type="text" name="ageInYears" id="ageInYears" class="form-control forceNumeric" maxlength="3" placeholder="Age in Years" title="Enter age in years" value="<?= htmlspecialchars($vlQueryInfo['patient_age_in_years']); ?>" />
										</div>
									</div>
									<div class="col-xs-3 col-md-3">
										<div class="form-group">
											<label for="ageInMonths">If Age
												< 1, Age in Months </label> <input type="text" name="ageInMonths" id="ageInMonths" class="form-control forceNumeric" maxlength="2" placeholder="Age in Month" title="Enter age in months" value="<?= htmlspecialchars($vlQueryInfo['patient_age_in_months']); ?>" />
										</div>
									</div>
								</div>
								<div class="row">
									<div class="col-xs-3 col-md-3">
										<div class="form-group">
											<label for="patientFirstName">Patient Name (First Name, Last Name) <span class="mandatory">*</span></label>
											<input type="text" name="patientFirstName" id="patientFirstName" class="form-control isRequired" placeholder="Enter Patient Name" title="Enter patient name" value="<?php echo $patientFullName; ?>" />
										</div>
									</div>
									<div class="col-xs-3 col-md-3">
										<div class="form-group">
											<label for="gender">Gender</label><br>
											<label class="radio-inline" style="margin-left:0px;">
												<input type="radio" class="" id="genderMale" name="gender" value="male" title="Please check gender" <?php echo ($vlQueryInfo['patient_gender'] == 'male') ? "checked='checked'" : "" ?>>
												Male
											</label>
											<label class="radio-inline" style="margin-left:0px;">
												<input type="radio" class="" id="genderFemale" name="gender" value="female" title="Please check gender" <?php echo ($vlQueryInfo['patient_gender'] == 'female') ? "checked='checked'" : "" ?>>
												Female
											</label>
											<label class="radio-inline" style="margin-left:0px;">
												<input type="radio" class="" id="genderNotRecorded" name="gender" value="not_recorded" title="Please check gender" <?php echo ($vlQueryInfo['patient_gender'] == 'not_recorded') ? "checked='checked'" : "" ?>>Not
												Recorded
											</label>
										</div>
									</div>
									<div class="col-xs-3 col-md-3">
										<div class="form-group">
											<label for="gender">Patient consent to receive SMS?</label><br>
											<label class="radio-inline" style="margin-left:0px;">
												<input type="radio" class="" id="receivesmsYes" name="receiveSms" value="yes" title="Patient consent to receive SMS" onclick="checkPatientReceivesms(this.value);" <?php echo ($vlQueryInfo['consent_to_receive_sms'] == 'yes') ? "checked='checked'" : "" ?>>
												Yes
											</label>
											<label class="radio-inline" style="margin-left:0px;">
												<input type="radio" class="" id="receivesmsNo" name="receiveSms" value="no" title="Patient consent to receive SMS" onclick="checkPatientReceivesms(this.value);" <?php echo ($vlQueryInfo['consent_to_receive_sms'] == 'no') ? "checked='checked'" : "" ?>>
												No
											</label>
										</div>
									</div>
									<div class="col-xs-3 col-md-3">
										<div class="form-group">
											<label for="patientPhoneNumber">Phone Number</label>
											<input type="text" name="patientPhoneNumber" id="patientPhoneNumber" class="form-control forceNumeric" maxlength="15" placeholder="Enter Phone Number" title="Enter phone number" value="<?= htmlspecialchars($vlQueryInfo['patient_mobile_number']); ?>" />
										</div>
									</div>
								</div>
								<div class="row ">
									<div class="col-xs-3 col-md-3 femaleSection" style="display:<?php echo ($vlQueryInfo['patient_gender'] == 'female' || $vlQueryInfo['patient_gender'] == '' || $vlQueryInfo['patient_gender'] == null) ? "" : "none" ?>" ;>
										<div class="form-group">
											<label for="patientPregnant">Is Patient Pregnant? </label><br>
											<label class="radio-inline">
												<input type="radio" class="" id="pregYes" name="patientPregnant" value="yes" title="Please check one" <?php echo ($vlQueryInfo['is_patient_pregnant'] == 'yes') ? "checked='checked'" : "" ?>>
												Yes
											</label>
											<label class="radio-inline">
												<input type="radio" class="" id="pregNo" name="patientPregnant" value="no" <?php echo ($vlQueryInfo['is_patient_pregnant'] == 'no') ? "checked='checked'" : "" ?>>
												No
											</label>
										</div>
									</div>
									<div class="col-xs-3 col-md-3 femaleSection" style="display:<?php echo ($vlQueryInfo['patient_gender'] == 'female' || $vlQueryInfo['patient_gender'] == '' || $vlQueryInfo['patient_gender'] == null) ? "" : "none" ?>" ;>
										<div class="form-group">
											<label for="breastfeeding">Is Patient Breastfeeding? </label><br>
											<label class="radio-inline">
												<input type="radio" class="" id="breastfeedingYes" name="breastfeeding" value="yes" title="Please check one" <?php echo ($vlQueryInfo['is_patient_breastfeeding'] == 'yes') ? "checked='checked'" : "" ?>>
												Yes
											</label>
											<label class="radio-inline">
												<input type="radio" class="" id="breastfeedingNo" name="breastfeeding" value="no" <?php echo ($vlQueryInfo['is_patient_breastfeeding'] == 'no') ? "checked='checked'" : "" ?>>
												No
											</label>
										</div>
									</div>
									<div class="col-xs-3 col-md-3" style="display:none;">
										<div class="form-group">
											<label for="">How long has this patient been on treatment ? </label>
											<input type="text" class="form-control" id="treatPeriod" name="treatPeriod" placeholder="Enter Treatment Period" title="Please enter how long has this patient been on treatment" value="<?= htmlspecialchars($vlQueryInfo['treatment_initiation']); ?>" />
										</div>
									</div>
								</div>
								<div class="row" id="patientDynamicForm"></div>
							</div>
							<div class="box box-primary">
								<div class="box-header with-border">
									<h3 class="box-title">Sample Information</h3>
								</div>
								<div class="box-body">
									<div class="row">
										<div class="col-xs-3 col-md-3">
											<div class="form-group">
												<label for="">Date of Sample Collection <span class="mandatory">*</span></label>
												<input type="text" class="form-control isRequired dateTime" style="width:100%;" name="sampleCollectionDate" id="sampleCollectionDate" placeholder="Sample Collection Date" title="Please select sample collection date" value="<?php echo $vlQueryInfo['sample_collection_date']; ?>" onchange="checkSampleReceviedDate();checkSampleTestingDate();">
											</div>
										</div>
										<div class="col-xs-3 col-md-3">
											<div class="form-group">
												<label for="">Sample Dispatched On <span class="mandatory">*</span></label>
												<input type="text" class="form-control isRequired dateTime" style="width:100%;" name="sampleDispatchedDate" id="sampleDispatchedDate" placeholder="Sample Dispatched On" title="Please select sample dispatched on" value="<?php echo $vlQueryInfo['sample_dispatched_datetime']; ?>">
											</div>
										</div>
										<div class="col-xs-3 col-md-3" id="specimenDynamicForm">
											<div class="form-group">
												<label for="specimenType">Sample Type <span class="mandatory">*</span></label>
												<select name="specimenType" id="specimenType" class="form-control isRequired" title="Please choose sample type">
													<option value=""> -- Select -- </option>
													<?php foreach ($sResult as $name) { ?>
														<option value="<?php echo $name['sample_type_id']; ?>" <?php echo ($vlQueryInfo['sample_type'] == $name['sample_type_id']) ? "selected='selected'" : "" ?>>
															<?php echo ($name['sample_type_name']); ?></option>
													<?php } ?>
												</select>
											</div>
										</div>
									</div>
									<!-- <div id="specimenDynamicForm"></div> -->
								</div>
							</div>
							<div id="othersDynamicForm"></div>
							<?php if ($usersService->isAllowed('generic-test-results.php') && $_SESSION['accessType'] != 'collection-site') { ?>
								<div class="box box-primary">
									<div class="box-header with-border">
										<h3 class="box-title">Laboratory Information</h3>
									</div>
									<div class="box-body labSectionBody">
										<div class="row">
											<div class="col-md-6">
												<label for="vlFocalPerson" class="col-lg-5 control-label"> Focal Person
												</label>
												<div class="col-lg-7">
													<select class="form-control ajax-select2" id="vlFocalPerson" name="vlFocalPerson" title="Please enter Focal Person">
														<option value="<?= htmlspecialchars($vlQueryInfo['testing_lab_focal_person']); ?>" selected='selected'>
															<?= htmlspecialchars($vlQueryInfo['testing_lab_focal_person']); ?>
														</option>
													</select>
												</div>
											</div>
											<div class="col-md-6">
												<label for="vlFocalPersonPhoneNumber" class="col-lg-5 control-label">
													Focal Person Phone Number</label>
												<div class="col-lg-7">
													<input type="text" class="form-control forceNumeric labSection" id="vlFocalPersonPhoneNumber" name="vlFocalPersonPhoneNumber" maxlength="15" placeholder="Phone Number" title="Please enter focal person phone number" value="<?= htmlspecialchars($vlQueryInfo['testing_lab_focal_person_phone_number']); ?>" />
												</div>
											</div>
										</div>
										<div class="row" style="margin-top: 10px;">
											<div class="col-md-6">
												<label class="col-lg-5 control-label" for="sampleReceivedAtHubOn">Date
													Sample Received at Hub (PHL) </label>
												<div class="col-lg-7">
													<input type="text" class="form-control dateTime" id="sampleReceivedAtHubOn" name="sampleReceivedAtHubOn" placeholder="Sample Received at HUB Date" title="Please select sample received at HUB date" value="<?php echo $vlQueryInfo['sample_received_at_hub_datetime']; ?>" onchange="checkSampleReceviedAtHubDate()" />
												</div>
											</div>

											<div class="col-md-6">
												<label class="col-lg-5 control-label" for="sampleReceivedDate">Date
													Sample Received at Testing Lab </label>
												<div class="col-lg-7">
													<input type="text" class="form-control labSection dateTime" id="sampleReceivedDate" name="sampleReceivedDate" placeholder="Sample Received Date" title="Please select sample received date" value="<?php echo $vlQueryInfo['sample_received_at_testing_lab_datetime']; ?>" onchange="checkSampleReceviedDate()" />
												</div>
											</div>
										</div>
										<div class="row" style="margin-top: 10px;">
											<div class="col-md-6">
												<label for="testPlatform" class="col-lg-5 control-label"> Testing
													Platform <span class="mandatory result-span">*</span></label>
												<div class="col-lg-7">
													<select name="testPlatform" id="testPlatform" class="form-control isRequired result-optional labSection" title="Please choose VL Testing Platform">
														<option value="">-- Select --</option>
														<?php foreach ($importResult as $mName) { ?>
															<option value="<?php echo $mName['machine_name'] . '##' . $mName['lower_limit'] . '##' . $mName['higher_limit'] . '##' . $mName['config_id']; ?>" <?php echo ($vlQueryInfo['test_platform'] == $mName['machine_name']) ? 'selected="selected"' : ''; ?>>
																<?php echo $mName['machine_name']; ?></option>
														<?php } ?>
													</select>
												</div>
											</div>
											<div class="col-md-6">
												<label class="col-lg-5 control-label" for="noResult">Sample Rejection
													<span class="mandatory result-span">*</span></label>
												<div class="col-lg-7">
													<select name="noResult" id="noResult" class="form-control isRequired labSection" title="Please check if sample is rejected or not">
														<option value="">-- Select --</option>
														<option value="yes" <?php echo ($vlQueryInfo['is_sample_rejected'] == 'yes') ? 'selected="selected"' : ''; ?>>
															Yes</option>
														<option value="no" <?php echo ($vlQueryInfo['is_sample_rejected'] == 'no') ? 'selected="selected"' : ''; ?>>
															No</option>
													</select>
												</div>
											</div>
										</div>
										<div class="row rejectionReason" style="display:<?php echo ($vlQueryInfo['is_sample_rejected'] == 'yes') ? '' : 'none'; ?>;margin-top: 10px;">
											<div class="col-md-6 rejectionReason" style="display:<?php echo ($vlQueryInfo['is_sample_rejected'] == 'yes') ? '' : 'none'; ?>;">
												<label class="col-lg-5 control-label" for="rejectionReason">Rejection
													Reason </label>
												<div class="col-lg-7">
													<select name="rejectionReason" id="rejectionReason" class="form-control labSection" title="Please choose reason" onchange="checkRejectionReason();">
														<option value="">-- Select --</option>
														<?php foreach ($rejectionTypeResult as $type) { ?>
															<optgroup label="<?php echo ($type['rejection_type']); ?>">
																<?php
																foreach ($rejectionResult as $reject) {
																	if ($type['rejection_type'] == $reject['rejection_type']) { ?>
																		<option value="<?php echo $reject['rejection_reason_id']; ?>" <?php echo ($vlQueryInfo['reason_for_sample_rejection'] == $reject['rejection_reason_id']) ? 'selected="selected"' : ''; ?>>
																			<?php echo ($reject['rejection_reason_name']); ?>
																		</option>
																<?php }
																} ?>
															</optgroup>
														<?php }
														if ($sarr['sc_user_type'] != 'vluser') {  ?>
															<option value="other">Other (Please Specify) </option>
														<?php } ?>
													</select>
													<input type="text" class="form-control newRejectionReason" name="newRejectionReason" id="newRejectionReason" placeholder="Rejection Reason" title="Please enter rejection reason" style="width:100%;display:none;margin-top:2px;">
												</div>
											</div>
											<div class="col-md-6 rejectionReason" style="display:<?php echo ($vlQueryInfo['is_sample_rejected'] == 'yes') ? '' : 'none'; ?>;">
												<label class="col-lg-5 control-label" for="rejectionDate">Rejection Date
												</label>
												<div class="col-lg-7">
													<input value="<?php echo DateUtility::humanReadableDateFormat($vlQueryInfo['rejection_on']); ?>" class="form-control date rejection-date" type="text" name="rejectionDate" id="rejectionDate" placeholder="Select Rejection Date" title="Please select Sample Rejection Date" />
												</div>
											</div>
										</div>
										<div class="row" style="margin-top: 10px;">
											<div class="col-md-6">
												<label class="col-lg-5 control-label" for="sampleTestingDateAtLab">Sample Testing Date <span class="mandatory result-span">*</span></label>
												<div class="col-lg-7">
													<input type="text" class="form-control isRequired dateTime result-fieldsform-control result-fields labSection <?php echo ($vlQueryInfo['is_sample_rejected'] == 'no') ? 'isRequired' : ''; ?>" <?php echo ($vlQueryInfo['is_sample_rejected'] == 'yes') ? ' disabled="disabled" ' : ''; ?> id="sampleTestingDateAtLab" name="sampleTestingDateAtLab" placeholder="Sample Testing Date" title="Please select sample testing date" value="<?php echo $vlQueryInfo['sample_tested_datetime']; ?>" onchange="checkSampleTestingDate();" />
												</div>
											</div>
											<div class="col-md-6">
												<label class="col-lg-5 control-label labels" for="reasonForTesting">Reason For Testing <span class="mandatory result-span">*</span></label>
												<div class="col-lg-7">
													<select name="reasonForTesting" id="reasonForTesting" class="form-control isRequired result-optional" title="Please choose reason for testing">
														<option value="">-- Select --</option>
														<?php foreach ($testReason as $treason) { ?>
															<option value="<?php echo $treason['test_reason_id']; ?>" <?php echo ($vlQueryInfo['reason_for_testing'] == $treason['test_reason_id']) ? 'selected="selected"' : ''; ?>>
																<?php echo ucwords($treason['test_reason']); ?></option>
														<?php } ?>
													</select>
												</div>
											</div>
										</div>

										<div class="row" style="margin-top: 10px;">
											<?php if (count($reasonForFailure) > 0) { ?>
												<div class="col-md-6 labSection" style="<?php echo (!isset($vlQueryInfo['result']) || $vlQueryInfo['result'] == 'Failed') ? '' : 'display: none;'; ?>">
													<label class="col-lg-5 control-label" for="reasonForFailure">Reason for
														Failure </label>
													<div class="col-lg-7">
														<select name="reasonForFailure" id="reasonForFailure" class="form-control vlResult" title="Please choose reason for failure" style="width: 100%;">
															<?= $general->generateSelectOptions($reasonForFailure, $vlQueryInfo['reason_for_failure'], '-- Select --'); ?>
														</select>
													</div>
												</div>
											<?php } ?>
											<div class="col-md-6 vlResult">
												<label class="col-lg-5 control-label" for="resultDispatchedOn">Date
													Results Dispatched </label>
												<div class="col-lg-7">
													<input type="text" class="form-control labSection dateTime" id="resultDispatchedOn" name="resultDispatchedOn" placeholder="Result Dispatched Date" title="Please select result dispatched date" value="<?php echo $vlQueryInfo['result_dispatched_datetime']; ?>" />
												</div>
											</div>
										</div>
										<div class="row">
											<div class="col-md-12">
												<table aria-describedby="table" class="table table-bordered table-striped" aria-hidden="true" id="testNameTable">
													<thead>
														<tr>
															<th scope="row" class="text-center">Test No.</th>
															<th scope="row" class="text-center">Test Method</th>
															<th scope="row" class="text-center">Date of Testing</th>
															<th scope="row" class="text-center">Test Platform/Test
																Kit</th>
															<th scope="row" class="text-center">Test Result</th>
														</tr>
													</thead>
													<tbody id="testKitNameTable">
														<?php
														if (isset($genericTestInfo) && !empty($genericTestInfo)) {
															$kitShow = false;
															foreach ($genericTestInfo as $indexKey => $rows) { ?>
																<tr>
																	<td class="text-center"><?= ($indexKey + 1); ?><input type="hidden" name="testId[]" value="<?php echo base64_encode($rows['test_id']); ?>">
																	</td>
																	<td>
																		<?php
																		$value = '';
																		if (!in_array($rows['test_name'], array('Real Time RT-PCR', 'RDT-Antibody', 'RDT-Antigen', 'ELISA', 'other'))) {
																			$value = 'value="' . $rows['test_name'] . '"';
																			$show =  "block";
																		} else {
																			$show =  "none";
																		} ?>
																		<select class="form-control test-name-table-input" id="testName<?= ($indexKey + 1); ?>" name="testName[]" title="Please enter the name of the Testkit (or) Test Method used">
																			<option value="">--Select--</option>
																			<option value="Real Time RT-PCR" <?php echo (isset($rows['test_name']) && $rows['test_name'] == 'Real Time RT-PCR') ? "selected='selected'" : ""; ?>>
																				Real Time RT-PCR</option>
																			<option value="RDT-Antibody" <?php echo (isset($rows['test_name']) && $rows['test_name'] == 'RDT-Antibody') ? "selected='selected'" : ""; ?>>
																				RDT-Antibody</option>
																			<option value="RDT-Antigen" <?php echo (isset($rows['test_name']) && $rows['test_name'] == 'RDT-Antigen') ? "selected='selected'" : ""; ?>>
																				RDT-Antigen</option>
																			<option value="ELISA" <?php echo (isset($rows['test_name']) && $rows['test_name'] == 'ELISA') ? "selected='selected'" : ""; ?>>
																				ELISA</option>
																			<option value="other" <?php echo (isset($show) && $show == 'block') ? "selected='selected'" : ""; ?>>
																				Others</option>
																		</select>
																		<input <?php echo $value; ?> type="text" name="testNameOther[]" id="testNameOther<?= ($indexKey + 1); ?>" class="form-control testNameOther<?= ($indexKey + 1); ?>" title="Please enter the name of the Testkit (or) Test Method used" placeholder="Enter Test Method used" style="display: <?php echo $show; ?>;margin-top: 10px;" />
																	</td>
																	<td><input type="text" value="<?php echo DateUtility::humanReadableDateFormat($rows['sample_tested_datetime'], true); ?>" name="testDate[]" id="testDate<?= ($indexKey + 1); ?>" class="form-control test-name-table-input dateTime" placeholder="Tested on" title="Please enter the tested on for row <?= ($indexKey + 1); ?>" />
																	</td>
																	<td>
																		<select name="testingPlatform[]" id="testingPlatform<?= ($indexKey + 1); ?>" class="form-control result-optional test-name-table-input" title="Please select the Testing Platform for <?= ($indexKey + 1); ?>">
																			<?= $general->generateSelectOptions($testPlatformList, $rows['testing_platform'], '-- Select --'); ?>
																		</select>
																	</td>
																	<td>
																		<input type="text" id="testResult<?= ($indexKey + 1); ?>" value="<?php echo $rows['result']; ?>" name="testResult[]" class="form-control result-focus" value="<?php echo $vlQueryInfo['result']; ?>" placeholder="Enter result" title="Please enter final results">
																	</td>
																	<td style="vertical-align:middle;text-align: center;width:100px;">
																		<a class="btn btn-xs btn-primary test-name-table" href="javascript:void(0);" onclick="addTestRow();"><em class="fa-solid fa-plus"></em></a>&nbsp;
																		<a class="btn btn-xs btn-default test-name-table" href="javascript:void(0);" onclick="removeTestRow(this.parentNode.parentNode);deleteRow('<?php echo base64_encode($rows['test_id']); ?>');"><em class="fa-solid fa-minus"></em></a>
																	</td>
																</tr>
															<?php }
														} else { ?>
															<tr>
																<td class="text-center">1</td>
																<td>
																	<select class="form-control test-name-table-input" id="testName1" name="testName[]" title="Please enter the name of the Testkit (or) Test Method used">
																		<option value="">-- Select --</option>
																		<option value="Real Time RT-PCR">Real Time
																			RT-PCR</option>
																		<option value="RDT-Antibody">RDT-Antibody
																		</option>
																		<option value="RDT-Antigen">RDT-Antigen</option>
																		<option value="GeneXpert">GeneXpert</option>
																		<option value="ELISA">ELISA</option>
																		<option value="other">Others</option>
																	</select>
																	<input type="text" name="testNameOther[]" id="testNameOther1" class="form-control testNameOther1" title="Please enter the name of the Testkit (or) Test Method used" placeholder="Please enter the name of the Testkit (or) Test Method used" style="display: none;margin-top: 10px;" />
																</td>
																<td><input type="text" name="testDate[]" id="testDate1" class="form-control test-name-table-input dateTime" placeholder="Tested on" title="Please enter the tested on for row 1" />
																</td>
																<td>
																	<select name="testingPlatform[]" id="testingPlatform<?= ($indexKey + 1); ?>" class="form-control  result-optional test-name-table-input" title="Please select the Testing Platform for <?= ($indexKey + 1); ?>">
																		<?= $general->generateSelectOptions($testPlatformList, null, '-- Select --'); ?>
																	</select>
																</td>
																<td>
																	<input type="text" id="testResult<?= ($indexKey + 1); ?>" name="testResult[]" class="form-control result-focus" placeholder="Enter result" title="Please enter final results">
																</td>
																<td style="vertical-align:middle;text-align: center;width:100px;">
																	<a class="btn btn-xs btn-primary test-name-table" href="javascript:void(0);" onclick="addTestRow();"><em class="fa-solid fa-plus"></em></a>&nbsp;
																	<a class="btn btn-xs btn-default test-name-table" href="javascript:void(0);" onclick="removeTestRow(this.parentNode.parentNode);"><em class="fa-solid fa-minus"></em></a>
																</td>
															</tr>
														<?php } ?>
													</tbody>
													<tfoot id="resultSection">
														<tr>
															<th scope="row" colspan="4" class="text-right final-result-row">Final Result
															</th>
															<td id="result-sections">

															</td>
														</tr>
													</tfoot>
												</table>
											</div>
										</div>
										<div class="row">
											<div class="col-md-6" style="margin-top: 10px;">
												<label class="col-lg-5 control-label" for="reviewedBy">Reviewed By
													<span class="mandatory review-approve-span" style="display: <?php echo ($vlQueryInfo['is_sample_rejected'] != '') ? 'inline' : 'none'; ?>;">*</span></label>
												<div class="col-lg-7">
													<select name="reviewedBy" id="reviewedBy" class="select2 form-control" title="Please choose reviewed by" style="width: 100%;">
														<?= $general->generateSelectOptions($userInfo, $vlQueryInfo['result_reviewed_by'], '-- Select --'); ?>
													</select>
												</div>
											</div>
											<div class="col-md-6">
												<label class="col-lg-5 control-label" for="reviewedOn">Reviewed On
													<span class="mandatory review-approve-span" style="display: <?php echo ($vlQueryInfo['is_sample_rejected'] != '') ? 'inline' : 'none'; ?>;">*</span></label>
												<div class="col-lg-7">
													<input type="text" value="<?php echo $vlQueryInfo['result_reviewed_datetime']; ?>" name="reviewedOn" id="reviewedOn" class="dateTime form-control" placeholder="Reviewed on" title="Please enter the Reviewed on" />
												</div>
											</div>
										</div>
										<div class="row">
											<div class="col-md-6">
												<label class="col-lg-5 control-label" for="testedBy">Tested By
												</label>
												<div class="col-lg-7">
													<select name="testedBy" id="testedBy" class="select2 form-control" title="Please choose approved by">
														<?= $general->generateSelectOptions($userInfo, $vlQueryInfo['tested_by'], '-- Select --'); ?>
													</select>
												</div>
											</div>
											<?php
											$styleStatus = '';
											if ((($_SESSION['accessType'] == 'collection-site') && $vlQueryInfo['result_status'] == 9) || ($sCode != '')) {
												$styleStatus = "display:none"; ?>
												<input type="hidden" name="status" value="<?= htmlspecialchars($vlQueryInfo['result_status']); ?>" />
											<?php } ?>
											<div class="col-md-6" style="margin-top: 10px;">
												<label class="col-lg-5 control-label" for="approvedBy">Approved By
													<span class="mandatory review-approve-span" style="display: <?php echo ($vlQueryInfo['is_sample_rejected'] != '') ? 'block' : 'none'; ?>;">*</span></label>
												<div class="col-lg-7">
													<select name="approvedBy" id="approvedBy" class="form-control labSection" title="Please choose approved by">
														<?= $general->generateSelectOptions($userInfo, $vlQueryInfo['result_approved_by'], '-- Select --'); ?>
													</select>
												</div>
											</div>
										</div>
										<div class="row">
											<div class="col-md-6">
												<label class="col-lg-5 control-label" for="approvedOn">Approved On
													<span class="mandatory review-approve-span" style="display: <?php echo ($vlQueryInfo['is_sample_rejected'] != '') ? 'block' : 'none'; ?>;">*</span></label>
												<div class="col-lg-7">
													<input type="text" value="<?php echo $vlQueryInfo['result_approved_datetime']; ?>" class="form-control dateTime" id="approvedOn" name="approvedOn" placeholder="<?= _("Please enter date"); ?>" style="width:100%;" />
												</div>
											</div>
										</div>
										<div class="row">
											<div class="col-md-6">
												<label class="col-lg-5 control-label" for="labComments">Lab Tech. Comments </label>
												<div class="col-lg-7">
													<textarea class="form-control labSection" name="labComments" id="labComments" placeholder="Lab comments" style="width:100%"><?php echo trim($vlQueryInfo['lab_tech_comments']); ?></textarea>
												</div>
											</div>
											<div class="col-md-6 change-reason" style="display:none;">
												<label class="col-lg-5 control-label" for="reasonForResultChanges">Reason For Changes in Result<span class="mandatory">*</span></label>
												<div class="col-lg-7">
													<textarea class="form-control" name="reasonForResultChanges" id="reasonForResultChanges" placeholder="Enter Reason For Result Changes" title="Please enter reason for result changes" style="width:100%;"></textarea>
												</div>
											</div>
										</div>
										<?php if (!empty($allChange)) { ?>
											<div class="row">
												<div class="col-md-12"><?php echo $rch; ?></div>
											</div>
										<?php } ?>
										<div class="row" id="lapDynamicForm"></div>
									</div>
								<?php } ?>
								</div>
						</div>
					</div>
					<div class="box-footer">
						<input type="hidden" name="revised" id="revised" value="no" />
						<input type="hidden" name="vlSampleId" id="vlSampleId" value="<?= htmlspecialchars($vlQueryInfo['sample_id']); ?>" />
						<input type="hidden" name="isRemoteSample" value="<?= htmlspecialchars($vlQueryInfo['remote_sample']); ?>" />
						<input type="hidden" name="reasonForResultChangesHistory" id="reasonForResultChangesHistory" value="<?php echo base64_encode($vlQueryInfo['reason_for_testing']); ?>" />
						<input type="hidden" name="oldStatus" value="<?= htmlspecialchars($vlQueryInfo['result_status']); ?>" />
						<input type="hidden" name="countryFormId" id="countryFormId" value="<?php echo $arr['vl_form']; ?>" />
						<a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;">Save</a>&nbsp;
						<a href="view-requests.php" class="btn btn-default"> Cancel</a>
					</div>
			</div>
		</div>
</div>
</form>
</div>
</section>
</div>
<script type="text/javascript" src="/assets/js/datalist-css.min.js"></script>

<script>
	let provinceName = true;
	let facilityName = true;
	let testCounter = <?php echo (isset($genericTestInfo) && !empty($genericTestInfo)) ? (count($genericTestInfo)) : 1; ?>;
	let __clone = null;
	let reason = null;
	let resultValue = null;
	$(document).ready(function() {

		var testType = $("#testType").val();
		getSampleTypeList(testType);

		$('.date').datepicker({
			changeMonth: true,
			changeYear: true,
			dateFormat: 'dd-M-yy',
			timeFormat: "HH:mm",
			maxDate: "Today",
			yearRange: <?= (date('Y') - 100); ?> + ":" + "<?= date('Y') ?>"
		}).click(function() {
			$('.ui-datepicker-calendar').show();
		});
		$('.dateTime').datetimepicker({
			changeMonth: true,
			changeYear: true,
			dateFormat: 'dd-M-yy',
			timeFormat: "HH:mm",
			maxDate: "Today",
			onChangeMonthYear: function(year, month, widget) {
				setTimeout(function() {
					$('.ui-datepicker-calendar').show();
				});
			},
			yearRange: <?= (date('Y') - 100); ?> + ":" + "<?= date('Y') ?>"
		}).click(function() {
			$('.ui-datepicker-calendar').show();
		});
		$('.date').mask('99-aaa-9999');
		$('.dateTime').mask('99-aaa-9999 99:99');

		$('.result-focus').change(function(e) {
			var status = false;
			$(".result-focus").each(function(index) {
				if ($(this).val() != "") {
					status = true;
				}
			});
			if (status) {
				$('.change-reason').show();
				$('#reasonForResultChanges').addClass('isRequired');
			} else {
				$('.change-reason').hide();
				$('#reasonForResultChanges').removeClass('isRequired');
			}
		});

		/** Edit south sudan */
		$("#labId,#fName,#sampleCollectionDate").on('change', function() {

			if ($("#labId").val() != '' && $("#labId").val() == $("#fName").val() && $(
					"#sampleDispatchedDate").val() == "") {
				$('#sampleDispatchedDate').val($('#sampleCollectionDate').val());
			}
			if ($("#labId").val() != '' && $("#labId").val() == $("#fName").val() && $(
					"#sampleReceivedDate").val() == "") {
				$('#sampleReceivedDate').val($('#sampleCollectionDate').val());
				$('#sampleReceivedAtHubOn').val($('#sampleCollectionDate').val());
			}
		});

		$('#sampleCollectionDate').datetimepicker({
			changeMonth: true,
			changeYear: true,
			dateFormat: 'dd-M-yy',
			timeFormat: "HH:mm",
			maxDate: "Today",
			// yearRange: <?= (date('Y') - 100); ?> + ":" + "<?= date('Y') ?>",
			onSelect: function(date) {
				var dt2 = $('#sampleDispatchedDate');
				var startDate = $(this).datetimepicker('getDate');
				var minDate = $(this).datetimepicker('getDate');
				dt2.datetimepicker('setDate', minDate);
				startDate.setDate(startDate.getDate() + 1000000);
				dt2.datetimepicker('option', 'maxDate', "Today");
				dt2.datetimepicker('option', 'minDate', minDate);
				dt2.datetimepicker('option', 'minDateTime', minDate);
				dt2.val($(this).val());
			}
		}).click(function() {
			$('.ui-datepicker-calendar').show();
		});


		var minDate = $('#sampleCollectionDate').datetimepicker('getDate');
		var collectDate = $("#sampleCollectionDate").toString();
		var dispatchDate = $("#sampleDispatchedDate").toString();
		if ($("#sampleDispatchedDate").val() == "" || (collectDate >= dispatchDate))
			$("#sampleDispatchedDate").val($('#sampleCollectionDate').val());

		$('#sampleDispatchedDate').datetimepicker({
			changeMonth: true,
			changeYear: true,
			dateFormat: 'dd-M-yy',
			timeFormat: "HH:mm",
			minDate: minDate,
			startDate: minDate,
		});


		autoFillFocalDetails();
		$('#fName').select2({
			width: '100%',
			placeholder: "Select Clinic/Health Center"
		});
		$("#testType").select2({
			placeholder: "<?php echo _("Select Test Type"); ?>"
		});
		$('#labId').select2({
			width: '100%',
			placeholder: "Select Testing Lab"
		});
		$('#reviewedBy').select2({
			width: '100%',
			placeholder: "Select Reviewed By"
		});
		$('#testedBy').select2({
			width: '100%',
			placeholder: "Select Tested By"
		});

		$('#approvedBy').select2({
			width: '100%',
			placeholder: "Select Approved By"
		});
		$('#facilityId').select2({
			placeholder: "Select Clinic/Health Center"
		});
		$('#district').select2({
			placeholder: "District"
		});
		$('#province').select2({
			placeholder: "Province"
		});
		//getAge();
		getTestTypeForm();

		getfacilityProvinceDetails($("#fName").val());

		setTimeout(function() {
			$("#vlResult").trigger('change');
			$("#noResult").trigger('change');
			// just triggering sample collection date is enough,
			// it will automatically do everything that labId and fName changes will do
			$("#sampleCollectionDate").trigger('change');
			__clone = $(".labSectionBody").clone();
			reason = ($("#reasonForResultChanges").length) ? $("#reasonForResultChanges").val() : '';
			resultValue = $("#vlResult").val();

			$(".labSection").on("change", function() {
				if ($.trim(resultValue) != '') {
					if ($(".labSection").serialize() === $(__clone).serialize()) {
						$(".reasonForResultChanges").css("display", "none");
						$("#reasonForResultChanges").removeClass("isRequired");
					} else {
						$(".reasonForResultChanges").css("display", "block");
						$("#reasonForResultChanges").addClass("isRequired");
					}
				}
			});

		}, 500);

		checkPatientReceivesms('<?php echo $vlQueryInfo['consent_to_receive_sms']; ?>');

		$("#reqClinician").select2({
			placeholder: "Enter Request Clinician name",
			minimumInputLength: 0,
			width: '100%',
			allowClear: true,
			id: function(bond) {
				return bond._id;
			},
			ajax: {
				placeholder: "Type one or more character tp search",
				url: "/includes/get-data-list.php",
				dataType: 'json',
				delay: 250,
				data: function(params) {
					return {
						fieldName: 'request_clinician_name',
						tableName: 'form_generic',
						q: params.term, // search term
						page: params.page
					};
				},
				processResults: function(data, params) {
					params.page = params.page || 1;
					return {
						results: data.result,
						pagination: {
							more: (params.page * 30) < data.total_count
						}
					};
				},
				//cache: true
			},
			escapeMarkup: function(markup) {
				return markup;
			}
		});

		$("#reqClinician").change(function() {
			$.blockUI();
			var search = $(this).val();
			if ($.trim(search) != '') {
				$.get("/includes/get-data-list.php", {
						fieldName: 'request_clinician_name',
						tableName: 'form_generic',
						returnField: 'request_clinician_phone_number',
						limit: 1,
						q: search,
					},
					function(data) {
						if (data != "") {
							$("#reqClinicianPhoneNumber").val(data);
						}
					});
			}
			$.unblockUI();
		});

		$("#vlFocalPerson").select2({
			placeholder: "Enter Request Focal name",
			minimumInputLength: 0,
			width: '100%',
			allowClear: true,
			id: function(bond) {
				return bond._id;
			},
			ajax: {
				placeholder: "Type one or more character tp search",
				url: "/includes/get-data-list.php",
				dataType: 'json',
				delay: 250,
				data: function(params) {
					return {
						fieldName: 'testing_lab_focal_person',
						tableName: 'form_generic',
						q: params.term, // search term
						page: params.page
					};
				},
				processResults: function(data, params) {
					params.page = params.page || 1;
					return {
						results: data.result,
						pagination: {
							more: (params.page * 30) < data.total_count
						}
					};
				},
				//cache: true
			},
			escapeMarkup: function(markup) {
				return markup;
			}
		});

		$("#vlFocalPerson").change(function() {
			$.blockUI();
			var search = $(this).val();
			if ($.trim(search) != '') {
				$.get("/includes/get-data-list.php", {
						fieldName: 'testing_lab_focal_person',
						tableName: 'form_generic',
						returnField: 'testing_lab_focal_person_phone_number',
						limit: 1,
						q: search,
					},
					function(data) {
						if (data != "") {
							$("#vlFocalPersonPhoneNumber").val(data);
						}
					});
			}
			$.unblockUI();
		});

		$('#vlResult').on('change', function() {
			if ($(this).val().trim().toLowerCase() == 'failed' || $(this).val().trim().toLowerCase() ==
				'error') {
				if ($(this).val().trim().toLowerCase() == 'failed') {
					$('.reasonForFailure').show();
					$('#reasonForFailure').addClass('isRequired');
				}
			} else {
				$('.reasonForFailure').hide();
				$('#reasonForFailure').removeClass('isRequired');
			}
		});
	});

	function checkSampleReceviedAtHubDate() {
		var sampleCollectionDate = $("#sampleCollectionDate").val();
		var sampleReceivedAtHubOn = $("#sampleReceivedAtHubOn").val();
		if ($.trim(sampleCollectionDate) != '' && $.trim(sampleReceivedAtHubOn) != '') {

			date1 = new Date(sampleCollectionDate);
			date2 = new Date(sampleReceivedAtHubOn);

			if (date2.getTime() < date1.getTime()) {
				alert("<?= _("Sample Received at Hub Date cannot be earlier than Sample Collection Date"); ?>");
				$("#sampleReceivedAtHubOn").val("");
			}
		}
	}

	function checkSampleReceviedDate() {
		var sampleCollectionDate = $("#sampleCollectionDate").val();
		var sampleReceivedDate = $("#sampleReceivedDate").val();
		if ($.trim(sampleCollectionDate) != '' && $.trim(sampleReceivedDate) != '') {

			date1 = new Date(sampleCollectionDate);
			date2 = new Date(sampleReceivedDate);

			if (date2.getTime() < date1.getTime()) {
				alert("<?= _("Sample Received at Testing Lab Date cannot be earlier than Sample Collection Date"); ?>");
				$("#sampleReceivedDate").val("");
			}
		}
	}

	function checkSampleTestingDate() {
		var sampleCollectionDate = $("#sampleCollectionDate").val();
		var sampleTestingDate = $("#sampleTestingDateAtLab").val();
		if ($.trim(sampleCollectionDate) != '' && $.trim(sampleTestingDate) != '') {

			date1 = new Date(sampleCollectionDate);
			date2 = new Date(sampleTestingDate);

			if (date2.getTime() < date1.getTime()) {
				alert("<?= _("Sample Testing Date cannot be earlier than Sample Collection Date"); ?>");
				$("#sampleTestingDateAtLab").val("");
			}
		}
	}

	function checkARTInitiationDate() {
		var dob = changeFormat($("#dob").val());
		var artInitiationDate = $("#dateOfArtInitiation").val();
		if ($.trim(dob) != '' && $.trim(artInitiationDate) != '') {

			date1 = new Date(dob);
			date2 = new Date(artInitiationDate);

			if (date2.getTime() < date1.getTime()) {
				alert("<?= _("ART Initiation Date cannot be earlier than Patient Date of Birth"); ?>");
				$("#dateOfArtInitiation").val("");
			}
		}
	}

	function checkSampleNameValidation(tableName, fieldName, id, fnct, alrt) {
		if ($.trim($("#" + id).val()) != '') {
			$.blockUI();
			$.post("/vl/requests/checkSampleDuplicate.php", {
					tableName: tableName,
					fieldName: fieldName,
					value: $("#" + id).val(),
					fnct: fnct,
					format: "html"
				},
				function(data) {
					if (data != 0) {
						<?php if (isset($sarr['sc_user_type']) && ($sarr['sc_user_type'] == 'remoteuser' || $sarr['sc_user_type'] == 'standalone')) { ?>
							alert(alrt);
							$("#" + id).val('');
						<?php } else { ?>
							data = data.split("##");
							document.location.href = "editVlRequest.php?id=" + data[0] + "&c=" + data[1];
						<?php } ?>
					}
				});
			$.unblockUI();
		}
	}

	function getAge() {
		let dob = $("#dob").val();
		if ($.trim(dob) != '') {
			let age = getAgeFromDob(dob);
			$("#ageInYears").val("");
			$("#ageInMonths").val("");
			if (age.years >= 1) {
				$("#ageInYears").val(age.years);
			} else {
				$("#ageInMonths").val(age.months);
			}
		}
	}

	function clearDOB(val) {
		if ($.trim(val) != "") {
			$("#dob").val("");
		}
	}

	function checkARTRegimenValue() {
		var artRegimen = $("#artRegimen").val();
		if (artRegimen == 'other') {
			$(".newArtRegimen").show();
			$("#newArtRegimen").addClass("isRequired");
			$("#newArtRegimen").focus();
		} else {
			$(".newArtRegimen").hide();
			$("#newArtRegimen").removeClass("isRequired");
			$('#newArtRegimen').val("");
		}
	}

	function changeFormat(date) {
		splitDate = date.split("-");
		var fDate = new Date(splitDate[1] + splitDate[2] + ", " + splitDate[0]);
		var monthDigit = fDate.getMonth();
		var fMonth = isNaN(monthDigit) ? 1 : (parseInt(monthDigit) + parseInt(1));
		fMonth = (fMonth < 10) ? '0' + fMonth : fMonth;
		format = splitDate[2] + '-' + fMonth + '-' + splitDate[0];
		return format;
	}

	function showPatientList() {
		$("#showEmptyResult").hide();
		if ($.trim($("#artPatientNo").val()) != '') {
			$.post("/vl/requests/search-patients.php", {
					artPatientNo: $.trim($("#artPatientNo").val())
				},
				function(data) {
					if (data >= '1') {
						showModal('patientModal.php?artNo=' + $.trim($("#artPatientNo").val()), 900, 520);
					} else {
						$("#showEmptyResult").show();
					}
				});
		}
	}

	function getfacilityProvinceDetails(obj) {
		$.blockUI();
		//check facility name`
		var cName = $("#fName").val();
		var pName = $("#province").val();
		if (cName != '' && provinceName && facilityName) {
			provinceName = false;
		}
		if (cName != '' && facilityName) {
			$.post("/includes/siteInformationDropdownOptions.php", {
					cName: cName,
					testType: 'vl'
				},
				function(data) {
					if (data != "") {
						details = data.split("###");
						$("#province").html(details[0]);
						$("#district").html(details[1]);
					}
				});
		} else if (pName == '' && cName == '') {
			provinceName = true;
			facilityName = true;
			$("#province").html("<?php echo $province; ?>");
			$("#fName").html("<?php echo $facility; ?>");
		}
		$.unblockUI();
	}


	/** Edit south sudan function */
	function showTesting(chosenClass) {
		$(".viralTestData").val('');
		$(".hideTestData").hide();
		$("." + chosenClass).show();
	}

	function getProvinceDistricts(obj) {
		$.blockUI();
		var cName = $("#fName").val();
		var pName = $("#province").val();
		if (pName != '' && provinceName && facilityName) {
			facilityName = false;
		}
		if ($.trim(pName) != '') {
			//if (provinceName) {
			$.post("/includes/siteInformationDropdownOptions.php", {
					pName: pName,
					testType: 'vl'
				},
				function(data) {
					if (data != "") {
						details = data.split("###");
						$("#fName").html(details[0]);
						$("#district").html(details[1]);
						$("#fCode").val('');
						$(".facilityDetails").hide();
						$(".facilityEmails").html('');
						$(".facilityMobileNumbers").html('');
						$(".facilityContactPerson").html('');
					}
				});
			//}
		} else if (pName == '' && cName == '') {
			provinceName = true;
			facilityName = true;
			$("#province").html("<?php echo $province; ?>");
			$("#fName").html(
				"<option data-code='' data-emails='' data-mobile-nos='' data-contact-person='' value=''> -- Select -- </option>"
			);
		}
		$.unblockUI();
	}

	function getFacilities(obj) {
		//alert(obj);
		$.blockUI();
		var dName = $("#district").val();
		var cName = $("#fName").val();
		if (dName != '') {
			$.post("/includes/siteInformationDropdownOptions.php", {
					dName: dName,
					cliName: cName,
					fType: 2,
					testType: 'vl'
				},
				function(data) {
					if (data != "") {
						details = data.split("###");
						$("#fName").html(details[0]);
						//$("#labId").html(details[1]);
						$(".facilityDetails").hide();
						$(".facilityEmails").html('');
						$(".facilityMobileNumbers").html('');
						$(".facilityContactPerson").html('');
					}
				});
		}
		$.unblockUI();
	}

	function getfacilityProvinceDetails(obj) {
		$.blockUI();
		//check facility name
		var cName = $("#fName").val();
		var pName = $("#province").val();
		if (cName != '' && provinceName && facilityName) {
			provinceName = false;
		}
		if (cName != '' && facilityName) {
			$.post("/includes/siteInformationDropdownOptions.php", {
					cName: cName,
					testType: 'vl'
				},
				function(data) {
					if (data != "") {
						details = data.split("###");
						$("#province").html(details[0]);
						$("#district").html(details[1]);
						$("#clinicianName").val(details[2]);
					}
				});
		} else if (pName == '' && cName == '') {
			provinceName = true;
			facilityName = true;
			$("#province").html("<?php echo $province; ?>");
			$("#facilityId").html("<?php echo $facility; ?>");
		}
		$.unblockUI();
	}

	function fillFacilityDetails(obj) {
		getfacilityProvinceDetails(obj)
		$("#fCode").val($('#fName').find(':selected').data('code'));
		var femails = $('#fName').find(':selected').data('emails');
		var fmobilenos = $('#fName').find(':selected').data('mobile-nos');
		var fContactPerson = $('#fName').find(':selected').data('contact-person');
		if ($.trim(femails) != '' || $.trim(fmobilenos) != '' || fContactPerson != '') {
			$(".facilityDetails").show();
		} else {
			$(".facilityDetails").hide();
		}
		($.trim(femails) != '') ? $(".femails").show(): $(".femails").hide();
		($.trim(femails) != '') ? $(".facilityEmails").html(femails): $(".facilityEmails").html('');
		($.trim(fmobilenos) != '') ? $(".fmobileNumbers").show(): $(".fmobileNumbers").hide();
		($.trim(fmobilenos) != '') ? $(".facilityMobileNumbers").html(fmobilenos): $(".facilityMobileNumbers").html('');
		($.trim(fContactPerson) != '') ? $(".fContactPerson").show(): $(".fContactPerson").hide();
		($.trim(fContactPerson) != '') ? $(".facilityContactPerson").html(fContactPerson): $(".facilityContactPerson").html(
			'');
	}
	$("input:radio[name=gender]").click(function() {
		if ($(this).val() == 'male' || $(this).val() == 'not_recorded') {
			$('.femaleSection').hide();
			$('input[name="breastfeeding"]').prop('checked', false);
			$('input[name="patientPregnant"]').prop('checked', false);
		} else if ($(this).val() == 'female') {
			$('.femaleSection').show();
		}
	});
	$("#sampleTestingDateAtLab").change(function() {
		if ($(this).val() != "") {
			$(".result-fields").attr("disabled", false);
			$(".result-fields").addClass("isRequired");
			$(".result-span").show();
			$('.vlResult').css('display', 'block');
			$('.rejectionReason').hide();
			$('#rejectionReason').removeClass('isRequired');
			$('#rejectionDate').removeClass('isRequired');
			$('#rejectionReason').val('');
			$(".review-approve-span").hide();
			$("#noResult").trigger('change');
		}
	});
	$("#noResult").on("change", function() {

		if ($(this).val() == 'yes') {
			$('.rejectionReason').show();
			$('.vlResult').css('display', 'none');
			$("#sampleTestingDateAtLab, #vlResult").val("");
			$(".result-fields").val("");
			$(".result-fields").attr("disabled", true);
			$(".result-fields").removeClass("isRequired");
			$(".result-span").hide();
			$(".review-approve-span").show();
			$('#rejectionReason').addClass('isRequired');
			$('#rejectionDate').addClass('isRequired');
			$('#reviewedBy').addClass('isRequired');
			$('#reviewedOn').addClass('isRequired');
			$('#approvedBy').addClass('isRequired');
			$('#approvedOn').addClass('isRequired');
			$(".result-optional").removeClass("isRequired");
			$("#reasonForFailure").removeClass('isRequired');
		} else if ($(this).val() == 'no') {
			$(".result-fields").attr("disabled", false);
			$(".result-fields").addClass("isRequired");
			$(".result-span").show();
			$(".review-approve-span").show();
			$('.vlResult').css('display', 'block');
			$('.rejectionReason').hide();
			$('#rejectionReason').removeClass('isRequired');
			$('#rejectionDate').removeClass('isRequired');
			$('#rejectionReason').val('');
			$('#reviewedBy').addClass('isRequired');
			$('#reviewedOn').addClass('isRequired');
			$('#approvedBy').addClass('isRequired');
			$('#approvedOn').addClass('isRequired');
		} else {
			$(".result-fields").attr("disabled", false);
			$(".result-fields").removeClass("isRequired");
			$(".result-optional").removeClass("isRequired");
			$(".result-span").show();
			$('.vlResult').css('display', 'block');
			$('.rejectionReason').hide();
			$(".result-span").hide();
			$(".review-approve-span").hide();
			$('#rejectionReason').removeClass('isRequired');
			$('#rejectionDate').removeClass('isRequired');
			$('#rejectionReason').val('');
			$('#reviewedBy').removeClass('isRequired');
			$('#reviewedOn').removeClass('isRequired');
			$('#approvedBy').removeClass('isRequired');
			$('#approvedOn').removeClass('isRequired');
		}
	});


	$('#testingPlatform').on("change", function() {
		$(".vlResult").show();
		//$('#vlResult, #noResult').addClass('isRequired');
		$("#noResult").val("");
		//$("#noResult").trigger("change");
	});


	function checkRejectionReason() {
		var rejectionReason = $("#rejectionReason").val();
		if (rejectionReason == "other") {
			$("#newRejectionReason").show();
			$("#newRejectionReason").addClass("isRequired");
		} else {
			$("#newRejectionReason").hide();
			$("#newRejectionReason").removeClass("isRequired");
			$('#newRejectionReason').val("");
		}
	}

	function validateNow() {
		flag = deforayValidator.init({
			formId: 'vlRequestFormRwd'
		});

		/* $('.isRequired').each(function() {
		    ($(this).val() == '') ? $(this).css('background-color', '#FFFF99'): $(this).css('background-color', '#FFFFFF')
		}); */
		if (flag) {
			$.blockUI();
			document.getElementById('vlRequestFormRwd').submit();
		}
	}

	function checkPatientReceivesms(val) {
		if (val == 'yes') {
			$('#patientPhoneNumber').addClass('isRequired');
		} else {
			$('#patientPhoneNumber').removeClass('isRequired');
		}
	}

	function autoFillFocalDetails() {
		// labId = $("#labId").val();
		// if ($.trim(labId) != '') {
		//     $("#vlFocalPerson").val($('#labId option:selected').attr('data-focalperson')).trigger('change');
		//     $("#vlFocalPersonPhoneNumber").val($('#labId option:selected').attr('data-focalphone'));
		// }
	}

	function getTestTypeForm() {
		var testType = $("#testType").val();
		if (testType != "") {
			$(".requestForm").show();
			$.post("/generic-tests/requests/getTestTypeForm.php", {
					testType: testType,
					testTypeForm: '<?php echo base64_encode($vlQueryInfo['test_type_form']); ?>',
				},
				function(data) {
					//console.log(data);
					data = JSON.parse(data);
					if (data.facility.length > 0) {
						$("#clinicDynamicForm").after(data.facility);
					}
					if (data.patient.length > 0) {
						$("#patientDynamicForm").after(data.patient);
					}
					if (data.lab.length > 0) {
						$("#lapDynamicForm").html(data.lab);
					}
					if (data.result.length > 0) {
						$("#result-sections").html(data.result);
					} else {
						$('#resultSection').hide()
					}
					if (data.specimen.length > 0) {
						$("#specimenDynamicForm").after(data.specimen);
					}
					if (data.others.length > 0) {
						$("#othersDynamicForm").html(data.others);
					}
					$('.date').datepicker({
						changeMonth: true,
						changeYear: true,
						dateFormat: 'dd-M-yy',
						timeFormat: "hh:mm",
						maxDate: "Today",
						yearRange: <?= (date('Y') - 100); ?> + ":" + "<?= date('Y') ?>"
					}).click(function() {
						$('.ui-datepicker-calendar').show();
					});
				});
		} else {
			$("#clinicDynamicFormInput").remove();
			$("#patientDynamicFormInput").remove();
			$("#lapDynamicForm").html('');
			$("#specimenDynamicFormInput").remove();
			$("#othersDynamicForm").html('');
			$(".requestForm").hide();
		}
	}

	function getSampleTypeList(testTypeId) {
		$.post("/includes/get-sample-type.php", {
				testTypeId: testTypeId,
				sampleTypeId: '<?php echo $vlQueryInfo['sample_type']; ?>'
			},
			function(data) {
				if (data != "") {
					$("#specimenType").html(data);
				}
			});
	}

	function addTestRow() {
		testCounter++;
		let rowString = `<tr>
                    <td class="text-center">${testCounter}</td>
                    <td>
                    <select class="form-control test-name-table-input" id="testName${testCounter}" name="testName[]" title="Please enter the name of the Testkit (or) Test Method used">
                    <option value="">-- Select --</option>
                    <option value="Real Time RT-PCR">Real Time RT-PCR</option>
                    <option value="RDT-Antibody">RDT-Antibody</option>
                    <option value="RDT-Antigen">RDT-Antigen</option>
                    <option value="GeneXpert">GeneXpert</option>
                    <option value="ELISA">ELISA</option>
                    <option value="other">Others</option>
                </select>
                <input type="text" name="testNameOther[]" id="testNameOther${testCounter}" class="form-control testNameOther${testCounter}" title="Please enter the name of the Testkit (or) Test Method used" placeholder="Please enter the name of the Testkit (or) Test Method used" style="display: none;margin-top: 10px;" />
            </td>
            <td><input type="text" name="testDate[]" id="testDate${testCounter}" class="form-control test-name-table-input dateTime" placeholder="Tested on" title="Please enter the tested on for row ${testCounter}" /></td>
            <td><select name="testingPlatform[]" id="testingPlatform${testCounter}" class="form-control test-name-table-input" title="Please select the Testing Platform for ${testCounter}"><?= $general->generateSelectOptions($testPlatformList, null, '-- Select --'); ?></select></td>
            <td class="kitlabels" style="display: none;"><input type="text" name="lotNo[]" id="lotNo${testCounter}" class="form-control kit-fields${testCounter}" placeholder="Kit lot no" title="Please enter the kit lot no. for row ${testCounter}" style="display:none;"/></td>
            <td class="kitlabels" style="display: none;"><input type="text" name="expDate[]" id="expDate${testCounter}" class="form-control expDate kit-fields${testCounter}" placeholder="Expiry date" title="Please enter the expiry date for row ${testCounter}" style="display:none;"/></td>
            <td>
               <input type="text" id="testResult${testCounter}" name="testResult[]" class="form-control" placeholder="Enter result" title="Please enter final results">
            </td>
            <td style="vertical-align:middle;text-align: center;width:100px;">
                <a class="btn btn-xs btn-primary test-name-table" href="javascript:void(0);" onclick="addTestRow(this);"><em class="fa-solid fa-plus"></em></a>&nbsp;
                <a class="btn btn-xs btn-default test-name-table" href="javascript:void(0);" onclick="removeTestRow(this.parentNode.parentNode);"><em class="fa-solid fa-minus"></em></a>
            </td>
        </tr>`;
		$("#testKitNameTable").append(rowString);

		$('.date').datepicker({
			changeMonth: true,
			changeYear: true,
			onSelect: function() {
				$(this).change();
			},
			dateFormat: 'dd-M-yy',
			timeFormat: "HH:mm",
			maxDate: "Today",
			yearRange: <?= (date('Y') - 100); ?> + ":" + "<?= date('Y') ?>"
		}).click(function() {
			$('.ui-datepicker-calendar').show();
		});

		$('.expDate').datepicker({
			changeMonth: true,
			changeYear: true,
			onSelect: function() {
				$(this).change();
			},
			dateFormat: 'dd-M-yy',
			timeFormat: "HH:mm",
			// minDate: "Today",
			yearRange: <?= (date('Y') - 100); ?> + ":" + "<?= date('Y') ?>"
		}).click(function() {
			$('.ui-datepicker-calendar').show();
		});

		$('.dateTime').datetimepicker({
			changeMonth: true,
			changeYear: true,
			dateFormat: 'dd-M-yy',
			timeFormat: "HH:mm",
			maxDate: "Today",
			onChangeMonthYear: function(year, month, widget) {
				setTimeout(function() {
					$('.ui-datepicker-calendar').show();
				});
			}
		}).click(function() {
			$('.ui-datepicker-calendar').show();
		});

		if ($('.kitlabels').is(':visible') == true) {
			$('.kitlabels').show();
		}

	}

	function removeTestRow(el) {
		$(el).fadeOut("slow", function() {
			el.parentNode.removeChild(el);
			rl = document.getElementById("testKitNameTable").rows.length;
			if (rl == 0) {
				testCounter = 0;
				addTestRow();
			}
		});
	}
</script>
<?php require_once APPLICATION_PATH . '/footer.php';
