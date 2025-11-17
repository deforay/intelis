<?php

use const COUNTRY\CAMEROON;
use const COUNTRY\DRC;
use App\Services\EidService;
use App\Registries\ContainerRegistry;
use App\Utilities\MiscUtility;
use App\Services\DatabaseService;
use App\Services\CommonService;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use App\Utilities\DateUtility;


ini_set('memory_limit', '512M');
set_time_limit(300);
ini_set('max_execution_time', 300);


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


if (isset($_POST['patientInfo']) && $_POST['patientInfo'] == 'yes') {
    $headings = ["S.No.", "Sample ID", "Remote Sample ID", "Health Facility Name", "Health Facility Code", "District/County", "Province/State", "Testing Lab Name (Hub)", "Sample Received On", "Child ID", "Child Name", "Mother ID", "Child Date of Birth", "Child Age", "Child Sex", "Breastfeeding", "Clinician's Phone Number", "PCR Test Performed Before", "Last PCR Test results", "Reason For PCR Test", "Sample Collection Date", "Sample Requestor Phone Number", "EID Number", "Is Sample Rejected?", "Freezer", "Rack", "Box", "Position", "Volume (ml)", "Sample Tested On", "Result", "Lab Assigned Code", "Date Result Dispatched", "Comments", "Funding Source", "Implementing Partner", "Request Created On"];
} else {
    $headings = ["S.No.", "Sample ID", "Remote Sample ID", "Health Facility Name", "Health Facility Code", "District/County", "Province/State", "Testing Lab Name (Hub)", "Sample Received On", "Child Date of Birth", "Child Age", "Child Sex", "Breastfeeding", "Clinician's Phone Number", "PCR Test Performed Before", "Last PCR Test results", "Reason For PCR Test", "Sample Collection Date", "Sample Requestor Phone Number", "EID Number", "Is Sample Rejected?", "Freezer", "Rack", "Box", "Position", "Volume (ml)", "Sample Tested On", "Result", "Lab Assigned Code", "Date Result Dispatched", "Comments", "Funding Source", "Implementing Partner", "Request Created On"];
}


if ($general->isStandaloneInstance() && ($key = array_search("Remote Sample ID", $headings)) !== false) {
    unset($headings[$key]);
}

if ($formId != CAMEROON) {
    $headings = MiscUtility::removeMatchingElements($headings, [_translate("Lab Assigned Code")]);
}
if ($formId != DRC) {
    $headings = MiscUtility::removeMatchingElements($headings, ["Freezer", "Rack", "Box", "Position", "Volume (ml)"]);
}



$buildRow = function ($aRow, $no) use ($general, $key, $formId, $globalConf): array {

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
    if (trim((string) $aRow['is_sample_rejected']) === 'yes' || ($aRow['reason_for_sample_rejection'] != null && trim((string) $aRow['reason_for_sample_rejection']) !== '' && $aRow['reason_for_sample_rejection'] > 0)) {
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
    $row[] = ($aRow['child_age'] != null && trim((string) $aRow['child_age']) !== '' && $aRow['child_age'] > 0) ? $aRow['child_age'] : 0;
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
    if ($formId == DRC) {
        $formAttributes = empty($aRow['form_attributes']) ? null : json_decode((string) $aRow['form_attributes']);
        $storageObj = isset($formAttributes->storage) ? json_decode($formAttributes->storage) : null;

        $row[] = $storageObj->storageCode ?? '';
        $row[] = $storageObj->rack ?? '';
        $row[] = $storageObj->box ?? '';
        $row[] = $storageObj->position ?? '';
        $row[] = $storageObj->volume ?? '';
    }
    $row[] = DateUtility::humanReadableDateFormat($aRow['sample_tested_datetime'] ?? '');
    $row[] = $eidResults[$aRow['result']] ?? $aRow['result'];
    if ($formId == CAMEROON) {
        $row[] = ($aRow['lab_assigned_code']);
    }
    $row[] = DateUtility::humanReadableDateFormat($aRow['result_printed_datetime'] ?? '');
    $row[] = $aRow['lab_tech_comments'];
    $row[] = $aRow['funding_source_name'] ?? null;
    $row[] = $aRow['i_partner_name'] ?? null;
    $row[] = DateUtility::humanReadableDateFormat($aRow['request_created_datetime'], true);
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
	$processedHeadings = array_map(function ($value): string|array|null {
		$string = str_replace(' ', '', $value);
		return preg_replace('/[^A-Za-z0-9\-]/', '', $string);
	}, $headings);
}

$filename = TEMP_PATH . DIRECTORY_SEPARATOR . 'VLSM-EID-REQUESTS-' . date('d-M-Y-H-i-s') . '-' . MiscUtility::generateRandomString(6) . '.xlsx';

$writer = new Writer();
$writer->openToFile($filename);

// Write filter info row
$writer->addRow(Row::fromValues([html_entity_decode($nameValue)]));

// Empty row for spacing
$writer->addRow(Row::fromValues(['']));

// Write headings
$writer->addRow(Row::fromValues(array_map('html_entity_decode', $processedHeadings)));

// Stream data
$resultSet = $db->rawQueryGenerator($_SESSION['eidRequestSearchResultQuery']);
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
