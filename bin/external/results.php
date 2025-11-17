<?php

use const SAMPLE_STATUS\REJECTED;
use const SAMPLE_STATUS\ACCEPTED;

// This script is used to send results to an external API for e.g. EMR

$cliMode = php_sapi_name() === 'cli';
$forceRun = false;

if ($cliMode) {
    require_once __DIR__ . "/../../bootstrap.php";

    declare(ticks=1);

    // Check for the force flag in command-line arguments
    $options = getopt("f", ["force"]);
    $forceRun = isset($options['f']) || isset($options['force']);
}


$configFile = APPLICATION_PATH . '/../configs/config.interop.php';
if (file_exists($configFile)) {
    require_once $configFile;
} else {
    if ($cliMode) {
        echo "Interop config file is missing." . PHP_EOL;
    }
    exit(0);
}

if (!defined('EXTERNAL_RESULTS_RECEIVER_URL')) {
    if ($cliMode) {
        echo "EXTERNAL_RESULTS_RECEIVER_URL constant is not defined in $configFile" . PHP_EOL;
    }
    exit(0);
}

use App\Services\ApiService;
use App\Services\TestsService;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

$lockFile = MiscUtility::getLockFile(__FILE__);

// If the force flag is set, delete the lock file if it exists
if ($forceRun && MiscUtility::fileExists($lockFile)) {
    MiscUtility::deleteLockFile($lockFile);
}

// Check if the lock file already exists
if (!MiscUtility::isLockFileExpired($lockFile)) {
    if ($cliMode) {
        echo "Another instance of the script is already running." . PHP_EOL;
    }
    exit;
}

MiscUtility::touchLockFile($lockFile); // Create or update the lock file
MiscUtility::setupSignalHandler($lockFile);

// ini_set('memory_limit', -1);
// set_time_limit(0);
// ini_set('max_execution_time', 300000);


$transactionId = MiscUtility::generateULID();

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var ApiService $apiService */
$apiService = ContainerRegistry::get(ApiService::class);

$dateFormat = "d-M-Y H:i:s";

try {
    $tableName = TestsService::getTestTableName('vl');
    $primaryKey = TestsService::getPrimaryColumn('vl');
    $resultStatus = [
        REJECTED,
        ACCEPTED
    ];

    $db->where("((result IS NOT NULL AND result != '')
                    OR IFNULL(is_sample_rejected, 'no') = 'yes')");
    $db->where("app_sample_code IS NOT NULL");
    $db->where("(IFNULL(result_sent_to_source, 'pending') = 'pending')");
    $db->where("result_status", $resultStatus, 'IN');
    $resultSet = $db->get($tableName, 100);
    $numberOfResults = count($resultSet ?? []);
    $numberSent = 0;
    $resultsSentSuccesfully = [];
    $counter = 0;
    foreach ($resultSet as $row) {

        $counter++;
        // This is to prevent the lock file from being deleted by the signal handler
        // and to keep the script running
        // touch the lock file every 10 iterations to reduce the number of times disk is accessed
        if ($counter % 10 === 0) {
            MiscUtility::touchLockFile($lockFile);
        }

        $payload = [
            "id" => $row[$primaryKey],
            "formId" => $row["vlsm_country_id"],
            "uniqueId" => $row["unique_id"],
            "appSampleCode" => $row["app_sample_code"],
            "sampleCode" => $row["sample_code"],
            "remoteSampleCode" => $row["remote_sample_code"],
            "sampleCodeTitle" => "",
            "sampleReordered" => $row["sample_reordered"],
            "sampleCodeFormat" => $row["remote_sample_code_format"],
            "facilityId" => $row["facility_id"],
            "provinceId" => $row["province_id"],
            "serialNo" => $row["external_sample_code"],
            "clinicianName" => $row["request_clinician_name"],
            "clinicanTelephone" => $row["request_clinician_phone_number"],
            "patientFirstName" => $row["patient_first_name"],
            "patientMiddleName" => $row["patient_middle_name"],
            "patientLastName" => $row["patient_last_name"],
            "patientGender" => $row["patient_gender"],
            "patientDob" => DateUtility::humanReadableDateFormat($row["patient_dob"]),
            "ageInYears" => $row["patient_age_in_years"],
            "ageInMonths" => $row["patient_age_in_months"],
            "patientPregnant" => $row["is_patient_pregnant"] ?? "N/A",
            "trimester" => $row["pregnancy_trimester"],
            "isPatientNew" => $row["is_patient_new"],
            "breastfeeding" => $row["is_patient_breastfeeding"] ?? "N/A",
            "patientArtNo" => $row["patient_art_no"],
            "dateOfArtInitiation" => DateUtility::humanReadableDateFormat($row["treatment_initiated_date"]),
            "artRegimen" => $row["current_regimen"],
            "hasChangedRegimen" => $row["has_patient_changed_regimen"],
            "reasonForArvRegimenChange" => $row["reason_for_regimen_change"],
            "dateOfArvRegimenChange" => DateUtility::humanReadableDateFormat($row["regimen_change_date"] ?? null),
            "regimenInitiatedOn" => DateUtility::humanReadableDateFormat($row["date_of_initiation_of_current_regimen"]),
            "vlTestReason" => $row["reason_for_vl_testing"],
            "lastViralLoadResult" => $row["last_viral_load_result"],
            "lastViralLoadTestDate" => DateUtility::humanReadableDateFormat($row["last_viral_load_date"] ?? null),
            "conservationTemperature" => "",
            "durationOfConservation" => $row["treatment_duration"],
            "dateOfCompletionOfViralLoad" => DateUtility::humanReadableDateFormat($row["last_viral_load_result"] ?? null),
            "viralLoadNo" => $row["vl_test_number"],
            "patientPhoneNumber" => $row["patient_mobile_number"],
            "receiveSms" => $row["consent_to_receive_sms"],
            "specimenType" => $row["specimen_type"],
            "arvAdherence" => $row["arv_adherance_percentage"],
            "stViralTesting" => '',
            "rmTestingLastVLDate" => DateUtility::humanReadableDateFormat($row["last_vl_date_routine"]),
            "rmTestingVlValue" => $row["last_vl_result_routine"],
            "repeatTestingLastVLDate" => DateUtility::humanReadableDateFormat($row["last_vl_date_failure_ac"] ?? null),
            "repeatTestingVlValue" => $row["last_vl_result_failure_ac"],
            "suspendTreatmentLastVLDate" => DateUtility::humanReadableDateFormat($row["last_vl_date_failure"] ?? null),
            "suspendTreatmentVlValue" => $row["last_vl_result_failure"],
            "reqClinician" => $row["request_clinician_name"],
            "reqClinicianPhoneNumber" => $row["request_clinician_phone_number"],
            "requestDate" => null,
            "vlFocalPerson" => $row["vl_focal_person"],
            "vlFocalPersonPhoneNumber" => $row["vl_focal_person_phone_number"],
            "labId" => $row["lab_id"],
            "testingPlatform" => $row["vl_test_platform"],
            "sampleReceivedAtHubOn" => DateUtility::humanReadableDateFormat($row["sample_received_at_hub_datetime"] ?? null, true, $dateFormat),
            "sampleReceivedDate" => DateUtility::humanReadableDateFormat($row["sample_received_at_lab_datetime"] ?? null, true, $dateFormat),
            "sampleTestingDateAtLab" => DateUtility::humanReadableDateFormat($row["sample_tested_datetime"] ?? null, true, $dateFormat),
            "sampleDispatchedOn" => DateUtility::humanReadableDateFormat($row["sample_dispatched_datetime"] ?? null, true, $dateFormat),
            "resultDispatchedOn" => DateUtility::humanReadableDateFormat($row["test_requested_on"] ?? null, true, $dateFormat),
            "isSampleRejected" => $row["is_sample_rejected"],
            "rejectionReason" => $row["reason_for_sample_rejection"] ?? null,
            "rejectionDate" => DateUtility::humanReadableDateFormat($row["rejection_on"] ?? null),
            "vlResult" => $row["result_value_absolute"],
            "vlResultDecimal" => $row["result_value_absolute_decimal"],
            "result" => $row["result"],
            "revisedBy" => $row["revised_by"],
            "revisedOn" => DateUtility::humanReadableDateFormat($row["revised_on"] ?? null, true, $dateFormat),
            "reasonForVlResultChanges" => $row["reason_for_result_changes"],
            "vlLog" => $row["result_value_log"],
            "testedBy" => $row["tested_by"],
            "reviewedBy" => $row["result_reviewed_by"],
            "reviewedOn" => DateUtility::humanReadableDateFormat($row["result_reviewed_datetime"], true),
            "approvedBy" => $row["result_approved_by"],
            "approvedOnDateTime" => DateUtility::humanReadableDateFormat($row["result_approved_datetime"] ?? null, true, $dateFormat),
            "labComments" => $row["lab_tech_comments"],
            "resultStatus" => $row["result_status"],
            "fundingSource" => $row["funding_source"],
            "implementingPartner" => $row["implementing_partner"],
            "sampleCollectionDate" => DateUtility::humanReadableDateFormat($row["sample_collection_date"] ?? null, true, $dateFormat),
            "patientId" => $row["patient_art_no"],
            "createdAt" => DateUtility::humanReadableDateFormat($row["request_created_datetime"] ?? null, true, $dateFormat),
            "updatedAt" => DateUtility::humanReadableDateFormat($row["last_modified_datetime"] ?? null, true, $dateFormat)
        ];

        //$payload = JsonUtility::encodeUtf8Json($payload);
        $apiResponse = $apiService->post(url: EXTERNAL_RESULTS_RECEIVER_URL, payload: $payload, gzip: false, returnWithStatusCode: true);

        if (!empty($apiResponse['httpStatusCode']) && $apiResponse['httpStatusCode'] === 200) {
            $resultsSentSuccesfully[] = $row['unique_id'];
        }
        if ($cliMode) {
            echo "PATIENT ART NO. : " . $row["patient_art_no"] . PHP_EOL;
            echo "SAMPLE ID : " . ($row["remote_sample_code"] ?? $row["sample_code"]) . PHP_EOL;
            echo "HTTP RESPONSE CODE : " . $apiResponse["httpStatusCode"] . PHP_EOL;
            echo "RESPONSE : " . JsonUtility::prettyJson($apiResponse['body']) . PHP_EOL;
            echo "_______________________________________________________________________" . PHP_EOL;
        }
        $general->addApiTracking($transactionId, null, 1, 'external-results', 'vl', EXTERNAL_RESULTS_RECEIVER_URL, $payload ?? [], $apiResponse['body'] ?? [], 'json');
    }
    if ($resultsSentSuccesfully !== []) {
        $numberSent = count($resultsSentSuccesfully);
        $db->where('unique_id', $resultsSentSuccesfully, 'IN');
        $db->update($tableName, [
            'result_sent_to_external' => 'yes',
            'result_sent_to_external_datetime' => DateUtility::getCurrentDateTime(),
            'result_sent_to_source' => 'sent',
            'result_sent_to_source_datetime' => DateUtility::getCurrentDateTime()
        ]);
    }

    if ($cliMode) {
        echo "Number of results sent: " . $numberSent . PHP_EOL;
    }
} catch (Exception $e) {
    if ($cliMode) {
        echo "Some or all results could not be sent" . PHP_EOL;
    }
    LoggerUtility::logError($e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'last_db_error' => $db->getLastError(),
        'last_db_query' => $db->getLastQuery(),
        'trace' => $e->getTraceAsString(),
    ]);
} finally {
    // Delete the lock file after execution completes
    MiscUtility::deleteLockFile(__FILE__);
}
