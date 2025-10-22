<?php

use App\Services\EidService;
use App\Registries\ContainerRegistry;
use App\Utilities\MiscUtility;
use App\Services\DatabaseService;
use App\Services\CommonService;
use App\Utilities\DateUtility;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;




/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var EidService $eidService */
$eidService = ContainerRegistry::get(EidService::class);
$eidResults = $eidService->getEidResults();
$formId = (int) $general->getGlobalConfig('vl_form');


$arr = $general->getGlobalConfig();
$key = (string) $general->getGlobalConfig('key');


$delimiter = $arr['default_csv_delimiter'] ?? ',';
$enclosure = $arr['default_csv_enclosure'] ?? '"';

$output = [];

if (isset($_POST['patientInfo']) && $_POST['patientInfo'] == 'yes') {
    $headings = array("S.No.", "Sample ID", "Remote Sample ID", "Health Facility Name", "Health Facility Code", "District/County", "Province/State", "Testing Lab Name (Hub)", "Sample Received On", "Child ID", "Child Name", "Mother ID", "Child Date of Birth", "Child Age", "Child Sex", "Breastfeeding", "Clinician's Phone Number", "PCR Test Performed Before", "Last PCR Test results", "Reason For PCR Test", "Sample Collection Date", "Sample Requestor Phone Number", "EID Number", "Is Sample Rejected?", "Freezer", "Rack", "Box", "Position", "Volume (ml)", "Sample Tested On", "Result", "Lab Assigned Code", "Date Result Dispatched", "Comments", "Funding Source", "Implementing Partner", "Request Created On");
} else {
    $headings = array("S.No.", "Sample ID", "Remote Sample ID", "Health Facility Name", "Health Facility Code", "District/County", "Province/State", "Testing Lab Name (Hub)", "Sample Received On", "Child Date of Birth", "Child Age", "Child Sex", "Breastfeeding", "Clinician's Phone Number", "PCR Test Performed Before", "Last PCR Test results", "Reason For PCR Test", "Sample Collection Date", "Sample Requestor Phone Number", "EID Number", "Is Sample Rejected?", "Freezer", "Rack", "Box", "Position", "Volume (ml)", "Sample Tested On", "Result", "Lab Assigned Code", "Date Result Dispatched", "Comments", "Funding Source", "Implementing Partner", "Request Created On");
}


if ($general->isStandaloneInstance() && ($key = array_search("Remote Sample ID", $headings)) !== false) {
    unset($headings[$key]);
}

if ($formId != COUNTRY\CAMEROON) {
    $headings = MiscUtility::removeMatchingElements($headings, [_translate("Lab Assigned Code")]);
}
if ($formId != COUNTRY\DRC) {
    $headings = MiscUtility::removeMatchingElements($headings, ["Freezer", "Rack", "Box", "Position", "Volume (ml)"]);
}

$no = 1;
$resultSet = $db->rawQuery($_SESSION['eidRequestSearchResultQuery']);
foreach ($resultSet as $aRow) {
    $row = [];
    //set gender
    $gender = '';
    if ($aRow['child_gender'] == 'male') {
        $gender = 'M';
    } elseif ($aRow['child_gender'] == 'female') {
        $gender = 'F';
    } elseif ($aRow['child_gender'] == 'unreported') {
        $gender = 'Unreported';
    }

    //set sample rejection
    $sampleRejection = 'No';
    if (trim((string) $aRow['is_sample_rejected']) == 'yes' || ($aRow['reason_for_sample_rejection'] != null && trim((string) $aRow['reason_for_sample_rejection']) != '' && $aRow['reason_for_sample_rejection'] > 0)) {
        $sampleRejection = 'Yes';
    }

    $row[] = $no;
    if ($general->isStandaloneInstance()) {
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
    $row[] = DateUtility::humanReadableDateFormat($aRow['sample_received_at_lab_datetime'] ?? '');
    if (isset($_POST['patientInfo']) && $_POST['patientInfo'] == 'yes') {
        if (!empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes') {
            $aRow['child_id'] = $general->crypto('decrypt', $aRow['child_id'], $key);
            $aRow['child_name'] = $general->crypto('decrypt', $aRow['child_name'], $key);
            $aRow['mother_id'] = $general->crypto('decrypt', $aRow['mother_id'], $key);
            //$aRow['mother_name'] = $general->crypto('decrypt', $aRow['mother_name'], $key);
        }
        $row[] = $aRow['child_id'];
        $row[] = $aRow['child_name'];
        $row[] = $aRow['mother_id'];
    }
    $row[] = DateUtility::humanReadableDateFormat($aRow['child_dob']);
    $row[] = ($aRow['child_age'] != null && trim((string) $aRow['child_age']) != '' && $aRow['child_age'] > 0) ? $aRow['child_age'] : 0;
    $row[] = $gender;
    $row[] = $aRow['has_infant_stopped_breastfeeding'];
    $row[] = $aRow['request_clinician_phone_number'];
    $row[] = $aRow['pcr_test_performed_before'];
    $row[] = $aRow['previous_pcr_result'];
    $row[] = $aRow['reason_for_pcr'];
    $row[] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '');
    $row[] = $aRow['sample_requestor_phone'];
    $row[] = $aRow['eid_number'];
    $row[] = $sampleRejection;
    if ($formId == COUNTRY\DRC) {
        $formAttributes = !empty($aRow['form_attributes']) ? json_decode($aRow['form_attributes']) : null;
        $storageObj = isset($formAttributes->storage) ? json_decode($formAttributes->storage) : null;

        $row[] = $storageObj->storageCode ?? '';
        $row[] = $storageObj->rack ?? '';
        $row[] = $storageObj->box ?? '';
        $row[] = $storageObj->position ?? '';
        $row[] = $storageObj->volume ?? '';
    }
    $row[] = DateUtility::humanReadableDateFormat($aRow['sample_tested_datetime'] ?? '');
    $row[] = $eidResults[$aRow['result']] ?? $aRow['result'];
    if ($formId == COUNTRY\CAMEROON) {
        $row[] = ($aRow['lab_assigned_code']);
    }
    $row[] = DateUtility::humanReadableDateFormat($aRow['result_printed_datetime'] ?? '');
    $row[] = $aRow['lab_tech_comments'];
    $row[] = $aRow['funding_source_name'] ?? null;
    $row[] = $aRow['i_partner_name'] ?? null;
    $row[] = DateUtility::humanReadableDateFormat($aRow['request_created_datetime'], true);
    $output[] = $row;
    $no++;
}

if (isset($_SESSION['eidRequestSearchResultQueryCount']) && $_SESSION['eidRequestSearchResultQueryCount'] > 50000) {

    $fileName = TEMP_PATH . DIRECTORY_SEPARATOR . 'InteLIS-EID-Requests-' . date('d-M-Y-H-i-s') . '.csv';
    $fileName = MiscUtility::generateCsv($headings, $output, $fileName, $delimiter, $enclosure);
    // we dont need the $output variable anymore
    unset($output);
    echo base64_encode((string) $fileName);
} else {


    $excel = new Spreadsheet();
    $sheet = $excel->getActiveSheet();

    $sheet->fromArray($headings, null, 'A3');

    foreach ($output as $rowNo => $rowData) {
        $rRowCount = $rowNo + 4;
        $sheet->fromArray($rowData, null, 'A' . $rRowCount);
    }

    $writer = IOFactory::createWriter($excel, IOFactory::READER_XLSX);
    $filename = 'InteLIS-EID-Requests-' . date('d-M-Y-H-i-s') . '.xlsx';
    $writer->save(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
    echo urlencode(basename($filename));
}
