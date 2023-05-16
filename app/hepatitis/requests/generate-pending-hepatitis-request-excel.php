<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

use App\Services\Covid19Service;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var Covid19Service $covid19Service */
$covid19Service = ContainerRegistry::get(Covid19Service::class);
$covid19Results = $covid19Service->getCovid19Results();

/* Global config data */
$arr = $general->getGlobalConfig();
$sarr = $general->getSystemConfig();
$tableName = "form_hepatitis";
$primaryKey = "hepatitis_id";
/*
$sQuery = "SELECT SQL_CALC_FOUND_ROWS vl.*, f.*, ts.status_name, b.batch_code FROM $tableName as vl 
          LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id 
          LEFT JOIN r_sample_status as ts ON ts.status_id=vl.result_status 
          LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id";

if (isset($_SESSION['hepatitisRequestData']['sWhere']) && !empty($_SESSION['hepatitisRequestData']['sWhere'])) {
    $sQuery = $sQuery . ' WHERE ' . $_SESSION['hepatitisRequestData']['sWhere'];
}
if (isset($_SESSION['hepatitisRequestData']['sOrder']) && !empty($_SESSION['hepatitisRequestData']['sOrder'])) {
    $sQuery = $sQuery . " ORDER BY " . $_SESSION['hepatitisRequestData']['sOrder'];
}*/
// die($sQuery);
$sQuery = $_SESSION['hepatitisRequestSearchResultQuery'];
$rResult = $db->rawQuery($sQuery);

$excel = new Spreadsheet();
$output = [];
$sheet = $excel->getActiveSheet();
if ($arr['vl_form'] == 1) {
    if (isset($_POST['patientInfo']) && $_POST['patientInfo'] == 'yes') {
        $headings = array("S. No.", "Sample Code", "Remote Sample Code", "Testing Lab Name", "Testing Point", "Lab staff Assigned", "Source Of Alert / POE", "Health Facility/POE County", "Health Facility/POE State", "Health Facility/POE", "Case ID", "Patient Name", "Patient DoB", "Patient Age", "Patient Gender", "Nationality", "Patient State", "Patient County", "Patient City/Village", "Date specimen collected", "Reason for Test Request",  "Date specimen Received", "Date specimen Entered", "Specimen Condition", "Specimen Status", "Specimen Type", "Date specimen Tested", "Testing Platform", "Test Method", "HCV VL Result", "HBV VL Result", "Date result released");
    } else {
        $headings = array("S. No.", "Sample Code", "Remote Sample Code", "Testing Lab Name", "Testing Point", "Lab staff Assigned", "Source Of Alert / POE", "Health Facility/POE County", "Health Facility/POE State", "Health Facility/POE", "Patient DoB", "Patient Age", "Patient Gender", "Nationality", "Patient State", "Patient County", "Patient City/Village", "Date specimen collected", "Reason for Test Request",  "Date specimen Received", "Date specimen Entered", "Specimen Condition", "Specimen Status", "Specimen Type", "Date specimen Tested", "Testing Platform", "Test Method", "HCV VL Result", "HBV VL Result", "Date result released");
    }
} else {
    if (isset($_POST['patientInfo']) && $_POST['patientInfo'] == 'yes') {
        $headings = array("S. No.", "Sample Code", "Remote Sample Code", "Health Facility Name", "Health Facility Code", "District/County", "Province/State", "Patient ID", "Patient Name", "Patient DoB", "Patient Age", "Patient Gender", "Sample Collection Date", "Date of Symptom Onset", "Has the patient had contact with a confirmed case?", "Has the patient had a recent history of travelling to an affected area?", "If Yes, Country Name(s)", "Return Date", "Is Sample Rejected?", "Sample Tested On", "HCV VL Result", "HBV VL Result", "Sample Received On", "Date Result Dispatched", "Comments");
    } else {
        $headings = array("S. No.", "Sample Code", "Remote Sample Code", "Health Facility Name", "Health Facility Code", "District/County", "Province/State", "Patient DoB", "Patient Age", "Patient Gender", "Sample Collection Date", "Date of Symptom Onset", "Has the patient had contact with a confirmed case?", "Has the patient had a recent history of travelling to an affected area?", "If Yes, Country Name(s)", "Return Date", "Is Sample Rejected?", "Sample Tested On", "HCV VL Result", "HBV VL Result", "Sample Received On", "Date Result Dispatched", "Comments");
    }
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
    // if($arr['vl_form'] == 1){
    // 	// Get testing platform and test method 
    // 	$covid19TestQuery = "SELECT * from covid19_tests where covid19_id= " . $aRow['covid19_id'] . " ORDER BY test_id ASC";
    // 	$covid19TestInfo = $db->rawQuery($covid19TestQuery);
    // 	foreach ($covid19TestInfo as $indexKey => $rows) {
    // 		$testPlatform = $rows['testing_platform'];
    // 		$testMethod = $rows['test_name'];
    // 	}
    // }

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
    if ($aRow['patient_last_name'] != '') {
        $patientLname = ($general->crypto('doNothing', $aRow['patient_surname'], $aRow['patient_id']));
    } else {
        $patientLname = '';
    }

    if (isset($aRow['source_of_alert']) && $aRow['source_of_alert'] != "others") {
        $sourceOfArtPOE = str_replace("-", " ", $aRow['source_of_alert']);
    } else {
        $sourceOfArtPOE = $aRow['source_of_alert_other'];
    }

    if ($arr['vl_form'] == 1) {

        $row[] = $no;
        if ($_SESSION['instanceType'] == 'standalone') {
            $row[] = $aRow["sample_code"];
        } else {
            $row[] = $aRow["sample_code"];
            $row[] = $aRow["remote_sample_code"];
        }
        $row[] = ($aRow['labName']);
        $row[] = ($aRow['testing_point']);
        $row[] = ($aRow['labTechnician']);
        $row[] = ($sourceOfArtPOE);
        $row[] = ($aRow['facility_district']);
        $row[] = ($aRow['facility_state']);
        $row[] = ($aRow['facility_name']);
        if (isset($_POST['patientInfo']) && $_POST['patientInfo'] == 'yes') {
            $row[] = $aRow['patient_id'];
            $row[] = $patientFname . " " . $patientLname;
        }
        $row[] = DateUtility::humanReadableDateFormat($aRow['patient_dob']);
        $row[] = ($aRow['patient_age'] != null && trim($aRow['patient_age']) != '' && $aRow['patient_age'] > 0) ? $aRow['patient_age'] : 0;
        $row[] = ($aRow['patient_gender']);
        $row[] = ($aRow['nationality']);
        $row[] = ($aRow['patient_province']);
        $row[] = ($aRow['patient_district']);
        $row[] = ($aRow['patient_city']);
        $row[] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date']);
        $row[] = ($aRow['test_reason_name']);
        $row[] = DateUtility::humanReadableDateFormat($aRow['sample_received_at_vl_lab_datetime']);
        $row[] = DateUtility::humanReadableDateFormat($aRow['request_created_datetime']);
        $row[] = ($aRow['sample_condition']);
        $row[] = ($aRow['status_name']);
        $row[] = ($aRow['sample_name']);
        $row[] = DateUtility::humanReadableDateFormat($aRow['sample_tested_datetime']);
        // $row[] = ($testPlatform);
        // $row[] = ($testMethod);
        $row[] = ($aRow['hcv_vl_result']);
        $row[] = ($aRow['hbv_vl_result']);
        $row[] = DateUtility::humanReadableDateFormat($aRow['result_printed_datetime']);
    } else {

        $row[] = $no;
        if ($_SESSION['instanceType'] == 'standalone') {
            $row[] = $aRow["sample_code"];
        } else {
            $row[] = $aRow["sample_code"];
            $row[] = $aRow["remote_sample_code"];
        }
        $row[] = ($aRow['facility_name']);
        $row[] = $aRow['facility_code'];
        $row[] = ($aRow['facility_district']);
        $row[] = ($aRow['facility_state']);
        $row[] = $aRow['patient_id'];
        $row[] = $patientFname . " " . $patientLname;
        $row[] = $dob;
        $row[] = ($aRow['patient_age'] != null && trim($aRow['patient_age']) != '' && $aRow['patient_age'] > 0) ? $aRow['patient_age'] : 0;
        $row[] = $gender;
        $row[] = $sampleCollectionDate;
        $row[] = DateUtility::humanReadableDateFormat($aRow['date_of_symptom_onset']);
        $row[] = ($aRow['contact_with_confirmed_case']);
        $row[] = ($aRow['has_recent_travel_history']);
        $row[] = ($aRow['travel_country_names']);
        $row[] = DateUtility::humanReadableDateFormat($aRow['travel_return_date']);
        $row[] = $sampleRejection;
        $row[] = $sampleTestedOn;
        $row[] = ($aRow['hcv_vl_result']);
        $row[] = ($aRow['hbv_vl_result']);
        $row[] = DateUtility::humanReadableDateFormat($aRow['sample_received_at_vl_lab_datetime']);
        $row[] = $resultDispatchedDate;
        $row[] = ($aRow['lab_tech_comments']);
        //  $row[] = $aRow['funding_source_name'] ?? null;
        //  $row[] = $aRow['i_partner_name'] ?? null;
    }
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
$filename = 'Hepatitis-Export-Data-' . date('d-M-Y-H-i-s') . '.xlsx';
$writer->save(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
echo base64_encode(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
