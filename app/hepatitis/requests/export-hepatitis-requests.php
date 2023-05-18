<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


ini_set('memory_limit', -1);
ini_set('max_execution_time', -1);

use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;


/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$sessionQuery = $_SESSION['hepatitisRequestSearchResultQuery'];
if (isset($sessionQuery) && trim($sessionQuery) != "") {
    $rResult = $db->rawQuery($sessionQuery);


    $output = [];
    if (isset($_POST['patientInfo']) && $_POST['patientInfo'] == 'yes') {
        $headings = array("S. No.", "Sample Code", "Remote Sample Code", "Health Facility Name", "Health Facility Code", "District/County", "Province/State", "Patient ID", "Patient Name", "Patient DoB", "Patient Age", "Patient Gender", "Sample Collection Date", "Is Sample Rejected?", "Rejection Reason", "Sample Tested On", "HCV VL Result", "HBV VL Result", "Sample Received On", "Date Result Dispatched", "Result Status", "Comments", "Funding Source", "Implementing Partner");
    } else {
        $headings = array("S. No.", "Sample Code", "Remote Sample Code", "Health Facility Name", "Health Facility Code", "District/County", "Province/State", "Patient DoB", "Patient Age", "Patient Gender", "Sample Collection Date", "Is Sample Rejected?", "Rejection Reason", "Sample Tested On", "HCV VL Result", "HBV VL Result", "Sample Received On", "Date Result Dispatched", "Result Status", "Comments", "Funding Source", "Implementing Partner");
    }
    if ($_SESSION['instanceType'] == 'standalone' && ($key = array_search('Remote Sample Code', $headings)) !== false) {
        unset($headings[$key]);
    }

    $no = 1;
    foreach ($rResult as $aRow) {
        $row = [];

        //Gender
        switch (strtolower($aRow['patient_gender'])) {
            case 'male':
            case 'm':
                $gender = 'M';
                break;
            case 'female':
            case 'f':
                $gender = 'F';
                break;
            case 'not_recorded':
            case 'notrecorded':
            case 'unreported':
                $gender = 'Unreported';
                break;
            default:
                $gender = '';
                break;
        }

        //set sample rejection
        $sampleRejection = 'No';
        if (trim($aRow['is_sample_rejected']) == 'yes' || ($aRow['reason_for_sample_rejection'] != null && trim($aRow['reason_for_sample_rejection']) != '' && $aRow['reason_for_sample_rejection'] > 0)) {
            $sampleRejection = 'Yes';
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
        $row = [];
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
        if (isset($_POST['patientInfo']) && $_POST['patientInfo'] == 'yes') {
            $row[] = $aRow['patient_id'];
            $row[] = $patientFname . " " . $patientLname;
        }
        $row[] = DateUtility::humanReadableDateFormat($aRow['patient_dob']);
        $row[] = ($aRow['patient_age'] != null && trim($aRow['patient_age']) != '' && $aRow['patient_age'] > 0) ? $aRow['patient_age'] : 0;
        $row[] = $gender;
        $row[] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date']);
        $row[] = $sampleRejection;
        $row[] = $aRow['rejection_reason'];
        $row[] = DateUtility::humanReadableDateFormat($aRow['sample_tested_datetime']);
        $row[] = ($aRow['hcv_vl_result']);
        $row[] = ($aRow['hbv_vl_result']);
        $row[] = DateUtility::humanReadableDateFormat($aRow['sample_received_at_vl_lab_datetime']);
        $row[] = DateUtility::humanReadableDateFormat($aRow['result_printed_datetime']);
        $row[] = $aRow['status_name'];
        $row[] = ($aRow['lab_tech_comments']);
        $row[] = $aRow['funding_source_name'] ?? null;
        $row[] = $aRow['i_partner_name'] ?? null;
        $output[] = $row;
        $no++;
    }


    if (isset($_SESSION['hepatitisRequestSearchResultQueryCount']) && $_SESSION['hepatitisRequestSearchResultQueryCount'] > 5000) {

        $fileName = TEMP_PATH . DIRECTORY_SEPARATOR . 'Hepatitis-Requests-' . date('d-M-Y-H-i-s') . '.csv';
        $file = new SplFileObject($fileName, 'w');
        $file->fputcsv($headings);
        foreach ($output as $row) {
            $file->fputcsv($row);
        }
        // we dont need the $file variable anymore
        $file = null;
        echo base64_encode($fileName);
    } else {
        $excel = new Spreadsheet();
        $sheet = $excel->getActiveSheet();
        $nameValue = '';

        $colNo = 1;


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
                $sheet->getCell(Coordinate::stringFromColumnIndex($colNo) . '3')
                    ->setValueExplicit(html_entity_decode($value));
                $colNo++;
            }
        } else {
            foreach ($headings as $field => $value) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($colNo) . '3', html_entity_decode($value));
                $colNo++;
            }
        }
        //$start = (count($output)) + 2;
        foreach ($output as $rowNo => $rowData) {
            $colNo = 1;
            $rRowCount = $rowNo + 4;
            foreach ($rowData as $field => $value) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($colNo) . $rRowCount, html_entity_decode($value));
                $colNo++;
            }
        }
        $writer = IOFactory::createWriter($excel, 'Xlsx');
        $fileName = TEMP_PATH . DIRECTORY_SEPARATOR . 'Hepatitis-Requests-' . date('d-M-Y-H-i-s') . '.xlsx';
        $writer->save($fileName);
        echo base64_encode($fileName);
    }
}
