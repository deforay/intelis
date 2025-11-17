<?php

use App\Utilities\JsonUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use App\Services\GeoLocationsService;

$title = _translate("Geographical Divisions");

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var GeoLocationsService $geoLocationService */
$geoLocationService = ContainerRegistry::get(GeoLocationsService::class);

$provinceRecords = $geoLocationService->fetchActiveGeolocations(0, 0, "no", false, orderByStatus: true);
$districtRecords = $geoLocationService->fetchActiveGeolocations(0, 'all', "no", false, orderByStatus: true);
$identifiedOrphanDistricts = [];
if (!empty($districtRecords)) {
	$districtIds = array_column($districtRecords, 'geo_id');
	if (!empty($districtIds)) {
		/** @var DatabaseService $db */
		$db = ContainerRegistry::get(DatabaseService::class);
		$placeholders = implode(',', array_fill(0, count($districtIds), '?'));
		$orphanDistrictQuery = "SELECT d.geo_id
				FROM geographical_divisions d
				LEFT JOIN geographical_divisions p ON p.geo_id = CAST(NULLIF(d.geo_parent, '') AS UNSIGNED)
				WHERE d.geo_id IN ($placeholders)
				AND d.geo_parent != 0
				AND (p.geo_id IS NULL OR p.geo_status != 'active')";
		$orphanRows = $db->rawQuery($orphanDistrictQuery, $districtIds);
		$identifiedOrphanDistricts = array_map(fn($row) => (int) $row['geo_id'], $orphanRows);
	}
}

$provinceList = [];
foreach ($provinceRecords as $province) {
	$statusLabel = $province['geo_status'] ?? 'active';
	$provinceList[$province['geo_id']] = sprintf("%s (%s)", $province['geo_name'], _translate(ucwords($statusLabel)));
}

$districtList = [];
$districtMeta = [];
foreach ($districtRecords as $district) {
	$statusLabel = $district['geo_status'] ?? 'active';
	$districtList[$district['geo_id']] = sprintf("%s (%s)", $district['geo_name'], _translate(ucwords($statusLabel)));
	$districtMeta[] = [
		'id' => (int) $district['geo_id'],
		'name' => sprintf("%s (%s)", $district['geo_name'], _translate(ucwords($statusLabel))),
		'parent' => (int) ($district['geo_parent'] ?? 0),
		'status' => $statusLabel
	];
}

require_once APPLICATION_PATH . '/header.php';

?>
<style>
	.orphan-row {
		background-color: #fff4d6 !important;
	}
</style>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><em class="fa-solid fa-gears"></em> <?php echo _translate("Geographical Divisions"); ?></h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _translate("Home"); ?></a></li>
			<li class="active"><?php echo _translate("Geographical Divisions"); ?></li>
		</ol>
	</section>

	<!-- Main content -->
	<section class="content">
		<div class="row">
			<div class="col-xs-12">
				<div class="box">
					<div class="box-header with-border">
						<?php if (_isAllowed("add-geographical-divisions.php") && $general->isSTSInstance()) { ?>
							<a href="add-geographical-divisions.php" class="btn btn-primary pull-right"> <em
									class="fa-solid fa-plus"></em>
								<?php echo _translate("Add New Geographical Divisions"); ?></a>
						<?php } ?>
					</div>
					<!-- /.box-header -->
					<div class="box-body">
						<div class="row" style="margin-bottom:15px;">
							<div class="col-md-4 col-sm-6">
								<label for="statusFilter"><?php echo _translate("Status"); ?></label>
								<select class="form-control select2-element" id="statusFilter" name="statusFilter"
									data-placeholder="<?php echo _translate('-- All --'); ?>">
									<option value="active" selected><?php echo _translate("Active"); ?></option>
									<option value="inactive"><?php echo _translate("Inactive"); ?></option>
									<option value="all"><?php echo _translate("All"); ?></option>
								</select>
							</div>
							<div class="col-md-4 col-sm-6">
								<label for="provinceFilter"><?php echo _translate("Province/Region"); ?></label>
								<select class="form-control select2-element" id="provinceFilter" name="provinceFilter"
									title="<?php echo _translate('Please choose a province/region'); ?>"
									data-placeholder="<?php echo _translate('-- All --'); ?>">
									<?= $general->generateSelectOptions($provinceList, null, _translate("-- All --")); ?>
								</select>
							</div>
							<div class="col-md-4 col-sm-6">
								<label for="districtFilter"><?php echo _translate("District/County"); ?></label>
								<select class="form-control select2-element" id="districtFilter" name="districtFilter"
									title="<?php echo _translate('Please choose a district/county'); ?>"
									data-placeholder="<?php echo _translate('-- All --'); ?>">
									<?= $general->generateSelectOptions($districtList, null, _translate("-- All --")); ?>
								</select>
							</div>
						</div>
						<?php if ($general->isSTSInstance()) { ?>
							<div class="row" style="margin-bottom:15px;">
								<div class="col-md-12">
									<a href="merge-geographical-divisions.php" class="btn btn-warning btn-sm">
										<em class="fa-solid fa-layer-group"></em>
										<?php echo _translate("Merge Geographical Divisions"); ?>
									</a>
								</div>
							</div>
						<?php } ?>
						<?php if (!empty($identifiedOrphanDistricts)) { ?>
							<div class="row" style="margin-bottom:15px;">
								<div class="col-md-4 col-sm-6">
									<label for="orphanFilter" class="control-label"
										style="padding-top:0;"><?php echo _translate("Orphaned Districts"); ?></label>
									<div class="form-check" style="margin-top:5px;">
										<input type="checkbox" class="form-check-input" id="orphanFilter" value="yes">
										<label class="form-check-label" for="orphanFilter">
											<?php echo _translate("Show only districts whose province is inactive or missing"); ?>
										</label>
									</div>
								</div>
							</div>
						<?php } ?>
						<table aria-describedby="table" id="samTypDataTable" class="table table-bordered table-striped"
							aria-hidden="true">
							<thead>
								<tr>
									<th scope="row"><?php echo _translate("Name"); ?></th>
									<th scope="row"><?php echo _translate("Code"); ?></th>
									<th scope="row"><?php echo _translate("Parent"); ?></th>
									<th scope="row"><?php echo _translate("Status"); ?></th>
									<?php if ($hasAction) { ?>
										<th scope="row"><?php echo _translate("Action"); ?></th>
									<?php } ?>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td colspan="3" class="dataTables_empty">
										<?php echo _translate("Loading data from server"); ?>
									</td>
								</tr>
							</tbody>

						</table>
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
<script>
	var allDistricts = <?php echo JsonUtility::encodeUtf8Json($districtMeta); ?>;
	var orphanDistrictIds = <?php echo JsonUtility::encodeUtf8Json($identifiedOrphanDistricts); ?>;
	var districtPlaceholderText = <?php echo JsonUtility::encodeUtf8Json(_translate("-- All --")); ?>;
	var oTable = null;
	var $provinceFilter = null;
	var $districtFilter = null;
	var $orphanToggle = null;
	var suppressProvinceChange = false;
	var suppressDistrictChange = false;

	function refreshTable() {
		if (oTable) {
			$.blockUI();
			oTable.fnDraw();
		}
	}

	function isDistrictInList(list, districtId) {
		for (var index = 0; index < list.length; index++) {
			if (String(list[index].id) === String(districtId)) {
				return true;
			}
		}
		return false;
	}

	function getDistrictDetails(districtId) {
		for (var index = 0; index < allDistricts.length; index++) {
			if (String(allDistricts[index].id) === String(districtId)) {
				return allDistricts[index];
			}
		}
		return null;
	}

	function populateDistricts(provinceId, selectedDistrictId) {
		if (!$districtFilter || $districtFilter.length === 0) {
			return;
		}
		var filteredDistricts = [];
		for (var i = 0; i < allDistricts.length; i++) {
			var district = allDistricts[i];
			if (!provinceId || provinceId === '' || String(district.parent) === String(provinceId)) {
				filteredDistricts.push(district);
			}
		}
		var activeDistricts = filteredDistricts.filter(function (d) {
			return (d.status || '').toLowerCase() === 'active';
		}).sort(function (a, b) {
			return a.name.localeCompare(b.name);
		});
		var inactiveDistricts = filteredDistricts.filter(function (d) {
			return (d.status || '').toLowerCase() !== 'active';
		}).sort(function (a, b) {
			return a.name.localeCompare(b.name);
		});
		filteredDistricts = activeDistricts.concat(inactiveDistricts);
		$districtFilter.empty();
		$districtFilter.append($('<option>', {
			value: '',
			text: districtPlaceholderText
		}));
		for (var j = 0; j < filteredDistricts.length; j++) {
			var currentDistrict = filteredDistricts[j];
			$districtFilter.append($('<option>', {
				value: currentDistrict.id,
				text: currentDistrict.name
			}));
		}
		if (selectedDistrictId && isDistrictInList(filteredDistricts, selectedDistrictId)) {
			$districtFilter.val(String(selectedDistrictId));
		} else {
			$districtFilter.val('');
		}
		suppressDistrictChange = true;
		$districtFilter.trigger('change');
		suppressDistrictChange = false;
	}

	function highlightOrphanRows() {
		if (!oTable || orphanDistrictIds.length === 0) {
			return;
		}
		var tableRows = $('#samTypDataTable tbody tr');
		tableRows.removeClass('orphan-row');
		tableRows.each(function () {
			var row = $(this);
			var districtId = row.attr('data-district-id');
			if (!districtId) {
				var districtElement = row.find('[data-district-id]').first();
				if (districtElement.length) {
					districtId = districtElement.data('district-id');
					row.attr('data-district-id', districtId);
				}
			}
			if (districtId && orphanDistrictIds.includes(parseInt(districtId, 10))) {
				row.addClass('orphan-row');
			}
		});
	}

	$(document).ready(function () {
		$provinceFilter = $('#provinceFilter');
		$districtFilter = $('#districtFilter');
		$orphanToggle = $('#orphanFilter');

		$('#statusFilter').select2({
			width: '100%',
			minimumResultsForSearch: 0
		});

		$provinceFilter.select2({
			width: '100%',
			allowClear: true,
			minimumResultsForSearch: 0
		});

		$districtFilter.select2({
			width: '100%',
			allowClear: true,
			minimumResultsForSearch: 0
		});

		populateDistricts($provinceFilter.val(), $districtFilter.val());

		$.blockUI();
		oTable = $('#samTypDataTable').dataTable({
			"bJQueryUI": false,
			"bAutoWidth": false,
			"bInfo": true,
			"bScrollCollapse": true,
			"bStateSave": true,
			"bRetrieve": true,
			"aoColumns": [{
				"sClass": "center"
			},
			{
				"sClass": "center"
			},
			{
				"sClass": "center"
			},
			{
				"sClass": "center"
			},
				<?php if (_isAllowed("geographical-divisions-details.php") && $general->isLISInstance() === false) { ?> {
					"sClass": "center",
					"bSortable": false
				},
				<?php } ?>
			],
			"aaSorting": [
				[3, "asc"],
				[0, "asc"]
			],
			"bProcessing": true,
			"bServerSide": true,
			"sAjaxSource": "/common/reference/get-geographical-divisions-helper.php",
			"fnServerData": function (sSource, aoData, fnCallback) {
				aoData.push({
					"name": "statusFilter",
					"value": $("#statusFilter").val()
				}, {
					"name": "provinceFilter",
					"value": $("#provinceFilter").val()
				}, {
					"name": "districtFilter",
					"value": $("#districtFilter").val()
				}, {
					"name": "orphanOnly",
					"value": $("#orphanFilter").is(':checked') ? 'yes' : 'no'
				});
				$.ajax({
					"dataType": 'json',
					"type": "POST",
					"url": sSource,
					"data": aoData,
					"success": function (data) {
						fnCallback(data);
						highlightOrphanRows();
						$.unblockUI();
					},
					"error": function () {
						$.unblockUI();
					}
				});
			}
		});

		$('#statusFilter').change(function () {
			refreshTable();
		});

		$provinceFilter.on('change', function () {
			if (suppressProvinceChange) {
				return;
			}
			var provinceId = $(this).val();
			var currentDistrict = $districtFilter.val();
			populateDistricts(provinceId, currentDistrict);
			refreshTable();
		});

		$districtFilter.on('change', function () {
			if (suppressDistrictChange) {
				return;
			}
			var districtId = $(this).val();
			if (districtId) {
				var selectedDistrict = getDistrictDetails(districtId);
				if (selectedDistrict) {
					var provinceId = String(selectedDistrict.parent);
					if ($provinceFilter.val() !== provinceId) {
						suppressProvinceChange = true;
						$provinceFilter.val(provinceId).trigger('change');
						suppressProvinceChange = false;
					}
					populateDistricts(provinceId, districtId);
				}
			}
			refreshTable();
		});

		$orphanToggle.on('change', function () {
			refreshTable();
		});
	});
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';
