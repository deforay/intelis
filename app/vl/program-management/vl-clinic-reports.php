<?php

use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use App\Services\GeoLocationsService;

$title = _translate("VL | Clinic Reports");

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

// Conditions go before the ORDER BY: appended after it they become part of the
// order expression, which silently stopped the facility map filtering and, for
// a lab-scoped user, failed outright. No lab scope on this table either:
// facility_details has no lab_id, since a facility is not owned by a lab; the
// report's own sample queries are what scope to the lab.
$fWhere = ["status = 'active'"];
if (!empty($_SESSION['facilityMap'])) {
	$fWhere[] = "facility_id IN (" . $_SESSION['facilityMap'] . ")";
}
$fQuery = "SELECT * FROM facility_details WHERE " . implode(' AND ', $fWhere) . " ORDER BY facility_name";
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

$implementingPartnerList = $general->getImplementationPartners();

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
											<div class="box box-default report-filter-box">
												<div class="box-header with-border report-filter-header">
													<h3 class="box-title"><em class="fa-solid fa-filter"></em> <?php echo _translate("Filters"); ?></h3>
													<span class="report-filter-summary"></span>
													<div class="box-tools pull-right">
														<button type="button" class="btn btn-box-tool report-filter-toggle" title="<?php echo _htmlTranslate("Show or hide filters"); ?>"><em class="fa fa-minus"></em></button>
													</div>
												</div>
												<div class="box-body pageFilters">
												<div class="row">
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="hvlSampleTestDate"><?php echo _translate("Sample Test Date"); ?></label>
															<input type="text" id="hvlSampleTestDate"
															name="hvlSampleTestDate"
															class="form-control highViralLoadReportFilter stDate"
															placeholder="<?php echo _translate('Select Sample Test Date'); ?>"
															readonly style="background:#fff;"
															onchange="setSampleTestDate(this)" />
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="hvlBatchCode"><?php echo _translate("Batch Code"); ?></label>
															<select
															class="form-control select2Class highViralLoadReportFilter"
															id="hvlBatchCode" name="hvlBatchCode"
															title="<?php echo _translate('Please select batch code'); ?>">
															<option value="">
															<?php echo _translate("-- Select --"); ?>
															</option>
															<?php foreach ($batResult as $code) { ?>
															<option value="<?php echo $code['batch_code']; ?>">
															<?php echo $code['batch_code']; ?>
															</option>
															<?php } ?>
															</select>
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="hvlSampleType"><?php echo _translate("Sample Type"); ?></label>
															<select
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
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="state"><?php echo _translate("Province/State"); ?></label>
															<select class="form-control highViralLoadReportFilter"
															id="state"
															onchange="getByProvince('district','hvlFacilityName',this.value)"
															name="state"
															title="<?php echo _translate('Please select Province/State'); ?>">
															<?= $general->generateSelectOptions($state, null, _translate("-- Select --")); ?>
															</select>
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="district"><?php echo _translate("District/County"); ?></label>
															<select class="form-control highViralLoadReportFilter"
															id="district" name="district"
															title="<?php echo _translate('Please select District/County'); ?>"
															onchange="getByDistrict('hvlFacilityName',this.value)">
															</select>
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="hvlFacilityName"><?php echo _translate("Facility"); ?></label>
															<select class="form-control highViralLoadReportFilter"
															id="hvlFacilityName" name="hvlFacilityName"
															multiple="multiple"
															title="<?php echo _translate('Please select facility name'); ?>">
															<option value="">
															<?php echo _translate("-- Select --"); ?>
															</option>
															<?php foreach ($fResult as $name) { ?>
															<option value="<?php echo $name['facility_id']; ?>">
															<?php echo ($name['facility_name'] . " - " . $name['facility_code']); ?>
															</option>
															<?php } ?>
															</select>
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="hvlContactStatus"><?php echo _translate("Contact Status"); ?></label>
															<select class="form-control select2 highViralLoadReportFilter"
															id="hvlContactStatus" name="hvlContactStatus"
															title="<?php echo _translate('Please select contact status'); ?>">
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
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="hvlGender"><?php echo _translate("Sex"); ?></label>
															<select name="hvlGender" id="hvlGender"
															class="form-control select2 highViralLoadReportFilter"
															title="<?php echo _translate('Please select sex'); ?>"
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
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="hvlPatientPregnant"><?php echo _translate("Pregnant"); ?></label>
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
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="hvlPatientBreastfeeding"><?php echo _translate("Breastfeeding"); ?></label>
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
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="patientInfo"><?php echo _translate("Export with Patient Name"); ?></label>
															<select name="patientInfo" id="patientInfo"
															class="form-control select2 highViralLoadReportFilter"
															title="<?php echo _translate('Please choose community sample'); ?>">
															<option value="yes">
															<?php echo _translate("Yes"); ?>
															</option>
															<option value="no">
															<?php echo _translate("No"); ?>
															</option>
															</select>
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="hvlImplementingPartner"><?php echo _translate("Implementing Partner"); ?></label>
															<select name="hvlImplementingPartner" id="hvlImplementingPartner"
															class="form-control select2Class highViralLoadReportFilter"
															title="<?php echo _translate('Please choose implementing partner'); ?>">
															<option value="">
															<?php echo _translate("-- Select --"); ?>
															</option>
															<?php foreach ($implementingPartnerList as $implementingPartner) { ?>
															<option value="<?php echo base64_encode((string) $implementingPartner['i_partner_id']); ?>">
															<?= $implementingPartner['i_partner_name']; ?>
															</option>
															<?php } ?>
															</select>
														</div>
													</div>
												</div>
												</div>
												<div class="box-footer filter-actions">
													&nbsp;<input type="button"
													onclick="searchVlRequestData();"
													value="<?= _translate('Search'); ?>"
													class="btn btn-success btn-sm">
													&nbsp;<button type="button" class="btn btn-default btn-sm"
													onclick="resetFilters('highViralLoadReportFilter');"><span>
													<?= _translate('Reset'); ?>
													</span></button>
													<button class="filter-export btn btn-success btn-sm" type="button"
													onclick="exportHighViralLoadInexcel()"><em
													class="fa-solid fa-cloud-arrow-down"></em>
													<?php echo _translate("Export to excel"); ?>
													</button>
												</div>
											</div>

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
														<th>
															<?php echo _translate("Implementing Partner"); ?>
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
											<div class="box box-default report-filter-box">
												<div class="box-header with-border report-filter-header">
													<h3 class="box-title"><em class="fa-solid fa-filter"></em> <?php echo _translate("Filters"); ?></h3>
													<span class="report-filter-summary"></span>
													<div class="box-tools pull-right">
														<button type="button" class="btn btn-box-tool report-filter-toggle" title="<?php echo _htmlTranslate("Show or hide filters"); ?>"><em class="fa fa-minus"></em></button>
													</div>
												</div>
												<div class="box-body pageFilters">
												<div class="row">
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="vfVlnsState"><?php echo _translate("Province/State"); ?></label>
															<select
															class="form-control vfvlnsfilters select2 select2-element"
															id="vfVlnsState"
															onchange="getByProvince('vfVlnsDistrict','vfVlnsfacilityName',this.value)"
															name="vfVlnsState"
															title="<?php echo _translate('Please select Province/State'); ?>">
															<?= $general->generateSelectOptions($state, null, _translate("-- Select --")); ?>
															</select>
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="vfVlnsDistrict"><?php echo _translate("District/County"); ?></label>
															<select
															class="form-control vfvlnsfilters select2 select2-element"
															id="vfVlnsDistrict" name="vfVlnsDistrict"
															title="<?php echo _translate('Please select District/County'); ?>"
															onchange="getByDistrict('vfVlnsfacilityName',this.value)">
															</select>
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="vfVlnsfacilityName"><?php echo _translate("Facility"); ?></label>
															<select class="form-control vfvlnsfilters"
															id="vfVlnsfacilityName" name="vfVlnsfacilityName"
															multiple="multiple"
															title="<?php echo _translate('Please select facility name'); ?>">
															<?php foreach ($fResult as $name) { ?>
															<option value="<?php echo $name['facility_id']; ?>">
															<?php echo ($name['facility_name'] . " - " . $name['facility_code']); ?>
															</option>
															<?php } ?>
															</select>
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="vfVlnsSampleCollectionDate"><?php echo _translate("Sample Collection Date"); ?></label>
															<input type="text" id="vfVlnsSampleCollectionDate"
															name="vfVlnsSampleCollectionDate"
															class="form-control vfvlnsfilters daterangefield"
															placeholder="<?php echo _translate('Select Collection Date'); ?>"
															style="background:#fff;" />
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="vfVlnsSampleTestDate"><?php echo _translate("Sample Tested Date"); ?></label>
															<input type="text" id="vfVlnsSampleTestDate"
															name="vfVlnsSampleTestDate"
															class="form-control vfvlnsfilters daterangefield"
															placeholder="<?php echo _translate('Select Tested Date'); ?>"
															style="background:#fff;" />
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="vfvlnGender"><?php echo _translate("Sex"); ?></label>
															<select name="vfvlnGender" id="vfvlnGender"
															class="form-control select2 vfvlnsfilters"
															title="<?php echo _translate('Please select sex'); ?>"
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
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="pregnancy"><?php echo _translate("Pregnancy"); ?></label>
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
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="breastfeeding"><?php echo _translate("Breastfeeding"); ?></label>
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
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="min_age"><?php echo _translate("Age Range"); ?> <small class="text-muted"><?php echo _translate("in years"); ?></small></label>
															<div class="input-pair">
																<input type="number" id="min_age" class="form-control vfvlnsfilters" name="min_age" min="0" max="120" value="0" aria-label="<?php echo _htmlTranslate("Youngest age"); ?>">
																<span class="input-pair-sep"><?php echo _translate("to"); ?></span>
																<input type="number" id="max_age" name="max_age" class="form-control vfvlnsfilters" min="0" max="120" value="120" aria-label="<?php echo _htmlTranslate("Oldest age"); ?>">
															</div>
															<div class="range-slider" data-range-min="#min_age" data-range-max="#max_age" data-floor="0" data-ceiling="120"></div>
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="vfVlnsImplementingPartner"><?php echo _translate("Implementing Partner"); ?></label>
															<select name="vfVlnsImplementingPartner" id="vfVlnsImplementingPartner"
															class="form-control select2Class vfvlnsfilters"
															title="<?php echo _translate('Please choose implementing partner'); ?>">
															<option value="">
															<?php echo _translate("-- Select --"); ?>
															</option>
															<?php foreach ($implementingPartnerList as $implementingPartner) { ?>
															<option value="<?php echo base64_encode((string) $implementingPartner['i_partner_id']); ?>">
															<?= $implementingPartner['i_partner_name']; ?>
															</option>
															<?php } ?>
															</select>
														</div>
													</div>
												</div>
												</div>
												<div class="box-footer filter-actions">
													&nbsp;<button onclick="vfVlnsExportInexcel();" value="Search"
													class="filter-export btn btn-success btn-sm"><em
													class="fa-solid fa-cloud-arrow-down"></em><span><?php echo _translate(" Generate report"); ?></span></button>
													&nbsp;<button type="button" class="btn btn-default btn-sm"
													onclick="resetFilters('vfvlnsfilters');"><span><?php echo _translate("Reset"); ?></span></button>
												</div>
											</div>
										</div>
										<div class="tab-pane fade" id="sampleRjtReport">
											<div class="box box-default report-filter-box">
												<div class="box-header with-border report-filter-header">
													<h3 class="box-title"><em class="fa-solid fa-filter"></em> <?php echo _translate("Filters"); ?></h3>
													<span class="report-filter-summary"></span>
													<div class="box-tools pull-right">
														<button type="button" class="btn btn-box-tool report-filter-toggle" title="<?php echo _htmlTranslate("Show or hide filters"); ?>"><em class="fa fa-minus"></em></button>
													</div>
												</div>
												<div class="box-body pageFilters">
												<div class="row">
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="rjtSampleCollectionDate"><?php echo _translate("Sample Collection Date"); ?></label>
															<input type="text" id="rjtSampleCollectionDate"
															name="rjtSampleCollectionDate"
															class="form-control sampleRjtReportFilter stDate daterange"
															placeholder="<?php echo _translate('Select Sample Collection Date'); ?>"
															readonly style="background:#fff;"
															onchange="setSampleTestDate(this)" />
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="rjtBatchCode"><?php echo _translate("Batch Code"); ?></label>
															<select class="form-control select2Class sampleRjtReportFilter"
															id="rjtBatchCode" name="rjtBatchCode"
															title="<?php echo _translate('Please select batch code'); ?>">
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
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="rjtSampleType"><?php echo _translate("Sample Type"); ?></label>
															<select
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
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="rjtState"><?php echo _translate("Province/State"); ?></label>
															<select
															class="form-control sampleRjtReportFilter select2-element"
															id="rjtState"
															onchange="getByProvince('rjtDistrict','rjtFacilityName',this.value)"
															name="rjtState"
															title="<?php echo _translate('Please select Province/State'); ?>">
															<?= $general->generateSelectOptions($state, null, _translate("-- Select --")); ?>
															</select>
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="rjtDistrict"><?php echo _translate("District/County"); ?></label>
															<select
															class="form-control sampleRjtReportFilter select2-element"
															id="rjtDistrict" name="rjtDistrict"
															title="<?php echo _translate('Please select District/County'); ?>"
															onchange="getByDistrict('rjtFacilityName',this.value)">
															</select>
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="rjtFacilityName"><?php echo _translate("Facility"); ?></label>
															<select class="form-control sampleRjtReportFilter"
															id="rjtFacilityName" name="facilityName"
															title="<?php echo _translate('Please select facility name'); ?>"
															multiple="multiple">
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
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="rjtGender"><?php echo _translate("Sex"); ?></label>
															<select name="rjtGender" id="rjtGender"
															class="form-control select2 sampleRjtReportFilter"
															title="<?php echo _translate('Please select sex'); ?>"
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
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="rjtPatientPregnant"><?php echo _translate("Pregnant"); ?></label>
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
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="rjtPatientBreastfeeding"><?php echo _translate("Breastfeeding"); ?></label>
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
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="rejectionReason"><?php echo _translate("Rejection Reason"); ?></label>
															<select name="rejectionReason" id="rejectionReason"
															class="form-control select2 sampleRjtReportFilter"
															title="<?php echo _translate('Please choose reason'); ?>">
															<option value="">
															<?php echo _translate("-- Select --"); ?>
															</option>
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
															<?php } ?>
															</select>
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="patientRejectedInfo"><?php echo _translate("Export with Patient Name"); ?></label>
															<select name="patientRejectedInfo" id="patientRejectedInfo"
															class="form-control select2 sampleRjtReportFilter"
															title="<?php echo _translate('Please choose community sample'); ?>">
															<option value="yes">
															<?php echo _translate("Yes"); ?>
															</option>
															<option value="no">
															<?php echo _translate("No"); ?>
															</option>
															</select>
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="rjtImplementingPartner"><?php echo _translate("Implementing Partner"); ?></label>
															<select name="rjtImplementingPartner" id="rjtImplementingPartner"
															class="form-control select2Class sampleRjtReportFilter"
															title="<?php echo _translate('Please choose implementing partner'); ?>">
															<option value="">
															<?php echo _translate("-- Select --"); ?>
															</option>
															<?php foreach ($implementingPartnerList as $implementingPartner) { ?>
															<option value="<?php echo base64_encode((string) $implementingPartner['i_partner_id']); ?>">
															<?= $implementingPartner['i_partner_name']; ?>
															</option>
															<?php } ?>
															</select>
														</div>
													</div>
												</div>
												</div>
												<div class="box-footer filter-actions">
													&nbsp;<input type="button"
													onclick="searchVlRequestData();"
													value="<?= _translate('Search'); ?>"
													class="btn btn-success btn-sm">
													&nbsp;<button type="button" class="btn btn-default btn-sm"
													onclick="resetFilters('sampleRjtReportFilter');"><span>
													<?= _translate('Reset'); ?>
													</span></button>
													<button class="filter-export btn btn-success btn-sm" type="button"
													onclick="exportRejectedResultInexcel()"><em
													class="fa-solid fa-cloud-arrow-down"></em>
													<?php echo _translate("Export to excel"); ?>
													</button>
												</div>
											</div>
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
														<th>
															<?php echo _translate("Implementing Partner"); ?>
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
											<div class="box box-default report-filter-box">
												<div class="box-header with-border report-filter-header">
													<h3 class="box-title"><em class="fa-solid fa-filter"></em> <?php echo _translate("Filters"); ?></h3>
													<span class="report-filter-summary"></span>
													<div class="box-tools pull-right">
														<button type="button" class="btn btn-box-tool report-filter-toggle" title="<?php echo _htmlTranslate("Show or hide filters"); ?>"><em class="fa fa-minus"></em></button>
													</div>
												</div>
												<div class="box-body pageFilters">
												<div class="row">
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="noResultSampleTestDate"><?php echo _translate("Sample Collection Date"); ?></label>
															<input type="text" id="noResultSampleTestDate"
															name="noResultSampleTestDate"
															class="form-control notAvailReportFilter stDate daterange"
															placeholder="<?php echo _translate('Select Sample Collection Date'); ?>"
															readonly style="background:#fff;"
															onchange="setSampleTestDate(this)" />
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="noResultBatchCode"><?php echo _translate("Batch Code"); ?></label>
															<select class="form-control select2Class notAvailReportFilter"
															id="noResultBatchCode" name="noResultBatchCode"
															title="<?php echo _translate('Please select batch code'); ?>">
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
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="noResultSampleType"><?php echo _translate("Sample Type"); ?></label>
															<select
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
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="noResultState"><?php echo _translate("Province/State"); ?></label>
															<select
															class="form-control notAvailReportFilter select2-element"
															id="noResultState"
															onchange="getByProvince('noResultDistrict','noResultFacilityName',this.value)"
															name="rjtState"
															title="<?php echo _translate('Please select Province/State'); ?>">
															<?= $general->generateSelectOptions($state, null, _translate("-- Select --")); ?>
															</select>
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="noResultDistrict"><?php echo _translate("District/County"); ?></label>
															<select
															class="form-control notAvailReportFilter select2-element"
															id="noResultDistrict" name="noResultDistrict"
															title="<?php echo _translate('Please select District/County'); ?>"
															onchange="getByDistrict('noResultFacilityName',this.value)">
															</select>
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="noResultFacilityName"><?php echo _translate("Facility"); ?></label>
															<select class="form-control notAvailReportFilter"
															id="noResultFacilityName" name="facilityName"
															title="<?php echo _translate('Please select facility name'); ?>"
															multiple="multiple">
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
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="noResultGender"><?php echo _translate("Sex"); ?></label>
															<select name="noResultGender" id="noResultGender"
															class="form-control select2 notAvailReportFilter"
															title="<?php echo _translate('Please select sex'); ?>"
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
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="noResultPatientPregnant"><?php echo _translate("Pregnant"); ?></label>
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
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="noResultPatientBreastfeeding"><?php echo _translate("Breastfeeding"); ?></label>
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
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="patientNtAvailInfo"><?php echo _translate("Export with Patient Name"); ?></label>
															<select name="patientNtAvailInfo" id="patientNtAvailInfo"
															class="form-control select2 notAvailReportFilter"
															title="<?php echo _translate('Please choose community sample'); ?>">
															<option value="yes">
															<?php echo _translate("Yes"); ?>
															</option>
															<option value="no">
															<?php echo _translate("No"); ?>
															</option>
															</select>
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="noResultImplementingPartner"><?php echo _translate("Implementing Partner"); ?></label>
															<select name="noResultImplementingPartner"
															id="noResultImplementingPartner"
															class="form-control select2Class notAvailReportFilter"
															title="<?php echo _translate('Please choose implementing partner'); ?>">
															<option value="">
															<?php echo _translate("-- Select --"); ?>
															</option>
															<?php foreach ($implementingPartnerList as $implementingPartner) { ?>
															<option value="<?php echo base64_encode((string) $implementingPartner['i_partner_id']); ?>">
															<?= $implementingPartner['i_partner_name']; ?>
															</option>
															<?php } ?>
															</select>
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="noResultIncludeExpired"><?php echo _translate("Include Expired Samples"); ?></label>
															<select name="noResultIncludeExpired" id="noResultIncludeExpired"
															class="form-control notAvailReportFilter"
															title="<?php echo _translate('Please choose whether expired samples are counted'); ?>">
															<option value=""><?php echo _translate("Yes"); ?></option>
															<option value="no"><?php echo _translate("No"); ?></option>
															</select>
														</div>
													</div>
												</div>
												</div>
												<div class="box-footer filter-actions">
													&nbsp;<input type="button"
													onclick="searchVlRequestData();"
													value="<?= _translate('Search'); ?>"
													class="btn btn-success btn-sm">
													&nbsp;<button type="button" class="btn btn-default btn-sm"
													onclick="resetFilters('notAvailReportFilter');"><span>
													<?= _translate('Reset'); ?>
													</span></button>
													<button class="filter-export btn btn-success btn-sm" type="button"
													onclick="exportNotAvailableResultInexcel()"><em
													class="fa-solid fa-cloud-arrow-down"></em>
													<?php echo _translate("Export to excel"); ?>
													</button>
												</div>
											</div>
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
														<th>
															<?php echo _translate("Implementing Partner"); ?>
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
											<div class="box box-default report-filter-box">
												<div class="box-header with-border report-filter-header">
													<h3 class="box-title"><em class="fa-solid fa-filter"></em> <?php echo _translate("Filters"); ?></h3>
													<span class="report-filter-summary"></span>
													<div class="box-tools pull-right">
														<button type="button" class="btn btn-box-tool report-filter-toggle" title="<?php echo _htmlTranslate("Show or hide filters"); ?>"><em class="fa fa-minus"></em></button>
													</div>
												</div>
												<div class="box-body pageFilters">
												<div class="row">
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="sampleCollectionDate"><?php echo _translate("Sample Collection Date"); ?></label>
															<input type="text" id="sampleCollectionDate"
															name="sampleCollectionDate"
															class="form-control incompleteFormReportFilter"
															placeholder="<?php echo _translate('Select Sample Collection Date'); ?>"
															readonly style="background:#fff;" />
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="formField"><?php echo _translate("Fields"); ?></label>
															<select class="form-control incompleteFormReportFilter"
															id="formField" name="formField" multiple="multiple"
															title="<?php echo _translate('Please fields'); ?>">
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
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="patientVlQualityInfo"><?php echo _translate("Export with Patient Name"); ?></label>
															<select name="patientVlQualityInfo" id="patientVlQualityInfo"
															class="form-control select2 incompleteFormReportFilter"
															title="<?php echo _translate('Please choose community sample'); ?>">
															<option value="yes">
															<?php echo _translate("Yes"); ?>
															</option>
															<option value="no">
															<?php echo _translate("No"); ?>
															</option>
															</select>
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="dqImplementingPartner"><?php echo _translate("Implementing Partner"); ?></label>
															<select name="dqImplementingPartner" id="dqImplementingPartner"
															class="form-control select2Class incompleteFormReportFilter"
															title="<?php echo _translate('Please choose implementing partner'); ?>">
															<option value="">
															<?php echo _translate("-- Select --"); ?>
															</option>
															<?php foreach ($implementingPartnerList as $implementingPartner) { ?>
															<option value="<?php echo base64_encode((string) $implementingPartner['i_partner_id']); ?>">
															<?= $implementingPartner['i_partner_name']; ?>
															</option>
															<?php } ?>
															</select>
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="dqFieldMatch"><?php echo _translate("Field Match"); ?></label>
															<select name="dqFieldMatch" id="dqFieldMatch"
															class="form-control select2Class incompleteFormReportFilter"
															title="<?php echo _translate('Please choose how the selected fields combine'); ?>">
															<option value="any">
															<?php echo _translate("Any selected field is missing"); ?>
															</option>
															<option value="all">
															<?php echo _translate("All selected fields are missing"); ?>
															</option>
															</select>
														</div>
													</div>
												</div>
												</div>
												<div class="box-footer filter-actions">
													&nbsp;<input type="button"
													onclick="searchVlRequestData();"
													value="<?= _translate('Search'); ?>"
													class="btn btn-success btn-sm">
													&nbsp;<button type="button" class="btn btn-default btn-sm"
													onclick="resetFilters('incompleteFormReportFilter');"><span>
													<?= _translate('Reset'); ?>
													</span></button>
													<button class="filter-export btn btn-success btn-sm" type="button"
													onclick="exportDataQualityInexcel()"><em
													class="fa-solid fa-cloud-arrow-down"></em>
													<?php echo _translate("Export to excel"); ?>
													</button>
												</div>
											</div>
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
														<th>
															<?php echo _translate("Implementing Partner"); ?>
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
										<div class="tab-pane fade" id="sampleTestingReport" style="width: 100%; overflow-x: auto;">
											<div class="box box-default report-filter-box">
												<div class="box-header with-border report-filter-header">
													<h3 class="box-title"><em class="fa-solid fa-filter"></em> <?php echo _translate("Filters"); ?></h3>
													<span class="report-filter-summary"></span>
													<div class="box-tools pull-right">
														<button type="button" class="btn btn-box-tool report-filter-toggle" title="<?php echo _htmlTranslate("Show or hide filters"); ?>"><em class="fa fa-minus"></em></button>
													</div>
												</div>
												<div class="box-body pageFilters">
												<div class="row">
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="stState"><?php echo _translate("Province/State"); ?></label>
															<select
															class="form-control stReportFilter select2 select2-element"
															id="stState"
															onchange="getByProvince('stDistrict','stfacilityName',this.value)"
															name="stState"
															title="<?php echo _translate('Please select Province/State'); ?>">
															<?= $general->generateSelectOptions($state, null, _translate("-- Select --")); ?>
															</select>
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="stDistrict"><?php echo _translate("District/County"); ?></label>
															<select
															class="form-control stReportFilter select2 select2-element"
															id="stDistrict" name="stDistrict"
															title="<?php echo _translate('Please select District/County'); ?>"
															onchange="getByDistrict('stfacilityName',this.value)">
															</select>
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="stfacilityName"><?php echo _translate("Facility"); ?></label>
															<select class="form-control stReportFilter" id="stfacilityName"
															name="stfacilityName" multiple="multiple"
															title="<?php echo _translate('Please select facility name'); ?>">
															<option value=""><?php echo _translate('-- Select --'); ?>
															</option>
															<?php foreach ($fResult as $name) { ?>
															<option value="<?php echo $name['facility_id']; ?>">
															<?php echo ($name['facility_name'] . " - " . $name['facility_code']); ?>
															</option>
															<?php } ?>
															</select>
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="stSampleCollectionDate"><?php echo _translate("Sample Collection Date "); ?></label>
															<input type="text" id="stSampleCollectionDate"
															name="stSampleCollectionDate"
															class="form-control stReportFilter"
															placeholder="<?= _translate('Select Sample Collection date'); ?>"
															style="background:#fff;" />
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="stImplementingPartner"><?php echo _translate("Implementing Partner"); ?></label>
															<select name="stImplementingPartner" id="stImplementingPartner"
															class="form-control select2Class stReportFilter"
															title="<?php echo _translate('Please choose implementing partner'); ?>">
															<option value="">
															<?php echo _translate("-- Select --"); ?>
															</option>
															<?php foreach ($implementingPartnerList as $implementingPartner) { ?>
															<option value="<?php echo base64_encode((string) $implementingPartner['i_partner_id']); ?>">
															<?= $implementingPartner['i_partner_name']; ?>
															</option>
															<?php } ?>
															</select>
														</div>
													</div>
												</div>
												</div>
												<div class="box-footer filter-actions">
													&nbsp;<input type="button"
													onclick="sampleTestingReport();"
													value="<?= _translate('Search'); ?>"
													class="searchBtn btn btn-success btn-sm">
													&nbsp;<button type="button" class="btn btn-default btn-sm"
													onclick="resetFilters('stReportFilter');"><span>
													<?= _translate("Reset"); ?>
													</span></button>
												</div>
											</div>
											<figure class="highcharts-figure">
												<div id="container"></div>
												<div id="sampleTestingResultDetails">
													<p class="highcharts-description"></p>
											</figure>
										</div>
										<div class="tab-pane fade" id="patientTestHistoryFormReport">
											<div class="box box-default report-filter-box">
												<div class="box-header with-border report-filter-header">
													<h3 class="box-title"><em class="fa-solid fa-filter"></em> <?php echo _translate("Filters"); ?></h3>
													<span class="report-filter-summary"></span>
													<div class="box-tools pull-right">
														<button type="button" class="btn btn-box-tool report-filter-toggle" title="<?php echo _htmlTranslate("Show or hide filters"); ?>"><em class="fa fa-minus"></em></button>
													</div>
												</div>
												<div class="box-body pageFilters">
												<div class="row">
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="patientId"><?php echo _translate("Patient ID"); ?></label>
															<input type="text" id="patientId" name="patientId"
															class="form-control patientHistoryFilter"
															placeholder="<?php echo _translate('Enter Patient ID'); ?>"
															style="background:#fff;" />
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="patientName"><?php echo _translate("Patient Name"); ?></label>
															<input type="text" id="patientName" name="patientName"
															class="form-control patientHistoryFilter"
															placeholder="<?php echo _translate('Enter Patient Name'); ?>"
															style="background:#fff;" />
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="pthImplementingPartner"><?php echo _translate("Implementing Partner"); ?></label>
															<select name="pthImplementingPartner" id="pthImplementingPartner"
															class="form-control select2Class patientHistoryFilter"
															title="<?php echo _translate('Please choose implementing partner'); ?>">
															<option value="">
															<?php echo _translate("-- Select --"); ?>
															</option>
															<?php foreach ($implementingPartnerList as $implementingPartner) { ?>
															<option value="<?php echo base64_encode((string) $implementingPartner['i_partner_id']); ?>">
															<?= $implementingPartner['i_partner_name']; ?>
															</option>
															<?php } ?>
															</select>
														</div>
													</div>
												</div>
												</div>
												<div class="box-footer filter-actions">
													<input type="button" onclick="searchVlRequestData();"
													value="<?= _translate('Search'); ?>"
													class="btn btn-success btn-sm">
													&nbsp;<button type="button" class="btn btn-default btn-sm"
													onclick="resetFilters('patientHistoryFilter');">
													<span><?= _translate('Reset'); ?></span>
													</button>
													<button class="filter-export btn btn-success btn-sm" type="button"
													onclick="exportPatientTesthistoryInexcel()"><em
													class="fa-solid fa-cloud-arrow-down"></em>
													<?php echo _translate("Export to excel"); ?>
													</button>
												</div>
											</div>
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
															<?php echo _translate("Implementing Partner"); ?>
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
<script type="text/javascript" src="<?= _asset('/assets/plugins/daterangepicker/daterangepicker.js') ?>"></script>
<link rel="stylesheet" media="all" type="text/css" href="<?= _asset('/assets/css/clinic-reports.css') ?>">
<script type="text/javascript" src="<?= _asset('/assets/js/clinic-reports.js') ?>"></script>
<script type="text/javascript">
	let searchExecuted = false;
	var oTableViralLoad = null;
	var oTableRjtReport = null;
	var oTablenotAvailReport = null;
	var oTableincompleteReport = null;
	var oTablepatientTestHistoryReport = null;
	let currentXHR = null;
	let currentRequestType = null;

	var filterClasses = [
		'highViralLoadReportFilter',
		'vfvlnsfilters',
		'sampleRjtReportFilter',
		'notAvailReportFilter',
		'incompleteFormReportFilter',
		'stReportFilter',
		'patientHistoryFilter'
	];

	function getStorageKey(filtersClass) {
		return 'vlClinicReport_' + filtersClass;
	}

	function saveFiltersToStorage(filtersClass) {
		var filters = {};
		$('.' + filtersClass).each(function () {
			var id = $(this).attr('id');
			if (id) {
				filters[id] = $(this).val();
			}
		});
		localStorage.setItem(getStorageKey(filtersClass), JSON.stringify(filters));
	}

	function restoreFiltersFromStorage(filtersClass) {
		var saved = localStorage.getItem(getStorageKey(filtersClass));
		if (!saved) return;
		try {
			var filters = JSON.parse(saved);
			$.each(filters, function (id, value) {
				var $el = $('#' + id);
				if ($el.length && value !== null && value !== '' && !(Array.isArray(value) && value.length === 0)) {
					var drp = $el.data('daterangepicker');
					if (drp && value) {
						$el.val(value);
						var parts = value.split(' to ');
						if (parts.length === 2) {
							drp.setStartDate(moment(parts[0], 'DD-MMM-YYYY'));
							drp.setEndDate(moment(parts[1], 'DD-MMM-YYYY'));
						}
					} else {
						$el.val(value).trigger('change');
					}
				}
			});
		} catch (e) {}
	}

	function restoreAllFilters() {
		$.each(filterClasses, function (i, cls) {
			restoreFiltersFromStorage(cls);
		});
	}

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
		$('#hvlSampleTestDate,#rjtSampleCollectionDate,#noResultSampleTestDate,#sampleCollectionDate,#vfVlnsSampleCollectionDate,#vfVlnsSampleTestDate,#stSampleCollectionDate').daterangepicker({
			locale: {
				cancelLabel: "<?= _translate("Clear", true); ?>",
				format: 'DD-MMM-YYYY',
				separator: ' to ',
			},
			showDropdowns: true,
			alwaysShowCalendars: true,
			startDate: moment().subtract(28, 'days'),
			endDate: moment(),
			minDate: moment('2013-01-01'),
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
				'Last 18 Months': [moment().subtract(18, 'month').startOf('month'), moment().endOf('month')],
				'Last 24 Months': [moment().subtract(24, 'month').startOf('month'), moment().endOf('month')],
				'Last 30 Months': [moment().subtract(30, 'month').startOf('month'), moment().endOf('month')],
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
			alwaysShowCalendars: true,
			startDate: moment().subtract(180, 'days'),
			endDate: moment(),
			minDate: moment('2013-01-01'),
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
				'Last 18 Months': [moment().subtract(18, 'month').startOf('month'), moment().endOf('month')],
				'Last 24 Months': [moment().subtract(24, 'month').startOf('month'), moment().endOf('month')],
				'Last 30 Months': [moment().subtract(30, 'month').startOf('month'), moment().endOf('month')],
				'Previous Year': [moment().subtract(1, 'year').startOf('year'), moment().subtract(1, 'year').endOf('year')],
				'Current Year To Date': [moment().startOf('year'), moment()]
			}
		},
			function (start, end) {
				startDate = start.format('YYYY-MM-DD');
				endDate = end.format('YYYY-MM-DD');
			});
		$('#hvlSampleTestDate,#rjtSampleCollectionDate,#noResultSampleTestDate,#sampleCollectionDate,#vfVlnsSampleCollectionDate,#vfVlnsSampleTestDate,#stSampleCollectionDate').on('cancel.daterangepicker', function (ev, picker) {
			$(this).val('');
		});
		$('#vfVlnsSampleTestDate').val('');
		restoreAllFilters();
		ClinicReports.registerTab('highViralLoadReport', { init: highViralLoadReport, table: function () { return oTableViralLoad; } });
		ClinicReports.registerTab('sampleRjtReport', { init: sampleRjtReport, table: function () { return oTableRjtReport; } });
		ClinicReports.registerTab('notAvailReport', { init: notAvailReport, table: function () { return oTablenotAvailReport; } });
		ClinicReports.registerTab('incompleteFormReport', { init: incompleteForm, table: function () { return oTableincompleteReport; } });
		ClinicReports.registerTab('sampleTestingReport', { init: getSampleResult, search: sampleTestingReport });
		ClinicReports.registerTab('patientTestHistoryFormReport', { init: patientHistoryReport, table: function () { return oTablepatientTestHistoryReport; } });
		/* Filters copied in from another tab are applied with a namespaced
		   event, so the change handlers above never see them. The last
		   search no longer matches what is on screen. */
		$(document).on('clinicreports:filterschanged', function () {
			searchExecuted = false;
		});
		ClinicReports.start();
		$("#highViralLoadReport input, #highViralLoadReport select, #sampleRjtReport input, #sampleRjtReport select, #notAvailReport input, #notAvailReport select, #incompleteFormReport input, #incompleteFormReport select, #patientTestHistoryFormReport input").on("change", function () {
			searchExecuted = false;
		});
		$.each(filterClasses, function (i, cls) {
			$('.' + cls).on('change', function () {
				saveFiltersToStorage(cls);
			});
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
			implementingPartner: $('#vfVlnsImplementingPartner').val(),
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
				"sClass": "center"
			},
			{
				"sClass": "center",
				"bSortable": false
			},
			],
			"aaSorting": [
				[<?= ($general->isStandaloneInstance()) ? 6 : 7; ?>, "desc"]
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
				aoData.push({
					"name": "hvlImplementingPartner",
					"value": $("#hvlImplementingPartner").val()
				});
				ClinicReports.serverData(sSource, aoData, fnCallback);
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
					"name": "rjtSampleCollectionDate",
					"value": $("#rjtSampleCollectionDate").val()
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
				aoData.push({
					"name": "rjtImplementingPartner",
					"value": $("#rjtImplementingPartner").val()
				});
				ClinicReports.serverData(sSource, aoData, fnCallback);
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
				aoData.push({
					"name": "noResultImplementingPartner",
					"value": $("#noResultImplementingPartner").val()
				});
				aoData.push({
					"name": "noResultIncludeExpired",
					"value": $("#noResultIncludeExpired").val()
				});
				ClinicReports.serverData(sSource, aoData, fnCallback);
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
				aoData.push({
					"name": "dqImplementingPartner",
					"value": $("#dqImplementingPartner").val()
				});
				aoData.push({
					"name": "dqFieldMatch",
					"value": $("#dqFieldMatch").val()
				});
				ClinicReports.serverData(sSource, aoData, fnCallback);
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
				aoData.push({
					"name": "pthImplementingPartner",
					"value": $("#pthImplementingPartner").val()
				});
				ClinicReports.serverData(sSource, aoData, fnCallback);
			}
		});
	}

	/* Every tab is a server-side table over its own endpoint, so redrawing all
	   of them cost five queries to look at one. Only the visible tab is drawn,
	   and the promise settles when its request comes back -- which is what the
	   exports wait on before asking the server to replay the query. */
	function searchVlRequestData() {
		searchExecuted = true;
		return ClinicReports.searchActive();
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
		/* The export replays the query the last search stored in the session,
		   so it has to wait for that search rather than race it. */
		if (!searchExecuted) {
			return searchVlRequestData().then(exportHighViralLoadInexcel);
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
		/* The export replays the query the last search stored in the session,
		   so it has to wait for that search rather than race it. */
		if (!searchExecuted) {
			return searchVlRequestData().then(exportRejectedResultInexcel);
		}
		$.blockUI();
		$.post("/vl/program-management/vlRejectedResultExportInExcel.php", {
			Sample_Collection_Date: $("#rjtSampleCollectionDate").val(),
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
		/* The export replays the query the last search stored in the session,
		   so it has to wait for that search rather than race it. */
		if (!searchExecuted) {
			return searchVlRequestData().then(exportNotAvailableResultInexcel);
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
		/* The export replays the query the last search stored in the session,
		   so it has to wait for that search rather than race it. */
		if (!searchExecuted) {
			return searchVlRequestData().then(exportDataQualityInexcel);
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
		/* The export replays the query the last search stored in the session,
		   so it has to wait for that search rather than race it. */
		if (!searchExecuted) {
			return searchVlRequestData().then(exportPatientTesthistoryInexcel);
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
		localStorage.removeItem(getStorageKey(filtersClass));
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
			implementingPartner: $('#stImplementingPartner').val(),
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
