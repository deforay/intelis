<?php

use App\Services\EidService;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;
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
$dateTimeUtil = new DateUtility();


/** @var EidService $eidService */
$eidService = ContainerRegistry::get(EidService::class);
$eidResults = $eidService->getEidResults();

//system config
$systemConfigQuery = "SELECT * from system_config";
$systemConfigResult = $db->query($systemConfigQuery);
$sarr = [];
// now we create an associative array so that we can easily create view variables
for ($i = 0; $i < sizeof($systemConfigResult); $i++) {
    $sarr[$systemConfigResult[$i]['name']] = $systemConfigResult[$i]['value'];
}
/*
$sQuery = "SELECT SQL_CALC_FOUND_ROWS vl.*, f.*,
     b.batch_code,
     ts.status_name,
     f.facility_name,
     l_f.facility_name as labName,
     f.facility_code,
     f.facility_state,
     f.facility_district,
     u_d.user_name as reviewedBy,
     a_u_d.user_name as approvedBy,
     rs.rejection_reason_name,
     r_f_s.funding_source_name,
     r_i_p.i_partner_name 

     FROM form_eid as vl 

     INNER JOIN facility_details as f ON vl.facility_id=f.facility_id 
     INNER JOIN facility_details as l_f ON vl.lab_id=l_f.facility_id 
     LEFT JOIN r_sample_status as ts ON ts.status_id=vl.result_status 
     LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id 
     LEFT JOIN user_details as u_d ON u_d.user_id=vl.result_reviewed_by 
     LEFT JOIN user_details as a_u_d ON a_u_d.user_id=vl.result_approved_by 
     LEFT JOIN r_eid_sample_rejection_reasons as rs ON rs.rejection_reason_id=vl.reason_for_sample_rejection 
     LEFT JOIN r_funding_sources as r_f_s ON r_f_s.funding_source_id=vl.funding_source 
     LEFT JOIN r_implementation_partners as r_i_p ON r_i_p.i_partner_id=vl.implementing_partner";

if (isset($_SESSION['eidRequestData']['sWhere']) && !empty($_SESSION['eidRequestData']['sWhere'])) {
    $sQuery = $sQuery . ' WHERE ' . $_SESSION['eidRequestData']['sWhere'];
}

if (isset($_SESSION['eidRequestData']['sOrder']) && !empty($_SESSION['eidRequestData']['sOrder'])) {
    $sQuery = $sQuery . " ORDER BY " . $_SESSION['eidRequestData']['sOrder'];
}*/

// die($sQuery);
$rResult = $db->rawQuery($_SESSION['eidRequestSearchResultQuery']);

$excel = new Spreadsheet();
$output = [];
$sheet = $excel->getActiveSheet();
if (isset($_POST['patientInfo']) && $_POST['patientInfo'] == 'yes') {
    $headings = array("S.No.", "Sample Code", "Remote Sample Code", "Health Facility Name", "Health Facility Code", "District/County", "Province/State", "Testing Lab Name (Hub)", "Child ID", "Child Name", "Mother ID", "Child Date of Birth", "Child Age", "Child Gender", "Breastfeeding status", "PCR Test Performed Before", "Last PCR Test results", "Sample Collection Date", "Is Sample Rejected?", "Sample Tested On", "Result", "Sample Received On", "Date Result Dispatched", "Comments", "Funding Source", "Implementing Partner", "Request Created On");
}
else
{
    $headings = array("S.No.", "Sample Code", "Remote Sample Code", "Health Facility Name", "Health Facility Code", "District/County", "Province/State", "Testing Lab Name (Hub)", "Child Date of Birth", "Child Age", "Child Gender", "Breastfeeding status", "PCR Test Performed Before", "Last PCR Test results", "Sample Collection Date", "Is Sample Rejected?", "Sample Tested On", "Result", "Sample Received On", "Date Result Dispatched", "Comments", "Funding Source", "Implementing Partner", "Request Created On");
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
    //date of birth
    $dob = '';
    if (!empty($aRow['child_dob'])) {
        $dob =  DateUtility::humanReadableDateFormat($aRow['child_dob']);
    }
    //set gender
    $gender = '';
    if ($aRow['child_gender'] == 'male') {
        $gender = 'M';
    } else if ($aRow['child_gender'] == 'female') {
        $gender = 'F';
    } else if ($aRow['child_gender'] == 'not_recorded') {
        $gender = 'Unreported';
    }
    //sample collecion date
    $sampleCollectionDate = '';
    if (!empty($aRow['sample_collection_date'])) {
        $sampleCollectionDate =  DateUtility::humanReadableDateFormat($aRow['sample_collection_date']);
    }

    $sampleTestedOn = '';
    if (!empty($aRow['sample_tested_datetime'])) {
        $sampleTestedOn =  DateUtility::humanReadableDateFormat($aRow['sample_tested_datetime']);
    }

    if (!empty($aRow['sample_received_at_vl_lab_datetime'])) {
        $sampleReceivedOn =  DateUtility::humanReadableDateFormat($aRow['sample_received_at_vl_lab_datetime']);
    }


    //set sample rejection
    $sampleRejection = 'No';
    if (trim($aRow['is_sample_rejected']) == 'yes' || ($aRow['reason_for_sample_rejection'] != null && trim($aRow['reason_for_sample_rejection']) != '' && $aRow['reason_for_sample_rejection'] > 0)) {
        $sampleRejection = 'Yes';
    }
    //result dispatched date
    $resultDispatchedDate = '';
    if ($aRow['result_printed_datetime'] != null && trim($aRow['result_printed_datetime']) != '' && $aRow['result_dispatched_datetime'] != '0000-00-00 00:00:00') {
        $resultDispatchedDate =  DateUtility::humanReadableDateFormat($aRow['result_printed_datetime']);
    }

    //requeste created date time
    $requestCreatedDatetime = '';
    if ($aRow['request_created_datetime'] != null && trim($aRow['request_created_datetime']) != '' && $aRow['request_created_datetime'] != '0000-00-00') {
        $requestCreatedDatetime =  DateUtility::humanReadableDateFormat($aRow['request_created_datetime'], true);
    }
    //set result log value
    $logVal = '0.0';
    if ($aRow['result_value_log'] != null && trim($aRow['result_value_log']) != '') {
        $logVal = round($aRow['result_value_log'], 1);
    } else if ($aRow['result_value_absolute'] != null && trim($aRow['result_value_absolute']) != '' && $aRow['result_value_absolute'] > 0) {
        $logVal = round(log10((float)$aRow['result_value_absolute']), 1);
    }

    if ($aRow['patient_first_name'] != '') {
        $patientFname = ($general->crypto('doNothing', $aRow['patient_first_name'], $aRow['patient_art_no']));
    } else {
        $patientFname = '';
    }
    if ($aRow['patient_middle_name'] != '') {
        $patientMname = ($general->crypto('doNothing', $aRow['patient_middle_name'], $aRow['patient_art_no']));
    } else {
        $patientMname = '';
    }
    if ($aRow['patient_last_name'] != '') {
        $patientLname = ($general->crypto('doNothing', $aRow['patient_last_name'], $aRow['patient_art_no']));
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
    $row[] = ($aRow['facility_name']);
    $row[] = $aRow['facility_code'];
    $row[] = ($aRow['facility_district']);
    $row[] = ($aRow['facility_state']);
    $row[] = ($aRow['labName']);
    if (isset($_POST['patientInfo']) && $_POST['patientInfo'] == 'yes') {
        $row[] = $aRow['child_id'];
        $row[] = $aRow['child_name'];
        $row[] = $aRow['mother_id'];
    }
    $row[] = $dob;
    $row[] = ($aRow['child_age'] != null && trim($aRow['child_age']) != '' && $aRow['child_age'] > 0) ? $aRow['child_age'] : 0;
    $row[] = $gender;
    $row[] = ($aRow['has_infant_stopped_breastfeeding']);
    $row[] = ($aRow['pcr_test_performed_before']);
    $row[] = ($aRow['previous_pcr_result']);
    $row[] = $sampleCollectionDate;
    $row[] = $sampleRejection;
    $row[] = $sampleTestedOn;
    $row[] = $eidResults[$aRow['result']];
    $row[] = $sampleReceivedOn;
    $row[] = $resultDispatchedDate;
    $row[] = ($aRow['lab_tech_comments']);
    $row[] = (isset($aRow['funding_source_name']) && trim($aRow['funding_source_name']) != '') ? ($aRow['funding_source_name']) : '';
    $row[] = (isset($aRow['i_partner_name']) && trim($aRow['i_partner_name']) != '') ? ($aRow['i_partner_name']) : '';
    $row[] = $requestCreatedDatetime;
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
$filename = 'VLSM-EID-Requested-Data-' . date('d-M-Y-H-i-s') . '.xlsx';
$writer->save(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
echo base64_encode(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
