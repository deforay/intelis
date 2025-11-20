<?php

use App\Utilities\DateUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\StorageService;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Services\GeoLocationsService;

// Sanitized values from $request object

/** @var Psr\Http\Message\ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$title = _translate("Export Data");

require_once APPLICATION_PATH . '/header.php';

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

/** @var GeoLocationsService $geolocationService */
$geolocationService = ContainerRegistry::get(GeoLocationsService::class);

/** @var StorageService $storageService */
$storageService = ContainerRegistry::get(StorageService::class);

$formId = (int) $general->getGlobalConfig('vl_form');


$tsQuery = "SELECT * FROM r_sample_status";
$tsResult = $db->rawQuery($tsQuery);
$arr = $general->getGlobalConfig();

$sQuery = "SELECT * FROM r_vl_sample_type where status='active'";
$sResult = $db->rawQuery($sQuery);


$healthFacilites = $facilitiesService->getHealthFacilities('vl');
$facilitiesDropdown = $general->generateSelectOptions($healthFacilites, $_POST['facilityName'], "-- Select --");

$sRemoveQuery = "SELECT * FROM r_reasons_for_sample_removal where removal_reason_status='active'";
$sRemoveResult = $db->rawQuery($sRemoveQuery);

$state = $geolocationService->getProvinces("yes");

if (isset($_POST['sampleCollectionDate']) && $_POST['sampleCollectionDate'] != "") {
	[$startDate, $endDate] = DateUtility::convertDateRange($_POST['sampleCollectionDate'] ?? '');
	[$labStartDate, $labEndDate] = DateUtility::convertDateRange($_POST['sampleReceivedDate'] ?? '');
}
if (!empty($_POST['sampleCollectionDate'])) {
	$sWhere[] = " (DATE(vl.sample_collection_date) BETWEEN '$startDate' AND '$endDate')";
} else {
	$from = date('Y-m-d', strtotime('today - 30 days'));
	$to = date('Y-m-d');
	$sWhere[] = " (DATE(vl.sample_collection_date) BETWEEN '$from' AND '$to')";
}
if (isset($_POST['sampleReceivedDate']) && trim((string) $_POST['sampleReceivedDate']) !== '') {
	$sWhere[] = " (DATE(vl.sample_received_at_lab_datetime) BETWEEN '$labStartDate' AND '$labEndDate')";
}

if (isset($_POST['freezerCode']) && trim((string) $_POST['freezerCode']) !== '') {
	$sWhere[] = ' h.freezer_id = "' . $_POST['freezerCode'] . '"';
}

if ($general->isLISInstance() && $arr['vl_lab_id'] != '') {
	$sWhere[] = ' s.lab_id = "' . $arr['vl_lab_id'] . '"';
} elseif (isset($_POST['labId']) && trim((string) $_POST['labId']) !== '') {
	$sWhere[] = ' s.lab_id = "' . $_POST['labId'] . '"';
}

if (isset($_POST['district']) && trim((string) $_POST['district']) !== '') {
	$sWhere[] = ' f.facility_district_id = "' . $_POST['district'] . '"';
}
if (isset($_POST['state']) && trim((string) $_POST['state']) !== '') {
	$sWhere[] = ' f.facility_state_id = "' . $_POST['state'] . '"';
}

if (isset($_POST['facilityName']) && trim((string) $_POST['facilityName']) !== '') {
	$sWhere[] = ' f.facility_id IN (' . implode(',', $_POST['facilityName']) . ')';
}

$sWhere = isset($sWhere) && !empty($sWhere) ? ' AND ' . implode(" AND ", $sWhere) : "";
$vlQuery = "SELECT vl.*,f.facility_name,s.storage_code,h.*,r.removal_reason_name FROM form_vl as vl
            LEFT JOIN lab_storage_history as h ON h.sample_unique_id = vl.unique_id
			LEFT JOIN lab_storage as s ON s.storage_id = h.freezer_id
			LEFT JOIN r_reasons_for_sample_removal as r ON r.removal_reason_id=h.sample_removal_reason
            LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id WHERE vl.sample_code IS NOT NULL ";

$vlQuery = "$vlQuery $sWhere ORDER BY h.history_id";

//echo $vlQuery;  die;
$_SESSION['sampleStorageQuery'] = $vlQuery;
$vlQueryInfo = $db->rawQuery($vlQuery);

$storageInfo = $storageService->getLabStorage();
$uniqueId = [];
foreach ($vlQueryInfo as $info) {
	$uniqueId[] = "'" . $info['unique_id'] . "'";
}

$sampleUniqueId = implode(',', $uniqueId);
$currentStorage = "";
if ($sampleUniqueId !== '' && $sampleUniqueId !== '0') {
	$getCurrentStorage = "SELECT sh.*,s.storage_code,s.storage_id FROM lab_storage_history as sh
	LEFT JOIN lab_storage as s ON s.storage_id=sh.freezer_id WHERE sh.sample_unique_id IN ($sampleUniqueId) ";
	$currentStorage = $db->rawQuery($getCurrentStorage);
}

$testingLabs = $facilitiesService->getTestingLabs('vl');
?>
<style>
	.select2-selection__choice {
		color: black !important;
	}

	.select2-selection--multiple {
		max-height: 100px;
		width: auto;
		overflow-y: scroll !important;
	}
</style>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><em class="fa-solid fa-jar"></em>
			<?php echo _translate("Samples Storage"); ?>
			<!--<ol class="breadcrumb">-->
			<!--  <li><a href="/"><em class="fa-solid fa-chart-pie"></em> Home</a></li>-->
			<!--  <li class="active">Export Result</li>-->
			<!--</ol>-->

		</h1>
	</section>
	<!-- Main content -->
	<section class="content">
		<div class="row">
			<div class="col-xs-12">
				<div class="box" id="filterDiv">
					<form name="searchSample" id="searchSample" method="post" action="sample-storage.php">
						<table aria-describedby="table" class="table" aria-hidden="true"
							style="margin-left:1%;margin-top:20px;width:98%;">
							<tr>
								<td class="labField"><strong>
										<?php echo _translate("Nom du laboratoire"); ?>&nbsp;:
									</strong>
								</td>
								<td class="labField">
									<select name="labId" id="labId" class="form-control labSelect" style="width:220px;"
										onchange="getFreezers(this.value, 'freezerCode');">
										<?php if ($general->isLISInstance()) {
											echo $general->generateSelectOptions($testingLabs, $arr['vl_lab_id'], '-- Select --');
										} else {
											echo $general->generateSelectOptions($testingLabs, null, '-- Select --');
										} ?>
									</select>
								</td>
								<td><strong>
										<?php echo _translate("Freezer"); ?>&nbsp;:
									</strong>
								</td>
								<td>
									<select name="freezerCode" id="freezerCode" class="form-control freezerSelect"
										style="width:220px;">
										<!-- < ?= $general->generateSelectOptions($storageInfo, $_POST['freezerCode'], '-- Select --') ?> -->
									</select>
								</td>
								<td><strong>
										<?php echo _translate("Sample Collection Date"); ?>&nbsp;:
									</strong>
								</td>
								<td>
									<input type="text" id="sampleCollectionDate" name="sampleCollectionDate"
										class="form-control daterangefield"
										placeholder="<?php echo _translate('Select Collection Date'); ?>"
										style="width:220px;background:#fff;" value="<?php if (isset($_POST['sampleCollectionDate']) && $_POST['sampleCollectionDate'] != "") {
											echo str_replace('+', ' ', $_POST['sampleCollectionDate']);
										} ?>" style="width:220px;" />
								</td>
							</tr>
							<tr>
								<td><strong>
										<?php echo _translate("Sample Received at Lab Date"); ?>&nbsp;:
									</strong></td>
								<td>
									<input type="text" id="sampleReceivedDate" name="sampleReceivedDate"
										class="form-control daterangefield"
										placeholder="<?php echo _translate('Select Received Date'); ?>"
										style="width:220px;background:#fff;" value="<?php //if(isset($_POST['sampleReceivedDate']) && $_POST['sampleReceivedDate']!="") echo str_replace('+',' ',$_POST['sampleReceivedDate']);
										?>" />
								</td>
								<td><strong>
										<?php echo _translate("Province/State"); ?> :
									</strong></td>
								<td>
									<select class="form-control select2-element" id="state"
										onchange="getByProvince(this.value)" name="state"
										title="<?php echo _translate('Please select Province/State'); ?>"
										style="width:220px;">
										<?= $general->generateSelectOptions($state, $_POST['state'], _translate("-- Select --")); ?>
									</select>
								</td>
								<td><strong>
										<?php echo _translate("District/County"); ?> :
									</strong></td>
								<td>
									<select class="form-control select2-element" id="district" name="district"
										title="<?php echo _translate('Please select District/County'); ?>"
										onchange="getByDistrict(this.value)">
									</select>
								</td>
							</tr>
							<tr>
								<td><strong>
										<?php echo _translate("Facility Name"); ?> :
									</strong></td>
								<td>
									<select class="form-control" id="facilityName" name="facilityName[]"
										title="<?php echo _translate('Please select facility name'); ?>"
										multiple="multiple" style="width:220px;">
										<?= $facilitiesDropdown; ?>
									</select>
								</td>
							</tr>
							<tr>
								<td colspan="6">
									&nbsp;<button onclick="searchVlSampleData();" value="Search"
										class="btn btn-primary btn-sm"><span>
											<?php echo _translate("Get Samples"); ?>
										</span></button>

									&nbsp;<button class="btn btn-danger btn-sm"
										onclick="document.location.href = document.location" type="reset"><span>
											<?php echo _translate("Clear Search"); ?>
										</span></button>

									&nbsp;<button class="btn btn-success btn-sm" style="margin-right:5px;"
										href="javascript:void(0);" onclick="exportStorageSamples();"><em
											class="fa-solid fa-file-excel"></em>&nbsp;&nbsp;
										<?php echo _translate("Export Excel"); ?></button>

									<?php
									if (_isAllowed("/vl/requests/vl-requests.php") && $formId == COUNTRY\DRC) { ?>
										<a href="/vl/requests/upload-storage.php" class="btn btn-primary btn-sm pull-right">
											<em class="fa-solid fa-plus"></em>
											<?php echo _translate("Storage Bulk Upload"); ?>
										</a>
									<?php } ?>
								</td>
							</tr>
						</table>
					</form>
					<!-- /.box-header -->
					<div class="box-body">
						<form name="sampleStorageForm" id="sampleStorageForm" method="post"
							action="sample-storage-helper.php">
							<table aria-describedby="table" id="vlRequestDataTable"
								class="table table-bordered table-striped" aria-hidden="true">
								<thead>
									<tr>
										<th>
											<?php echo _translate("Sample ID"); ?>
										</th>
										<th scope="row">
											<?php echo _translate("Facility Name"); ?>
										</th>
										<th scope="row">
											<?php echo _translate("Sample Collection Date"); ?>
										</th>
										<th>
											<?php echo _translate("Patient ID"); ?>
										</th>
										<th width="5%">
											<?php echo _translate("Current Storage"); ?>
										</th>
										<th width="3%">
											<?php echo _translate("Volume(ml)"); ?>
										</th>
										<th width="10%" class="labCol">
											<?php echo _translate("Lab"); ?>
										</th>
										<th width="10%">
											<?php echo _translate("Freezer"); ?>
										</th>
										<th width="5%">
											<?php echo _translate("Rack"); ?>
										</th>
										<th width="5%">
											<?php echo _translate("Box"); ?>
										</th>
										<th width="5%">
											<?php echo _translate("Position"); ?>
										</th>
										<th width="10%">
											<?php echo _translate("Date out"); ?>
										</th>
										<th>
											<?php echo _translate("Comments"); ?>
										</th>
										<!--<th>
											< ?php echo _translate("Status"); ?>
										</th>-->
										<th>
											<?php echo _translate("Action"); ?>
										</th>
									</tr>
								</thead>
								<tbody>
									<?php
									$i = 0;
									if (!empty($vlQueryInfo)) {
										foreach ($vlQueryInfo as $vl) {
											if (!empty($arr['display_encrypt_pii_option']) && $arr['display_encrypt_pii_option'] == "yes" && !empty($vlQueryInfo['is_encrypted']) && $vlQueryInfo['is_encrypted'] == 'yes') {
												$key = (string) $general->getGlobalConfig('key');
												$vl['patient_art_no'] = $general->crypto('decrypt', $vl['patient_art_no'], $key);
											} ?>
											<tr>
												<td class="dataTables_empty">
													<?php echo $vl['sample_code'];

													?>
													<input type="hidden" name="sampleUniqueId[<?= $i; ?>]"
														id="sampleUniqueId<?= $i; ?>" class="form-control"
														value="<?php echo $vl['unique_id']; ?>" size="5" />
												</td>
												<td class="dataTables_empty">
													<?php echo $vl['facility_name']; ?>
												</td>
												<td class="dataTables_empty">
													<?php echo DateUtility::humanReadableDateFormat($vl['sample_collection_date'] ?? ''); ?>
												</td>
												<td class="dataTables_empty">
													<?php echo $vl['patient_art_no']; ?>
												</td>
												<td class="dataTables_empty">
													<input type="hidden" name="storageId[<?= $i; ?>]" id="storageId<?= $i; ?>"
														class="form-control" value="<?= $currentStorage[$i]['storage_id']; ?>"
														size="5" />
													<input type="hidden" name="historyId[<?= $i; ?>]" id="historyId<?= $i; ?>"
														class="form-control" value="<?= $currentStorage[$i]['history_id']; ?>"
														size="5" />

													<span id="currentStorage<?= $i; ?>"><?php echo $existingStorage; ?></span>
												</td>
												<td class="dataTables_empty">
													<input type="text" name="volume[<?= $i; ?>]" id="volume<?= $i; ?>"
														class="form-control" size="2" />
												</td>
												<td class="dataTables_empty labCol">
													<select type="text" name="lab[<?= $i; ?>]" id="lab[<?= $i; ?>]"
														class="form-control labSelect" style="width:90px;"
														onchange="getFreezers(this.value, 'freezer<?= $i; ?>');">
														<?php if ($general->isLISInstance()) {
															echo $general->generateSelectOptions($testingLabs, $arr['vl_lab_id'], '-- Select --');
														} else {
															echo $general->generateSelectOptions($testingLabs, null, '-- Select --');
														} ?>
													</select>
												</td>
												<td class="dataTables_empty">
													<select type="text" name="freezer[<?= $i; ?>]" id="freezer<?= $i; ?>"
														class="form-control freezerSelect" style="width:90px;">
														<!-- < ?= $general->generateSelectOptions($storageInfo, null, '-- Select --') ?> -->
													</select>
												</td>
												<td class="dataTables_empty">
													<input type="text" name="rack[<?= $i; ?>]" id="rack<?= $i; ?>"
														class="form-control" size="5" />
												</td>
												<td class="dataTables_empty">
													<input type="text" name="box[<?= $i; ?>]" id="box<?= $i; ?>"
														class="form-control" size="5" />
												</td>
												<td class="dataTables_empty">
													<input type="text" name="position[<?= $i; ?>]" id="position<?= $i; ?>"
														class="form-control" size="5" />
												</td>
												<td class="dataTables_empty">
													<input type="text" name="dateOut[<?= $i; ?>]" id="dateOut<?= $i; ?>"
														class="form-control date" size="23" />
												</td>
												<td class="dataTables_empty">
													<input type="text" name="comments[<?= $i; ?>]" id="comments<?= $i; ?>"
														class="form-control" size="20" />
												</td>
												<!--	<td class="dataTables_empty">
													< ?php echo ucfirst($vl['sample_status']); ?>
												</td>-->
												<td class="dataTables_empty">
													<?php if ($existingStorage != "" && (strtolower((string) $vl['sample_status']) !== "removed")) {
														?>
														<a href="#" class="btn btn-danger btn-xs"
															onclick="showRemovalReason(<?= $i; ?>);"><em
																class="fa-solid fa-xmark"></em>&nbsp; Remove</a>
														<select id="sampleRemovalReason<?= $i;
														?>" name="sampleRemovalReason[<?= $i; ?>]" class="form-control"
															title="Please Enter Sample Removal Reason" onchange="removeSampleFromFreezer(this.value,<?= $i;
															?>);" style="width:100%; display:none;">
															<option value=""><?= _translate("-- Select --");
															?> </option>
															<?php
															foreach ($sRemoveResult as $reason) { ?>
																<option value="<?php echo $reason['removal_reason_id']; ?>">
																	<?php echo ($reason['removal_reason_name']); ?></option>
															<?php }
															?>
															<option value="other">Other</option>
														</select>
														<input type="text" class="form-control"
															name="newSampleRemovalReason[<?= $i; ?>]" id="newSampleRemovalReason<?= $i;
															  ?>" onchange="removeSampleFromFreezer(this.value,<?= $i;
															  ?>);" style="display:none;" />
														<?php
													} elseif ($vl['removal_reason_name'] != "") {
														echo "Sample Removed Reason : " . $vl['removal_reason_name'];
													} ?>
												</td>
											</tr>
											<?php $i++;
										}
									} else { ?>
										<tr>
											<th colspan="13" style="text-align: center"> -- No samples found -- </th>
										</tr>
									<?php } ?>
								</tbody>
							</table>
						</form>
						<div class="box-footer">
							<button id="storageSubmit" class="btn btn-primary" href="javascript:void(0);"
								title="<?php echo _translate('Please select machine'); ?>"
								onclick="validateNow();return false;"><?php echo _translate("Save"); ?></button>
							<a href="sample-storage.php" class="btn btn-default">
								<?php echo _translate("Cancel"); ?></a>
						</div>

					</div>
					<!-- /.box-body -->
				</div>
				<!-- /.box -->
			</div>
			<!-- /.col -->
		</div>
		<!-- /.row -->
	</section>
	<!-- /.content -->
</div>
<script src="/assets/js/moment.min.js"></script>
<script type="text/javascript" src="/assets/plugins/daterangepicker/daterangepicker.js"></script>
<script type="text/javascript">
	let searchExecuted = false;
	var startDate = "";
	var endDate = "";
	var selectedTests = [];
	var selectedTestsId = [];
	$(document).ready(function () {
		<?php if (isset($_POST['district'])) { ?>
			getByProvince($("#state").val());
		<?php } ?>

		$("#state").select2({
			placeholder: "<?php echo _translate("Select Province"); ?>"
		});
		$("#district").select2({
			placeholder: "<?php echo _translate("Select District"); ?>"
		});
		$("#facilityName").select2({
			placeholder: "<?php echo _translate("Select Facilities"); ?>"
		});
		$(".freezerSelect").select2({
			placeholder: "<?php echo _translate("Select Freezer"); ?>"
		});
		$("#labId").select2({
			placeholder: "<?php echo _translate("Select Lab"); ?>"
		});

		var currentLabId = $('#labId').val();
		<?php if ($general->isLISInstance()) { ?>
			if (currentLabId != '') {
				getFreezers(currentLabId, 'freezerCode'); // Load the freezers for the current lab
				$('.labField').hide();
			}

			// Loop through all form lab dropdowns
			$('[id^="lab["]').each(function () {
				var id = $(this).attr('id');
				var index = id.match(/\d+/)[0]; // Extract the index from the id
				var freezerId = 'freezer' + index;

				if ($(this).val() != '') {
					getFreezers($(this).val(), freezerId);
					$('.labCol').hide();
				}
			});
		<?php } ?>



		$('.daterangefield').daterangepicker({
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
				'This Month': [moment().startOf('month'), moment().endOf('month')],
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

			function (start, end) {
				startDate = start.format('YYYY-MM-DD');
				endDate = end.format('YYYY-MM-DD');
			});
		$("#sampleReceivedDate").val("");
		$('.daterangefield').on('cancel.daterangepicker', function (ev, picker) {
			$(this).val('');
		});

	});

	function validateNow() {

		flag = deforayValidator.init({
			formId: 'sampleStorageForm'
		});
		if (flag) {
			$.blockUI();
			document.getElementById('sampleStorageForm').submit();
		}
	}

	function searchVlSampleData() {
		flag = deforayValidator.init({
			formId: 'searchSample'
		});
		if (flag) {
			$.blockUI();
			document.getElementById('searchSample').submit();
		}
	}

	function getFreezers(labId, freezerSelectId) {
		$.blockUI();
		$.post("/vl/program-management/get-freezer-list-by-lab.php", {
			labId: labId,
		},
			function (data) {
				$.unblockUI();
				$("#" + freezerSelectId).html(data);
			});
	}

	const presetDistrict = <?= json_encode($_POST['district'] ?? '') ?>;

	function getByProvince(provinceId) {
		$("#district").empty();
		$.post("/common/get-by-province-id.php", {
			provinceId: provinceId,
			districts: presetDistrict
		},
			function (data) {
				const Obj = $.parseJSON(data);
				$("#district").html(Obj.districts);
			}
		);
	}


	function getByDistrict(districtId) {
		$("#facilityName").html('');
		$("#vlLab").html('');
		$.post("/common/get-by-district-id.php", {
			districtId: districtId,
			facilities: true,
			labs: true
		},
			function (data) {
				Obj = $.parseJSON(data);
				$("#facilityName").html(Obj['facilities']);
			});
	}

	function showRemovalReason(rowId) {
		$("#sampleRemovalReason" + rowId).show();
	}

	function removeSample(value, rowId) {
		if (value == "other") {
			$("#newSampleRemovalReason" + rowId).show();
		} else {
			removeSampleFromFreezer(value, rowId);
		}
	}

	function removeSampleFromFreezer(reason, rowId) {
		if ($("#volume" + rowId).val() > 0) {
			if ($("#freezer" + rowId).val() == "") {
				$("#freezer" + rowId).addClass('isRequired');
				alert("please select freezer to move");
				return false;
			}
		}
		$.post("/vl/requests/update-sample-storage-status.php", {
			freezerId: $("#freezer" + rowId).val(),
			uniqueId: $("#sampleUniqueId" + rowId).val(),
			rack: $("#rack" + rowId).val(),
			box: $("#box" + rowId).val(),
			position: $("#position" + rowId).val(),
			volume: $("#volume" + rowId).val(),
			dateOut: $("#dateOut" + rowId).val(),
			comments: $("#comments" + rowId).val(),
			removalReason: reason,
			currentStorage: $("#currentStorage" + rowId).html(),
			historyId: $("#historyId" + rowId).val()
		},
			function (data) {
				if (data != '')
					alert("Sample is removed from this freezer");
				location.reload();
			});
	}

	function exportStorageSamples() {

		$.blockUI();
		$.post("/vl/requests/export-sample-storage.php",
			function (data) {
				$.unblockUI();
				if (data === "" || data === null || data === undefined) {
					alert("<?php echo _translate("Unable to generate the excel file"); ?>");
				} else {
					window.open('/download.php?d=a&f=' + data, '_blank');
				}
			});
	}
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';
