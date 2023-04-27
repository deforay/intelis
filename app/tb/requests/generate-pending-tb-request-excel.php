<?php

use App\Services\CommonService;
use App\Services\TbService;
use App\Utilities\DateUtils;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$general = new CommonService();

$tbModel = new TbService();
$tbResults = $tbModel->getTbResults();
/* Global config data */
$arr = $general->getGlobalConfig();
$sarr = $general->getSystemConfig();

$sQuery = $_SESSION['tbRequestSearchResultQuery'];

$rResult = $db->rawQuery($sQuery);

$excel = new Spreadsheet();
$output = [];
$sheet = $excel->getActiveSheet();
if (isset($_POST['patientInfo']) && $_POST['patientInfo'] == 'yes') {
    $headings = array("S. No.", "Sample Code", "Remote Sample Code", "Testing Lab Name", "Lab staff Assigned", "Health Facility/POE County", "Health Facility/POE State", "Health Facility/POE", "Case ID", "Patient Name", "Patient DoB", "Patient Age", "Patient Gender", "Date specimen collected", "Reason for Test Request",  "Date specimen Received", "Date specimen Entered", "Specimen Status", "Specimen Type", "Date specimen Tested", "Testing Platform", "Test Method", "Result", "Date result released");
} else {
    $headings = array("S. No.", "Sample Code", "Remote Sample Code", "Testing Lab Name", "Lab staff Assigned", "Health Facility/POE County", "Health Facility/POE State", "Health Facility/POE", "Patient DoB", "Patient Age", "Patient Gender", "Date specimen collected", "Reason for Test Request",  "Date specimen Received", "Date specimen Entered", "Specimen Status", "Specimen Type", "Date specimen Tested", "Testing Platform", "Test Method", "Result", "Date result released");
}

if ($_SESSION['instanceType'] == 'standalone') {
    if (($key = array_search("Remote Sample Code", $headings)) !== false) {
        unset($headings[$key]);
    }
}
$colNo = 1;

$styleArray = array(
    'font' => array(
        'bold' => true,
        'size' => 12,
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

$borderStyle = array(
    'alignment' => array(
        'horizontal' => Alignment::HORIZONTAL_CENTER,
    ),
    'borders' => array(
        'outline' => array(
            'style' => Border::BORDER_THIN,
        ),
    )
);

$sheet->mergeCells('A1:AG1');
$nameValue = '';
foreach ($_POST as $key => $value) {
    if (trim($value) != '' && trim($value) != '-- Select --') {
        $nameValue .= str_replace("_", " ", $key) . " : " . $value . "&nbsp;&nbsp;";
    }
}
$sheet->getCell(Coordinate::stringFromColumnIndex($colNo) . '1')
    ->setValueExplicit(html_entity_decode($nameValue));
if ($_POST['withAlphaNum'] == 'yes') {
    foreach ($headings as $field => $value) {
        $string = str_replace(' ', '', $value);
        $value = preg_replace('/[^A-Za-z0-9\-]/', '', $string);
        $sheet->getCell(Coordinate::stringFromColumnIndex($colNo) . '3')
            ->setValueExplicit(html_entity_decode($value));
        $colNo++;
    }
} else {
    foreach ($headings as $field => $value) {
        $sheet->getCell(Coordinate::stringFromColumnIndex($colNo) . '3')
            ->setValueExplicit(html_entity_decode($value));
        $colNo++;
    }
}
$sheet->getStyle('A3:AG3')->applyFromArray($styleArray);

$no = 1;
foreach ($rResult as $aRow) {
    $row = [];
    if ($arr['vl_form'] == 1) {
        // Get testing platform and test method 
        $tbTestQuery = "SELECT * from tb_tests where tb_id= " . $aRow['tb_id'] . " ORDER BY tb_test_id ASC";
        $tbTestInfo = $db->rawQuery($tbTestQuery);

        foreach ($tbTestInfo as $indexKey => $rows) {
            $testPlatform = $rows['testing_platform'];
            $testMethod = $rows['test_name'];
        }
    }

    //date of birth
    $dob = '';
    if ($aRow['patient_dob'] != null && trim($aRow['patient_dob']) != '' && $aRow['patient_dob'] != '0000-00-00') {
        $dob =  date("d-m-Y", strtotime($aRow['patient_dob']));
    }
    //set gender
    $gender = '';
    if ($aRow['patient_gender'] == 'male') {
        $gender = 'M';
    } else if ($aRow['patient_gender'] == 'female') {
        $gender = 'F';
    } else if ($aRow['patient_gender'] == 'not_recorded') {
        $gender = 'Unreported';
    }
    //sample collecion date
    $sampleCollectionDate = '';
    if ($aRow['sample_collection_date'] != null && trim($aRow['sample_collection_date']) != '' && $aRow['sample_collection_date'] != '0000-00-00 00:00:00') {
        $expStr = explode(" ", $aRow['sample_collection_date']);
        $sampleCollectionDate =  date("d-m-Y", strtotime($expStr[0]));
    }

    $sampleTestedOn = '';
    if ($aRow['sample_tested_datetime'] != null && trim($aRow['sample_tested_datetime']) != '' && $aRow['sample_tested_datetime'] != '0000-00-00') {
        $sampleTestedOn =  date("d-m-Y", strtotime($aRow['sample_tested_datetime']));
    }


    //set sample rejection
    $sampleRejection = 'No';
    if (trim($aRow['is_sample_rejected']) == 'yes' || ($aRow['reason_for_sample_rejection'] != null && trim($aRow['reason_for_sample_rejection']) != '' && $aRow['reason_for_sample_rejection'] > 0)) {
        $sampleRejection = 'Yes';
    }
    //result dispatched date
    $resultDispatchedDate = '';
    if ($aRow['result_printed_datetime'] != null && trim($aRow['result_printed_datetime']) != '' && $aRow['result_dispatched_datetime'] != '0000-00-00 00:00:00') {
        $expStr = explode(" ", $aRow['result_printed_datetime']);
        $resultDispatchedDate =  date("d-m-Y", strtotime($expStr[0]));
    }

    if ($aRow['patient_name'] != '') {
        $patientFname = ($general->crypto('doNothing', $aRow['patient_name'], $aRow['patient_id']));
    } else {
        $patientFname = '';
    }
    if ($aRow['patient_surname'] != '') {
        $patientLname = ($general->crypto('doNothing', $aRow['patient_surname'], $aRow['patient_id']));
    } else {
        $patientLname = '';
    }

    // if (isset($aRow['source_of_alert']) && $aRow['source_of_alert'] != "others") {
    // 	$sourceOfArtPOE = str_replace("-", " ", $aRow['source_of_alert']);
    // } else {
    // 	$sourceOfArtPOE = $aRow['source_of_alert_other'];
    // }



    $row[] = $no;
    if ($_SESSION['instanceType'] == 'standalone') {
        $row[] = $aRow["sample_code"];
    } else {
        $row[] = $aRow["sample_code"];
        $row[] = $aRow["remote_sample_code"];
    }
    $row[] = ($aRow['lab_name']);
    $row[] = ($aRow['labTechnician']);
    $row[] = ($aRow['facility_district']);
    $row[] = ($aRow['facility_state']);
    $row[] = ($aRow['facility_name']);
    if (isset($_POST['patientInfo']) && $_POST['patientInfo'] == 'yes') {
        $row[] = $aRow['patient_id'];
        $row[] = $patientFname . " " . $patientLname;
    }
    $row[] = DateUtils::humanReadableDateFormat($aRow['patient_dob']);
    $row[] = ($aRow['patient_age'] != null && trim($aRow['patient_age']) != '' && $aRow['patient_age'] > 0) ? $aRow['patient_age'] : 0;
    $row[] = ($aRow['patient_gender']);
    $row[] = DateUtils::humanReadableDateFormat($aRow['sample_collection_date']);
    $row[] = ($aRow['test_reason_name']);
    $row[] = DateUtils::humanReadableDateFormat($aRow['sample_received_at_lab_datetime']);
    $row[] = DateUtils::humanReadableDateFormat($aRow['request_created_datetime']);
    $row[] = ($aRow['status_name']);
    $row[] = ($aRow['sample_name']);
    $row[] = DateUtils::humanReadableDateFormat($aRow['sample_tested_datetime']);
    $row[] = ($testPlatform);
    $row[] = ($testMethod);
    $row[] = $tbResults[$aRow['result']];
    $row[] = DateUtils::humanReadableDateFormat($aRow['result_printed_datetime']);

    $output[] = $row;
    $no++;
}

$start = (count($output)) + 2;
foreach ($output as $rowNo => $rowData) {
    $colNo = 1;
    $rRowCount = $rowNo + 4;
    foreach ($rowData as $field => $value) {
        $sheet->setCellValue(
            Coordinate::stringFromColumnIndex($colNo) . $rRowCount,
            html_entity_decode($value)
        );
        $colNo++;
    }
}
$writer = IOFactory::createWriter($excel, 'Xlsx');
$filename = 'TB-Export-Data-' . date('d-M-Y-H-i-s') . '.xlsx';
$writer->save(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
echo base64_encode(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
