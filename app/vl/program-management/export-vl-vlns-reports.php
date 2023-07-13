<?php

use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

ini_set('memory_limit', -1);

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var DateUtility $dateTimeUtil */
$dateTimeUtil = new DateUtility();
$styleArray = array(
    'font' => array(
         'bold' => true,
         'size' => '13',
    ),
    'alignment' => array(
         'horizontal' => Alignment::HORIZONTAL_CENTER,
         'vertical' => Alignment::VERTICAL_CENTER,
    ),
    'borders' => array(
         'outline' => array(
              'style' => Border::BORDER_THIN,
         ),
    )
);
$sQuery = "SELECT 
                SQL_CALC_FOUND_ROWS 
                vl.patient_art_no, 
                f.facility_name, 
                f.facility_code, 
                vl.patient_age_in_years, 
                vl.patient_gender, 
                vl.is_patient_pregnant, 
                vl.is_patient_breastfeeding,
                vl.current_regimen, 
                vl.result 
            FROM form_vl as vl 
            LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id";

$sWhere[] =  " vl.vl_result_category = 'not suppressed' AND vl.patient_age_in_years IS NOT NULL AND vl.patient_gender IS NOT NULL AND vl.current_regimen IS NOT NULL ";
/* Sample collection date filter */
$start_date = '';
$end_date = '';
if (isset($_POST['sampleCollectionDate']) && trim($_POST['sampleCollectionDate']) != '') {
     $s_c_date = explode("to", $_POST['sampleCollectionDate']);
     if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
          $start_date = DateUtility::isoDateFormat(trim($s_c_date[0]));
     }
     if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
          $end_date = DateUtility::isoDateFormat(trim($s_c_date[1]));
     }
}
/* Sample type filter */
if (isset($_POST['facilityName']) && trim($_POST['facilityName']) != '') {
     $sWhere[] =  ' f.facility_id IN (' . $_POST['facilityName'] . ')';
}
/* Sample test date filter */
$sTestDate = '';
$eTestDate = '';
if (isset($_POST['sampleTestDate']) && trim($_POST['sampleTestDate']) != '') {
     $s_t_date = explode("to", $_POST['sampleTestDate']);
     if (isset($s_t_date[0]) && trim($s_t_date[0]) != "") {
          $sTestDate = DateUtility::isoDateFormat(trim($s_t_date[0]));
     }
     if (isset($s_t_date[1]) && trim($s_t_date[1]) != "") {
          $eTestDate = DateUtility::isoDateFormat(trim($s_t_date[1]));
     }
}

/* Assign date time filters */
if (isset($_POST['sampleCollectionDate']) && trim($_POST['sampleCollectionDate']) != '') {
     if (trim($start_date) == trim($end_date)) {
          $sWhere[] =  '  DATE(vl.sample_collection_date) = "' . $start_date . '"';
     } else {
          $sWhere[] =  '  DATE(vl.sample_collection_date) >= "' . $start_date . '" AND DATE(vl.sample_collection_date) <= "' . $end_date . '"';
     }
}
if (isset($_POST['sampleTestDate']) && trim($_POST['sampleTestDate']) != '') {
     if (trim($sTestDate) == trim($eTestDate)) {
          $sWhere[] = '  DATE(vl.sample_tested_datetime) = "' . $sTestDate . '"';
     } else {
          $sWhere[] =  '  DATE(vl.sample_tested_datetime) >= "' . $sTestDate . '" AND DATE(vl.sample_tested_datetime) <= "' . $eTestDate . '"';
     }
}

if (!empty($_SESSION['facilityMap'])) {
     $sWhere[] =  "  vl.facility_id IN (" . $_SESSION['facilityMap'] . ")   ";
}

if (!empty($sWhere)) {
    $sWhere = implode(" AND ", $sWhere);
    $sQuery = $sQuery . ' WHERE ' . $sWhere;
}
$sQuery = $sQuery . ' ORDER BY patient_art_no asc, sample_collection_date asc';
// die($sQuery);
$rResult = $db->rawQuery($sQuery);
// Separate the data into two arrays
$vfData = [];
$vlnsData = [];
$patientIds = [];
$output = [];
$headings = array(
    'Patient ID',
    'Facility Name',
    'Facility Code',
    'Age',
    'Gender',
    'Pregnant',
    'Breastfeeding',
    'Regimen',
    'VL Result'
);
// Separate the data into two arrays
$vfData = [];
$vlnsData = [];
$patientIds = [];

foreach ($rResult as $aRow) {
    $patientId = $aRow['patient_art_no'];
    $vfData[] = $aRow;
    $vlnsData[] = $aRow;
    // Check if patient id already there in array
    if (in_array($patientId, $patientIds)) {
        // If there we remove vlsndata for this dublication
        foreach ($vlnsData as $key => $vlnsDataRow) {
            if ($vlnsDataRow['patient_art_no'] === $patientId) {
                unset($vlnsData[$key]);
            }
        }
    }else{
        $patientIds[] = $patientId;
    } 
}
foreach ($vlnsData as $key => $vlnsDataRow) {
    foreach ($vfData as $key => $vfDataRow) {
        if ($vfDataRow['patient_art_no'] === $vlnsDataRow['patient_art_no']) {
            unset($vfData[$key]);
        }
    }
}

$vfData = array_combine(range(1, count($vfData)), array_values($vfData));
$vlnsData = array_combine(range(1, count($vlnsData)), array_values($vlnsData));
$colNo = 1;
$excel = new Spreadsheet();
$vfSheet = $excel->getActiveSheet();
$vfSheet->setTitle('VF');
foreach ($headings as $field => $value) {
    $vfSheet->setCellValue(Coordinate::stringFromColumnIndex($colNo) . '1', html_entity_decode($value));
    $colNo++;
}
$vfSheet->getStyle('A1:I1')->applyFromArray($styleArray);
foreach ($vfData as $rowNo => $rowData) {
    $colNo = 1;
    $rRowCount = $rowNo + 1;
    foreach ($rowData as $field => $value) {
        $vfSheet->setCellValue(Coordinate::stringFromColumnIndex($colNo) . $rRowCount, html_entity_decode(ucwords($value)));
        $colNo++;
    }
}
$colNo = 1;
$vlnsSheet = $excel->createSheet();
$vlnsSheet->setTitle('VLNS');
foreach ($headings as $field => $value) {
    $vlnsSheet->setCellValue(Coordinate::stringFromColumnIndex($colNo) . '1', html_entity_decode($value));
    $colNo++;
}
$vlnsSheet->getStyle('A1:I1')->applyFromArray($styleArray);
foreach ($vlnsData as $rowNo => $rowData) {
    $colNo = 1;
    $rRowCount = $rowNo + 1;
    foreach ($rowData as $field => $value) {
        $vlnsSheet->setCellValue(Coordinate::stringFromColumnIndex($colNo) . $rRowCount, html_entity_decode(ucwords($value)));
        $colNo++;
    }
}
$writer = IOFactory::createWriter($excel, 'Xlsx');
$filename = TEMP_PATH . DIRECTORY_SEPARATOR . 'VLSM-HIGH-VL-AND-VIROLOGIC-FAILURE-REPORT' . date('d-M-Y-H-i-s') . '-' . $general->generateRandomString(5) . '.xlsx';
$writer->save($filename);
echo base64_encode($filename);
