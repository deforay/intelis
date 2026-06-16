<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

ini_set('memory_limit', -1);
set_time_limit(0);
ini_set('max_execution_time', 20000);

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$sWhere = [];
$facilityType = $_POST['facilityType'] ?? '';
if (trim((string) $facilityType) !== '') {
	$sWhere[] = ' f_t.facility_type_id = "' . $facilityType . '"';
}
if (isset($_POST['district']) && trim((string) $_POST['district']) !== '') {
	$sWhere[] = " d.geo_name LIKE '%" . $_POST['district'] . "%' ";
}
if (isset($_POST['state']) && trim((string) $_POST['state']) !== '') {
	$sWhere[] = " p.geo_name LIKE '%" . $_POST['state'] . "%' ";
}
$qry = "";
if (isset($_POST['testType']) && trim((string) $_POST['testType']) !== '' && !empty($facilityType)) {
	if ($facilityType == '2') {
		$qry = " LEFT JOIN testing_labs tl ON tl.facility_id=f_d.facility_id";
		$sWhere[] = ' tl.test_type = "' . $_POST['testType'] . '"';
	} else {
		$qry = " LEFT JOIN health_facilities hf ON hf.facility_id=f_d.facility_id";
		$sWhere[] = ' hf.test_type = "' . $_POST['testType'] . '"';
	}
}
if (isset($_POST['activeFacility']) && trim((string) $_POST['activeFacility']) !== '') {
	$sWhere[] = " f_d.status = '" . $_POST['activeFacility'] . "' ";
}
if (isset($_POST['orphanFacility']) && $_POST['orphanFacility'] === 'yes') {
	$sWhere[] = "(f_d.status = 'active' AND (p.geo_status IS NULL OR p.geo_status != 'active' OR d.geo_status IS NULL OR d.geo_status != 'active'))";
}
$sQuery = "SELECT f_d.*, f_t.*, p.geo_name as province, d.geo_name as district
            FROM facility_details as f_d
            LEFT JOIN facility_type as f_t ON f_t.facility_type_id=f_d.facility_type
            LEFT JOIN geographical_divisions as p ON f_d.facility_state_id = p.geo_id
            LEFT JOIN geographical_divisions as d ON f_d.facility_district_id = d.geo_id $qry ";

if (!empty($sWhere)) {
	$sQuery = $sQuery . ' where ' . implode(' AND ', $sWhere);
}

/*   Added to activity log */
$general->activityLog('Export-facilities', $_SESSION['userName'] . ' Exported facilities details to excelsheet' . ($_POST['facilityName'] ?? ''), 'facility');

$headings = [
	_translate("Facility Name"),
	_translate("Facility Code"),
	_translate("External Facility Code"),
	_translate("Facility Type"),
	_translate("Status"),
	_translate("Province/State"),
	_translate("District/County"),
	_translate("Address"),
	_translate("Email"),
	_translate("Phone Number"),
	_translate("Latitude"),
	_translate("Longitude"),
];

// Build the applied-filters caption (mirrors the legacy title row).
$nameValue = '';
foreach ($_POST as $key => $value) {
	if (trim((string) $value) !== '' && trim((string) $value) !== '-- Select --') {
		$nameValue .= str_replace("_", " ", $key) . " : " . $value . "  ";
	}
}

$filename = TEMP_PATH . DIRECTORY_SEPARATOR . 'Facility-Detail-Report-' . date('d-M-Y-H-i-s') . '.xlsx';

$writer = new Writer();
$writer->openToFile($filename);

// Row 1: applied filters caption, Row 2: blank, Row 3: headings (matches the old layout)
$writer->addRow(Row::fromValues([html_entity_decode(trim($nameValue))]));
$writer->addRow(Row::fromValues([]));
$writer->addRow(Row::fromValues($headings));

// Stream data rows straight from the DB so we never hold the full result set in memory.
$resultSet = $db->rawQueryGenerator($sQuery);
$no = 0;
foreach ($resultSet as $aRow) {
	$row = [
		$aRow['facility_name'],
		$aRow['facility_code'],
		$aRow['other_id'],
		$aRow['facility_type_name'],
		$aRow['status'],
		$aRow['province'],
		$aRow['district'],
		$aRow['address'],
		$aRow['facility_emails'],
		$aRow['facility_mobile_numbers'],
		$aRow['latitude'],
		$aRow['longitude'],
	];
	$writer->addRow(Row::fromValues(array_map(fn($v) => html_entity_decode((string) $v), $row)));

	if (++$no % 5000 === 0) {
		gc_collect_cycles();
	}
}

$writer->close();
echo urlencode(basename($filename));
