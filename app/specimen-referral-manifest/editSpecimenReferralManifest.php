<?php

use App\Registries\ContainerRegistry;
use App\Services\Covid19Service;
use App\Services\EidService;
use App\Services\FacilitiesService;
use App\Services\TbService;
use App\Services\GenericTestsService;
use App\Services\UsersService;
use App\Services\VlService;


$title = "Edit Specimen Referral Manifest";

require_once APPLICATION_PATH . '/header.php';


/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

$usersList = [];
$users = $usersService->getActiveUsers($_SESSION['facilityMap']);
foreach ($users as $u) {
	$usersList[$u["user_id"]] = $u['user_name'];
}
//global config
$configQuery = "SELECT `value` FROM global_config WHERE name ='vl_form'";
$configResult = $db->query($configQuery);
$country = $configResult[0]['value'];

// Sanitize values before using them below
$_GET = array_map('htmlspecialchars', $_GET);
$id = (isset($_GET['id'])) ? base64_decode($_GET['id']) : null;

$pQuery = "SELECT * FROM package_details WHERE package_id=" . $id;
$pResult = $db->rawQuery($pQuery);

if ($pResult[0]['package_status'] == 'dispatch') {
	header("Location:packageList.php");
}
if ($_SESSION['instanceType'] == 'remoteuser') {
	$sCode = 'remote_sample_code';
} else if ($sarr['sc_user_type'] == 'vluser' || $sarr['sc_user_type'] == 'standalone') {
	$sCode = 'sample_code';
}

// Sanitize values before using them below
$_GET = array_map('htmlspecialchars', $_GET);
$module = isset($_GET['t']) ? base64_decode($_GET['t']) : 'vl';
if ($module == 'vl') {
	$query = "SELECT vl.sample_code,vl.remote_sample_code,vl.vl_sample_id,vl.sample_package_id FROM form_vl as vl where (vl.remote_sample_code IS NOT NULL) AND (vl.sample_package_id is null OR vl.sample_package_id='' OR vl.sample_package_id=" . $id . ") AND (remote_sample = 'yes') ";
	$m = ($module == 'vl') ? 'vl' : $module;
	$vlService = new VlService($db);
	$sampleTypes = $vlService->getVlSampleTypes();
} else if ($module == 'eid') {
	$query = "SELECT vl.sample_code,vl.remote_sample_code,vl.eid_id,vl.sample_package_id FROM form_eid as vl where (vl.remote_sample_code IS NOT NULL) AND (vl.sample_package_id is null OR vl.sample_package_id='' OR vl.sample_package_id=" . $id . ") AND (remote_sample = 'yes') ";
	$m = ($module == 'eid') ? 'eid' : $module;
	$eidService = new EidService($db);
	$sampleTypes = $eidService->getEidSampleTypes();
} else if ($module == 'hepatitis') {
	$query = "SELECT vl.sample_code,vl.remote_sample_code,vl.hepatitis_id,vl.sample_package_id FROM form_hepatitis as vl where (vl.remote_sample_code IS NOT NULL) AND (vl.sample_package_id is null OR vl.sample_package_id='' OR vl.sample_package_id=" . $id . ") AND (remote_sample = 'yes')  ";
	$m = ($module == 'HEP') ? 'hepatitis' : $module;
} else if ($module == 'covid19') {
	$query = "SELECT vl.sample_code,vl.remote_sample_code,vl.covid19_id,vl.sample_package_id FROM form_covid19 as vl where (vl.remote_sample_code IS NOT NULL) AND (vl.sample_package_id is null OR vl.sample_package_id='' OR vl.sample_package_id=" . $id . ") AND (remote_sample = 'yes') ";
	$m = ($module == 'C19') ? 'covid19' : $module;
	$covid19Service = new Covid19Service($db);
	$sampleTypes = $covid19Service->getCovid19SampleTypes();
} else if ($module == 'tb') {
	$query = "SELECT vl.sample_code,vl.remote_sample_code,vl.tb_id,vl.sample_package_id FROM form_tb as vl where (vl.remote_sample_code IS NOT NULL) AND (vl.sample_package_id is null OR vl.sample_package_id='' OR vl.sample_package_id=" . $id . ") AND (remote_sample = 'yes')  ";
	$m = ($module == 'TB') ? 'tb' : $module;
	$tbService = new TbService($db);
	$sampleTypes = $tbService->getTbSampleTypes();
} else if ($module == 'generic-tests') {
	$query = "SELECT vl.sample_code,vl.remote_sample_code,vl.sample_id,vl.sample_package_id FROM form_generic as vl where (vl.remote_sample_code IS NOT NULL) AND (vl.sample_package_id is null OR vl.sample_package_id='' OR vl.sample_package_id=" . $id . ") AND (remote_sample = 'yes')  ";
	$m = ($module == 'GEN') ? 'generic-tests' : $module;
	$genService = new GenericTestsService($db);
	$sampleTypes = $genService->getGenericSampleTypes();
}
$testingLabs = $facilitiesService->getTestingLabs($m);
$facilities = $facilitiesService->getHealthFacilities($module);

if (!empty($_SESSION['facilityMap'])) {
	$query = $query . " AND facility_id IN(" . $_SESSION['facilityMap'] . ")";
}

$query = $query . " ORDER BY vl.request_created_datetime ASC";

$result = $db->rawQuery($query);
// if($sarr['sc_user_type']=='remoteuser'){
//   $sCode = 'remote_sample_code';
// }else if($sarr['sc_user_type']=='vluser'){
//   $sCode = 'sample_code';
// }

$global = $general->getGlobalConfig();


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
		margin: 30px 0px 30px 0px;
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
		<h1><em class="fa-solid fa-pen-to-square"></em> Edit Specimen Referral Manifest</h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em> Home</a></li>
			<li><a href="/specimen-referral-manifest/specimenReferralManifestList.php"> Manage Specimen Referral Manifest</a></li>
			<li class="active">Edit Specimen Referral Manifest</li>
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
				<form class="form-horizontal" method="post" name="editSpecimenReferralManifestForm" id="editSpecimenReferralManifestForm" autocomplete="off" action="editSpecimenReferralManifestCodeHelper.php">
					<div class="box-body">
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="packageCode" class="col-lg-4 control-label">Manifest Code <span class="mandatory">*</span></label>
									<div class="col-lg-7" style="margin-left:3%;">
										<input type="text" class="form-control isRequired" id="packageCode" name="packageCode" placeholder="Manifest Code" title="Please enter manifest code" readonly value="<?php echo strtoupper($pResult[0]['package_code']); ?>" />
									</div>
								</div>
							</div>

							<div class="col-md-6">
								<div class="form-group">
									<label for="packageCode" class="col-lg-4 control-label">Testing Lab :</label>
									<div class="col-lg-7" style="margin-left:3%;">
										<select class="form-control" id="testingLab" name="testingLab" title="Choose one test lab" readonly="readonly">
											<?= $general->generateSelectOptions($testingLabs, $pResult[0]['lab_id'], '-- Select --'); ?>
										</select>
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="packageCode" class="col-lg-4 control-label">Manifest Status <span class="mandatory">*</span></label>
									<div class="col-lg-7" style="margin-left:3%;">
										<select class="form-control isRequired" name="packageStatus" id="packageStatus" title="Please select manifest status" readonly="readonly">
											<option value="">-- Select --</option>
											<option value="pending" <?php echo ($pResult[0]['package_status'] == 'pending') ? "selected='selected'" : ''; ?>>Pending</option>
											<option value="dispatch" <?php echo ($pResult[0]['package_status'] == 'dispatch') ? "selected='selected'" : ''; ?>>Dispatch</option>
											<option value="received" <?php echo ($pResult[0]['package_status'] == 'received') ? "selected='selected'" : ''; ?>>Received</option>
										</select>
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="operator" class="col-lg-4 control-label"><?php echo _("Operator/Technician"); ?></label>
									<div class="col-lg-7" style="margin-left:3%;">
										<select class="form-control select2" id="operator" name="operator" title="Choose one Operator/Technician">
											<?= $general->generateSelectOptions($usersList, $pResult[0]['added_by'], '-- Select --'); ?>
										</select>
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="facility" class="col-lg-4 control-label"><?php echo _("Sample Collection Point"); ?></label>
									<div class="col-lg-7" style="margin-left:3%;">
										<select class="form-control select2" id="facility" name="facility" title="Choose one sample collection point">
											<?= $general->generateSelectOptions($facilities, null, '-- Select --'); ?>
										</select>
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="sampleType" class="col-lg-4 control-label"><?php echo _("Sample Type"); ?></label>
									<div class="col-lg-7" style="margin-left:3%;">
										<select class="form-control select2" id="sampleType" name="sampleType" title="Choose Sample Type">
											<?= $general->generateSelectOptions($sampleTypes, null, '-- Select --'); ?>
										</select>
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="daterange" class="col-lg-4 control-label"><?php echo _("Sample Collection Date Range"); ?></label>
									<div class="col-lg-7" style="margin-left:3%;">
										<input type="text" class="form-control" id="daterange" name="daterange" placeholder="<?php echo _('Sample Collection Date Range'); ?>" title="Choose one sample collection date range">
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-12 text-center">
								<div class="form-group">
									<a class="btn btn-primary" href="javascript:void(0);" title="Please select testing lab" onclick="getSampleCodeDetails();return false;">Search </a>
									<a href="javascript:void(0);" class="btn btn-default" onclick="clearSelection();"> Clear</a>
								</div>
							</div>
						</div>
					</div>
					<div class="row" id="sampleDetails">
						<div class="col-md-9 col-md-offset-1">
							<div class="form-group">
								<div class="col-md-12">
									<div style="width:60%;margin:0 auto;clear:both;">
										<a href='#' id='select-all-samplecode' style="float:left" class="btn btn-info btn-xs">Select All&nbsp;&nbsp;<em class="fa-solid fa-chevron-right"></em></a> <a href='#' id='deselect-all-samplecode' style="float:right" class="btn btn-danger btn-xs"><em class="fa-solid fa-chevron-left"></em>&nbsp;Deselect All</a>
									</div><br /><br />
									<select id='sampleCode' name="sampleCode[]" multiple='multiple' class="search">
										<?php foreach ($result as $sample) {
											if ($sample[$sCode] != '') {
												if ($module == 'vl') {
													$sampleId  = $sample['vl_sample_id'];
												} else if ($module == 'eid') {
													$sampleId  = $sample['eid_id'];
												} else if ($module == 'covid19') {
													$sampleId  = $sample['covid19_id'];
												} else if ($module == 'hepatitis') {
													$sampleId  = $sample['hepatitis_id'];
												} else if ($module == 'tb') {
													$sampleId  = $sample['tb_id'];
												}
										?>
												<option value="<?php echo $sampleId; ?>" <?php echo ($sample['sample_package_id'] == $id) ? 'selected="selected"' : ''; ?>><?php echo $sample[$sCode]; ?></option>
										<?php }
										} ?>
									</select>
								</div>
							</div>
						</div>
					</div>
					<div class="row" id="alertText" style="font-size:18px;"></div>
			</div>
			<!-- /.box-body -->
			<div class="box-footer">
				<input type="hidden" name="packageId" value="<?php echo $pResult[0]['package_id']; ?>" />
				<input type="hidden" class="form-control isRequired" id="module" name="module" placeholder="" title="" readonly value="<?= htmlspecialchars($module); ?>" />
				<a id="packageSubmit" class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;">Submit</a>
				<a href="javascript:history.go(-1);" class="btn btn-default"> Cancel</a>
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
<script src="/assets/js/moment.min.js"></script>
<script type="text/javascript" src="/assets/plugins/daterangepicker/daterangepicker.js"></script>
<script src="/assets/js/jquery.multi-select.js"></script>
<script src="/assets/js/jquery.quicksearch.js"></script>
<script type="text/javascript">
	noOfSamples = 100;

	function validateNow() {
		flag = deforayValidator.init({
			formId: 'editSpecimenReferralManifestForm'
		});

		if (flag) {
			$.blockUI();
			document.getElementById('editSpecimenReferralManifestForm').submit();
		}
	}

	//$("#auditRndNo").multiselect({height: 100,minWidth: 150});
	$(document).ready(function() {

		$('#daterange').daterangepicker({
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
		$(".select2").select2();
		$(".select2").select2({
			tags: true
		});


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
				//button disabled/enabled
				if (this.qs2.cache().matchedResultsCount == noOfSamples) {
					alert("You have selected maximum number of samples - " + this.qs2.cache().matchedResultsCount);
					$("#packageSubmit").attr("disabled", false);
					$("#packageSubmit").css("pointer-events", "auto");
				} else if (this.qs2.cache().matchedResultsCount <= noOfSamples) {
					$("#packageSubmit").attr("disabled", false);
					$("#packageSubmit").css("pointer-events", "auto");
				} else if (this.qs2.cache().matchedResultsCount > noOfSamples) {
					alert("You have already selected Maximum no. of sample " + noOfSamples);
					$("#packageSubmit").attr("disabled", true);
					$("#packageSubmit").css("pointer-events", "none");
				}
				this.qs1.cache();
				this.qs2.cache();
			},
			afterDeselect: function() {
				//button disabled/enabled
				if (this.qs2.cache().matchedResultsCount == 0) {
					//$("#packageSubmit").attr("disabled", true);
					//$("#packageSubmit").css("pointer-events", "none");
				} else if (this.qs2.cache().matchedResultsCount == noOfSamples) {
					alert("You have selected maximum number of samples - " + this.qs2.cache().matchedResultsCount);
					$("#packageSubmit").attr("disabled", false);
					$("#packageSubmit").css("pointer-events", "auto");
				} else if (this.qs2.cache().matchedResultsCount <= noOfSamples) {
					$("#packageSubmit").attr("disabled", false);
					$("#packageSubmit").css("pointer-events", "auto");
				} else if (this.qs2.cache().matchedResultsCount > noOfSamples) {
					$("#packageSubmit").attr("disabled", true);
					$("#packageSubmit").css("pointer-events", "none");
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
			$("#packageSubmit").attr("disabled", true);
			$("#packageSubmit").css("pointer-events", "none");
			return false;
		});
	});

	function checkNameValidation(tableName, fieldName, obj, fnct, alrt, callback) {
		var removeDots = obj.value.replace(/\./g, "");
		var removeDots = removeDots.replace(/\,/g, "");
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
		if ($('#testingLab').val() != '') {
			$.blockUI();

			$.post("/specimen-referral-manifest/getSpecimenReferralManifestSampleCodeDetails.php", {
					module: $("#module").val(),
					testingLab: $('#testingLab').val(),
					facility: $('#facility').val(),
					daterange: $('#daterange').val(),
					sampleType: $('#sampleType').val(),
					operator: $('#operator').val()
				},
				function(data) {
					if (data != "") {
						$("#sampleDetails").html(data);
						$("#packageSubmit").attr("disabled", true);
						$("#packageSubmit").css("pointer-events", "none");
					}
				});
			$.unblockUI();
		} else {
			alert('Please select the testing lab');
		}
	}

	function clearSelection() {
		//$('#testingLab').val('').trigger('change');
		getSampleCodeDetails();
	}
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';
