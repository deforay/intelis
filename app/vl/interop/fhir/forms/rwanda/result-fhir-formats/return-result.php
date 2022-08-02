<?php

return '{
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
                        "value": "' . $testerPhoneNumber . '"
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
                        "http://openhie.org/StructureDefinition/hiv-viral-load-suppression-most-recent-test-result"
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
                                "code": "' . $row['vl_result_category'] . '",
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