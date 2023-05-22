<?php

use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;



$title = "Edit Batch";


require_once APPLICATION_PATH . '/header.php';


/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);
$healthFacilites = $facilitiesService->getHealthFacilities('covid19');
//$formId = $general->getGlobalConfig('vl_form');

$facilitiesDropdown = $general->generateSelectOptions($healthFacilites, null, "-- Select --");

// Sanitize values before using them below
$_GET = array_map('htmlspecialchars', $_GET);
$id = (isset($_GET['id'])) ? base64_decode($_GET['id']) : null;

//global config

$batchQuery = "SELECT * from batch_details as b_d LEFT JOIN instruments as i_c ON i_c.config_id=b_d.machine where batch_id=?";
$batchInfo = $db->rawQuery($batchQuery, array($id));
$bQuery = "SELECT vl.sample_code,vl.sample_batch_id,vl.covid19_id,vl.facility_id,vl.result,vl.result_status,f.facility_name,f.facility_code FROM form_covid19 as vl INNER JOIN facility_details as f ON vl.facility_id=f.facility_id WHERE  (vl.is_sample_rejected IS NULL OR vl.is_sample_rejected = '' OR vl.is_sample_rejected = 'no') AND (vl.reason_for_sample_rejection IS NULL OR vl.reason_for_sample_rejection ='' OR vl.reason_for_sample_rejection = 0) AND vl.sample_code!='' AND vl.sample_batch_id = ? ORDER BY vl.last_modified_datetime ASC";
//error_log($bQuery);die;
$batchResultresult = $db->rawQuery($bQuery, array($id));

$query = "SELECT vl.sample_code,vl.sample_batch_id,vl.covid19_id,vl.facility_id,vl.result,vl.result_status,f.facility_name,f.facility_code FROM form_covid19 as vl INNER JOIN facility_details as f ON vl.facility_id=f.facility_id WHERE (vl.sample_batch_id IS NULL OR vl.sample_batch_id = '') AND (vl.is_sample_rejected IS NULL OR vl.is_sample_rejected = '' OR vl.is_sample_rejected = 'no') AND (vl.reason_for_sample_rejection IS NULL OR vl.reason_for_sample_rejection ='' OR vl.reason_for_sample_rejection = 0) AND (vl.result is NULL or vl.result = '') AND vl.sample_code!='' ORDER BY vl.last_modified_datetime ASC";
//error_log($query);die;
$result = $db->rawQuery($query, array($arr['vl_form']));
$result = array_merge($batchResultresult, $result);

//Get active machines

$testPlatformResult = $general->getTestingPlatforms('covid19');
// $machinesLabelOrder = [];
?>
<link href="/assets/css/multi-select.css" rel="stylesheet" />
<style>
	.select2-selection__choice {
		color: #000000 !important;
	}

	#ms-sampleCode {
		width: 110%;
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
		<h1><?php echo _("Edit Batch"); ?></h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _("Home"); ?></a></li>
			<li class="active">Batch</li>
		</ol>
	</section>

	<!-- Main content -->
	<section class="content">
		<div class="box box-default">
			<div class="box-header with-border">
				<div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span> <?php echo _("indicates required field"); ?> &nbsp;</div>
			</div>
			<table aria-describedby="table" class="table" aria-hidden="true" style="margin-left:1%;margin-top:20px;width: 100%;">
				<tr>

					<th scope="col"><?php echo _("Facility"); ?></th>
					<td>
						<select style="width: 275px;" class="form-control" id="facilityName" name="facilityName" title="<?php echo _('Please select facility name'); ?>" multiple="multiple">
							<?= $facilitiesDropdown; ?>
						</select>
					</td>
					<th scope="col"></th>
					<td></td>
				</tr>
				<tr>
					<th scope="col"><?php echo _("Sample Collection Date"); ?></th>
					<td>
						<input type="text" id="sampleCollectionDate" name="sampleCollectionDate" class="form-control daterange" placeholder="<?php echo _('Select Collection Date'); ?>" readonly style="width:275px;background:#fff;" />
					</td>
					<th scope="col">Date Sample Receieved at Lab</th>
					<td>
						<input type="text" id="sampleReceivedAtLab" name="sampleReceivedAtLab" class="form-control daterange" placeholder="<?php echo _('Select Received at Lab Date'); ?>" readonly style="width:275px;background:#fff;" />
					</td>
				</tr>
				<tr>
					<th scope="col"><?php echo _("Positions"); ?></th>
					<td>
						<select id="positions-type" class="form-control" title="<?php echo _('Please select the postion'); ?>">
							<option value="numeric" <?php echo ($batchInfo[0]['position_type'] == "numeric") ? 'selected="selected"' : ''; ?>><?php echo _("Numeric"); ?></option>
							<option value="alpha-numeric" <?php echo ($batchInfo[0]['position_type'] == "alpha-numeric") ? 'selected="selected"' : ''; ?>><?php echo _("Alpha Numeric"); ?></option>
						</select>
					</td>
					<th scope="col"></th>
					<td></td>
				</tr>
				<tr>
					<td colspan="4">&nbsp;<input type="button" onclick="getSampleCodeDetails();" value="<?php echo _('Filter Samples'); ?>" class="btn btn-success btn-sm">
						&nbsp;<button class="btn btn-danger btn-sm" onclick="document.location.href = document.location"><span><?php echo _("Reset Filters"); ?></span></button>
					</td>
				</tr>
			</table>
			<!-- /.box-header -->
			<div class="box-body">
				<!-- form start -->
				<form class="form-horizontal" method='post' name='editBatchForm' id='editBatchForm' autocomplete="off" action="covid-19-edit-batch-helper.php">
					<div class="box-body">
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="batchCode" class="col-lg-4 control-label"><?php echo _("Batch Code"); ?> <span class="mandatory">*</span></label>
									<div class="col-lg-7" style="margin-left:3%;">
										<input type="text" class="form-control isRequired" id="batchCode" name="batchCode" placeholder="<?php echo _('Batch Code'); ?>" title="<?php echo _('Please enter batch code'); ?>" value="<?php echo $batchInfo[0]['batch_code']; ?>" onblur="checkNameValidation('batch_details','batch_code',this,'<?php echo "batch_id##" . $id; ?>','<?php echo _("This batch code already exists.Try another code"); ?>',null)" />
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="machine" class="col-lg-4 control-label"><?php echo _("Testing Platform"); ?> <span class="mandatory">*</span></label>
									<div class="col-lg-7" style="margin-left:3%;">
										<select name="machine" id="machine" class="form-control isRequired" title="<?php echo _('Please choose machine'); ?>">
											<option value=""> <?php echo _("-- Select --"); ?> </option>
											<?php
											foreach ($testPlatformResult as $machine) {
											?>
												<option value="<?php echo $machine['config_id']; ?>" <?php if ($batchInfo[0]['machine'] == $machine['config_id']) echo "selected='selected'"; ?> data-no-of-samples="<?php echo $machine['max_no_of_samples_in_a_batch']; ?>" <?php echo ($batchInfo[0]['machine'] == $machine['config_id']) ? 'selected="selected"' : ''; ?>><?php echo ($machine['machine_name']); ?></option>
											<?php } ?>
										</select>
									</div>
								</div>
							</div>
							<div class="col-md-6"><a href="covid-19-edit-batch-position.php?id=<?php echo base64_encode($batchInfo[0]['batch_id']); ?>" class="btn btn-default btn-xs" style="margin-right: 2px;margin-top:6px;" title="<?php echo _('Edit Position'); ?>"><em class="fa-solid fa-arrow-down-1-9"></em> <?php echo _("Edit Position"); ?></a></div>
						</div>
						<div class="row" id="sampleDetails">

							<div class="col-md-5">
								<!-- <div class="col-lg-5"> -->
								<select name="sampleCode[]" id="search" class="form-control" size="8" multiple="multiple">
									<?php
									foreach ($result as $key => $sample) {
									?>
										<option value="<?php echo $sample['covid19_id']; ?>" <?php echo (trim($sample['sample_batch_id']) == $id) ? 'selected="selected"' : ''; ?>><?php echo $sample['sample_code'] . " - " . ($sample['facility_name']); ?></option>
									<?php
									}
									?>
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

								</select>
							</div>



						</div>
						<div class="row" id="alertText" style="font-size:18px;"></div>
					</div>
					<!-- /.box-body -->
					<div class="box-footer">
						<input type="hidden" name="batchId" id="batchId" value="<?php echo $batchInfo[0]['batch_id']; ?>" />
						<input type="hidden" name="selectedSample" id="selectedSample" />
						<input type="hidden" name="positions" id="positions" value="<?php echo $batchInfo[0]['position_type']; ?>" />
						<a id="batchSubmit" class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;"><?php echo _("Submit"); ?></a>
						<a href="covid-19-batches.php" class="btn btn-default"> <?php echo _("Cancel"); ?></a>
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
			alert("You have selected maximum number of samples");
			return false;
		}

		if (selVal == "") {
			alert("Please select sample code");
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
				left: '<input type="text" name="q" class="form-control" placeholder="<?php echo _("Search"); ?>..." />',
				right: '<input type="text" name="q" class="form-control" placeholder="<?php echo _("Search"); ?>..." />',
			},
			fireSearch: function(value) {
				return value.length > 3;
			}
		});
		$("#facilityName").select2({
			placeholder: "<?php echo _('Select Facilities'); ?>"
		});
		setTimeout(function() {
			$("#search_rightSelected").trigger('click');
		}, 10);
		$('#sampleCollectionDate').daterangepicker({
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

		$.post("/covid-19/batch/get-covid-19-samples-batch.php", {
				sampleCollectionDate: $("#sampleCollectionDate").val(),
				sampleReceivedAtLab: $("#sampleReceivedAtLab").val(),
				fName: fName
			},
			function(data) {
				if (data != "") {
					$("#sampleDetails").html(data);
					//$("#batchSubmit").attr("disabled", true);
					//$("#batchSubmit").css("pointer-events", "none");
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
	$(document.body).on("change", "#search, #search_to", function() {
		countOff().then(function(count) {
			// use the result here
			if (count > 0) {
				$('#alertText').html('<?php echo _("You have picked"); ?> ' + $("#machine option:selected").text() + ' <?php echo _("testing platform and it has limit of maximum"); ?> ' + count + '/' + noOfSamples + ' <?php echo _("samples per batch"); ?>');
			} else {
				$('#alertText').html('<?php echo _("You have picked"); ?> ' + $("#machine option:selected").text() + ' <?php echo _("testing platform and it has limit of maximum"); ?> ' + noOfSamples + ' <?php echo _("samples per batch"); ?>');
			}
		});
	});

	function countOff() {
		return new Promise(function(resolve, reject) {
			setTimeout(function() {
				resolve();
			}, 300);
		}).then(function() {
			return $("#search_to option").length;
		});
	}
</script>

<?php
require_once APPLICATION_PATH . '/footer.php';
