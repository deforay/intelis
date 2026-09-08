<?php

use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Services\GeoLocationsService;

$title = _translate("EID | Clinic Reports");

require_once APPLICATION_PATH . '/header.php';



/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

/** @var GeoLocationsService $geolocationService */
$geolocationService = ContainerRegistry::get(GeoLocationsService::class);


$healthFacilites = $facilitiesService->getHealthFacilities('eid');
$facilitiesDropdown = $general->generateSelectOptions($healthFacilites, null, "-- Select --");
$testingLabs = $facilitiesService->getTestingLabs('eid');
$testingLabsDropdown = $general->generateSelectOptions($testingLabs, null, "-- Select --");



$tsQuery = "SELECT * FROM r_sample_status";
$tsResult = $db->rawQuery($tsQuery);
//config  query

//$arr = $general->getGlobalConfig();

$sQuery = "SELECT * FROM r_eid_sample_type where status='active'";
$sResult = $db->rawQuery($sQuery);

$batQuery = "SELECT batch_code FROM batch_details WHERE test_type = 'eid' AND batch_status='completed'";
$batResult = $db->rawQuery($batQuery);

$rejectionTypeQuery = "SELECT DISTINCT rejection_type FROM r_eid_sample_rejection_reasons WHERE rejection_reason_status ='active'";
$rejectionTypeResult = $db->rawQuery($rejectionTypeQuery);

//sample rejection reason
$rejectionQuery = "SELECT * FROM r_eid_sample_rejection_reasons where rejection_reason_status = 'active'";
$rejectionResult = $db->rawQuery($rejectionQuery);
$state = $geolocationService->getProvinces("yes");

$implementingPartnerList = $general->getImplementationPartners();


foreach ($rejectionTypeResult as $type) {
	$rejectionReason .= '<optgroup label="' . ($type['rejection_type']) . '">';
	foreach ($rejectionResult as $reject) {
		if ($type['rejection_type'] == $reject['rejection_type']) {
			$rejectionReason .= '<option value="' . $reject['rejection_reason_id'] . '">' . ($reject['rejection_reason_name']) . '</option>';
		}
	}
	$rejectionReason .= '</optgroup>';
}

?>
<style>
	.select2-selection__choice {
		color: #000000 !important;
	}
</style>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1> <em class="fa-solid fa-book"></em> <?php echo _translate("Clinic Reports"); ?></h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _translate("Home"); ?></a></li>
			<li class="active"><?php echo _translate("Clinic Reports"); ?></li>
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
										<li class="active"><a href="#highViralLoadReport" data-toggle="tab"><?php echo _translate("Positivity Report"); ?></a></li>
										<li><a href="#sampleRjtReport" data-toggle="tab"><?php echo _translate("Sample Rejection Report"); ?></a></li>
										<li><a href="#notAvailReport" data-toggle="tab"><?php echo _translate("Results Not Available Report"); ?></a></li>
										<li><a href="#incompleteFormReport" data-toggle="tab"><?php echo _translate("Data Quality Check"); ?></a></li>
										<li><a href="#sampleTestingReport" data-toggle="tab"><?php echo _translate("Sample Testing Report"); ?></a></li>
										<li><a href="#patientTestHistoryFormReport" data-toggle="tab"><?php echo _translate("Patient Test History"); ?></a></li>
									</ul>
									<div id="myTabContent" class="tab-content">
										<div class="tab-pane fade in active" id="highViralLoadReport">
											<div class="box box-default report-filter-box">
												<div class="box-header with-border report-filter-header">
													<h3 class="box-title"><em class="fa-solid fa-filter"></em> <?php echo _translate("Filters"); ?></h3>
													<span class="report-filter-summary"></span>
													<div class="box-tools pull-right">
														<button type="button" class="btn btn-box-tool" data-widget="collapse" title="<?php echo _translate("Show or hide filters"); ?>"><em class="fa fa-minus"></em></button>
													</div>
												</div>
												<div class="box-body">
												<div class="row">
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="hvlSampleTestDate"><?php echo _translate("Sample Test Date"); ?></label>
															<input type="text" id="hvlSampleTestDate" name="hvlSampleTestDate" class="form-control stDate" placeholder="<?php echo _translate('Select Sample Test Date'); ?>" readonly style="background:#fff;" onchange="setSampleTestDate(this)" />
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="hvlBatchCode"><?php echo _translate("Batch Code"); ?></label>
															<select class="form-control" id="hvlBatchCode" name="hvlBatchCode" title="<?php echo _translate('Please select batch code'); ?>">
															<option value=""> <?php echo _translate("-- Select --"); ?> </option>
															<?php
															foreach ($batResult as $code) {
															?>
															<option value="<?php echo $code['batch_code']; ?>"><?php echo $code['batch_code']; ?></option>
															<?php
															}
															?>
															</select>
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="hvlSampleType"><?php echo _translate("Sample Type"); ?></label>
															<select class="form-control" id="hvlSampleType" name="sampleType" title="<?php echo _translate('Please select sample type'); ?>">
															<option value=""> <?php echo _translate("-- Select --"); ?> </option>
															<?php
															foreach ($sResult as $type) {
															?>
															<option value="<?php echo $type['sample_id']; ?>"><?= $type['sample_name']; ?></option>
															<?php
															}
															?>
															</select>
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="state"><?php echo _translate("Province/State"); ?></label>
															<select class="form-control select2-element" id="state" onchange="getByProvince('district','hvlFacilityName',this.value)" name="state" title="<?php echo _translate('Please select Province/State'); ?>">
															<?= $general->generateSelectOptions($state, null, _translate("-- Select --")); ?>
															</select>
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="district"><?php echo _translate("District/County"); ?></label>
															<select class="form-control select2-element" id="district" name="district" title="<?php echo _translate('Please select District/County'); ?>" onchange="getByDistrict('hvlFacilityName',this.value)">
															</select>
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="hvlFacilityName"><?php echo _translate("Facility Name"); ?></label>
															<select class="form-control" id="hvlFacilityName" name="hvlFacilityName" title="<?php echo _translate('Please select facility name'); ?>" multiple="multiple">
															<?= $facilitiesDropdown; ?>
															</select>
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="hvlContactStatus"><?php echo _translate("Contact Status"); ?></label>
															<select class="form-control" id="hvlContactStatus" name="hvlContactStatus" title="<?php echo _translate('Please select contact status'); ?>">
															<option value=""> <?php echo _translate("-- Select --"); ?> </option>
															<option value="yes"><?php echo _translate("Completed"); ?></option>
															<option value="no"><?php echo _translate("Not Completed"); ?></option>
															<option value="all" selected="selected"><?php echo _translate("All"); ?></option>
															</select>
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="hvlGender"><?php echo _translate("Sex"); ?></label>
															<select name="hvlGender" id="hvlGender" class="form-control" title="<?php echo _translate('Please select sex'); ?>" onchange="">
															<option value=""> <?php echo _translate("-- Select --"); ?> </option>
															<option value="male"><?php echo _translate("Male"); ?></option>
															<option value="female"><?php echo _translate("Female"); ?></option>
															<option value="unreported"><?php echo _translate("Unreported"); ?></option>
															</select>
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="hvlImplementingPartner"><?php echo _translate("Implementing Partner"); ?></label>
															<select name="hvlImplementingPartner" id="hvlImplementingPartner" class="form-control" title="<?php echo _translate('Please choose implementing partner'); ?>">
															<option value=""> <?php echo _translate("-- Select --"); ?> </option>
															<?php foreach ($implementingPartnerList as $implementingPartner) { ?>
															<option value="<?php echo base64_encode((string) $implementingPartner['i_partner_id']); ?>"><?= $implementingPartner['i_partner_name']; ?></option>
															<?php } ?>
															</select>
														</div>
													</div>
												</div>
												<div class="filter-actions">
													&nbsp;<input type="button" onclick="searchVlRequestData();" value="<?php echo _translate("Search"); ?>" class="btn btn-success btn-sm">
													&nbsp;<button type="button" class="btn btn-default btn-sm" onclick="document.location.href = document.location"><span><?= _translate('Reset'); ?></span></button>
													<button class="btn btn-success btn-sm" type="button" onclick="exportHighViralLoadInexcel()"><em class="fa-solid fa-cloud-arrow-down"></em> <?php echo _translate("Export to excel"); ?></button>
												</div>
												</div>
											</div>

											<table aria-describedby="table" id="highViralLoadReportTable" class="table table-bordered table-striped" aria-hidden="true">
												<thead>
													<tr>
														<th><?php echo _translate("Sample ID"); ?></th>
														<?php if (!$general->isStandaloneInstance()) { ?>
															<th><?php echo _translate("Remote Sample ID"); ?></th>
														<?php } ?>
														<th><?php echo _translate("Facility Name"); ?></th>
														<th><?php echo _translate("Child's ID"); ?></th>
														<th><?php echo _translate("Child's Name"); ?></th>
														<th><?php echo _translate("Caretaker Phone No"); ?>.</th>
														<th><?php echo _translate("Sample Collection Date"); ?></th>
														<th><?php echo _translate("Sample Tested Date"); ?></th>
														<th><?php echo _translate("Testing Lab"); ?></th>
														<th><?php echo _translate("Result"); ?></th>
														<th><?php echo _translate("Implementing Partner"); ?></th>
														<th><?php echo _translate("Status"); ?></th>
													</tr>
												</thead>
												<tbody>
													<tr>
														<td colspan="7" class="dataTables_empty"><?php echo _translate("Loading data from server"); ?></td>
													</tr>
												</tbody>
											</table>
										</div>
										<div class="tab-pane fade" id="sampleRjtReport">
											<div class="box box-default report-filter-box">
												<div class="box-header with-border report-filter-header">
													<h3 class="box-title"><em class="fa-solid fa-filter"></em> <?php echo _translate("Filters"); ?></h3>
													<span class="report-filter-summary"></span>
													<div class="box-tools pull-right">
														<button type="button" class="btn btn-box-tool" data-widget="collapse" title="<?php echo _translate("Show or hide filters"); ?>"><em class="fa fa-minus"></em></button>
													</div>
												</div>
												<div class="box-body">
												<div class="row">
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="rjtSampleTestDate"><?php echo _translate("Sample Test Date"); ?></label>
															<input type="text" id="rjtSampleTestDate" name="rjtSampleTestDate" class="form-control stDate daterange" placeholder="<?php echo _translate('Select Sample Test Date'); ?>" readonly style="background:#fff;" onchange="setSampleTestDate(this)" />
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="rjtBatchCode"><?php echo _translate("Batch Code"); ?></label>
															<select class="form-control" id="rjtBatchCode" name="rjtBatchCode" title="<?php echo _translate('Please select batch code'); ?>">
															<option value=""> <?php echo _translate("-- Select --"); ?> </option>
															<?php
															foreach ($batResult as $code) {
															?>
															<option value="<?php echo $code['batch_code']; ?>"><?php echo $code['batch_code']; ?></option>
															<?php
															}
															?>
															</select>
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="rjtSampleType"><?php echo _translate("Sample Type"); ?></label>
															<select class="form-control" id="rjtSampleType" name="sampleType" title="<?php echo _translate('Please select sample type'); ?>">
															<option value=""> <?php echo _translate("-- Select --"); ?> </option>
															<?php
															foreach ($sResult as $type) {
															?>
															<option value="<?php echo $type['sample_id']; ?>"><?= $type['sample_name']; ?></option>
															<?php
															}
															?>
															</select>
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="rjtState"><?php echo _translate("Province/State"); ?></label>
															<select class="form-control select2-element" id="rjtState" onchange="getByProvince('rjtDistrict','rjtFacilityName',this.value)" name="rjtState" title="<?php echo _translate('Please select Province/State'); ?>">
															<?= $general->generateSelectOptions($state, null, _translate("-- Select --")); ?>
															</select>
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="rjtDistrict"><?php echo _translate("District/County"); ?></label>
															<select class="form-control select2-element" id="rjtDistrict" name="rjtDistrict" title="<?php echo _translate('Please select District/County'); ?>" onchange="getByDistrict('rjtFacilityName',this.value)">
															</select>
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="rjtFacilityName"><?php echo _translate("Facility Name"); ?></label>
															<select class="form-control" id="rjtFacilityName" name="facilityName" title="<?php echo _translate('Please select facility name'); ?>" multiple="multiple">
															<?= $facilitiesDropdown; ?>
															</select>
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="rjtGender"><?php echo _translate("Sex"); ?></label>
															<select name="rjtGender" id="rjtGender" class="form-control" title="<?php echo _translate('Please select sex'); ?>" onchange="">
															<option value=""> <?php echo _translate("-- Select --"); ?> </option>
															<option value="male"><?php echo _translate("Male"); ?></option>
															<option value="female"><?php echo _translate("Female"); ?></option>
															<option value="unreported"><?php echo _translate("Unreported"); ?></option>
															</select>
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="sampleRejectionReason"><?php echo _translate("Rejection Reason"); ?></label>
															<select class="form-control" name="sampleRejectionReason" id="sampleRejectionReason" title="Please select the reason for sample rejection">
															<option value=''> -- Select -- </option>
															<?php echo $rejectionReason; ?>
															</select>
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="rjtImplementingPartner"><?php echo _translate("Implementing Partner"); ?></label>
															<select name="rjtImplementingPartner" id="rjtImplementingPartner" class="form-control" title="<?php echo _translate('Please choose implementing partner'); ?>">
															<option value=""> <?php echo _translate("-- Select --"); ?> </option>
															<?php foreach ($implementingPartnerList as $implementingPartner) { ?>
															<option value="<?php echo base64_encode((string) $implementingPartner['i_partner_id']); ?>"><?= $implementingPartner['i_partner_name']; ?></option>
															<?php } ?>
															</select>
														</div>
													</div>
												</div>
												<div class="filter-actions">
													&nbsp;<input type="button" onclick="searchVlRequestData();" value="<?php echo _translate("Search"); ?>" class="btn btn-success btn-sm">
													&nbsp;<button type="button" class="btn btn-default btn-sm" onclick="document.location.href = document.location"><span><?= _translate('Reset'); ?></span></button>
													<button class="btn btn-success btn-sm" type="button" onclick="exportRejectedResultInexcel()"><em class="fa-solid fa-cloud-arrow-down"></em> <?php echo _translate("Export to excel"); ?></button>
												</div>
												</div>
											</div>
											<table aria-describedby="table" id="sampleRjtReportTable" class="table table-bordered table-striped" aria-hidden="true">
												<thead>
													<tr>
														<th><?php echo _translate("Sample ID"); ?></th>
														<?php if (!$general->isStandaloneInstance()) { ?>
															<th><?php echo _translate("Remote Sample ID"); ?></th>
														<?php } ?>
														<th><?php echo _translate("Facility Name"); ?></th>
														<th><?php echo _translate("Child's ID"); ?></th>
														<th><?php echo _translate("Child's Name"); ?></th>
														<th><?php echo _translate("Sample Collection Date"); ?></th>
														<th><?php echo _translate("Testing Lab Name"); ?></th>
														<th><?php echo _translate("Rejection Reason"); ?></th>
														<th><?php echo _translate("Recommended Corrective action"); ?></th>
														<th><?php echo _translate("Implementing Partner"); ?></th>
													</tr>
												</thead>
												<tbody>
													<tr>
														<td colspan="6" class="dataTables_empty"><?php echo _translate("Loading data from server"); ?></td>
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
														<button type="button" class="btn btn-box-tool" data-widget="collapse" title="<?php echo _translate("Show or hide filters"); ?>"><em class="fa fa-minus"></em></button>
													</div>
												</div>
												<div class="box-body">
												<div class="row">
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="noResultSampleTestDate"><?php echo _translate("Sample Collection Date"); ?></label>
															<input type="text" id="noResultSampleTestDate" name="noResultSampleTestDate" class="form-control stDate daterange" placeholder="<?php echo _translate('Select Sample Collection Date'); ?>" readonly style="background:#fff;" onchange="setSampleTestDate(this)" />
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="noResultBatchCode"><?php echo _translate("Batch Code"); ?></label>
															<select class="form-control" id="noResultBatchCode" name="noResultBatchCode" title="<?php echo _translate('Please select batch code'); ?>">
															<option value=""> <?php echo _translate("-- Select --"); ?> </option>
															<?php
															foreach ($batResult as $code) {
															?>
															<option value="<?php echo $code['batch_code']; ?>"><?php echo $code['batch_code']; ?></option>
															<?php
															}
															?>
															</select>
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="noResultSampleType"><?php echo _translate("Sample Type"); ?></label>
															<select class="form-control" id="noResultSampleType" name="sampleType" title="<?php echo _translate('Please select sample type'); ?>">
															<option value=""> <?php echo _translate("-- Select --"); ?> </option>
															<?php
															foreach ($sResult as $type) {
															?>
															<option value="<?php echo $type['sample_id']; ?>"><?= $type['sample_name']; ?></option>
															<?php
															}
															?>
															</select>
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="noResultState"><?php echo _translate("Province/State"); ?></label>
															<select class="form-control select2-element" id="noResultState" onchange="getByProvince('noResultDistrict','noResultFacilityName',this.value)" name="rjtState" title="<?php echo _translate('Please select Province/State'); ?>">
															<?= $general->generateSelectOptions($state, null, _translate("-- Select --")); ?>
															</select>
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="noResultDistrict"><?php echo _translate("District/County"); ?></label>
															<select class="form-control select2-element" id="noResultDistrict" name="noResultDistrict" title="<?php echo _translate('Please select District/County'); ?>" onchange="getByDistrict('noResultFacilityName',this.value)">
															</select>
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="noResultFacilityName"><?php echo _translate("Facility Name"); ?></label>
															<select class="form-control" id="noResultFacilityName" name="facilityName" title="<?php echo _translate('Please select facility name'); ?>" multiple="multiple">
															<?= $facilitiesDropdown; ?>
															</select>
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="noResultGender"><?php echo _translate("Sex"); ?></label>
															<select name="noResultGender" id="noResultGender" class="form-control" title="<?php echo _translate('Please select sex'); ?>" onchange="">
															<option value=""> <?php echo _translate("-- Select --"); ?> </option>
															<option value="male"><?php echo _translate("Male"); ?></option>
															<option value="female"><?php echo _translate("Female"); ?></option>
															<option value="unreported"><?php echo _translate("Unreported"); ?></option>
															</select>
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="noResultImplementingPartner"><?php echo _translate("Implementing Partner"); ?></label>
															<select name="noResultImplementingPartner" id="noResultImplementingPartner" class="form-control" title="<?php echo _translate('Please choose implementing partner'); ?>">
															<option value=""> <?php echo _translate("-- Select --"); ?> </option>
															<?php foreach ($implementingPartnerList as $implementingPartner) { ?>
															<option value="<?php echo base64_encode((string) $implementingPartner['i_partner_id']); ?>"><?= $implementingPartner['i_partner_name']; ?></option>
															<?php } ?>
															</select>
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="noResultIncludeExpired"><?php echo _translate("Include Expired Samples"); ?></label>
															<select name="noResultIncludeExpired" id="noResultIncludeExpired" class="form-control" title="<?php echo _translate('Please choose whether expired samples are counted'); ?>">
															<option value=""><?php echo _translate("Yes"); ?></option>
															<option value="no"><?php echo _translate("No"); ?></option>
															</select>
														</div>
													</div>
												</div>
												<div class="filter-actions">
													&nbsp;<input type="button" onclick="searchVlRequestData();" value="<?php echo _translate("Search"); ?>" class="btn btn-success btn-sm">
													&nbsp;<button type="button" class="btn btn-default btn-sm" onclick="document.location.href = document.location"><span><?= _translate('Reset'); ?></span></button>
													<button class="btn btn-success btn-sm" type="button" onclick="exportNotAvailableResultInexcel()"><em class="fa-solid fa-cloud-arrow-down"></em> <?php echo _translate("Export to excel"); ?></button>
												</div>
												</div>
											</div>
											<table aria-describedby="table" id="notAvailReportTable" class="table table-bordered table-striped" aria-hidden="true">
												<thead>
													<tr>
														<th><?php echo _translate("Sample ID"); ?></th>
														<?php if (!$general->isStandaloneInstance()) { ?>
															<th><?php echo _translate("Remote Sample ID"); ?></th>
														<?php } ?>
														<th><?php echo _translate("Facility Name"); ?></th>
														<th><?php echo _translate("Child's ID"); ?></th>
														<th><?php echo _translate("Child's Name"); ?></th>
														<th><?php echo _translate("Sample Collection Date"); ?></th>
														<th><?php echo _translate("Testing Lab Name"); ?></th>
														<th><?php echo _translate("Sample Status"); ?></th>
														<th><?php echo _translate("Implementing Partner"); ?></th>
													</tr>
												</thead>
												<tbody>
													<tr>
														<td colspan="4" class="dataTables_empty"><?php echo _translate("Loading data from server"); ?></td>
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
														<button type="button" class="btn btn-box-tool" data-widget="collapse" title="<?php echo _translate("Show or hide filters"); ?>"><em class="fa fa-minus"></em></button>
													</div>
												</div>
												<div class="box-body">
												<div class="row">
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="sampleCollectionDate"><?php echo _translate("Sample Collection Date"); ?></label>
															<input type="text" id="sampleCollectionDate" name="sampleCollectionDate" class="form-control daterangefield" placeholder="<?php echo _translate('Select Sample Collection Date'); ?>" readonly style="background:#fff;" />
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="formField"><?php echo _translate("Fields"); ?></label>
															<select class="form-control" id="formField" name="formField" multiple="multiple" title="<?php echo _translate('Please fields'); ?>">
															<option value=""> <?php echo _translate("-- Select --"); ?> </option>
															<option value="sample_code"><?php echo _translate("Sample ID"); ?></option>
															<option value="sample_collection_date"><?php echo _translate("Sample Collection Date"); ?></option>
															<option value="sample_batch_id"><?php echo _translate("Batch Code"); ?></option>
															<option value="child_id"><?php echo _translate("Child ID"); ?></option>
															<option value="child_name"><?php echo _translate("Child's Name"); ?></option>
															<option value="facility_id"><?php echo _translate("Facility Name"); ?></option>
															<option value="facility_state"><?php echo _translate("Province"); ?></option>
															<option value="facility_district"><?php echo _translate("County"); ?></option>
															<option value="specimen_type"><?php echo _translate("Sample Type"); ?></option>
															<option value="result"><?php echo _translate("Result"); ?></option>
															<option value="result_status"><?php echo _translate("Status"); ?></option>
															</select>
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="dqImplementingPartner"><?php echo _translate("Implementing Partner"); ?></label>
															<select name="dqImplementingPartner" id="dqImplementingPartner" class="form-control" title="<?php echo _translate('Please choose implementing partner'); ?>">
															<option value=""> <?php echo _translate("-- Select --"); ?> </option>
															<?php foreach ($implementingPartnerList as $implementingPartner) { ?>
															<option value="<?php echo base64_encode((string) $implementingPartner['i_partner_id']); ?>"><?= $implementingPartner['i_partner_name']; ?></option>
															<?php } ?>
															</select>
														</div>
													</div>
												</div>
												<div class="filter-actions">
													&nbsp;<input type="button" onclick="searchVlRequestData();" value="<?php echo _translate("Search"); ?>" class="btn btn-success btn-sm">
													&nbsp;<button type="button" class="btn btn-default btn-sm" onclick="document.location.href = document.location"><span><?= _translate('Reset'); ?></span></button>
													<button class="btn btn-success btn-sm" type="button" onclick="exportDataQualityInexcel()"><em class="fa-solid fa-cloud-arrow-down"></em> <?php echo _translate("Export to excel"); ?></button>
												</div>
												</div>
											</div>
											<table aria-describedby="table" id="incompleteReport" class="table table-bordered table-striped" aria-hidden="true">
												<thead>
													<tr>
														<th><?php echo _translate("Sample ID"); ?></th>
														<?php if (!$general->isStandaloneInstance()) { ?>
															<th><?php echo _translate("Remote Sample ID"); ?></th>
														<?php } ?>
														<th><?php echo _translate("Sample Collection Date"); ?></th>
														<th><?php echo _translate("Batch Code"); ?></th>
														<th><?php echo _translate("Child's Name"); ?></th>
														<th><?php echo _translate("Facility Name"); ?></th>
														<th><?php echo _translate("Province/State"); ?></th>
														<th><?php echo _translate("District/County"); ?></th>
														<th><?php echo _translate("Sample Type"); ?></th>
														<th><?php echo _translate("Result"); ?></th>
														<th><?php echo _translate("Status"); ?></th>
														<th><?php echo _translate("Implementing Partner"); ?></th>
													</tr>
												</thead>
												<tbody>
													<tr>
														<td colspan="12" class="dataTables_empty"><?php echo _translate("Loading data from server"); ?></td>
													</tr>
												</tbody>
											</table>
										</div>
										<div class="tab-pane fade" id="sampleTestingReport">
											<div class="box box-default report-filter-box">
												<div class="box-header with-border report-filter-header">
													<h3 class="box-title"><em class="fa-solid fa-filter"></em> <?php echo _translate("Filters"); ?></h3>
													<span class="report-filter-summary"></span>
													<div class="box-tools pull-right">
														<button type="button" class="btn btn-box-tool" data-widget="collapse" title="<?php echo _translate("Show or hide filters"); ?>"><em class="fa fa-minus"></em></button>
													</div>
												</div>
												<div class="box-body">
												<div class="row">
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="stState"><?php echo _translate("Province/State"); ?></label>
															<select class="form-control stReportFilter select2 select2-element" id="stState" onchange="getByProvince('stDistrict','stfacilityName',this.value)" name="stState" title="<?php echo _translate('Please select Province/State'); ?>">
															<?= $general->generateSelectOptions($state, null, _translate("-- Select --")); ?>
															</select>
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="stDistrict"><?php echo _translate("District/County"); ?></label>
															<select class="form-control stReportFilter select2 select2-element" id="stDistrict" name="stDistrict" title="<?php echo _translate('Please select District/County'); ?>" onchange="getByDistrict('stfacilityName',this.value)">
															</select>
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="stfacilityName"><?php echo _translate("Facility Name"); ?></label>
															<select class="form-control stReportFilter" id="stfacilityName" name="stfacilityName" title="<?php echo _translate('Please select facility name'); ?>" multiple="multiple">
															<?= $facilitiesDropdown; ?>
															</select>
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="stSampleCollectionDate"><?php echo _translate("Sample Collection Date "); ?></label>
															<input type="text" id="stSampleCollectionDate" name="stSampleCollectionDate" class="form-control stReportFilter" placeholder="<?= _translate('Select Sample Collection date'); ?>" style="background:#fff;" />
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="stImplementingPartner"><?php echo _translate("Implementing Partner"); ?></label>
															<select name="stImplementingPartner" id="stImplementingPartner" class="form-control stReportFilter" title="<?php echo _translate('Please choose implementing partner'); ?>">
															<option value=""> <?php echo _translate("-- Select --"); ?> </option>
															<?php foreach ($implementingPartnerList as $implementingPartner) { ?>
															<option value="<?php echo base64_encode((string) $implementingPartner['i_partner_id']); ?>"><?= $implementingPartner['i_partner_name']; ?></option>
															<?php } ?>
															</select>
														</div>
													</div>
												</div>
												<div class="filter-actions">
													&nbsp;<input type="button" onclick="sampleTestingReport();" value="<?= _translate('Search'); ?>" class="searchBtn btn btn-success btn-sm">
													&nbsp;<button type="button" class="btn btn-default btn-sm" onclick="resetFilters('stReportFilter');"><span>
													<?= _translate("Reset"); ?>
													</span></button>
												</div>
												</div>
											</div>
											<figure class="highcharts-figure">
												<div id="container"></div>
												<div id="sampleTestingResultDetails">
													<p class="highcharts-description">
													</p>
											</figure>
										</div>
										<div class="tab-pane fade" id="patientTestHistoryFormReport">
											<div class="box box-default report-filter-box">
												<div class="box-header with-border report-filter-header">
													<h3 class="box-title"><em class="fa-solid fa-filter"></em> <?php echo _translate("Filters"); ?></h3>
													<span class="report-filter-summary"></span>
													<div class="box-tools pull-right">
														<button type="button" class="btn btn-box-tool" data-widget="collapse" title="<?php echo _translate("Show or hide filters"); ?>"><em class="fa fa-minus"></em></button>
													</div>
												</div>
												<div class="box-body">
												<div class="row">
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="childId"><?php echo _translate("Child ID"); ?></label>
															<input type="text" id="childId" name="childId" class="form-control patientHistoryFilter" placeholder="<?php echo _translate('Enter Child ID'); ?>" style="background:#fff;" />
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="childName"><?php echo _translate("Child Name"); ?></label>
															<input type="text" id="childName" name="childName" class="form-control patientHistoryFilter" placeholder="<?php echo _translate('Enter Child Name'); ?>" style="background:#fff;" />
														</div>
													</div>
													<div class="col-md-4 col-sm-6">
														<div class="form-group">
															<label class="control-label" for="pthImplementingPartner"><?php echo _translate("Implementing Partner"); ?></label>
															<select name="pthImplementingPartner" id="pthImplementingPartner" class="form-control patientHistoryFilter" title="<?php echo _translate('Please choose implementing partner'); ?>">
															<option value=""> <?php echo _translate("-- Select --"); ?> </option>
															<?php foreach ($implementingPartnerList as $implementingPartner) { ?>
															<option value="<?php echo base64_encode((string) $implementingPartner['i_partner_id']); ?>"><?= $implementingPartner['i_partner_name']; ?></option>
															<?php } ?>
															</select>
														</div>
													</div>
												</div>
												<div class="filter-actions">
													<input type="button" onclick="searchVlRequestData();" value="<?= _translate('Search'); ?>" class="btn btn-success btn-sm">

													&nbsp;<button type="button" class="btn btn-default btn-sm" onclick="document.location.href = document.location"><span><?= _translate('Reset'); ?></span></button>

													<button class="btn btn-success btn-sm" type="button" onclick="exportPatientTesthistoryInexcel()"><em class="fa-solid fa-cloud-arrow-down"></em>
													<?php echo _translate("Export to excel"); ?>
													</button>
												</div>
												</div>
											</div>
											<table aria-describedby="table" id="patientTestHistoryReport" class="table table-bordered table-striped" aria-hidden="true">
												<thead>
													<tr>
														<th>
															<?php echo _translate("Child's ID"); ?>
														</th>
														<th scope="row">
															<?php echo _translate("Child's Name"); ?>
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
						</div><!-- /.box-body -->
						<!-- /.box -->
					</div>
					<!-- /.col -->
				</div>
				<!-- /.row -->
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
	$(document).ready(function() {
		$("#state,#rjtState,#noResultState,#stState").select2({
			placeholder: "<?php echo _translate("Select Province"); ?>",
			width: '100%'
		});
		$("#district,#rjtDistrict,#noResultDistrict,#stDistrict").select2({
			placeholder: "<?php echo _translate("Select District"); ?>",
			width: '100%'
		});
		$("#hvlFacilityName,#rjtFacilityName,#noResultFacilityName,#stfacilityName").select2({
			placeholder: "<?php echo _translate("Select Facilities"); ?>"
		});
		$("#formField").select2({
			placeholder: "<?php echo _translate("Select Fields"); ?>"
		});
		$('#hvlSampleTestDate,#rjtSampleTestDate,#noResultSampleTestDate,#sampleCollectionDate,#stSampleCollectionDate').daterangepicker({
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
			function(start, end) {
				startDate = start.format('YYYY-MM-DD');
				endDate = end.format('YYYY-MM-DD');
			});
		$('#hvlSampleTestDate,#rjtSampleTestDate,#noResultSampleTestDate,#sampleCollectionDate').val('');
		ClinicReports.registerTab('highViralLoadReport', { init: highViralLoadReport, table: function () { return oTableViralLoad; } });
		ClinicReports.registerTab('sampleRjtReport', { init: sampleRjtReport, table: function () { return oTableRjtReport; } });
		ClinicReports.registerTab('notAvailReport', { init: notAvailReport, table: function () { return oTablenotAvailReport; } });
		ClinicReports.registerTab('incompleteFormReport', { init: incompleteForm, table: function () { return oTableincompleteReport; } });
		ClinicReports.registerTab('sampleTestingReport', { init: getSampleTestingResult, search: sampleTestingReport });
		ClinicReports.registerTab('patientTestHistoryFormReport', { init: patientHistoryReport, table: function () { return oTablepatientTestHistoryReport; } });
		ClinicReports.start();
		$("#highViralLoadReport input, #highViralLoadReport select, #sampleRjtReport input, #sampleRjtReport select, #notAvailReport input, #notAvailReport select, #incompleteFormReport input, #incompleteFormReport select, #patientTestHistoryFormReport input, #patientTestHistoryFormReport select").on("change", function() {
			searchExecuted = false;
		});
	});

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
					"sClass": "center"
				},
			],
			"aaSorting": [
				[<?= ($general->isStandaloneInstance()) ? 5 : 6; ?>, "desc"]
			],
			"bProcessing": true,
			"bServerSide": true,
			"sAjaxSource": "getPositiveEidResultDetails.php",
			"fnServerData": function(sSource, aoData, fnCallback) {
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
			"fnServerData": function(sSource, aoData, fnCallback) {
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
					"name": "sampleRejectionReason",
					"value": $("#sampleRejectionReason").val()
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
			"fnServerData": function(sSource, aoData, fnCallback) {
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
			],
			"aaSorting": [
				[<?= ($general->isStandaloneInstance()) ? 1 : 2; ?>, "desc"]
			],
			"bProcessing": true,
			"bServerSide": true,
			"sAjaxSource": "dataQualityCheck.php",
			"fnServerData": function(sSource, aoData, fnCallback) {
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
			"fnServerData": function(sSource, aoData, fnCallback) {
				aoData.push({
					"name": "childId",
					"value": $("#childId").val()
				});
				aoData.push({
					"name": "childName",
					"value": $("#childName").val()
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
	   of them cost a query per tab to look at one. Only the visible tab is
	   drawn, and the promise settles when its request comes back -- which is
	   what the exports wait on before asking the server to replay the query. */
	function searchVlRequestData() {
		searchExecuted = true;
		return ClinicReports.searchActive();
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
		$.post("/eid/management/eidClinicResultExportInExcel.php", {
				Sample_Test_Date: $("#hvlSampleTestDate").val(),
				Batch_Code: $("#hvlBatchCode  option:selected").text(),
				Sample_Type: $("#hvlSampleType  option:selected").text(),
				Facility_Name: $("#hvlFacilityName  option:selected").text(),
				Sex: $("#hvlGender  option:selected").text(),
				markAsComplete: markAsComplete
			},
			function(data) {
				if (data == "" || data == null || data == undefined) {
					$.unblockUI();
					alert("<?php echo _translate("Unable to generate the excel file"); ?>");
				} else {
					$.unblockUI();
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
		$.post("/eid/management/eidRejectedResultExportInExcel.php", {
				Sample_Test_Date: $("#rjtSampleTestDate").val(),
				Batch_Code: $("#rjtBatchCode  option:selected").text(),
				Sample_Type: $("#rjtSampleType  option:selected").text(),
				Facility_Name: $("#rjtFacilityName  option:selected").text(),
				Sex: $("#rjtGender  option:selected").text(),
				Rejection_Reason: $("#sampleRejectionReason  option:selected").val()
			},
			function(data) {
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
		$.post("/eid/management/eidNotAvailableResultExportInExcel.php", {
				Sample_Test_Date: $("#noResultSampleTestDate").val(),
				Batch_Code: $("#noResultBatchCode  option:selected").text(),
				Sample_Type: $("#noResultSampleType  option:selected").text(),
				Facility_Name: $("#noResultFacilityName  option:selected").text(),
				Sex: $("#noResultGender  option:selected").text()
			},
			function(data) {
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
		$.post("/eid/management/eidDataQualityExportInExcel.php", {
				Sample_Collection_Date: $("#sampleCollectionDate").val(),
				Field_Name: $("#formField  option:selected").text()
			},
			function(data) {
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
		$.post("/eid/management/eidPatientTesthistoryInExcel.php", {
				child_id: $("#childId").val(),
				child_name: $("#childName").val()
			},
			function(data) {
				if (data == "" || data == null || data == undefined) {
					$.unblockUI();
					alert("<?php echo _translate("Unable to generate the excel file"); ?>");
				} else {
					$.unblockUI();
					window.open('/download.php?f=' + data, '_blank');
				}
			});
	}

	function setSampleTestDate(obj) {
		$(".stDate").val($("#" + obj.id).val());
	}

	function getByProvince(districtId, facilityId, provinceId) {
		$("#" + districtId).html('');
		$("#" + facilityId).html('');
		$.post("/common/get-by-province-id.php", {
				provinceId: provinceId,
				districts: true,
				facilities: true,
			},
			function(data) {
				Obj = $.parseJSON(data);
				$("#" + districtId).html(Obj['districts']);
				$("#" + facilityId).html(Obj['facilities']);
			});

	}

	function getByDistrict(facilityId, districtId) {
		$("#" + facilityId).html('');
		$.post("/common/get-by-district-id.php", {
				districtId: districtId,
				facilities: true,
			},
			function(data) {
				Obj = $.parseJSON(data);
				$("#" + facilityId).html(Obj['facilities']);
			});
	}

	function resetFilters(filtersClass) {
		$('.' + filtersClass).val('');
		$('.' + filtersClass).val(null).trigger('change');
	}

	function sampleTestingReport() {
		$.when(
				getSampleTestingResult()
			)
			.done(function() {
				$.unblockUI();
				$(window).scroll();
			});

		$(window).on('beforeunload', function() {
			if (currentXHR !== null && currentXHR !== undefined) {
				currentXHR.abort();
			}
		});
	}

	function getSampleTestingResult() {
		currentXHR = $.post("/eid/management/eid-sample-testing-report.php", {
				sampleCollectionDate: $("#stSampleCollectionDate").val(),
				state: $('#stState').val(),
				district: $('#stDistrict').val(),
				facilityName: $('#stfacilityName').val(),
				implementingPartner: $('#stImplementingPartner').val(),
			},
			function(data) {
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
		$path = '/eid/results/generate-result-pdf.php';
		?>
		$.post("<?php echo $path; ?>", {
				source: 'print',
				id: id
			},
			function(data) {
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
