<?php



ini_set('memory_limit', -1);
set_time_limit(0);
ini_set('max_execution_time', 300000);

use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Utilities\MiscUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$arr = $general->getGlobalConfig();
$key = (string) $general->getGlobalConfig('key');

$delimiter = $arr['default_csv_delimiter'] ?? ',';
$enclosure = $arr['default_csv_enclosure'] ?? '"';

$sessionQuery = $_SESSION['hepatitisRequestSearchResultQuery'];
if (isset($sessionQuery) && trim((string) $sessionQuery) != "") {


    $output = [];
    if (isset($_POST['patientInfo']) && $_POST['patientInfo'] == 'yes') {
        $headings = array("S. No.", "Sample ID", "Remote Sample ID", "Testing Lab Name", "Sample Received On", "Health Facility Name", "Health Facility Code", "District/County", "Province/State", "Patient ID", "Patient Name", "Patient DoB", "Patient Age", "Patient Sex", "Sample Collection Date", "Is Sample Rejected?", "Rejection Reason", "Sample Tested On", "HCV VL Result", "HBV VL Result", "Date Result Dispatched", "Result Status", "Comments", "Funding Source", "Implementing Partner");
    } else {
        $headings = array("S. No.", "Sample ID", "Remote Sample ID", "Testing Lab Name", "Sample Received On", "Health Facility Name", "Health Facility Code", "District/County", "Province/State", "Patient DoB", "Patient Age", "Patient Sex", "Sample Collection Date", "Is Sample Rejected?", "Rejection Reason", "Sample Tested On", "HCV VL Result", "HBV VL Result", "Date Result Dispatched", "Result Status", "Comments", "Funding Source", "Implementing Partner");
    }
    if ($general->isStandaloneInstance() && ($key = array_search('Remote Sample ID', $headings)) !== false) {
        unset($headings[$key]);
    }

    $no = 1;
    $resultSet = $db->rawQuery($sessionQuery);
    foreach ($resultSet as $aRow) {
        $row = [];

        //Sex
        $gender = match (strtolower((string)$aRow['patient_gender'])) {
            'male', 'm' => 'M',
            'female', 'f' => 'F',
            'not_recorded', 'notrecorded', 'unreported' => 'Unreported',
            default => '',
        };

        //set sample rejection
        $sampleRejection = 'No';
        if (trim((string) $aRow['is_sample_rejected']) == 'yes' || ($aRow['reason_for_sample_rejection'] != null && trim((string) $aRow['reason_for_sample_rejection']) != '' && $aRow['reason_for_sample_rejection'] > 0)) {
            $sampleRejection = 'Yes';
        }

        if (!empty($aRow['patient_name'])) {
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
            $sourceOfArtPOE = str_replace("-", " ", (string) $aRow['source_of_alert']);
        } else {
            $sourceOfArtPOE = $aRow['source_of_alert_other'];
        }
        $row = [];
        $row[] = $no;
        if ($general->isStandaloneInstance()) {
            $row[] = $aRow["sample_code"];
        } else {
            $row[] = $aRow["sample_code"];
            $row[] = $aRow["remote_sample_code"];
        }
        $row[] = ($aRow['labName']);
        $row[] = DateUtility::humanReadableDateFormat($aRow['sample_received_at_lab_datetime'] ?? '');
        $row[] = ($aRow['facility_name']);
        $row[] = $aRow['facility_code'];
        $row[] = ($aRow['facility_district']);
        $row[] = ($aRow['facility_state']);
        if (isset($_POST['patientInfo']) && $_POST['patientInfo'] == 'yes') {
            if (!empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes') {
                $aRow['patient_id'] = $general->crypto('decrypt', $aRow['patient_id'], $key);
                $patientFname = $general->crypto('decrypt', $patientFname, $key);
                $patientLname = $general->crypto('decrypt', $patientLname, $key);
            }
            $row[] = $aRow['patient_id'];
            $row[] = $patientFname . " " . $patientLname;
        }
        $row[] = DateUtility::humanReadableDateFormat($aRow['patient_dob']);
        $row[] = ($aRow['patient_age'] != null && trim((string) $aRow['patient_age']) != '' && $aRow['patient_age'] > 0) ? $aRow['patient_age'] : 0;
        $row[] = $gender;
        $row[] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '');
        $row[] = $sampleRejection;
        $row[] = $aRow['rejection_reason'];
        $row[] = DateUtility::humanReadableDateFormat($aRow['sample_tested_datetime'] ?? '');
        $row[] = ($aRow['hcv_vl_count']);
        $row[] = ($aRow['hbv_vl_count']);
        $row[] = DateUtility::humanReadableDateFormat($aRow['result_printed_datetime'] ?? '');
        $row[] = $aRow['status_name'];
        $row[] = ($aRow['lab_tech_comments']);
        $row[] = $aRow['funding_source_name'] ?? null;
        $row[] = $aRow['i_partner_name'] ?? null;
        $output[] = $row;
        $no++;
    }


    if (isset($_SESSION['hepatitisRequestSearchResultQueryCount']) && $_SESSION['hepatitisRequestSearchResultQueryCount'] > 50000) {

        $fileName = TEMP_PATH . DIRECTORY_SEPARATOR . 'Hepatitis-Requests-' . date('d-M-Y-H-i-s') . '.csv';
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
        $fileName = TEMP_PATH . DIRECTORY_SEPARATOR . 'Hepatitis-Requests-' . date('d-M-Y-H-i-s') . '.xlsx';
        $writer->save($fileName);
        echo urlencode(basename($fileName));
    }
}
