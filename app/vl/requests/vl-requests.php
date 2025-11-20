<?php

// vl-requests.php


$title = _translate("VL Requests");

require_once APPLICATION_PATH . '/header.php';

use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Services\GeoLocationsService;

$hidesrcofreq = false;
$dateRange = $labName = $srcOfReq = $srcStatus = null;

// Sanitized values from $request object
/** @var Psr\Http\Message\ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());

if (!empty($_GET['id'])) {
	$params = explode("##", base64_decode((string) $_GET['id']));
	$dateRange = $params[0];
	$labName = $params[1];
	$srcOfReq = $params[2];
	$srcStatus = $params[3];
	$hidesrcofreq = true;
}
$facilityId = null;
$labId = null;
if (isset($_GET['facilityId']) && $_GET['facilityId'] != "" && isset($_GET['labId']) && $_GET['labId'] != "") {
	$facilityId = base64_decode((string) $_GET['facilityId']);
	$labId = base64_decode((string) $_GET['labId']);
}

$interopConfig = [];
if (file_exists(APPLICATION_PATH . '/../configs/config.interop.php')) {
	$interopConfig = require_once(APPLICATION_PATH . '/../configs/config.interop.php');
}


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$globalConfig = $general->getGlobalConfig();

$formId = (int) $globalConfig['vl_form'];

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

/** @var GeoLocationsService $geolocationService */
$geolocationService = ContainerRegistry::get(GeoLocationsService::class);


$global = $general->getGlobalConfig();
$state = $geolocationService->getProvinces("yes");
$healthFacilites = $facilitiesService->getHealthFacilities('vl');

$facilitiesDropdown = $general->generateSelectOptions($healthFacilites, $facilityId, "-- Select --");
$testingLabs = $facilitiesService->getTestingLabs('vl');
$testingLabsDropdown = $general->generateSelectOptions($testingLabs, $labId, "-- Select --");

$sampleStatusData = $general->getSampleStatus();

//Funding source list
$fundingSourceList = $general->getFundingSources();

//Implementing partner list
$implementingPartnerList = $general->getImplementationPartners();

$sQuery = "SELECT * FROM r_vl_sample_type WHERE `status`='active'";
$sResult = $db->rawQuery($sQuery);

$sourceOfRequests = $general->getSourcesOfTestRequests('form_vl', asNameValuePair: true);
$srcOfReqList = [];
foreach ($sourceOfRequests as $value => $displayText) {
	$srcOfReqList[$value] = $displayText;
}

$lastModifiedColumnPosition = ($general->isSTSInstance() || $general->isLISInstance()) ? 12 : 11;

if ($formId == COUNTRY\CAMEROON) {
	$lastModifiedColumnPosition += 2;
}

$sampleColumnToSort = ($general->isSTSInstance()) ? 1 : 0;

?>
<style>
	.select2-selection__choice {
		color: black !important;
	}

	<?php if (!empty($_GET['id'])) { ?>
		header {
			display: none;
		}

		.main-sidebar {
			z-index: -9;
		}

		.content-wrapper {
			margin-left: 0px;
		}

	<?php } ?>
</style>
<link rel="stylesheet" type="text/css" href="/assets/css/tooltipster.bundle.min.css" />
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<?php if (!$hidesrcofreq) { ?>
		<!-- Content Header (Page header) -->
		<section class="content-header">
			<h1><em class="fa-solid fa-pen-to-square"></em>
				<?php echo _translate("Viral Load Test Requests"); ?>
			</h1>
			<ol class="breadcrumb">
				<li><a href="/"><em class="fa-solid fa-chart-pie"></em>
						<?php echo _translate("Home"); ?>
					</a></li>
				<li class="active">
					<?php echo _translate("Test Request"); ?>
				</li>
			</ol>
		</section>
	<?php } ?>

	<!-- Main content -->
	<section class="content">
		<div class="row">
			<div class="col-xs-12">
				<div class="box">
					<table aria-describedby="table" id="advancedFilters" class="table pageFilters" aria-hidden="true"
						style="margin-left:1%;margin-top:20px;width: 98%;margin-bottom: 0px;display: none;">
						<tr>
							<td><strong>
									<?php echo _translate("Sample Collection Date"); ?>&nbsp;:
								</strong></td>
							<td>
								<input type="text" id="sampleCollectionDate" name="sampleCollectionDate"
									class="form-control daterangefield"
									placeholder="<?php echo _translate('Select Collection Date'); ?>" readonly
									style="background:#fff;"
									value="<?php echo (empty($_GET['daterange'])) ? "" : $_GET['daterange']; ?>" />
							</td>
							<td><strong>
									<?php echo _translate("Sample Received at Lab Date"); ?>&nbsp;:
								</strong></td>
							<td>
								<input type="text" id="sampleReceivedDateAtLab" name="sampleReceivedDateAtLab"
									class="form-control daterangefield"
									placeholder="<?php echo _translate('Select Sample Received Date At Lab'); ?>"
									readonly style="background:#fff;" />
							</td>

							<td><strong>
									<?php echo _translate("Sample Type"); ?> :
								</strong></td>
							<td>
								<select class="form-control" id="sampleType" name="sampleType"
									title="<?php echo _translate('Please select sample type'); ?>">
									<option value="">
										<?php echo _translate("-- Select --"); ?>
									</option>
									<?php
									foreach ($sResult as $type) { ?>
										<option value="<?php echo $type['sample_id']; ?>"><?= $type['sample_name']; ?>
										</option>
										<?php
									} ?>
								</select>
							</td>
						</tr>
						<tr>

							<td><strong>
									<?php echo _translate("Sample Test Date"); ?>&nbsp;:
								</strong></td>
							<td>
								<input type="text" id="sampleTestedDate" name="sampleTestedDate"
									class="form-control daterangefield"
									placeholder="<?php echo _translate('Select Tested Date'); ?>" readonly
									style="background:#fff;" />
							</td>
							<td><strong>
									<?php echo _translate("Viral Load Suppression"); ?> &nbsp;:
								</strong></td>
							<td>
								<select class="form-control" id="vLoad" name="vLoad"
									title="Please select VL Suppression" style="width:220px;">
									<option value="">
										<?php echo _translate("-- Select --"); ?>
									</option>
									<option value="suppressed"><?= _translate("Suppressed"); ?></option>
									<option value="not suppressed"><?= _translate("Not Suppressed"); ?></option>
								</select>
							</td>
							<td><strong>
									<?php echo _translate("Print Date"); ?>&nbsp;:
								</strong></td>
							<td>
								<input type="text" id="printDate" name="printDate" class="form-control daterangefield"
									placeholder="<?php echo _translate('Select Print Date'); ?>" readonly
									style="width:220px;background:#fff;" />
							</td>
						</tr>
						<tr>

							<td><strong>
									<?php echo _translate("Request Creation Date"); ?>&nbsp;:
								</strong></td>
							<td>
								<input type="text" id="requestCreatedDatetime" name="requestCreatedDatetime"
									class="form-control daterangefield"
									placeholder="<?php echo _translate('Select Request Created Datetime'); ?>" readonly
									style="width:220px;background:#fff;" />
							</td>
							<td><strong>
									<?php echo _translate("Status"); ?>&nbsp;:
								</strong></td>
							<td>
								<select name="status" id="status" class="form-control"
									title="<?php echo _translate('Please choose status'); ?>"
									onchange="checkSampleCollectionDate();">
									<option value="" selected=selected><?php echo _translate("All Status"); ?></option>
									<?php
									foreach ($sampleStatusData as $sample) { ?>
										<option value="<?= $sample['status_id']; ?>"><?= $sample['status_name'] ?></option>
										<?php
									} ?>
								</select>
							</td>
							<td><strong>
									<?php echo _translate("Show only Reordered Samples"); ?>&nbsp;:
								</strong></td>
							<td>
								<select name="showReordSample" id="showReordSample" class="form-control"
									title="Please choose record sample">
									<option value=""><?= _translate('-- Select --'); ?></option>
									<option value="yes">
										<?php echo _translate("Yes"); ?>
									</option>
									<option value="no">
										<?php echo _translate("No"); ?>
									</option>
								</select>
							</td>
						</tr>
						<tr>
							<td colspan="2">
								<div class="col-md-12">
									<div class="col-md-6">
										<strong>
											<?php echo _translate("Pregnant"); ?>&nbsp;:
										</strong>
										<select name="patientPregnant" id="patientPregnant" class="form-control"
											title="<?php echo _translate('Please choose pregnant option'); ?>">
											<option value="">
												<?php echo _translate("-- Select --"); ?>
											</option>
											<option value="yes">
												<?php echo _translate("Yes"); ?>
											</option>
											<option value="no">
												<?php echo _translate("No"); ?>
											</option>
										</select>
									</div>
									<div class="col-md-6">
										<strong>
											<?php echo _translate("Breastfeeding"); ?>&nbsp;:
										</strong>
										<select name="breastFeeding" id="breastFeeding" class="form-control"
											title="<?php echo _translate('Please choose pregnant option'); ?>">
											<option value="">
												<?php echo _translate("-- Select --"); ?>
											</option>
											<option value="yes">
												<?php echo _translate("Yes"); ?>
											</option>
											<option value="no">
												<?php echo _translate("No"); ?>
											</option>
										</select>
									</div>
								</div>
							</td>
							<td><strong>
									<?php echo _translate("Batch Code"); ?> :
								</strong></td>
							<td>
								<input type="text" id="batchCode" name="batchCode" class="form-control autocomplete"
									placeholder="<?php echo _translate('Enter Batch Code'); ?>"
									style="background:#fff;" />

							</td>
							<td><strong>
									<?php echo _translate("Funding Sources"); ?>&nbsp;:
								</strong></td>
							<td>
								<select class="form-control" name="fundingSource" id="fundingSource"
									title="<?php echo _translate('Please choose funding source'); ?>">
									<option value="">
										<?php echo _translate("-- Select --"); ?>
									</option>
									<?php
									foreach ($fundingSourceList as $fundingSource) { ?>
										<option
											value="<?php echo base64_encode((string) $fundingSource['funding_source_id']); ?>">
											<?= $fundingSource['funding_source_name']; ?>
										</option>
									<?php } ?>
								</select>
							</td>

						</tr>
						<tr>
							<td><strong>
									<?php echo _translate("Implementing Partners"); ?>&nbsp;:
								</strong></td>
							<td>
								<select class="form-control" name="implementingPartner" id="implementingPartner"
									title="<?php echo _translate('Please choose implementing partner'); ?>">
									<option value="">
										<?php echo _translate("-- Select --"); ?>
									</option>
									<?php
									foreach ($implementingPartnerList as $implementingPartner) { ?>
										<option
											value="<?php echo base64_encode((string) $implementingPartner['i_partner_id']); ?>">
											<?= $implementingPartner['i_partner_name']; ?>
										</option>
									<?php } ?>
								</select>
							</td>
							<td><strong>
									<?php echo _translate("Sex"); ?>&nbsp;:
								</strong></td>
							<td>
								<select name="gender" id="gender" class="form-control"
									title="<?php echo _translate('Please select sex'); ?>" style="width:220px;"
									onchange="hideFemaleDetails(this.value)">
									<option value="">
										<?php echo _translate("-- Select --"); ?>
									</option>
									<option value="male">
										<?php echo _translate("Male"); ?>
									</option>
									<option value="female">
										<?php echo _translate("Female"); ?>
									</option>
									<option value="unreported">
										<?php echo _translate("Unreported"); ?>
									</option>
								</select>
							</td>
							<td><strong>
									<?php echo _translate("Sample Type"); ?> :
								</strong></td>
							<td>
								<select class="form-control" id="requestSampleType" name="requestSampleType"
									title="<?php echo _translate('Please select request sample type'); ?>">
									<option value="">
										<?php echo _translate("All"); ?>
									</option>
									<option value="result">
										<?php echo _translate("Samples with result"); ?>
									</option>
									<option value="noresult">
										<?php echo _translate("Samples without result"); ?>
									</option>
								</select>
							</td>
						</tr>
						<tr>

							<td><strong>
									<?php echo _translate("Source of Request"); ?> :
								</strong></td>
							<td>
								<select class="form-control" id="srcOfReq" name="srcOfReq"
									title="<?php echo _translate('Please select source of request'); ?>">
									<?= $general->generateSelectOptions($srcOfReqList, null, "--Select--"); ?>
								</select>
							</td>
							<td><strong>
									<?php echo _translate("Community Sample"); ?>&nbsp;:
								</strong></td>
							<td>
								<select name="communitySample" id="communitySample" class="form-control"
									title="<?php echo _translate('Please choose community sample'); ?>"
									style="width:100%;">
									<option value="">
										<?php echo _translate("-- Select --"); ?>
									</option>
									<option value="yes">
										<?php echo _translate("Yes"); ?>
									</option>
									<option value="no">
										<?php echo _translate("No"); ?>
									</option>
								</select>
							</td>

							<td><strong>
									<?php echo _translate("Province/State"); ?>&nbsp;:
								</strong></td>
							<td>
								<select name="state" id="state" onchange="getByProvince(this.value)"
									class="form-control"
									title="<?php echo _translate('Please choose Province/State/Region'); ?>"
									onkeyup="searchVlRequestData()">
									<?= $general->generateSelectOptions($state, null, _translate("-- Select --")); ?>
								</select>
							</td>
						</tr>
						<tr>

							<td><strong>
									<?php echo _translate("District/County"); ?> :
								</strong></td>
							<td>
								<select class="form-control" id="district" onchange="getByDistrict(this.value)"
									name="district" title="<?php echo _translate('Please select District/County'); ?>">
								</select>
							</td>
							<td><strong>
									<?php echo _translate("Facility Name"); ?> :
								</strong></td>
							<td>
								<select class="form-control" id="facilityName" name="facilityName" multiple="multiple"
									title="<?php echo _translate('Please select facility name'); ?>"
									style="width:100%;">
									<?= $facilitiesDropdown; ?>
								</select>
							</td>
							<td><strong>
									<?php echo _translate("Testing Lab"); ?> :
								</strong></td>
							<td>
								<select class="form-control" id="vlLab" name="vlLab"
									title="<?php echo _translate('Please select Testing Lab'); ?>" style="width:220px;">
									<?= $testingLabsDropdown; ?>
								</select>
							</td>
						</tr>
						<tr>
							<td><strong>
									<?php echo _translate("Export with Patient ID and Name"); ?>&nbsp;:
								</strong></td>
							<td>
								<select name="patientInfo" id="patientInfo" class="form-control"
									title="<?php echo _translate('Please choose community sample'); ?>"
									style="width:100%;">
									<option value="yes">
										<?php echo _translate("Yes"); ?>
									</option>
									<option value="no">
										<?php echo _translate("No"); ?>
									</option>
								</select>

							</td>
							<td><strong>
									<?php echo _translate("Patient ID"); ?>&nbsp;:
								</strong></td>
							<td>
								<input type="text" id="patientId" name="patientId" class="form-control"
									placeholder="<?php echo _translate('Enter Patient ID'); ?>"
									style="background:#fff;" />
							</td>
							<td><strong>
									<?php echo _translate("Patient Name"); ?>&nbsp;:
								</strong></td>
							<td>
								<input type="text" id="patientName" name="patientName" class="form-control"
									placeholder="<?php echo _translate('Enter Patient Name'); ?>"
									style="background:#fff;" />
							</td>
						</tr>

						<tr>
							<?php if (!empty(SYSTEM_CONFIG['recency']['crosslogin']) && SYSTEM_CONFIG['recency']['crosslogin'] === true) { ?>
								<td><strong>
										<?php echo _translate("Include Recency Samples"); ?>&nbsp;:
									</strong></td>
								<td>
									<select name="recencySamples" id="recencySamples" class="form-control"
										title="<?php echo _translate('Include Recency Samples'); ?>" style="width:100%;">
										<option value="yes">
											<?php echo _translate("Yes"); ?>
										</option>
										<option value="no" selected="selected">
											<?php echo _translate("No"); ?>
										</option>
									</select>
								</td>
							<?php } ?>
							<td><strong>
									<?php echo _translate("Show Rejected Samples"); ?>&nbsp;:
								</strong></td>
							<td>
								<select name="rejectedSamples" id="rejectedSamples" class="form-control"
									title="<?php echo _translate('Please choose show rejected samples'); ?>"
									style="width:100%;">
									<option value="yes" selected="selected">
										<?php echo _translate("Yes"); ?>
									</option>
									<option value="no">
										<?php echo _translate("No"); ?>
									</option>
								</select>
							</td>
						</tr>

						<tr>
							<td colspan="2"><input type="button" onclick="searchVlRequestData();"
									value="<?= _translate('Search'); ?>" class="btn btn-default btn-sm">
								&nbsp;<button class="btn btn-danger btn-sm"
									onclick="document.location.href = document.location"><span>
										<?= _translate('Reset'); ?>
									</span></button>
								&nbsp;<button class="btn btn-danger btn-sm"
									onclick="hideAdvanceSearch('advancedFilters','filter');"><span>
										<?php echo _translate("Hide Advanced Search Options"); ?>
									</span></button>
							</td>
							<td colspan="4">
								<?php
								if (_isAllowed("/vl/requests/addVlRequest.php") && !$hidesrcofreq) { ?>
									<a href="/vl/requests/addVlRequest.php" class="btn btn-primary btn-sm pull-right"> <em
											class="fa-solid fa-plus"></em>
										<?php echo _translate("Add VL Request Form"); ?>
									</a>
								<?php }
								?>

								&nbsp;<button class="btn btn-primary btn-sm pull-right" style="margin-right:5px;"
									onclick="$('#showhide').fadeToggle();return false;"><span>
										<?php echo _translate("Manage Columns"); ?>
									</span></button>
								&nbsp;
								<?php
								if (_isAllowed("/vl/requests/export-vl-requests.php")) {
									?>
									<a class="btn btn-success btn-sm pull-right" style="margin-right:5px;"
										href="javascript:void(0);" onclick="exportTestRequests();"><em
											class="fa-solid fa-file-excel"></em>&nbsp;&nbsp;
										<?php echo _translate("Export Excel"); ?>
									</a>
								<?php } ?>
							</td>
						</tr>
					</table>
					<table aria-describedby="table" id="filter" class="table" aria-hidden="true"
						style="margin-left:1%;margin-top:20px;width: 98%;margin-bottom: 0px;">
						<tr id="">
							<td>

								<?php
								if (_isAllowed("/vl/requests/addVlRequest.php") && !$hidesrcofreq) { ?>
									<a href="/vl/requests/addVlRequest.php" class="btn btn-primary btn-sm pull-right"> <em
											class="fa-solid fa-plus"></em>
										<?php echo _translate("Add VL Request Form"); ?>
									</a>
								<?php }
								?>

								<?php
								if (_isAllowed("/vl/requests/vl-requests.php") && $formId == COUNTRY\DRC) { ?>
									<a href="/vl/requests/sample-storage.php" class="btn btn-primary btn-sm pull-right">
										<em class="fa-solid fa-jar"></em>&nbsp;
										<?php echo _translate("Samples Storage"); ?>
									</a>
								<?php }
								?>

								&nbsp;<button class="btn btn-primary btn-sm pull-right" style="margin-right:5px;"
									onclick="$('#showhide').fadeToggle();return false;"><span>
										<?php echo _translate("Manage Columns"); ?>
									</span></button>
								<?php if (_isAllowed("/vl/requests/export-vl-requests.php")) { ?>
									&nbsp;<a class="btn btn-success btn-sm pull-right" style="margin-right:5px;"
										href="javascript:void(0);" onclick="exportTestRequests();"><em
											class="fa-solid fa-file-excel"></em>&nbsp;&nbsp;
										<?php echo _translate("Export Excel"); ?>
									</a>
								<?php } ?>

								<?php if (!empty($interopConfig['FHIR']['url'])) { ?>
									&nbsp;<a class="btn btn-warning btn-sm pull-right" style="margin-right:5px;"
										href="javascript:void(0);" onclick="sendEMRDataToFHIR();"><em
											class="fa-solid fa-paper-plane"></em>
										<?php echo _translate("EMR/FHIR - SEND RESULTS"); ?>
									</a>
									&nbsp;<a class="btn btn-warning btn-sm pull-right" style="margin-right:5px;"
										href="javascript:void(0);" onclick="receiveEMRDataFromFHIR();"><em
											class="fa-solid fa-download"></em>
										<?php echo _translate("EMR/FHIR - GET TESTS"); ?>
									</a>
								<?php } ?>
								&nbsp;<button class="btn btn-primary btn-sm pull-right" style="margin-right:5px;"
									onclick="hideAdvanceSearch('filter','advancedFilters');"><span>
										<?php echo _translate("Show Advanced Search Options"); ?>
									</span></button>
							</td>
						</tr>
					</table>
					<span style="display: none;position:absolute;z-index: 9999 !important;color:#000;padding:5px;"
						id="showhide" class="">
						<div class="row" style="background:#e0e0e0;float: right !important;padding: 15px;">
							<div class="col-md-12">
								<div class="col-md-3">
									<input type="checkbox" onclick="fnShowHide(this.value);" value="0" id="iCol0"
										data-showhide="sample_code" class="showhideCheckBox" /> <label for="iCol0">
										<?php echo _translate("Sample ID"); ?>
									</label>
								</div>
								<?php $i = 0;
								if (!$general->isStandaloneInstance()) {
									$i = 1; ?>
									<div class="col-md-3">
										<input type="checkbox" onclick="fnShowHide(this.value);" value="<?php echo $i; ?>"
											id="iCol<?php echo $i; ?>" data-showhide="remote_sample_code"
											class="showhideCheckBox" /> <label
											for="iCol<?php echo $i; ?>"><?php echo _translate("Remote Sample ID"); ?></label>
									</div>
								<?php } ?>
								<div class="col-md-3">
									<input type="checkbox" onclick="fnShowHide(this.value);"
										value="<?php echo $i += 1; ?>" id="iCol<?php echo $i; ?>"
										data-showhide="sample_collection_date" class="showhideCheckBox" /> <label
										for="iCol<?php echo $i; ?>"><?php echo _translate("Sample Collection Date"); ?></label>
									<br>
								</div>
								<div class="col-md-3">
									<input type="checkbox" onclick="fnShowHide(this.value);"
										value="<?php echo $i += 1; ?>" id="iCol<?php echo $i; ?>"
										data-showhide="batch_code" class="showhideCheckBox" /> <label
										for="iCol<?php echo $i; ?>"><?php echo _translate("Batch Code"); ?></label>
								</div>
								<div class="col-md-3">
									<input type="checkbox" onclick="fnShowHide(this.value);"
										value="<?php echo $i += 1; ?>" id="iCol<?php echo $i; ?>"
										data-showhide="patient_art_no" class="showhideCheckBox" /> <label
										for="iCol<?php echo $i; ?>"><?php echo _translate("Art No"); ?></label> <br>
								</div>
								<div class="col-md-3">
									<input type="checkbox" onclick="fnShowHide(this.value);"
										value="<?php echo $i += 1; ?>" id="iCol<?php echo $i; ?>"
										data-showhide="patient_first_name" class="showhideCheckBox" /> <label
										for="iCol<?php echo $i; ?>"><?php echo _translate("Patient's Name"); ?></label>
								</div>
								<div class="col-md-3">
									<input type="checkbox" onclick="fnShowHide(this.value);"
										value="<?php echo $i += 1; ?>" id="iCol<?php echo $i; ?>"
										data-showhide="lab_name" class="showhideCheckBox" /> <label
										for="iCol<?php echo $i; ?>"><?php echo _translate("Testing Lab"); ?></label>
								</div>
								<div class="col-md-3">
									<input type="checkbox" onclick="fnShowHide(this.value);"
										value="<?php echo $i += 1; ?>" id="iCol<?php echo $i; ?>"
										data-showhide="facility_name" class="showhideCheckBox" /> <label
										for="iCol<?php echo $i; ?>"><?php echo _translate("Facility Name"); ?></label>
								</div>
								<div class="col-md-3">
									<input type="checkbox" onclick="fnShowHide(this.value);"
										value="<?php echo $i += 1; ?>" id="iCol<?php echo $i; ?>" data-showhide="state"
										class="showhideCheckBox" /> <label
										for="iCol<?php echo $i; ?>"><?php echo _translate("Province/State"); ?></label>
								</div>
								<div class="col-md-3">
									<input type="checkbox" onclick="fnShowHide(this.value);"
										value="<?php echo $i += 1; ?>" id="iCol<?php echo $i; ?>"
										data-showhide="district" class="showhideCheckBox" /> <label
										for="iCol<?php echo $i; ?>"><?php echo _translate("District/County"); ?></label>
								</div>
								<div class="col-md-3">
									<input type="checkbox" onclick="fnShowHide(this.value);"
										value="<?php echo $i += 1; ?>" id="iCol<?php echo $i; ?>"
										data-showhide="sample_name" class="showhideCheckBox" /> <label
										for="iCol<?php echo $i; ?>"><?php echo _translate("Sample Type"); ?></label>
								</div>
								<?php if ($formId == COUNTRY\CAMEROON) { ?>
									<div class="col-md-3">
										<input type="checkbox" onclick="fnShowHide(this.value);"
											value="<?php echo $i += 1; ?>" id="iCol<?php echo $i; ?>"
											data-showhide="health_insurance_code" class="showhideCheckBox" /> <label
											for="iCol<?php echo $i; ?>"><?php echo _translate("Health Insurance Code"); ?></label>
									</div>
									<div class="col-md-3">
										<input type="checkbox" onclick="fnShowHide(this.value);"
											value="<?php echo $i += 1; ?>" id="iCol<?php echo $i; ?>"
											data-showhide="lab_assigned_code" class="showhideCheckBox" /> <label
											for="iCol<?php echo $i; ?>"><?php echo _translate("Lab Assigned Code"); ?></label>
									</div>
								<?php } ?>
								<div class="col-md-3">
									<input type="checkbox" onclick="fnShowHide(this.value);"
										value="<?php echo $i += 1; ?>" id="iCol<?php echo $i; ?>" data-showhide="result"
										class="showhideCheckBox" /> <label
										for="iCol<?php echo $i; ?>"><?php echo _translate("Result"); ?></label>
								</div>
								<div class="col-md-3">
									<input type="checkbox" onclick="fnShowHide(this.value);"
										value="<?php echo $i += 1; ?>" id="iCol<?php echo $i; ?>"
										data-showhide="last_modified_datetime" class="showhideCheckBox" /> <label
										for="iCol<?php echo $i; ?>"><?php echo _translate("Last Modified Date"); ?></label>
								</div>
								<div class="col-md-3">
									<input type="checkbox" onclick="fnShowHide(this.value);"
										value="<?php echo $i += 1; ?>" id="iCol<?php echo $i; ?>"
										data-showhide="status_name" class="showhideCheckBox" /> <label
										for="iCol<?php echo $i; ?>"><?php echo _translate("Status"); ?></label>
								</div>
							</div>
						</div>
					</span>
					<!-- /.box-header -->
					<div class="box-body">
						<table aria-describedby="table" id="vlRequestDataTable"
							class="table table-bordered table-striped" aria-hidden="true">
							<thead>
								<tr>
									<!--<th><input type="checkbox" id="checkTestsData" onclick="toggleAllVisible()"/></th>-->
									<th>
										<?php echo _translate("Sample ID"); ?>
									</th>
									<?php if (!$general->isStandaloneInstance()) { ?>
										<th>
											<?php echo _translate("Remote Sample ID"); ?>
										</th>
									<?php } ?>
									<th>
										<?php echo _translate("Sample Collection Date"); ?>
									</th>
									<th>
										<?php echo _translate("Batch Code"); ?>
									</th>
									<th>
										<?php echo _translate("Unique ART No"); ?>
									</th>
									<th>
										<?php echo _translate("Patient's Name"); ?>
									</th>
									<th scope="row">
										<?php echo _translate("Testing Lab"); ?>
									</th>
									<th scope="row">
										<?php echo _translate("Facility Name"); ?>
									</th>
									<th>
										<?php echo _translate("Province/State"); ?>
									</th>
									<th>
										<?php echo _translate("District/County"); ?>
									</th>
									<th>
										<?php echo _translate("Sample Type"); ?>
									</th>
									<?php if ($formId == COUNTRY\CAMEROON) { ?>
										<th>
											<?php echo _translate("Health Insurance Code"); ?>
										</th>
										<th>
											<?php echo _translate("Lab Assigned Code"); ?>
										</th>
									<?php } ?>
									<th>
										<?php echo _translate("Result"); ?>
									</th>
									<th>
										<?php echo _translate("Last Modified Date"); ?>
									</th>
									<th scope="row">
										<?php echo _translate("Status"); ?>
									</th>
									<?php if ((_isAllowed("/vl/requests/editVlRequest.php")) && !$hidesrcofreq) { ?>
										<th>
											<?php echo _translate("Action"); ?>
										</th>
									<?php } ?>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td colspan="16" class="dataTables_empty">
										<?php echo _translate("Loading data from server"); ?>
									</td>
								</tr>
							</tbody>
						</table>
						<?php
						if (isset($global['bar_code_printing']) && $global['bar_code_printing'] == 'zebra-printer') {
							?>

							<div id="printer_data_loading" style="display:none"><span id="loading_message">
									<?php echo _translate("Loading Printer Details"); ?>...
								</span><br />
								<div class="progress" style="width:100%">
									<div class="progress-bar progress-bar-striped active" role="progressbar"
										aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="width: 100%">
									</div>
								</div>
							</div> <!-- /printer_data_loading -->
							<div id="printer_details" style="display:none">
								<span id="selected_printer">
									<?php echo _translate("No printer selected!"); ?>
								</span>
								<button type="button" class="btn btn-success" onclick="changePrinter()">
									<?php echo _translate("Change/Retry"); ?>
								</button>
							</div><br /> <!-- /printer_details -->
							<div id="printer_select" style="display:none">
								<?php echo _translate("Zebra Printer Options"); ?><br />
								<?php echo _translate("Printer"); ?>: <select id="printers"></select>
							</div> <!-- /printer_select -->

							<?php
						}
						?>

					</div>

				</div>
				<!-- /.box -->

			</div>
			<!-- /.col -->
		</div>
		<!-- /.row -->
	</section>
	<!-- /.content -->
</div>
<script src="/assets/js/moment.min.js"></script>
<script type="text/javascript" src="/assets/plugins/daterangepicker/daterangepicker.js"></script>
<script type="text/javascript" src="/assets/js/tooltipster.bundle.min.js"></script>

<?= CommonService::barcodeScripts(); ?>



<script type="text/javascript">
	let searchExecuted = false;
	let startDate = "";
	let endDate = "";
	let selectedTests = [];
	let selectedTestsId = [];
	let oTable = null;

	$(document).ready(function () {

		$("#batchCode").autocomplete({
			source: function (request, response) {
				// Fetch data
				$.ajax({
					url: "/batch/getBatchCodeHelper.php",
					type: 'post',
					dataType: "json",
					data: {
						search: request.term,
						type: 'vl'
					},
					success: function (data) {
						response(data);
					}

				});
			}
		});

		<?php
		if (isset($_GET['barcode']) && $_GET['barcode'] == 'true') {
			$sampleCode = htmlspecialchars((string) $_GET['s']);
			$facilityCode = htmlspecialchars((string) $_GET['f']);
			$patientID = htmlspecialchars((string) $_GET['p']);
			echo "printBarcodeLabel('$sampleCode','$facilityCode','$patientID');";
		}
		?>
		$("#facilityName").select2({
			placeholder: "<?php echo _translate("Select Facilities"); ?>"
		});
		$("#vlLab").select2({
			placeholder: "<?php echo _translate("Select Testing Lab"); ?>"
		});
		$('#sampleCollectionDate, #sampleReceivedDateAtLab, #sampleTestedDate, #printDate, #requestCreatedDatetime').daterangepicker({
			locale: {
				cancelLabel: "<?= _translate("Clear", true); ?>",
				format: 'DD-MMM-YYYY',
				separator: ' to ',
			},
			showDropdowns: true,
			alwaysShowCalendars: false,
			startDate: moment().subtract(28, 'days'),
			endDate: moment(),
			maxDate: moment(),
			ranges: {
				'Today': [moment(), moment()],
				'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
				'Last 7 Days': [moment().subtract(6, 'days'), moment()],
				'This Month': [moment().startOf('month'), moment().endOf('month')],
				'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
				'Last 30 Days': [moment().subtract(29, 'days'), moment()],
				'Last 90 Days': [moment().subtract(89, 'days'), moment()],
				'Last 120 Days': [moment().subtract(119, 'days'), moment()],
				'Last 180 Days': [moment().subtract(179, 'days'), moment()],
				'Last 12 Months': [moment().subtract(12, 'month').startOf('month'), moment().endOf('month')],
				'Previous Year': [moment().subtract(1, 'year').startOf('year'), moment().subtract(1, 'year').endOf('year')],
				'Current Year To Date': [moment().startOf('year'), moment()]
			}
		},
			function (start, end) {
				startDate = start.format('YYYY-MM-DD');
				endDate = end.format('YYYY-MM-DD');
			});
		<?php if ((!empty($_GET['daterange']) && isset($_GET['type']) && $_GET['type'] == 'rejection')) { ?>
			$('#sampleReceivedDateAtLab, #sampleTestedDate, #printDate, #requestCreatedDatetime').val("");
			$('#sampleCollectionDate').val('<?php echo $_GET['daterange']; ?>');
		<?php } else { ?>
			$('#sampleCollectionDate, #sampleReceivedDateAtLab, #sampleTestedDate, #printDate, #requestCreatedDatetime').val("");
		<?php } ?>



		loadVlRequestData();

		$(".showhideCheckBox").change(function () {
			if ($(this).attr('checked')) {
				idpart = $(this).attr('data-showhide');
				$("#" + idpart + "-sort").show();
			} else {
				idpart = $(this).attr('data-showhide');
				$("#" + idpart + "-sort").hide();
			}
		});

		$("#showhide").hover(function () { }, function () {
			$(this).fadeOut('slow')
		});
		var i = '<?php echo $i; ?>';
		for (colNo = 0; colNo <= i; colNo++) {
			$("#iCol" + colNo).attr("checked", oTable.fnSettings().aoColumns[parseInt(colNo)].bVisible);
			if (oTable.fnSettings().aoColumns[colNo].bVisible) {
				$("#iCol" + colNo + "-sort").show();
			} else {
				$("#iCol" + colNo + "-sort").hide();
			}
		}
		$("#advancedFilters input, #advancedFilters select").on("change", function () {
			searchExecuted = false;
		});

	});

	function fnShowHide(iCol) {
		var bVis = oTable.fnSettings().aoColumns[iCol].bVisible;
		oTable.fnSetColumnVis(iCol, bVis ? false : true);
	}

	function loadVlRequestData() {

		oTable = $('#vlRequestDataTable').dataTable({
			"bJQueryUI": false,
			"bAutoWidth": false,
			"bInfo": true,
			"bScrollCollapse": true,
			//"bStateSave" : true,
			"bRetrieve": true,
			"aoColumns": [

				{
					"sClass": "center"
				},
				<?php if (!$general->isStandaloneInstance()) { ?> {
						"sClass": "center"
					},
				<?php } ?> {
					"sClass": "center"
				},
				{
					"sClass": "center"
				},
				{
					"sClass": "center"
				},
				{
					"sClass": "center"
				},
				{
					"sClass": "center"
				},
				{
					"sClass": "center"
				},
				{
					"sClass": "center"
				},
				{
					"sClass": "center"
				},
				{
					"sClass": "center"
				},
				<?php
				if ($formId == COUNTRY\CAMEROON) {
					echo '{
						"sClass": "center",
						"bVisible": false
					},
					{
						"sClass": "center",
						"bVisible": false
					},';
				}
				?> {
					"sClass": "center"
				},
				{
					"sClass": "center"
				},
				{
					"sClass": "center"
				},
				<?php if (_isAllowed("/vl/requests/editVlRequest.php") && !$hidesrcofreq) { ?> {
						"sClass": "center",
						"bSortable": false
					},
				<?php } ?>
			],
			"order": [
				[<?= $lastModifiedColumnPosition; ?>, "desc"],
				[<?= $sampleColumnToSort; ?>, "desc"]
			],
			"fnDrawCallback": function () {
				var checkBoxes = document.getElementsByName("chk[]");
				len = checkBoxes.length;
				for (c = 0; c < len; c++) {
					if (jQuery.inArray(checkBoxes[c].id, selectedTestsId) != -1) {
						checkBoxes[c].setAttribute("checked", true);
					}
				}
				$('.top-tooltip').tooltipster({
					contentAsHTML: true
				});
			},
			"bProcessing": true,
			"bServerSide": true,
			"sAjaxSource": "/vl/requests/get-request-list.php",
			"fnServerData": function (sSource, aoData, fnCallback) {
				aoData.push({
					"name": "batchCode",
					"value": $("#batchCode").val()
				});
				aoData.push({
					"name": "sampleCollectionDate",
					"value": $("#sampleCollectionDate").val()
				});
				aoData.push({
					"name": "requestCreatedDatetime",
					"value": $("#requestCreatedDatetime").val()
				});
				aoData.push({
					"name": "facilityName",
					"value": $("#facilityName").val()
				});
				aoData.push({
					"name": "sampleType",
					"value": $("#sampleType").val()
				});
				aoData.push({
					"name": "district",
					"value": $("#district").val()
				});
				aoData.push({
					"name": "printDate",
					"value": $("#printDate").val()
				});
				aoData.push({
					"name": "vLoad",
					"value": $("#vLoad").val()
				});

				aoData.push({
					"name": "communitySample",
					"value": $("#communitySample").val()
				});
				aoData.push({
					"name": "vlLab",
					"value": $("#vlLab").val()
				});
				aoData.push({
					"name": "gender",
					"value": $("#gender").val()
				});
				aoData.push({
					"name": "status",
					"value": $("#status").val()
				});
				aoData.push({
					"name": "showReordSample",
					"value": $("#showReordSample").val()
				});
				aoData.push({
					"name": "patientPregnant",
					"value": $("#patientPregnant").val()
				});
				aoData.push({
					"name": "breastFeeding",
					"value": $("#breastFeeding").val()
				});
				aoData.push({
					"name": "fundingSource",
					"value": $("#fundingSource").val()
				});
				aoData.push({
					"name": "implementingPartner",
					"value": $("#implementingPartner").val()
				});
				aoData.push({
					"name": "state",
					"value": $("#state").val()
				});
				aoData.push({
					"name": "reqSampleType",
					"value": $("#requestSampleType").val()
				});
				aoData.push({
					"name": "sampleReceivedDateAtLab",
					"value": $("#sampleReceivedDateAtLab").val()
				});
				aoData.push({
					"name": "sampleTestedDate",
					"value": $("#sampleTestedDate").val()
				});
				aoData.push({
					"name": "srcOfReq",
					"value": $("#srcOfReq").val()
				});
				aoData.push({
					"name": "dateRangeModel",
					"value": '<?php echo $dateRange; ?>'
				});
				aoData.push({
					"name": "patientId",
					"value": $("#patientId").val()
				});
				aoData.push({
					"name": "patientName",
					"value": $("#patientName").val()
				});
				aoData.push({
					"name": "labIdModel",
					"value": '<?php echo $labName; ?>'
				});
				aoData.push({
					"name": "srcOfReqModel",
					"value": '<?php echo $srcOfReq; ?>'
				});
				aoData.push({
					"name": "srcStatus",
					"value": '<?php echo $srcStatus; ?>'
				});
				aoData.push({
					"name": "hidesrcofreq",
					"value": '<?php echo $hidesrcofreq; ?>'
				});
				aoData.push({
					"name": "recencySamples",
					"value": $("#recencySamples").val()
				});
				aoData.push({
					"name": "rejectedSamples",
					"value": $("#rejectedSamples").val()
				});
				$.ajax({
					"dataType": 'json',
					"type": "POST",
					"url": sSource,
					"data": aoData,
					"success": fnCallback
				});
			}
		});
	}

	function searchVlRequestData() {
		searchExecuted = true;
		$.blockUI();
		oTable.fnDraw();
		$.unblockUI();
	}

	function loadVlRequestStateDistrict() {
		oTable.fnDraw();
	}

	function toggleTest(obj) {
		if ($(obj).is(':checked')) {
			if ($.inArray(obj.value, selectedTests) == -1) {
				selectedTests.push(obj.value);
				selectedTestsId.push(obj.id);
			}
		} else {
			selectedTests.splice($.inArray(obj.value, selectedTests), 1);
			selectedTestsId.splice($.inArray(obj.id, selectedTestsId), 1);
			$("#checkTestsData").attr("checked", false);
		}
		$("#checkedTests").val(selectedTests.join());
		if (selectedTests.length != 0) {
			$("#status").prop('disabled', false);
		} else {
			$("#status").prop('disabled', true);
		}
	}

	function toggleAllVisible() {
		//alert(tabStatus);
		$(".checkTests").each(function () {
			$(this).prop('checked', false);
			selectedTests.splice($.inArray(this.value, selectedTests), 1);
			selectedTestsId.splice($.inArray(this.id, selectedTestsId), 1);
			$("#status").prop('disabled', true);
		});
		if ($("#checkTestsData").is(':checked')) {
			$(".checkTests").each(function () {
				$(this).prop('checked', true);
				selectedTests.push(this.value);
				selectedTestsId.push(this.id);
			});
			$("#status").prop('disabled', false);
		} else {
			$(".checkTests").each(function () {
				$(this).prop('checked', false);
				selectedTests.splice($.inArray(this.value, selectedTests), 1);
				selectedTestsId.splice($.inArray(this.id, selectedTestsId), 1);
				$("#status").prop('disabled', true);
			});
		}
		$("#checkedTests").val(selectedTests.join());
	}

	function submitTestStatus() {
		var stValue = $("#status").val();
		var testIds = $("#checkedTests").val();
		if (stValue != '' && testIds != '') {
			conf = confirm("<?= _translate("Are you sure you want to modify the sample status?", true); ?>");
			if (conf) {
				$.post("/vl/results/updateTestStatus.php", {
					status: stValue,
					id: testIds,
					format: "html"
				},
					function (data) {
						if (data != "") {
							$("#checkedTests").val('');
							selectedTests = [];
							selectedTestsId = [];
							$("#checkTestsData").attr("checked", false);
							$("#status").val('');
							$("#status").prop('disabled', true);
							oTable.fnDraw();
							alert("<?php echo _translate("Updated successfully."); ?>");
						}
					});
			}
		} else {
			alert("<?php echo _translate("Please checked atleast one checkbox."); ?>");
		}
	}

	function exportTestRequests() {

		const exportButton = $('[onclick="exportTestRequests();"]');
		const originalButtonHtml = exportButton.html();

		exportButton.html('<i class="fa fa-spinner fa-spin"></i> <?php echo _translate("Generating..."); ?>').prop('disabled', true);
		if (searchExecuted === false) {
			searchVlRequestData();
		}
		$.blockUI();
		$.post("/vl/requests/export-vl-requests.php", {
			reqSampleType: $('#requestSampleType').val(),
			patientInfo: $('#patientInfo').val(),
		},
			function (data) {
				$.unblockUI();
				exportButton.html(originalButtonHtml).prop('disabled', false);
				if (data === "" || data === null || data === undefined) {
					alert("<?php echo _translate("Unable to generate the excel file"); ?>");
				} else {
					window.open('/download.php?d=a&f=' + data, '_blank');
				}
			});
	}


	function hideAdvanceSearch(hideId, showId) {
		$("#" + hideId).hide();
		$("#" + showId).show();
	}

	<?php if ($general->isLISInstance()) { ?>
		let remoteURL = '<?php echo $general->getRemoteURL(); ?>';

		function forceResultSync(sampleCode) {
			$.blockUI({
				message: "<h3><?php echo _translate("Trying to sync"); ?> " + sampleCode + "<br><?php echo _translate("Please wait", true); ?>...</h3>"
			});

			if (remoteSync && remoteURL != null && remoteURL != '') {
				var jqxhr = $.ajax({
					url: "/tasks/remote/results-sender.php?sampleCode=" + sampleCode + "&forceSyncModule=vl",
				})
					.done(function (data) {
						//console.log(data);
						//alert( "success" );
					})
					.fail(function () {
						$.unblockUI();
					})
					.always(function () {
						oTable.fnDraw();
						$.unblockUI();
					});
			}
		}
	<?php } ?>

	function receiveEMRDataFromFHIR() {
		$.blockUI({
			message: "<h3><?php echo _translate("Trying to sync from EMR/FHIR"); ?> " + "<br><?php echo _translate("Please wait", true); ?>...</h3>"
		});


		var jqxhr = $.ajax({
			url: "/vl/interop/fhir/vl-receive.php",
		})
			.done(function (data) {
				//console.log(data);
				//alert( "success" );
				$.unblockUI();
				//alert(data.processed + " records added from EMR/FHIR");
				alert("EMR/FHIR sync completed");
				if (data.error) {
					alert(data.error);
				}
				oTable.fnDraw();
				$.unblockUI();
			})
			.fail(function () {
				$.unblockUI();
			})
			.always(function () {
				oTable.fnDraw();
				$.unblockUI();
			});

	}

	function sendEMRDataToFHIR() {
		$.blockUI({
			message: "<h3><?php echo _translate("Trying to sync to EMR/FHIR"); ?> " + "<br><?php echo _translate("Please wait", true); ?>...</h3>"
		});

		var jqxhr = $.ajax({
			url: "/vl/interop/fhir/vl-send.php",
		})
			.done(function (data) {
				////console.log(data);
				//alert( "success" );
				$.unblockUI();
				alert(data.processed + " records sent to EMR/FHIR");
				if (data.error) {
					alert(data.error);
				}
				oTable.fnDraw();
				$.unblockUI();
			})
			.fail(function () {
				$.unblockUI();
			})
			.always(function () {
				oTable.fnDraw();
				$.unblockUI();
			});

	}

	function getByProvince(provinceId) {
		$("#district").html('');
		$("#facilityName").html('');
		$("#vlLab").html('');
		$.post("/common/get-by-province-id.php", {
			provinceId: provinceId,
			districts: true,
			facilities: true,
		},
			function (data) {
				Obj = $.parseJSON(data);
				$("#district").html(Obj['districts']);
				$("#facilityName").html(Obj['facilities']);
				$("#vlLab").html(Obj['labs']);
			});
	}

	function getByDistrict(districtId) {
		$("#facilityName").html('');
		$("#vlLab").html('');
		$.post("/common/get-by-district-id.php", {
			districtId: districtId,
			facilities: true,
			labs: true,
		},
			function (data) {
				Obj = $.parseJSON(data);
				$("#facilityName").html(Obj['facilities']);
				$("#vlLab").html(Obj['labs']);
			});
	}

	function hideFemaleDetails(value) {
		if (value == 'female') {
			$("#patientPregnant").attr("disabled", false);
			$("#breastFeeding").attr("disabled", false);
		} else {
			$('select#patientPregnant').val('');
			$('select#breastFeeding').val('');
			$("#patientPregnant").attr("disabled", true);
			$("#breastFeeding").attr("disabled", true);
		}
	}
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';
