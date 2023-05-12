<?php

use App\Exceptions\SystemException;
use App\Services\ApiService;
use App\Services\Covid19Service;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\UsersService;

session_unset(); // no need of session in json response

ini_set('memory_limit', -1);


/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

/** @var Covid19Service $covid19Service */
$covid19Service = ContainerRegistry::get(Covid19Service::class);

// /** @var ApiService $app */
// $app = ContainerRegistry::get(ApiService::class);

$arr = $general->getGlobalConfig();

$transactionId = $general->generateUUID();

$input = json_decode(file_get_contents("php://input"), true);
/* For API Tracking params */
$requestUrl = $_SERVER['HTTP_HOST'];
$requestUrl .= $_SERVER['REQUEST_URI'];
$params = file_get_contents("php://input");
$authToken = $general->getAuthorizationBearerToken();
$user = $usersService->getUserFromToken($authToken);


try {

    $sQuery = "SELECT
        vl.app_sample_code                as appSampleCode,
        vl.unique_id                            as uniqueId,
        vl.covid19_id                           as covid19Id,
        vl.sample_code                          as sampleCode,
        vl.remote_sample_code                   as remoteSampleCode,
        vl.external_sample_code                 as externalSampleCode,
        vl.facility_id                          as facilityId,
        f.facility_name                         as facilityName,
        vl.investigator_name                    as investigatorName,
        vl.investigator_phone                   as investigatorPhone,
        vl.investigator_email                   as investigatorEmail,
        vl.clinician_name                       as clinicianName,
        vl.clinician_phone                      as clinicianPhone,
        vl.clinician_email                      as clinicianEmail,
        vl.test_number                          as testNumber,
        vl.lab_id                               as labId,
        vl.testing_point                        as testingPoint,
        vl.source_of_alert                      as sourceOfAlertPOE,
        vl.source_of_alert_other                as sourceOfAlertOtherPOE,
        vl.patient_id                           as patientId,
        vl.patient_name                         as firstName,
        vl.patient_surname                      as lastName,
        vl.patient_dob                          as patientDob,
        vl.patient_gender                       as patientGender,
        vl.is_patient_pregnant                  as isPatientPregnant,
        vl.patient_age                          as patientAge,
        vl.patient_phone_number                 as patientPhoneNumber,
        vl.patient_address                      as patientAddress,
        vl.patient_city                         as patientCity,
        vl.patient_zone                         as patientZone,
        vl.patient_occupation                   as patientOccupation,
        vl.does_patient_smoke                   as doesPatientSmoke,
        vl.patient_nationality                  as patientNationality,
        vl.patient_passport_number              as patientPassportNumber,
        vl.flight_airline                       as airline,
        vl.flight_seat_no                       as seatNo,
        vl.flight_arrival_datetime              as arrivalDateTime,
        vl.flight_airport_of_departure          as airportOfDeparture,
        vl.flight_transit                       as transit,
        vl.reason_of_visit                      as reasonOfVisit,
        vl.is_sample_collected                  as isSampleCollected,
        vl.reason_for_covid19_test              as reasonForCovid19Test,
        vl.type_of_test_requested               as testTypeRequested,
        vl.specimen_type                        as specimenType,
        vl.sample_collection_date               as sampleCollectionDate,
        vl.health_outcome                       as healthOutcome,
        vl.health_outcome_date                  as outcomeDate,
        vl.is_sample_post_mortem                as isSamplePostMortem,
        vl.priority_status                      as priorityStatus,
        vl.number_of_days_sick                  as numberOfDaysSick,
        vl.suspected_case                       as suspectedCase,
        vl.date_of_symptom_onset                as dateOfSymptomOnset,
        vl.date_of_initial_consultation         as dateOfInitialConsultation,
        vl.fever_temp                           as feverTemp,
        vl.medical_history                      as medicalHistory,
        vl.recent_hospitalization               as recentHospitalization,
        vl.patient_lives_with_children          as patientLivesWithChildren,
        vl.patient_cares_for_children           as patientCaresForChildren,
        vl.temperature_measurement_method       as temperatureMeasurementMethod,
        vl.respiratory_rate                     as respiratoryRate,
        vl.oxygen_saturation                    as oxygenSaturation,
        vl.close_contacts                       as closeContacts,
        vl.contact_with_confirmed_case          as contactWithConfirmedCase,
        vl.has_recent_travel_history            as hasRecentTravelHistory,
        vl.travel_country_names                 as countryName,
        vl.travel_return_date                   as returnDate,
        vl.sample_received_at_vl_lab_datetime   as sampleReceivedDate,
        vl.sample_condition                     as sampleCondition,
        vl.is_sample_rejected                   as isSampleRejected,
        vl.result                               as result,
        vl.if_have_other_diseases               as ifOtherDiseases,
        vl.other_diseases                       as otherDiseases,
        vl.is_result_authorised                 as isResultAuthorized,
        vl.authorized_by                        as authorizedBy,
        vl.authorized_on                        as authorizedOn,
        vl.rejection_on                         as rejectionDate,
        vl.result_status                        as resultStatus,
        vl.sample_tested_datetime               as sampleTestedDate,
        vl.sample_dispatched_datetime           as sampleDispatchedOn,
        vl.result_dispatched_datetime           as resultDispatchedOn,
        l_f.facility_name                       as labName,
        vl.result,
        vl.sample_received_at_vl_lab_datetime   as sampleReceivedDate,
        vl.is_sample_rejected                   as sampleRejected,
        vl.is_result_authorised                 as isAuthorised,
        vl.lab_tech_comments                    as approverComments,
        vl.request_created_datetime             as requestedDate,
        vl.result_printed_datetime              as resultPrintedDate,
        vl.testing_point                        as testingPoint,
        vl.authorized_on                        as authorisedOn,
        vl.authorized_by                        as authorisedBy,
        vl.request_created_datetime             as createdOn,
        vl.last_modified_datetime               as updatedOn,
        u_d.user_name                           as reviewedBy,
        vl.lab_technician                       as labTechnician,
        lt_u_d.user_name                        as labTechnicianName,
        vl.tested_by                            as testedBy,
        t_b.user_name                           as testedByName,
        rs.rejection_reason_name                as rejectionReason,
        vl.rejection_on                         as rejectionDate,
        vl.funding_source                       as fundingSource,
        r_f_s.funding_source_name               as fundingSourceName,
        vl.implementing_partner                 as implementingPartner,
        r_i_p.i_partner_name                    as implementingPartnerName,
        gdp.geo_name                            as province,
        gdp.geo_id                              as provinceId,
        gdd.geo_name                            as district,
        gdd.geo_id                              as districtId,
        gdpp.geo_name                           as patientProvince,
        gdpp.geo_id                             as patientProvinceId,
        gdpd.geo_name                           as patientDistrict,
        gdpd.geo_id                             as patientDistrictId,
        ts.status_name                          as resultStatusName,
        vl.revised_by                           as revisedBy,
        r_r_b.user_name                         as revisedByName,
        vl.revised_on                           as revisedOn,
        vl.patient_nationality                  as patientNationality,
        CONCAT_WS('',c.iso_name, ' (', c.iso3,')') as patientNationalityName,
        vl.reason_for_changing                  as reasonForCovid19ResultChanges
        
        FROM form_covid19 as vl
        LEFT JOIN r_countries as c ON vl.patient_nationality=c.id
        LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
        LEFT JOIN facility_details as l_f ON vl.lab_id=l_f.facility_id
        LEFT JOIN geographical_divisions as gdd ON f.facility_district_id=gdd.geo_id
        LEFT JOIN geographical_divisions as gdp ON vl.province_id=gdp.geo_id
        LEFT JOIN geographical_divisions as gdpp ON vl.patient_province=gdpp.geo_name
        LEFT JOIN geographical_divisions as gdpd ON vl.patient_district=gdpd.geo_name
        LEFT JOIN r_sample_status as ts ON ts.status_id=vl.result_status
        LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id
        LEFT JOIN user_details as u_d ON u_d.user_id=vl.result_reviewed_by
        LEFT JOIN user_details as a_u_d ON a_u_d.user_id=vl.result_approved_by
        LEFT JOIN user_details as r_r_b ON r_r_b.user_id=vl.revised_by
        LEFT JOIN user_details as lt_u_d ON lt_u_d.user_id=vl.lab_technician
        LEFT JOIN user_details as t_b ON t_b.user_id=vl.tested_by
        LEFT JOIN r_covid19_test_reasons as rtr ON rtr.test_reason_id=vl.reason_for_covid19_test
        LEFT JOIN r_covid19_sample_type as rst ON rst.sample_id=vl.specimen_type
        LEFT JOIN r_covid19_sample_rejection_reasons as rs ON rs.rejection_reason_id=vl.reason_for_sample_rejection
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

    /* To check the sample code filter */
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
        $where[] = " DATE(vl.request_created_datetime) >= '" . date('Y-m-d', strtotime($input['lastModifiedDateTime'])) . "'";
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
        $where[] = " (vl.patient_name like '" . $input['patientName'] . "' OR vl.patient_surname like '" . $input['patientName'] . "')";
    }

    $sampleStatus = $input['sampleStatus'];
    if (!empty($sampleStatus)) {
        $sampleStatus = implode("','", $sampleStatus);
        $where[] = " result_status IN ('$sampleStatus') ";
    }

    $where = " WHERE " . implode(" AND ", $where);
    $sQuery .= $where . " limit 100;";
    // // die($sQuery);
    $rowData = $db->rawQuery($sQuery);

    // No data found
    if (!$rowData) {
        // array_splice($rowData, 1, 2);
        $payload = array(
            'status' => 'success',
            'timestamp' => time(),
            'error' => 'No matching data',
            'data' => $rowData

        );
    } else {
        foreach ($rowData as $key => $row) {
            $rowData[$key]['c19Tests'] = $covid19Service->getCovid19TestsByC19Id($row['covid19Id']);
            $rowData[$key]['c19Symptoms'] = $covid19Service->getCovid19SymptomsByFormId($row['covid19Id'], false, true);
            $rowData[$key]['c19Comorbidities'] = $covid19Service->getCovid19ComorbiditiesByFormId($row['covid19Id'], false, true);
            $rowData[$key]['c19ReasonForTesting'] = $covid19Service->getCovid19ReasonsForTestingByFormId($row['covid19Id'], false, true);
            $rowData[$key]['c19ReasonDetails'] = $covid19Service->getCovid19ReasonsDetailsForTestingByFormId($row['covid19Id']);
        }
        $payload = array(
            'status' => 'success',
            'timestamp' => time(),
            'data' => $rowData
        );
    }
    http_response_code(200);
} catch (SystemException $exc) {

    // http_response_code(500);
    $payload = array(
        'status' => 'failed',
        'timestamp' => time(),
        'error' => $exc->getMessage(),
        'data' => array()
    );
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
$payload = json_encode($payload);
$general->addApiTracking($transactionId, $user['user_id'], count($rowData), 'fetch-results', 'covid19', $_SERVER['REQUEST_URI'], $params, $payload, 'json');
echo $payload;
// exit(0);
