<?php
ob_start();
#require_once('../startup.php');
include_once(APPLICATION_PATH . '/header.php');

$fQuery = "SELECT * FROM facility_type";
$fResult = $db->rawQuery($fQuery);
$pQuery = "SELECT * FROM province_details";
$pResult = $db->rawQuery($pQuery);
?>
<style>
	.ms-choice{
		border: 0px solid #aaa;
	}
</style>
<link href="/assets/css/jasny-bootstrap.min.css" rel="stylesheet" />
<link rel="stylesheet" href="/assets/css/jquery.multiselect.css" type="text/css" />
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><i class="fa fa-gears"></i> Add Facility</h1>
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
										<select class="form-control isRequired" id="facilityType" name="facilityType" title="Please select facility type" onchange="<?php echo ($sarr['user_type'] == 'remoteuser') ? 'getFacilityUser();' : ''; ?>; getTestType();">
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
									<label for="contactPerson" class="col-lg-4 control-label">Contact Person</label>
									<div class="col-lg-7">
										<input type="text" class="form-control" id="contactPerson" name="contactPerson" placeholder="Contact Person" />
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="phoneNo" class="col-lg-4 control-label">Phone Number</label>
									<div class="col-lg-7">
										<input type="text" class="form-control checkNum" id="phoneNo" name="phoneNo" placeholder="Phone Number" onblur="checkNameValidation('facility_details','facility_mobile_numbers',this,null,'The mobile no that you entered already exists.Enter another mobile no.',null)" />
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="state" class="col-lg-4 control-label">Province/State <span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<select name="state" id="state" class="form-control isRequired" title="Please choose province/state">
											<option value=""> -- Select -- </option>
											<?php
											foreach ($pResult as $province) {
											?>
												<option value="<?php echo $province['province_name']; ?>"><?php echo $province['province_name']; ?></option>
											<?php
											}
											?>
											<option value="other">Other</option>
										</select>
										<input type="text" class="form-control" name="provinceNew" id="provinceNew" placeholder="Enter Province/State" title="Please enter province/state" style="margin-top:4px;display:none;" />
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="district" class="col-lg-4 control-label">District/County <span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<input type="text" class="form-control isRequired" id="district" name="district" placeholder="District/County" title="Please enter district/county" />
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
										<input type="text" class="form-control checkNum" id="latitude" name="latitude" placeholder="Latitude" title="Please enter latitude" />
									</div>
								</div>
							</div>
						</div>
						<div class="row">

							<div class="col-md-6">
								<div class="form-group">
									<label for="longitude" class="col-lg-4 control-label">Longitude</label>
									<div class="col-lg-7">
										<input type="text" class="form-control checkNum" id="longitude" name="longitude" placeholder="Longitude" title="Please enter longitude" />
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="testType" class="col-lg-4 control-label">Test Type</label>
									<div class="col-lg-7">
										<select type="text" class="" id="testType" name="testType" title="Choose one test type" onchange="getTestType();" multiple>
											<option value="vl">Viral Load</option>
											<option value="eid">Early Infant Diagnosis</option>
											<option value="covid19">Covid-19</option>
											<?php if(isset($systemConfig['modules']['hepatitis']) && $systemConfig['modules']['hepatitis'] == true) {?> 
												<option value='hepatitis'>Hepatitis</option>
											<?php } ?>
										</select>
									</div>
								</div>
							</div>
						</div>
						<div class="row logoImage" style="display:none;">
							<div class="col-md-6">
								<div class="form-group">
									<label for="" class="col-lg-4 control-label">Logo Image </label>
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
							<div class="col-md-6">
								<div class="form-group">
									<label for="" class="col-lg-4 control-label">Header Text</label>
									<div class="col-lg-7">
										<input type="text" class="form-control " id="headerText" name="headerText" placeholder="Header Text" title="Please enter header text" />
									</div>
								</div>
							</div>
						</div>

						<div class="row" id="userDetails">

						</div>

						<div class="row" id="testDetails">

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

	});

	function validateNow() {
		var selVal = [];
		$('#search_to option').each(function(i, selected) {
			selVal[i] = $(selected).val();
		});
		$("#selectedUser").val(selVal);
		flag = deforayValidator.init({
			formId: 'addFacilityForm'
		});

		if (flag) {
			$.blockUI();
			document.getElementById('addFacilityForm').submit();
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
				}
				else if (testType[i] == 'hepatitis') {
					testOrg = 'Hepatitis';
					var extraDiv = '<td></td>';
				}
				div += '<tr><td>' + testOrg + '<input type="hidden" name="testData[]" id ="testData' + i + '" value="' + testType[i] + '" /></td>';
				div += '<td><input type="text" class=" isRequired" name="monTar[]" id ="monTar' + i + '" value="" title="Please enter monthly target"/></td>';
				div += extraDiv;
				div += '</tr>';
			}
			div += '</tbody></table>';
			$("#testDetails").html(div);
		} else {
			$("#testDetails").html('');
		}
	}
</script>

<?php
include(APPLICATION_PATH . '/footer.php');
?>