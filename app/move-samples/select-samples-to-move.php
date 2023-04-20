<?php

use App\Models\General;

$title = "Add Sample List";

require_once(APPLICATION_PATH . '/header.php');

$general = new General();


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
$pResult = $general->fetchDataFromTable('geographical_divisions');

$province = "<option value=''> -- select -- </option>";
foreach ($pResult as $provinceName) {
	$province .= "<option value='" . $provinceName['geo_name'] . "##" . $provinceName['geo_code'] . "'>" . ($provinceName['geo_name']) . "</option>";
}
//$facility = "";
$facility = "<option value=''> -- select -- </option>";
foreach ($fResult as $fDetails) {
	$facility .= "<option value='" . $fDetails['facility_id'] . "'>" . (addslashes($fDetails['facility_name'])) . "</option>";
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
		<h1><em class="fa-solid fa-pen-to-square"></em> Select Samples to Move</h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em> Home</a></li>
			<li class="active">Sample List</li>
		</ol>
	</section>

	<!-- Main content -->
	<section class="content">

		<div class="box box-default">
			<div class="box-header with-border">
				<div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span> indicates required field &nbsp;</div>
			</div>
			<table class="table" aria-hidden="true" style="margin-left:1%;margin-top:20px;width: 90%;">
				<tr>
					<td>&nbsp;<strong>From Lab Name&nbsp;:<span class="mandatory">*</span></strong></td>
					<td>
						<select style="width: 100%;" class="form-control" id="labName" name="labName" title="Please select lab name">
							<option value="">-- select --</option>
							<?php
							foreach ($lResult as $name) {
							?>
								<option value="<?php echo $name['facility_id']; ?>"><?php echo ($name['facility_name']); ?></option>
							<?php
							}
							?>
						</select>
					</td>
					<td>&nbsp;<strong>Test Type&nbsp;:<span class="mandatory">*</span></strong></td>
					<td>
						<select style="width: 100%;" class="form-control" id="testType" name="testType" title="Choose one test type">
							<option value="">-- select --</option>
							<?php if (isset(SYSTEM_CONFIG['modules']['vl']) && SYSTEM_CONFIG['modules']['vl'] === true) { ?>
								<option value="vl">Viral Load</option>
							<?php } ?>
							<?php if (isset(SYSTEM_CONFIG['modules']['eid']) && SYSTEM_CONFIG['modules']['eid'] === true) { ?>
								<option value="eid">Early Infant Diagnosis</option>
							<?php } ?>
							<?php if (isset(SYSTEM_CONFIG['modules']['covid19']) && SYSTEM_CONFIG['modules']['covid19'] === true) { ?>
								<option value="covid19">Covid-19</option>
							<?php } ?>
							<?php if (isset(SYSTEM_CONFIG['modules']['hepatitis']) && SYSTEM_CONFIG['modules']['hepatitis'] === true) { ?>
								<option value='hepatitis'>Hepatitis</option>
							<?php } ?>
						</select>
					</td>
				</tr>
				<tr>
					<td>&nbsp;<strong>Province&nbsp;:</strong></td>
					<td>
						<select style="width: 100%;" class="form-control" id="provinceName" name="provinceName" title="Please select province name" onchange="getfacilityDetails(this);">
							<option value="">-- select --</option>
							<?php echo $province; ?>
						</select>
					</td>
					<td>&nbsp;<strong>District&nbsp;:</strong></td>
					<td>
						<select style="width: 100%;" class="form-control" id="districtName" name="districtName" title="Please select district name" onchange="getfacilityDistrictwise(this);">
							<option value="">-- select --</option>
						</select>
					</td>
				</tr>
				<tr>
					<td>&nbsp;<strong>Facility Name&nbsp;:</strong></td>
					<td>
						<select style="width: 100%;" class="form-control" id="facilityName" name="facilityName" title="Please select facility name">
							<option value="">-- select --</option>
							<?php echo $facility; ?>
						</select>
					</td>
					<td><strong>Sample Collection Date&nbsp;:</strong></td>
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
				<form class="form-horizontal" method="post" name="selectSamplesToMove" id="selectSamplesToMove" autocomplete="off" action="select-samples-to-move-helper.php">
					<div class="box-body">
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="batchCode" class="col-lg-4 control-label">Move To Lab <span class="mandatory">*</span></label>
									<div class="col-lg-7" style="margin-left:3%;">
										<select style="width: 100%;" class="form-control isRequired" id="labNameTo" name="labNameTo" title="Please select lab name">
											<option value="">-- select --</option>
											<?php foreach ($lResult as $name) { ?>
												<option value="<?php echo $name['facility_id']; ?>"><?php echo ($name['facility_name']); ?></option>
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
										<textarea style="width: 100%;" class="form-control" name="reasonForMoving" id="reasonForMoving" title="Reason For Moving" placeholder="Reason"></textarea>
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="batchCode" class="col-lg-4 control-label">Approve By </label>
									<div class="col-lg-7" style="margin-left:3%;">
										<input style="width: 100%;" type="text" class="form-control" name="approveBy" id="approveBy" title="Approve by" placeholder="Approve by" />
									</div>
								</div>
							</div>
						</div>
						<div class="row" id="sampleDetails">
							<div class="col-md-8">
								<div class="form-group">
									<div class="col-md-12">
										<div style="width:60%;margin:0 auto;clear:both;">
											<a href='#' id='select-all-samplecode' style="float:left" class="btn btn-info btn-xs">Select All&nbsp;&nbsp;<em class="fa-solid fa-chevron-right"></em></a> <a href='#' id='deselect-all-samplecode' style="float:right" class="btn btn-danger btn-xs"><em class="fa-solid fa-chevron-left"></em>&nbsp;Deselect All</a>
										</div><br /><br />
										<select id='sampleCode' name="sampleCode[]" multiple='multiple' class="search"></select>
									</div>
								</div>
							</div>
						</div>
						<div class="row" id="alertText" style="font-size:18px;"></div>
					</div>
					<!-- /.box-body -->
					<div class="box-footer">
						<input type="hidden" name="testTypeId" id="testTypeId" />
						<input type="hidden" name="labId" id="labId" title="Please choose lab from name" />
						<a id="sampleSubmit" class="btn btn-primary" href="javascript:void(0);" title="Please select machine" onclick="validateNow();return false;" style="pointer-events:none;" disabled>Save</a>
						<a href="move-samples.php" class="btn btn-default"> Cancel</a>
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
<script src="/assets/js/moment.min.js"></script>
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

	});

	function validateNow() {
		if (!confirm("Are you sure you want to move the selected samples? This action cannot be undone.")) {
			return false;
		}
		flag = deforayValidator.init({
			formId: 'selectSamplesToMove'
		});
		$("#labId").val($("#labName").val());
		$("#testTypeId").val($("#testType").val());
		var labFrom = $("#labName").val();
		var labTo = $("#labNameTo").val();
		if (labFrom == labTo) {
			alert("Lab from and Lab To name can not be same!");
			return false;
		}
		if (flag) {
			$.blockUI();
			document.getElementById('selectSamplesToMove').submit();
		}
	}

	//$("#auditRndNo").multiselect({height: 100,minWidth: 150});
	$(document).ready(function() {
		$('.search').multiSelect({
			selectableHeader: "<input type='text' class='search-input form-control' autocomplete='off' placeholder='Enter Sample Code'>",
			selectionHeader: "<input type='text' class='search-input form-control' autocomplete='off' placeholder='Enter Sample Code'>",
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
				this.qs2.cache();
			},
			afterDeselect: function() {
				//button disabled/enabled
				if (this.qs2.cache().matchedResultsCount == 0) {
					$("#sampleSubmit").attr("disabled", true);
					$("#sampleSubmit").css("pointer-events", "none");
				} else {
					$("#sampleSubmit").attr("disabled", false);
					$("#sampleSubmit").css("pointer-events", "auot");
				}
				this.qs1.cache();
				this.qs2.cache();
			}
		});

		$('#select-all-samplecode').click(function() {
			$('#sampleCode').multiSelect('select_all');
			return false;
		});
		$('#deselect-all-samplecode').click(function() {
			$('#sampleCode').multiSelect('deselect_all');
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
			$.post("/move-samples/get-move-samples-codes.php", {
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
						pName: pName,
						testType: 'vl'
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
require_once(APPLICATION_PATH . '/footer.php');
