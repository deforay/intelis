<?php

use App\Models\Facilities;
use App\Models\General;



$title = "Edit Batch";


require_once(APPLICATION_PATH . '/header.php');

$general = new General();
$facilitiesDb = new Facilities();
$healthFacilites = $facilitiesDb->getHealthFacilities('vl');
//$formId = $general->getGlobalConfig('vl_form');

$facilitiesDropdown = $general->generateSelectOptions($healthFacilites, null, "-- Select --");


$id = base64_decode($_GET['id']);
//global config
// $configQuery = "SELECT `value` FROM global_config WHERE name ='vl_form'";
// $configResult = $db->query($configQuery);
// $showUrgency = ($configResult[0]['value'] == 1 || $configResult[0]['value'] == 2) ? true : false;
$batchQuery = "SELECT * from batch_details as b_d LEFT JOIN instruments as i_c ON i_c.config_id=b_d.machine where batch_id=$id";
$batchInfo = $db->rawQuery($batchQuery, array($id));
$bQuery = "SELECT vl.sample_code,vl.sample_batch_id,vl.vl_sample_id,vl.facility_id,vl.result,vl.result_status,f.facility_name,f.facility_code FROM form_vl as vl INNER JOIN facility_details as f ON vl.facility_id=f.facility_id WHERE  (vl.is_sample_rejected IS NULL OR vl.is_sample_rejected = '' OR vl.is_sample_rejected = 'no') AND (vl.reason_for_sample_rejection IS NULL OR vl.reason_for_sample_rejection ='' OR vl.reason_for_sample_rejection = 0) AND vl.sample_code!='' AND vl.sample_batch_id = $id ORDER BY vl.last_modified_datetime ASC";
//error_log($bQuery);die;
//echo '<pre>'; print_r($batchInfo); die;
$batchResultresult = $db->rawQuery($bQuery);
$query = "SELECT vl.sample_code,vl.sample_batch_id,vl.vl_sample_id,vl.facility_id,vl.result,vl.result_status,f.facility_name,f.facility_code FROM form_vl as vl INNER JOIN facility_details as f ON vl.facility_id=f.facility_id WHERE (vl.sample_batch_id IS NULL OR vl.sample_batch_id = '') AND (vl.is_sample_rejected IS NULL OR vl.is_sample_rejected = '' OR vl.is_sample_rejected = 'no') AND (vl.reason_for_sample_rejection IS NULL OR vl.reason_for_sample_rejection ='' OR vl.reason_for_sample_rejection = 0) AND (vl.result is NULL or vl.result = '') AND vl.sample_code!='' ORDER BY vl.last_modified_datetime ASC";
//error_log($query);die;
$result = $db->rawQuery($query);
$result = array_merge($batchResultresult, $result);

$sQuery = "SELECT * FROM r_vl_sample_type where status='active'";
$sResult = $db->rawQuery($sQuery);
//Get active machines
$testPlatformResult = $general->getTestingPlatforms('vl');
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
		<h1>Edit Batch</h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em> Home</a></li>
			<li class="active">Batch</li>
		</ol>
	</section>

	<!-- Main content -->
	<section class="content">

		<div class="box box-default">
			<div class="box-header with-border">
				<div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span> indicates required field &nbsp;</div>
			</div>
			<table class="table" aria-hidden="true" style="margin-left:1%;margin-top:20px;width: 80%;">

				<tr>
					<td>&nbsp;<strong>Sample Collection Date&nbsp;:</strong></td>
					<td>
						<input type="text" id="sampleCollectionDate" name="sampleCollectionDate" class="form-control" placeholder="Select Collection Date" readonly style="width:275px;background:#fff;" />
					</td>
					<td>&nbsp;<strong>Sample Type&nbsp;:</strong></td>
					<td>
						<select class="form-control" id="sampleType" name="sampleType" title="Please select sample type">
							<option value=""> -- Select -- </option>
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
					<td>&nbsp;<strong>Facility Name & Code&nbsp;:</strong></td>
					<td>
						<select style="width: 275px;" class="form-control" id="facilityName" name="facilityName" title="Please select facility name" multiple="multiple">
							<?= $facilitiesDropdown; ?>
						</select>
					</td>
					<td><strong>Gender&nbsp;:</strong></td>
					<td>
						<select name="gender" id="gender" class="form-control" title="Please choose gender" onchange="enableFemaleSection(this);">
							<option value=""> -- Select -- </option>
							<option value="male">Male</option>
							<option value="female">Female</option>
							<option value="not_recorded">Not Recorded</option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="col"><?php echo _("Positions"); ?></th>
					<td>
						<select id="positions-type" class="form-control" title="Please select the postion">
							<option value="numeric" <?php echo ($batchInfo[0]['position_type'] == "numeric") ? 'selected="selected"' : ''; ?>><?php echo _("Numeric"); ?></option>
							<option value="alpha-numeric" <?php echo ($batchInfo[0]['position_type'] == "alpha-numeric") ? 'selected="selected"' : ''; ?>><?php echo _("Alpha Numeric"); ?></option>
						</select>
					</td>
					<th scope="col"></th>
					<td></td>
				</tr>
				<tr class="showFemaleSection">
					<td><strong>Pregnant&nbsp;:</strong></td>
					<td>
						<input type="radio" name="pregnant" title="Please choose type" class="pregnant" id="prgYes" value="yes" disabled="disabled" />&nbsp;&nbsp;Yes
						<input type="radio" name="pregnant" title="Please choose type" class="pregnant" id="prgNo" value="no" disabled="disabled" />&nbsp;&nbsp;No
					</td>
					<td><strong>Is Patient Breastfeeding&nbsp;:</strong></td>
					<td>
						<input type="radio" name="breastfeeding" title="Please choose type" class="breastfeeding" id="breastFeedingYes" value="yes" disabled="disabled" />&nbsp;&nbsp;Yes
						<input type="radio" name="breastfeeding" title="Please choose type" class="breastfeeding" id="breastFeedingNo" value="no" disabled="disabled" />&nbsp;&nbsp;No
					</td>
				</tr>
				<tr>
					<td colspan="4">&nbsp;<input type="button" onclick="getSampleCodeDetails();" value="Search" class="btn btn-success btn-sm">
						&nbsp;<button class="btn btn-danger btn-sm" onclick="document.location.href = document.location"><span>Reset</span></button>
					</td>
				</tr>
			</table>
			<!-- /.box-header -->
			<div class="box-body">
				<!-- form start -->
				<form class="form-horizontal" method='post' name='editBatchForm' id='editBatchForm' autocomplete="off" action="editBatchCodeHelper.php">
					<div class="box-body">
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="batchCode" class="col-lg-4 control-label">Batch Code <span class="mandatory">*</span></label>
									<div class="col-lg-7" style="margin-left:3%;">
										<input type="text" class="form-control isRequired" id="batchCode" name="batchCode" placeholder="Batch Code" title="Please enter batch code" value="<?php echo $batchInfo[0]['batch_code']; ?>" onblur="checkNameValidation('batch_details','batch_code',this,'<?php echo "batch_id##" . $id; ?>','This batch code already exists.Try another code',null)" />
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="machine" class="col-lg-4 control-label">Testing Platform <span class="mandatory">*</span></label>
									<div class="col-lg-7" style="margin-left:3%;">
										<select name="machine" id="machine" class="form-control isRequired" title="Please choose machine">
											<option value=""> -- Select -- </option>
											<?php
											foreach ($testPlatformResult as $machine) {
											?>
												<option value="<?php echo $machine['config_id']; ?>" <?php if ($batchInfo[0]['machine'] == $machine['config_id']) echo "selected='selected'"; ?> data-no-of-samples="<?php echo $machine['max_no_of_samples_in_a_batch']; ?>" <?php echo ($batchInfo[0]['machine'] == $machine['config_id']) ? 'selected="selected"' : ''; ?>><?php echo ($machine['machine_name']); ?></option>
											<?php } ?>
										</select>
									</div>
								</div>
							</div>
							<div class="col-md-6"><a href="editBatchControlsPosition.php?id=<?php echo base64_encode($batchInfo[0]['batch_id']); ?>" class="btn btn-default btn-xs" style="margin-right: 2px;margin-top:6px;" title="Edit Position"><em class="fa-solid fa-arrow-down-1-9"></em> Edit Position</a></div>
						</div>
						<div class="row" id="sampleDetails">
							<!--<div class="col-md-8">
								<div class="form-group">
									<div class="col-md-12">
										<div class="col-md-12">
											<div style="width:60%;margin:0 auto;clear:both;">
												<a href='#' id='select-all-samplecode' style="float:left" class="btn btn-info btn-xs">Select All&nbsp;&nbsp;<em class="fa-solid fa-chevron-right"></em></a> <a href='#' id='deselect-all-samplecode' style="float:right" class="btn btn-danger btn-xs"><em class="fa-solid fa-chevron-left"></em>&nbsp;Deselect All</a>
											</div><br /><br />
											<select id='sampleCode' name="sampleCode[]" multiple='multiple' class="search">
												<?php
												foreach ($result as $key => $sample) {
												?>
													<option value="<?php echo $sample['vl_sample_id']; ?>" <?php echo (trim($sample['sample_batch_id']) == $id) ? 'selected="selected"' : ''; ?>><?php echo $sample['sample_code'] . " - " . ($sample['facility_name']); ?></option>
												<?php
												}
												?>
											</select>
										</div>
									</div>
								</div>
							</div>-->
							<h4> <?php echo _("Sample Code"); ?></h4>
							<div class="col-md-5">
								<!-- <div class="col-lg-5"> -->
								<select name="sampleCode[]" id="search" class="form-control" size="8" multiple="multiple">
									<?php
									foreach ($result as $key => $sample) {
									?>
										<option value="<?php echo $sample['vl_sample_id']; ?>" <?php echo (trim($sample['sample_batch_id']) == $id) ? 'selected="selected"' : ''; ?>><?php echo $sample['sample_code'] . " - " . ($sample['facility_name']); ?></option>
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
						<a id="batchSubmit" class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;">Submit</a>
						<a href="batchcode.php" class="btn btn-default"> Cancel</a>
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
		setTimeout(function() {
			$("#search_rightSelected").trigger('click');
		}, 10);
		$("#facilityName").select2({
			placeholder: "Select Facilities"
		});
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
		/*noOfSamples = 0;
		<?php
		if (isset($batchInfo[0]['max_no_of_samples_in_a_batch']) && trim($batchInfo[0]['max_no_of_samples_in_a_batch']) > 0) {
		?>
			noOfSamples = <?php echo intval($batchInfo[0]['max_no_of_samples_in_a_batch']); ?>;
		<?php }
		?>
		$("#facilityName").select2({
			placeholder: "Select Facilities"
		});
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
		$('#sampleCollectionDate').val("");
		var unSelectedLength = $('.search > option').length - $(".search :selected").length;
		/*$('.search').multiSelect({
			selectableHeader: "<input type='text' class='search-input form-control' autocomplete='off' placeholder='Enter Sample Code'>",
			selectionHeader: "<input type='text' class='search-input form-control' autocomplete='off' placeholder='Enter Sample Code'>",
			selectableFooter: "<div style='background-color: #367FA9;color: white;padding:5px;text-align: center;' class='custom-header' id='unselectableCount'>Available samples(" + unSelectedLength + ")</div>",
			selectionFooter: "<div style='background-color: #367FA9;color: white;padding:5px;text-align: center;' class='custom-header' id='selectableCount'>Selected samples(" + $(".search :selected").length + ")</div>",
			afterInit: function(ms) {
				var that = this,
					$selectableSearch = that.$selectableUl.prev(),
					$selectionSearch = that.$selectionUl.prev(),
					selectableSearchString = '#' + that.$container.attr('id') + ' .ms-elem-selectable:not(.ms-selected)',
					selectionSearchString = '#' + that.$container.attr('id') + ' .ms-elem-selection.ms-selected';

				that.qs1 = $selectableSearch.quicksearch(selectableSearchString)
					.on('keydown', function(e) {
						if (e.which === 40) {
							that.$selectableUl.focus();
							return false;
						}
					});

				that.qs2 = $selectionSearch.quicksearch(selectionSearchString)
					.on('keydown', function(e) {
						if (e.which == 40) {
							that.$selectionUl.focus();
							return false;
						}
					});
			},
			afterSelect: function() {
				//button disabled/enabled
				if (this.qs2.cache().matchedResultsCount == noOfSamples) {
					alert("You have selected maximum number of samples - " + this.qs2.cache().matchedResultsCount);
					$("#batchSubmit").attr("disabled", false);
					$("#batchSubmit").css("pointer-events", "auto");
				} else if (this.qs2.cache().matchedResultsCount <= noOfSamples) {
					$("#batchSubmit").attr("disabled", false);
					$("#batchSubmit").css("pointer-events", "auto");
				} else if (this.qs2.cache().matchedResultsCount > noOfSamples) {
					alert("You have already selected Maximum no. of sample " + noOfSamples);
					$("#batchSubmit").attr("disabled", true);
					$("#batchSubmit").css("pointer-events", "none");
				}
				this.qs1.cache();
				this.qs2.cache();
				$("#unselectableCount").html("Available samples(" + this.qs1.cache().matchedResultsCount + ")");
				$("#selectableCount").html("Selected samples(" + this.qs2.cache().matchedResultsCount + ")");
			},
			afterDeselect: function() {
				//button disabled/enabled
				if (this.qs2.cache().matchedResultsCount == 0) {
					$("#batchSubmit").attr("disabled", true);
					$("#batchSubmit").css("pointer-events", "none");
				} else if (this.qs2.cache().matchedResultsCount == noOfSamples) {
					alert("You have selected maximum number of samples - " + this.qs2.cache().matchedResultsCount);
					$("#batchSubmit").attr("disabled", false);
					$("#batchSubmit").css("pointer-events", "auto");
				} else if (this.qs2.cache().matchedResultsCount <= noOfSamples) {
					$("#batchSubmit").attr("disabled", false);
					$("#batchSubmit").css("pointer-events", "auto");
				} else if (this.qs2.cache().matchedResultsCount > noOfSamples) {
					$("#batchSubmit").attr("disabled", true);
					$("#batchSubmit").css("pointer-events", "none");
				}
				this.qs1.cache();
				this.qs2.cache();
				$("#unselectableCount").html("Available samples(" + this.qs1.cache().matchedResultsCount + ")");
				$("#selectableCount").html("Selected samples(" + this.qs2.cache().matchedResultsCount + ")");
			}
		});
		$('#select-all-samplecode').click(function() {
			$('#sampleCode').multiSelect('select_all');
			return false;
		});
		$('#deselect-all-samplecode').click(function() {
			$('#sampleCode').multiSelect('deselect_all');
			$("#batchSubmit").attr("disabled", true);
			$("#batchSubmit").css("pointer-events", "none");
			return false;
		});

		if (noOfSamples == 0) {
			$("#batchSubmit").attr("disabled", true);
			$("#batchSubmit").css("pointer-events", "none");
		} else if ($("#sampleCode :selected").length > noOfSamples) {
			$("#batchSubmit").attr("disabled", true);
			$("#batchSubmit").css("pointer-events", "none");
		}

		<?php
		$r = 1;
		foreach ($result as $sample) {
			if (isset($sample['batch_id']) && trim($sample['batch_id']) == $id) {
				if (isset($sample['result']) && trim($sample['result']) != '') {
					if ($r == 1) {
		?>
						$("#deselect-all-samplecode").remove();
					<?php } ?>
					resultSampleArray.push('<?php echo $sample['vl_sample_id']; ?>');
		<?php $r++;
				}
			}
		}
		?>
		$("#resultSample").val(resultSampleArray);
		if ($("#machine option:selected").text() != ' -- Select -- ') {
			$('#alertText').html('You have picked ' + $("#machine option:selected").text() + ' and it has limit of maximum ' + noOfSamples + ' samples to make it a batch');
		}*/
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

		/*var urgent = $("input:radio[name=urgency]");
		if ((urgent[0].checked == false && urgent[1].checked == false) || urgent == 'undefined') {
			urgent = '';
		} else {
			urgent = $('input[name=urgency]:checked').val();
		}*/

		var fName = $("#facilityName").val();
		var sName = $("#sampleType").val();
		var gender = $("#gender").val();
		var prg = $("input:radio[name=pregnant]");
		if ((prg[0].checked == false && prg[1].checked == false) || prg == 'undefined') {
			pregnant = '';
		} else {
			pregnant = $('input[name=pregnant]:checked').val();
		}
		var breastfeeding = $("input:radio[name=breastfeeding]");
		if ((breastfeeding[0].checked == false && breastfeeding[1].checked == false) || breastfeeding == 'undefined') {
			breastfeeding = '';
		} else {
			breastfeeding = $('input[name=breastfeeding]:checked').val();
		}
		$.post("/vl/batch/getSampleCodeDetails.php", {
				sampleCollectionDate: $("#sampleCollectionDate").val(),
				fName: fName,
				sName: sName,
				gender: gender,
				pregnant: pregnant,
				breastfeeding: breastfeeding
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
</script>

<?php
require_once(APPLICATION_PATH . '/footer.php');
