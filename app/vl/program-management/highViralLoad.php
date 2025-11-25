<?php

use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use App\Services\GeoLocationsService;

$title = _translate("VL | Clinics Report");

require_once APPLICATION_PATH . '/header.php';

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var GeoLocationsService $geolocationService */
$geolocationService = ContainerRegistry::get(GeoLocationsService::class);

$tsQuery = "SELECT * FROM r_sample_status";
$tsResult = $db->rawQuery($tsQuery);

$sQuery = "SELECT * FROM r_vl_sample_type where status='active'";
$sResult = $db->rawQuery($sQuery);

$fQuery = "SELECT * FROM facility_details WHERE status='active' Order By facility_name";

if (!empty($_SESSION['facilityMap'])) {
	$fQuery .= " AND facility_id IN (" . $_SESSION['facilityMap'] . ")";
}
$fResult = $db->rawQuery($fQuery);

$batQuery = "SELECT batch_code FROM batch_details where test_type = 'vl' AND batch_status='completed'";
$batResult = $db->rawQuery($batQuery);
//sample rejection reason
$condition = "rejection_reason_status ='active'";
$rejectionResult = $general->fetchDataFromTable('r_vl_sample_rejection_reasons', $condition);

//rejection type
$rejectionTypeQuery = "SELECT DISTINCT rejection_type FROM r_vl_sample_rejection_reasons WHERE rejection_reason_status ='active'";
$rejectionTypeResult = $db->rawQuery($rejectionTypeQuery);

$state = $geolocationService->getProvinces("yes");

?>
<style>
	.select2-selection__choice {
		color: #000000 !important;
	}

	#container {
		height: 600px;
	}

	.highcharts-figure,
	.highcharts-data-table table {
		min-width: 310px;
		max-width: 1000px;

	}

	.highcharts-data-table table {
		font-family: Verdana, sans-serif;
		border-collapse: collapse;
		border: 1px solid #ebebeb;
		margin: 10px auto;
		text-align: center;
		width: 100%;
		max-width: 500px;
	}

	.highcharts-data-table caption {
		padding: 1em 0;
		font-size: 1.2em;
		color: #555;
	}

	.highcharts-data-table th {
		font-weight: 600;
		padding: 0.5em;
	}

	.highcharts-data-table td,
	.highcharts-data-table th,
	.highcharts-data-table caption {
		padding: 0.5em;
	}

	.highcharts-data-table thead tr,
	.highcharts-data-table tr:nth-child(even) {
		background: #f8f8f8;
	}

	.highcharts-data-table tr:hover {
		background: #f1f7ff;
	}

	.bs-tabs .nav>li>a {
		position: relative;
		display: block;
		padding: 10px 7px;
	}

	#myTab li a {
		text-align: center;
		/* Center the text */
		white-space: normal;
		/* Allow wrapping and line breaks */
	}
</style>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1> <em class="fa-solid fa-book"></em>
			<?php echo _translate("Clinic Reports"); ?>
		</h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em>
					<?php echo _translate("Home"); ?>
				</a></li>
			<li class="active">
				<?php echo _translate("Clinic Reports"); ?>
			</li>
		</ol>
	</section>
	<!-- Main content -->
	<section class="content">
		<div class="row">
			<div class="col-xs-12">
				<div class="box">
					<!-- /.box-header -->
					<div class="box-body">
						<div class="widget">
							<div class="widget-content">
								<div class="bs bs-tabs">
									<ul id="myTab" class="nav nav-tabs">
										<li class="active"><a href="#highViralLoadReport"
												data-toggle="tab"><?php echo _translate("High Viral Load"); ?><br><?php echo _translate("Report"); ?></a>
										</li>
										<li><a href="#highVlVirologicFailureReport"
												data-toggle="tab"><?php echo _translate("High VL and Virologic Failure"); ?><br><?php echo _translate("Report"); ?></a>
										</li>
										<li><a href="#sampleRjtReport"
												data-toggle="tab"><?php echo _translate("Sample Rejection"); ?><br><?php echo _translate("Report"); ?></a>
										</li>
										<li><a href="#notAvailReport"
												data-toggle="tab"><?php echo _translate("Results Not Available"); ?><br><?php echo _translate("Report"); ?></a>
										</li>
										<li><a href="#incompleteFormReport"
												data-toggle="tab"><?php echo _translate("Data Quality Check"); ?><br><?php echo _translate("Report"); ?></a>
										</li>
										<li><a href="#sampleTestingReport"
												data-toggle="tab"><?php echo _translate("Sample Testing"); ?><br><?php echo _translate("Report"); ?></a>
										</li>
										<li><a href="#patientTestHistoryFormReport"
												data-toggle="tab"><?php echo _translate("Patient Test History"); ?><br><?php echo _translate("Report"); ?></a>
										</li>
									</ul>
									<div id="myTabContent" class="tab-content">
										<div class="tab-pane fade in active" id="highViralLoadReport">
											<table aria-describedby="table" class="table pageFilters" aria-hidden="true"
												style="margin-left:1%;margin-top:20px;width:98%;padding: 3%;">
												<tr>
													<td style="width: 10%;"><strong>
															<?php echo _translate("Sample Test Date"); ?>&nbsp;:
														</strong></td>
													<td style="width: 23.33%;">
														<input type="text" id="hvlSampleTestDate"
															name="hvlSampleTestDate"
															class="form-control highViralLoadReportFilter stDate"
															placeholder="<?php echo _translate('Select Sample Test Date'); ?>"
															readonly style="width:100%;background:#fff;"
															onchange="setSampleTestDate(this)" />
													</td>
													<td style="width: 10%;"><strong>
															<?php echo _translate("Batch Code"); ?>&nbsp;:
														</strong></td>
													<td style="width: 23.33%;">
														<select
															class="form-control select2Class highViralLoadReportFilter"
															id="hvlBatchCode" name="hvlBatchCode"
															title="<?php echo _translate('Please select batch code'); ?>"
															style="width:100%;">
															<option value="">
																<?php echo _translate("-- Select --"); ?>
															</option>
															<?php foreach ($batResult as $code) { ?>
																<option value="<?php echo $code['batch_code']; ?>">
																	<?php echo $code['batch_code']; ?>
																</option>
															<?php } ?>
														</select>
													</td>
													<td style="width: 10%;"><strong>
															<?php echo _translate("Sample Type"); ?>&nbsp;:
														</strong></td>
													<td style="width: 23.33%;">
														<select style="width:100%;"
															class="form-control highViralLoadReportFilter"
															id="hvlSampleType" name="sampleType"
															title="<?php echo _translate('Please select sample type'); ?>">
															<option value="">
																<?php echo _translate("-- Select --"); ?>
															</option>
															<?php foreach ($sResult as $type) { ?>
																<option value="<?php echo $type['sample_id']; ?>">
																	<?= $type['sample_name']; ?>
																</option>
															<?php } ?>
														</select>
													</td>
												</tr>
												<tr>
													<td style="width: 10%;"><strong>
															<?php echo _translate("Province/State"); ?>&nbsp;:
														</strong></td>
													<td style="width: 23.33%;">
														<select class="form-control highViralLoadReportFilter"
															id="state"
															onchange="getByProvince('district','hvlFacilityName',this.value)"
															name="state"
															title="<?php echo _translate('Please select Province/State'); ?>">
															<?= $general->generateSelectOptions($state, null, _translate("-- Select --")); ?>
														</select>
													</td>

													<td style="width: 10%;"><strong>
															<?php echo _translate("District/County"); ?> :
														</strong></td>
													<td style="width: 23.33%;">
														<select class="form-control highViralLoadReportFilter"
															id="district" name="district"
															title="<?php echo _translate('Please select District/County'); ?>"
															onchange="getByDistrict('hvlFacilityName',this.value)">
														</select>
													</td>
													<td style="width: 10%;"><strong>
															<?php echo _translate("Facility"); ?>&nbsp;:
														</strong></td>
													<td style="width: 23.33%;">
														<select class="form-control highViralLoadReportFilter"
															id="hvlFacilityName" name="hvlFacilityName"
															multiple="multiple"
															title="<?php echo _translate('Please select facility name'); ?>"
															style="width:100%;">
															<option value="">
																<?php echo _translate("-- Select --"); ?>
															</option>
															<?php foreach ($fResult as $name) { ?>
																<option value="<?php echo $name['facility_id']; ?>">
																	<?php echo ($name['facility_name'] . " - " . $name['facility_code']); ?>
																</option>
															<?php } ?>
														</select>
													</td>
												</tr>
												<tr>
													<td style="width: 10%;"><strong>
															<?php echo _translate("Contact Status"); ?>&nbsp;:
														</strong></td>
													<td style="width: 23.33%;">
														<select class="form-control select2 highViralLoadReportFilter"
															id="hvlContactStatus" name="hvlContactStatus"
															title="<?php echo _translate('Please select contact status'); ?>"
															style="width:100%;">
															<option value="">
																<?php echo _translate("-- Select --"); ?>
															</option>
															<option value="yes">
																<?php echo _translate("Completed"); ?>
															</option>
															<option value="no">
																<?php echo _translate("Not Completed"); ?>
															</option>
															<option value="all" selected="selected">
																<?php echo _translate("All"); ?>
															</option>
														</select>
													</td>
													<td style="width: 10%;"><strong>
															<?php echo _translate("Sex"); ?>&nbsp;:
														</strong></td>
													<td style="width: 23.33%;">
														<select name="hvlGender" id="hvlGender"
															class="form-control select2 highViralLoadReportFilter"
															title="<?php echo _translate('Please select sex'); ?>"
															style="width:100%;"
															onchange="hideFemaleDetails(this.value,'hvlPatientPregnant','hvlPatientBreastfeeding');">
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
													<td style="width: 10%;"><strong>
															<?php echo _translate("Pregnant"); ?>&nbsp;:
														</strong></td>
													<td style="width: 23.33%;">
														<select name="hvlPatientPregnant" id="hvlPatientPregnant"
															class="form-control select2 highViralLoadReportFilter"
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
													</td>

												</tr>
												<tr>
													<td style="width: 10%;"><strong>
															<?php echo _translate("Breastfeeding"); ?>&nbsp;:
														</strong></td>
													<td style="width: 23.33%;">
														<select name="hvlPatientBreastfeeding"
															id="hvlPatientBreastfeeding"
															class="form-control select2 highViralLoadReportFilter"
															title="<?php echo _translate('Please choose option'); ?>">
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
													<td style="width: 10%;"><strong>
															<?php echo _translate("Export with Patient Name"); ?>&nbsp;:
														</strong></td>
													<td style="width: 23.33%;">
														<select name="patientInfo" id="patientInfo"
															class="form-control select2 highViralLoadReportFilter"
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
												</tr>
												<tr>
													<td colspan="6">&nbsp;<input type="button"
															onclick="searchVlRequestData();"
															value="<?= _translate('Search'); ?>"
															class="btn btn-success btn-sm">
														&nbsp;<button class="btn btn-danger btn-sm"
															onclick="resetFilters('highViralLoadReportFilter');"><span>
																<?= _translate('Reset'); ?>
															</span></button>
														<button class="btn btn-success btn-sm" type="button"
															onclick="exportHighViralLoadInexcel()"><em
																class="fa-solid fa-cloud-arrow-down"></em>
															<?php echo _translate("Export to excel"); ?>
														</button>
													</td>
												</tr>
											</table>

											<table aria-describedby="table" id="highViralLoadReportTable"
												class="table table-bordered table-striped" aria-hidden="true">
												<thead>
													<tr>
														<th>
															<?php echo _translate("Sample ID"); ?>
														</th>
														<?php if (!$general->isStandaloneInstance()) { ?>
															<th>
																<?php echo _translate("Remote Sample ID"); ?>
															</th>
														<?php } ?>
														<th scope="row">
															<?php echo _translate("Facility Name"); ?>
														</th>
														<th>
															<?php echo _translate("Patient ART no"); ?>.
														</th>
														<th>
															<?php echo _translate("Patient's Name"); ?>
														</th>
														<th>
															<?php echo _translate("Patient Phone no"); ?>.
														</th>
														<th scope="row">
															<?php echo _translate("Sample Collection Date"); ?>
														</th>
														<th>
															<?php echo _translate("Sample Tested Date"); ?>
														</th>
														<th>
															<?php echo _translate("Viral Load Lab"); ?>
														</th>
														<th>
															<?php echo _translate("Viral Load (cp/mL)"); ?>
														</th>
														<th scope="row">
															<?php echo _translate("Status"); ?>
														</th>
													</tr>
												</thead>
												<tbody>
													<tr>
														<td colspan="7" class="dataTables_empty">
															<?php echo _translate("Loading data from server"); ?>
														</td>
													</tr>
												</tbody>
											</table>
										</div>
										<div class="tab-pane fade" id="highVlVirologicFailureReport">
											<table aria-describedby="table" class="table pageFilters" aria-hidden="true"
												style="margin-left:1%;margin-top:20px;width:98%;">
												<tr>
													<td style="width: 10%;"><strong>
															<?php echo _translate("Province/State"); ?>&nbsp;:
														</strong></td>
													<td style="width: 23.33%;">
														<select
															class="form-control vfvlnsfilters select2 select2-element"
															id="vfVlnsState"
															onchange="getByProvince('vfVlnsDistrict','vfVlnsfacilityName',this.value)"
															name="vfVlnsState"
															title="<?php echo _translate('Please select Province/State'); ?>">
															<?= $general->generateSelectOptions($state, null, _translate("-- Select --")); ?>
														</select>
													</td>

													<td style="width: 10%;"><strong>
															<?php echo _translate("District/County"); ?> :
														</strong></td>
													<td style="width: 23.33%;">
														<select
															class="form-control vfvlnsfilters select2 select2-element"
															id="vfVlnsDistrict" name="vfVlnsDistrict"
															title="<?php echo _translate('Please select District/County'); ?>"
															onchange="getByDistrict('vfVlnsfacilityName',this.value)">
														</select>
													</td>
													<td style="width: 10%;">
														<strong><?php echo _translate("Facility"); ?> :</strong>
													</td>
													<td style="width: 23.33%;">
														<select class="form-control vfvlnsfilters"
															id="vfVlnsfacilityName" name="vfVlnsfacilityName"
															multiple="multiple"
															title="<?php echo _translate('Please select facility name'); ?>"
															style="width:220px;">
															<?php foreach ($fResult as $name) { ?>
																<option value="<?php echo $name['facility_id']; ?>">
																	<?php echo ($name['facility_name'] . " - " . $name['facility_code']); ?>
																</option>
															<?php } ?>
														</select>
													</td>
												</tr>
												<tr>
													<td style="width: 10%;">
														<strong><?php echo _translate("Sample Collection Date"); ?>&nbsp;:</strong>
													</td>
													<td style="width: 23.33%;">
														<input type="text" id="vfVlnsSampleCollectionDate"
															name="vfVlnsSampleCollectionDate"
															class="form-control vfvlnsfilters daterangefield"
															placeholder="<?php echo _translate('Select Collection Date'); ?>"
															style="background:#fff;" />
													</td>
													<td style="width: 10%;">
														<strong><?php echo _translate("Sample Tested Date"); ?>&nbsp;:</strong>
													</td>
													<td style="width: 23.33%;">
														<input type="text" id="vfVlnsSampleTestDate"
															name="vfVlnsSampleTestDate"
															class="form-control vfvlnsfilters daterangefield"
															placeholder="<?php echo _translate('Select Tested Date'); ?>"
															style="background:#fff;" />
													</td>
													<td style="width: 10%;"><strong>
															<?php echo _translate("Sex"); ?>&nbsp;:
														</strong></td>
													<td style="width: 23.33%;">
														<select name="vfvlnGender" id="vfvlnGender"
															class="form-control select2 vfvlnsfilters"
															title="<?php echo _translate('Please select sex'); ?>"
															style="width:100%;"
															onchange="hideFemaleDetails(this.value,'pregnancy','breastfeeding');">
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

												</tr>
												<tr>
													<td style="width: 10%;"><strong>
															<?php echo _translate("Pregnancy"); ?>&nbsp;:
														</strong></td>
													<td style="width: 23.33%;">
														<select
															class="form-control select2 select2-element vfvlnsfilters"
															id="pregnancy" name="pregnancy"
															title="<?php echo _translate('Please select pregnancy'); ?>">
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
													<td style="width: 10%;"><strong>
															<?php echo _translate("Breastfeeding"); ?>&nbsp;:
														</strong></td>
													<td style="width: 23.33%;">
														<select
															class="form-control select2 select2-element vfvlnsfilters"
															id="breastfeeding" name="breastfeeding"
															title="<?php echo _translate('Please select Province/State'); ?>">
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
													<td style="width: 10%;">
														<strong><?php echo _translate("Age Range"); ?>&nbsp;:</strong>
													</td>
													<td>
														<div class="col-sm-6">
															<input type="number" id="min_age"
																class="form-control vfvlnsfilters" name="min_age"
																min="0" max="120" value="0">
														</div>
														<div class="col-sm-6">
															<input type="number" id="max_age" name="max_age"
																class="form-control" min="0" max="120" value="120">
														</div>
													</td>
												</tr>
												<tr>
													<td colspan="6">
														&nbsp;<button onclick="vfVlnsExportInexcel();" value="Search"
															class="btn btn-success btn-sm"><em
																class="fa-solid fa-cloud-arrow-down"></em><span><?php echo _translate(" Generate report"); ?></span></button>
														&nbsp;<button class="btn btn-danger btn-sm"
															onclick="resetFilters('vfvlnsfilters');"><span><?php echo _translate("Reset"); ?></span></button>
													</td>
												</tr>
											</table>
										</div>
										<div class="tab-pane fade" id="sampleRjtReport">
											<table aria-describedby="table" class="table pageFilters" aria-hidden="true"
												style="margin-left:1%;margin-top:20px;width:98%;padding: 3%;">
												<tr>
													<td style="width: 10%;"><strong>
															<?php echo _translate("Sample Test Date"); ?>&nbsp;:
														</strong></td>
													<td style="width: 23.33%;">
														<input type="text" id="rjtSampleTestDate"
															name="rjtSampleTestDate"
															class="form-control sampleRjtReportFilter stDate daterange"
															placeholder="<?php echo _translate('Select Sample Test Date'); ?>"
															readonly style="width:100%;background:#fff;"
															onchange="setSampleTestDate(this)" />
													</td>
													<td style="width: 10%;"><strong>
															<?php echo _translate("Batch Code"); ?>&nbsp;:
														</strong></td>
													<td style="width: 23.33%;">
														<select class="form-control select2Class sampleRjtReportFilter"
															id="rjtBatchCode" name="rjtBatchCode"
															title="<?php echo _translate('Please select batch code'); ?>"
															style="width:100%;">
															<option value="">
																<?php echo _translate("-- Select --"); ?>
															</option>
															<?php
															foreach ($batResult as $code) {
																?>
																<option value="<?php echo $code['batch_code']; ?>">
																	<?php echo $code['batch_code']; ?>
																</option>
																<?php
															}
															?>
														</select>
													</td>
													<td style="width: 10%;"><strong>
															<?php echo _translate("Sample Type"); ?>&nbsp;:
														</strong></td>
													<td style="width: 23.33%;">
														<select style="width:100%;"
															class="form-control select2 sampleRjtReportFilter"
															id="rjtSampleType" name="sampleType"
															title="<?php echo _translate('Please select sample type'); ?>">
															<option value="">
																<?php echo _translate("-- Select --"); ?>
															</option>
															<?php
															foreach ($sResult as $type) {
																?>
																<option value="<?php echo $type['sample_id']; ?>">
																	<?= $type['sample_name']; ?>
																</option>
																<?php
															}
															?>
														</select>
													</td>
												</tr>
												<tr>
													<td style="width: 10%;"><strong>
															<?php echo _translate("Province/State"); ?>&nbsp;:
														</strong></td>
													<td style="width: 23.33%;">
														<select
															class="form-control sampleRjtReportFilter select2-element"
															id="rjtState"
															onchange="getByProvince('rjtDistrict','rjtFacilityName',this.value)"
															name="rjtState"
															title="<?php echo _translate('Please select Province/State'); ?>">
															<?= $general->generateSelectOptions($state, null, _translate("-- Select --")); ?>
														</select>
													</td>

													<td style="width: 10%;"><strong>
															<?php echo _translate("District/County"); ?> :
														</strong></td>
													<td style="width: 23.33%;">
														<select
															class="form-control sampleRjtReportFilter select2-element"
															id="rjtDistrict" name="rjtDistrict"
															title="<?php echo _translate('Please select District/County'); ?>"
															onchange="getByDistrict('rjtFacilityName',this.value)">
														</select>
													</td>
													<td style="width: 10%;"><strong>
															<?php echo _translate("Facility"); ?>&nbsp;:
														</strong></td>
													<td style="width: 23.33%;">
														<select class="form-control sampleRjtReportFilter"
															id="rjtFacilityName" name="facilityName"
															title="<?php echo _translate('Please select facility name'); ?>"
															multiple="multiple" style="width:100%;">
															<option value="">
																<?php echo _translate("-- Select --"); ?>
															</option>
															<?php
															foreach ($fResult as $name) {
																?>
																<option value="<?php echo $name['facility_id']; ?>">
																	<?php echo ($name['facility_name'] . " - " . $name['facility_code']); ?>
																</option>
																<?php
															}
															?>
														</select>
													</td>

												</tr>
												<tr>
													<td style="width: 10%;"><strong>
															<?php echo _translate("Sex"); ?>&nbsp;:
														</strong></td>
													<td style="width: 23.33%;">
														<select name="rjtGender" id="rjtGender"
															class="form-control select2 sampleRjtReportFilter"
															title="<?php echo _translate('Please select sex'); ?>"
															style="width:100%;"
															onchange="hideFemaleDetails(this.value,'rjtPatientPregnant','rjtPatientBreastfeeding');">
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
													<td style="width: 10%;"><strong>
															<?php echo _translate("Pregnant"); ?>&nbsp;:
														</strong></td>
													<td style="width: 23.33%;">
														<select name="rjtPatientPregnant" id="rjtPatientPregnant"
															class="form-control select2 sampleRjtReportFilter"
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
													</td>
													<td style="width: 10%;"><strong>
															<?php echo _translate("Breastfeeding"); ?>&nbsp;:
														</strong></td>
													<td style="width: 23.33%;">
														<select name="rjtPatientBreastfeeding"
															id="rjtPatientBreastfeeding"
															class="form-control select2 sampleRjtReportFilter"
															title="<?php echo _translate('Please choose option'); ?>">
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

												</tr>
												<tr>
													<td style="width: 10%;"><strong>
															<?php echo _translate("Rejection Reason"); ?>&nbsp;:
														</strong></td>
													<td colspan="3">
														<select name="rejectionReason" id="rejectionReason"
															class="form-control select2 sampleRjtReportFilter"
															title="Please choose reason"
															onchange="checkRejectionReason();">
															<option value="">-- Select --</option>
															<?php foreach ($rejectionTypeResult as $type) { ?>
																<optgroup
																	label="<?php echo strtoupper((string) $type['rejection_type']); ?>">
																	<?php foreach ($rejectionResult as $reject) {
																		if ($type['rejection_type'] == $reject['rejection_type']) {
																			?>
																			<option
																				value="<?php echo $reject['rejection_reason_id']; ?>">
																				<?= $reject['rejection_reason_name']; ?>
																			</option>
																		<?php }
																	} ?>
																</optgroup>
															<?php }
															if ($general->isLISInstance() === false) { ?>
																<option value="other">Other (Please Specify) </option>
															<?php } ?>
														</select>
													</td>
													<td style="width: 10%;"><strong>
															<?php echo _translate("Export with Patient Name"); ?>&nbsp;:
														</strong></td>
													<td style="width: 23.33%;">
														<select name="patientRejectedInfo" id="patientRejectedInfo"
															class="form-control select2 sampleRjtReportFilter"
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
												</tr>
												<tr>
													<td colspan="6">&nbsp;<input type="button"
															onclick="searchVlRequestData();"
															value="<?= _translate('Search'); ?>"
															class="btn btn-success btn-sm">
														&nbsp;<button class="btn btn-danger btn-sm"
															onclick="resetFilters('sampleRjtReportFilter');"><span>
																<?= _translate('Reset'); ?>
															</span></button>
														<button class="btn btn-success btn-sm" type="button"
															onclick="exportRejectedResultInexcel()"><em
																class="fa-solid fa-cloud-arrow-down"></em>
															<?php echo _translate("Export to excel"); ?>
														</button>
													</td>
												</tr>
											</table>
											<table aria-describedby="table" id="sampleRjtReportTable"
												class="table table-bordered table-striped" aria-hidden="true">
												<thead>
													<tr>
														<th>
															<?php echo _translate("Sample ID"); ?>
														</th>
														<?php if (!$general->isStandaloneInstance()) { ?>
															<th>
																<?php echo _translate("Remote Sample ID"); ?>
															</th>
														<?php } ?>
														<th scope="row">
															<?php echo _translate("Facility Name"); ?>
														</th>
														<th>
															<?php echo _translate("Patient ART no"); ?>.
														</th>
														<th>
															<?php echo _translate("Patient Name"); ?>
														</th>
														<th scope="row">
															<?php echo _translate("Sample Collection Date"); ?>
														</th>
														<th>
															<?php echo _translate("Testing Lab Name"); ?>
														</th>
														<th>
															<?php echo _translate("Rejection Reason"); ?>
														</th>
														<th>
															<?php echo _translate("Recommended Corrective Action"); ?>
														</th>
													</tr>
												</thead>
												<tbody>
													<tr>
														<td colspan="6" class="dataTables_empty">
															<?php echo _translate("Loading data from server"); ?>
														</td>
													</tr>
												</tbody>
											</table>
										</div>
										<div class="tab-pane fade" id="notAvailReport">
											<table aria-describedby="table" class="table pageFilters" aria-hidden="true"
												style="margin-left:1%;margin-top:20px;width:98%;padding: 3%;">
												<tr>
													<td style="width: 10%;"><strong>
															<?php echo _translate("Sample Collection Date"); ?>&nbsp;:
														</strong></td>
													<td style="width: 23.33%;">
														<input type="text" id="noResultSampleTestDate"
															name="noResultSampleTestDate"
															class="form-control notAvailReportFilter stDate daterange"
															placeholder="<?php echo _translate('Select Sample Collection Date'); ?>"
															readonly style="width:100%;background:#fff;"
															onchange="setSampleTestDate(this)" />
													</td>
													<td style="width: 10%;"><strong>
															<?php echo _translate("Batch Code"); ?>&nbsp;:
														</strong></td>
													<td style="width: 23.33%;">
														<select class="form-control select2Class notAvailReportFilter"
															id="noResultBatchCode" name="noResultBatchCode"
															title="<?php echo _translate('Please select batch code'); ?>"
															style="width:100%;">
															<option value="">
																<?php echo _translate("-- Select --"); ?>
															</option>
															<?php
															foreach ($batResult as $code) {
																?>
																<option value="<?php echo $code['batch_code']; ?>">
																	<?php echo $code['batch_code']; ?>
																</option>
																<?php
															}
															?>
														</select>
													</td>
													<td style="width: 10%;"><strong>
															<?php echo _translate("Sample Type"); ?>&nbsp;:
														</strong></td>
													<td style="width: 23.33%;">
														<select style="width:100%;"
															class="form-control select2 notAvailReportFilter"
															id="noResultSampleType" name="sampleType"
															title="<?php echo _translate('Please select sample type'); ?>">
															<option value="">
																<?php echo _translate("-- Select --"); ?>
															</option>
															<?php
															foreach ($sResult as $type) {
																?>
																<option value="<?php echo $type['sample_id']; ?>">
																	<?= $type['sample_name']; ?>
																</option>
																<?php
															}
															?>
														</select>
													</td>
												</tr>
												<tr>
													<td style="width: 10%;"><strong>
															<?php echo _translate("Province/State"); ?>&nbsp;:
														</strong></td>
													<td style="width: 23.33%;">
														<select
															class="form-control notAvailReportFilter select2-element"
															id="noResultState"
															onchange="getByProvince('noResultDistrict','noResultFacilityName',this.value)"
															name="rjtState"
															title="<?php echo _translate('Please select Province/State'); ?>">
															<?= $general->generateSelectOptions($state, null, _translate("-- Select --")); ?>
														</select>
													</td>

													<td style="width: 10%;"><strong>
															<?php echo _translate("District/County"); ?> :
														</strong></td>
													<td style="width: 23.33%;">
														<select
															class="form-control notAvailReportFilter select2-element"
															id="noResultDistrict" name="noResultDistrict"
															title="<?php echo _translate('Please select District/County'); ?>"
															onchange="getByDistrict('noResultFacilityName',this.value)">
														</select>
													</td>
													<td style="width: 10%;"><strong>
															<?php echo _translate("Facility"); ?>&nbsp;:
														</strong></td>
													<td style="width: 23.33%;">
														<select class="form-control notAvailReportFilter"
															id="noResultFacilityName" name="facilityName"
															title="<?php echo _translate('Please select facility name'); ?>"
															multiple="multiple" style="width:100%;">
															<option value="">
																<?php echo _translate("-- Select --"); ?>
															</option>
															<?php
															foreach ($fResult as $name) {
																?>
																<option value="<?php echo $name['facility_id']; ?>">
																	<?php echo ($name['facility_name'] . " - " . $name['facility_code']); ?>
																</option>
																<?php
															}
															?>
														</select>
													</td>

												</tr>
												<tr>
													<td style="width: 10%;"><strong>
															<?php echo _translate("Sex"); ?>&nbsp;:
														</strong></td>
													<td style="width: 23.33%;">
														<select name="noResultGender" id="noResultGender"
															class="form-control select2 notAvailReportFilter"
															title="<?php echo _translate('Please select sex'); ?>"
															style="width:100%;"
															onchange="hideFemaleDetails(this.value,'noResultPatientPregnant','noResultPatientBreastfeeding');">
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
													<td style="width: 10%;"><strong>
															<?php echo _translate("Pregnant"); ?>&nbsp;:
														</strong></td>
													<td style="width: 23.33%;">
														<select name="noResultPatientPregnant"
															id="noResultPatientPregnant"
															class="form-control select2 notAvailReportFilter"
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
													</td>
													<td style="width: 10%;"><strong>
															<?php echo _translate("Breastfeeding"); ?>&nbsp;:
														</strong></td>
													<td style="width: 23.33%;">
														<select name="noResultPatientBreastfeeding"
															id="noResultPatientBreastfeeding"
															class="form-control select2 notAvailReportFilter"
															title="<?php echo _translate('Please choose option'); ?>">
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
												</tr>
												<tr>
													<td style="width: 10%;"><strong>
															<?php echo _translate("Export with Patient Name"); ?>&nbsp;:
														</strong></td>
													<td style="width: 23.33%;">
														<select name="patientNtAvailInfo" id="patientNtAvailInfo"
															class="form-control select2 notAvailReportFilter"
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
												</tr>
												<tr>
													<td colspan="6">&nbsp;<input type="button"
															onclick="searchVlRequestData();"
															value="<?= _translate('Search'); ?>"
															class="btn btn-success btn-sm">
														&nbsp;<button class="btn btn-danger btn-sm"
															onclick="resetFilters('notAvailReportFilter');"><span>
																<?= _translate('Reset'); ?>
															</span></button>
														<button class="btn btn-success btn-sm" type="button"
															onclick="exportNotAvailableResultInexcel()"><em
																class="fa-solid fa-cloud-arrow-down"></em>
															<?php echo _translate("Export to excel"); ?>
														</button>
													</td>
												</tr>
											</table>
											<table aria-describedby="table" id="notAvailReportTable"
												class="table table-bordered table-striped" aria-hidden="true">
												<thead>
													<tr>
														<th>
															<?php echo _translate("Sample ID"); ?>
														</th>
														<?php if (!$general->isStandaloneInstance()) { ?>
															<th>
																<?php echo _translate("Remote Sample ID"); ?>
															</th>
														<?php } ?>
														<th scope="row">
															<?php echo _translate("Facility Name"); ?>
														</th>
														<th>
															<?php echo _translate("Patient ART no"); ?>.
														</th>
														<th>
															<?php echo _translate("Patient Name"); ?>
														</th>
														<th scope="row">
															<?php echo _translate("Sample Collection Date"); ?>
														</th>
														<th>
															<?php echo _translate("Testing Lab Name"); ?>
														</th>
														<th>
															<?php echo _translate("Sample Status"); ?>
														</th>
													</tr>
												</thead>
												<tbody>
													<tr>
														<td colspan="4" class="dataTables_empty">
															<?php echo _translate("Loading data from server"); ?>
														</td>
													</tr>
												</tbody>
											</table>
										</div>
										<div class="tab-pane fade" id="incompleteFormReport">
											<table aria-describedby="table" class="table pageFilters" aria-hidden="true"
												style="margin-left:1%;margin-top:20px;width:98%;padding: 3%;">
												<tr>
													<td style="width: 10%;"><strong>
															<?php echo _translate("Sample Collection Date"); ?>&nbsp;:
														</strong></td>
													<td style="width: 23.33%;">
														<input type="text" id="sampleCollectionDate"
															name="sampleCollectionDate"
															class="form-control incompleteFormReportFilter"
															placeholder="<?php echo _translate('Select Sample Collection Date'); ?>"
															readonly style="width:100%;background:#fff;" />
													</td>
													<td style="width: 10%;"><strong>
															<?php echo _translate("Fields"); ?>&nbsp;:
														</strong></td>
													<td style="width: 23.33%;">
														<select class="form-control incompleteFormReportFilter"
															id="formField" name="formField" multiple="multiple"
															title="<?php echo _translate('Please fields'); ?>"
															style="width:100%;">
															<option value="">
																<?php echo _translate("-- Select --"); ?>
															</option>
															<option value="sample_code">
																<?php echo _translate("Sample ID"); ?>
															</option>
															<option value="sample_collection_date">
																<?php echo _translate("Sample Collection Date"); ?>
															</option>
															<option value="sample_batch_id">
																<?php echo _translate("Batch Code"); ?>
															</option>
															<option value="patient_art_no">
																<?php echo _translate("Unique ART No"); ?>.
															</option>
															<option value="patient_first_name">
																<?php echo _translate("Patient Name"); ?>
															</option>
															<option value="facility_id">
																<?php echo _translate("Facility Name"); ?>
															</option>
															<option value="facility_state">
																<?php echo _translate("Province"); ?>
															</option>
															<option value="facility_district">
																<?php echo _translate("County"); ?>
															</option>
															<option value="sample_type">
																<?php echo _translate("Sample Type"); ?>
															</option>
															<option value="result">
																<?php echo _translate("Result"); ?>
															</option>
															<option value="result_status">
																<?php echo _translate("Status"); ?>
															</option>
														</select>
													</td>
													<td style="width: 10%;"><strong>
															<?php echo _translate("Export with Patient Name"); ?>&nbsp;:
														</strong></td>
													<td style="width: 23.33%;">
														<select name="patientVlQualityInfo" id="patientVlQualityInfo"
															class="form-control select2 incompleteFormReportFilter"
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
												</tr>

												<tr>
													<td colspan="4">&nbsp;<input type="button"
															onclick="searchVlRequestData();"
															value="<?= _translate('Search'); ?>"
															class="btn btn-success btn-sm">
														&nbsp;<button class="btn btn-danger btn-sm"
															onclick="resetFilters('incompleteFormReportFilter');"><span>
																<?= _translate('Reset'); ?>
															</span></button>
														<button class="btn btn-success btn-sm" type="button"
															onclick="exportDataQualityInexcel()"><em
																class="fa-solid fa-cloud-arrow-down"></em>
															<?php echo _translate("Export to excel"); ?>
														</button>
													</td>
												</tr>
											</table>
											<table aria-describedby="table" id="incompleteReport"
												class="table table-bordered table-striped" aria-hidden="true">
												<thead>
													<tr>
														<th>
															<?php echo _translate("Sample ID"); ?>
														</th>
														<?php if (!$general->isStandaloneInstance()) { ?>
															<th>
																<?php echo _translate("Remote Sample ID"); ?>
															</th>
														<?php } ?>
														<th scope="row">
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
														<th>
															<?php echo _translate("Result"); ?>
														</th>
														<th scope="row">
															<?php echo _translate("Status"); ?>
														</th>
													</tr>
												</thead>
												<tbody>
													<tr>
														<td colspan="13" class="dataTables_empty">
															<?php echo _translate("Loading data from server"); ?>
														</td>
													</tr>
												</tbody>
											</table>
										</div>
										<div class="tab-pane fade" id="sampleTestingReport">
											<table aria-describedby="table" class="table pageFilters" aria-hidden="true"
												style="margin-left:1%;margin-top:20px;width:98%;padding: 3%;">
												<tr>
													<td style="width: 10%;"><strong>
															<?php echo _translate("Province/State"); ?>&nbsp;:
														</strong></td>
													<td style="width: 23.3%;">
														<select
															class="form-control stReportFilter select2 select2-element"
															id="stState"
															onchange="getByProvince('stDistrict','stfacilityName',this.value)"
															name="stState"
															title="<?php echo _translate('Please select Province/State'); ?>">
															<?= $general->generateSelectOptions($state, null, _translate("-- Select --")); ?>
														</select>
													</td>

													<td style="width: 10%;"><strong>
															<?php echo _translate("District/County"); ?> :
														</strong></td>
													<td style="width: 23.3%;">
														<select
															class="form-control stReportFilter select2 select2-element"
															id="stDistrict" name="stDistrict"
															title="<?php echo _translate('Please select District/County'); ?>"
															onchange="getByDistrict('stfacilityName',this.value)">
														</select>
													</td>
													<td style="width: 10%;">
														<strong><?php echo _translate("Facility"); ?> :</strong>
													</td>
													<td style="width: 23.3%;">
														<select class="form-control stReportFilter" id="stfacilityName"
															name="stfacilityName" multiple="multiple"
															title="<?php echo _translate('Please select facility name'); ?>"
															style="width:220px;">
															<option value=""><?php echo _translate('-- Select --'); ?>
															</option>
															<?php foreach ($fResult as $name) { ?>
																<option value="<?php echo $name['facility_id']; ?>">
																	<?php echo ($name['facility_name'] . " - " . $name['facility_code']); ?>
																</option>
															<?php } ?>
														</select>
													</td>
												<tr>
													<td style="width: 10%;"><strong>
															<?php echo _translate("Sample Collection Date "); ?>&nbsp;:
														</strong></td>
													<td style="width: 23.3%;">
														<input type="text" id="stSampleCollectionDate"
															name="stSampleCollectionDate"
															class="form-control stReportFilter"
															placeholder="<?= _translate('Select Sample Collection date'); ?>"
															style="width:220px;background:#fff;" />
													</td>
													<td colspan="3">&nbsp;<input type="button"
															onclick="sampleTestingReport();"
															value="<?= _translate('Search'); ?>"
															class="searchBtn btn btn-success btn-sm">
														&nbsp;<button class="btn btn-danger btn-sm"
															onclick="resetFilters('stReportFilter');"><span>
																<?= _translate("Reset"); ?>
															</span></button>
													</td>
												</tr>
											</table>
											<figure class="highcharts-figure">
												<div id="container"></div>
												<div id="sampleTestingResultDetails">
													<p class="highcharts-description">
													</p>
											</figure>
										</div>
										<div class="tab-pane fade" id="patientTestHistoryFormReport">
											<table aria-describedby="table" class="table pageFilters" aria-hidden="true"
												style="margin-left:1%;margin-top:20px;width:98%;padding: 3%;">
												<tr>
													<td style="width: 10%;"><strong>
															<?php echo _translate("Patient ID"); ?>&nbsp;:
														</strong></td>
													<td style="width: 23.33%;">
														<input type="text" id="patientId" name="patientId"
															class="form-control patientHistoryFilter"
															placeholder="<?php echo _translate('Enter Patient ID'); ?>"
															style="background:#fff;" />
													</td>
													<td style="width: 10%;"><strong>
															<?php echo _translate("Patient Name"); ?>&nbsp;:
														</strong></td>
													<td style="width: 23.33%;">
														<input type="text" id="patientName" name="patientName"
															class="form-control patientHistoryFilter"
															placeholder="<?php echo _translate('Enter Patient Name'); ?>"
															style="background:#fff;" />
													</td>
													<td> <input type="button" onclick="searchVlRequestData();"
															value="<?= _translate('Search'); ?>"
															class="btn btn-success btn-sm">
														&nbsp;<button class="btn btn-danger btn-sm"
															onclick="resetFilters('patientHistoryFilter');">
															<span><?= _translate('Reset'); ?></span>
														</button>
														<button class="btn btn-success btn-sm" type="button"
															onclick="exportPatientTesthistoryInexcel()"><em
																class="fa-solid fa-cloud-arrow-down"></em>
															<?php echo _translate("Export to excel"); ?>
														</button>
													</td>
												</tr>
											</table>
											<table aria-describedby="table" id="patientTestHistoryReport"
												class="table table-bordered table-striped" aria-hidden="true">
												<thead>
													<tr>
														<th>
															<?php echo _translate("Patient ID"); ?>
														</th>
														<th scope="row">
															<?php echo _translate("Patient Name"); ?>
														</th>
														<th>
															<?php echo _translate("Age"); ?>.
														</th>
														<th>
															<?php echo _translate("DoB"); ?>
														</th>
														<th scope="row">
															<?php echo _translate("Facility Name"); ?>
														</th>
														<th>
															<?php echo _translate("Requesting Clinican"); ?>
														</th>
														<th>
															<?php echo _translate("Sample Collection Date"); ?>
														</th>
														<th>
															<?php echo _translate("Sample Type"); ?>
														</th>
														<th>
															<?php echo _translate("Lab Name"); ?>
														</th>
														<th>
															<?php echo _translate("Sample Tested Date"); ?>
														</th>
														<th>
															<?php echo _translate("Result"); ?>
														</th>
														<th>
															<?php echo _translate("Download PDF"); ?>
														</th>
													</tr>
												</thead>
												<tbody>
													<tr>
														<td colspan="12" class="dataTables_empty">
															<?php echo _translate("Loading data from server"); ?>
														</td>
													</tr>
												</tbody>
											</table>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div><!-- /.box-body -->
				</div><!-- /.box -->
			</div><!-- /.col -->
		</div><!-- /.row -->
	</section>
	<!-- /.content -->
</div>
<script src="/assets/js/moment.min.js"></script>
<script type="text/javascript" src="/assets/plugins/daterangepicker/daterangepicker.js"></script>
<script src="/assets/js/highcharts.js"></script>
<script src="/assets/js/highcharts-exporting.js"></script>
<script src="/assets/js/highcharts-offline-exporting.js"></script>
<script src="/assets/js/highcharts-accessibility.js"></script>
<script type="text/javascript">
	let searchExecuted = false;
	var oTableViralLoad = null;
	var oTableRjtReport = null;
	var oTablenotAvailReport = null;
	var oTableincompleteReport = null;
	var oTablepatientTestHistoryReport = null;
	let currentXHR = null;
	let currentRequestType = null;
	$(document).ready(function () {
		$("#state,#vfVlnsState,#rjtState,#noResultState,#stState").select2({
			width: '100%',
			placeholder: "<?php echo _translate("Select Province"); ?>"
		});
		$("#district,#vfVlnsDistrict,#rjtDistrict,#noResultDistrict,#stDistrict").select2({
			width: '100%',
			placeholder: "<?php echo _translate("Select District"); ?>"
		});
		$("#hvlFacilityName,#vfVlnsfacilityName,#rjtFacilityName,#noResultFacilityName,#stfacilityName").select2({
			width: '100%',
			placeholder: "<?php echo _translate("Select Facilities"); ?>"
		});
		$(".select2Class").select2({
			width: '100%',
			placeholder: "<?php echo _translate("Select Option"); ?>"
		});
		$("#formField").select2({
			width: '100%',
			placeholder: "<?php echo _translate("Select Fields"); ?>"
		});
		$('#hvlSampleTestDate,#rjtSampleTestDate,#noResultSampleTestDate,#sampleCollectionDate,#vfVlnsSampleCollectionDate,#vfVlnsSampleTestDate,#stSampleCollectionDate').daterangepicker({
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
		$('#vfVlnsSampleCollectionDate').daterangepicker({
			locale: {
				cancelLabel: "<?= _translate("Clear", true); ?>",
				format: 'DD-MMM-YYYY',
				separator: ' to ',
			},
			showDropdowns: true,
			alwaysShowCalendars: false,
			startDate: moment().subtract(180, 'days'),
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
		$('#hvlSampleTestDate,#rjtSampleTestDate,#noResultSampleTestDate,#sampleCollectionDate,#vfVlnsSampleCollectionDate,#vfVlnsSampleTestDate,#stSampleCollectionDate').on('cancel.daterangepicker', function (ev, picker) {
			$(this).val('');
		});
		$('#vfVlnsSampleTestDate').val('');
		highViralLoadReport();
		sampleRjtReport();
		notAvailReport();
		incompleteForm();
		getSampleResult();
		patientHistoryReport();
		$("#highViralLoadReport input, #highViralLoadReport select, #sampleRjtReport input, #sampleRjtReport select, #notAvailReport input, #notAvailReport select, #incompleteFormReport input, #incompleteFormReport select, #patientTestHistoryFormReport input").on("change", function () {
			searchExecuted = false;
		});

	});

	function vfVlnsExportInexcel() {
		$.blockUI();
		$.post('/vl/program-management/export-virologic-failure-report.php', {
			sampleCollectionDate: $('#vfVlnsSampleCollectionDate').val(),
			sampleTestDate: $('#vfVlnsSampleTestDate').val(),
			state: $('#vfVlnsState').val(),
			district: $('#vfVlnsDistrict').val(),
			facilityName: $("#vfVlnsfacilityName").val(),
			gender: $('#vfvlnGender').val(),
			pregnancy: $('#pregnancy').val(),
			breastfeeding: $('#breastfeeding').val(),
			minAge: $('#min_age').val(),
			maxAge: $('#max_age').val(),
			withAlphaNum: 'yes',
		},
			function (data) {
				if (data == "age") {
					$.unblockUI();
					alert("Age range is incorrect");
				} else if (data == "" || data == null || data == undefined) {
					$.unblockUI();
					alert("<?php echo _translate("No data found matching the selected parameters"); ?>");
				} else {
					$.unblockUI();
					window.open('/download.php?f=' + data, '_blank');
				}
			});
	}

	function highViralLoadReport() {
		oTableViralLoad = $('#highViralLoadReportTable').dataTable({
			"bJQueryUI": false,
			"bAutoWidth": false,
			"bInfo": true,
			"bScrollCollapse": true,
			//"bStateSave" : true,
			"bRetrieve": true,
			"aoColumns": [{
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
				"sClass": "center",
				"bSortable": false
			},
			],
			"aaSorting": [
				[<?= ($general->isStandaloneInstance()) ? 5 : 6; ?>, "desc"]
			],
			"bProcessing": true,
			"bServerSide": true,
			"sAjaxSource": "getHighVlResultDetails.php",
			"fnServerData": function (sSource, aoData, fnCallback) {
				aoData.push({
					"name": "hvlBatchCode",
					"value": $("#hvlBatchCode").val()
				});
				aoData.push({
					"name": "hvlSampleTestDate",
					"value": $("#hvlSampleTestDate").val()
				});
				aoData.push({
					"name": "state",
					"value": $("#state").val()
				});
				aoData.push({
					"name": "district",
					"value": $("#district").val()
				});
				aoData.push({
					"name": "hvlFacilityName",
					"value": $("#hvlFacilityName").val()
				});
				aoData.push({
					"name": "hvlSampleType",
					"value": $("#hvlSampleType").val()
				});
				aoData.push({
					"name": "hvlContactStatus",
					"value": $("#hvlContactStatus").val()
				});
				aoData.push({
					"name": "hvlGender",
					"value": $("#hvlGender").val()
				});
				aoData.push({
					"name": "hvlPatientPregnant",
					"value": $("#hvlPatientPregnant").val()
				});
				aoData.push({
					"name": "hvlPatientBreastfeeding",
					"value": $("#hvlPatientBreastfeeding").val()
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

	function sampleRjtReport() {
		oTableRjtReport = $('#sampleRjtReportTable').dataTable({
			"bJQueryUI": false,
			"bAutoWidth": false,
			"bInfo": true,
			"bScrollCollapse": true,
			//"bStateSave" : true,
			"bRetrieve": true,
			"aoColumns": [{
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
			],
			"aaSorting": [
				[<?= ($general->isStandaloneInstance()) ? 5 : 6; ?>, "desc"]
			],
			"bProcessing": true,
			"bServerSide": true,
			"sAjaxSource": "getSampleRejectionReport.php",
			"fnServerData": function (sSource, aoData, fnCallback) {
				aoData.push({
					"name": "rjtBatchCode",
					"value": $("#rjtBatchCode").val()
				});
				aoData.push({
					"name": "rjtSampleTestDate",
					"value": $("#rjtSampleTestDate").val()
				});
				aoData.push({
					"name": "rjtState",
					"value": $("#rjtState").val()
				});
				aoData.push({
					"name": "rjtDistrict",
					"value": $("#rjtDistrict").val()
				});
				aoData.push({
					"name": "rjtFacilityName",
					"value": $("#rjtFacilityName").val()
				});
				aoData.push({
					"name": "rjtSampleType",
					"value": $("#rjtSampleType").val()
				});
				aoData.push({
					"name": "rjtGender",
					"value": $("#rjtGender").val()
				});
				aoData.push({
					"name": "rjtPatientPregnant",
					"value": $("#rjtPatientPregnant").val()
				});
				aoData.push({
					"name": "rjtPatientBreastfeeding",
					"value": $("#rjtPatientBreastfeeding").val()
				});
				aoData.push({
					"name": "rejectionReason",
					"value": $("#rejectionReason").val()
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

	function notAvailReport() {

		oTablenotAvailReport = $('#notAvailReportTable').dataTable({
			"bJQueryUI": false,
			"bAutoWidth": false,
			"bInfo": true,
			"bScrollCollapse": true,
			//"bStateSave" : true,
			"bRetrieve": true,
			"aoColumns": [{
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
			}
			],
			"aaSorting": [
				[<?= ($general->isStandaloneInstance()) ? 4 : 5; ?>, "desc"]
			],
			"bProcessing": true,
			"bServerSide": true,
			"sAjaxSource": "getResultNotAvailable.php",
			"fnServerData": function (sSource, aoData, fnCallback) {
				aoData.push({
					"name": "noResultBatchCode",
					"value": $("#noResultBatchCode").val()
				});
				aoData.push({
					"name": "noResultSampleTestDate",
					"value": $("#noResultSampleTestDate").val()
				});
				aoData.push({
					"name": "noResultState",
					"value": $("#noResultState").val()
				});
				aoData.push({
					"name": "noResultDistrict",
					"value": $("#noResultDistrict").val()
				});
				aoData.push({
					"name": "noResultFacilityName",
					"value": $("#noResultFacilityName").val()
				});
				aoData.push({
					"name": "noResultSampleType",
					"value": $("#noResultSampleType").val()
				});
				aoData.push({
					"name": "noResultGender",
					"value": $("#noResultGender").val()
				});
				aoData.push({
					"name": "noResultPatientPregnant",
					"value": $("#noResultPatientPregnant").val()
				});
				aoData.push({
					"name": "noResultPatientBreastfeeding",
					"value": $("#noResultPatientBreastfeeding").val()
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

	function incompleteForm() {
		oTableincompleteReport = $('#incompleteReport').dataTable({
			"bJQueryUI": false,
			"bAutoWidth": false,
			"bInfo": true,
			"bScrollCollapse": true,
			//"bStateSave" : true,
			"bRetrieve": true,
			"aoColumns": [{
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
			{
				"sClass": "center"
			},
			],
			"aaSorting": [
				[<?= ($general->isStandaloneInstance()) ? 1 : 2; ?>, "desc"]
			],
			"bProcessing": true,
			"bServerSide": true,
			"sAjaxSource": "dataQualityCheck.php",
			"fnServerData": function (sSource, aoData, fnCallback) {
				aoData.push({
					"name": "sampleCollectionDate",
					"value": $("#sampleCollectionDate").val()
				});
				aoData.push({
					"name": "formField",
					"value": $("#formField").val()
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

	function patientHistoryReport() {
		oTablepatientTestHistoryReport = $('#patientTestHistoryReport').dataTable({
			"bJQueryUI": false,
			"bAutoWidth": false,
			"bInfo": true,
			"bScrollCollapse": true,
			//"bStateSave" : true,
			"bRetrieve": true,
			"aoColumns": [{
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
			{
				"sClass": "center"
			},
			{
				"sClass": "center"
			},
			{
				"sClass": "center",
				"bSortable": false
			},
			],
			"aaSorting": [
				[9, "desc"]
			],
			"bProcessing": true,
			"bServerSide": true,
			"sAjaxSource": "getPatientTestHistoryReport.php",
			"fnServerData": function (sSource, aoData, fnCallback) {
				aoData.push({
					"name": "patientId",
					"value": $("#patientId").val()
				});
				aoData.push({
					"name": "patientName",
					"value": $("#patientName").val()
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
		oTableViralLoad.fnDraw();
		oTableRjtReport.fnDraw();
		oTablenotAvailReport.fnDraw();
		//incompleteForm();
		oTableincompleteReport.fnDraw();
		oTablepatientTestHistoryReport.fnDraw();
		$.unblockUI();
	}

	function updateStatus(id, value) {
		conf = confirm("<?php echo _translate("Do you wisht to change the contact completed status?"); ?>");
		if (conf) {
			$.post("/vl/program-management/updateContactCompletedStatus.php", {
				id: id,
				value: value
			},
				function (data) {
					alert("<?php echo _translate("Status updated successfully"); ?>");
					oTableViralLoad.fnDraw();
				});
		} else {
			oTableViralLoad.fnDraw();
		}
	}

	function exportHighViralLoadInexcel() {
		if (searchExecuted === false) {
			searchVlRequestData();
		}
		var markAsComplete = false;
		confm = confirm("<?php echo _translate("Do you want to mark these as complete ?"); ?>");
		if (confm) {
			var markAsComplete = true;
		}
		$.blockUI();
		$.post("/vl/program-management/vlHighViralLoadResultExportInExcel.php", {
			Sample_Test_Date: $("#hvlSampleTestDate").val(),
			Batch_Code: $("#hvlBatchCode  option:selected").text(),
			Sample_Type: $("#hvlSampleType  option:selected").text(),
			Facility_Name: $("#hvlFacilityName  option:selected").text(),
			Sex: $("#hvlGender  option:selected").text(),
			patientInfo: $("#patientInfo  option:selected").val(),
			Pregnant: $("#hvlPatientPregnant  option:selected").text(),
			Breastfeeding: $("#hvlPatientBreastfeeding  option:selected").text(),
			markAsComplete: markAsComplete
		},
			function (data) {
				if (data == "" || data == null || data == undefined) {
					$.unblockUI();
					alert("<?php echo _translate("Unable to generate the excel file"); ?>");
				} else {
					$.unblockUI();
					//location.href = '/temporary/' + data;
					window.open('/download.php?f=' + data, '_blank');

				}
			});
	}

	function exportRejectedResultInexcel() {
		if (searchExecuted === false) {
			searchVlRequestData();
		}
		$.blockUI();
		$.post("/vl/program-management/vlRejectedResultExportInExcel.php", {
			Sample_Test_Date: $("#rjtSampleTestDate").val(),
			Batch_Code: $("#rjtBatchCode  option:selected").text(),
			Sample_Type: $("#rjtSampleType  option:selected").text(),
			Facility_Name: $("#rjtFacilityName  option:selected").text(),
			Sex: $("#rjtGender  option:selected").text(),
			patientInfo: $("#patientRejectedInfo  option:selected").val(),
			Pregnant: $("#rjtPatientPregnant  option:selected").text(),
			Breastfeeding: $("#rjtPatientBreastfeeding  option:selected").text(),
			RejectionReason: $("#rejectionReason  option:selected").val()
		},
			function (data) {
				if (data == "" || data == null || data == undefined) {
					$.unblockUI();
					alert("<?php echo _translate("Unable to generate the excel file"); ?>");
				} else {
					$.unblockUI();
					window.open('/download.php?f=' + data, '_blank');
				}
			});
	}

	function exportNotAvailableResultInexcel() {
		if (searchExecuted === false) {
			searchVlRequestData();
		}
		$.blockUI();
		$.post("/vl/program-management/vlNotAvailableResultExportInExcel.php", {
			Sample_Test_Date: $("#noResultSampleTestDate").val(),
			Batch_Code: $("#noResultBatchCode  option:selected").text(),
			Sample_Type: $("#noResultSampleType  option:selected").text(),
			Facility_Name: $("#noResultFacilityName  option:selected").text(),
			Sex: $("#noResultGender  option:selected").text(),
			patientInfo: $("#patientNtAvailInfo  option:selected").val(),
			Pregnant: $("#noResultPatientPregnant  option:selected").text(),
			Breastfeeding: $("#noResultPatientBreastfeeding  option:selected").text()
		},
			function (data) {
				if (data == "" || data == null || data == undefined) {
					$.unblockUI();
					alert("<?php echo _translate("Unable to generate the excel file"); ?>");
				} else {
					$.unblockUI();
					window.open('/download.php?f=' + data, '_blank');
				}
			});
	}

	function exportDataQualityInexcel() {
		if (searchExecuted === false) {
			searchVlRequestData();
		}
		$.blockUI();
		$.post("/vl/program-management/vlDataQualityExportInExcel.php", {
			Sample_Collection_Date: $("#sampleCollectionDate").val(),
			Field_Name: $("#formField  option:selected").text(),
			patientInfo: $("#patientVlQualityInfo  option:selected").val(),

		},
			function (data) {
				if (data == "" || data == null || data == undefined) {
					$.unblockUI();
					alert("<?php echo _translate("Unable to generate the excel file"); ?>");
				} else {
					$.unblockUI();
					window.open('/download.php?f=' + data, '_blank');
				}
			});
	}

	function exportPatientTesthistoryInexcel() {
		if (searchExecuted === false) {
			searchVlRequestData();
		}
		$.blockUI();
		$.post("/vl/program-management/vlPatientTesthistoryInExcel.php", {
			patient_id: $("#patientId").val(),
			patient_name: $("#patientName").val()
		},
			function (data) {
				if (data == "" || data == null || data == undefined) {
					$.unblockUI();
					alert("<?php echo _translate("Unable to generate the excel file"); ?>");
				} else {
					$.unblockUI();
					window.open('/download.php?f=' + data, '_blank');
				}
			});
	}

	function hideFemaleDetails(value, pregnant, breastFeeding) {
		if (value == 'female') {
			$("#" + pregnant).attr("disabled", false);
			$("#" + breastFeeding).attr("disabled", false);
		} else {
			$('select#' + pregnant).val('');
			$('select#' + breastFeeding).val('');
			$("#" + pregnant).attr("disabled", true);
			$("#" + breastFeeding).attr("disabled", true);
		}
	}

	function setSampleTestDate(obj) {
		$(".stDate").val($("#" + obj.id).val());
	}

	function getByProvince(districtId, facilityId, provinceId) {
		if (provinceId != '') {
			$.blockUI();
			$("#" + districtId).html('');
			$("#" + facilityId).html('');
			$.post("/common/get-by-province-id.php", {
				provinceId: provinceId,
				districts: true,
				facilities: true,
				facilityCode: true
			},
				function (data) {
					$.unblockUI();
					Obj = $.parseJSON(data);
					$("#" + districtId).html(Obj['districts']);
					$("#" + facilityId).html(Obj['facilities']);
				});
		}
	}

	function getByDistrict(facilityId, districtId) {
		if (districtId != '') {
			$("#" + facilityId).html('');
			$.post("/common/get-by-district-id.php", {
				districtId: districtId,
				facilities: true,
				facilityCode: true
			},
				function (data) {
					Obj = $.parseJSON(data);
					$("#" + facilityId).html(Obj['facilities']);
				});
		}
	}

	function resetFilters(filtersClass) {
		$('.' + filtersClass).val('');
		$('.' + filtersClass).val(null).trigger('change');
	}

	function sampleTestingReport() {

		$.when(
			getSampleResult()
		)
			.done(function () {
				$.unblockUI();
				$(window).scroll();
			});

		$(window).on('beforeunload', function () {
			if (currentXHR !== null && currentXHR !== undefined) {
				currentXHR.abort();
			}
		});
	}

	function getSampleResult() {
		currentXHR = $.post("/vl/program-management/getSampleTestingReport.php", {
			sampleCollectionDate: $("#stSampleCollectionDate").val(),
			state: $('#stState').val(),
			district: $('#stDistrict').val(),
			facilityName: $('#stfacilityName').val(),
		},
			function (data) {
				if (data != '') {
					$("#sampleTestingResultDetails").html(data);
				}
			});
		return currentXHR;
	}

	function generateResultPDF(id) {
		$.blockUI();
		<?php
		$path = '';
		$path = '/vl/results/generate-result-pdf.php';
		?>
		$.post("<?php echo $path; ?>", {
			source: 'print',
			id: id
		},
			function (data) {
				if (data == "" || data == null || data == undefined) {
					$.unblockUI();
					alert("<?= _translate("Unable to generate download", true); ?>");
				} else {
					$.unblockUI();
					oTablepatientTestHistoryReport.fnDraw();
					window.open('/download.php?f=' + data, '_blank');
				}
			});
	}
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';
