<?php

use App\Services\ApiService;
use App\Services\TestsService;
use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Services\GenericTestsService;

ini_set('memory_limit', -1);
set_time_limit(0);
ini_set('max_execution_time', 20000);


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

/** @var GenericTestsService $genericService */
$genericService = ContainerRegistry::get(GenericTestsService::class);

/** @var ApiService $apiService */
$apiService = ContainerRegistry::get(ApiService::class);

/** @var Slim\Psr7\Request $request */
$request = AppRegistry::get('request');


$origJson = $apiService->getJsonFromRequest($request);
if (JsonUtility::isJSON($origJson) === false) {
    throw new SystemException("Invalid JSON Payload", 400);
}
$input = JsonUtility::decodeJson($origJson, true);


$user = null;

$primaryKey = TestsService::getPrimaryColumn('generic-tests');
$tableName = TestsService::getTestTableName('generic-tests');

/* For API Tracking params */
$requestUrl = $_SERVER['HTTP_HOST'];
$requestUrl .= $_SERVER['REQUEST_URI'];
$authToken = ApiService::extractBearerToken($request);
$user = $usersService->findUserByApiToken($authToken);
try {
    $transactionId = MiscUtility::generateULID();
    $sQuery = "SELECT
        vl.sample_id as testRequestId,
        vl.unique_id as uniqueId,
        vl.app_sample_code as appSampleCode,
        vl.test_type as testType,
        vl.sub_tests as subTests,
        vl.test_type_form as testTypeForm,
        vl.sample_code as sampleCode,
        vl.remote_sample_code as remoteSampleCode,
        vl.lab_assigned_code as labAssignedCode,
        vl.external_sample_code as externalSampleCode,
        vl.facility_id as facilityId,
        f.facility_name as facilityName,
        gdp.geo_name as province,
        vl.facility_sample_id as facilitySampleId,
        b.batch_code as batchCode,
        p.package_code as samplePackageCode,
        vl.test_urgency as testUrgency,
        vl.funding_source as fundingSource,
        r_f_s.funding_source_name as fundingSourceName,
        vl.implementing_partner as implementingPartner,
        r_i_p.i_partner_name as implementingPartnerName,
        vl.system_patient_code as systemPatientCode,
        vl.patient_id as patientId,
        vl.patient_first_name as firstName,
        vl.patient_middle_name as middleName,
        vl.patient_last_name as lastName,
        vl.patient_attendant as patientAttendant,
        vl.patient_nationality as patientNationality,
        vl.patient_address as patientAddress,
        vl.patient_dob as patientDob,
        vl.patient_gender as patientGender,
        vl.patient_mobile_number as patientMobileNumber,
        vl.patient_location as patientLocation,
        pgdd.geo_name as patientProvince,
        pgdp.geo_name as patientDistrict,
        vl.patient_group as patientGroup,
        vl.laboratory_number as laboratoryNumber,
        vl.is_encrypted as isEncrypted,
        vl.sample_collection_date as sampleCollectionDate,
        vl.sample_dispatched_datetime as sampleDispatchedOn,
        vl.specimen_type as specimenType,
        vl.treatment_initiation as treatmentInitiation,
        vl.is_patient_pregnant as isPatientPregnant,
        vl.is_patient_breastfeeding as isPatientBreastfeeding,
        vl.pregnancy_trimester as pregnancyTrimester,
        vl.consent_to_receive_sms as consentToReceiveSms,
        vl.request_clinician_name as clinicianName,
        vl.test_requested_on as testRequestedOn,
        vl.request_clinician_phone_number as clinicianPhone,
        vl.sample_testing_date as sampleTestingDate,
        vl.testing_lab_focal_person as testingLabFocalPerson,
        vl.testing_lab_focal_person_phone_number as testingLabFocalPersonPhoneNumber,
        vl.sample_received_at_hub_datetime as sample_received_at_hub_datetime,
        vl.result_dispatched_datetime as resultDispatchedOn,
        vl.is_sample_rejected as sampleRejected,
        vl.sample_rejection_facility as sampleRejectionFacility,
        vl.reason_for_sample_rejection as rejectionReasonId,
        vl.recommended_corrective_action as recommendedCorrectiveAction,
        vl.rejection_on as rejectionDate,
        r_c_b.user_name as requestCreatedBy,
        l_m_b.user_name as lastModifiedBy,
        vl.last_modified_datetime as lastModifiedDatetime,
        vl.request_created_datetime as requestedDate,
        vl.patient_other_id as patientOtherId,
        vl.patient_age_in_years as patientAgeInYears,
        vl.patient_age_in_months as patientAgeInMonths,
        vl.treatment_initiated_date as treatmentInitiatedDate,
        vl.treatment_indication as treatmentIndication,
        vl.treatment_details as treatmentDetails,
        vl.lab_id as labId,
        l_f.facility_name as labName,
        vl.samples_referred_datetime as samplesReferredDatetime,
        vl.referring_lab_id as referringLabId,
        vl.lab_technician as labTechnician,
        vl.lab_contact_person as labContactPerson,
        vl.lab_phone_number as labPhoneNumber,
        vl.sample_tested_datetime as sampleTestedDate,
        vl.result,
        vl.result_unit as resultUnit,
        vl.final_result_interpretation as finalResultInterpretation,
        vl.result_status as resultStatus,
        vl.approver_comments as approverComments,
        vl.reason_for_test_result_changes as reasonForGenericResultChanges,
        vl.lot_number as lotNumber,
        vl.lot_expiration_date as lotExpirationDate,
        t_b.user_name as testedBy,
        vl.lab_tech_comments as approverComments,
        a_u_d.user_name as approvedBy,
        vl.revised_on as revisedOn,
        r_r_b.user_name as revisedBy,
        u_d.user_name as reviewedBy,
        vl.result_reviewed_datetime as reviewedOn,
        vl.test_methods as testMethods,
        vl.reason_for_testing as reasonForGenericTest,
        s_c_b.user_name as sampleCollectedBy,
        vl.test_platform as testPlatform,
        vl.test_number as testNumber,
        vl.result_printed_datetime as resultPrintedDate,
        vl.result_printed_on_sts_datetime as resultPrintedOnStsDatetime,
        vl.result_printed_on_lis_datetime as resultPrintedOnLisDatetime,
        gdd.geo_name as district,
        vl.sample_received_at_lab_datetime as sampleReceivedDate,
        rs.rejection_reason_name as rejectionReason,
        vl.is_sample_rejected as isSampleRejected,
        vl.last_modified_datetime as updatedOn,
        ts.status_name as resultStatusName,
        vl.result_approved_datetime as approvedOn,
        lt_u_d.user_name as labTechnicianName

        FROM form_generic as vl
        LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
        LEFT JOIN facility_details as l_f ON vl.lab_id=l_f.facility_id
        LEFT JOIN geographical_divisions as gdd ON f.facility_district_id=gdd.geo_id
        LEFT JOIN geographical_divisions as gdp ON vl.province_id=gdp.geo_id
        LEFT JOIN geographical_divisions as pgdp ON vl.patient_district=pgdp.geo_id
        LEFT JOIN geographical_divisions as pgdd ON vl.patient_province=pgdd.geo_id
        LEFT JOIN r_sample_status as ts ON ts.status_id=vl.result_status
        LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id
        LEFT JOIN user_details as u_d ON u_d.user_id=vl.result_reviewed_by
        LEFT JOIN user_details as a_u_d ON a_u_d.user_id=vl.result_approved_by
        LEFT JOIN user_details as r_r_b ON r_r_b.user_id=vl.revised_by
        LEFT JOIN user_details as lt_u_d ON lt_u_d.user_id=vl.lab_technician
        LEFT JOIN user_details as t_b ON t_b.user_id=vl.tested_by
        LEFT JOIN user_details as r_c_b ON r_c_b.user_id=vl.request_created_by
        LEFT JOIN user_details as l_m_b ON l_m_b.user_id=vl.last_modified_by
        LEFT JOIN user_details as s_c_b ON s_c_b.user_id=vl.sample_collected_by
        LEFT JOIN specimen_manifests as p ON p.package_id=vl.sample_package_id
        LEFT JOIN r_generic_sample_types as rst ON rst.sample_type_id=vl.specimen_type
        LEFT JOIN r_generic_sample_rejection_reasons as rs ON rs.rejection_reason_id=vl.reason_for_sample_rejection
        LEFT JOIN r_funding_sources as r_f_s ON r_f_s.funding_source_id=vl.funding_source
        LEFT JOIN r_implementation_partners as r_i_p ON r_i_p.i_partner_id=vl.implementing_partner";


    $where = [];
    if (!empty($user)) {
        $facilityMap = $facilitiesService->getUserFacilityMap($user['user_id'], 1);
        if (!empty($facilityMap)) {
            $where[] = " vl.facility_id IN (" . $facilityMap . ")";
        } else {
            $where[] = " (request_created_by = '" . $user['user_id'] . "')";
        }
    }

    /* To check the uniqueId filter */
    $uniqueId = $input['uniqueId'] ?? [];
    if (!empty($uniqueId)) {
        $uniqueId = implode("','", $uniqueId);
        $where[] = " unique_id IN ('$uniqueId')";
    }

    /* To check the sample id filter */
    $sampleCode = $input['sampleCode'] ?? [];
    if (!empty($sampleCode)) {
        $sampleCode = implode("','", $sampleCode);
        $where[] = " (sample_code IN ('$sampleCode') OR remote_sample_code IN ('$sampleCode') )";
    }
    /* To check the facility and date range filter */
    $from = $input['sampleCollectionDate'][0];
    $to = $input['sampleCollectionDate'][1];
    if (!empty($from) && !empty($to)) {
        $where[] = " DATE(sample_collection_date) between '$from' AND '$to' ";
    }

    if (!empty($input['lastModifiedDateTime'])) {
        $where[] = " DATE(vl.request_created_datetime) >= '" . DateUtility::isoDateFormat($input['lastModifiedDateTime']) . "'";
    }

    $facilityId = $input['facility'] ?? [];
    if (!empty($facilityId)) {
        $facilityId = implode("','", $facilityId);
        $where[] = " vl.facility_id IN ('$facilityId') ";
    }
    if (!empty($input['patientId'])) {
        $patientId = implode("','", $input['patientId']);
        $where[] = " vl.patient_id IN ('" . $patientId . "') ";
    }

    if (!empty($input['patientName'])) {
        $where[] = " (vl.patient_first_name like '" . $input['patientName'] . "' OR vl.patient_last_name like '" . $input['patientName'] . "')";
    }

    $sampleStatus = $input['sampleStatus'];
    if (!empty($sampleStatus)) {
        $sampleStatus = implode("','", $sampleStatus);
        $where[] = " result_status IN ('$sampleStatus') ";
    }
    $whereStr = "";
    if (!empty($where)) {
        $whereStr = " WHERE " . implode(" AND ", $where);
    }
    $sQuery .= "$whereStr ORDER BY vl.last_modified_datetime DESC limit 100 ";
    $rowData = $db->rawQuery($sQuery);

    if (!empty($rowData)) {
        foreach ($rowData as $key => $row) {
            $rowData[$key]['genericTests'] = $genericService->getTestsByGenericSampleIds($row['sampleId']);
        }
    }

    $now = DateUtility::getCurrentDateTime();
    $affectedSamples = array_values(array_filter(array_unique(array_column($rowData, 'testRequestId'))));
    if (!empty($affectedSamples)) {
        // 1) result_sent_to_source / result_sent_to_source_datetime — set once
        $db->where($primaryKey, $affectedSamples, 'IN');
        $db->where('result_sent_to_source_datetime IS NULL');
        $db->update($tableName, [
            'result_sent_to_source'          => 'sent',
            'result_sent_to_source_datetime' => $now,
        ]);

        // 2) result_dispatched_datetime — set once
        $db->where($primaryKey, $affectedSamples, 'IN');
        $db->where('result_dispatched_datetime IS NULL');
        $db->update($tableName, [
            'result_dispatched_datetime' => $now,
        ]);

        // 3) Stamp “first pulled via API” once for rows actually returned
        $db->where($primaryKey, $affectedSamples, 'IN');
        $db->where('result_pulled_via_api_datetime IS NULL');
        $db->update($tableName, [
            'result_pulled_via_api_datetime' => $now,
        ]);
    }

    http_response_code(200);
    $payload = [
        'status' => 'success',
        'timestamp' => time(),
        'transactionId' => $transactionId,
        'data' => $rowData ?? []
    ];
} catch (Throwable $exc) {
    $payload = [
        'status' => 'failed',
        'timestamp' => time(),
        'transactionId' => $transactionId,
        'error' => _translate('Failed to process this request. Please contact the system administrator if the problem persists'),
        'data' => []
    ];
    LoggerUtility::logError($exc->getMessage(), [
        'file' => $exc->getFile(),
        'line' => $exc->getLine(),
        'requestUrl' => $requestUrl,
        'stacktrace' => $exc->getTraceAsString()
    ]);
}
$payload = JsonUtility::encodeUtf8Json($payload);
$general->addApiTracking($transactionId, $user['user_id'], count($rowData ?? []), 'fetch-results', 'tb', $_SERVER['REQUEST_URI'], $origJson, $payload, 'json');

//echo $payload
echo ApiService::generateJsonResponse($payload, $request);
