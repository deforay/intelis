<?php

namespace App\Services;


use MysqliDb;
use Exception;
use DateTimeImmutable;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;
use App\Services\GeoLocationsService;

/**
 * Generic tests functions
 *
 * @author Amit
 */

class GenericTestsService
{

    protected ?MysqliDb $db = null;
    protected string $table = 'form_generic';
    protected string $shortCode = 'T';
    protected CommonService $commonService;

    public function __construct($db = null, $commonService = null)
    {
        $this->db = $db ?? ContainerRegistry::get('db');
        $this->commonService = $commonService;
    }

    public function generateGenericTestSampleCode($provinceCode, $sampleCollectionDate, $sampleFrom = null, $provinceId = '', $maxCodeKeyVal = null, $user = null, $testType = null)
    {
        $globalConfig = $this->commonService->getGlobalConfig();
        $vlsmSystemConfig = $this->commonService->getSystemConfig();

        if (DateUtility::verifyIfDateValid($sampleCollectionDate) === false) {
            $sampleCollectionDate = 'now';
        }
        $dateObj = new DateTimeImmutable($sampleCollectionDate);

        $year = $dateObj->format('y');
        $month = $dateObj->format('m');
        $day = $dateObj->format('d');

        $remotePrefix = '';
        $sampleCodeKeyCol = 'sample_code_key';
        $sampleCodeCol = 'sample_code';
        if ($vlsmSystemConfig['sc_user_type'] == 'remoteuser') {
            $remotePrefix = 'R';
            $sampleCodeKeyCol = 'remote_sample_code_key';
            $sampleCodeCol = 'remote_sample_code';
        }

        $mnthYr = $month . $year;
        // Checking if sample code format is empty then we set by default 'MMYY'
        $sampleCodeFormat = $globalConfig['sample_code'] ?? 'MMYY';
        $prefixFromConfig = $globalConfig['sample_code_prefix'] ?? '';
        if (!empty($testType)) {
            $prefixFromConfig = $testType;
        }
        if ($sampleCodeFormat == 'MMYY') {
            $mnthYr = $month . $year;
        } elseif ($sampleCodeFormat == 'YY') {
            $mnthYr = $year;
        }

        $autoFormatedString = $year . $month . $day;


        if (empty($maxCodeKeyVal)) {
            // If it is PNG form
            if ($globalConfig['vl_form'] == 5) {

                if (empty($provinceId) && !empty($provinceCode)) {
                    /** @var GeoLocationsService $geoLocationsService */
                    $geoLocationsService = ContainerRegistry::get(GeoLocationsService::class);
                    $provinceId = $geoLocationsService->getProvinceIDFromCode($provinceCode);
                }

                if (!empty($provinceId)) {
                    $this->db->where('province_id', $provinceId);
                }
            }

            $this->db->where('YEAR(sample_collection_date) = ?', array($dateObj->format('Y')));
            $maxCodeKeyVal = $this->db->setQueryOption('FOR UPDATE')->getValue($this->table, "MAX($sampleCodeKeyCol)");
        }


        if (!empty($maxCodeKeyVal) && $maxCodeKeyVal > 0) {
            $maxId = $maxCodeKeyVal + 1;
        } else {
            $maxId = 1;
        }

        $maxId = sprintf("%04d", (int) $maxId);

        $sampleCodeGenerator = [
            'sampleCode' => '',
            'sampleCodeInText' => '',
            'sampleCodeFormat' => '',
            'sampleCodeKey' => '',
            'maxId' => $maxId,
            'mnthYr' => $mnthYr,
            'auto' => $autoFormatedString
        ];

        if ($globalConfig['vl_form'] == 5) {
            // PNG format has an additional R in prefix
            $remotePrefix = $remotePrefix . "R";
        }
        if ($sampleCodeFormat == 'auto') {
            if (!empty($testType)) {
                $remotePrefix = $remotePrefix . $testType;
            }
            $sampleCodeGenerator['sampleCode'] = ($remotePrefix . $provinceCode . $autoFormatedString . $sampleCodeGenerator['maxId']);
            $sampleCodeGenerator['sampleCodeInText'] = ($remotePrefix . $provinceCode . $autoFormatedString . $sampleCodeGenerator['maxId']);
            $sampleCodeGenerator['sampleCodeFormat'] = ($remotePrefix . $provinceCode . $autoFormatedString);
            $sampleCodeGenerator['sampleCodeKey'] = ($sampleCodeGenerator['maxId']);
        } elseif ($sampleCodeFormat == 'auto2') {
            if (!empty($testType)) {
                $remotePrefix = $remotePrefix . $testType;
            }
            $sampleCodeGenerator['sampleCode'] = $remotePrefix . $year . $provinceCode . $this->shortCode . $sampleCodeGenerator['maxId'];
            $sampleCodeGenerator['sampleCodeInText'] = $remotePrefix . $year . $provinceCode . $this->shortCode . $sampleCodeGenerator['maxId'];
            $sampleCodeGenerator['sampleCodeFormat'] = $remotePrefix . $provinceCode . $autoFormatedString;
            $sampleCodeGenerator['sampleCodeKey'] = $sampleCodeGenerator['maxId'];
        } elseif ($sampleCodeFormat == 'YY' || $sampleCodeFormat == 'MMYY') {
            $sampleCodeGenerator['sampleCode'] = $remotePrefix . $prefixFromConfig . $sampleCodeGenerator['mnthYr'] . $sampleCodeGenerator['maxId'];
            $sampleCodeGenerator['sampleCodeInText'] = $remotePrefix . $prefixFromConfig . $sampleCodeGenerator['mnthYr'] . $sampleCodeGenerator['maxId'];
            $sampleCodeGenerator['sampleCodeFormat'] = $remotePrefix . $prefixFromConfig . $sampleCodeGenerator['mnthYr'];
            $sampleCodeGenerator['sampleCodeKey'] = ($sampleCodeGenerator['maxId']);
        }

        $checkQuery = "SELECT $sampleCodeCol,
                            $sampleCodeKeyCol
                            FROM $this->table
                            WHERE $sampleCodeCol=?";
        $checkResult = $this->db->rawQueryOne($checkQuery, [$sampleCodeGenerator['sampleCode']]);
        if (!empty($checkResult)) {
            // error_log("DUP::: Sample Code ====== " . $sampleCodeGenerator['sampleCode']);
            // error_log("DUP::: Sample Key Code ====== " . $maxId);
            // error_log('DUP::: ' . $this->db->getLastQuery());
            return $this->generateGenericTestSampleCode($provinceCode, $sampleCollectionDate, $sampleFrom, $provinceId, $maxId, $user);
        }
        return json_encode($sampleCodeGenerator);
    }

    public function getGenericSampleTypesByName($name = "")
    {
        $where = "";
        if (!empty($name)) {
            $where = " AND sample_type_name LIKE '$name%'";
        }
        $query = "SELECT * FROM r_generic_sample_types where sample_type_status='active '$where";
        return $this->db->rawQuery($query);
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

    public function getGenericResults(): array
    {
        return array(
            'positive' => 'Positive',
            'negative' => 'Negative',
            'invalid'  => 'Invalid'
        );
    }

    public function insertSampleCode($params, $returnSampleData = false)
    {
        try {

            $formId = $this->commonService->getGlobalConfig('vl_form');

            $testType = $params['testType'] ?? null;
            $provinceCode = $params['provinceCode'] ?? null;
            $provinceId = $params['provinceId'] ?? null;
            $sampleCollectionDate = $params['sampleCollectionDate'] ?? null;

            // PNG FORM (formId = 5) CANNOT HAVE PROVINCE EMPTY
            // Sample Collection Date Cannot be Empty
            // Test Type cannot be empty
            if (
                empty($testType) ||
                empty($sampleCollectionDate) ||
                ($formId == 5 && empty($provinceId))
            ) {
                return 0;
            }

            $oldSampleCodeKey = $params['oldSampleCodeKey'] ?? null;

            $sampleJson = $this->generateGenericTestSampleCode($provinceCode, $sampleCollectionDate, null, $provinceId, $oldSampleCodeKey, null, $testType);
            $sampleData = json_decode($sampleJson, true);

            $sQuery = "SELECT sample_id FROM form_generic ";
            if (!empty($sampleData['sampleCode'])) {
                $sQuery .= " WHERE (sample_code like '" . $sampleData['sampleCode'] . "' OR remote_sample_code like '" . $sampleData['sampleCode'] . "')";
            }
            $sQuery .= " LIMIT 1";
            $rowData = $this->db->rawQueryOne($sQuery);
            $id = 0;

            if (empty($rowData) && !empty($sampleData['sampleCode'])) {

                $tesRequestData = [
                    'vlsm_country_id' => $formId,
                    'unique_id' => $params['uniqueId'] ?? $this->commonService->generateUUID(),
                    'facility_id' => $params['facilityId'] ?? null,
                    'lab_id' => $params['labId'] ?? null,
                    'app_sample_code' => $params['appSampleCode'] ?? null,
                    'sample_collection_date' => DateUtility::isoDateFormat($sampleCollectionDate, true),
                    'vlsm_instance_id' => $_SESSION['instanceId'] ?? $this->commonService->getInstanceId() ?? null,
                    'province_id' => $provinceId,
                    'test_type' => $testType,
                    'request_created_by' => $_SESSION['userId'] ?? $params['userId'] ?? null,
                    'form_attributes' => $params['formAttributes'] ?? "{}",
                    'request_created_datetime' => DateUtility::getCurrentDateTime(),
                    'last_modified_by' => $_SESSION['userId'] ?? $params['userId'] ?? null,
                    'last_modified_datetime' => DateUtility::getCurrentDateTime()
                ];

                $accessType = $_SESSION['accessType'] ?? $params['accessType'] ?? null;
                $instanceType = $_SESSION['instanceType'] ?? $params['instanceType'] ?? null;

                if ($instanceType === 'remoteuser') {
                    $tesRequestData['remote_sample_code'] = $sampleData['sampleCode'];
                    $tesRequestData['remote_sample_code_format'] = $sampleData['sampleCodeFormat'];
                    $tesRequestData['remote_sample_code_key'] = $sampleData['sampleCodeKey'];
                    $tesRequestData['remote_sample'] = 'yes';
                    $tesRequestData['result_status'] = 9;
                    if ($accessType === 'testing-lab') {
                        $tesRequestData['sample_code'] = $sampleData['sampleCode'];
                        $tesRequestData['result_status'] = 6;
                    }
                } else {
                    $tesRequestData['sample_code'] = $sampleData['sampleCode'];
                    $tesRequestData['sample_code_format'] = $sampleData['sampleCodeFormat'];
                    $tesRequestData['sample_code_key'] = $sampleData['sampleCodeKey'];
                    $tesRequestData['remote_sample'] = 'no';
                    $tesRequestData['result_status'] = 6;
                }

                $formAttributes = [
                    'applicationVersion'  => $this->commonService->getSystemConfig('sc_version'),
                    'ip_address'    => $this->commonService->getClientIpAddress()
                ];
                $tesRequestData['form_attributes'] = json_encode($formAttributes);
                $this->db->insert("form_generic", $tesRequestData);
                $id = $this->db->getInsertId();
                if ($this->db->getLastErrno() > 0) {
                    error_log($this->db->getLastError());
                    error_log($this->db->getLastQuery());
                }
            } else {
                // If this sample code exists, let us regenerate the sample code and insert
                $params['oldSampleCodeKey'] = $sampleData['sampleCodeKey'];
                return $this->insertSampleCode($params);
            }
        } catch (Exception $e) {
            error_log('Insert lab tests Sample : ' . $this->db->getLastErrno());
            error_log('Insert lab tests Sample : ' . $this->db->getLastError());
            error_log('Insert lab tests Sample : ' . $this->db->getLastQuery());
            error_log('Insert lab tests Sample : ' . $e->getMessage());
            $id = 0;
        }

        if ($returnSampleData === true) {
            return [
                'id' => max($id, 0),
                'uniqueId' => $tesRequestData['unique_id'] ?? null,
                'sampleCode' => $tesRequestData['sample_code'] ?? null,
                'remoteSampleCode' => $tesRequestData['remote_sample_code'] ?? null
            ];
        } else {
            return max($id, 0);
        }
    }

    public function getDynamicFields($genericTestId): array
    {
        $return = [];
        if ($genericTestId > 0) {
            $labelsResponse = $dynamicJson = [];
            $this->db->where("sample_id", $genericTestId);
            $generic = $this->db->getOne('form_generic');
            if ($generic['test_type_form']) {
                $dynamicJson = (array)json_decode($generic['test_type_form']);
                $this->db->where('test_type_id', $generic['test_type']);
                $testTypes = $this->db->getOne('r_test_types');
                $labels = json_decode($testTypes['test_form_config'], true);

                foreach ($labels['field_id'] as $key => $le) {
                    $labelsResponse[$le] = $labels['field_name'][$key];
                }
            }
            $return = array('dynamicValue' => $dynamicJson, 'dynamicLabel' => $labelsResponse);
        }
        return $return;
    }

    public function getReasonForFailure($option = true, $updatedDateTime = null)
    {
        $result = [];
        $this->db->where('test_failure_reason_status', 'active');
        if ($updatedDateTime) {
            $this->db->where('updated_datetime >= "' . $updatedDateTime . '"');
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

        $resultConfig = json_decode($testTypeResult['test_results_config'], true);
        $return = null;
        if (isset($resultConfig['result_type']) && $resultConfig['result_type'] == 'quantitative') {
            if (is_numeric($result)) {
                if ($result >= $resultConfig['high_value']) {
                    $return = $resultConfig['above_threshold'];
                }
                if ($result == $resultConfig['threshold_value']) {
                    $return = $resultConfig['at_threshold'];
                }
                if ($result < $resultConfig['low_value']) {
                    $return = $resultConfig['below_threshold'];
                }
            } else {
                $resultIndex =  (isset($result) && isset($resultConfig['quantitative_result']) && in_array($result, $resultConfig['quantitative_result'])) ? array_search(strtolower($result), array_map('strtolower', $resultConfig['quantitative_result'])) : '';
                $return = $resultConfig['quantitative_result_interpretation'][$resultIndex];
            }
        } elseif (isset($resultConfig['result_type']) && $resultConfig['result_type'] == 'qualitative') {
            $resultIndex =  (isset($result) && isset($resultConfig['result']) && in_array($result, $resultConfig['result'])) ? array_search(strtolower($result), array_map('strtolower', $resultConfig['result'])) : '';
            $return = $resultConfig['result_interpretation'][$resultIndex];
        }

        return $return;
    }

    public function getGenericTestsByFormId($genId = ""): array
    {
        $response = [];

        // Using this in sync requests/results
        if (is_array($genId) && !empty($genId)) {
            $results = $this->db->rawQuery("SELECT * FROM generic_test_results WHERE `generic_id` IN (" . implode(",", $genId) . ") ORDER BY test_id ASC");

            foreach ($results as $row) {
                $response[$row['generic_id']][$row['test_id']] = $row;
            }
        } elseif (!empty($genId) && $genId != "" && !is_array($genId)) {
            $response = $this->db->rawQuery("SELECT * FROM generic_test_results WHERE `generic_id` = $genId ORDER BY test_id ASC");
        } elseif (!is_array($genId)) {
            $response = $this->db->rawQuery("SELECT * FROM generic_test_results ORDER BY test_id ASC");
        }

        return $response;
    }

    // Quickly insert data in dynamic
    public function quickInsert($table, $fields, $values)
    {
        // echo "<pre>";print_r(array_combine($fields, $values));die;
        return $this->db->insert($table, array_combine($fields, $values));
    }

    public function getSampleType($testTypeId)
    {
        $sampleTypeQry = "SELECT * FROM r_generic_sample_types as st INNER JOIN generic_test_sample_type_map as map ON map.sample_type_id=st.sample_type_id WHERE map.test_type_id=$testTypeId AND st.sample_type_status='active'";
        return $this->db->query($sampleTypeQry);
    }

    public function getTestReason($testTypeId)
    {
        $testReasonQry = "SELECT * FROM r_generic_test_reasons as tr INNER JOIN generic_test_reason_map as map ON map.test_reason_id=tr.test_reason_id WHERE map.test_type_id=$testTypeId AND tr.test_reason_status='active'";
        return $this->db->query($testReasonQry);
    }

    public function getTestMethod($testTypeId)
    {
        $testMethodQry = "SELECT * FROM r_generic_test_methods as tm INNER JOIN generic_test_methods_map as map ON map.test_method_id=tm.test_method_id WHERE map.test_type_id=$testTypeId AND tm.test_method_status='active'";
        return $this->db->query($testMethodQry);
    }

    public function getTestResultUnit($testTypeId)
    {
        $testResultUnitQry = "SELECT * FROM r_generic_test_result_units as tu INNER JOIN generic_test_result_units_map as map ON map.unit_id=tu.unit_id WHERE map.test_type_id=$testTypeId AND tu.unit_status='active'";
        return $this->db->query($testResultUnitQry);
    }
}
