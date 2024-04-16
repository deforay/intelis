<?php
use App\Utilities\DateUtility;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\GeoLocationsService;
use App\Services\StorageService;

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

$formId = $general->getGlobalConfig('vl_form');


$tsQuery = "SELECT * FROM r_sample_status";
$tsResult = $db->rawQuery($tsQuery);
$arr = $general->getGlobalConfig();

$sQuery = "SELECT * FROM r_vl_sample_type where status='active'";
$sResult = $db->rawQuery($sQuery);


$healthFacilites = $facilitiesService->getHealthFacilities('vl');
$facilitiesDropdown = $general->generateSelectOptions($healthFacilites, $_POST['facilityName'], "-- Select --");



$state = $geolocationService->getProvinces("yes");
if(isset($_POST['sampleCollectionDate']) && $_POST['sampleCollectionDate']!=""){
    [$startDate, $endDate] = DateUtility::convertDateRange($_POST['sampleCollectionDate'] ?? '');
    [$labStartDate, $labEndDate] = DateUtility::convertDateRange($_POST['sampleReceivedDate'] ?? '');
}
if (!empty($_POST['sampleCollectionDate'])) {
    if (trim((string) $startDate) == trim((string) $endDate)) {
         $sWhere[] = ' DATE(vl.sample_collection_date) =  "' . $startDate . '"';
    } else {
         $sWhere[] = " (DATE(vl.sample_collection_date) BETWEEN '$startDate' AND '$endDate')";
    }
}
else{
	$to = date('Y-m-d', strtotime('today - 30 days'));
	$from = date('Y-m-d');
	$sWhere[] = " (DATE(vl.sample_collection_date) BETWEEN '$from' AND '$to')";

}
if (isset($_POST['sampleReceivedDate']) && trim((string) $_POST['sampleReceivedDate']) != '') {
    if (trim((string) $labStartDate) == trim((string) $labEndDate)) {
         $sWhere[] = ' DATE(vl.sample_received_at_lab_datetime) = "' . $labStartDate . '"';
    } else {
         $sWhere[] = " (DATE(vl.sample_received_at_lab_datetime) BETWEEN '$labStartDate' AND '$labEndDate')";
    }
}

if (isset($_POST['freezerCode']) && trim((string) $_POST['freezerCode']) != '') {
	$sWhere[] = ' h.freezer_id = "' . $_POST['freezerCode'] . '"';
}

if (isset($_POST['district']) && trim((string) $_POST['district']) != '') {
	$sWhere[] = ' f.facility_district_id = "' . $_POST['district'] . '"';
}
if (isset($_POST['state']) && trim((string) $_POST['state']) != '') {
	$sWhere[] = ' f.facility_state_id = "' . $_POST['state'] . '"';
}

if (isset($_POST['facilityName']) && trim((string) $_POST['facilityName']) != '') {
	$sWhere[] = ' f.facility_id IN (' . implode(',',$_POST['facilityName']) . ')';
}

if(isset($sWhere) && !empty($sWhere)){
	$sWhere = ' where '. implode(" AND ", $sWhere);
}

$vlQuery = "SELECT vl.*,f.facility_name , s.storage_code, h.* FROM form_vl as vl 
            LEFT JOIN lab_storage_history as h ON h.sample_unique_id = vl.unique_id 
			LEFT JOIN lab_storage as s ON s.storage_id = h.freezer_id 
            LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id ";

$vlQuery = $vlQuery . $sWhere;

//echo $vlQuery;  die;
$_SESSION['sampleStorageQuery'] = $vlQuery;
$vlQueryInfo = $db->rawQuery($vlQuery);

$storageInfo = $storageService->getLabStorage();
$uniqueId = array();
foreach($vlQueryInfo as $info)
{
	$uniqueId[] = "'".$info['unique_id']."'";
}
$sampleUniqueId = implode(',',$uniqueId);
if(!empty($sampleUniqueId)){
	$getCurrentStorage = "SELECT sh.*,s.storage_code,s.storage_id FROM lab_storage_history as sh LEFT JOIN lab_storage as s ON s.storage_id=sh.freezer_id WHERE sh.sample_unique_id IN ($sampleUniqueId) ";
	$currentStorage = $db->rawQuery($getCurrentStorage);
}
//echo '<pre>'; print_r($currentStorage); die;
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
		<h1><em class="fa-solid fa-plus"></em>
			<?php echo _translate("Add Samples To Storage"); ?>
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
					<table aria-describedby="table" class="table" aria-hidden="true" style="margin-left:1%;margin-top:20px;width:98%;">
						<tr>
							<td><strong>
									<?php echo _translate("Freezer"); ?>&nbsp;:
								</strong>
							</td>
							<td>
								<select type="text" name="freezerCode" id="freezerCode" class="form-control freezerSelect" style="width:250px;">
									<?= $general->generateSelectOptions($storageInfo, $_POST['freezerCode'], '-- Select --') ?>
                                </select>
							</td>
							<td><strong>
									<?php echo _translate("Sample Collection Date"); ?>&nbsp;:
								</strong>
							</td>
							<td>
								<input type="text" id="sampleCollectionDate" name="sampleCollectionDate" class="form-control daterangefield" placeholder="<?php echo _translate('Select Collection Date'); ?>" style="width:220px;background:#fff;" value="<?php if(isset($_POST['sampleCollectionDate']) && $_POST['sampleCollectionDate']!="") echo str_replace('+',' ',$_POST['sampleCollectionDate']); ?>" />
							</td>
							<td><strong>
									<?php echo _translate("Sample Received at Lab Date"); ?>&nbsp;:
								</strong></td>
							<td>
								<input type="text" id="sampleReceivedDate" name="sampleReceivedDate" class="form-control daterangefield" placeholder="<?php echo _translate('Select Received Date'); ?>" style="width:220px;background:#fff;" value="<?php //if(isset($_POST['sampleReceivedDate']) && $_POST['sampleReceivedDate']!="") echo str_replace('+',' ',$_POST['sampleReceivedDate']); ?>" />
							</td>
                           
						</tr>
						<tr>
						<td><strong>
									<?php echo _translate("Province/State"); ?> :
								</strong></td>
							
							<td>
								<select class="form-control select2-element" id="state" onchange="getByProvince(this.value)" name="state" title="<?php echo _translate('Please select Province/State'); ?>">
									<?= $general->generateSelectOptions($state, $_POST['state'], _translate("-- Select --")); ?>
								</select>
							</td>
							<td><strong>
									<?php echo _translate("District/County"); ?> :
								</strong></td>
							<td>
								<select class="form-control select2-element" id="district" name="district" title="<?php echo _translate('Please select Province/State'); ?>" onchange="getByDistrict(this.value)">
								</select>
							</td>
							<td><strong>
									<?php echo _translate("Facility Name"); ?> :
								</strong></td>
							<td>
								<select class="form-control" id="facilityName" name="facilityName[]" title="<?php echo _translate('Please select facility name'); ?>" multiple="multiple" style="width:220px;">
								<?= $facilitiesDropdown; ?>
								</select>
							</td>
						</tr>
						<tr>
							<td colspan="6">
								&nbsp;<button onclick="searchVlSampleData();" value="Search" class="btn btn-primary btn-sm"><span>
										<?php echo _translate("Get Samples"); ?>
									</span></button>

								&nbsp;<button class="btn btn-danger btn-sm" onclick="document.location.href = document.location" type="reset"><span>
										<?php echo _translate("Clear Search"); ?>
									</span></button>

									&nbsp;<button class="btn btn-success btn-sm" style="margin-right:5px;" href="javascript:void(0);" onclick="exportStorageSamples();"><em class="fa-solid fa-file-excel"></em>&nbsp;&nbsp;
									<?php echo _translate("Export Excel"); ?></button>

									<?php
								if (_isAllowed("/vl/requests/vl-requests.php") && $formId == COUNTRY\DRC) { ?>
									<a href="/vl/requests/upload-storage.php" class="btn btn-primary btn-sm"> <em class="fa-solid fa-plus"></em>
										<?php echo _translate("Storage Bulk Upload"); ?>
									</a>
								<?php }
								?>
							</td>
						</tr>
					</table>
				</form>
					<!-- /.box-header -->
					<div class="box-body">
					<form name="sampleStorageForm" id="sampleStorageForm" method="post" action="sample-storage-helper.php">
						<table aria-describedby="table" id="vlRequestDataTable" class="table table-bordered table-striped" aria-hidden="true">
							<thead>
								<tr>
									<th>
										<?php echo _translate("Sample Code"); ?>
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
									<th>
										<?php echo _translate("Patient's Name"); ?>
									</th>
                                    <th>
										<?php echo _translate("Current Storage"); ?>
									</th>
                                    <th>
										<?php echo _translate("Volume(ml)"); ?>
									</th>
                                    <th>
										<?php echo _translate("Freezer"); ?>
									</th>
                                    <th>
										<?php echo _translate("Rack"); ?>
									</th>
                                    <th>
										<?php echo _translate("Box"); ?>
									</th>
                                    <th>
										<?php echo _translate("Position"); ?>
									</th>
                                    <th>
										<?php echo _translate("Date out"); ?>
									</th>
                                    <th>
										<?php echo _translate("Comments"); ?>
									</th>
									<th>
										<?php echo _translate("Status"); ?>
									</th>
									<th>
										<?php echo _translate("Action"); ?>
									</th>
								</tr>
							</thead>
							<tbody>
                                <?php 
								$i=0;
								if(!empty($vlQueryInfo)){
								foreach($vlQueryInfo as $vl) { 
                                    $patientFirstName = $vl['patient_first_name'] ?? '';
                                    $patientMiddleName = $vl['patient_middle_name'] ?? '';
                                    $patientLastName = $vl['patient_last_name'] ?? '';
                                    if (!empty($arr['display_encrypt_pii_option']) && $arr['display_encrypt_pii_option'] == "yes" && !empty($vlQueryInfo['is_encrypted']) && $vlQueryInfo['is_encrypted'] == 'yes') {
                                        $key = (string) $general->getGlobalConfig('key');
                                        $vl['patient_art_no'] = $general->crypto('decrypt', $vl['patient_art_no'], $key);
                                        if ($patientFirstName != '') {
                                             $vl['patient_first_name'] = $patientFirstName = $general->crypto('decrypt', $patientFirstName, $key);
                                        }
                                   
                                        if ($patientMiddleName != '') {
                                             $patientMiddleName = $general->crypto('decrypt', $patientMiddleName, $key);
                                        }
                                   
                                        if ($patientLastName != '') {
                                             $vl['patient_last_name']  = $patientLastName = $general->crypto('decrypt', $patientLastName, $key);
                                        }
                                        $patientFullName = $patientFirstName . " " . $patientMiddleName . " " . $patientLastName;
                                   } else {
                                        $patientFullName = trim($patientFirstName ?? ' ' . $patientMiddleName ?? ' ' . $patientLastName ?? '');
                                   }

								   if(is_array($currentStorage[$i]) && in_array($vl['unique_id'],$currentStorage[$i]) && ($currentStorage[$i]['freezer_id']!=""))
								   {
										$existingStorage = $currentStorage[$i]['storage_code'].'-'.$currentStorage[$i]['rack'].'-'.$currentStorage[$i]['box'].'-'.$currentStorage[$i]['position'].' '.$currentStorage[$i]['volume'].' ml';
								   }
								   else{
										$existingStorage = "";
								   }
                                   
                                    ?>
								<tr>
									<td class="dataTables_empty">
										<?php echo $vl['sample_code']; ?>
										<input type="hidden" name="sampleUniqueId[<?= $i; ?>]" id="sampleUniqueId<?= $i; ?>" class="form-control" value="<?php echo $vl['unique_id']; ?>" size="5"/>
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
										<?php echo $patientFullName; ?>
									</td>
                                    <td class="dataTables_empty">
										<input type="hidden" name="storageId[<?= $i; ?>]" id="storageId<?= $i; ?>" class="form-control" value="<?= $currentStorage[$i]['storage_id']; ?>" size="5"/>
										<?php echo $existingStorage; ?>
									</td>
                                    <td class="dataTables_empty">
										<input type="text" name="volume[<?= $i; ?>]" id="volume<?= $i; ?>" class="form-control" size="5"/>
									</td>
                                    <td class="dataTables_empty">
                                    <select type="text" name="freezer[<?= $i; ?>]" id="freezer<?= $i; ?>" class="form-control freezerSelect" onchange="showSamples(<?= $i; ?>);" style="width:70px;">
										<?= $general->generateSelectOptions($storageInfo, null, '-- Select --') ?>
                                	</select>
									</td>
                                    <td class="dataTables_empty">
                                    	<input type="text" name="rack[<?= $i; ?>]" id="rack<?= $i; ?>" class="form-control" size="5"/>
									</td>
                                    <td class="dataTables_empty">
                                    	<input type="text" name="box[<?= $i; ?>]" id="box<?= $i; ?>" class="form-control" size="5"/>
									</td>
                                    <td class="dataTables_empty">
                                    	<input type="text" name="position[<?= $i; ?>]" id="position<?= $i; ?>" class="form-control" size="5"/>
									</td>
                                    <td class="dataTables_empty">
                                    	<input type="text" name="dateOut[<?= $i; ?>]" id="dateOut<?= $i; ?>" class="form-control date" size="5"/>
									</td>
                                    <td class="dataTables_empty">
                                    	<input type="text" name="comments[<?= $i; ?>]" id="comments<?= $i; ?>" class="form-control" size="5"/>
									</td>
									<td class="dataTables_empty">
									<?php echo ucfirst($vl['sample_status']); ?>
									</td>
									<td class="dataTables_empty">
                                    	<a href="#" class="btn btn-danger btn-xs" onclick="removeSample(<?= $i; ?>);"><em class="fa-solid fa-xmark"></em>&nbsp;  Remove</a>
									</td>
								</tr>
                                <?php $i++; } } else{  ?>
									<tr>
										<th colspan="13" style="text-align: center"> -- No samples found -- </th>
									</tr>
									<?php } ?>
							</tbody>
						</table>
					</form>
						<div class="box-footer">
                        <button id="storageSubmit" class="btn btn-primary" href="javascript:void(0);" title="<?php echo _translate('Please select machine'); ?>" onclick="validateNow();return false;"><?php echo _translate("Save"); ?></button>
                        <a href="sample-storage.php" class="btn btn-default"> <?php echo _translate("Cancel"); ?></a>
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
	$(document).ready(function() {
		
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
			function(start, end) {
				startDate = start.format('YYYY-MM-DD');
				endDate = end.format('YYYY-MM-DD');
			});
	$("#sampleReceivedDate").val("");
		$('.daterangefield').on('cancel.daterangepicker', function(ev, picker) {
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

	function searchVlSampleData(){
		flag = deforayValidator.init({
            formId: 'searchSample'
        });
        if (flag) {
            $.blockUI();
            document.getElementById('searchSample').submit();
        }
	}


	function getByProvince(provinceId) {
		$("#district").html('');
		$("#facilityName").html('');
		$.post("/common/get-by-province-id.php", {
				provinceId: provinceId,
				districts: true,
				facilities: true,
			},
			function(data) {
				Obj = $.parseJSON(data);
				$("#district").html(Obj['districts']);
				$("#facilityName").html(Obj['facilities']);
			});
	}

	function getByDistrict(districtId) {
		$("#facilityName").html('');
		$("#vlLab").html('');
		$.post("/common/get-by-district-id.php", {
				districtId: districtId,
				facilities: true,
				labs: true
			},
			function(data) {
				Obj = $.parseJSON(data);
				$("#facilityName").html(Obj['facilities']);
			});
	}

	function removeSample(rowId){
		storageId = $("#storageId"+rowId).val();
		sampleUniqueId = $("#sampleUniqueId"+rowId).val();
		$.post("/vl/requests/update-sample-storage-status.php", {
				storageId: storageId,
				uniqueId: sampleUniqueId,
				status: 'removed'
			},
			function(data) {
				if(data!='')
					alert("Sample is removed from this freezer");
			});
	}

	function exportStorageSamples() {
		
		$.blockUI();
		$.post("/vl/requests/export-sample-storage.php", 
			function(data) {
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
