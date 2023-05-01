<?php

use App\Registries\ContainerRegistry;
use App\Services\GeoLocationsService;

$title = _("VL | Clinics Report");

require_once(APPLICATION_PATH . '/header.php');

/** @var GeoLocationsService $geolocationService */
$geolocationService = ContainerRegistry::get(GeoLocationsService::class);
$tsQuery = "SELECT * FROM r_sample_status";
$tsResult = $db->rawQuery($tsQuery);
//config  query
$configQuery = "SELECT * from global_config";
$configResult = $db->query($configQuery);
$arr = [];
// now we create an associative array so that we can easily create view variables
for ($i = 0; $i < sizeof($configResult); $i++) {
	$arr[$configResult[$i]['name']] = $configResult[$i]['value'];
}
$sQuery = "SELECT * FROM r_vl_sample_type where status='active'";
$sResult = $db->rawQuery($sQuery);
$fQuery = "SELECT * FROM facility_details where status='active'";
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

	.center {
		/*text-align:left;*/
	}
</style>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1> <em class="fa-solid fa-book"></em> <?php echo _("Clinic Reports"); ?></h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _("Home"); ?></a></li>
			<li class="active"><?php echo _("Clinic Reports"); ?></li>
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
								<div class="bs-example bs-example-tabs">
									<ul id="myTab" class="nav nav-tabs">
										<li class="active"><a href="#highViralLoadReport" data-toggle="tab"><?php echo _("High Viral Load Report"); ?></a></li>
										<li><a href="#sampleRjtReport" data-toggle="tab"><?php echo _("Sample Rejection Report"); ?></a></li>
										<li><a href="#notAvailReport" data-toggle="tab"><?php echo _("Results Not Available Report"); ?></a></li>
										<li><a href="#incompleteFormReport" data-toggle="tab"><?php echo _("Data Quality Check"); ?></a></li>
									</ul>
									<div id="myTabContent" class="tab-content">
										<div class="tab-pane fade in active" id="highViralLoadReport">
											<table aria-describedby="table" class="table" aria-hidden="true" style="margin-left:1%;margin-top:20px;width:98%;padding: 3%;">
												<tr>
													<td><strong><?php echo _("Sample Test Date"); ?>&nbsp;:</strong></td>
													<td>
														<input type="text" id="hvlSampleTestDate" name="hvlSampleTestDate" class="form-control stDate" placeholder="<?php echo _('Select Sample Test Date'); ?>" readonly style="width:220px;background:#fff;" onchange="setSampleTestDate(this)" />
													</td>
													<td>&nbsp;<strong><?php echo _("Batch Code"); ?>&nbsp;:</strong></td>
													<td>
														<select class="form-control" id="hvlBatchCode" name="hvlBatchCode" title="<?php echo _('Please select batch code'); ?>" style="width:220px;">
															<option value=""> <?php echo _("-- Select --"); ?> </option>
															<?php
															foreach ($batResult as $code) {
															?>
																<option value="<?php echo $code['batch_code']; ?>"><?php echo $code['batch_code']; ?></option>
															<?php
															}
															?>
														</select>
													</td>
													<td>&nbsp;<strong><?php echo _("Sample Type"); ?>&nbsp;:</strong></td>
													<td>
														<select style="width:220px;" class="form-control" id="hvlSampleType" name="sampleType" title="<?php echo _('Please select sample type'); ?>">
															<option value=""> <?php echo _("-- Select --"); ?> </option>
															<?php
															foreach ($sResult as $type) {
															?>
																<option value="<?php echo $type['sample_id']; ?>"><?php echo ($type['sample_name']); ?></option>
															<?php
															}
															?>
														</select>
													</td>
												</tr>
												<tr>
													<td><strong><?php echo _("Province/State"); ?>&nbsp;:</strong></td>
													<td>
														<select class="form-control select2-element" id="state" onchange="getByProvince('district','hvlFacilityName',this.value)" name="state" title="<?php echo _('Please select Province/State'); ?>">
															<?= $general->generateSelectOptions($state, null, _("-- Select --")); ?>
														</select>
													</td>

													<td><strong><?php echo _("District/County"); ?> :</strong></td>
													<td>
														<select class="form-control select2-element" id="district" name="district" title="<?php echo _('Please select Province/State'); ?>" onchange="getByDistrict('hvlFacilityName',this.value)">
														</select>
													</td>
													<td>&nbsp;<strong><?php echo _("Facility Name & Code"); ?>&nbsp;:</strong></td>
													<td>
														<select class="form-control" id="hvlFacilityName" name="hvlFacilityName" title="<?php echo _('Please select facility name'); ?>" multiple="multiple" style="width:220px;">
															<option value=""> <?php echo _("-- Select --"); ?> </option>
															<?php
															foreach ($fResult as $name) {
															?>
																<option value="<?php echo $name['facility_id']; ?>"><?php echo ($name['facility_name'] . "-" . $name['facility_code']); ?></option>
															<?php
															}
															?>
														</select>
													</td>

												</tr>
												<tr>
													<td>&nbsp;<strong><?php echo _("Contact Status"); ?>&nbsp;:</strong></td>
													<td>
														<select class="form-control" id="hvlContactStatus" name="hvlContactStatus" title="<?php echo _('Please select contact status'); ?>" style="width:220px;">
															<option value=""> <?php echo _("-- Select --"); ?> </option>
															<option value="yes"><?php echo _("Completed"); ?></option>
															<option value="no"><?php echo _("Not Completed"); ?></option>
															<option value="all" selected="selected"><?php echo _("All"); ?></option>
														</select>
													</td>
													<td><strong><?php echo _("Gender"); ?>&nbsp;:</strong></td>
													<td>
														<select name="hvlGender" id="hvlGender" class="form-control" title="<?php echo _('Please choose gender'); ?>" style="width:220px;" onchange="hideFemaleDetails(this.value,'hvlPatientPregnant','hvlPatientBreastfeeding');">
															<option value=""> <?php echo _("-- Select --"); ?> </option>
															<option value="male"><?php echo _("Male"); ?></option>
															<option value="female"><?php echo _("Female"); ?></option>
															<option value="not_recorded"><?php echo _("Not Recorded"); ?></option>
														</select>
													</td>
													<td><strong><?php echo _("Pregnant"); ?>&nbsp;:</strong></td>
													<td>
														<select name="hvlPatientPregnant" id="hvlPatientPregnant" class="form-control" title="<?php echo _('Please choose pregnant option'); ?>">
															<option value=""> <?php echo _("-- Select --"); ?> </option>
															<option value="yes"><?php echo _("Yes"); ?></option>
															<option value="no"><?php echo _("No"); ?></option>
														</select>
													</td>

												</tr>
												<tr>
													<td><strong><?php echo _("Breastfeeding"); ?>&nbsp;:</strong></td>
													<td>
														<select name="hvlPatientBreastfeeding" id="hvlPatientBreastfeeding" class="form-control" title="<?php echo _('Please choose option'); ?>">
															<option value=""> <?php echo _("-- Select --"); ?> </option>
															<option value="yes"><?php echo _("Yes"); ?></option>
															<option value="no"><?php echo _("No"); ?></option>
														</select>
													</td>
												</tr>
												<tr>
													<td colspan="6">&nbsp;<input type="button" onclick="searchVlRequestData();" value="<?php echo _('Search'); ?>" class="btn btn-success btn-sm">
														&nbsp;<button class="btn btn-danger btn-sm" onclick="document.location.href = document.location"><span><?php echo _("Reset"); ?></span></button>
														<button class="btn btn-success btn-sm" type="button" onclick="exportHighViralLoadInexcel()"><em class="fa-solid fa-cloud-arrow-down"></em> <?php echo _("Export to excel"); ?></button>
													</td>
												</tr>
											</table>

											<table id="highViralLoadReportTable" class="table table-bordered table-striped" aria-hidden="true">
												<thead>
													<tr>
														<th><?php echo _("Sample Code"); ?></th>
														<?php if ($_SESSION['instanceType'] != 'standalone') { ?>
															<th><?php echo _("Remote Sample"); ?> <br /><?php echo _("Code"); ?></th>
														<?php } ?>
														<th><?php echo _("Facility Name"); ?></th>
														<th><?php echo _("Patient ART no"); ?>.</th>
														<th><?php echo _("Patient's Name"); ?></th>
														<th><?php echo _("Patient Phone no"); ?>.</th>
														<th><?php echo _("Sample Collection Date"); ?></th>
														<th><?php echo _("Sample Tested Date"); ?></th>
														<th><?php echo _("Viral Load Lab"); ?></th>
														<th><?php echo _("Viral Load (cp/ml)"); ?></th>
														<th><?php echo _("Status"); ?></th>
													</tr>
												</thead>
												<tbody>
													<tr>
														<td colspan="7" class="dataTables_empty"><?php echo _("Loading data from server"); ?></td>
													</tr>
												</tbody>
											</table>
										</div>
										<div class="tab-pane fade" id="sampleRjtReport">
											<table aria-describedby="table" class="table" aria-hidden="true" style="margin-left:1%;margin-top:20px;width:98%;padding: 3%;">
												<tr>
													<td><strong><?php echo _("Sample Test Date"); ?>&nbsp;:</strong></td>
													<td>
														<input type="text" id="rjtSampleTestDate" name="rjtSampleTestDate" class="form-control stDate daterange" placeholder="<?php echo _('Select Sample Test Date'); ?>" readonly style="width:220px;background:#fff;" onchange="setSampleTestDate(this)" />
													</td>
													<td>&nbsp;<strong><?php echo _("Batch Code"); ?>&nbsp;:</strong></td>
													<td>
														<select class="form-control" id="rjtBatchCode" name="rjtBatchCode" title="<?php echo _('Please select batch code'); ?>" style="width:220px;">
															<option value=""> <?php echo _("-- Select --"); ?> </option>
															<?php
															foreach ($batResult as $code) {
															?>
																<option value="<?php echo $code['batch_code']; ?>"><?php echo $code['batch_code']; ?></option>
															<?php
															}
															?>
														</select>
													</td>
													<td>&nbsp;<strong><?php echo _("Sample Type"); ?>&nbsp;:</strong></td>
													<td>
														<select style="width:220px;" class="form-control" id="rjtSampleType" name="sampleType" title="<?php echo _('Please select sample type'); ?>">
															<option value=""> <?php echo _("-- Select --"); ?> </option>
															<?php
															foreach ($sResult as $type) {
															?>
																<option value="<?php echo $type['sample_id']; ?>"><?php echo ($type['sample_name']); ?></option>
															<?php
															}
															?>
														</select>
													</td>
												</tr>
												<tr>
													<td><strong><?php echo _("Province/State"); ?>&nbsp;:</strong></td>
													<td>
														<select class="form-control select2-element" id="rjtState" onchange="getByProvince('rjtDistrict','rjtFacilityName',this.value)" name="rjtState" title="<?php echo _('Please select Province/State'); ?>">
															<?= $general->generateSelectOptions($state, null, _("-- Select --")); ?>
														</select>
													</td>

													<td><strong><?php echo _("District/County"); ?> :</strong></td>
													<td>
														<select class="form-control select2-element" id="rjtDistrict" name="rjtDistrict" title="<?php echo _('Please select Province/State'); ?>" onchange="getByDistrict('rjtFacilityName',this.value)">
														</select>
													</td>
													<td>&nbsp;<strong><?php echo _("Facility Name & Code"); ?>&nbsp;:</strong></td>
													<td>
														<select class="form-control" id="rjtFacilityName" name="facilityName" title="<?php echo _('Please select facility name'); ?>" multiple="multiple" style="width:220px;">
															<option value=""> <?php echo _("-- Select --"); ?> </option>
															<?php
															foreach ($fResult as $name) {
															?>
																<option value="<?php echo $name['facility_id']; ?>"><?php echo ($name['facility_name'] . "-" . $name['facility_code']); ?></option>
															<?php
															}
															?>
														</select>
													</td>

												</tr>
												<tr>
													<td><strong><?php echo _("Gender"); ?>&nbsp;:</strong></td>
													<td>
														<select name="rjtGender" id="rjtGender" class="form-control" title="<?php echo _('Please choose gender'); ?>" style="width:220px;" onchange="hideFemaleDetails(this.value,'rjtPatientPregnant','rjtPatientBreastfeeding');">
															<option value=""> <?php echo _("-- Select --"); ?> </option>
															<option value="male"><?php echo _("Male"); ?></option>
															<option value="female"><?php echo _("Female"); ?></option>
															<option value="not_recorded"><?php echo _("Not Recorded"); ?></option>
														</select>
													</td>
													<td><strong><?php echo _("Pregnant"); ?>&nbsp;:</strong></td>
													<td>
														<select name="rjtPatientPregnant" id="rjtPatientPregnant" class="form-control" title="<?php echo _('Please choose pregnant option'); ?>">
															<option value=""> <?php echo _("-- Select --"); ?> </option>
															<option value="yes"><?php echo _("Yes"); ?></option>
															<option value="no"><?php echo _("No"); ?></option>
														</select>
													</td>
													<td><strong><?php echo _("Breastfeeding"); ?>&nbsp;:</strong></td>
													<td>
														<select name="rjtPatientBreastfeeding" id="rjtPatientBreastfeeding" class="form-control" title="<?php echo _('Please choose option'); ?>">
															<option value=""> <?php echo _("-- Select --"); ?> </option>
															<option value="yes"><?php echo _("Yes"); ?></option>
															<option value="no"><?php echo _("No"); ?></option>
														</select>
													</td>

												</tr>
												<tr>
													<td><strong><?php echo _("Rejection Reason"); ?>&nbsp;:</strong></td>
													<td colspan="2">
														<select name="rejectionReason" id="rejectionReason" class="form-control" title="Please choose reason" onchange="checkRejectionReason();">
															<option value="">-- Select --</option>
															<?php foreach ($rejectionTypeResult as $type) { ?>
																<optgroup label="<?php echo ($type['rejection_type']); ?>">
																	<?php foreach ($rejectionResult as $reject) {
																		if ($type['rejection_type'] == $reject['rejection_type']) {
																	?>
																			<option value="<?php echo $reject['rejection_reason_id']; ?>"><?= $reject['rejection_reason_name']; ?></option>
																	<?php }
																	} ?>
																</optgroup>
															<?php }
															if ($sarr['sc_user_type'] != 'vluser') {  ?>
																<option value="other">Other (Please Specify) </option>
															<?php } ?>
														</select>
													</td>
												</tr>
												<tr>
													<td colspan="6">&nbsp;<input type="button" onclick="searchVlRequestData();" value="<?php echo _('Search'); ?>" class="btn btn-success btn-sm">
														&nbsp;<button class="btn btn-danger btn-sm" onclick="document.location.href = document.location"><span><?php echo _("Reset"); ?></span></button>
														<button class="btn btn-success btn-sm" type="button" onclick="exportRejectedResultInexcel()"><em class="fa-solid fa-cloud-arrow-down"></em> <?php echo _("Export to excel"); ?></button>
													</td>
												</tr>
											</table>
											<table id="sampleRjtReportTable" class="table table-bordered table-striped" aria-hidden="true">
												<thead>
													<tr>
														<th><?php echo _("Sample Code"); ?></th>
														<?php if ($_SESSION['instanceType'] != 'standalone') { ?>
															<th><?php echo _("Remote Sample"); ?> <br /><?php echo _("Code"); ?></th>
														<?php } ?>
														<th><?php echo _("Facility Name"); ?></th>
														<th><?php echo _("Patient ART no"); ?>.</th>
														<th><?php echo _("Patient Name"); ?></th>
														<th><?php echo _("Sample Collection Date"); ?></th>
														<th><?php echo _("VL Lab Name"); ?></th>
														<th><?php echo _("Rejection Reason"); ?></th>
													</tr>
												</thead>
												<tbody>
													<tr>
														<td colspan="6" class="dataTables_empty"><?php echo _("Loading data from server"); ?></td>
													</tr>
												</tbody>
											</table>
										</div>
										<div class="tab-pane fade" id="notAvailReport">
											<table aria-describedby="table" class="table" aria-hidden="true" style="margin-left:1%;margin-top:20px;width:98%;padding: 3%;">
												<tr>
													<td><strong><?php echo _("Sample Collection Date"); ?>&nbsp;:</strong></td>
													<td>
														<input type="text" id="noResultSampleTestDate" name="noResultSampleTestDate" class="form-control stDate daterange" placeholder="<?php echo _('Select Sample Collection Date'); ?>" readonly style="width:220px;background:#fff;" onchange="setSampleTestDate(this)" />
													</td>
													<td>&nbsp;<strong><?php echo _("Batch Code"); ?>&nbsp;:</strong></td>
													<td>
														<select class="form-control" id="noResultBatchCode" name="noResultBatchCode" title="<?php echo _('Please select batch code'); ?>" style="width:220px;">
															<option value=""> <?php echo _("-- Select --"); ?> </option>
															<?php
															foreach ($batResult as $code) {
															?>
																<option value="<?php echo $code['batch_code']; ?>"><?php echo $code['batch_code']; ?></option>
															<?php
															}
															?>
														</select>
													</td>
													<td>&nbsp;<strong><?php echo _("Sample Type"); ?>&nbsp;:</strong></td>
													<td>
														<select style="width:220px;" class="form-control" id="noResultSampleType" name="sampleType" title="<?php echo _('Please select sample type'); ?>">
															<option value=""> <?php echo _("-- Select --"); ?> </option>
															<?php
															foreach ($sResult as $type) {
															?>
																<option value="<?php echo $type['sample_id']; ?>"><?php echo ($type['sample_name']); ?></option>
															<?php
															}
															?>
														</select>
													</td>
												</tr>
												<tr>
													<td><strong><?php echo _("Province/State"); ?>&nbsp;:</strong></td>
													<td>
														<select class="form-control select2-element" id="noResultState" onchange="getByProvince('noResultDistrict','noResultFacilityName',this.value)" name="rjtState" title="<?php echo _('Please select Province/State'); ?>">
															<?= $general->generateSelectOptions($state, null, _("-- Select --")); ?>
														</select>
													</td>

													<td><strong><?php echo _("District/County"); ?> :</strong></td>
													<td>
														<select class="form-control select2-element" id="noResultDistrict" name="noResultDistrict" title="<?php echo _('Please select Province/State'); ?>" onchange="getByDistrict('noResultFacilityName',this.value)">
														</select>
													</td>
													<td>&nbsp;<strong><?php echo _("Facility Name & Code"); ?>&nbsp;:</strong></td>
													<td>
														<select class="form-control" id="noResultFacilityName" name="facilityName" title="<?php echo _('Please select facility name'); ?>" multiple="multiple" style="width:220px;">
															<option value=""> <?php echo _("-- Select --"); ?> </option>
															<?php
															foreach ($fResult as $name) {
															?>
																<option value="<?php echo $name['facility_id']; ?>"><?php echo ($name['facility_name'] . "-" . $name['facility_code']); ?></option>
															<?php
															}
															?>
														</select>
													</td>

												</tr>
												<tr>
													<td><strong><?php echo _("Gender"); ?>&nbsp;:</strong></td>
													<td>
														<select name="noResultGender" id="noResultGender" class="form-control" title="<?php echo _('Please choose gender'); ?>" style="width:220px;" onchange="hideFemaleDetails(this.value,'noResultPatientPregnant','noResultPatientBreastfeeding');">
															<option value=""> <?php echo _("-- Select --"); ?> </option>
															<option value="male"><?php echo _("Male"); ?></option>
															<option value="female"><?php echo _("Female"); ?></option>
															<option value="not_recorded"><?php echo _("Not Recorded"); ?></option>
														</select>
													</td>
													<td><strong><?php echo _("Pregnant"); ?>&nbsp;:</strong></td>
													<td>
														<select name="noResultPatientPregnant" id="noResultPatientPregnant" class="form-control" title="<?php echo _('Please choose pregnant option'); ?>">
															<option value=""> <?php echo _("-- Select --"); ?> </option>
															<option value="yes"><?php echo _("Yes"); ?></option>
															<option value="no"><?php echo _("No"); ?></option>
														</select>
													</td>
													<td><strong><?php echo _("Breastfeeding"); ?>&nbsp;:</strong></td>
													<td>
														<select name="noResultPatientBreastfeeding" id="noResultPatientBreastfeeding" class="form-control" title="<?php echo _('Please choose option'); ?>">
															<option value=""> <?php echo _("-- Select --"); ?> </option>
															<option value="yes"><?php echo _("Yes"); ?></option>
															<option value="no"><?php echo _("No"); ?></option>
														</select>
													</td>
												</tr>
												<tr>
													<td colspan="6">&nbsp;<input type="button" onclick="searchVlRequestData();" value="<?php echo _('Search'); ?>" class="btn btn-success btn-sm">
														&nbsp;<button class="btn btn-danger btn-sm" onclick="document.location.href = document.location"><span><?php echo _("Reset"); ?></span></button>
														<button class="btn btn-success btn-sm" type="button" onclick="exportNotAvailableResultInexcel()"><em class="fa-solid fa-cloud-arrow-down"></em> <?php echo _("Export to excel"); ?></button>
													</td>
												</tr>
											</table>
											<table id="notAvailReportTable" class="table table-bordered table-striped" aria-hidden="true">
												<thead>
													<tr>
														<th><?php echo _("Sample Code"); ?></th>
														<?php if ($_SESSION['instanceType'] != 'standalone') { ?>
															<th><?php echo _("Remote Sample"); ?> <br /><?php echo _("Code"); ?></th>
														<?php } ?>
														<th><?php echo _("Facility Name"); ?></th>
														<th><?php echo _("Patient ART no"); ?>.</th>
														<th><?php echo _("Patient Name"); ?></th>
														<th><?php echo _("Sample Collection Date"); ?></th>
														<th><?php echo _("VL Lab Name"); ?></th>
														<th><?php echo _("Sample Status"); ?></th>
													</tr>
												</thead>
												<tbody>
													<tr>
														<td colspan="4" class="dataTables_empty"><?php echo _("Loading data from server"); ?></td>
													</tr>
												</tbody>
											</table>
										</div>
										<div class="tab-pane fade" id="incompleteFormReport">
											<table aria-describedby="table" class="table" aria-hidden="true" style="margin-left:1%;margin-top:20px;width:98%;padding: 3%;">
												<tr>
													<td><strong><?php echo _("Sample Collection Date"); ?>&nbsp;:</strong></td>
													<td>
														<input type="text" id="sampleCollectionDate" name="sampleCollectionDate" class="form-control" placeholder="<?php echo _('Select Sample Collection Date'); ?>" readonly style="width:220px;background:#fff;" />
													</td>
													<td>&nbsp;<strong><?php echo _("Fields"); ?>&nbsp;:</strong></td>
													<td>
														<select class="form-control" id="formField" name="formField" multiple="multiple" title="<?php echo _('Please fields'); ?>" style="width:220px;">
															<option value=""> <?php echo _("-- Select --"); ?> </option>
															<option value="sample_code"><?php echo _("Sample Code"); ?></option>
															<option value="sample_collection_date"><?php echo _("Sample Collection Date"); ?></option>
															<option value="sample_batch_id"><?php echo _("Batch Code"); ?></option>
															<option value="patient_art_no"><?php echo _("Unique ART No"); ?>.</option>
															<option value="patient_first_name"><?php echo _("Patient Name"); ?></option>
															<option value="facility_id"><?php echo _("Facility Name"); ?></option>
															<option value="facility_state"><?php echo _("Province"); ?></option>
															<option value="facility_district"><?php echo _("County"); ?></option>
															<option value="sample_type"><?php echo _("Sample Type"); ?></option>
															<option value="result"><?php echo _("Result"); ?></option>
															<option value="result_status"><?php echo _("Status"); ?></option>
														</select>
													</td>
												</tr>

												<tr>
													<td colspan="4">&nbsp;<input type="button" onclick="searchVlRequestData();" value="<?php echo _('Search'); ?>" class="btn btn-success btn-sm">
														&nbsp;<button class="btn btn-danger btn-sm" onclick="document.location.href = document.location"><span><?php echo _("Reset"); ?></span></button>
														<button class="btn btn-success btn-sm" type="button" onclick="exportDataQualityInexcel()"><em class="fa-solid fa-cloud-arrow-down"></em> <?php echo _("Export to excel"); ?></button>
													</td>
												</tr>
											</table>
											<table id="incompleteReport" class="table table-bordered table-striped" aria-hidden="true">
												<thead>
													<tr>
														<th><?php echo _("Sample Code"); ?></th>
														<?php if ($_SESSION['instanceType'] != 'standalone') { ?>
															<th><?php echo _("Remote Sample"); ?> <br /><?php echo _("Code"); ?></th>
														<?php } ?>
														<th><?php echo _("Sample Collection Date"); ?></th>
														<th><?php echo _("Batch Code"); ?></th>
														<th><?php echo _("Unique ART No"); ?></th>
														<th><?php echo _("Patient's Name"); ?></th>
														<th><?php echo _("Facility Name"); ?></th>
														<th><?php echo _("Province/State"); ?></th>
														<th><?php echo _("District/County"); ?></th>
														<th><?php echo _("Sample Type"); ?></th>
														<th><?php echo _("Result"); ?></th>
														<th><?php echo _("Status"); ?></th>
													</tr>
												</thead>
												<tbody>
													<tr>
														<td colspan="13" class="dataTables_empty"><?php echo _("Loading data from server"); ?></td>
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
<script type="text/javascript" src="/assets/plugins/daterangepicker/daterangepicker.js"></script>
<script type="text/javascript">
	let searchExecuted = false;
	var oTableViralLoad = null;
	var oTableRjtReport = null;
	var oTablenotAvailReport = null;
	var oTableincompleteReport = null;
	$(document).ready(function() {
		$("#state,#rjtState,#noResultState").select2({
			placeholder: "<?php echo _("Select Province"); ?>"
		});
		$("#district,#rjtDistrict,#noResultDistrict").select2({
			placeholder: "<?php echo _("Select District"); ?>"
		});
		$("#hvlFacilityName,#rjtFacilityName,#noResultFacilityName").select2({
			placeholder: "<?php echo _("Select Facilities"); ?>"
		});
		$("#formField").select2({
			placeholder: "<?php echo _("Select Fields"); ?>"
		});
		$('#hvlSampleTestDate,#rjtSampleTestDate,#noResultSampleTestDate,#sampleCollectionDate').daterangepicker({
				locale: {
					cancelLabel: "<?= _("Clear"); ?>",
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
					'Last 12 Months': [moment().subtract(12, 'month').startOf('month'), moment().endOf('month')]
				}
			},
			function(start, end) {
				startDate = start.format('YYYY-MM-DD');
				endDate = end.format('YYYY-MM-DD');
			});
		$('#hvlSampleTestDate,#rjtSampleTestDate,#noResultSampleTestDate,#sampleCollectionDate').on('cancel.daterangepicker', function(ev, picker) {
			$(this).val('');
		});
		highViralLoadReport();
		sampleRjtReport();
		notAvailReport();
		incompleteForm();

		$("#highViralLoadReport input, #highViralLoadReport select, #sampleRjtReport input, #sampleRjtReport select, #notAvailReport input, #notAvailReport select, #incompleteFormReport input, #incompleteFormReport select").on("change", function() {
			searchExecuted = false;
		});

	});

	function highViralLoadReport() {
		$.blockUI();
		oTableViralLoad = $('#highViralLoadReportTable').dataTable({
			"oLanguage": {
				"sLengthMenu": "_MENU_ records per page"
			},
			"bJQueryUI": false,
			"bAutoWidth": false,
			"bInfo": true,
			"bScrollCollapse": true,
			//"bStateSave" : true,
			"bRetrieve": true,
			"aoColumns": [{
					"sClass": "center"
				},
				<?php if ($_SESSION['instanceType'] != 'standalone') { ?> {
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
			<?php if ($_SESSION['instanceType'] != 'standalone') { ?> "aaSorting": [
					[6, "desc"]
				],
			<?php } else { ?> "aaSorting": [
					[5, "desc"]
				],
			<?php } ?>
			//aaSorting: [[ 4, "desc" ]],
			"bProcessing": true,
			"bServerSide": true,
			"sAjaxSource": "getHighVlResultDetails.php",
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
		$.unblockUI();
	}

	function sampleRjtReport() {
		$.blockUI();
		oTableRjtReport = $('#sampleRjtReportTable').dataTable({
			"oLanguage": {
				"sLengthMenu": "_MENU_ records per page"
			},
			"bJQueryUI": false,
			"bAutoWidth": false,
			"bInfo": true,
			"bScrollCollapse": true,
			//"bStateSave" : true,
			"bRetrieve": true,
			"aoColumns": [{
					"sClass": "center"
				},
				<?php if ($_SESSION['instanceType'] != 'standalone') { ?> {
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
			],
			<?php if ($_SESSION['instanceType'] != 'standalone') { ?> "aaSorting": [
					[5, "desc"]
				],
			<?php } else { ?> "aaSorting": [
					[4, "desc"]
				],
			<?php } ?>
			//"aaSorting": [[ 3, "desc" ]],
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
		$.unblockUI();
	}

	function notAvailReport() {
		$.blockUI();
		oTablenotAvailReport = $('#notAvailReportTable').dataTable({
			"oLanguage": {
				"sLengthMenu": "_MENU_ records per page"
			},
			"bJQueryUI": false,
			"bAutoWidth": false,
			"bInfo": true,
			"bScrollCollapse": true,
			//"bStateSave" : true,
			"bRetrieve": true,
			"aoColumns": [{
					"sClass": "center"
				},
				<?php if ($_SESSION['instanceType'] != 'standalone') { ?> {
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
			<?php if ($_SESSION['instanceType'] != 'standalone') { ?> "aaSorting": [
					[5, "desc"]
				],
			<?php } else { ?> "aaSorting": [
					[4, "desc"]
				],
			<?php } ?>
			//"aaSorting": [[ 3, "desc" ]],
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
		$.unblockUI();
	}

	function incompleteForm() {
		$.blockUI();
		oTableincompleteReport = $('#incompleteReport').dataTable({
			"oLanguage": {
				"sLengthMenu": "_MENU_ records per page"
			},
			"bJQueryUI": false,
			"bAutoWidth": false,
			"bInfo": true,
			"bScrollCollapse": true,
			//"bStateSave" : true,
			"bRetrieve": true,
			"aoColumns": [{
					"sClass": "center"
				},
				<?php if ($_SESSION['instanceType'] != 'standalone') { ?> {
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
			<?php if ($_SESSION['instanceType'] != 'standalone') { ?> "aaSorting": [
					[2, "desc"]
				],
			<?php } else { ?> "aaSorting": [
					[1, "desc"]
				],
			<?php } ?>
			//"aaSorting": [[ 1, "desc" ]],
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
				$.ajax({
					"dataType": 'json',
					"type": "POST",
					"url": sSource,
					"data": aoData,
					"success": fnCallback
				});
			}
		});
		$.unblockUI();
	}

	function searchVlRequestData() {
		searchExecuted = true;
		$.blockUI();
		oTableViralLoad.fnDraw();
		oTableRjtReport.fnDraw();
		oTablenotAvailReport.fnDraw();
		//incompleteForm();
		oTableincompleteReport.fnDraw();
		$.unblockUI();
	}

	function updateStatus(id, value) {
		conf = confirm("<?php echo _("Do you wisht to change the contact completed status?"); ?>");
		if (conf) {
			$.post("/vl/program-management/updateContactCompletedStatus.php", {
					id: id,
					value: value
				},
				function(data) {
					alert("<?php echo _("Status updated successfully"); ?>");
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
		confm = confirm("<?php echo _("Do you want to mark these as complete ?"); ?>");
		if (confm) {
			var markAsComplete = true;
		}
		$.blockUI();
		$.post("/vl/program-management/vlHighViralLoadResultExportInExcel.php", {
				Sample_Test_Date: $("#hvlSampleTestDate").val(),
				Batch_Code: $("#hvlBatchCode  option:selected").text(),
				Sample_Type: $("#hvlSampleType  option:selected").text(),
				Facility_Name: $("#hvlFacilityName  option:selected").text(),
				Gender: $("#hvlGender  option:selected").text(),
				Pregnant: $("#hvlPatientPregnant  option:selected").text(),
				Breastfeeding: $("#hvlPatientBreastfeeding  option:selected").text(),
				markAsComplete: markAsComplete
			},
			function(data) {
				if (data == "" || data == null || data == undefined) {
					$.unblockUI();
					alert("<?php echo _("Unable to generate the excel file"); ?>");
				} else {
					$.unblockUI();
					location.href = '/temporary/' + data;
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
				Gender: $("#rjtGender  option:selected").text(),
				Pregnant: $("#rjtPatientPregnant  option:selected").text(),
				Breastfeeding: $("#rjtPatientBreastfeeding  option:selected").text(),
				RejectionReason: $("#rejectionReason  option:selected").val()
			},
			function(data) {
				if (data == "" || data == null || data == undefined) {
					$.unblockUI();
					alert("<?php echo _("Unable to generate the excel file"); ?>");
				} else {
					$.unblockUI();
					location.href = '/temporary/' + data;
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
				Gender: $("#noResultGender  option:selected").text(),
				Pregnant: $("#noResultPatientPregnant  option:selected").text(),
				Breastfeeding: $("#noResultPatientBreastfeeding  option:selected").text()
			},
			function(data) {
				if (data == "" || data == null || data == undefined) {
					$.unblockUI();
					alert("<?php echo _("Unable to generate the excel file"); ?>");
				} else {
					$.unblockUI();
					location.href = '/temporary/' + data;
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
				Field_Name: $("#formField  option:selected").text()
			},
			function(data) {
				if (data == "" || data == null || data == undefined) {
					$.unblockUI();
					alert("<?php echo _("Unable to generate the excel file"); ?>");
				} else {
					$.unblockUI();
					location.href = '/temporary/' + data;
				}
			});
	}

	function hideFemaleDetails(value, pregnant, breastFeeding) {
		if (value == 'female') {
			$("#" + pregnant).attr("disabled", false);
			$("#" + breastFeeding).attr("disabled", false);
		} else {
			$('select#' + pregnant + ' option').removeAttr("selected");
			$('select#' + breastFeeding + ' option').removeAttr("selected");
			$("#" + pregnant).attr("disabled", true);
			$("#" + breastFeeding).attr("disabled", true);
		}
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
				facilityCode: true
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
				facilityCode: true
			},
			function(data) {
				Obj = $.parseJSON(data);
				$("#" + facilityId).html(Obj['facilities']);
			});
	}
</script>
<?php
require_once(APPLICATION_PATH . '/footer.php');
