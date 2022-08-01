<?php

// this file is included in /vl/interop/fhir/vl-receive.php

use DCarbone\PHPFHIRGenerated\R4\PHPFHIRResponseParser;
use Vlsm\Interop\Fhir;

$interopConfig = require(APPLICATION_PATH . '/../configs/config.interop.php');

$general = new \Vlsm\Models\General();
$vlModel = new \Vlsm\Models\Vl();
$facilityDb = new \Vlsm\Models\Facilities();

$vlsmSystemConfig = $general->getSystemConfig();

$fhir = new Fhir($interopConfig['FHIR']['url'], $interopConfig['FHIR']['auth']);

//$query = "SELECT * FROM form_vl WHERE (source_of_request LIKE 'fhir' OR unique_id like 'fhir%') AND result_status = 7 AND (result_sent_to_source is null or result_sent_to_source NOT LIKE 'sent')";
//$query = "SELECT * FROM form_vl WHERE source_of_request LIKE 'fhir' AND result_status = 7";// AND result_sent_to_source NOT LIKE 'sent'";
$query = "SELECT * FROM form_vl WHERE (source_of_request LIKE 'fhir' OR unique_id like 'fhir%') AND result IS NOT NULL AND (result_sent_to_source is null or result_sent_to_source NOT LIKE 'sent')";

$formResults = $db->rawQuery($query);

$counter = 0;

foreach ($formResults as $row) {
    
    $sampleCollectionDate = ((new DateTime($row['sample_collection_date']))->format("Y-m-d"));
    $sampleReceivedDate = ((new DateTime($row['sample_received_at_vl_lab_datetime']))->format("Y-m-d"));
    $sampleTestedDate = ((new DateTime($row['sample_tested_datetime']))->format("Y-m-d"));
    $lastModifiedDateTime = ((new DateTime($row['last_modified_datetime']))->format("Y-m-d"));

    $formAttributes = json_decode($row['form_attributes'], true);
    $formAttributes['fhir'] = $formAttributes;

    $db->where("facility_id", $row['facility_id']);
    $facilityRow = $db->getOne("facility_details");
    $facilityAttributes = json_decode($facilityRow['facility_attributes'], true);
    $fhirFacilityId = $facilityAttributes['facility_fhir_id']; 
    
    $db->where("facility_id", $row['lab_id']);
    $labRow = $db->getOne("facility_details");
    $labAttributes = json_decode($labRow['facility_attributes'], true);
    $fhirLabId = $labAttributes['facility_fhir_id']; 

    $json = '{
        "resourceType": "Bundle",
        "id": "LabResultBundle",
        "type": "transaction",
        "entry": [
            {
                "fullUrl": "Task/LabOrderTaskUpdatedExample",
                "resource": {
                    "resourceType": "Task",
                    "id": "LabOrderTaskUpdatedExample",
                    "status": "completed",
                    "intent": "order",
                    "identifier": [
                        {
                            "system": "http://openhie.org/fhir/lab-integration/test-order-number",
                            "value": "' . $row['external_sample_code'] . '"
                        }
                    ],
                    "requester": {
                        "reference": "Organization/' . $fhirFacilityId . '"
                    },
                    "owner": {
                        "reference": "Organization/' . $fhirLabId . '"
                    },
                    "lastModified": "' . $lastModifiedDateTime . '",
                    "basedOn": [
                        {
                            "reference": "ServiceRequest/' . $formAttributes['fhir']['serviceRequest'] . '"
                        }
                    ],
                    "output": [
                        {
                            "type": {
                                "coding": [
                                    {
                                        "code": "result",
                                        "system": "http://openhie.org/fhir/lab-integration/task-output"
                                    }
                                ]
                            },
                            "valueReference": {
                                "reference": "DiagnosticReport/DiagnosticReportExample"
                            }
                        }
                    ]
                },
                "request": {
                    "method": "PUT",
                    "url": "Task/' . $formAttributes['fhir']['task'] . '"
                }
            },
            {
                "fullUrl": "DiagnosticReport/DiagnosticReportExample",
                "resource": {
                    "resourceType": "DiagnosticReport",
                    "id": "DiagnosticReportExample",
                    "status": "final",
                    "code": {
                        "coding": [
                            {
                                "code": "10351-5",
                                "system": "http://loinc.org"
                            }
                        ]
                    },
                    "performer": [
                        {
                            "reference": "Practitioner/LabPractitionerExample"
                        }
                    ],
                    "conclusion": "' . $row['vl_result_category'] . '",
                    "result": [
                        {
                            "reference": "Observation/ViralLoadSuppressionMostRecentTestResultExample"
                        }
                    ]
                },
                "request": {
                    "method": "POST",
                    "url": "Task/DiagnosticReportExample"
                }
            },
            {
                "fullUrl": "Practitioner/LabPractitionerExample",
                "resource": {
                    "resourceType": "Practitioner",
                    "id": "LabPractitionerExample",
                    "name": [
                        {
                            "given": [
                                "' . $testerFirstName . '"
                            ],
                            "family": "' . $testerLastName . '"
                        }
                    ],
                    "telecom": [
                        {
                            "system": "phone",
                            "value": "' . $testerPhone . '"
                        }
                    ]
                },
                "request": {
                    "method": "POST",
                    "url": "Task/LabPractitionerExample"
                }
            },
            {
                "fullUrl": "Specimen/LabSpecimenUpdatedExample",
                "resource": {
                    "resourceType": "Specimen",
                    "id": "LabSpecimenUpdatedExample",
                    "type": {
                        "coding": [
                            {
                                "code": "' . $specimenCode . '",
                                "system": "http://openhie.org/fhir/lab-integration/specimen-type-code"
                            }
                        ]
                    },
                    "collection": {
                        "collectedDateTime": "' . $sampleCollectionDate . '"
                    },
                    "processing": [
                        {
                            "timeDateTime": "2022-07-22"
                        }
                    ],
                    "receivedTime": "2022-07-22"
                },
                "request": {
                    "method": "PUT",
                    "url": "Specimen/' . $formAttributes['fhir']['specimen'] . '"
                }
            },
            {
                "fullUrl": "Observation/ViralLoadSuppressionMostRecentTestResultExample",
                "resource": {
                    "resourceType": "Observation",
                    "id": "ViralLoadSuppressionMostRecentTestResultExample",
                    "meta": {
                        "profile": [
                            "http://example.org/StructureDefinition/hiv-viral-load-suppression-most-recent-test-result"
                        ]
                    },
                    "code": {
                        "coding": [
                            {
                                "code": "HIV-RECENCY-TEST-CONDUCTED",
                                "system": "http://example.org/CodeSystem/cs-hiv-obs-codes",
                                "display": "VL most recent test result"
                            }
                        ]
                    },
                    "status": "final",
                    "subject": {
                        "reference": "Patient/' . $formAttributes['fhir']['patient'] . '"
                    },
                    "valueString": "' . $row['result'] . '",
                    "interpretation": [
                        {
                            "coding": [
                                {
                                    "code": "D",
                                    "system": "http://example.org/CodeSystem/cs-vl-interpretation"
                                }
                            ]
                        }
                    ]
                },
                "request": {
                    "method": "POST",
                    "url": "Observation/ViralLoadSuppressionMostRecentTestResultExample"
                }
            }
        ]
    }';

    // echo "<pre>";
    // echo $json;
    // echo "</pre>";
    $resp = $fhir->post(null, $json);

    $updateData = array('result_sent_to_source' => 'sent');
    $db = $db->where('vl_sample_id', $row['vl_sample_id']);
    $db->update('form_vl', $updateData);
    $counter++;    
}


$response = array('processed' => $counter);
$app = new \Vlsm\Models\App();
$trackId = $app->addApiTracking(NULL, $counter, 'FHIR-VL-Send', 'vl');
echo (json_encode($response));