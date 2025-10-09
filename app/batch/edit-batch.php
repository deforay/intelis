<?php

use App\Services\TestsService;
use App\Services\UsersService;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;


// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());
$id = isset($_GET['id']) ? base64_decode((string) $_GET['id']) : null;


if (empty($_GET['type'])) {
	MiscUtility::redirect("/batch/batches.php");
}


$testType = $_GET['type'];
$genericTestType = null;

if ($testType == 'generic-tests') {
	$genericTestType = !empty($_GET['testType']) ? base64_decode((string) $_GET['testType']) : null;
}

$testShortCode = TestsService::getTestShortCode($testType);
$refTable = TestsService::getTestTableName($testType);
$refPrimaryColumn = TestsService::getPrimaryColumn($testType);
$sampleTypeTable = TestsService::getSpecimenTypeTable($testType);
$patientIdColumn = TestsService::getPatientIdColumn($testType);
$resultColumn = TestsService::getResultColumn($testType);

$title = $testShortCode . " | " . _translate("Edit Batch");
require_once APPLICATION_PATH . '/header.php';

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);
$healthFacilites = $facilitiesService->getHealthFacilities($testType);
$facilitiesDropdown = $general->generateSelectOptions($healthFacilites, null, "-- Select --");

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);
$userNameList = $usersService->getAllUsers(null, 'active', 'drop-down');


$batchQuery = "SELECT * FROM batch_details as b_d
                    LEFT JOIN instruments as i_c ON i_c.instrument_id=b_d.machine
                    WHERE batch_id=?";
$batchInfo = $db->rawQuery($batchQuery, [$id]);
if (!empty($batchInfo[0]['batch_attributes'])) {
	$batchAttribute = json_decode($batchInfo[0]['batch_attributes']);
	$sortBy = $batchAttribute->sort_by;
	$sortType = $batchAttribute->sort_type;
}
$bQuery = "(SELECT vl.sample_code,vl.sample_batch_id,
                    vl.$refPrimaryColumn,vl.$patientIdColumn,vl.facility_id,
                    vl.$resultColumn,vl.result_status,vl.lab_assigned_code,
                    f.facility_name,f.facility_code
                    FROM $refTable as vl
                    INNER JOIN facility_details as f ON vl.facility_id=f.facility_id
                    WHERE (vl.is_sample_rejected IS NULL
                                OR vl.is_sample_rejected = ''
                                OR vl.is_sample_rejected = 'no')
                    AND (vl.reason_for_sample_rejection IS NULL
                                OR vl.reason_for_sample_rejection =''
                                OR vl.reason_for_sample_rejection = 0)
                    AND vl.sample_code NOT LIKE ''
                    AND vl.sample_batch_id = ?";

/* if ($testType == 'generic-tests') {
	$bQuery .= " AND vl.test_type = ?";
} */

$bQuery .= ") UNION

                    (SELECT vl.sample_code,vl.sample_batch_id,
                        vl.$refPrimaryColumn,vl.$patientIdColumn ,vl.facility_id,
                        vl.$resultColumn,vl.result_status,vl.lab_assigned_code,
                        f.facility_name,f.facility_code
                        FROM $refTable as vl
                        INNER JOIN facility_details as f ON vl.facility_id=f.facility_id
                        WHERE (vl.sample_batch_id IS NULL OR vl.sample_batch_id = '')
                        AND (vl.is_sample_rejected IS NULL
                                    OR vl.is_sample_rejected like ''
                                    OR vl.is_sample_rejected like 'no')
                        AND (vl.reason_for_sample_rejection IS NULL
                                OR vl.reason_for_sample_rejection like ''
                                OR vl.reason_for_sample_rejection = 0)
                        AND (vl.$resultColumn is NULL or vl.$resultColumn = '')
                        AND vl.sample_code!=''
                        ORDER BY vl.last_modified_datetime ASC)";
$result = $db->rawQuery($bQuery, [$id]);
$testPlatformResult = $general->getTestingPlatforms($testType);

$sQuery = "SELECT * FROM $sampleTypeTable where status='active'";
$sResult = $db->rawQuery($sQuery);
$fundingSourceList = $general->getFundingSources();
$formId = (int) $general->getGlobalConfig('vl_form');

?>
<link href="/assets/css/multi-select.css" rel="stylesheet" />
<style>
	.select2-selection__choice {
		color: #000000 !important;
	}

	#ms-unbatchedSamples {
		width: 100%;
	}

	.showFemaleSection {
		display: none;
	}

	#sortableRow {
		list-style-type: none;
		margin: 10px 0px 30px 0px;
		padding: 0;
		width: 100%;
		text-align: center;
	}

	#sortableRow li {
		color: #333 !important;
		font-size: 16px;
	}
</style>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><?php echo _translate("Edit Batch"); ?></h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _translate("Home"); ?></a></li>
			<li class="active">Batch</li>
		</ol>
	</section>

	<!-- Main content -->
	<section class="content">
		<div class="box box-default">
			<div class="box-header with-border">
				<div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span> <?php echo _translate("indicates required fields"); ?> &nbsp;</div>
			</div>
			&nbsp;<button class="btn btn-primary btn-sm pull-left" style="margin-right:5px;" onclick="hideAdvanceSearch('filter','advanceFilter');"><span>
					<?php echo _translate("Show Advanced Search Options"); ?>
				</span></button>
			<table aria-describedby="table" id="advanceFilter" class="table" aria-hidden="true" style="margin-top:20px;width: 100%; display:none;">
				<tr>
					<th style="width: 20%;" scope="col"><?php echo _translate("Facility"); ?></th>
					<td style="width: 30%;">
						<select style="width: 100%;" class="" id="facilityName" name="facilityName" title="<?php echo _translate('Please select facility name'); ?>" multiple="multiple">
							<?= $facilitiesDropdown; ?>
						</select>
					</td>
					<td><label for="fundingSource"><?= _translate("Samples Entered By"); ?></label></td>
					<td>
						<select class="form-control" name="userId" id="userId" title="Please choose source de financement" style="width:100%;">
							<?php echo $general->generateSelectOptions($userNameList, null, '--Select--'); ?>
						</select>
					</td>
				</tr>
				<tr>
					<th style="width: 20%;" scope="col">Date Sample Receieved at Lab</th>
					<td style="width: 30%;">
						<input type="text" id="sampleReceivedAtLab" name="sampleReceivedAtLab" class="form-control daterange" placeholder="<?php echo _translate('Select Received at Lab Date'); ?>" readonly style="width:100%;background:#fff;" />
					</td>
					<th style="width: 20%;" scope="col"><?php echo _translate("Sample Collection Date"); ?></th>
					<td style="width: 30%;">
						<input type="text" id="sampleCollectionDate" name="sampleCollectionDate" class="form-control daterange" placeholder="<?php echo _translate('Select Collection Date'); ?>" readonly style="width:100%;background:#fff;" />
					</td>
				</tr>
				<tr>
					<th scope="col"><?php echo _translate("Sample Type"); ?></th>
					<td>
						<select class="form-control" id="sampleType" name="sampleType" title="<?php echo _translate('Please select sample type'); ?>">
							<option value=""> <?php echo _translate("-- Select --"); ?> </option>
							<?php
							foreach ($sResult as $type) {
							?>
								<option value="<?php echo $type['sample_id']; ?>"><?php echo ($type['sample_name']); ?></option>
							<?php
							}
							?>
						</select>
					</td>
					<th style="width: 20%;" scope="col"><?php echo _translate("Positions"); ?></th>
					<td style="width: 30%;">
						<select id="positions-type" class="form-control" title="<?php echo _translate('Please select the postion'); ?>">
							<option value="numeric" <?php echo ($batchInfo[0]['position_type'] == "numeric") ? 'selected="selected"' : ''; ?>><?php echo _translate("Numeric"); ?></option>
							<option value="alpha-numeric" <?php echo ($batchInfo[0]['position_type'] == "alpha-numeric") ? 'selected="selected"' : ''; ?>><?php echo _translate("Alpha Numeric"); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<td><label for="fundingSource">Funding Partner</label></td>
					<td>
						<select class="form-control" name="fundingSource" id="fundingSource" title="Please choose source de financement" style="width:100%;">
							<option value=""> -- Select -- </option>
							<?php
							foreach ($fundingSourceList as $fundingSource) {
							?>
								<option value="<?php echo base64_encode((string) $fundingSource['funding_source_id']); ?>"><?= $fundingSource['funding_source_name']; ?></option>
							<?php } ?>
						</select>
					</td>

				</tr>
				<tr>
					<td><label for="sortBy"><?= _translate("Sort By"); ?></label></td>

					<td><select class="form-control" id="sortBy" name="sortBy" onchange="">
							<option <?= $sortBy == 'requestCreated' ? "selected='selected'" : '' ?> value="requestCreated"><?= _translate("Request Created"); ?></option>
							<option <?= $sortBy == 'lastModified' ? "selected='selected'" : '' ?> value="lastModified"><?= _translate("Last Modified"); ?></option>
							<option <?= $sortBy == 'sampleCode' ? "selected='selected'" : '' ?> value="sampleCode"><?= _translate("Sample ID"); ?></option>
							<option <?= $sortBy == 'labAssignedCode' ? "selected='selected'" : '' ?> value="labAssignedCode"><?= _translate("Lab Assigned Code"); ?></option>
						</select></td>
					<td><label for="sortType"><?= _translate("Sort Type"); ?></label></td>
					<td>
						<select class="form-control" id="sortType" onchange="">
							<option <?= $sortType == 'asc' ? "selected='selected'" : '' ?> value="asc">Ascending</option>
							<option <?= $sortType == 'desc' ? "selected='selected'" : '' ?> value="desc">Descending</option>
						</select>
					</td>
				</tr>
				<tr>
					<td colspan="4">&nbsp;<input type="button" onclick="getSampleCodeDetails();" value="<?php echo _translate('Filter Samples'); ?>" class="btn btn-success btn-sm">
						&nbsp;<button class="btn btn-danger btn-sm" onclick="document.location.href = document.location"><span><?php echo _translate("Reset Filters"); ?></span></button>
						&nbsp;<button class="btn btn-danger btn-sm" onclick="hideAdvanceSearch('advanceFilter','filter');"><span>
								<?php echo _translate("Hide Advanced Search Options"); ?>
							</span></button>
					</td>
				</tr>
			</table>
			<!-- /.box-header -->
			<div class="box-body">
				<!-- form start -->
				<form class="form-horizontal" method='post' name='editBatchForm' id='editBatchForm' autocomplete="off" action="save-batch-helper.php">
					<div class="box-body">
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="batchCode" class="col-lg-4 control-label"><?php echo _translate("Batch Code"); ?> <span class="mandatory">*</span></label>
									<div class="col-lg-7" style="margin-left:3%;">
										<input type="text" class="form-control isRequired" id="batchCode" name="batchCode" readonly="readonly" placeholder="<?php echo _translate('Batch Code'); ?>" title="<?php echo _translate('Please enter batch code'); ?>" value="<?php echo $batchInfo[0]['batch_code']; ?>" onblur="checkNameValidation('batch_details','batch_code',this,'<?php echo "batch_id##" . $id; ?>','<?php echo _translate("This batch code already exists.Try another code"); ?>',null)" />
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="machine" class="col-lg-4 control-label"><?php echo _translate("Testing Platform"); ?> <span class="mandatory">*</span></label>
									<div class="col-lg-7" style="margin-left:3%;">
										<select name="machine" id="machine" class="form-control isRequired" title="<?php echo _translate('Please choose machine'); ?>">
											<option value=""> <?php echo _translate("-- Select --"); ?> </option>
											<?php foreach ($testPlatformResult as $machine) { ?>
												<option value="<?= $machine['instrument_id'] ?>" <?= ($batchInfo[0]['machine'] == $machine['instrument_id']) ? 'selected' : '' ?> data-no-of-samples="<?= $machine['max_no_of_samples_in_a_batch'] ?>"><?= $machine['machine_name'] ?></option>
											<?php } ?>
										</select>
									</div>
								</div>
							</div>

						</div>
						<?php if ($formId == COUNTRY\CAMEROON) { ?>
							<div class="row">
								<div class="col-md-6">
									<div class="form-group">
										<label for="machine" class="col-lg-4 control-label"><?php echo _translate("Lab Assigned Batch Code"); ?></label>
										<div class="col-lg-7" style="margin-left:3%;">
											<input type="text" name="labAssignedBatchCode" id="labAssignedBatchCode" class="form-control" placeholder="<?php echo _translate('Enter Lab Assigned Batch Code'); ?>" value="<?php echo $batchInfo[0]['lab_assigned_batch_code'] ?>" />
										</div>
									</div>
								</div>
							</div>
						<?php } ?>
						<div class="row" id="sampleDetails">
							<div class="col-md-5">
								<select name="unbatchedSamples[]" id="search" class="form-control" size="8" multiple="multiple">

								</select>
								<div class="sampleCounterDiv"><?= _translate("Number of unselected samples"); ?> : <span id="unselectedCount"></span></div>
							</div>
							<div class="col-md-2">
								<button type="button" id="search_rightAll" class="btn btn-block"><em class="fa-solid fa-forward"></em></button>
								<button type="button" id="search_rightSelected" class="btn btn-block"><em class="fa-sharp fa-solid fa-chevron-right"></em></button>
								<button type="button" id="search_leftSelected" class="btn btn-block"><em class="fa-sharp fa-solid fa-chevron-left"></em></button>
								<button type="button" id="search_leftAll" class="btn btn-block"><em class="fa-solid fa-backward"></em></button>
							</div>

							<div class="col-md-5">
								<select name="to[]" id="search_to" class="form-control" size="8" multiple="multiple">
									<?php foreach ($result as $key => $sample) {
										$labCode = "";
										if ($sample['lab_assigned_code'] != "") {
											$labCode = ' - ' . $sample['lab_assigned_code'];
										}
										if (trim((string) $sample['sample_batch_id']) == $id) { ?>
											<option value="<?php echo $sample[$refPrimaryColumn]; ?>"><?php echo $sample['sample_code'] . " - " . $sample[$patientIdColumn] . " - " .  ($sample['facility_name']) . $labCode; ?></option>
									<?php }
									} ?>
								</select>
								<div class="sampleCounterDiv"><?= _translate("Number of selected samples"); ?> : <span id="selectedCount"></span></div>
							</div>
						</div>
						<div class="row" id="alertText"></div>
					</div>
					<!-- /.box-body -->
					<div class="box-footer">
						<input type="hidden" name="type" id="type" value="<?php echo $testType; ?>" />
						<input type="hidden" name="batchId" id="batchId" value="<?php echo $batchInfo[0]['batch_id']; ?>" />
						<input type="hidden" name="batchedSamples" id="batchedSamples" />
						<input type="hidden" name="positions" id="positions" value="<?php echo $batchInfo[0]['position_type']; ?>" />
						<a id="batchSubmit" class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;"><?php echo _translate("Submit"); ?></a>
						<a href="/batch/batches.php?type=<?php echo $testType; ?>" class="btn btn-default"> <?php echo _translate("Cancel"); ?></a>
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


<script type="text/javascript" src="/assets/js/jasny-bootstrap.js"></script>
<script src="/assets/js/moment.min.js"></script>
<script type="text/javascript" src="/assets/plugins/daterangepicker/daterangepicker.js"></script>
<script type="text/javascript">
	var startDate = "";
	var endDate = "";
	var resultSampleArray = [];
	var noOfSamples = $("#machine").find('option:selected').data('no-of-samples');

	function validateNow() {
		var selVal = [];
		$('#search_to option').each(function(i, selected) {
			selVal[i] = $(selected).val();
		});
		const sqids = new Sqids()
		$("#batchedSamples").val(sqids.encode(selVal));
		var selected = $("#machine").find('option:selected');
		noOfSamples = selected.data('no-of-samples');
		if (noOfSamples < selVal.length) {
			alert("<?= _translate("You have selected more than allowed number of samples", true); ?>");
			return false;
		}

		if (selVal == "") {
			alert("<?= _translate("Please select one or more samples", true); ?>");
			return false;
		}

		flag = deforayValidator.init({
			formId: 'editBatchForm'
		});
		if (flag) {
			$("#positions").val($('#positions-type').val());
			$.blockUI();
			document.getElementById('editBatchForm').submit();
		}
	}
	$(document).ready(function() {
		$("#facilityName").selectize({
			plugins: ["restore_on_backspace", "remove_button", "clear_button"],
		});
		setTimeout(function() {
			$("#search_rightSelected").trigger('click');
		}, 10);

		$("#userId").select2({
			placeholder: "<?= _translate('Select User', true); ?>"
		});

		$('#sampleCollectionDate').daterangepicker({
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
					'Last 30 Days': [moment().subtract(29, 'days'), moment()],
					'This Month': [moment().startOf('month'), moment().endOf('month')],
					'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
				}
			},
			function(start, end) {
				startDate = start.format('YYYY-MM-DD');
				endDate = end.format('YYYY-MM-DD');
			});
		$('#sampleCollectionDate').val("");
		//var unSelectedLength = $('.search > option').length - $(".search :selected").length;

		<?php
		$r = 1;
		foreach ($result as $sample) {
			if (isset($sample['batch_id']) && trim((string) $sample['batch_id']) == $id) {
				if (isset($sample['result']) && trim((string) $sample['result']) != '') {
					if ($r == 1) {
		?>
						$("#deselect-all-samplecode").remove();
					<?php } ?>
					resultSampleArray.push('<?php echo $sample['eid_id']; ?>');
		<?php $r++;
				}
			}
		}
		?>
		getSampleCodeDetails();
	});

	function checkNameValidation(tableName, fieldName, obj, fnct, alrt, callback) {
		var removeDots = obj.value.replace(/\./g, "");
		removeDots = removeDots.replace(/\,/g, "");
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
					duplicateName = false;
					document.getElementById(obj.id).value = "";
				}
			});
	}

	function getSampleCodeDetails() {


		$.blockUI();


		var facilityId = $("#facilityName").val();

		$.post("/batch/get-samples-batch.php", {
				sampleCollectionDate: $("#sampleCollectionDate").val(),
				sampleReceivedAtLab: $("#sampleReceivedAtLab").val(),
				type: '<?php echo $testType; ?>',
				batchId: $("#batchId").val(),
				genericTestType: '<?php echo $genericTestType; ?>',
				facilityId: facilityId,
				sName: $("#sampleType").val(),
				fundingSource: $("#fundingSource").val(),
				userId: $("#userId").val(),
				sortBy: $("#sortBy").val(),
				sortType: $("#sortType").val(),
			},
			function(data) {
				if (data != "") {
					if ($("#batchId").val() > 0) {
						$("#search").html(data);
						var count = $('#search option').length;
						$("#unselectedCount").html(count);
					} else {
						//$("#sampleDetails").html(data);
					}
				}
			});
		$.unblockUI();
	}


	function enableFemaleSection(obj) {
		if (obj.value == "female") {
			$(".showFemaleSection").show();
			$(".pregnant,.breastfeeding").prop("disabled", false);
		} else {
			$(".showFemaleSection").hide();
			$(".pregnant,.breastfeeding").prop("checked", false);
			$(".pregnant,.breastfeeding").attr("disabled", true);
		}
	}

	function hideAdvanceSearch(hideId, showId) {
		$("#" + hideId).hide();
		$("#" + showId).show();
	}

	$("#machine").change(function() {
		var self = this.value;
		if (self != '') {
			getSampleCodeDetails();
			var selected = $(this).find('option:selected');
			noOfSamples = selected.data('no-of-samples');
			$('#alertText').html("<?= _translate("Maximum number of samples allowed for the selected platform", true); ?> : " + noOfSamples);
		} else {
			//$('#alertText').html('');
		}
	});
</script>

<?php
require_once APPLICATION_PATH . '/footer.php';
