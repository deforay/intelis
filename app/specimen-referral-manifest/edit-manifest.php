<?php

use App\Services\UsersService;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;

$title = "Edit Specimen Referral Manifest";
require_once APPLICATION_PATH . '/header.php';


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);


/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

$usersList = [];
$users = $usersService->getActiveUsers($_SESSION['facilityMap']);
foreach ($users as $u) {
	$usersList[$u["user_id"]] = $u['user_name'];
}


// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());
$id = isset($_GET['id']) ? base64_decode((string) $_GET['id']) : null;
$m = $module = $_GET['t'] ?? 'vl';

$pQuery = "SELECT * FROM specimen_manifests WHERE package_id = ?";
$pResult = $db->rawQueryOne($pQuery, [$id]);

if ($pResult['package_status'] == 'dispatch') {
	header("Location:/specimen-referral-manifest/view-manifests.php?t=" . $module);
}

$testingLabs = $facilitiesService->getTestingLabs($m);
$facilities = $facilitiesService->getHealthFacilities($module);


$testTypeResult = null;
if ($module == 'generic-tests') {

	$testQuery = "SELECT * FROM form_generic WHERE sample_package_id = ?";
	$testType = $db->rawQueryOne($testQuery, [$pResult['package_id']]);


	$testTypeQuery = "SELECT * FROM r_test_types
					WHERE test_status='active' ORDER BY test_standard_name ASC";
	$testTypeResult = $db->rawQuery($testTypeQuery);
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
			<li><a href="/specimen-referral-manifest/view-manifests.php"> Manage Specimen Referral Manifest</a></li>
			<li class="active">Edit Specimen Referral Manifest</li>
		</ol>
	</section>
	<!-- Main content -->
	<section class="content">
		<div class="box box-default">
			<div class="box-header with-border">
				<div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span> <?= _translate("indicates required fields"); ?> &nbsp;</div>
			</div>

				<!-- form start -->
				<form class="form-horizontal" method="post" name="editManifestForm" id="editManifestForm" autocomplete="off" action="/specimen-referral-manifest/edit-manifest-helper.php">
					<div class="box-body">
						<?php
						if ($module == 'generic-tests') { ?>
							<div class="row">
								<div class="col-md-6">
									<div class="form-group">
										<label for="testType" class="col-lg-4 control-label">Test Type</label>
										<div class="col-lg-7" style="margin-left:3%;">
											<select disabled="disabled" class="form-control select2" name="testType" id="testType" title="Please choose test type" style="width:100%;" onchange="getManifestCodeForm(this.value)">
												<option value=""> -- Select -- </option>
												<?php foreach ($testTypeResult as $testTypeInfo) { ?>
													<option value="<?php echo $testTypeInfo['test_type_id'] ?>" data-short="<?php echo $testTypeInfo['test_short_code']; ?>" <?php echo ($testType['test_type'] == $testTypeInfo['test_type_id']) ? "selected='selected'" : ""; ?>><?php echo $testTypeInfo['test_standard_name'] ?></option>
												<?php } ?>
											</select>
										</div>
									</div>
								</div>
							</div>
						<?php } ?>

						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="packageCode" class="col-lg-4 control-label">Manifest Code <span class="mandatory">*</span></label>
									<div class="col-lg-7" style="margin-left:3%;">
										<input type="text" class="form-control isRequired" id="packageCode" name="packageCode" placeholder="Manifest Code" title="Please enter manifest code" readonly value="<?php echo strtoupper((string) $pResult['package_code']); ?>" />
									</div>
								</div>
							</div>

							<div class="col-md-6">
								<div class="form-group">
									<label for="packageCode" class="col-lg-4 control-label">Testing Lab :</label>
									<div class="col-lg-7" style="margin-left:3%;">
										<select class="form-control" id="testingLab" name="testingLab" title="Choose one test lab" <?= !empty($pResult['lab_id']) ? 'readonly="readonly"' : ''; ?>>
											<?= $general->generateSelectOptions($testingLabs, $pResult['lab_id'], '-- Select --'); ?>
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
											<option value="pending" <?php echo ($pResult['package_status'] == 'pending') ? "selected='selected'" : ''; ?>>Pending</option>
											<option value="dispatch" <?php echo ($pResult['package_status'] == 'dispatch') ? "selected='selected'" : ''; ?>>Dispatch</option>
											<option value="received" <?php echo ($pResult['package_status'] == 'received') ? "selected='selected'" : ''; ?>>Received</option>
										</select>
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="operator" class="col-lg-4 control-label">
										<?php echo _translate("Operator/Technician"); ?>
									</label>
									<div class="col-lg-7" style="margin-left:3%;">
										<select class="form-control select2" id="operator" name="operator" title="Choose one Operator/Technician">
											<?= $general->generateSelectOptions($usersList, $pResult['added_by'], '-- Select --'); ?>
										</select>
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="facility" class="col-lg-4 control-label">
										<?php echo _translate("Sample Collection Point"); ?>
									</label>
									<div class="col-lg-7" style="margin-left:3%;">
										<select class="form-control select2" id="facility" name="facility" title="Choose one sample collection point">
											<?= $general->generateSelectOptions($facilities, null, '-- Select --'); ?>
										</select>
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="sampleType" class="col-lg-4 control-label">
										<?php echo _translate("Sample Type"); ?>
									</label>
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
									<label for="daterange" class="col-lg-4 control-label">
										<?php echo _translate("Sample Collection Date Range"); ?>
									</label>
									<div class="col-lg-7" style="margin-left:3%;">
										<input type="text" class="form-control" id="daterange" name="daterange" placeholder="<?php echo _translate('Sample Collection Date Range'); ?>" title="Choose one sample collection date range">
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-12 text-center">
								<div class="form-group">
									<a class="btn btn-primary" href="javascript:void(0);" title="Please select testing lab" onclick="getSamplesForManifest();return false;">Search </a>
									<a href="javascript:void(0);" class="btn btn-default" onclick="clearSelection();">
										Clear</a>
								</div>
							</div>
						</div>

					<br>
					<div class="row">
						<div class="col-md-6">
							<div class="form-group">
								<label for="reasonForChange" class="col-lg-4 control-label">
									<?php echo _translate("Reason for changing manifest"); ?><span class="mandatory">*</span>
								</label>
								<div class="col-lg-7" style="margin-left:3%;">
									<textarea class="form-control isRequired" id="reasonForChange" name="reasonForChange" placeholder="<?php echo _translate('Reason for changing this manifest'); ?>" title="Enter the Reason for changing this manifest"></textarea>
								</div>
							</div>
						</div>
					</div>
					<br>
					<div class="row" id="sampleDetails">

					</div>
					<div class="row" id="alertText" style="font-size:18px;"></div>
			</div>
			</div>
			<!-- /.box-body -->
			<div class="box-footer">
				<input type="hidden" name="selectedSample" id="selectedSample" />
				<input type="hidden" name="packageId" id="packageId" value="<?php echo $pResult['package_id']; ?>" />
				<input type="hidden" class="form-control isRequired" id="module" name="module" placeholder="" title="" readonly value="<?= htmlspecialchars((string) $module); ?>" />
				<a id="packageSubmit" class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;">Submit</a>
				<a href="/specimen-referral-manifest/view-manifests.php" class="btn btn-default"> Cancel</a>
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
		var selVal = [];
		$('#search_to option').each(function(i, selected) {
			selVal[i] = $(selected).val();
		});
		const sqids = new Sqids()
		$("#selectedSample").val(sqids.encode(selVal));
		if (selVal == "") {
			alert("<?= _translate("Please select one or more samples", true); ?>");
			return false;
		}
		flag = deforayValidator.init({
			formId: 'editManifestForm'
		});

		if (flag) {
			$.blockUI();
			document.getElementById('editManifestForm').submit();
		}
	}

	//$("#auditRndNo").multiselect({height: 100,minWidth: 150});
	$(document).ready(function() {
		$('#daterange').val('');
		$('#daterange').daterangepicker({
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
					'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
					'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
					'Last 30 Days': [moment().subtract(29, 'days'), moment()],
					'Last 90 Days': [moment().subtract(89, 'days'), moment()],
					'Last 120 Days': [moment().subtract(119, 'days'), moment()],
					'Last 180 Days': [moment().subtract(179, 'days'), moment()],
					'Last 12 Months': [moment().subtract(12, 'month').startOf('month'), moment().endOf('month')],
					'Previous Year': [moment().subtract(1, 'year').startOf('year'), moment().subtract(1, 'year').endOf('year')],
					'Current Year To Date': [moment().startOf('year'), moment()]
				}
			},
			function(start, end) {
				startDate = start.format('YYYY-MM-DD');
				endDate = end.format('YYYY-MM-DD');
			});

		$("#daterange").val("");
		getSamplesForManifest();

		$(".select2").select2();
		$(".select2").select2({
			tags: true
		});


		$('.search').multiSelect({
			selectableHeader: "<input type='text' class='search-input form-control' autocomplete='off' placeholder='Enter Sample ID'>",
			selectionHeader: "<input type='text' class='search-input form-control' autocomplete='off' placeholder='Enter Sample ID'>",
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
		removeDots = removeDots.replace(/\,/g, "");
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

	function getSamplesForManifest() {
		if ($('#testingLab').val() != '') {
			$.blockUI();

			$.post("/specimen-referral-manifest/get-samples-for-manifest.php", {
					module: $("#module").val(),
					testType: $("#testType").val(),
					testingLab: $('#testingLab').val(),
					facility: $('#facility').val(),
					daterange: $('#daterange').val(),
					sampleType: $('#sampleType').val(),
					pkgId: $('#packageId').val(),
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
			alert("<?= _translate("Please select the Testing Lab", true); ?>");
		}
	}

	function clearSelection() {
		//$('#testingLab').val('').trigger('change');
		getSamplesForManifest();
	}
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';
