<?php

// this file is included in /vl/interop/fhir/vl-receive.php
header('Content-Type: application/json');

function prettyJson($json)
{
    if (is_array($json)) {
        return stripslashes(json_encode($json, JSON_PRETTY_PRINT));
    } else {
        return stripslashes(json_encode(json_decode($json), JSON_PRETTY_PRINT));
    }
}


use App\Models\Facilities;
use App\Models\General;
use App\Models\Vl;
use DCarbone\PHPFHIRGenerated\R4\PHPFHIRResponseParser;
use App\Interop\Fhir;

$interopConfig = require(APPLICATION_PATH . '/../configs/config.interop.php');

$general = new General();
$vlModel = new Vl();
$facilityDb = new Facilities();

$vlsmSystemConfig = $general->getSystemConfig();

$transactionId = $general->generateUUID();

$fhir = new Fhir($interopConfig['FHIR']['url'], $interopConfig['FHIR']['auth']);

//$query = "SELECT * FROM form_vl WHERE (source_of_request LIKE 'fhir' OR unique_id like 'fhir%') AND result_status = 7 AND (result_sent_to_source is null or result_sent_to_source NOT LIKE 'sent')";
//$query = "SELECT * FROM form_vl WHERE source_of_request LIKE 'fhir' AND result_status = 7";// AND result_sent_to_source NOT LIKE 'sent'";
// $query = "SELECT vl.*, rej.rejection_reason_name, tester.user_name as tester_name, tester.phone_number as tester_phone_number
//             FROM form_vl as vl
//             LEFT JOIN r_vl_sample_rejection_reasons as rej ON rej.rejection_reason_id = vl.reason_for_sample_rejection
//             LEFT JOIN user_details as tester ON vl.tested_by = tester.user_id 
//             WHERE ((source_of_request LIKE 'fhir' AND source_of_request is NOT NULL) OR (unique_id like 'fhir%'))
//             AND (result IS NOT NULL OR (is_sample_rejected IS NOT NULL AND is_sample_rejected = 'yes')))) 
//             AND (result_sent_to_source is null or result_sent_to_source NOT LIKE 'sent')";
$query = "SELECT vl.*, rej.rejection_reason_name, tester.user_name as tester_name, tester.phone_number as tester_phone_number
            FROM form_vl as vl
            LEFT JOIN r_vl_sample_rejection_reasons as rej ON rej.rejection_reason_id = vl.reason_for_sample_rejection
            LEFT JOIN user_details as tester ON vl.tested_by = tester.user_id 
            WHERE ((source_of_request LIKE 'fhir' AND source_of_request is NOT NULL) OR (unique_id like 'fhir%'))
            AND (result IS NOT NULL OR (is_sample_rejected IS NOT NULL AND is_sample_rejected = 'yes'))
            AND (result_sent_to_source is null or result_sent_to_source NOT LIKE 'sent')
            ";

$formResults = $db->rawQuery($query);

//var_dump($formResults);die;

$counter = 0;
$json = "";
foreach ($formResults as $row) {


    $sampleCollectionDate = ((new DateTime($row['sample_collection_date']))->format("Y-m-d"));
    $sampleReceivedDate = ((new DateTime($row['sample_received_at_vl_lab_datetime']))->format("Y-m-d"));
    $sampleTestedDate = ((new DateTime($row['sample_tested_datetime']))->format("Y-m-d"));
    $lastModifiedDateTime = ((new DateTime($row['last_modified_datetime']))->format("Y-m-d"));

    $rejectionReasonCode = $row['reason_for_sample_rejection'] ?: null;
    $rejectionReason = $row['rejection_reason_name'] ?: null;

    $testerFirstName = $row['tester_name'] ?: "VLSM";
    $testerLastName = $row['tester_name'] ?: "User";

    $row['vl_result_category'] = $row['vl_result_category'] ?: "Unknown";

    $specimenCode = 'venous-blood';

    $testerPhoneNumber = $row['tester_phone_number'] ?: "Unknown";

    $formAttributes = json_decode($row['form_attributes'], true);


    $db->where("facility_id", $row['facility_id']);
    $facilityRow = $db->getOne("facility_details");
    $facilityAttributes = json_decode($facilityRow['facility_attributes'], true);
    $fhirFacilityId = $facilityAttributes['facility_fhir_id'];

    $db->where("facility_id", $row['lab_id']);
    $labRow = $db->getOne("facility_details");
    $labAttributes = json_decode($labRow['facility_attributes'], true);
    $fhirLabId = $labAttributes['facility_fhir_id'];

    if (($row['is_sample_rejected'] === 'no') && $row['result'] !== null) {
        $json = include(__DIR__ . "/result-fhir-formats/return-result.php");
    }
    if ($row['is_sample_rejected'] === 'yes' || 4 === (int) $row['result_status']) {
        $json = include(__DIR__ . "/result-fhir-formats/return-rejected.php");
    }


    // echo "\n\n\n";
    // echo prettyJson($json);
    // continue;

    $resp = $fhir->post("/", $json);

    //echo prettyJson($resp);


    $updateData = array('result_sent_to_source' => 'sent');
    $db = $db->where('vl_sample_id', $row['vl_sample_id']);
    $db->update('form_vl', $updateData);
    $counter++;
}


$response = json_encode(array('timestamp' => time(), 'processed' => $counter, 'response' => $resp));

$general->addApiTracking($transactionId, 'vlsm-system', $counter, 'FHIR-VL-Send', 'vl', $fhir->getRequestUrl(), $json, null, 'json', null);

echo prettyJson($response);
