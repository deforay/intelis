<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\TbService;
use App\Utilities\DateUtility;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);


/** @var TbService $tbService */
$tbService = ContainerRegistry::get(TbService::class);
$tbResults = $tbService->getTbResults();
/* Global config data */
$arr = $general->getGlobalConfig();

$sQuery = $_SESSION['tbRequestSearchResultQuery'];

$rResult = $db->rawQuery($sQuery);


$output = [];

if (isset($_POST['patientInfo']) && $_POST['patientInfo'] == 'yes') {
    $headings = array("S. No.", "Sample Code", "Remote Sample Code", "Testing Lab Name", "Lab staff Assigned", "Health Facility/POE County", "Health Facility/POE State", "Health Facility/POE", "Case ID", "Patient Name", "Patient DoB", "Patient Age", "Patient Gender", "Date specimen collected", "Reason for Test Request",  "Date specimen Received", "Date specimen Entered", "Specimen Status", "Specimen Type", "Date specimen Tested", "Testing Platform", "Test Method", "Result", "Date result released");
} else {
    $headings = array("S. No.", "Sample Code", "Remote Sample Code", "Testing Lab Name", "Lab staff Assigned", "Health Facility/POE County", "Health Facility/POE State", "Health Facility/POE", "Patient DoB", "Patient Age", "Patient Gender", "Date specimen collected", "Reason for Test Request",  "Date specimen Received", "Date specimen Entered", "Specimen Status", "Specimen Type", "Date specimen Tested", "Testing Platform", "Test Method", "Result", "Date result released");
}

if ($_SESSION['instanceType'] == 'standalone' && ($key = array_search("Remote Sample Code", $headings)) !== false) {
    unset($headings[$key]);
}

$no = 1;
foreach ($rResult as $aRow) {
    $row = [];

    // Get testing platform and test method
    $tbTestQuery = "SELECT * from tb_tests where tb_id= ? ORDER BY tb_test_id ASC";
    $tbTestInfo = $db->rawQuery($tbTestQuery, [$aRow['tb_id']]);

    foreach ($tbTestInfo as $indexKey => $rows) {
        $testPlatform = $rows['testing_platform'];
        $testMethod = $rows['test_name'];
    }

    //set gender
    $gender = '';
    if ($aRow['patient_gender'] == 'male') {
        $gender = 'M';
    } elseif ($aRow['patient_gender'] == 'female') {
        $gender = 'F';
    } elseif ($aRow['patient_gender'] == 'not_recorded') {
        $gender = 'Unreported';
    }

    //set sample rejection
    $sampleRejection = 'No';
    if (trim($aRow['is_sample_rejected']) == 'yes' || ($aRow['reason_for_sample_rejection'] != null && trim($aRow['reason_for_sample_rejection']) != '' && $aRow['reason_for_sample_rejection'] > 0)) {
        $sampleRejection = 'Yes';
    }
    if (!empty($aRow['patient_name'])) {
        $patientFname = ($general->crypto('doNothing', $aRow['patient_name'], $aRow['patient_id']));
    } else {
        $patientFname = '';
    }
    if (!empty($aRow['patient_surname'])) {
        $patientLname = ($general->crypto('doNothing', $aRow['patient_surname'], $aRow['patient_id']));
    } else {
        $patientLname = '';
    }


    $row[] = $no;
    if ($_SESSION['instanceType'] == 'standalone') {
        $row[] = $aRow["sample_code"];
    } else {
        $row[] = $aRow["sample_code"];
        $row[] = $aRow["remote_sample_code"];
    }
    $row[] = $aRow['lab_name'];
    $row[] = $aRow['labTechnician'];
    $row[] = $aRow['facility_district'];
    $row[] = $aRow['facility_state'];
    $row[] = $aRow['facility_name'];
    if (isset($_POST['patientInfo']) && $_POST['patientInfo'] == 'yes') {
        $row[] = $aRow['patient_id'];
        $row[] = $patientFname . " " . $patientLname;
    }
    $row[] = DateUtility::humanReadableDateFormat($aRow['patient_dob']);
    $row[] = ($aRow['patient_age'] != null && trim($aRow['patient_age']) != '' && $aRow['patient_age'] > 0) ? $aRow['patient_age'] : 0;
    $row[] = $aRow['patient_gender'];
    $row[] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date']);
    $row[] = $aRow['test_reason_name'];
    $row[] = DateUtility::humanReadableDateFormat($aRow['sample_received_at_lab_datetime']);
    $row[] = DateUtility::humanReadableDateFormat($aRow['request_created_datetime']);
    $row[] = $aRow['status_name'];
    $row[] = $aRow['sample_name'];
    $row[] = DateUtility::humanReadableDateFormat($aRow['sample_tested_datetime']);
    $row[] = $testPlatform;
    $row[] = $testMethod;
    $row[] = $tbResults[$aRow['result']];
    $row[] = DateUtility::humanReadableDateFormat($aRow['result_printed_datetime']);

    $output[] = $row;
    $no++;
}

$start = (count($output)) + 2;
foreach ($output as $rowNo => $rowData) {
    $colNo = 1;
    $rRowCount = $rowNo + 4;
    foreach ($rowData as $field => $value) {
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colNo) . $rRowCount, html_entity_decode($value));
        $colNo++;
    }
}

if (isset($_SESSION['tbRequestSearchResultQueryCount']) && $_SESSION['tbRequestSearchResultQueryCount'] > 5000) {

    $fileName = TEMP_PATH . DIRECTORY_SEPARATOR . 'VLSM-TB-Requests-' . date('d-M-Y-H-i-s') . '.csv';
    $file = new SplFileObject($fileName, 'w');
    $file->setCsvControl("\t", "\r\n");
    $file->fputcsv($headings);
    foreach ($output as $row) {
        $file->fputcsv($row);
    }
    // we dont need the $file variable anymore
    $file = null;
    echo base64_encode($fileName);
} else {

    $colNo = 1;
    $excel = new Spreadsheet();
    $sheet = $excel->getActiveSheet();
    $nameValue = '';
    foreach ($_POST as $key => $value) {
        if (trim($value) != '' && trim($value) != '-- Select --') {
            $nameValue .= str_replace("_", " ", $key) . " : " . $value . "&nbsp;&nbsp;";
        }
    }
    $sheet->setCellValue(Coordinate::stringFromColumnIndex($colNo) . '1', html_entity_decode($nameValue));
    if ($_POST['withAlphaNum'] == 'yes') {
        foreach ($headings as $field => $value) {
            $string = str_replace(' ', '', $value);
            $value = preg_replace('/[^A-Za-z0-9\-]/', '', $string);
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colNo) . '3', html_entity_decode($value));
            $colNo++;
        }
    } else {
        foreach ($headings as $field => $value) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colNo) . '3', html_entity_decode($value));
            $colNo++;
        }
    }

    $writer = IOFactory::createWriter($excel, 'Xlsx');
    $filename = TEMP_PATH . DIRECTORY_SEPARATOR . 'VLSM-TB-Requests-' . date('d-M-Y-H-i-s') . '.xlsx';
    $writer->save($filename);
    echo base64_encode($filename);
}
