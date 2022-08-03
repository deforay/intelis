<?php

return '{
    "resourceType": "Bundle",
    "id": "LabResultRejectedBundle",
    "type": "transaction",
    "entry": [
        {
            "fullUrl": "Task/LabOrderTaskRejectedExample",
            "resource": {
            "resourceType": "Task",
            "id": "LabOrderTaskRejectedExample",
            "status": "rejected",
            "statusReason": {
                "coding": [
                    {
                        "code": "' . $rejectionReasonCode . '",
                        "system": "http://openhie.org/fhir/lab-integration/status-reason",
                        "display": "' . trim($rejectionReason) . '"
                    }
                ]
            },
            "intent": "order",
            "identifier": [
                {
                "system": "OHRI_ENCOUNTER_UUID",
                "value": "' . $formAttributes['fhir']['OHRI_ENCOUNTER_UUID'] . '"
                },
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
            ]
            },
            "request": {
                "method": "PUT",
                "url": "Task/' . $formAttributes['fhir']['task'] . '"
            }
        }
    ]
}';
