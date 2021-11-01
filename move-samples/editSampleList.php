<?php
ob_start();
$title = "Edit Sample List";
#require_once('../startup.php');
include_once(APPLICATION_PATH . '/header.php');

$general = new \Vlsm\Models\General($db);

$id = base64_decode($_GET['id']);

//global config
$configQuery = "SELECT `value` FROM global_config WHERE name ='vl_form'";
$configResult = $db->rawQueryOne($configQuery);
$country = $configResult['value'];

$sampleQuery = "SELECT * from move_samples as ms where ms.move_sample_id = $id";
$sampleInfo = $db->rawQueryOne($sampleQuery);

$testTableName = "";
$primaryKey = "";
if ($sampleInfo['test_type'] == "vl") {
	$testTableName = "vl_request_form";
	$primaryKey = "vl_sample_id";
} else if ($sampleInfo['test_type'] == "eid") {
	$testTableName = "eid_form";
	$primaryKey = "eid_id";
} else if ($sampleInfo['test_type'] == "covid19") {
	$testTableName = "form_covid19";
	$primaryKey = "covid19_id";
} else if ($sampleInfo['test_type'] == "hepatitis") {
	$testTableName = "form_hepatitis";
	$primaryKey = "hepatitis_id";
}

$selectedLab = array();
$sampleListQuery = "SELECT msm.* from move_samples_map as msm where msm.move_sample_id=$id";
$sampleListDataInfo = $db->query($sampleListQuery);
if (count($sampleListDataInfo) > 0) {
	foreach ($sampleListDataInfo as $info) {
		$selectedLab[] = $info['test_type_sample_id'];
	}
}
$sampleListLabQuery = "SELECT vl.remote_sample_code,vl.$primaryKey, vl.data_sync from $testTableName as vl  where (vl.result is NULL or vl.result = '') AND vlsm_country_id = $country AND (vl.remote_sample_code IS NOT NULL OR vl.remote_sample_code NOT LIKE '') AND (vl.lab_id='" . $sampleInfo['moved_from_lab_id'] . "' OR vl.lab_id='" . $sampleInfo['moved_to_lab_id'] . "')";
$sampleListLabDataInfo = $db->query($sampleListLabQuery);

//get lab facility details
$condition = "facility_type='2' AND status='active'";
$lResult = $general->fetchDataFromTable('facility_details', $condition);
//get facility data
$condition = "status = 'active'";
$fResult = $general->fetchDataFromTable('facility_details', $condition);
//Implementing partner list
$condition = "i_partner_status = 'active'";
$implementingPartnerList = $general->fetchDataFromTable('r_implementation_partners', $condition);
//province data
$pResult = $general->fetchDataFromTable('province_details');

$province = "";
$province .= "<option value=''> -- select -- </option>";
foreach ($pResult as $provinceName) {
	$province .= "<option value='" . $provinceName['province_name'] . "##" . $provinceName['province_code'] . "'>" . ucwords($provinceName['province_name']) . "</option>";
}
//$facility = "";
$facility = "<option value=''> -- select -- </option>";
foreach ($fResult as $fDetails) {
	$facility .= "<option value='" . $fDetails['facility_id'] . "'>" . ucwords(addslashes($fDetails['facility_name'])) . "</option>";
}

?>
<link href="/assets/css/multi-select.css" rel="stylesheet" />
<style>
	.select2-selection__choice {
		color: #000000 !important;
	}

	#ms-sampleCode {
		width: 110%;
	}

	#alertText {
		text-shadow: 1px 1px #eee;
	}
</style>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><i class="fa fa-edit"></i> Edit Sample List</h1>
		<ol class="breadcrumb">
			<li><a href="/"><i class="fa fa-dashboard"></i> Home</a></li>
			<li class="active">Sample List</li>
		</ol>
	</section>

	<!-- Main content -->
	<section class="content">
		<div class="box box-default">
			<div class="box-header with-border">
				<div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span> indicates required field &nbsp;</div>
			</div>
			<table class="table" cellpadding="1" cellspacing="3" style="margin-left:1%;margin-top:20px;width: 80%;">
				<tr>
					<td>&nbsp;<b>From Lab Name&nbsp;:<span class="mandatory">*</span></b></td>
					<td>
						<select style="width: 275px;" class="form-control" id="labName" name="labName" title="Please select lab name">
							<option value="">-- select --</option>
							<?php
							foreach ($lResult as $name) {
							?>
								<option value="<?php echo $name['facility_id']; ?>" <?php echo ($name['facility_id'] == $sampleInfo['moved_from_lab_id']) ? "selected='selected'" : ''; ?>><?php echo ucwords($name['facility_name']); ?></option>
							<?php
							}
							?>
						</select>
					</td>
					<td>&nbsp;<b>Test Type&nbsp;:<span class="mandatory">*</span></b></td>
					<td>
						<select style="width: 100%;" class="form-control" id="testType" name="testType" title="Choose one test type">
							<option value="">-- select --</option>
							<?php if (isset($systemConfig['modules']['vl']) && $systemConfig['modules']['vl'] == true) { ?>
								<option value="vl" <?php echo ('vl' == $sampleInfo['test_type']) ? "selected='selected'" : ''; ?>>Viral Load</option>
							<?php } ?>
							<?php if (isset($systemConfig['modules']['eid']) && $systemConfig['modules']['eid'] == true) { ?>
								<option value="eid" <?php echo ('eid' == $sampleInfo['test_type']) ? "selected='selected'" : ''; ?>>Early Infant Diagnosis</option>
							<?php } ?>
							<?php if (isset($systemConfig['modules']['covid19']) && $systemConfig['modules']['covid19'] == true) { ?>
								<option value="covid19" <?php echo ('covid19' == $sampleInfo['test_type']) ? "selected='selected'" : ''; ?>>Covid-19</option>
							<?php } ?>
							<?php if (isset($systemConfig['modules']['hepatitis']) && $systemConfig['modules']['hepatitis'] == true) { ?>
								<option value='hepatitis' <?php echo ('hepatitis' == $sampleInfo['test_type']) ? "selected='selected'" : ''; ?>>Hepatitis</option>
							<?php } ?>
						</select>
					</td>
				</tr>

				<tr>
					<td>&nbsp;<b>Province&nbsp;:</b></td>
					<td>
						<select style="width: 275px;" class="form-control" id="provinceName" name="provinceName" title="Please select province name" onchange="getfacilityDetails(this);">
							<option value="">-- select --</option>
							<?php echo $province; ?>
						</select>
					</td>
					<td>&nbsp;<b>District&nbsp;:</b></td>
					<td>
						<select style="width: 275px;" class="form-control" id="districtName" name="districtName" title="Please select district name" onchange="getfacilityDistrictwise(this);">
							<option value="">-- select --</option>
						</select>
					</td>
				</tr>
				<tr>
					<td>&nbsp;<b>Facility Name&nbsp;:</b></td>
					<td>
						<select style="width: 275px;" class="form-control" id="facilityName" name="facilityName" title="Please select facility name">
							<option value="">-- select --</option>
							<?php echo $facility; ?>
						</select>
					</td>
					<td><b>Sample Collection Date&nbsp;:</b></td>
					<td>
						<input type="text" id="sampleCollectionDate" name="sampleCollectionDate" class="form-control" placeholder="Select Collection Date" readonly style="background:#fff;width: 100%;" />
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
				<form class="form-horizontal" method="post" name="editSampleList" id="editSampleList" autocomplete="off" action="editSampleListHelper.php">
					<div class="box-body">
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="batchCode" class="col-lg-4 control-label">Move To Lab <span class="mandatory">*</span></label>
									<div class="col-lg-7" style="margin-left:3%;">
										<select style="width: 275px;" class="form-control isRequired" id="labNameTo" name="labNameTo" title="Please select lab name">
											<option value="">-- select --</option>
											<?php foreach ($lResult as $name) { ?>
												<option value="<?php echo $name['facility_id']; ?>" <?php echo ($name['facility_id'] == $sampleInfo['moved_to_lab_id']) ? "selected='selected'" : ''; ?>><?php echo ucwords($name['facility_name']); ?></option>
											<?php } ?>
										</select>
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="batchCode" class="col-lg-4 control-label">Reason For Moving </label>
									<div class="col-lg-7" style="margin-left:3%;">
										<textarea style="width: 275px;" class="form-control" name="reasonForMoving" id="reasonForMoving" title="Reason For Moving" placeholder="Reason"><?php echo $sampleInfo['reason_for_moving']; ?></textarea>
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="batchCode" class="col-lg-4 control-label">Approve By </label>
									<div class="col-lg-7" style="margin-left:3%;">
										<input style="width: 275px;" type="text" class="form-control" name="approveBy" id="approveBy" title="Approve by" placeholder="Approve by" value="<?php echo $sampleInfo['move_approved_by']; ?>" />
									</div>
								</div>
							</div>
						</div>
						<div class="row" id="sampleDetails">
							<div class="col-md-8">
								<div class="form-group">
									<div class="col-md-12">
										<div style="width:60%;margin:0 auto;clear:both;">
											<a href='#' id='select-all-samplecode' style="float:left" class="btn btn-info btn-xs">Select All&nbsp;&nbsp;<i class="icon-chevron-right"></i></a> <a href='#' id='deselect-all-samplecode' style="float:right;display:none;" class="btn btn-danger btn-xs"><i class="icon-chevron-left"></i>&nbsp;Deselect All</a>
										</div><br /><br />
										<select id='sampleCode' name="sampleCode[]" multiple='multiple' class="search">
											<?php
											foreach ($sampleListLabDataInfo as $sample) {
											?>
												<option value="<?php echo $sample[$primaryKey]; ?>" <?php echo (in_array($sample[$primaryKey], $selectedLab)) ? 'selected="selected"' : ''; ?> <?php echo ($sample['data_sync'] == 1) ? 'disabled="disabled"' : ''; ?>><?php echo $sample['remote_sample_code']; ?></option>
											<?php
											}
											?>
										</select>
									</div>
								</div>
							</div>
						</div>
						<div class="row" id="alertText" style="font-size:18px;"></div>
					</div>
					<!-- /.box-body -->
					<div class="box-footer">
						<input type="hidden" name="labId" id="labId" title="Please choose lab from name" />
						<input type="hidden" name="testTypeId" id="testTypeId" value="<?php echo $sampleInfo['test_type']; ?>" />
						<input type="hidden" name="selectedSampleIdFromtable" value="<?php echo json_encode($selectedLab); ?>" />
						<input type="hidden" name="moveSampleId" id="moveSampleId" value="<?php echo base64_encode($sampleInfo['move_sample_id']); ?>" />
						<a id="sampleSubmit" class="btn btn-primary" href="javascript:void(0);" title="Please select machine" onclick="validateNow();return false;">Save</a>
						<a href="sampleList.php" class="btn btn-default"> Cancel</a>
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
<script src="/assets/js/jquery.multi-select.js"></script>
<script src="/assets/js/jquery.quicksearch.js"></script>
<script type="text/javascript" src="/assets/plugins/daterangepicker/moment.min.js"></script>
<script type="text/javascript" src="/assets/plugins/daterangepicker/daterangepicker.js"></script>
<script type="text/javascript">
	noOfSamples = 0;
	provinceName = true;
	facilityName = true;
	$(document).ready(function() {
		$("#labName").select2({
			placeholder: "Select From Lab Name"
		});
		$("#facilityName").select2({
			placeholder: "Select Facilities"
		});
		$("#provinceName").select2({
			placeholder: "Select Province"
		});

		$('#sampleCollectionDate').daterangepicker({
				locale: {
					cancelLabel: 'Clear'
				},
				format: 'DD-MMM-YYYY',
				separator: ' to ',
				startDate: moment().subtract(29, 'days'),
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
	});

	function validateNow() {
		flag = deforayValidator.init({
			formId: 'editSampleList'
		});
		$("#labId").val($("#labName").val());
		var labFrom = $("#labName").val();
		var labTo = $("#labNameTo").val();
		if (labFrom == labTo) {
			alert("Lab from and Lab To name can not be same!");
			return false;
		}
		if (flag) {
			$.blockUI();
			document.getElementById('editSampleList').submit();
		}
	}

	//$("#auditRndNo").multiselect({height: 100,minWidth: 150});
	$(document).ready(function() {
		var unSelectedLength = $('.search > option').length - $(".search :selected").length;
		$('.search').multiSelect({
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
				this.qs1.cache();
				console.log(this.qs1.cache());
				this.qs2.cache();
				$("#unselectableCount").html("Available samples(" + this.qs1.cache().matchedResultsCount + ")");
				$("#selectableCount").html("Selected samples(" + this.qs2.cache().matchedResultsCount + ")");
			},
			afterDeselect: function() {
				//button disabled/enabled
				if (this.qs2.cache().matchedResultsCount == 0) {
					$("#sampleSubmit").attr("disabled", true);
					$("#sampleSubmit").css("pointer-events", "none");
				} else {
					$("#sampleSubmit").attr("disabled", false);
					$("#sampleSubmit").css("pointer-events", "auto");
				}
				this.qs1.cache();
				this.qs2.cache();
				$("#unselectableCount").html("Available samples(" + this.qs1.cache().matchedResultsCount + ")");
				$("#selectableCount").html("Selected samples(" + this.qs2.cache().matchedResultsCount + ")");
			}
		});

		$('#select-all-samplecode').click(function() {
			$('#sampleCode').multiSelect('select_all');
			$("li.ms-elem-selection.disabled").css("display", "block").addClass("ms-selected");
			$("li.ms-elem-selectable.disabled").css("display", "none").removeClass("ms-selected");
			return false;
		});
		$('#deselect-all-samplecode').click(function() {
			$('#sampleCode').multiSelect('deselect_all');
			$("li.ms-elem-selection.disabled").css("display", "block").addClass("ms-selected");
			$("li.ms-elem-selectable.disabled").css("display", "none").removeClass("ms-selected");

			$("#sampleSubmit").attr("disabled", true);
			$("#sampleSubmit").css("pointer-events", "none");
			return false;
		});
	});


	function getSampleCodeDetails() {
		$.blockUI();
		var lName = $("#labName").val();
		var testType = $("#testType").val();
		var pName = $("#provinceName").val();
		var dName = $("#districtName").val();
		var fName = $("#facilityName").val();
		var scDate = $("#sampleCollectionDate").val();
		if (lName != "" && testType != "") {
			$.post("/move-samples/getMoveSampleCodeDetails.php", {
					lName: lName,
					testType: testType,
					pName: pName,
					dName: dName,
					fName: fName,
					scDate: scDate
				},
				function(data) {
					if (data != "") {
						$("#sampleDetails").html(data);
						$("#sampleSubmit").attr("disabled", true);
						$("#sampleSubmit").css("pointer-events", "none");
					}
				});
		} else {
			if (lName == '') {
				alert($("#labName").attr('title'));
				$("#labName").focus();
				$("#labName").css('border-color', 'red');
			} else if (testType == '') {
				alert($("#testType").attr('title'));
				$("#testType").focus();
				$("#testType").css('border-color', 'red');
			}
		}
		$.unblockUI();
	}

	function getfacilityDetails(obj) {
		$.blockUI();
		var cName = $("#facilityName").val();
		var pName = $("#provinceName").val();
		if (pName != '' && provinceName && facilityName) {
			facilityName = false;
		}
		if ($.trim(pName) != '') {
			if (provinceName) {
				$.post("/includes/siteInformationDropdownOptions.php", {
						pName: pName
					},
					function(data) {
						if (data != "") {
							details = data.split("###");
							$("#facilityName").html(details[0]);
							$("#districtName").html(details[1]);
						}
					});
			}
		} else if (pName == '' && cName == '') {
			provinceName = true;
			facilityName = true;
			$("#provinceName").html("<?php echo $province; ?>");
			$("#facilityName").html("<?php echo $facility; ?>");
		} else {
			$("#districtName").html("<option value=''> -- select -- </option>");
		}
		$.unblockUI();
	}

	function getfacilityDistrictwise(obj) {
		$.blockUI();
		var dName = $("#districtName").val();
		var cName = $("#facilityName").val();
		if (dName != '') {
			$.post("/includes/siteInformationDropdownOptions.php", {
					dName: dName,
					cliName: cName
				},
				function(data) {
					if (data != "") {
						details = data.split("###");
						$("#facilityName").html(details[0]);
					}
				});
		} else {
			$("#facilityName").html("<option value=''> -- select -- </option>");
		}
		$.unblockUI();
	}
</script>
<?php
include(APPLICATION_PATH . '/footer.php');
?>