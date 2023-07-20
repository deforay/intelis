<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\GeoLocationsService;
use App\Services\UsersService;



require_once APPLICATION_PATH . '/header.php';
/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var GeoLocationsService $geolocationService */
$geolocationService = ContainerRegistry::get(GeoLocationsService::class);

$fQuery = "SELECT * FROM facility_type";
$fResult = $db->rawQuery($fQuery);
$pQuery = "SELECT * FROM geographical_divisions WHERE geo_parent = 0 and geo_status='active'";
$pResult = $db->rawQuery($pQuery);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);
$userResult = $usersService->getAllUsers();

$userInfo = [];
foreach ($userResult as $user) {
	$userInfo[$user['user_id']] = ($user['user_name']);
}

$reportFormats = [];
if (isset(SYSTEM_CONFIG['modules']['covid19']) && SYSTEM_CONFIG['modules']['covid19'] === true) {
	$reportFormats['covid19'] = $general->activeReportFormats('covid-19');
}
if (isset(SYSTEM_CONFIG['modules']['eid']) && SYSTEM_CONFIG['modules']['eid'] === true) {
	$reportFormats['eid'] = $general->activeReportFormats('eid');
}
if (isset(SYSTEM_CONFIG['modules']['vl']) && SYSTEM_CONFIG['modules']['vl'] === true) {
	$reportFormats['vl'] = $general->activeReportFormats('vl');
}

if (isset(SYSTEM_CONFIG['modules']['hepatitis']) && SYSTEM_CONFIG['modules']['hepatitis'] === true) {
	$reportFormats['hepatitis'] = $general->activeReportFormats('hepatitis');
}

if (isset(SYSTEM_CONFIG['modules']['tb']) && SYSTEM_CONFIG['modules']['tb'] === true) {
	$reportFormats['tb'] = $general->activeReportFormats('tb');
}
if (isset(SYSTEM_CONFIG['modules']['generic-tests']) && SYSTEM_CONFIG['modules']['generic-tests'] === true) {
	$reportFormats['generic-tests'] = $general->activeReportFormats('generic-tests');
}
$geoLocationParentArray = $geolocationService->fetchActiveGeolocations();
if (isset($_GET['total'])) {
	$addedRecords = $_GET['total'] - $_GET['notAdded'];
}
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
		<h1><em class="fa-solid fa-hospital"></em>
			<?php echo _("Upload Facility"); ?>
		</h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em>
					<?php echo _("Home"); ?>
				</a></li>
			<li class="active">
				<?php echo _("Facilities"); ?>
			</li>
		</ol>
	</section>

	<!-- Main content -->
	<section class="content">
		<div class="box box-default">
			<div class="box-header with-border">
				<div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span>
					<?php echo _("indicates required field"); ?> &nbsp;
				</div>
			</div>
			<!-- /.box-header -->
			<div class="box-body">
				<!-- form start -->
				<form class="form-horizontal" method='post' name='uploadFacilityForm' id='uploadFacilityForm' autocomplete="off" enctype="multipart/form-data" action="upload-facilities-helper.php">
					<div class="box-body">
						<div class="row">
							<div class="col-md-6">
								<?php if (isset($_GET['total'])) { ?>
									<span style="margin-left:74px; color:green;">Total Records : <?= $_GET['total']; ?> | Facilities Added : <?= $addedRecords; ?> | Facilities Not Added : <?= $_GET['notAdded']; ?></span>
									<?php if ($_GET['notAdded'] > 0) { ?>
										<a class="text-success" style="text-decoration:underline;margin-left:74px; margin-bottom:10px;" href="/temporary/INCORRECT-FACILITY-ROWS.xlsx" download>Download the Excel sheet of incorrect rows of facilities</a><br><br>
									<?php } ?>
								<?php } ?>

								<div class="form-group">
									<label for="facilityName" class="col-lg-4 control-label">
										<?php echo _("Upload File"); ?> <span class="mandatory">*</span>
									</label>
									<div class="col-lg-7">
										<input type="file" class="form-control isRequired" id="facilitiesInfo" name="facilitiesInfo" placeholder="<?php echo _('Facility Name'); ?>" title="<?php echo _('Please upload file'); ?>" onblur='checkNameValidation("facility_details","facility_name",this,null,"<?php echo _("The facility name that you entered already exists.Enter another name"); ?>",null)' />
										<a class="text-primary" style="text-decoration:underline;" href="/files/facilities/Facilities_Bulk_Upload_Excel_Format.xlsx" download>Click here to download the Excel format for uploading facilities</a>
									</div>
								</div>
							</div>

						</div>


					</div>

			</div>
			<!-- /.box-body -->
			<div class="box-footer">
				<input type="hidden" name="selectedUser" id="selectedUser" />
				<a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;">
					<?php echo _("Submit"); ?>
				</a>
				<a href="facilities.php" class="btn btn-default">
					<?php echo _("Cancel"); ?>
				</a>
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
<script type="text/javascript" src="/assets/js/multiselect.min.js"></script>
<script type="text/javascript" src="/assets/js/jasny-bootstrap.js"></script>

<script type="text/javascript">
	$(document).ready(function() {
		$("#testType").multipleSelect({
			placeholder: '<?php echo _("Select Test Type"); ?>',
			width: '100%'
		});
		$(".select2").select2({
			placeholder: '<?php echo _("Select Lab Manager"); ?>',
			width: '150px'
		});
		$("#availablePlatforms").multipleSelect({
			placeholder: '<?php echo _("Select Available Platforms"); ?>',
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
							$("#districtId").append('<option value="other"><?php echo _("Other"); ?></option>');
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
		tt = $("#testType").val();
		if (tt == "") {
			alert("Please choose one test type");
			return false;
		}
		flag = deforayValidator.init({
			formId: 'uploadFacilityForm'
		});

		if (flag) {
			$.blockUI();
			document.getElementById('uploadFacilityForm').submit();
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

	$('#facilityType').on('change', function() {
		if (this.value == '2') {
			$("#allowResultUpload option[value=yes]").attr('selected', 'selected');
			$("#allowResultUpload option[value='']").removeAttr('selected', 'selected');
			$('.allowResultsUpload').show();
			$('#allowResultUpload').addClass('isRequired');
			$('#allowResultUpload').focus();
		} else {
			$("#allowResultUpload option[value=yes]").removeAttr('selected', 'selected');
			$("#allowResultUpload option[value='']").attr('selected', 'selected');
			$('.allowResultsUpload').hide();
			$('#allowResultUpload').removeClass('isRequired');
			$('#allowResultUpload').val('');
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
			var div = '<table aria-describedby="table" class="table table-bordered table-striped" aria-hidden="true" ><thead><th> Test Type</th> <th> Monthly Target <span class="mandatory">*</span></th><th>Suppressed Monthly Target <span class="mandatory">*</span></th> </thead><tbody>';
			for (var i = 0; i < testType.length; i++) {
				var testOrg = '';
				if (testType[i] == 'vl') {
					testOrg = 'Viral Load';
					var extraDiv = '<td><input type="text" class=" isRequired" name="supMonTar[]" id ="supMonTar' + i + '" value="" title="<?php echo _("Please enter Suppressed monthly target"); ?>"/></td>';
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
				} else if (testType[i] == 'generic-tests') {
					testOrg = 'Lab Tests';
					var extraDiv = '<td></td>';
				}
				div += '<tr><td>' + testOrg + '<input type="hidden" name="testData[]" id ="testData' + i + '" value="' + testType[i] + '" /></td>';
				div += '<td><input type="text" class=" isRequired" name="monTar[]" id ="monTar' + i + '" value="" title="<?php echo _("Please enter monthly target"); ?>"/></td>';
				div += extraDiv;
				div += '</tr>';
			}
			div += '</tbody></table>';
			// $("#testDetails").html(div); // commented the validation functionality code
		} else {
			$("#testDetails").html('');
		}

		if ($("#testType").val() != '') {
			$.post("/facilities/getSampleType.php", {
					testType: $("#testType").val()
				},
				function(data) {
					$("#sampleType").html(data);
				});
		} else {
			$("#sampleType").html('');
		}
	}
	let testCounter = 1;

	function addNewRow() {
		testCounter++;
		let rowString = `<tr>
			<td style="width:14%;"><input type="text" class="form-control" name="signName[]" id="signName${testCounter}" placeholder="<?php echo _("Name"); ?>" title="<?php echo _("Please enter the name"); ?>"></td>
			<td style="width:14%;"><input type="text" class="form-control" name="designation[]" id="designation${testCounter}" placeholder="<?php echo _("Designation"); ?>" title="<?php echo _("Please enter the Designation"); ?>"></td>
			<td style="width:14%;"><input type="file" name="signature[]" id="signature${testCounter}" placeholder="Signature" title="<?php echo _("Please enter the Signature"); ?>"></td>
			<td style="width:14%;">
				<select class="select2" id="testSignType${testCounter}" name="testSignType[${testCounter}][]" title="<?php echo _("Choose one test type"); ?>" multiple>
					<option value="vl"><?php echo _("Viral Load"); ?></option>
					<option value="eid"><?php echo _("Early Infant Diagnosis"); ?></option>
					<option value="covid19"><?php echo _("Covid-19"); ?></option>
					<option value='hepatitis'><?php echo _("Hepatitis"); ?></option>
				</select>
			</td>
			<td style="width:14%;"><input type="number" class="form-control" name="sortOrder[]" id="sortOrder${testCounter}" placeholder="<?php echo _("Display Order"); ?>" title="<?php echo _("Please enter the Display Order"); ?>"></td>
			<td style="width:14%;">
				<select class="form-control" id="signStatus${testCounter}" name="signStatus[]" title="<?php echo _("Please select the status"); ?>">
					<option value="active"><?php echo _("Active"); ?></option>
					<option value="inactive"><?php echo _("Inactive"); ?></option>
				</select>
			</td>
			<td style="vertical-align:middle;text-align: center;width:10%;">
				<a class="btn btn-xs btn-primary test-name-table" href="javascript:void(0);" onclick="addNewRow();"><em class="fa-solid fa-plus"></em></a>&nbsp;
				<a class="btn btn-xs btn-default test-name-table" href="javascript:void(0);" onclick="removeNewRow(this.parentNode.parentNode);"><em class="fa-solid fa-minus"></em></a>
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
require_once APPLICATION_PATH . '/footer.php';
