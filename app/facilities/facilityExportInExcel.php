<?php

use App\Registries\AppRegistry;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Psr\Http\Message\ServerRequestInterface;

ini_set('memory_limit', -1);
set_time_limit(0);
ini_set('max_execution_time', 20000);

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$sWhere = [];
$params = [];
$joins = '';

$facilityType = trim((string) ($_POST['facilityType'] ?? ''));
if ($facilityType !== '') {
	$sWhere[] = 'f_d.facility_type = ?';
	$params[] = $facilityType;
}
if (trim((string) ($_POST['district'] ?? '')) !== '') {
	$sWhere[] = 'd.geo_name LIKE ?';
	$params[] = '%' . addcslashes(trim((string) $_POST['district']), '%_\\') . '%';
}
if (trim((string) ($_POST['state'] ?? '')) !== '') {
	$sWhere[] = 'p.geo_name LIKE ?';
	$params[] = '%' . addcslashes(trim((string) $_POST['state']), '%_\\') . '%';
}
if (trim((string) ($_POST['testType'] ?? '')) !== '' && $facilityType !== '') {
	// EXISTS instead of a JOIN so a facility never appears once per matching row.
	$testTypeTable = $facilityType === '2' ? 'testing_labs' : 'health_facilities';
	$sWhere[] = "EXISTS (SELECT 1 FROM $testTypeTable tt WHERE tt.facility_id = f_d.facility_id AND tt.test_type = ?)";
	$params[] = trim((string) $_POST['testType']);
}
if (trim((string) ($_POST['activeFacility'] ?? '')) !== '') {
	$sWhere[] = 'f_d.status = ?';
	$params[] = trim((string) $_POST['activeFacility']);
}
if (($_POST['orphanFacility'] ?? '') === 'yes') {
	$sWhere[] = "(f_d.status = 'active' AND (p.geo_status IS NULL OR p.geo_status != 'active' OR d.geo_status IS NULL OR d.geo_status != 'active'))";
}

$sQuery = "SELECT f_d.facility_name, f_d.facility_code, f_d.other_id, f_d.facility_type,
                f_d.status, f_d.address, f_d.facility_emails, f_d.facility_mobile_numbers,
                f_d.latitude, f_d.longitude,
                p.geo_name AS province, d.geo_name AS district
            FROM facility_details AS f_d
            LEFT JOIN geographical_divisions AS p ON f_d.facility_state_id = p.geo_id
            LEFT JOIN geographical_divisions AS d ON f_d.facility_district_id = d.geo_id";

if (!empty($sWhere)) {
	$sQuery .= ' WHERE ' . implode(' AND ', $sWhere);
}
$sQuery .= ' ORDER BY f_d.facility_name';

$general->activityLog('Export-facilities', $_SESSION['userName'] . ' exported facility details to Excel', 'facility');

$filename = TEMP_PATH . DIRECTORY_SEPARATOR . 'Facility-Detail-Report-' . date('d-M-Y-H-i-s') . '.xlsx';

$writer = new Writer();
$writer->openToFile($filename);

// Same columns and order as the bulk upload template, so an export can be
// edited and uploaded again without rearranging anything.
$writer->addRow(Row::fromValues(FacilitiesService::bulkUploadHeadings()));

$resultSet = $db->rawQueryGenerator($sQuery, $params);
$no = 0;
foreach ($resultSet as $aRow) {
	$row = [
		$aRow['facility_name'],
		$aRow['facility_code'],
		$aRow['other_id'],
		$aRow['province'],
		$aRow['district'],
		$aRow['facility_type'],
		$aRow['address'],
		$aRow['facility_emails'],
		$aRow['facility_mobile_numbers'],
		$aRow['latitude'],
		$aRow['longitude'],
		$aRow['status'],
	];
	$writer->addRow(Row::fromValues(array_map(fn($v) => html_entity_decode((string) $v), $row)));

	if (++$no % 5000 === 0) {
		gc_collect_cycles();
	}
}

$writer->close();
echo _downloadToken($filename);
