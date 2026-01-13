<?php

// api/v1.1/vl/save-request.php
use Slim\Psr7\Request;
use const COUNTRY\PNG;
use const SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
use const SAMPLE_STATUS\RECEIVED_AT_CLINIC;
use const SAMPLE_STATUS\TEST_FAILED;
use const SAMPLE_STATUS\REJECTED;
use JsonMachine\Items;
use App\Services\VlService;
use App\Services\ApiService;
use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;
use App\Services\TestRequestsService;
use JsonMachine\JsonDecoder\ExtJsonDecoder;
use JsonMachine\Exception\PathNotFoundException;

ini_set('memory_limit', -1);
set_time_limit(0);
ini_set('max_execution_time', 20000);

// Configuration flag for duplicate detection
$enableDuplicateDetection = true; // TODO: Replace with global config once implemented
// $enableDuplicateDetection = (bool) $general->getGlobalConfig('enable_duplicate_detection', true);

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var ApiService $apiService */
$apiService = ContainerRegistry::get(ApiService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

/** @var VlService $vlService */
$vlService = ContainerRegistry::get(VlService::class);

/** @var TestRequestsService $testRequestsService */
$testRequestsService = ContainerRegistry::get(TestRequestsService::class);

try {

    $db->beginTransaction();

    /** @var Request $request */
    $request = AppRegistry::get('request');
    $noOfFailedRecords = 0;
    $duplicateBlockedRecords = 0;
    $duplicateWarningRecords = 0;

    $origJson = $apiService->getJsonFromRequest($request);

    if (JsonUtility::isJSON($origJson) === false) {
        throw new SystemException("Invalid JSON Payload", 400);
    }

    $updatedLabs = [];

    // Attempt to extract appVersion
    try {
        $appVersion = Items::fromString($origJson, [
            'pointer' => '/appVersion',
            'decoder' => new ExtJsonDecoder(true)
        ]);
        $appVersion = _getIteratorKey($appVersion, 'appVersion');
    } catch (PathNotFoundException | Throwable $e) {
        $appVersion = null;
    }

    try {
        $input = Items::fromString($origJson, [
            'pointer' => '/data',
            'decoder' => new ExtJsonDecoder(true)
        ]);
        if (empty($input)) {
            throw new PathNotFoundException();
        }
    } catch (PathNotFoundException $e) {
        throw new SystemException("Invalid request", 400, $e);
    }

    $transactionId = MiscUtility::generateULID();

    $authToken = ApiService::extractBearerToken($request);
    $user = $usersService->findUserByApiToken($authToken);
    $roleUser = $usersService->getUserRole($user['user_id']);
    $responseData = [];
    $duplicateInfo = []; // Track duplicate detection results
    $uniqueIdsForSampleCodeGeneration = [];
    $instanceId = $general->getInstanceId();
    $formId = (int) $general->getGlobalConfig('vl_form');
    $version = $general->getAppVersion();

    /* To save the user attributes from API */
    $userAttributes = [];
    foreach (['deviceId', 'osVersion', 'ipAddress'] as $header) {
        $userAttributes[$header] = $apiService->getHeader($request, $header);
    }
    $userAttributes = JsonUtility::jsonToSetString(json_encode($userAttributes), 'user_attributes');
    $usersService->saveUserAttributes($userAttributes, $user['user_id']);

    $dataCounter = 0;
    foreach ($input as $rootKey => $data) {
        $dataCounter++;

        $mandatoryFields = [
            'sampleCollectionDate',
            'facilityId',
            'appSampleCode',
            'labId'
        ];
        $cantBeFutureDates = [
            'sampleCollectionDate',
            'dob',
            'requestDate',
            'sampleTestingDateAtLab',
            'sampleDispatchedOn',
            'sampleReceivedDate',
        ];

        if ($formId == PNG) {
            $mandatoryFields[] = 'provinceId';
        }

        $data = MiscUtility::arrayEmptyStringsToNull($data);

        // Basic validation checks
        if (MiscUtility::hasEmpty(array_intersect_key($data, array_flip($mandatoryFields)))) {
            $noOfFailedRecords++;
            $responseData[$rootKey] = [
                'transactionId' => $transactionId,
                'appSampleCode' => $data['appSampleCode'] ?? null,
                'status' => 'failed',
                'action' => 'skipped',
                'message' => _translate("Missing required fields")
            ];
            continue;
        } elseif (DateUtility::hasFutureDates(array_intersect_key($data, array_flip($cantBeFutureDates)))) {
            $noOfFailedRecords++;
            $responseData[$rootKey] = [
                'transactionId' => $transactionId,
                'appSampleCode' => $data['appSampleCode'] ?? null,
                'status' => 'failed',
                'action' => 'skipped',
                'message' => _translate("Invalid Dates. Cannot be in the future")
            ];
            continue;
        }

        // Process province data
        if (!empty($data['provinceId']) && !is_numeric($data['provinceId'])) {
            $province = explode("##", (string) $data['provinceId']);
            if ($province !== []) {
                $data['provinceId'] = $province[0];
            }
            $data['provinceId'] = $general->getValueByName($data['provinceId'], 'geo_name', 'geographical_divisions', 'geo_id');
        }

        $data['api'] = "yes";
        $provinceCode = $data['provinceCode'] ?? null;
        $provinceId = $data['provinceId'] ?? null;
        $sampleCollectionDate = $data['sampleCollectionDate'] = DateUtility::isoDateFormat($data['sampleCollectionDate'], true);

        // Check for existing sample
        $update = "no";
        $rowData = null;
        $uniqueId = null;
        if (!empty($data['labId']) && !empty($data['appSampleCode'])) {
            $sQuery = "SELECT vl_sample_id, unique_id, sample_code, remote_sample_code, result_status, locked
                        FROM form_vl
                        WHERE (app_sample_code like ? AND lab_id = ?) ";

            $rowData = $db->rawQueryOne($sQuery, [$data['appSampleCode'], $data['labId']]);
            if (!empty($rowData)) {
                if ($rowData['result_status'] == 7 || $rowData['locked'] == 'yes') {
                    $noOfFailedRecords++;
                    $responseData[$rootKey] = [
                        'transactionId' => $transactionId,
                        'appSampleCode' => $data['appSampleCode'] ?? null,
                        'status' => 'failed',
                        'action' => 'skipped',
                        'error' => _translate("Sample Locked or Finalized")
                    ];
                    continue;
                }
                $update = "yes";
                $uniqueId = $rowData['unique_id'];
            } else {
                $uniqueId = MiscUtility::generateULID();
            }
        }

        // Process sample insertion/update setup
        $currentSampleData = [];
        if (!empty($rowData)) {
            $data['vlSampleId'] = $rowData['vl_sample_id'];
            $currentSampleData['sampleCode'] = $rowData['sample_code'] ?? null;
            $currentSampleData['remoteSampleCode'] = $rowData['remote_sample_code'] ?? null;
            $currentSampleData['uniqueId'] = $rowData['unique_id'] ?? null;
            $currentSampleData['action'] = 'updated';
        } else {
            $params['appSampleCode'] = $data['appSampleCode'] ?? null;
            $params['provinceCode'] = $provinceCode;
            $params['provinceId'] = $provinceId;
            $params['uniqueId'] = $uniqueId;
            $params['sampleCollectionDate'] = $sampleCollectionDate;
            $params['userId'] = $user['user_id'];
            $params['accessType'] = $roleUser['access_type'] ?? $user['access_type'];
            $params['instanceType'] = $general->getInstanceType();
            $params['facilityId'] = $data['facilityId'] ?? null;
            $params['labId'] = $data['labId'] ?? null;
            $params['insertOperation'] = true;

            $currentSampleData['id'] = $vlService->insertSample($params);
            $uniqueIdsForSampleCodeGeneration[] = $currentSampleData['uniqueId'] = $uniqueId;
            $currentSampleData['action'] = 'inserted';
            $data['vlSampleId'] = (int) $currentSampleData['id'];
            if ($data['vlSampleId'] == 0) {
                $noOfFailedRecords++;
                $responseData[$rootKey] = [
                    'transactionId' => $transactionId,
                    'appSampleCode' => $data['appSampleCode'] ?? null,
                    'status' => 'failed',
                    'action' => 'skipped',
                    'error' => _translate("Failed to insert sample")
                ];
                continue;
            }
        }

        // Process result status and data
        $status = RECEIVED_AT_TESTING_LAB;
        if ($roleUser['access_type'] != 'testing-lab') {
            $status = RECEIVED_AT_CLINIC;
        }

        $data['sampleDispatchedOn'] = (isset($data['dateDispatchedFromClinicToLab']) && !empty($data['dateDispatchedFromClinicToLab'])) ? $data['dateDispatchedFromClinicToLab'] : $data['sampleDispatchedOn'];

        if (isset($data['patientGender']) && trim((string) $data['patientGender']) === 'male') {
            $data['patientPregnant'] = null;
            $data['breastfeeding'] = null;
        }

        if (isset($data['tnd']) && $data['tnd'] == 'yes' && $data['isSampleRejected'] == 'no') {
            $data['vlResult'] = 'Target Not Detected';
        }
        if (isset($data['bdl']) && $data['bdl'] == 'bdl' && $data['isSampleRejected'] == 'no') {
            $data['vlResult'] = 'Below Detection Level';
        }

        // Process viral load results
        $processedResults = $vlService->processViralLoadResultFromForm($data);
        $isRejected = $processedResults['isRejected'];
        $finalResult = $processedResults['finalResult'];
        $absDecimalVal = $processedResults['absDecimalVal'];
        $absVal = $processedResults['absVal'];
        $logVal = $processedResults['logVal'];
        $txtVal = $processedResults['txtVal'];
        $hivDetection = $processedResults['hivDetection'];
        $status = $processedResults['resultStatus'] ?? $status;

        // Prepare form attributes
        $formAttributes = [
            'applicationVersion' => $version,
            'apiTransactionId' => $transactionId,
            'mobileAppVersion' => $appVersion,
            'deviceId' => $userAttributes['deviceId'] ?? null
        ];

        /* Reason for VL Result changes */
        $reasonForChanges = null;
        $allChange = [];
        if (isset($data['reasonForResultChanges']) && !empty($data['reasonForResultChanges'])) {
            foreach ($data['reasonForResultChanges'] as $row) {
                $allChange[] = [
                    'usr' => $row['changed_by'],
                    'msg' => $row['reason'],
                    'dtime' => $row['change_datetime']
                ];
            }
        }
        if ($allChange !== []) {
            $reasonForChanges = json_encode($allChange);
        }

        /* Field missing corrections */
        $data['dob'] ??= $data['patientDob'] ?? null;
        $data['sampleTestingDateAtLab'] ??= $data['sampleTestedDateTime'] ?? null;

        // BUILD THE COMPLETE DATA ARRAY FIRST
        $vlFulldata = [
            'vlsm_instance_id' => $instanceId,
            'sample_collection_date' => $sampleCollectionDate,
            'external_sample_code' => $data['externalSampleCode'] ?? $data['appSampleCode'] ?? null,
            'app_sample_code' => $data['appSampleCode'] ?? $data['externalSampleCode'] ?? null,
            'lab_sample_code' => $data['labSampleCode'] ?? null,
            'sample_reordered' => $data['sampleReordered'] ?? 'no',
            'facility_id' => $data['facilityId'] ?? null,
            'patient_gender' => $data['patientGender'] ?? null,
            'health_insurance_code' => $data['healthInsuranceCode'] ?? null,
            'patient_dob' => DateUtility::isoDateFormat($data['dob']),
            'patient_age_in_years' => $data['ageInYears'] ?? null,
            'patient_age_in_months' => $data['ageInMonths'] ?? null,
            'is_patient_pregnant' => $data['patientPregnant'] ?? null,
            'is_patient_breastfeeding' => $data['breastfeeding'] ?? null,
            'no_of_breastfeeding_weeks' => $data['noOfBreastfeedingWeeks'] ?? null,
            'pregnancy_trimester' => $data['trimester'] ?? null,
            'patient_art_no' => $data['patientArtNo'] ?? null,
            'treatment_initiated_date' => DateUtility::isoDateFormat($data['dateOfArtInitiation'] ?? ''),
            'reason_for_regimen_change' => $data['reasonForArvRegimenChange'] ?? null,
            'regimen_change_date' => DateUtility::isoDateFormat($data['dateOfArvRegimenChange'] ?? ''),
            'current_regimen' => $data['artRegimen'] ?? null,
            'date_of_initiation_of_current_regimen' => DateUtility::isoDateFormat($data['regimenInitiatedOn'] ?? '', true),
            'patient_mobile_number' => $data['patientPhoneNumber'] ?? null,
            'consent_to_receive_sms' => $data['receiveSms'] ?? 'no',
            'specimen_type' => $data['specimenType'] ?? null,
            'arv_adherance_percentage' => $data['arvAdherence'] ?? null,
            'reason_for_vl_testing' => $data['reasonForVLTesting'] ?? $data['vlTestReason'] ?? null,
            'community_sample' => $data['communitySample'] ?? null,
            'last_vl_date_routine' => DateUtility::isoDateFormat($data['rmTestingLastVLDate'] ?? ''),
            'last_vl_result_routine' => $data['rmTestingVlValue'] ?? null,
            'last_vl_date_failure_ac' => DateUtility::isoDateFormat($data['repeatTestingLastVLDate'] ?? ''),
            'last_vl_result_failure_ac' => $data['repeatTestingVlValue'] ?? null,
            'line_of_treatment' => $data['lineOfTreatment'] ?? null,
            'last_vl_date_failure' => DateUtility::isoDateFormat($data['suspendTreatmentLastVLDate'] ?? ''),
            'last_vl_result_failure' => $data['suspendTreatmentVlValue'] ?? null,
            'request_clinician_name' => $data['reqClinician'] ?? null,
            'request_clinician_phone_number' => $data['reqClinicianPhoneNumber'] ?? null,
            'test_requested_on' => DateUtility::isoDateFormat($data['requestDate'] ?? ''),
            'vl_focal_person' => $data['vlFocalPerson'] ?? null,
            'vl_focal_person_phone_number' => $data['vlFocalPersonPhoneNumber'] ?? null,
            'lab_id' => $data['labId'] ?? null,
            'vl_test_platform' => $data['testingPlatform'] ?? null,
            'sample_received_at_hub_datetime' => DateUtility::isoDateFormat($data['sampleReceivedAtHubOn'] ?? '', true),
            'sample_received_at_lab_datetime' => DateUtility::isoDateFormat($data['sampleReceivedDate'] ?? '', true),
            'sample_tested_datetime' => DateUtility::isoDateFormat($data['sampleTestingDateAtLab'], true),
            'sample_dispatched_datetime' => DateUtility::isoDateFormat($data['sampleDispatchedOn'] ?? '', true),
            'result_dispatched_datetime' => DateUtility::isoDateFormat($data['resultDispatchedOn'] ?? '', true),
            'result_value_hiv_detection' => $hivDetection,
            'reason_for_failure' => $data['reasonForFailure'] ?? null,
            'is_sample_rejected' => $isRejected ?? null,
            'reason_for_sample_rejection' => $data['rejectionReason'] ?? null,
            'rejection_on' => DateUtility::isoDateFormat($data['rejectionDate'] ?? ''),
            'result_value_absolute' => $absVal ?? null,
            'result_value_absolute_decimal' => $absDecimalVal ?? null,
            'result_value_text' => $txtVal ?? null,
            'result' => $finalResult ?? null,
            'result_value_log' => $logVal ?? null,
            'tested_by' => $data['testedBy'] ?? null,
            'result_approved_by' => $data['approvedBy'] ?? null,
            'result_approved_datetime' => DateUtility::isoDateFormat($data['approvedOnDateTime'] ?? '', true),
            'revised_by' => $data['revisedBy'] ?? null,
            'revised_on' => DateUtility::isoDateFormat($data['revisedOn'] ?? '', true),
            'lab_tech_comments' => $data['labComments'] ?? null,
            'reason_for_result_changes' => $reasonForChanges ?? null,
            'result_status' => $status,
            'funding_source' => $data['fundingSource'] ?? null,
            'implementing_partner' => $data['implementingPartner'] ?? null,
            'request_created_datetime' => DateUtility::isoDateFormat($data['createdOn'] ?? date('Y-m-d'), true),
            'last_modified_datetime' => DateUtility::getCurrentDateTime(),
            'manual_result_entry' => 'yes',
            'is_patient_new' => $data['isPatientNew'] ?? null,
            'has_patient_changed_regimen' => $data['hasChangedRegimen'] ?? null,
            'vl_test_number' => $data['viralLoadNo'] ?? null,
            'last_viral_load_result' => $data['lastViralLoadResult'] ?? null,
            'last_viral_load_date' => DateUtility::isoDateFormat($data['lastViralLoadTestDate'] ?? ''),
            'facility_support_partner' => $data['implementingPartner'] ?? null,
            'date_test_ordered_by_physician' => DateUtility::isoDateFormat($data['dateOfDemand'] ?? ''),
            'result_reviewed_by' => $data['reviewedBy'] ?? null,
            'result_reviewed_datetime' => DateUtility::isoDateFormat($data['reviewedOn'] ?? '', true),
            'source_of_request' => $data['sourceOfRequest'] ?? "API",
            'result_sent_to_source' => 'pending'
        ];

        // Process patient names - combining into single field as per your logic
        $vlFulldata['patient_first_name'] = $data['patientFirstName'] ?? '';
        $vlFulldata['patient_middle_name'] = $data['patientMiddleName'] ?? '';
        $vlFulldata['patient_last_name'] = $data['patientLastName'] ?? '';

        $patientFullName = [];
        if (!in_array(trim((string) $vlFulldata['patient_first_name']), ['', '0'], true)) {
            $patientFullName[] = trim((string) $vlFulldata['patient_first_name']);
        }
        if (!in_array(trim((string) $vlFulldata['patient_middle_name']), ['', '0'], true)) {
            $patientFullName[] = trim((string) $vlFulldata['patient_middle_name']);
        }
        if (!in_array(trim((string) $vlFulldata['patient_last_name']), ['', '0'], true)) {
            $patientFullName[] = trim((string) $vlFulldata['patient_last_name']);
        }

        $patientFullName = $patientFullName === [] ? '' : implode(" ", $patientFullName);
        $vlFulldata['patient_first_name'] = $patientFullName;
        $vlFulldata['patient_middle_name'] = null;
        $vlFulldata['patient_last_name'] = null;

        // Set user and timestamps
        if (!empty($rowData)) {
            $vlFulldata['last_modified_datetime'] = (empty($data['updatedOn'])) ? DateUtility::getCurrentDateTime() : DateUtility::isoDateFormat($data['updatedOn'], true);
            $vlFulldata['last_modified_by'] = $user['user_id'];
        } else {
            $vlFulldata['request_created_datetime'] = DateUtility::isoDateFormat($data['createdOn'] ?? date('Y-m-d'), true);
            $vlFulldata['request_created_by'] = $user['user_id'];
        }

        $vlFulldata['last_modified_by'] = $user['user_id'];

        // Process result category
        $vlFulldata['vl_result_category'] = $vlService->getVLResultCategory($vlFulldata['result_status'], $vlFulldata['result']);
        if ($vlFulldata['vl_result_category'] == 'failed' || $vlFulldata['vl_result_category'] == 'invalid') {
            $vlFulldata['result_status'] = TEST_FAILED;
        } elseif ($vlFulldata['vl_result_category'] == 'rejected') {
            $vlFulldata['result_status'] = REJECTED;
        }


        // ========================================
        // DUPLICATE DETECTION - ONLY FOR NEW SAMPLES WITH DIFFERENT CODES
        // ========================================
        $duplicateWarning = null;

        // Only check for duplicates if:
        // 1. This is a new sample (no existing rowData), OR
        // 2. This is an update but the appSampleCode is different from existing sample codes
        $shouldCheckDuplicates = false;

        if ($enableDuplicateDetection) {
            if (empty($rowData)) {
                // New sample - always check for duplicates
                $shouldCheckDuplicates = true;
                LoggerUtility::logInfo("Duplicate check: New sample", [
                    'appSampleCode' => $data['appSampleCode']
                ]);
            } else {
                // Existing sample - only check if sample codes are different
                $existingSampleCode = $rowData['sample_code'] ?? '';
                $existingRemoteSampleCode = $rowData['remote_sample_code'] ?? '';
                $currentAppSampleCode = $data['appSampleCode'] ?? '';

                // Check if this is truly a different sample
                if (
                    $currentAppSampleCode !== $existingSampleCode &&
                    $currentAppSampleCode !== $existingRemoteSampleCode
                ) {
                    $shouldCheckDuplicates = true;
                    LoggerUtility::logInfo("Duplicate check: Different sample code", [
                        'currentAppSampleCode' => $currentAppSampleCode,
                        'existingSampleCode' => $existingSampleCode,
                        'existingRemoteSampleCode' => $existingRemoteSampleCode
                    ]);
                } else {
                    LoggerUtility::logInfo("Duplicate check: Same sample - skipping", [
                        'currentAppSampleCode' => $currentAppSampleCode,
                        'existingSampleCode' => $existingSampleCode,
                        'existingRemoteSampleCode' => $existingRemoteSampleCode
                    ]);
                }
            }
        }

        if ($shouldCheckDuplicates) {
            try {
                // Pass the entire vlFulldata array - let the function extract what it needs
                // Add exclude ID for updates
                $vlFulldata['excludeSampleId'] = empty($rowData) ? null : $rowData['vl_sample_id'];

                $duplicateCheck = $testRequestsService->detectDuplicateSample($vlFulldata, 'vl', 7, true);

                LoggerUtility::logInfo("DEBUG: Duplicate detection result", [
                    'appSampleCode' => $data['appSampleCode'],
                    'isDuplicate' => $duplicateCheck['isDuplicate'] ?? false,
                    'duplicateCount' => $duplicateCheck['duplicateCount'] ?? 0,
                    'riskLevel' => $duplicateCheck['riskLevel'] ?? 'none',
                    'error' => $duplicateCheck['error'] ?? null
                ]);

                // IMPORTANT: Remove excludeSampleId from data before database operations
                unset($vlFulldata['excludeSampleId']);

                if ($duplicateCheck['isDuplicate']) {
                    $riskLevel = $duplicateCheck['riskLevel'];
                    $duplicateCount = $duplicateCheck['duplicateCount'];

                    // Store duplicate info for response payload
                    $duplicateInfo[$rootKey] = [
                        'detected' => true,
                        'riskLevel' => $riskLevel,
                        'duplicateCount' => $duplicateCount,
                        'withinDays' => $duplicateCheck['withinDays'],
                        'searchCriteria' => $duplicateCheck['searchCriteria'],
                        'duplicates' => array_map(fn($dup): array => [
                            'sampleCode' => $dup['sample_code'] ?? $dup['remote_sample_code'] ?? $dup['app_sample_code'],
                            'collectionDate' => $dup['sample_collection_date_formatted'],
                            'daysDifference' => $dup['days_abs_difference'],
                            'facility' => $dup['facility_name'],
                            'lab' => $dup['lab_name'],
                            'matchConfidence' => $dup['match_confidence'],
                            'riskLevel' => $dup['risk_level']
                        ], array_slice($duplicateCheck['duplicates'], 0, 5))
                    ];

                    // Log duplicate detection
                    LoggerUtility::logInfo("Duplicate sample detected", [
                        'appSampleCode' => $data['appSampleCode'],
                        'patientArtNo' => $vlFulldata['patient_art_no'],
                        'patientName' => $vlFulldata['patient_first_name'],
                        'facilityId' => $vlFulldata['facility_id'],
                        'riskLevel' => $riskLevel,
                        'duplicateCount' => $duplicateCount,
                        'action' => $riskLevel === 'high' ? 'blocked' : 'allowed_with_warning'
                    ]);

                    // Business logic based on risk level
                    switch ($riskLevel) {
                        case 'high':
                            // Block high-risk duplicates (within 1 day)
                            $noOfFailedRecords++;
                            $duplicateBlockedRecords++;
                            $responseData[$rootKey] = [
                                'transactionId' => $transactionId,
                                'appSampleCode' => $data['appSampleCode'] ?? null,
                                'status' => 'failed',
                                'action' => 'blocked_duplicate',
                                'message' => _translate("Potential duplicate sample detected within 1 day"),
                                'duplicateInfo' => $duplicateInfo[$rootKey]
                            ];
                            continue 2; // Continue to next record in the main loop

                        case 'medium':
                            // Warn but allow medium-risk duplicates (2-3 days)
                            $duplicateWarningRecords++;
                            $duplicateWarning = [
                                'detected' => true,
                                'riskLevel' => $riskLevel,
                                'count' => $duplicateCount,
                                'withinDays' => $duplicateCheck['withinDays'],
                                'message' => _translate("Medium-risk duplicate detected") . " ($duplicateCount duplicates within {$duplicateCheck['withinDays']} days)"
                            ];
                            break;

                        case 'low':
                            // Log low-risk duplicates for monitoring (4-7 days)
                            $duplicateWarning = [
                                'detected' => true,
                                'riskLevel' => $riskLevel,
                                'count' => $duplicateCount,
                                'withinDays' => $duplicateCheck['withinDays']
                            ];
                            break;
                    }
                }
            } catch (Throwable $duplicateError) {
                // Log duplicate detection errors but don't block the sample
                LoggerUtility::logError("Duplicate detection failed for sample", [
                    'appSampleCode' => $data['appSampleCode'],
                    'error' => $duplicateError->getMessage(),
                    'trace' => $duplicateError->getTraceAsString()
                ]);

                // Add error info for debugging
                $duplicateInfo[$rootKey] = [
                    'detected' => false,
                    'error' => $duplicateError->getMessage(),
                    'timestamp' => DateUtility::getCurrentDateTime()
                ];

                // IMPORTANT: Ensure excludeSampleId is removed even on error
                if (isset($vlFulldata['excludeSampleId'])) {
                    unset($vlFulldata['excludeSampleId']);
                }
            }
        } else {
            // Skip duplicate detection - just log why
            LoggerUtility::logInfo("Duplicate detection skipped", [
                'appSampleCode' => $data['appSampleCode'],
                'reason' => empty($rowData) ? 'new_sample' : 'same_sample_code'
            ]);
        }

        // Add duplicate detection info to form attributes (only for allowed samples)
        if ($duplicateWarning !== null && $duplicateWarning !== []) {
            $formAttributes['duplicateWarning'] = $duplicateWarning;
        }
        if (isset($duplicateInfo[$rootKey]) && !isset($duplicateInfo[$rootKey]['error'])) {
            $formAttributes['duplicateDetection'] = $duplicateInfo[$rootKey];
        }

        // Finalize form attributes
        $formAttributes = JsonUtility::jsonToSetString(json_encode($formAttributes), 'form_attributes');
        $vlFulldata['form_attributes'] = $formAttributes === null || $formAttributes === '' || $formAttributes === '0' ? null : $db->func($formAttributes);

        // Clean up data and perform database update
        $vlFulldata = MiscUtility::arrayEmptyStringsToNull($vlFulldata);

        // Double-check that excludeSampleId is not in the data
        if (isset($vlFulldata['excludeSampleId'])) {
            unset($vlFulldata['excludeSampleId']);
        }

        $id = false;

        if (!empty($data['vlSampleId'])) {
            $db->where('vl_sample_id', $data['vlSampleId']);
            $id = $db->update('form_vl', $vlFulldata);
        }

        // Build response
        if ($id === true) {
            $sampleResponse = [
                'status' => 'success',
                'action' => $currentSampleData['action'] ?? null,
                'sampleCode' => $currentSampleData['remoteSampleCode'] ?? $currentSampleData['sampleCode'] ?? null,
                'transactionId' => $transactionId,
                'uniqueId' => $uniqueId ?? $currentSampleData['uniqueId'] ?? null,
                'appSampleCode' => $data['appSampleCode'] ?? null,
            ];

            // Add duplicate warning information if present
            if ($duplicateWarning !== null && $duplicateWarning !== []) {
                $sampleResponse['warning'] = $duplicateWarning['message'];
                $sampleResponse['duplicateWarning'] = $duplicateWarning;
            }

            // Add duplicate detection info if available
            if (isset($duplicateInfo[$rootKey]) && !isset($duplicateInfo[$rootKey]['error'])) {
                $sampleResponse['duplicateInfo'] = $duplicateInfo[$rootKey];
            }

            $responseData[$rootKey] = $sampleResponse;
        } else {
            $noOfFailedRecords++;
            $responseData[$rootKey] = [
                'transactionId' => $transactionId,
                'status' => 'failed',
                'action' => 'skipped',
                'appSampleCode' => $data['appSampleCode'] ?? null,
                'error' => _translate('Failed to process this request. Please contact the system administrator if the problem persists'),
            ];
        }
    }

    // Commit transaction after processing all records
    $db->commitTransaction();

    // For inserted samples, generate sample code
    if ($uniqueIdsForSampleCodeGeneration !== []) {
        $sampleCodeData = $testRequestsService->processSampleCodeQueue(uniqueIds: $uniqueIdsForSampleCodeGeneration, parallelProcess: true);
        if (!empty($sampleCodeData)) {
            foreach ($responseData as $rootKey => $currentSampleData) {
                $uniqueId = $currentSampleData['uniqueId'] ?? null;
                if ($uniqueId && isset($sampleCodeData[$uniqueId])) {
                    $responseData[$rootKey]['sampleCode'] = $sampleCodeData[$uniqueId]['remote_sample_code'] ?? $sampleCodeData[$uniqueId]['sample_code'] ?? null;
                }
            }
        }
    }

    // Determine overall payload status
    if ($noOfFailedRecords > 0 && $noOfFailedRecords == $dataCounter) {
        $payloadStatus = 'failed';
    } elseif ($noOfFailedRecords > 0) {
        $payloadStatus = 'partial';
    } else {
        $payloadStatus = 'success';
    }

    if (!empty($data['lab_id'])) {
        $updatedLabs[] = $data['lab_id'];
    }

    // Payload with summary
    $payload = [
        'status' => $payloadStatus,
        'timestamp' => time(),
        'transactionId' => $transactionId,
        'data' => $responseData ?? [],
        'summary' => [
            'isDuplicateCheckEnabled' => $enableDuplicateDetection ? 'yes' : 'no',
            'totalRecords' => $dataCounter,
            'successfulRecords' => $dataCounter - $noOfFailedRecords,
            'failedRecords' => $noOfFailedRecords,
        ]
    ];

    // Add detailed duplicate information only if duplicates were detected
    if ($enableDuplicateDetection && $duplicateInfo !== []) {
        $payload['duplicateDetails'] = $duplicateInfo;
        $payload['summary']['duplicateRecords'] = [
            'blockedRecords' => $duplicateBlockedRecords,
            'warningRecords' => $duplicateWarningRecords,
            'totalDuplicatesDetected' => count($duplicateInfo),
            'duplicates' => [
                'highRisk' => $duplicateBlockedRecords,
                'mediumRisk' => $duplicateWarningRecords - array_sum(array_map(fn($info): int => isset($info['riskLevel']) && $info['riskLevel'] === 'low' ? 1 : 0, $duplicateInfo)),
                'lowRisk' => array_sum(array_map(fn($info): int => isset($info['riskLevel']) && $info['riskLevel'] === 'low' ? 1 : 0, $duplicateInfo))
            ]
        ];
    }

    http_response_code(200);
} catch (Throwable $e) {
    $db->rollbackTransaction();
    http_response_code(500);
    $payload = [
        'status' => 'failed',
        'timestamp' => time(),
        'transactionId' => $transactionId,
        'error' => _translate('Failed to process this request. Please contact the system administrator if the problem persists'),
        'data' => []
    ];
    LoggerUtility::logError($e->getMessage(), [
        'transactionId' => $transactionId,
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
        'last_db_query' => $db->getLastQuery(),
        'last_db_error' => $db->getLastError()
    ]);
}

$payload = JsonUtility::encodeUtf8Json($payload);

$general->addApiTracking($transactionId, $user['user_id'], $dataCounter, 'save-request', 'vl', $_SERVER['REQUEST_URI'], $origJson, $payload, 'json', null, null, $authToken);

$general->updateResultSyncDateTime('vl', null, $updatedLabs);

echo ApiService::generateJsonResponse($payload, $request);
