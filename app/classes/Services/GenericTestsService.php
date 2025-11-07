<?php

namespace App\Services;

use App\Utilities\MiscUtility;
use COUNTRY;
use Throwable;
use SAMPLE_STATUS;
use App\Utilities\DateUtility;
use App\Utilities\LoggerUtility;
use App\Exceptions\SystemException;
use App\Abstracts\AbstractTestService;


final class GenericTestsService extends AbstractTestService
{
    public string $testType = 'generic-tests';



    public function getSampleCode($params)
    {
        if (empty($params['sampleCollectionDate'])) {
            throw new SystemException("Sample Collection Date is required to generate Sample ID", 400);
        } else {
            $globalConfig = $this->commonService->getGlobalConfig();
            $params['sampleCodeFormat'] = $globalConfig['sample_code'] ?? 'MMYY';
            $params['prefix'] ??= $params['testType'] ?? $this->shortCode;
            $params['postfix'] ??= '';

            try {
                return $this->generateSampleCode($this->table, $params);
            } catch (Throwable $e) {
                LoggerUtility::logError('Unable to generate Sample ID : ' . $e->getMessage(), [
                    'exception' => $e,
                    'file' => $e->getFile(), // File where the error occurred
                    'line' => $e->getLine(), // Line number of the error
                    'stacktrace' => $e->getTraceAsString()
                ]);
                return json_encode([]);
            }
        }
    }

    public function getGenericSampleTypes($updatedDateTime = null): array
    {
        $query = "SELECT * FROM r_generic_sample_types where sample_type_status='active'";
        if ($updatedDateTime) {
            $query .= " AND updated_datetime >= '$updatedDateTime' ";
        }
        $results = $this->db->rawQuery($query);
        $response = [];
        foreach ($results as $row) {
            $response[$row['sample_type_id']] = $row['sample_type_name'];
        }
        return $response;
    }

    public function insertSample($params, $returnSampleData = false): int|array
    {
        try {

            // Start a new transaction (this starts a new transaction if not already started)
            // see the beginTransaction() function implementation to understand how this works
            $this->db->beginTransaction();

            $formId = (int) $this->commonService->getGlobalConfig('vl_form');

            $this->testType = $params['testType'] ?? $this->testType ?? 'generic-tests';
            $provinceId = $params['provinceId'] ?? null;
            $sampleCollectionDate = $params['sampleCollectionDate'] ?? null;

            // PNG FORM (formId = 5) CANNOT HAVE PROVINCE EMPTY
            // Sample Collection Date Cannot be Empty
            // Test Type cannot be empty
            if (empty($this->testType) || empty($sampleCollectionDate) || ($formId == COUNTRY\PNG && empty($provinceId))) {
                return 0;
            }

            $uniqueId = $params['uniqueId'] ?? MiscUtility::generateULID();
            $accessType = $params['accessType'] ?? $_SESSION['accessType'] ?? null;
            $params['prefix'] ??= $params['testType'] ?? $this->shortCode;
            $params['postfix'] ??= '';

            // Insert into the Code Generation Queue
            $this->testRequestsService->addToSampleCodeQueue(
                $uniqueId,
                'generic-tests',
                DateUtility::isoDateFormat($sampleCollectionDate, true),
                $params['provinceCode'] ?? null,
                $params['sampleCodeFormat'] ?? null,
                $params['prefix'] ?? $this->shortCode,
                $accessType
            );

            $id = 0;
            $tesRequestData = [
                'vlsm_country_id' => $formId,
                'sample_reordered' => $params['sampleReordered'] ?? 'no',
                'unique_id' => $uniqueId,
                'facility_id' => $params['facilityId'] ?? null,
                'lab_id' => $params['labId'] ?? null,
                'app_sample_code' => $params['appSampleCode'] ?? null,
                'sample_collection_date' => DateUtility::isoDateFormat($sampleCollectionDate, true),
                'vlsm_instance_id' => $_SESSION['instanceId'] ?? $this->commonService->getInstanceId() ?? null,
                'province_id' => _castVariable($provinceId, 'int'),
                'test_type' => $this->testType,
                'request_created_by' => $_SESSION['userId'] ?? $params['userId'] ?? null,
                'form_attributes' => $params['formAttributes'] ?? "{}",
                'request_created_datetime' => DateUtility::getCurrentDateTime(),
                'last_modified_by' => $_SESSION['userId'] ?? $params['userId'] ?? null,
                'last_modified_datetime' => DateUtility::getCurrentDateTime(),
                'is_result_sms_sent'  => 'no',
                'is_result_mail_sent'  => 'no',
                'locked' => 'no'
            ];

            if ($this->commonService->isSTSInstance()) {
                $tesRequestData['remote_sample'] = 'yes';
                $tesRequestData['result_status'] = SAMPLE_STATUS\RECEIVED_AT_CLINIC;
                if ($accessType === 'testing-lab') {
                    $tesRequestData['result_status'] = SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
                }
            } else {
                $tesRequestData['remote_sample'] = 'no';
                $tesRequestData['result_status'] = SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
            }

            $formAttributes = [
                'applicationVersion' => $this->commonService->getAppVersion(),
                'ip_address' => $this->commonService->getClientIpAddress()
            ];

            $tesRequestData['form_attributes'] = json_encode($formAttributes);
            $this->db->insert("form_generic", $tesRequestData);
            $id = $this->db->getInsertId();
            if ($this->db->getLastErrno() > 0) {
                throw new SystemException($this->db->getLastErrno() . " | " .  $this->db->getLastError());
            }
            // Commit the transaction after the successful insert
            $this->db->commitTransaction();
        } catch (Throwable $e) {
            // Rollback the current transaction to release locks and undo changes
            $this->db->rollbackTransaction();

            LoggerUtility::logError('Insert Generic Sample : ' . $e->getMessage(), [
                'exception' => $e,
                'file' => $e->getFile(), // File where the error occurred
                'line' => $e->getLine(), // Line number of the error
                'stacktrace' => $e->getTraceAsString()
            ]);
            $id = 0;
        }
        if ($returnSampleData === true) {
            return [
                'id' => max($id, 0),
                'uniqueId' => $uniqueId
            ];
        } else {
            return max($id, 0);
        }
    }

    public function getDynamicFields($genericTestId): array
    {
        $return = [];
        if ($genericTestId > 0) {
            $labelsResponse = $dynamicJson = $testTypes = [];
            $this->db->where("sample_id", $genericTestId);
            $generic = $this->db->getOne('form_generic');
            if ($generic['test_type_form']) {
                $dynamicJson = (array) json_decode((string) $generic['test_type_form']);
                $this->db->where('test_type_id', $generic['test_type']);
                $testTypes = $this->db->getOne('r_test_types');
                $labels = json_decode((string) $testTypes['test_form_config'], true);

                foreach ($labels['field_id'] as $key => $le) {
                    $labelsResponse[$le] = $labels['field_name'][$key];
                }
                unset(
                    $testTypes['test_form_config'],
                    $testTypes['test_results_config']
                );
            }
            $return = array('dynamicValue' => $dynamicJson, 'dynamicLabel' => $labelsResponse, 'testDetails' => $testTypes);
        }
        return $return;
    }

    public function getReasonForFailure($option = true, $updatedDateTime = null)
    {
        $result = [];
        $this->db->where('test_failure_reason_status', 'active');
        if ($updatedDateTime) {
            $this->db->where("updated_datetime >= '$updatedDateTime'");
        }
        $results = $this->db->get('r_generic_test_failure_reasons');
        if ($option) {
            foreach ($results as $row) {
                $result[$row['test_failure_reason_id']] = $row['test_failure_reason'];
            }
            return $result;
        } else {
            return $results;
        }
    }

    public function getInterpretationResults($testType, $result)
    {
        if (empty($result) || empty($testType)) {
            return null;
        }

        $this->db->where('test_type_id', $testType);
        $testTypeResult = $this->db->getOne('r_test_types');

        if (empty($testTypeResult['test_results_config'])) {
            return null;
        }

        $resultConfig = json_decode((string) $testTypeResult['test_results_config'], true);
        $return = null;

        if (isset($resultConfig['result_type'][1]) && $resultConfig['result_type'][1] == 'quantitative') {
            if (is_numeric($result)) {
                if ($result > $resultConfig['high_value']) {
                    $return = $resultConfig['above_threshold'];
                } elseif ($result == $resultConfig['threshold_value']) {
                    $return = $resultConfig['at_threshold'];
                } elseif ($result < $resultConfig['low_value']) {
                    $return = $resultConfig['below_threshold'];
                }
            } else {
                $resultIndex = (isset($result) && isset($resultConfig['quantitative_result']) && in_array($result, $resultConfig['quantitative_result'])) ? array_search(strtolower((string) $result), array_map('strtolower', $resultConfig['quantitative_result'])) : '';
                $return = $resultConfig['quantitative_result_interpretation'][$resultIndex];
            }
        } elseif (isset($resultConfig['result_type'][1]) && $resultConfig['result_type'][1] == 'qualitative') {
            //echo '<pre>'; print_r($resultConfig); die;
            $resultIndex = (isset($result) && isset($resultConfig['result']) && in_array($result, $resultConfig['result'])) ? array_search(strtolower((string) $result), array_map('strtolower', $resultConfig['result'])) : '';
            $return = $resultConfig['result_interpretation'][$resultIndex];
        }

        return $return;
    }

    public function getTestsByGenericSampleIds($genericSampleIds = null): ?array
    {
        $response = [];

        if (!empty($genericSampleIds) && is_array($genericSampleIds)) {
            $placeholders = implode(',', array_fill(0, count($genericSampleIds), '?'));
            $results = $this->db->rawQuery("SELECT * FROM generic_test_results
                                            WHERE `generic_id` IN ($placeholders)
                                            ORDER BY test_id ASC", $genericSampleIds);
            foreach ($results as $row) {
                $response[$row['generic_id']][$row['test_id']] = $row;
            }
        } elseif (!empty($genericSampleIds) && !is_array($genericSampleIds)) {
            $this->db->orderBy('test_id');
            $this->db->where('generic_id', $genericSampleIds);
            $response = $this->db->get('generic_test_results');
        } else {
            $response = $this->db->rawQuery("SELECT * FROM generic_test_results
                                            ORDER BY test_id ASC");
        }

        return $response;
    }

    public function getSampleType($testTypeId)
    {
        $sampleTypeQry = "SELECT *
                            FROM r_generic_sample_types as st
                            INNER JOIN generic_test_sample_type_map as map ON map.sample_type_id=st.sample_type_id
                            WHERE map.test_type_id=$testTypeId
                            AND st.sample_type_status='active'";
        return $this->db->query($sampleTypeQry);
    }

    public function getTestReason($testTypeId)
    {
        $testReasonQry = "SELECT *
                            FROM r_generic_test_reasons as tr
                            INNER JOIN generic_test_reason_map as map ON map.test_reason_id=tr.test_reason_id
                            WHERE map.test_type_id=$testTypeId
                            AND tr.test_reason_status='active'";
        return $this->db->query($testReasonQry);
    }

    public function getTestMethod($testTypeId)
    {
        $testMethodQry = "SELECT *
                            FROM r_generic_test_methods as tm
                            INNER JOIN generic_test_methods_map as map ON map.test_method_id=tm.test_method_id
                            WHERE map.test_type_id=$testTypeId
                            AND tm.test_method_status='active'";
        return $this->db->query($testMethodQry);
    }

    public function getTestResultUnit($testTypeId)
    {
        $testResultUnitQry = "SELECT *
                                FROM r_generic_test_result_units as tu
                                INNER JOIN generic_test_result_units_map as map ON map.unit_id=tu.unit_id
                                WHERE map.test_type_id=$testTypeId
                                AND tu.unit_status='active'";
        return $this->db->query($testResultUnitQry);
    }

    public function fetchRelaventDataUsingTestAttributeId($fcode)
    {
        if (!empty($fcode)) {
            // First get the collection of fcode from the following fcode
            $this->db->where("(JSON_SEARCH(test_form_config, 'one', '$fcode') IS NOT NULL) OR (test_form_config IS NOT NULL)");

            $this->db->orderBy('updated_datetime');
            $testTypeResult = $this->db->getOne('r_test_types', 'test_form_config');
            $testType = json_decode((string) $testTypeResult['test_form_config'], true);
            $fcodes = [];
            if (isset($testType) && !empty($testType)) {
                foreach ($testType as $section => $sectionArray) {
                    foreach ($sectionArray as $key => $value) {
                        if ($value['field_code'] == $fcode) {
                            $fcodes[] = $key;
                        }
                    }
                }
            }
            // print_r($fcodes);echo "<br>";
            // After that we get the list of available values from following fcodes
            if (isset($fcodes) && count($fcodes) > 0) {
                foreach ($fcodes as $value) {
                    $this->db->where("(JSON_SEARCH(test_type_form, 'all', '$value') IS NOT NULL) OR (test_type_form IS NOT NULL)");
                }
                $this->db->orderBy('last_modified_datetime');
                $result =  $this->db->getOne('form_generic', 'test_type_form');
                if ($result) {
                    $response = [];
                    foreach ((array) json_decode((string) $result['test_type_form']) as $key => $value) {
                        if (in_array($key, $fcodes)) {
                            $response[] = $value;
                        }
                        // print_r($key);echo "<br>";
                    }
                    // print_r($response);
                    return $response[0];
                } else {
                    return null;
                }
            }

            return null;
        } else {
            return null;
        }
    }
}
