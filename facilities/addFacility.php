<?php
ob_start();
#require_once('../startup.php');
include_once(APPLICATION_PATH . '/header.php');
$general = new \Vlsm\Models\General();
$geolocation = new \Vlsm\Models\GeoLocations();

$fQuery = "SELECT * FROM facility_type";
$fResult = $db->rawQuery($fQuery);
$pQuery = "SELECT * FROM province_details";
$pResult = $db->rawQuery($pQuery);
$usersModel = new \Vlsm\Models\Users();
$userResult = $usersModel->getActiveUsers();

$userInfo = array();
foreach ($userResult as $user) {
	$userInfo[$user['user_id']] = ucwords($user['user_name']);
}

$cntId = $general->reportPdfNames();
if (isset($systemConfig['modules']['covid19']) && $systemConfig['modules']['covid19'] == true) {
	$reportFormats['covid19'] = $general->activeReportFormats('covid-19', $cntId['covid19'], null, true);
}
if (isset($systemConfig['modules']['eid']) && $systemConfig['modules']['eid'] == true) {
	$reportFormats['eid'] = $general->activeReportFormats('eid', $cntId['eid'], null, true);
}
if (isset($systemConfig['modules']['vl']) && $systemConfig['modules']['vl'] == true) {
	$reportFormats['vl'] = $general->activeReportFormats('vl', $cntId['vl'], null, true);
}
if ($arr['vl_form'] == 7) {
	if (isset($systemConfig['modules']['hepatitis']) && $systemConfig['modules']['hepatitis'] == true) {
		$reportFormats['hepatitis'] = $general->activeReportFormats('hepatitis', $cntId['hepatitis'], null, true);
	}
}
if (isset($systemConfig['modules']['tb']) && $systemConfig['modules']['tb'] == true) {
	$reportFormats['tb'] = $general->activeReportFormats('tb', $cntId['tb'], null, true);
}
$geoLocationParentArray = $geolocation->fetchActiveGeolocations(0, 0);
?>
<style>
	.ms-choice {
		border: 0px solid #aaa;
	}
</style>
<link href="/assets/css/jasny-bootstrap.min.css" rel="stylesheet" />
<link rel="stylesheet" href="/assets/css/jquery.multiselect.css" type="text/css" />
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><i class="fa fa-hospital-o"></i> Add Facility</h1>
		<ol class="breadcrumb">
			<li><a href="/"><i class="fa fa-dashboard"></i> Home</a></li>
			<li class="active">Facilities</li>
		</ol>
	</section>

	<!-- Main content -->
	<section class="content">
		<div class="box box-default">
			<div class="box-header with-border">
				<div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span> indicates required field &nbsp;</div>
			</div>
			<!-- /.box-header -->
			<div class="box-body">
				<!-- form start -->
				<form class="form-horizontal" method='post' name='addFacilityForm' id='addFacilityForm' autocomplete="off" enctype="multipart/form-data" action="addFacilityHelper.php">
					<div class="box-body">
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="facilityName" class="col-lg-4 control-label">Facility Name <span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<input type="text" class="form-control isRequired" id="facilityName" name="facilityName" placeholder="Facility Name" title="Please enter facility name" onblur="checkNameValidation('facility_details','facility_name',this,null,'The facility name that you entered already exists.Enter another name',null)" />
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="facilityCode" class="col-lg-4 control-label">Facility Code</label>
									<div class="col-lg-7">
										<input type="text" class="form-control" id="facilityCode" name="facilityCode" placeholder="Facility Code" title="Please enter facility code" onblur="checkNameValidation('facility_details','facility_code',this,null,'The code that you entered already exists.Try another code',null)" />
									</div>
								</div>
							</div>
						</div>

						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="otherId" class="col-lg-4 control-label">Other Id </label>
									<div class="col-lg-7">
										<input type="text" class="form-control" id="otherId" name="otherId" placeholder="Other Id" />
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="facilityType" class="col-lg-4 control-label">Facility Type <span class="mandatory">*</span> </label>
									<div class="col-lg-7">
										<select class="form-control isRequired" id="facilityType" name="facilityType" title="Please select facility type" onchange="<?php echo ($_SESSION['instanceType'] == 'remoteuser') ? 'getFacilityUser();' : ''; ?>; getTestType(); showSignature(this.value);">
											<option value=""> -- Select -- </option>
											<?php
											foreach ($fResult as $type) {
											?>
												<option value="<?php echo $type['facility_type_id']; ?>"><?php echo ucwords($type['facility_type_name']); ?></option>
											<?php
											}
											?>
										</select>
									</div>
								</div>
							</div>
						</div>

						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="email" class="col-lg-4 control-label">Email(s) </label>
									<div class="col-lg-7">
										<input type="text" class="form-control" id="email" name="email" placeholder="eg-email1@gmail.com,email2@gmail.com" />
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="testingPoints" class="col-lg-4 control-label">Testing Point(s)<br> <small>(comma separated)</small> </label>
									<div class="col-lg-7">
										<input type="text" class="form-control" id="testingPoints" name="testingPoints" placeholder="eg. VCT, PMTCT" />
									</div>
								</div>
							</div>
							<!--<div class="col-md-6">
                    <div class="form-group">
                        <label for="reportEmail" class="col-lg-4 control-label">Report Email(s) </label>
                        <div class="col-lg-7">
                        <textarea class="form-control" id="reportEmail" name="reportEmail" placeholder="eg-user1@gmail.com,user2@gmail.com" rows="3"></textarea>
                        </div>
                    </div>
                  </div>-->
						</div>
						<br>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="Lab Manager" class="col-lg-4 control-label">Lab Manager</label>
									<div class="col-lg-7">
										<select name="contactPerson" id="contactPerson" class="select2 form-control" title="Please choose the Lab Manager" style="width: 100%;">
											<?= $general->generateSelectOptions($userInfo, null, '-- Select --'); ?>
										</select>
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="phoneNo" class="col-lg-4 control-label">Phone Number</label>
									<div class="col-lg-7">
										<input type="text" class="form-control forceNumeric" id="phoneNo" name="phoneNo" placeholder="Phone Number" onblur="checkNameValidation('facility_details','facility_mobile_numbers',this,null,'The mobile no that you entered already exists.Enter another mobile no.',null)" />
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="state" class="col-lg-4 control-label">Province/State <span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<?php if (sizeof($geoLocationParentArray) > 0) { ?>
											<select name="stateId" id="stateId" class="form-control isRequired" title="Please choose province/state">
												<?= $general->generateSelectOptions($geoLocationParentArray, null, '-- Select --'); ?>
												<option value="other">Other</option>
											</select>
											<input type="text" class="form-control" name="provinceNew" id="provinceNew" placeholder="Enter Province/State" title="Please enter province/state" style="margin-top:4px;display:none;" />
											<input type="hidden" name="state" id="state" />
										<?php } else { ?>
											<input type="text" class="form-control" name="provinceNew" id="provinceNew" placeholder="Enter Province/State" title="Please enter province/state" style="margin-top:4px;" />
										<?php } ?>
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="district" class="col-lg-4 control-label">District/County <span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<select name="districtId" id="districtId" class="form-control isRequired" title="Please choose District/County">
											<option value="">-- Select --</option>
											<option value="other">Other</option>
										</select>
										<input type="text" class="form-control" name="districtNew" id="districtNew" placeholder="Enter District/County" title="Please enter District/County" style="margin-top:4px;display:none;" />
										<input type="hidden" id="district" name="district" />
									</div>
								</div>
							</div>
						</div>

						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="hubName" class="col-lg-4 control-label">Linked Hub Name (If Applicable)</label>
									<div class="col-lg-7">
										<input type="text" class="form-control" id="hubName" name="hubName" placeholder="Hub Name" title="Please enter hub name" />
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="address" class="col-lg-4 control-label">Address</label>
									<div class="col-lg-7">
										<textarea class="form-control" name="address" id="address" placeholder="Address"></textarea>
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="country" class="col-lg-4 control-label">Country</label>
									<div class="col-lg-7">
										<input type="text" class="form-control" id="country" name="country" placeholder="Country" />
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="latitude" class="col-lg-4 control-label">Latitude</label>
									<div class="col-lg-7">
										<input type="text" class="form-control forceNumeric" id="latitude" name="latitude" placeholder="Latitude" title="Please enter latitude" />
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="longitude" class="col-lg-4 control-label">Longitude</label>
									<div class="col-lg-7">
										<input type="text" class="form-control forceNumeric" id="longitude" name="longitude" placeholder="Longitude" title="Please enter longitude" />
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="testType" class="col-lg-4 control-label">Test Type</label>
									<div class="col-lg-7">
										<select type="text" class="" id="testType" name="testType[]" title="Choose one test type" onchange="getTestType();" multiple>
											<?php if (isset($systemConfig['modules']['vl']) && $systemConfig['modules']['vl'] == true) { ?>
												<option value="vl">Viral Load</option>
											<?php }
											if (isset($systemConfig['modules']['eid']) && $systemConfig['modules']['eid'] == true) { ?>
												<option value="eid">Early Infant Diagnosis</option>
											<?php }
											if (isset($systemConfig['modules']['covid19']) && $systemConfig['modules']['covid19'] == true) { ?>
												<option value="covid19">Covid-19</option>
											<?php }
											if (isset($systemConfig['modules']['hepatitis']) && $systemConfig['modules']['hepatitis'] == true) { ?>
												<option value='hepatitis'>Hepatitis</option>
											<?php }
											if (isset($systemConfig['modules']['tb']) && $systemConfig['modules']['tb'] == true) { ?>
												<option value='tb'>TB</option>
											<?php } ?>
										</select>
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6 availablePlatforms" style="display:none;">
								<div class="form-group">
									<label for="availablePlatforms" class="col-lg-4 control-label">Available Platforms</label>
									<div class="col-lg-7">
										<select type="text" id="availablePlatforms" name="availablePlatforms[]" title="Choose one Available Platforms" multiple>
											<option value="microscopy">Microscopy</option>
											<option value="xpert">Xpert</option>
											<option value="lam">Lam</option>
										</select>
									</div>
								</div>
							</div>
						</div>
						<div class="row labDiv" style="display:none;">
							<?php if (isset($systemConfig['modules']['vl']) && $systemConfig['modules']['vl'] == true) {
								$count = sizeof($reportFormats['vl']); ?>
								<div class="col-md-6" style="display:<?php echo ($count > 1) ? 'block' : 'none'; ?>">
									<div class="form-group">
										<label for="reportFormat" class="col-lg-4 control-label">Report Format For VL</label>
										<div class="col-lg-7">
											<select class="form-control" name='reportFormat[vl]' id='reportFormat' title="Please select the status" onchange="checkIfExist(this);">
												<?php if (($count > 1)) { ?>
													<option value="">-- Select --</option>
												<?php } ?>
												<?php foreach ($reportFormats['vl'] as $key => $value) { ?>
													<option value="<?php echo $key; ?>"><?php echo ucwords($value); ?></option>
												<?php } ?>
											</select>
										</div>
									</div>
								</div>
							<?php }
							if (isset($systemConfig['modules']['eid']) && $systemConfig['modules']['eid'] == true) {
								$count = sizeof($reportFormats['eid']); ?>
								<div class="col-md-6" style="display:<?php echo ($count > 1) ? 'block' : 'none'; ?>">
									<div class="form-group">
										<label for="reportFormat" class="col-lg-4 control-label">Report Format For EID</label>
										<div class="col-lg-7">
											<select class="form-control" name='reportFormat[eid]' id='reportFormat' title="Please select the status" onchange="checkIfExist(this);">
												<?php if (($count > 1)) { ?>
													<option value="">-- Select --</option>
												<?php } ?>
												<?php foreach ($reportFormats['eid'] as $key => $value) { ?>
													<option value="<?php echo $key; ?>"><?php echo ucwords($value); ?></option>
												<?php } ?>
											</select>
										</div>
									</div>
								</div>
							<?php }
							if (isset($systemConfig['modules']['covid19']) && $systemConfig['modules']['covid19'] == true) {
								$count = sizeof($reportFormats['covid19']); ?>
								<div class="col-md-6" style="display:<?php echo ($count > 1) ? 'block' : 'none'; ?>">
									<div class="form-group">
										<label for="reportFormat" class="col-lg-4 control-label">Report Format For Covid-19</label>
										<div class="col-lg-7">
											<select class="form-control" name='reportFormat[covid19]' id='reportFormat' title="Please select the status" onchange="checkIfExist(this);">
												<?php if (($count > 1)) { ?>
													<option value="">-- Select --</option>
												<?php } ?>
												<?php foreach ($reportFormats['covid19'] as $key => $value) { ?>
													<option value="<?php echo $key; ?>"><?php echo ucwords($value); ?></option>
												<?php } ?>
											</select>
										</div>
									</div>
								</div>
							<?php }
							if (isset($systemConfig['modules']['hepatitis']) && $systemConfig['modules']['hepatitis'] == true) {
								$count = sizeof($reportFormats['hepatitis']); ?>
								<div class="col-md-6" style="display:<?php echo ($count > 1) ? 'block' : 'none'; ?>">
									<div class="form-group">
										<label for="reportFormat" class="col-lg-4 control-label">Report Format For Hepatitis</label>
										<div class="col-lg-7">
											<select class="form-control" name='reportFormat[hepatitis]' id='reportFormat' title="Please select the status" onchange="checkIfExist(this);">
												<?php if (($count > 1)) { ?>
													<option value="">-- Select --</option>
												<?php } ?>
												<?php foreach ($reportFormats['hepatitis'] as $key => $value) { ?>
													<option value="<?php echo $key; ?>"><?php echo ucwords($value); ?></option>
												<?php } ?>
											</select>
										</div>
									</div>
								</div>
							<?php }
							if (isset($systemConfig['modules']['tb']) && $systemConfig['modules']['tb'] == true) {
								$count = sizeof($reportFormats['tb']); ?>
								<div class="col-md-6" style="display:<?php echo ($count > 1) ? 'block' : 'none'; ?>">
									<div class="form-group">
										<label for="reportFormat" class="col-lg-4 control-label">Report Format For TB</label>
										<div class="col-lg-7">
											<select class="form-control" name='reportFormat[tb]' id='reportFormat' title="Please select the status" onchange="checkIfExist(this);">
												<?php if (($count > 1)) { ?>
													<option value="">-- Select --</option>
												<?php } ?>
												<?php foreach ($reportFormats['tb'] as $key => $value) { ?>
													<option value="<?php echo $key; ?>"><?php echo ucwords($value); ?></option>
												<?php } ?>
											</select>
										</div>
									</div>
								</div>
							<?php } ?>
						</div>
						<div class="row logoImage" style="display:none;">
							<div class="col-md-6">
								<div class="form-group">
									<label for="labLogo" class="col-lg-4 control-label">Logo Image </label>
									<div class="col-lg-8">
										<div class="fileinput fileinput-new labLogo" data-provides="fileinput">
											<div class="fileinput-preview thumbnail" data-trigger="fileinput" style="width:200px; height:150px;">
												<img src="http://www.placehold.it/200x150/EFEFEF/AAAAAA&text=No image">
											</div>
											<div>
												<span class="btn btn-default btn-file"><span class="fileinput-new">Select image</span><span class="fileinput-exists">Change</span>
													<input type="file" id="labLogo" name="labLogo" title="Please select logo image">
												</span>
												<a href="#" class="btn btn-default fileinput-exists" data-dismiss="fileinput">Remove</a>
											</div>
										</div>
										<div class="box-body">
											Please make sure logo image size of: <code>80x80</code>
										</div>
									</div>
								</div>
							</div>
							<!-- <div class="col-md-6">
								<div class="form-group">
									<label for="stampLogo" class="col-lg-4 control-label">Stamp Image </label>
									<div class="col-lg-8">
										<div class="fileinput fileinput-new stampLogo" data-provides="fileinput">
											<div class="fileinput-preview thumbnail" data-trigger="fileinput" style="width:200px; height:150px;">
												<img src="http://www.placehold.it/200x150/EFEFEF/AAAAAA&text=No image">
											</div>
											<div>
												<span class="btn btn-default btn-file"><span class="fileinput-new">Select image</span><span class="fileinput-exists">Change</span>
													<input type="file" id="stampLogo" name="stampLogo" title="Please select stamp image">
												</span>
												<a href="#" class="btn btn-default fileinput-exists" data-dismiss="fileinput">Remove</a>
											</div>
										</div>
										<div class="box-body">
											Please make sure logo image size of: <code>80x80</code>
										</div>
									</div>
								</div>
							</div> -->
							<div class="col-md-6">
								<div class="form-group">
									<label for="" class="col-lg-4 control-label">Header Text</label>
									<div class="col-lg-7">
										<input type="text" class="form-control " id="headerText" name="headerText" placeholder="Header Text" title="Please enter header text" />
									</div>
								</div>
							</div>
						</div>

						<div class="row labDiv" style="display:none;">
							<table class="table table-bordered">
								<thead>
									<tr>
										<th>Name</th>
										<th>Designation</th>
										<th>Upload Sign</th>
										<th>Test Types</th>
										<th>Display Order</th>
										<th>Status</th>
										<th>Action</th>
									</tr>
								</thead>
								<tbody id="signDetails">
									<tr>
										<td style="width:14%;"><input type="text" class="form-control" name="signName[]" id="signName1" placeholder="Name" title="Please enter the name"></td>
										<td style="width:14%;"><input type="text" class="form-control" name="designation[]" id="designation1" placeholder="Designation" title="Please enter the Designation"></td>
										<td style="width:10%;"><input type="file" name="signature[]" id="signature1" placeholder="Signature" title="Please enter the Signature"></td>
										<td style="width:14%;">
											<select type="text" class="select2" id="testSignType1" name="testSignType[1][]" title="Choose one test type" multiple>
												<option value="vl">Viral Load</option>
												<option value="eid">Early Infant Diagnosis</option>
												<option value="covid19">Covid-19</option>
												<option value='hepatitis'>Hepatitis</option>
												<option value='tb'>TB</option>
											</select>
										</td>
										<td style="width:14%;"><input type="text" class="form-control" name="sortOrder[]" id="sortOrder1" placeholder="Display Order" title="Please enter the Display Order"></td>
										<td style="width:14%;">
											<select class="form-control" id="signStatus1" name="signStatus[]" title="Please select the status">
												<option value="active">Active</option>
												<option value="inactive">Inactive</option>
											</select>
										</td>
										<td style="vertical-align:middle;text-align: center;width:10%;">
											<a class="btn btn-xs btn-primary test-name-table" href="javascript:void(0);" onclick="addNewRow();"><i class="fa fa-plus"></i></a>&nbsp;
											<a class="btn btn-xs btn-default test-name-table" href="javascript:void(0);" onclick="removeNewRow(this.parentNode.parentNode);"><i class="fa fa-minus"></i></a>
										</td>
									</tr>
								</tbody>
							</table>
						</div>

						<div class="row" id="userDetails">

						</div>

						<div class="row" id="testDetails" style="display:none;">

						</div>

					</div>
					<!-- /.box-body -->
					<div class="box-footer">
						<input type="hidden" name="selectedUser" id="selectedUser" />
						<a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;">Submit</a>
						<a href="facilities.php" class="btn btn-default"> Cancel</a>
					</div>
					<!-- /.box-footer -->
				</form>
				<!-- /.row -->
			</div>
		</div>
		<!-- /.box -->

	</section>
	<!-- /.content -->
</div>

<script type="text/javascript" src="/assets/js/jquery.multiselect.js"></script>
<script type="text/javascript" src="/assets/js/jasny-bootstrap.js"></script>
<script type="text/javascript">
	$(document).ready(function() {
		$("#testType").multipleSelect({
			placeholder: 'Select Test Type',
			width: '100%'
		});
		$(".select2").select2({
			placeholder: 'Select Lab Manager',
			width: '150px'
		});
		$("#availablePlatforms").multipleSelect({
			placeholder: 'Select Available Platforms',
			width: '100%'
		});

		$("#stateId").change(function() {
			if ($(this).val() == 'other') {
				$('#provinceNew').show();
			} else {
				$('#provinceNew').hide();
				$('#state').val($("#stateId option:selected").text());
			}
			$.blockUI();
			var pName = $(this).val();
			if ($.trim(pName) != '') {
				$.post("/includes/siteInformationDropdownOptions.php", {
						pName: pName,
					},
					function(data) {
						if (data != "") {
							details = data.split("###");
							$("#districtId").html(details[1]);
							$("#districtId").append('<option value="other">Other</option>');
						}
					});
			}
			$.unblockUI();
		});

		$("#districtId").change(function() {
			if ($(this).val() == 'other') {
				$('#districtNew').show();
			} else {
				$('#districtNew').hide();
				$('#district').val($("#districtId option:selected").text());
			}
		});

	});

	function validateNow() {
		var selVal = [];
		$('#search_to option').each(function(i, selected) {
			selVal[i] = $(selected).val();
		});
		$("#selectedUser").val(selVal);


		$('#state').val($("#stateId option:selected").text());
		$('#district').val($("#districtId option:selected").text());

		flag = deforayValidator.init({
			formId: 'addFacilityForm'
		});

		if (flag) {
			$.blockUI();
			document.getElementById('addFacilityForm').submit();
		}
	}

	function showSignature(facilityType) {
		if (facilityType == 2) {
			$(".labDiv").show();
		} else {
			$(".labDiv").hide();
		}
	}

	function checkNameValidation(tableName, fieldName, obj, fnct, alrt, callback) {
		var removeDots = obj.value.replace(/\./g, "");
		var removeDots = removeDots.replace(/\,/g, "");
		//str=obj.value;
		removeDots = removeDots.replace(/\s{2,}/g, ' ');

		$.post("/includes/checkDuplicate.php", {
				tableName: tableName,
				fieldName: fieldName,
				value: removeDots.trim(),
				fnct: fnct,
				format: "html"
			},
			function(data) {
				if (data === '1') {
					alert(alrt);
					document.getElementById(obj.id).value = "";
				}
			});
	}

	$('#state').on('change', function() {
		if (this.value == 'other') {
			$('#provinceNew').show();
			$('#provinceNew').addClass('isRequired');
			$('#provinceNew').focus();
		} else {
			$('#provinceNew').hide();
			$('#provinceNew').removeClass('isRequired');
			$('#provinceNew').val('');
		}
	});

	function getFacilityUser() {
		if ($("#facilityType").val() == '1' || $("#facilityType").val() == '4') {
			$.post("/facilities/getFacilityMapUser.php", {
					fType: $("#facilityType").val()
				},
				function(data) {
					$("#userDetails").html(data);
				});
		} else {
			$("#userDetails").html('');
		}
		if ($("#facilityType").val() == '2') {
			$(".logoImage").show();
		} else {
			$(".logoImage").hide();
		}
	}

	function getTestType() {
		var facility = $("#facilityType").val();
		var testType = $("#testType").val();
		if (testType == 'tb') {
			$('.availablePlatforms').show();
		} else {
			$('.availablePlatforms').hide();
		}
		if (facility && (testType.length > 0) && facility == '2') {
			var div = '<table class="table table-bordered table-striped"><thead><th> Test Type</th> <th> Monthly Target <span class="mandatory">*</span></th><th>Suppressed Monthly Target <span class="mandatory">*</span></th> </thead><tbody>';
			for (var i = 0; i < testType.length; i++) {
				var testOrg = '';
				if (testType[i] == 'vl') {
					testOrg = 'Viral Load';
					var extraDiv = '<td><input type="text" class=" isRequired" name="supMonTar[]" id ="supMonTar' + i + '" value="" title="Please enter Suppressed monthly target"/></td>';
				} else if (testType[i] == 'eid') {
					testOrg = 'Early Infant Diagnosis';
					var extraDiv = '<td></td>';
				} else if (testType[i] == 'covid19') {
					testOrg = 'Covid-19';
					var extraDiv = '<td></td>';
				} else if (testType[i] == 'hepatitis') {
					testOrg = 'Hepatitis';
					var extraDiv = '<td></td>';
				} else if (testType[i] == 'tb') {
					testOrg = 'TB';
					var extraDiv = '<td></td>';
				}
				div += '<tr><td>' + testOrg + '<input type="hidden" name="testData[]" id ="testData' + i + '" value="' + testType[i] + '" /></td>';
				div += '<td><input type="text" class=" isRequired" name="monTar[]" id ="monTar' + i + '" value="" title="Please enter monthly target"/></td>';
				div += extraDiv;
				div += '</tr>';
			}
			div += '</tbody></table>';
			// $("#testDetails").html(div); // commented the validation functionality code
		} else {
			$("#testDetails").html('');
		}
	}
	let testCounter = 1;

	function addNewRow() {
		testCounter++;
		let rowString = `<tr>
			<td style="width:14%;"><input type="text" class="form-control" name="signName[]" id="signName${testCounter}" placeholder="Name" title="Please enter the name"></td>
			<td style="width:14%;"><input type="text" class="form-control" name="designation[]" id="designation${testCounter}" placeholder="Designation" title="Please enter the Designation"></td>
			<td style="width:14%;"><input type="file" name="signature[]" id="signature${testCounter}" placeholder="Signature" title="Please enter the Signature"></td>
			<td style="width:14%;">
				<select type="text" class="select2" id="testSignType${testCounter}" name="testSignType[${testCounter}][]" title="Choose one test type" multiple>
					<option value="vl">Viral Load</option>
					<option value="eid">Early Infant Diagnosis</option>
					<option value="covid19">Covid-19</option>
					<option value='hepatitis'>Hepatitis</option>
				</select>
			</td>
			<td style="width:14%;"><input type="text" class="form-control" name="sortOrder[]" id="sortOrder${testCounter}" placeholder="Display Order" title="Please enter the Display Order"></td>
			<td style="width:14%;">
				<select class="form-control" id="signStatus${testCounter}" name="signStatus[]" title="Please select the status">
					<option value="active">Active</option>
					<option value="inactive">Inactive</option>
				</select>
			</td>
			<td style="vertical-align:middle;text-align: center;width:10%;">
				<a class="btn btn-xs btn-primary test-name-table" href="javascript:void(0);" onclick="addNewRow();"><i class="fa fa-plus"></i></a>&nbsp;
				<a class="btn btn-xs btn-default test-name-table" href="javascript:void(0);" onclick="removeNewRow(this.parentNode.parentNode);"><i class="fa fa-minus"></i></a>
			</td>
		</tr>`;
		$("#signDetails").append(rowString);

		$("#testSignType" + testCounter).multipleSelect({
			placeholder: 'Select Test Type',
			width: '150px'
		});
	}

	function removeNewRow(el) {
		$(el).fadeOut("slow", function() {
			el.parentNode.removeChild(el);
			rl = document.getElementById("signDetails").rows.length;
			if (rl == 0) {
				testCounter = 0;
				addNewRow();
			}
		});
	}
</script>

<?php
include(APPLICATION_PATH . '/footer.php');
?>