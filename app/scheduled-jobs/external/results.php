<?php

$cliMode = php_sapi_name() === 'cli';

if ($cliMode) {
    require_once(__DIR__ . "/../../../bootstrap.php");
}
require_once(APPLICATION_PATH . '/../configs/config.interop.php');

ini_set('memory_limit', -1);
set_time_limit(0);
ini_set('max_execution_time', 300000);

use App\Utilities\DateUtility;
use App\Services\TestsService;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Services\ApiService;
use App\Registries\ContainerRegistry;
use App\Utilities\JsonUtility;

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
    $resultSet = $db->getOne($tableName);
    if ($resultSet) {
        $payload = [
            "id" => (int) $resultSet[$primaryKey],
            "formId" => (string) $resultSet["vlsm_country_id"],
            "uniqueId" => (string) $resultSet["unique_id"],
            "appSampleCode" => (string) $resultSet["app_sample_code"],
            "sampleCode" => (string) $resultSet["sample_code"],
            "remoteSampleCode" => (string) $resultSet["remote_sample_code"],
            "sampleCodeTitle" => "",
            "sampleReordered" => (string) $resultSet["sample_reordered"],
            "sampleCodeFormat" => (string) $resultSet["remote_sample_code_format"],
            "facilityId" => (string) $resultSet["facility_id"],
            "provinceId" => (string) $resultSet["province_id"],
            "serialNo" => (string) $resultSet["external_sample_code"],
            "clinicianName" => (string) $resultSet["request_clinician_name"],
            "clinicanTelephone" => (string) $resultSet["request_clinician_phone_number"],
            "patientFirstName" => (string) $resultSet["patient_first_name"],
            "patientMiddleName" => (string) $resultSet["patient_middle_name"],
            "patientLastName" => (string) $resultSet["patient_last_name"],
            "patientGender" => (string) $resultSet["patient_gender"],
            "patientDob" => (string) DateUtility::humanReadableDateFormat($resultSet["patient_dob"]),
            "ageInYears" => (string) $resultSet["patient_age_in_years"],
            "ageInMonths" => (string) $resultSet["patient_age_in_months"],
            "patientPregnant" => (string) $resultSet["is_patient_pregnant"] ?? "N/A",
            "trimester" => (string) $resultSet["pregnancy_trimester"],
            "isPatientNew" => (string) $resultSet["is_patient_new"],
            "breastfeeding" => (string) $resultSet["is_patient_breastfeeding"] ?? "N/A",
            "patientArtNo" => (string) $resultSet["patient_art_no"],
            "dateOfArtInitiation" => (string) DateUtility::humanReadableDateFormat($resultSet["treatment_initiated_date"]),
            "artRegimen" => (string) $resultSet["current_regimen"],
            "hasChangedRegimen" => (string) $resultSet["has_patient_changed_regimen"],
            "reasonForArvRegimenChange" => (string) $resultSet["reason_for_regimen_change"],
            "dateOfArvRegimenChange" => DateUtility::humanReadableDateFormat($resultSet["regimen_change_date"] ?? null),
            "regimenInitiatedOn" => DateUtility::humanReadableDateFormat($resultSet["date_of_initiation_of_current_regimen"]),
            "vlTestReason" => (string) $resultSet["reason_for_vl_testing"],
            "lastViralLoadResult" => (string) $resultSet["last_viral_load_result"],
            "lastViralLoadTestDate" => DateUtility::humanReadableDateFormat($resultSet["last_viral_load_date"] ?? null),
            "conservationTemperature" => "",
            "durationOfConservation" => (string) $resultSet["treatment_duration"],
            "dateOfCompletionOfViralLoad" => DateUtility::humanReadableDateFormat($resultSet["last_viral_load_result"] ?? null),
            "viralLoadNo" => (string) $resultSet["vl_test_number"],
            "patientPhoneNumber" => (string) $resultSet["patient_mobile_number"],
            "receiveSms" => (string) $resultSet["consent_to_receive_sms"],
            "specimenType" => (string) $resultSet["specimen_type"],
            "arvAdherence" => (string) $resultSet["arv_adherance_percentage"],
            "rmTestingLastVLDate" => (string) DateUtility::humanReadableDateFormat($resultSet["last_vl_date_routine"]),
            "rmTestingVlValue" => (string) $resultSet["last_vl_result_routine"],
            "repeatTestingLastVLDate" => DateUtility::humanReadableDateFormat($resultSet["last_vl_date_failure_ac"] ?? null),
            "repeatTestingVlValue" => (string) $resultSet["last_vl_result_failure_ac"],
            "suspendTreatmentLastVLDate" => DateUtility::humanReadableDateFormat($resultSet["last_vl_date_failure"] ?? null),
            "suspendTreatmentVlValue" => (string) $resultSet["last_vl_result_failure"],
            "reqClinician" => (string) $resultSet["request_clinician_name"],
            "reqClinicianPhoneNumber" => (string) $resultSet["request_clinician_phone_number"],
            "requestDate" => DateUtility::humanReadableDateFormat($resultSet["test_requested_on"] ?? null),
            "vlFocalPerson" => (string) $resultSet["vl_focal_person"],
            "vlFocalPersonPhoneNumber" => (string) $resultSet["vl_focal_person_phone_number"],
            "labId" => (string) $resultSet["lab_id"],
            "testingPlatform" => (string) $resultSet["vl_test_platform"],
            "sampleReceivedAtHubOn" => DateUtility::humanReadableDateFormat($resultSet["sample_received_at_hub_datetime"] ?? null, true, null, true),
            "sampleReceivedDate" => DateUtility::humanReadableDateFormat($resultSet["sample_received_at_lab_datetime"] ?? null, true, null, true),
            "sampleTestingDateAtLab" => DateUtility::humanReadableDateFormat($resultSet["sample_tested_datetime"] ?? null, true, null, true),
            "sampleDispatchedOn" => DateUtility::humanReadableDateFormat($resultSet["sample_dispatched_datetime"] ?? null, true, null, true),
            "resultDispatchedOn" => DateUtility::humanReadableDateFormat($resultSet["result_dispatched_datetime"] ?? null, true, null, true),
            "isSampleRejected" => (string) $resultSet["is_sample_rejected"],
            "rejectionReason" => $resultSet["reason_for_sample_rejection"] ?? null,
            "rejectionDate" => DateUtility::humanReadableDateFormat($resultSet["rejection_on"] ?? null),
            "vlResult" => (string) $resultSet["result_value_absolute"],
            "vlResultDecimal" => (string) $resultSet["result_value_absolute_decimal"],
            "result" => (string) $resultSet["result"],
            "revisedBy" => (string) $resultSet["revised_by"],
            "revisedOn" => DateUtility::humanReadableDateFormat($resultSet["revised_on"] ?? null, true, null, true),
            "reasonForVlResultChanges" => (string) $resultSet["reason_for_result_changes"],
            "vlLog" => (string) $resultSet["result_value_log"],
            "testedBy" => (string) $resultSet["tested_by"],
            "reviewedBy" => (string) $resultSet["result_reviewed_by"],
            "reviewedOn" => (string) DateUtility::humanReadableDateFormat($resultSet["result_reviewed_datetime"], true, null, true),
            "approvedBy" => (string) $resultSet["result_approved_by"],
            "approvedOnDateTime" => DateUtility::humanReadableDateFormat($resultSet["result_approved_datetime"] ?? null, true, null, true),
            "labComments" => (string) $resultSet["lab_tech_comments"],
            "resultStatus" => (string) $resultSet["result_status"],
            "fundingSource" => (string) $resultSet["funding_source"],
            "implementingPartner" => (string) $resultSet["implementing_partner"],
            "sampleCollectionDate" => DateUtility::humanReadableDateFormat($resultSet["sample_collection_date"] ?? null, true, null, true),
            "patientId" => (string) $resultSet["patient_art_no"],
            "createdAt" => DateUtility::humanReadableDateFormat($resultSet["request_created_datetime"] ?? null, true, null, true),
            "updatedAt" => DateUtility::humanReadableDateFormat($resultSet["last_modified_datetime"] ?? null, true, null, true)
        ];

        $payload = JsonUtility::toJSON($payload);

        $jsonResponse = $apiService->post(EXTERNAL_RESULTS_RECEIVER_URL, $payload, false);
    }
} catch (Exception $e) {
    LoggerUtility::logError($e->getFile() . ':' . $e->getLine() . ":" . $db->getLastError());
    LoggerUtility::logError($e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);
}
