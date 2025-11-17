<?php

use Slim\Psr7\Request;
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

ini_set('memory_limit', -1);
set_time_limit(0);
ini_set('max_execution_time', 20000);

/** @var Request $request */
$request = AppRegistry::get('request');

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

/** @var ApiService $apiService */
$apiService = ContainerRegistry::get(ApiService::class);



$origJson = $apiService->getJsonFromRequest($request);
if (JsonUtility::isJSON($origJson) === false) {
    throw new SystemException("Invalid JSON Payload", 400);
}
$input = JsonUtility::decodeJson($origJson, true);

$user = null;

$primaryKey = TestsService::getPrimaryColumn('eid');
$tableName = TestsService::getTestTableName('eid');

/* For API Tracking params */
$requestUrl = $_SERVER['HTTP_HOST'];
$requestUrl .= $_SERVER['REQUEST_URI'];
$authToken = ApiService::extractBearerToken($request);
$user = $usersService->findUserByApiToken($authToken);
try {
    $transactionId = MiscUtility::generateULID();
    $sQuery = "SELECT
        vl.app_sample_code                                   as appSampleCode,
        vl.unique_id                                         as uniqueId,
        vl.eid_id                                            as testRequestId,
        vl.sample_code                                       as sampleCode,
        vl.remote_sample_code                                as remoteSampleCode,
        vl.vlsm_instance_id                                  as instanceId,
        vl.vlsm_country_id                                   as formId,
        vl.facility_id                                       as facilityId,
        vl.province_id                                       as provinceId,
        vl.lab_id                                            as labId,
        vl.implementing_partner                              as implementingPartner,
        vl.funding_source                                    as fundingSource,
        vl.mother_id                                         as mothersId,
        vl.caretaker_contact_consent                         as caretakerConsentForContact,
        vl.caretaker_phone_number                            as caretakerPhoneNumber,
        vl.caretaker_address                                 as caretakerAddress,
        vl.mother_name                                       as mothersName,
        vl.mother_dob                                        as mothersDob,
        vl.mother_marital_status                             as mothersMaritalStatus,
        vl.mother_treatment                                  as motherTreatment,
        vl.mother_treatment_other                            as motherTreatmentOther,
        vl.mother_treatment_initiation_date                  as motherTreatmentInitiationDate,
        vl.child_id                                          as childId,
        vl.child_name                                        as childName,
        vl.child_surname                                     as childSurName,
        vl.child_dob                                         as childDob,
        vl.child_gender                                      as childGender,
        vl.health_insurance_code                             as healthInsuranceCode,
        IFNULL(vl.child_age,'')                              as childAge,
        vl.child_treatment                                   as childTreatment,
        vl.child_treatment_other                             as childTreatmentOther,
        vl.mother_cd4                                        as mothercd4,
        IFNULL(vl.mother_vl_result,'')                       as motherVlResult,
        vl.mother_hiv_status                                 as mothersHIVStatus,
        vl.pcr_test_performed_before                         as pcrTestPerformedBefore,
        vl.previous_pcr_result                               as prePcrTestResult,
        vl.last_pcr_date                                     as previousPCRTestDate,
        vl.reason_for_pcr                                    as pcrTestReason,
        vl.has_infant_stopped_breastfeeding                  as hasInfantStoppedBreastfeeding,
        vl.age_breastfeeding_stopped_in_months               as ageBreastfeedingStopped,
        vl.choice_of_feeding                                 as choiceOfFeeding,
        vl.is_cotrimoxazole_being_administered_to_the_infant as isCotrimoxazoleBeingAdministered,
        vl.specimen_type                                     as specimenType,
        vl.sample_collection_date                            as sampleCollectionDate,
        vl.sample_requestor_phone                            as sampleRequestorPhone,
        vl.sample_requestor_name                             as sampleRequestorName,
        vl.rapid_test_performed                              as rapidTestPerformed,
        vl.rapid_test_date                                   as rapidtestDate,
        vl.rapid_test_result                                 as rapidTestResult,
        vl.lab_reception_person                              as labReceptionPerson,
        vl.sample_received_at_lab_datetime                   as sampleReceivedDate,
        vl.eid_test_platform                                 as eidPlatform,
        vl.import_machine_name                               as machineName,
        vl.sample_tested_datetime                            as sampleTestedDateTime,
        vl.sample_dispatched_datetime                        as sampleDispatchedOn,
        vl.result_dispatched_datetime                        as resultDispatchedOn,
        vl.is_sample_rejected                                as isSampleRejected,
        IFNULL(vl.result,'')                                 as result,
        vl.lab_tech_comments                                 as approverComments,
        vl.result_status                                     as status,
        l_f.facility_name                                    as labName,
        f.facility_district                                  as district,
        f.facility_district_id                               as districtId,
        f.facility_name                                      as facilityName,
        vl.result_reviewed_datetime                          as resultReviewedDatetime,

        vl.revised_by                                        as revisedBy,
        r_r_b.user_name                                      as revisedByName,
        vl.revised_on                                        as revisedOn,
        vl.tested_by                                         as testedBy,
        t_b.user_name                                        as testedByName,
        vl.result_approved_by                                as approvedBy,
        a_u_d.user_name                                      as approvedByName,
        vl.result_approved_datetime                          as approvedOn,
        IFNULL(vl.result_reviewed_by,'')                     as reviewedBy,
        u_d.user_name                                        as reviewedByName,
        vl.result_reviewed_datetime                          as reviewedOn,
        lt_u_d.user_name                                     as labTechnicianName,

        vl.reason_for_sample_rejection                       as sampleRejectionReason,
        vl.request_created_datetime                          as requestCreatedDatetime,
        vl.rejection_on                                      as rejectionDate,
        g.geo_name                                           as provinceName,
        r_f_s.funding_source_name                            as fundingSourceName,
        r_i_p.i_partner_name                                 as implementingPartnerName,
        ts.status_name                                       as resultStatusName,
        vl.reason_for_changing                               as reasonForEidResultChanges

        FROM form_eid as vl
        LEFT JOIN geographical_divisions as g ON vl.province_id=g.geo_id
        LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
        LEFT JOIN facility_details as l_f ON vl.lab_id=l_f.facility_id
        LEFT JOIN r_sample_status as ts ON ts.status_id=vl.result_status
        LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id
        LEFT JOIN user_details as u_d ON u_d.user_id=vl.result_reviewed_by
        LEFT JOIN user_details as a_u_d ON a_u_d.user_id=vl.result_approved_by
        LEFT JOIN user_details as r_r_b ON r_r_b.user_id=vl.revised_by
        LEFT JOIN user_details as lt_u_d ON lt_u_d.user_id=vl.lab_technician
        LEFT JOIN user_details as t_b ON t_b.user_id=vl.tested_by
        LEFT JOIN r_eid_test_reasons as rtr ON rtr.test_reason_id=vl.reason_for_eid_test
        LEFT JOIN r_eid_sample_type as rst ON rst.sample_id=vl.specimen_type
        LEFT JOIN r_eid_sample_rejection_reasons as rs ON rs.rejection_reason_id=vl.reason_for_sample_rejection
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

    if (!empty($input['childId'])) {
        $childId = implode("','", $input['childId']);
        $where[] = " vl.child_id IN ('" . $childId . "') ";
    }

    if (!empty($input['childName'])) {
        $where[] = " (vl.child_name like '" . $input['childName'] . "' OR vl.child_surname like '" . $input['childName'] . "')";
    }

    $sampleStatus = $input['sampleStatus'];
    if (!empty($sampleStatus)) {
        $sampleStatus = implode("','", $sampleStatus);
        $where[] = " result_status IN ('$sampleStatus') ";
    }

    $whereString = '';
    if ($where !== []) {
        $whereString = " WHERE " . implode(" AND ", $where);
    }
    $sQuery .= "$whereString ORDER BY vl.last_modified_datetime DESC limit 100 ";

    $rowData = $db->rawQuery($sQuery);

    $now = DateUtility::getCurrentDateTime();
    $affectedSamples = array_values(array_filter(array_unique(array_column($rowData, 'testRequestId'))));
    if ($affectedSamples !== []) {
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
} catch (Throwable $e) {
    $payload = [
        'status' => 'failed',
        'timestamp' => time(),
        'transactionId' => $transactionId,
        'error' => _translate('Failed to process this request. Please contact the system administrator if the problem persists'),
        'data' => []
    ];

    LoggerUtility::logError($e->getFile() . ':' . $e->getLine() . ":" . $db->getLastError());
    LoggerUtility::logError($e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'requestUrl' => $requestUrl,
        'stacktrace' => $e->getTraceAsString()
    ]);
}
$payload = JsonUtility::encodeUtf8Json($payload);
$general->addApiTracking($transactionId, $user['user_id'], count($rowData ?? []), 'fetch-results', 'eid', $_SERVER['REQUEST_URI'], $origJson, $payload, 'json');

//echo $payload
echo ApiService::generateJsonResponse($payload, $request);
