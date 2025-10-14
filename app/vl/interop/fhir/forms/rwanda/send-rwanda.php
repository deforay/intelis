<?php

// this file is included in /vl/interop/fhir/vl-receive.php
header('Content-Type: application/json');

use App\Interop\Fhir;
use App\Services\VlService;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use DCarbone\PHPFHIRGenerated\R4\PHPFHIRResponseParser;

$interopConfig = [];
if (file_exists(APPLICATION_PATH . '/../configs/config.interop.php')) {
    $interopConfig = require_once(APPLICATION_PATH . '/../configs/config.interop.php');
}

if (empty($interopConfig)) {
    echo "Interop config not found";
    exit(0);
}


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var VlService $vlService */
$vlService = ContainerRegistry::get(VlService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

$vlsmSystemConfig = $general->getSystemConfig();

$transactionId = MiscUtility::generateULID();

$fhir = new Fhir($interopConfig['FHIR']['url'], $interopConfig['FHIR']['auth']);

//$query = "SELECT * FROM form_vl WHERE (source_of_request LIKE 'fhir' OR unique_id like 'fhir%') AND result_status = 7 AND (result_sent_to_source is null or result_sent_to_source NOT LIKE 'sent')";
//$query = "SELECT * FROM form_vl WHERE source_of_request LIKE 'fhir' AND result_status = " . SAMPLE_STATUS\ACCEPTED;// AND result_sent_to_source NOT LIKE 'sent'";
// $query = "SELECT vl.*, rej.rejection_reason_name, tester.user_name as tester_name, tester.phone_number as tester_phone_number
//             FROM form_vl as vl
//             LEFT JOIN r_vl_sample_rejection_reasons as rej ON rej.rejection_reason_id = vl.reason_for_sample_rejection
//             LEFT JOIN user_details as tester ON vl.tested_by = tester.user_id
//             WHERE ((source_of_request LIKE 'fhir' AND source_of_request is NOT NULL) OR (unique_id like 'fhir%'))
//             AND (result IS NOT NULL OR (is_sample_rejected IS NOT NULL AND is_sample_rejected = 'yes'))))
//             AND (result_sent_to_source is null or result_sent_to_source NOT LIKE 'sent')";
$query = "SELECT vl.*,
                rej.rejection_reason_name,
                tester.user_name as tester_name,
                tester.phone_number as tester_phone_number
            FROM form_vl as vl
            LEFT JOIN r_vl_sample_rejection_reasons as rej ON rej.rejection_reason_id = vl.reason_for_sample_rejection
            LEFT JOIN user_details as tester ON vl.tested_by = tester.user_id
            WHERE ((source_of_request LIKE 'fhir' AND source_of_request is NOT NULL) OR (unique_id like 'fhir%'))
            AND (result IS NOT NULL OR (is_sample_rejected IS NOT NULL AND is_sample_rejected = 'yes'))
            AND (result_sent_to_source is null or result_sent_to_source NOT LIKE 'sent') LIMIT 100";

$formResults = $db->rawQuery($query);

$counter = 0;
$json = "";
foreach ($formResults as $row) {

    $sampleCollectionDate = ((new DateTime($row['sample_collection_date']))->format("Y-m-d"));
    $sampleReceivedDate = ((new DateTime($row['sample_received_at_lab_datetime']))->format("Y-m-d"));
    $sampleTestedDate = ((new DateTime($row['sample_tested_datetime']))->format("Y-m-d"));
    $lastModifiedDateTime = ((new DateTime($row['last_modified_datetime']))->format("Y-m-d"));

    $rejectionReasonCode = $row['reason_for_sample_rejection'] ?? null;
    $rejectionReason = $row['rejection_reason_name'] ?? null;

    $testerFirstName = $row['tester_name'] ?? "VLSM";
    $testerLastName = $row['tester_name'] ?? "User";

    $row['vl_result_category'] = $row['vl_result_category'] ?? "Unknown";

    $specimenCode = 'venous-blood';

    $testerPhoneNumber = $row['tester_phone_number'] ?? "Unknown";

    $formAttributes = json_decode((string) $row['form_attributes'], true);


    $db->where("facility_id", $row['facility_id']);
    $facilityRow = $db->getOne("facility_details");
    $facilityAttributes = json_decode((string) $facilityRow['facility_attributes'], true);
    $fhirFacilityId = $facilityAttributes['facility_fhir_id'];

    $db->where("facility_id", $row['lab_id']);
    $labRow = $db->getOne("facility_details");
    $labAttributes = json_decode((string) $labRow['facility_attributes'], true);
    $fhirLabId = $labAttributes['facility_fhir_id'];

    if (($row['is_sample_rejected'] === 'no') && $row['result'] !== null) {
        $json = include(__DIR__ . "/result-fhir-formats/return-result.php");
    }
    if ($row['is_sample_rejected'] === 'yes' || 4 === (int) $row['result_status']) {
        $json = include(__DIR__ . "/result-fhir-formats/return-rejected.php");
    }


    // echo "\n\n\n";
    // echo $general->prettyJson($json);
    // continue;

    $resp = $fhir->post("/", $json);

    //echo $general->prettyJson($resp);


    $updateData = [
        'result_sent_to_source' => 'sent',
        'result_dispatched_datetime' => DateUtility::getCurrentDateTime(),
        'result_sent_to_source_datetime' => DateUtility::getCurrentDateTime()
    ];
    $db->where('vl_sample_id', $row['vl_sample_id']);
    $db->update('form_vl', $updateData);
    $counter++;
}


$response = json_encode(array('timestamp' => time(), 'processed' => $counter, 'response' => $resp));

$general->addApiTracking($transactionId, 'vlsm-system', $counter, 'FHIR-VL-Send', 'vl', $fhir->getRequestUrl(), $json, null, 'json');

echo JsonUtility::prettyJson($response);
