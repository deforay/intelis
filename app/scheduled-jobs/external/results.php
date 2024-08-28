<?php

$cliMode = php_sapi_name() === 'cli';

if ($cliMode) {
    require_once __DIR__ . "/../../../bootstrap.php";
}

require_once APPLICATION_PATH . '/../configs/config.interop.php';

// ini_set('memory_limit', -1);
// set_time_limit(0);
// ini_set('max_execution_time', 300000);

use App\Services\ApiService;
use App\Services\TestsService;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var ApiService $apiService */
$apiService = ContainerRegistry::get(ApiService::class);
try {
    $tableName = TestsService::getTestTableName('vl');
    $primaryKey = TestsService::getTestPrimaryKeyColumn('vl');
    $resultStatus = [
        SAMPLE_STATUS\REJECTED,
        SAMPLE_STATUS\ACCEPTED
    ];
    $db->where("(result IS NOT NULL AND result != '')
                    OR IFNULL(is_sample_rejected, 'no') = 'yes'");
    $db->where("IFNULL(result_sent_to_external, 'no') = 'no'");
    $db->where("result_status", $resultStatus, 'IN');
    $resultSet = $db->get($tableName);

    foreach ($resultSet as $row) {
        $payload = [
            "id" => $row[$primaryKey],
            "formId" => (string) $row["vlsm_country_id"],
            "uniqueId" => (string) $row["unique_id"],
            "appSampleCode" => (string) $row["app_sample_code"],
            "sampleCode" => (string) $row["sample_code"],
            "remoteSampleCode" => (string) $row["remote_sample_code"],
            "sampleCodeTitle" => "",
            "sampleReordered" => (string) $row["sample_reordered"],
            "sampleCodeFormat" => (string) $row["remote_sample_code_format"],
            "facilityId" => (string) $row["facility_id"],
            "provinceId" => (string) $row["province_id"],
            "serialNo" => (string) $row["external_sample_code"],
            "clinicianName" => (string) $row["request_clinician_name"],
            "clinicanTelephone" => (string) $row["request_clinician_phone_number"],
            "patientFirstName" => (string) $row["patient_first_name"],
            "patientMiddleName" => (string) $row["patient_middle_name"],
            "patientLastName" => (string) $row["patient_last_name"],
            "patientGender" => (string) $row["patient_gender"],
            "patientDob" => (string) DateUtility::humanReadableDateFormat($row["patient_dob"]),
            "ageInYears" => (string) $row["patient_age_in_years"],
            "ageInMonths" => (string) $row["patient_age_in_months"],
            "patientPregnant" => (string) $row["is_patient_pregnant"] ?? "N/A",
            "trimester" => (string) $row["pregnancy_trimester"],
            "isPatientNew" => (string) $row["is_patient_new"],
            "breastfeeding" => (string) $row["is_patient_breastfeeding"] ?? "N/A",
            "patientArtNo" => (string) $row["patient_art_no"],
            "dateOfArtInitiation" => (string) DateUtility::humanReadableDateFormat($row["treatment_initiated_date"]),
            "artRegimen" => (string) $row["current_regimen"],
            "hasChangedRegimen" => (string) $row["has_patient_changed_regimen"],
            "reasonForArvRegimenChange" => (string) $row["reason_for_regimen_change"],
            "dateOfArvRegimenChange" => DateUtility::humanReadableDateFormat($row["regimen_change_date"] ?? null),
            "regimenInitiatedOn" => DateUtility::humanReadableDateFormat($row["date_of_initiation_of_current_regimen"]),
            "vlTestReason" => (string) $row["reason_for_vl_testing"],
            "lastViralLoadResult" => (string) $row["last_viral_load_result"],
            "lastViralLoadTestDate" => DateUtility::humanReadableDateFormat($row["last_viral_load_date"] ?? null),
            "conservationTemperature" => "",
            "durationOfConservation" => (string) $row["treatment_duration"],
            "dateOfCompletionOfViralLoad" => DateUtility::humanReadableDateFormat($row["last_viral_load_result"] ?? null),
            "viralLoadNo" => (string) $row["vl_test_number"],
            "patientPhoneNumber" => (string) $row["patient_mobile_number"],
            "receiveSms" => (string) $row["consent_to_receive_sms"],
            "specimenType" => (string) $row["specimen_type"],
            "arvAdherence" => (string) $row["arv_adherance_percentage"],
            "stViralTesting" => '',
            "rmTestingLastVLDate" => (string) DateUtility::humanReadableDateFormat($row["last_vl_date_routine"]),
            "rmTestingVlValue" => (string) $row["last_vl_result_routine"],
            "repeatTestingLastVLDate" => DateUtility::humanReadableDateFormat($row["last_vl_date_failure_ac"] ?? null),
            "repeatTestingVlValue" => (string) $row["last_vl_result_failure_ac"],
            "suspendTreatmentLastVLDate" => DateUtility::humanReadableDateFormat($row["last_vl_date_failure"] ?? null),
            "suspendTreatmentVlValue" => (string) $row["last_vl_result_failure"],
            "reqClinician" => (string) $row["request_clinician_name"],
            "reqClinicianPhoneNumber" => (string) $row["request_clinician_phone_number"],
            "requestDate" => DateUtility::humanReadableDateFormat($row["test_requested_on"] ?? null),
            "vlFocalPerson" => (string) $row["vl_focal_person"],
            "vlFocalPersonPhoneNumber" => (string) $row["vl_focal_person_phone_number"],
            "labId" => (string) $row["lab_id"],
            "testingPlatform" => (string) $row["vl_test_platform"],
            "sampleReceivedAtHubOn" => DateUtility::humanReadableDateFormat($row["sample_received_at_hub_datetime"] ?? null, true, null, true),
            "sampleReceivedDate" => DateUtility::humanReadableDateFormat($row["sample_received_at_lab_datetime"] ?? null, true, null, true),
            "sampleTestingDateAtLab" => DateUtility::humanReadableDateFormat($row["sample_tested_datetime"] ?? null, true, null, true),
            "sampleDispatchedOn" => DateUtility::humanReadableDateFormat($row["sample_dispatched_datetime"] ?? null, true, null, true),
            "resultDispatchedOn" => DateUtility::humanReadableDateFormat($row["result_dispatched_datetime"] ?? null, true, null, true),
            "isSampleRejected" => (string) $row["is_sample_rejected"],
            "rejectionReason" => $row["reason_for_sample_rejection"] ?? null,
            "rejectionDate" => DateUtility::humanReadableDateFormat($row["rejection_on"] ?? null),
            "vlResult" => (string) $row["result_value_absolute"],
            "vlResultDecimal" => (string) $row["result_value_absolute_decimal"],
            "result" => (string) $row["result"],
            "revisedBy" => (string) $row["revised_by"],
            "revisedOn" => DateUtility::humanReadableDateFormat($row["revised_on"] ?? null, true, null, true),
            "reasonForVlResultChanges" => (string) $row["reason_for_result_changes"],
            "vlLog" => (string) $row["result_value_log"],
            "testedBy" => (string) $row["tested_by"],
            "reviewedBy" => (string) $row["result_reviewed_by"],
            "reviewedOn" => (string) DateUtility::humanReadableDateFormat($row["result_reviewed_datetime"], true, null, true),
            "approvedBy" => (string) $row["result_approved_by"],
            "approvedOnDateTime" => DateUtility::humanReadableDateFormat($row["result_approved_datetime"] ?? null, true, null, true),
            "labComments" => (string) $row["lab_tech_comments"],
            "resultStatus" => (string) $row["result_status"],
            "fundingSource" => (string) $row["funding_source"],
            "implementingPartner" => (string) $row["implementing_partner"],
            "sampleCollectionDate" => DateUtility::humanReadableDateFormat($row["sample_collection_date"] ?? null, true, null, true),
            "patientId" => (string) $row["patient_art_no"],
            "createdAt" => DateUtility::humanReadableDateFormat($row["request_created_datetime"] ?? null, true, null, true),
            "updatedAt" => DateUtility::humanReadableDateFormat($row["last_modified_datetime"] ?? null, true, null, true)
        ];

        $payload = JsonUtility::encodeUtf8Json($payload);

        $response = $apiService->post(EXTERNAL_RESULTS_RECEIVER_URL, $payload, gzip: false, getRawResponse: true);
    }
} catch (Exception $e) {
    if ($cliMode) {
        echo $e->getFile() . ':' . $e->getLine() . ":" . $e->getMessage();
    }
    LoggerUtility::logError($e->getFile() . ':' . $e->getLine() . ":" . $db->getLastError());
    LoggerUtility::logError($e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);
}
