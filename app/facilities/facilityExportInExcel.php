<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;



/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$excel = new Spreadsheet();
$output = [];
$sheet = $excel->getActiveSheet();
$sWhere = [];
$facilityType = $_POST['facilityType'];
if (isset($facilityType) && trim((string) $facilityType) !== '') {
	$sWhere[] = ' f_t.facility_type_id = "' . $_POST['facilityType'] . '"';
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
$sQuery = "SELECT SQL_CALC_FOUND_ROWS f_d.*, f_t.*,p.geo_name as province ,d.geo_name as district
            FROM facility_details as f_d
            LEFT JOIN facility_type as f_t ON f_t.facility_type_id=f_d.facility_type
            LEFT JOIN geographical_divisions as p ON f_d.facility_state_id = p.geo_id
            LEFT JOIN geographical_divisions as d ON f_d.facility_district_id = d.geo_id $qry ";

if (!empty($sWhere)) {
	$sWhere = ' where ' . implode(' AND ', $sWhere);
	$sQuery = $sQuery . ' ' . $sWhere;
}

if (!empty($sOrder) && $sOrder !== '') {
	$sOrder = preg_replace('/(\v|\s)+/', ' ', (string) $sOrder);
	$sQuery = $sQuery . ' ORDER BY ' . $sOrder;
}

if (isset($sLimit) && isset($sOffset)) {
	$sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
}
$rResult = $db->rawQuery($sQuery);
/*   Added to activity log */
$general->activityLog('Export-facilities', $_SESSION['userName'] . ' Exported facilities details to excelsheet' . $_POST['facilityName'], 'facility');

$headings = ["Facility Name", "Facility Code", "External Facility Code", "Facility Type", "status", "Province/State", "District/County", "Address", "Email", "Phone Number", "Latitude", "Longitude"];

$colNo = 1;

$nameValue = '';
foreach ($_POST as $key => $value) {
	if (trim((string) $value) !== '' && trim((string) $value) !== '-- Select --') {
		$nameValue .= str_replace("_", " ", $key) . " : " . $value . "&nbsp;&nbsp;";
	}
}

$sheet->setCellValue(Coordinate::stringFromColumnIndex($colNo) . '1', html_entity_decode($nameValue));

foreach ($headings as $field => $value) {
	$sheet->setCellValue(Coordinate::stringFromColumnIndex($colNo) . '3', html_entity_decode($value));
	$colNo++;
}

foreach ($rResult as $aRow) {
	$row = [];

	$row[] = $aRow['facility_name'];
	$row[] = $aRow['facility_code'];
	$row[] = $aRow['other_id'];
	$row[] = $aRow['facility_type_name'];
	$row[] = $aRow['status'];
	$row[] = $aRow['province'];
	$row[] = $aRow['district'];
	$row[] = $aRow['address'];
	$row[] = $aRow['facility_emails'];
	$row[] = $aRow['facility_mobile_numbers'];
	$row[] = $aRow['latitude'];
	$row[] = $aRow['longitude'];
	$output[] = $row;
}

$start = (count($output)) + 2;
foreach ($output as $rowNo => $rowData) {
	$colNo = 1;
	foreach ($rowData as $field => $value) {
		$rRowCount = $rowNo + 4;
		$sheet->setCellValue(Coordinate::stringFromColumnIndex($colNo) . $rRowCount, html_entity_decode((string) $value));
		$colNo++;
	}
}
$writer = IOFactory::createWriter($excel, IOFactory::READER_XLSX);
$filename = 'Facility-Detail-Report-' . date('d-M-Y-H-i-s') . '.xlsx';
$writer->save(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
echo urlencode(basename($filename));
