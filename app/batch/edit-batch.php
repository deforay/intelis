<?php

use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;

$title = _translate($title . " | Edit Batch");

require_once APPLICATION_PATH . '/header.php';

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = $GLOBALS['request'];
$_GET = $request->getQueryParams();

$title = "Viral Load";
$refTable = "form_vl";
$refPrimaryColumn = "vl_sample_id";
if (isset($_GET['type']) && $_GET['type'] == 'vl') {
	$title = "Viral Load";
	$refTable = "form_vl";
	$refPrimaryColumn = "vl_sample_id";
} elseif (isset($_GET['type']) && $_GET['type'] == 'eid') {
	$title = "Early Infant Diagnosis";
	$refTable = "form_eid";
	$refPrimaryColumn = "eid_id";
} elseif (isset($_GET['type']) && $_GET['type'] == 'covid19') {
	$title = "Covid-19";
	$refTable = "form_covid19";
	$refPrimaryColumn = "covid19_id";
} elseif (isset($_GET['type']) && $_GET['type'] == 'hepatitis') {
	$title = "Hepatitis";
	$refTable = "form_hepatitis";
	$refPrimaryColumn = "hepatitis_id";
} elseif (isset($_GET['type']) && $_GET['type'] == 'tb') {
	$title = "TB";
	$refTable = "form_tb";
	$refPrimaryColumn = "tb_id";
} elseif (isset($_GET['type']) && $_GET['type'] == 'generic-tests') {
	$title = "Other Lab Tests";
	$refTable = "form_generic";
	$refPrimaryColumn = "sample_id";
}



/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);
$healthFacilites = $facilitiesService->getHealthFacilities($_GET['type']);
$facilitiesDropdown = $general->generateSelectOptions($healthFacilites, null, "-- Select --");

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = $GLOBALS['request'];
$_GET = $request->getQueryParams();
$id = (isset($_GET['id'])) ? base64_decode($_GET['id']) : null;
$testType = (isset($_GET['testType'])) ? base64_decode($_GET['testType']) : null;

$batchQuery = "SELECT * from batch_details as b_d
                    LEFT JOIN instruments as i_c ON i_c.config_id=b_d.machine
                    WHERE batch_id=?";
$batchInfo = $db->rawQuery($batchQuery, [$id]);
$bQuery = "(SELECT vl.sample_code,vl.sample_batch_id,
                    vl.$refPrimaryColumn,vl.facility_id,
                    vl.result,vl.result_status,
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

if (isset($_GET['type']) && $_GET['type'] == 'generic-tests') {
	$bQuery .= " AND vl.test_type = ?";
}

$bQuery .= ") UNION

                    (SELECT vl.sample_code,vl.sample_batch_id,
                        vl.$refPrimaryColumn,vl.facility_id,
                        vl.result,vl.result_status,
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
                        AND (vl.result is NULL or vl.result = '')
                        AND vl.sample_code!=''
                        ORDER BY vl.last_modified_datetime ASC)";
$result = $db->rawQuery($bQuery, [$id, $testType]);
$testPlatformResult = $general->getTestingPlatforms($_GET['type']);

?>
<link href="/assets/css/multi-select.css" rel="stylesheet" />
<style>
	.select2-selection__choice {
		color: #000000 !important;
	}

	#ms-sampleCode {
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

	#alertText {
		text-shadow: 1px 1px #eee;
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
				<div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span> <?php echo _translate("indicates required field"); ?> &nbsp;</div>
			</div>
			<table aria-describedby="table" class="table" aria-hidden="true" style="margin-top:20px;width: 100%;">
				<tr>
					<th style="width: 20%;" scope="col"><?php echo _translate("Facility"); ?></th>
					<td style="width: 30%;">
						<select style="width: 100%;" class="form-control" id="facilityName" name="facilityName" title="<?php echo _translate('Please select facility name'); ?>" multiple="multiple">
							<?= $facilitiesDropdown; ?>
						</select>
					</td>
					<th style="width: 20%;" scope="col"><?php echo _translate("Sample Collection Date"); ?></th>
					<td style="width: 30%;">
						<input type="text" id="sampleCollectionDate" name="sampleCollectionDate" class="form-control daterange" placeholder="<?php echo _translate('Select Collection Date'); ?>" readonly style="width:100%;background:#fff;" />
					</td>
				</tr>
				<tr>
					<th style="width: 20%;" scope="col">Date Sample Receieved at Lab</th>
					<td style="width: 30%;">
						<input type="text" id="sampleReceivedAtLab" name="sampleReceivedAtLab" class="form-control daterange" placeholder="<?php echo _translate('Select Received at Lab Date'); ?>" readonly style="width:100%;background:#fff;" />
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
					<td colspan="4">&nbsp;<input type="button" onclick="getSampleCodeDetails();" value="<?php echo _translate('Filter Samples'); ?>" class="btn btn-success btn-sm">
						&nbsp;<button class="btn btn-danger btn-sm" onclick="document.location.href = document.location"><span><?php echo _translate("Reset Filters"); ?></span></button>
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
										<input type="text" class="form-control isRequired" id="batchCode" name="batchCode" placeholder="<?php echo _translate('Batch Code'); ?>" title="<?php echo _translate('Please enter batch code'); ?>" value="<?php echo $batchInfo[0]['batch_code']; ?>" onblur="checkNameValidation('batch_details','batch_code',this,'<?php echo "batch_id##" . $id; ?>','<?php echo _translate("This batch code already exists.Try another code"); ?>',null)" />
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
												<option value="<?= $machine['config_id'] ?>" <?= ($batchInfo[0]['machine'] == $machine['config_id']) ? 'selected' : '' ?> data-no-of-samples="<?= $machine['max_no_of_samples_in_a_batch'] ?>"><?= $machine['machine_name'] ?></option>
											<?php } ?>
										</select>
									</div>
								</div>
							</div>
							<div class="col-md-6"></div>
						</div>
						<div class="row" id="sampleDetails">
							<div class="col-md-5">
								<select name="sampleCode[]" id="search" class="form-control" size="8" multiple="multiple">

								</select>
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
										if (trim($sample['sample_batch_id']) == $id) { ?>
											<option value="<?php echo $sample[$refPrimaryColumn]; ?>"><?php echo $sample['sample_code'] . " - " . ($sample['facility_name']); ?></option>
									<?php }
									} ?>
								</select>
							</div>
						</div>
						<div class="row" id="alertText" style="font-size:18px;"></div>
					</div>
					<!-- /.box-body -->
					<div class="box-footer">
						<input type="hidden" name="type" id="type" value="<?php echo $_GET['type']; ?>" />
						<input type="hidden" name="batchId" id="batchId" value="<?php echo $batchInfo[0]['batch_id']; ?>" />
						<input type="hidden" name="selectedSample" id="selectedSample" />
						<input type="hidden" name="positions" id="positions" value="<?php echo $batchInfo[0]['position_type']; ?>" />
						<a id="batchSubmit" class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;"><?php echo _translate("Submit"); ?></a>
						<a href="batches.php?type=<?php echo $_GET['type']; ?>" class="btn btn-default"> <?php echo _translate("Cancel"); ?></a>
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

<script type="text/javascript" src="/assets/js/multiselect.min.js"></script>
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
		$("#selectedSample").val(selVal);
		var selected = $("#machine").find('option:selected');
		noOfSamples = selected.data('no-of-samples');
		if (noOfSamples < selVal.length) {
			alert("<?= _translate("You have selected more than allowed number of samples"); ?>");
			return false;
		}

		if (selVal == "") {
			alert("<?= _translate("Please select one or more samples"); ?>");
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
	//$("#auditRndNo").multiselect({height: 100,minWidth: 150});
	$(document).ready(function() {
		$('#search').multiselect({
			search: {
				left: '<input type="text" name="q" class="form-control" placeholder="<?php echo _translate("Search"); ?>..." />',
				right: '<input type="text" name="q" class="form-control" placeholder="<?php echo _translate("Search"); ?>..." />',
			},
			fireSearch: function(value) {
				return value.length > 2;
			},
			afterMoveToRight: function($left, $right, $options) {
				const count = $right.find('option').length;
				if (count > 0) {
					$('#alertText').html('<?php echo _translate("You have picked"); ?> ' + $("#machine option:selected").text() + ' <?php echo _translate("testing platform and it has limit of maximum"); ?> ' + count + '/' + noOfSamples + ' <?php echo _translate("samples per batch"); ?>');
				} else {
					$('#alertText').html('<?php echo _translate("You have picked"); ?> ' + $("#machine option:selected").text() + ' <?php echo _translate("testing platform and it has limit of maximum"); ?> ' + noOfSamples + ' <?php echo _translate("samples per batch"); ?>');
				}
			},
			afterMoveToLeft: function($left, $right, $options) {
				const count = $right.find('option').length;
				if (count > 0) {
					$('#alertText').html('<?php echo _translate("You have picked"); ?> ' + $("#machine option:selected").text() + ' <?php echo _translate("testing platform and it has limit of maximum"); ?> ' + count + '/' + noOfSamples + ' <?php echo _translate("samples per batch"); ?>');
				} else {
					$('#alertText').html('<?php echo _translate("You have picked"); ?> ' + $("#machine option:selected").text() + ' <?php echo _translate("testing platform and it has limit of maximum"); ?> ' + noOfSamples + ' <?php echo _translate("samples per batch"); ?>');
				}
			}
		});
		$("#facilityName").select2({
			placeholder: "<?php echo _translate('Select Facilities'); ?>"
		});
		setTimeout(function() {
			$("#search_rightSelected").trigger('click');
		}, 10);
		$('#sampleCollectionDate').daterangepicker({
				locale: {
					cancelLabel: "<?= _translate("Clear"); ?>",
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
		var unSelectedLength = $('.search > option').length - $(".search :selected").length;

		<?php
		$r = 1;
		foreach ($result as $sample) {
			if (isset($sample['batch_id']) && trim($sample['batch_id']) == $id) {
				if (isset($sample['result']) && trim($sample['result']) != '') {
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
					duplicateName = false;
					document.getElementById(obj.id).value = "";
				}
			});
	}

	function getSampleCodeDetails() {
		$.blockUI();
		var fName = $("#facilityName").val();

		$.post("get-samples-batch.php", {
				sampleCollectionDate: $("#sampleCollectionDate").val(),
				sampleReceivedAtLab: $("#sampleReceivedAtLab").val(),
				type: '<?php echo $_GET['type']; ?>',
				batchId: $("#batchId").val(),
				testType: '<?php echo $testType; ?>',
				fName: fName
			},
			function(data) {
				if (data != "") {
					if ($("#batchId").val() > 0) {
						$("#search").html(data);
					} else {
						// $("#sampleDetails").html(data);
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

	$("#machine").change(function() {
		var self = this.value;
		if (self != '') {
			getSampleCodeDetails();
			var selected = $(this).find('option:selected');
			noOfSamples = selected.data('no-of-samples');
			$('#alertText').html('You have picked ' + $("#machine option:selected").text() + ' and it has limit of maximum ' + noOfSamples + ' samples to make it a batch');
		} else {
			$('.ms-list').html('');
			$('#alertText').html('');
		}
	});
</script>

<?php
require_once APPLICATION_PATH . '/footer.php';
