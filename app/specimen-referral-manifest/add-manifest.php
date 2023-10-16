<?php

use App\Services\TbService;
use App\Services\VlService;
use App\Services\EidService;
use App\Services\UsersService;
use App\Services\CommonService;
use App\Services\Covid19Service;
use App\Services\HepatitisService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Services\GenericTestsService;

$title = "Add New Specimen Referral Manifest";

require_once APPLICATION_PATH . '/header.php';

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = $GLOBALS['request'];
$_GET = $request->getQueryParams();
$module = $_GET['t'];
$testingLabs = $facilitiesService->getTestingLabs($module);


/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

$usersList = [];
$users = $usersService->getActiveUsers($_SESSION['facilityMap']);
foreach ($users as $u) {
	$usersList[$u["user_id"]] = $u['user_name'];
}
$facilities = $facilitiesService->getHealthFacilities($module);
$shortCode = strtoupper($module);
if ($module == 'vl') {
	/** @var VlService $vlService */
	$vlService = ContainerRegistry::get(VlService::class);
	$sampleTypes = $vlService->getVlSampleTypes();
} else if ($module == 'eid') {
	/** @var EidService $eidService */
	$eidService = ContainerRegistry::get(EidService::class);
	$sampleTypes = $eidService->getEidSampleTypes();
} else if ($module == 'covid19') {
	$shortCode = 'C19';
	/** @var Covid19Service $covid19Service */
	$covid19Service = ContainerRegistry::get(Covid19Service::class);
	$sampleTypes = $covid19Service->getCovid19SampleTypes();
} else if ($module == 'hepatitis') {
	$shortCode = 'HEP';
	/** @var HepatitisService $hepDb */
	$hepDb = ContainerRegistry::get(HepatitisService::class);
	$sampleTypes = $hepDb->getHepatitisSampleTypes();
} else if ($module == 'tb') {
	/** @var TbService $tbService */
	$tbService = ContainerRegistry::get(TbService::class);
	$sampleTypes = $tbService->getTbSampleTypes();
} else if ($module == 'generic-tests') {
	/** @var GenericTestsService $genService */
	$genService = ContainerRegistry::get(GenericTestsService::class);
	$sampleTypes = $genService->getGenericSampleTypes();
}
$packageNo = strtoupper($shortCode . date('ymd') .  $general->generateRandomString(6));

$testTypeQuery = "SELECT * FROM r_test_types where test_status='active' ORDER BY test_standard_name ASC";
$testTypeResult = $db->rawQuery($testTypeQuery);
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
		<h1><em class="fa-solid fa-pen-to-square"></em> Create Specimen Referral Manifest</h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em> Home</a></li>
			<li><a href="/specimen-referral-manifest/view-manifests.php"> Manage Specimen Referral Manifest</a></li>
			<li class="active">Create Specimen Referral Manifest</li>
		</ol>
	</section>

	<!-- Main content -->
	<section class="content">
		<div class="box box-default">
			<div class="box-header with-border">
				<div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span> indicates required field &nbsp;</div>
			</div>
			<form class="<?php echo $hide; ?>form-horizontal" method="post" name="addSpecimenReferralManifestForm" id="addSpecimenReferralManifestForm" autocomplete="off" action="add-manifest-helper.php">
				<!-- /.box-header -->
				<div class="box-body">
					<?php $hide = "";
					if ($module == 'generic-tests') {
						$hide = "hide " ?>
						<div class="row">
							<div class="col-xs-4 col-md-4">
								<div class="form-group" style="margin-left:30px; margin-top:30px;">
									<label for="testType">Test Type</label>
									<select class="form-control" name="testType" id="testType" title="Please choose test type" style="width:100%;" onchange="getManifestCodeForm(this.value)">
										<option value=""> -- Select -- </option>
										<?php foreach ($testTypeResult as $testType) { ?>
											<option value="<?php echo $testType['test_type_id'] ?>"><?php echo $testType['test_standard_name'] ?></option>
										<?php } ?>
									</select>
								</div>
							</div>
						</div>
					<?php } ?>
					<!-- form start -->
					<div class="box-body">
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="packageCode" class="col-lg-4 control-label">Manifest Code <span class="mandatory">*</span></label>
									<div class="col-lg-7" style="margin-left:3%;">
										<input type="text" class="form-control isRequired" id="packageCode" name="packageCode" placeholder="Manifest Code" title="Please enter manifest code" readonly value="<?php echo strtoupper(htmlspecialchars($packageNo)); ?>" />
										<input type="hidden" class="form-control isRequired" id="module" name="module" placeholder="" title="" readonly value="<?= htmlspecialchars($module); ?>" />
									</div>
								</div>
							</div>

							<div class="col-md-6">
								<div class="form-group">
									<label for="packageCode" class="col-lg-4 control-label">Testing Lab <span class="mandatory">*</span> :</label>
									<div class="col-lg-7" style="margin-left:3%;">
										<select class="form-control select2 isRequired" id="testingLab" name="testingLab" title="Choose one test lab">
											<?= $general->generateSelectOptions($testingLabs, null, '-- Select --'); ?>
										</select>
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="operator" class="col-lg-4 control-label"><?php echo _translate("Operator/Technician"); ?> </label>
									<div class="col-lg-7" style="margin-left:3%;">
										<select class="form-control select2" id="operator" name="operator" title="Choose one Operator/Technician">
											<?= $general->generateSelectOptions($usersList, null, '-- Select --'); ?>
										</select>
									</div>
								</div>
							</div>

							<div class="col-md-6">
								<div class="form-group">
									<label for="facility" class="col-lg-4 control-label"><?php echo _translate("Sample Collection Point"); ?></label>
									<div class="col-lg-7" style="margin-left:3%;">
										<select class="form-control select2" id="facility" name="facility" title="Choose one sample collection point">
											<?= $general->generateSelectOptions($facilities, null, '-- Select --'); ?>
										</select>
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="sampleType" class="col-lg-4 control-label"><?php echo _translate("Sample Type"); ?></label>
									<div class="col-lg-7" style="margin-left:3%;">
										<select class="form-control select2" id="sampleType" name="sampleType" title="Choose Sample Type">
											<?= $general->generateSelectOptions($sampleTypes, null, '-- Select --'); ?>
										</select>
									</div>
								</div>
							</div>

							<div class="col-md-6">
								<div class="form-group">
									<label for="daterange" class="col-lg-4 control-label"><?php echo _translate("Sample Collection Date Range"); ?></label>
									<div class="col-lg-7" style="margin-left:3%;">
										<input type="text" class="form-control" id="daterange" name="daterange" placeholder="<?php echo _translate('Sample Collection Date Range'); ?>" title="Choose one sample collection date range">
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

					<br>
					<div class="row" id="sampleDetails">
						
					</div>
					<div class="row" id="alertText" style="font-size:18px;"></div>
				</div>
				<!-- /.box-body -->
				<div class="box-footer">
				<input type="hidden" name="selectedSample" id="selectedSample" />
				<input type="hidden" name="type" id="type" value="<?php echo $_GET['type']; ?>" />
					<a id="packageSubmit" class="btn btn-primary" href="javascript:void(0);" title="Please select machine" onclick="validateNow();return false;" style="pointer-events:none;" disabled>Save </a>
					<a href="view-manifests.php?t=<?= ($_GET['t']); ?>" class="btn btn-default"> Cancel</a>
				</div>
				<!-- /.box-footer -->
			</form>
		</div>
	<!-- /.row -->
	</section>
	<!-- /.content -->
</div>
<script src="/assets/js/moment.min.js"></script>
<script type="text/javascript" src="/assets/plugins/daterangepicker/daterangepicker.js"></script>
<script src="/assets/js/jquery.multi-select.js"></script>
<script src="/assets/js/jquery.quicksearch.js"></script>
<script type="text/javascript">
	noOfSamples = 100;
	sortedTitle = [];

	function validateNow() {
		var selVal = [];
        $('#search_to option').each(function(i, selected) {
            selVal[i] = $(selected).val();
        });
        $("#selectedSample").val(selVal);
		if (selVal == "") {
            alert("<?= _translate("Please select one or more samples", true); ?>");
            return false;
        }
		flag = deforayValidator.init({
			formId: 'addSpecimenReferralManifestForm'
		});
		if (flag) {
			$.blockUI();
			document.getElementById('addSpecimenReferralManifestForm').submit();
		}
	}

	$(document).ready(function() {
		$("#testType").select2({
			width: '100%',
			placeholder: "<?php echo _translate("Select Test Type"); ?>"
		});
		$('#daterange').daterangepicker({
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
				'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
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
		/*$(".select2").select2();
		$(".select2").select2({
			tags: true
		});*/

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
					$("#packageSubmit").attr("disabled", true);
					$("#packageSubmit").css("pointer-events", "none");
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

		/* $('#select-all-samplecode').click(function() {
			$('#sampleCode').multiSelect('select_all');
			return false;
		});
		$('#deselect-all-samplecode').click(function() {
			$('#sampleCode').multiSelect('deselect_all');
			$("#packageSubmit").attr("disabled", true);
			$("#packageSubmit").css("pointer-events", "none");
			return false;
		}); */
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
		if ($('#testingLab').val() != '') {
			$.blockUI();

			$.post("/specimen-referral-manifest/get-samples-for-manifest.php", {
					module: $("#module").val(),
					testType: $("#testType").val(),
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
		$('#testingLab').val('').trigger('change');
		getSampleCodeDetails();
	}

	function getManifestCodeForm(value) {
		if (value != "") {
			var code = value.toUpperCase() + '<?php echo strtoupper(date('ymd') .  $general->generateRandomString(6)); ?>';
			$('#packageCode').val(code);
			$("#addSpecimenReferralManifestForm").removeClass("hide");
		}
	}
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';
