<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

if (session_status() == PHP_SESSION_NONE) {
	session_start();
}

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$excel = new Spreadsheet();
$output = [];
$sheet = $excel->getActiveSheet();
$facilityType = $_POST['facilityType'];
if (isset($facilityType) && trim($facilityType) != '') {
	$sWhere[] = ' f_t.facility_type_id = "' . $_POST['facilityType'] . '"';
}
if (isset($_POST['district']) && trim($_POST['district']) != '') {
	$sWhere[] = " d.geo_name LIKE '%" . $_POST['district'] . "%' ";
}
if (isset($_POST['state']) && trim($_POST['state']) != '') {
	$sWhere[] = " p.geo_name LIKE '%" . $_POST['state'] . "%' ";
}
$qry = "";
if (isset($_POST['testType']) && trim($_POST['testType']) != '') {
	if (!empty($facilityType)) {
		if ($facilityType == '2') {
			$qry = " LEFT JOIN testing_labs tl ON tl.facility_id=f_d.facility_id";
			$sWhere[] = ' tl.test_type = "' . $_POST['testType'] . '"';
		} else {
			$qry = " LEFT JOIN health_facilities hf ON hf.facility_id=f_d.facility_id";
			$sWhere[] = ' hf.test_type = "' . $_POST['testType'] . '"';
		}
	}
}
$sQuery = "SELECT SQL_CALC_FOUND_ROWS f_d.*, f_t.*,p.geo_name as province ,d.geo_name as district
            FROM facility_details as f_d
            LEFT JOIN facility_type as f_t ON f_t.facility_type_id=f_d.facility_type
            LEFT JOIN geographical_divisions as p ON f_d.facility_state_id = p.geo_id
            LEFT JOIN geographical_divisions as d ON f_d.facility_district_id = d.geo_id $qry ";

if (isset($sWhere) && !empty($sWhere)) {
	$sWhere = ' where ' . implode(' AND ', $sWhere);
	$sQuery = $sQuery . ' ' . $sWhere;
}

if (isset($sOrder) && !empty($sOrder)) {
	$sOrder = preg_replace('/(\v|\s)+/', ' ', $sOrder);
	$sQuery = $sQuery . ' order by ' . $sOrder;
}

if (isset($sLimit) && isset($sOffset)) {
	$sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
}
$rResult = $db->rawQuery($sQuery);
/*   Added to activity log */
$general->activityLog('Export-facilities', $_SESSION['userName'] . ' Exported facilities details to excelsheet' . $_POST['facilityName'], 'facility');

$headings = array("Facility Code", "Facility Name", "Facility Type", "status", "Province/State", "District");

$colNo = 1;

$nameValue = '';
foreach ($_POST as $key => $value) {
	if (trim($value) != '' && trim($value) != '-- Select --') {
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

	$row[] = $aRow['facility_code'];
	$row[] = $aRow['facility_name'];
	$row[] = $aRow['facility_type_name'];
	$row[] = $aRow['status'];
	$row[] = $aRow['province'];
	$row[] = $aRow['district'];
	$output[] = $row;
}

$start = (count($output)) + 2;
foreach ($output as $rowNo => $rowData) {
	$colNo = 1;
	foreach ($rowData as $field => $value) {
		$rRowCount = $rowNo + 4;
		$sheet->setCellValue(Coordinate::stringFromColumnIndex($colNo) . $rRowCount, html_entity_decode($value));
		$colNo++;
	}
}
$writer = IOFactory::createWriter($excel, 'Xlsx');
$filename = 'Facility-Detail-Report-' . date('d-M-Y-H-i-s') . '.xlsx';
$writer->save(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
echo base64_encode(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
