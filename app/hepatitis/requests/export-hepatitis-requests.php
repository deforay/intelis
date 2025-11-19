<?php



ini_set('memory_limit', -1);
set_time_limit(0);
ini_set('max_execution_time', 300000);

use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Utilities\MiscUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use OpenSpout\Writer\XLSX\Writer;
use OpenSpout\Common\Entity\Row;


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$arr = $general->getGlobalConfig();
$key = (string) $general->getGlobalConfig('key');



$sessionQuery = $_SESSION['hepatitisRequestSearchResultQuery'];
if (isset($sessionQuery) && trim((string) $sessionQuery) !== "") {


    $output = [];
    if (isset($_POST['patientInfo']) && $_POST['patientInfo'] == 'yes') {
        $headings = ["S. No.", "Sample ID", "Remote Sample ID", "Testing Lab Name", "Sample Received On", "Health Facility Name", "Health Facility Code", "District/County", "Province/State", "Patient ID", "Patient Name", "Patient DoB", "Patient Age", "Patient Sex", "Sample Collection Date", "Is Sample Rejected?", "Rejection Reason", "Sample Tested On", "HCV VL Result", "HBV VL Result", "Date Result Dispatched", "Result Status", "Comments", "Funding Source", "Implementing Partner"];
    } else {
        $headings = ["S. No.", "Sample ID", "Remote Sample ID", "Testing Lab Name", "Sample Received On", "Health Facility Name", "Health Facility Code", "District/County", "Province/State", "Patient DoB", "Patient Age", "Patient Sex", "Sample Collection Date", "Is Sample Rejected?", "Rejection Reason", "Sample Tested On", "HCV VL Result", "HBV VL Result", "Date Result Dispatched", "Result Status", "Comments", "Funding Source", "Implementing Partner"];
    }
    if ($general->isStandaloneInstance() && ($key = array_search('Remote Sample ID', $headings)) !== false) {
        unset($headings[$key]);
    }


    $buildRow = function ($aRow, $no) use ($general, $key): array {
        $row = [];

        //Sex
        $gender = match (strtolower((string) $aRow['patient_gender'])) {
            'male', 'm' => 'M',
            'female', 'f' => 'F',
            'not_recorded', 'notrecorded', 'unreported' => 'Unreported',
            default => '',
        };

        //set sample rejection
        $sampleRejection = 'No';
        if (trim((string) $aRow['is_sample_rejected']) === 'yes' || ($aRow['reason_for_sample_rejection'] != null && trim((string) $aRow['reason_for_sample_rejection']) !== '' && $aRow['reason_for_sample_rejection'] > 0)) {
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
        $row[] = ($aRow['patient_age'] != null && trim((string) $aRow['patient_age']) !== '' && $aRow['patient_age'] > 0) ? $aRow['patient_age'] : 0;
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
        return $row;
    };



    // Build filter info for header row
    $nameValue = '';
    foreach ($_POST as $key => $value) {
        if (trim((string) $value) !== '' && trim((string) $value) !== '-- Select --') {
            $nameValue .= str_replace("_", " ", $key) . " : " . $value . "  ";
        }
    }

    // Prepare headings (with alpha-numeric conversion if requested)
    $processedHeadings = $headings;
    if (isset($_POST['withAlphaNum']) && $_POST['withAlphaNum'] == 'yes') {
        $processedHeadings = array_map(function ($value): array|string|null {
            $string = str_replace(' ', '', $value);
            return preg_replace('/[^A-Za-z0-9\-]/', '', $string);
        }, $headings);
    }

    $filename = TEMP_PATH . DIRECTORY_SEPARATOR . 'InteLIS-HEPATITIS-REQUESTS-' . date('d-M-Y-H-i-s') . '-' . MiscUtility::generateRandomString(6) . '.xlsx';

    $writer = new Writer();
    $writer->openToFile($filename);

    // Write filter info row
    $writer->addRow(Row::fromValues([html_entity_decode($nameValue)]));

    // Empty row for spacing
    $writer->addRow(Row::fromValues(['']));

    // Write headings
    $writer->addRow(Row::fromValues(array_map('html_entity_decode', $processedHeadings)));

    // Stream data
    $resultSet = $db->rawQueryGenerator($sessionQuery);
    $no = 1;

    foreach ($resultSet as $aRow) {
        $row = $buildRow($aRow, $no++);
        $writer->addRow(Row::fromValues($row));

        // Periodic garbage collection
        if ($no % 5000 === 0) {
            gc_collect_cycles();
        }
    }

    $writer->close();

    echo urlencode(basename($filename));

}