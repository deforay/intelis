<?php

namespace App\Services;


use MysqliDb;
use Exception;
use DateTimeImmutable;
use App\Utilities\DateUtility;
use App\Registries\ContainerRegistry;

/**
 * General functions
 *
 * @author Amit
 */

class GenericTestsService
{

    protected ?MysqliDb $db = null;
    protected string $table = 'form_generic';
    protected string $shortCode = 'LAB';

    public function __construct($db = null)
    {
        $this->db = $db ?? ContainerRegistry::get('db');
    }

    public function generateGenericSampleID($provinceCode, $sampleCollectionDate, $sampleFrom = null, $provinceId = '', $maxCodeKeyVal = null, $user = null, $testType = null)
    {

        if (!empty($maxCodeKeyVal)) {
            error_log(" ===== MAXX Code ====== " . $maxCodeKeyVal);
        }


        /** @var CommonService $general */
        $general = ContainerRegistry::get(CommonService::class);

        $globalConfig = $general->getGlobalConfig();
        $vlsmSystemConfig = $general->getSystemConfig();

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
        if (isset($testType) && !empty($testType)) {
            $prefixFromConfig = $testType;
        }
        if ($sampleCodeFormat == 'MMYY') {
            $mnthYr = $month . $year;
        } elseif ($sampleCodeFormat == 'YY') {
            $mnthYr = $year;
        }

        $autoFormatedString = $year . $month . $day;


        if ($maxCodeKeyVal === null) {
            // If it is PNG form
            if ($globalConfig['vl_form'] == 5) {

                if (empty($provinceId) && !empty($provinceCode)) {
                    $geoLocations = new GeoLocationsService($this->db);
                    $provinceId = $geoLocations->getProvinceIDFromCode($provinceCode);
                }

                if (!empty($provinceId)) {
                    $this->db->where('province_id', $provinceId);
                }
            }

            $this->db->where('YEAR(sample_collection_date) = ?', array($dateObj->format('Y')));
            $maxCodeKeyVal = $this->db->getValue($this->table, "MAX($sampleCodeKeyCol)");
        }


        if (!empty($maxCodeKeyVal) && $maxCodeKeyVal > 0) {
            $maxId = $maxCodeKeyVal + 1;
        } else {
            $maxId = 1;
        }

        $maxId = sprintf("%04d", (int) $maxId);

        $sCodeKey = (array('maxId' => $maxId, 'mnthYr' => $mnthYr, 'auto' => $autoFormatedString));

        if ($globalConfig['vl_form'] == 5) {
            // PNG format has an additional R in prefix
            $remotePrefix = $remotePrefix . "R";
        }
        if ($sampleCodeFormat == 'auto') {
            if (isset($testType) && !empty($testType)) {
                $remotePrefix = $remotePrefix . $testType;
            }
            $sCodeKey['sampleCode'] = ($remotePrefix . $provinceCode . $autoFormatedString . $sCodeKey['maxId']);
            $sCodeKey['sampleCodeInText'] = ($remotePrefix . $provinceCode . $autoFormatedString . $sCodeKey['maxId']);
            $sCodeKey['sampleCodeFormat'] = ($remotePrefix . $provinceCode . $autoFormatedString);
            $sCodeKey['sampleCodeKey'] = ($sCodeKey['maxId']);
        } elseif ($sampleCodeFormat == 'auto2') {
            if (isset($testType) && !empty($testType)) {
                $remotePrefix = $remotePrefix . $testType;
            }
            $sCodeKey['sampleCode'] = $remotePrefix . $year . $provinceCode . $this->shortCode . $sCodeKey['maxId'];
            $sCodeKey['sampleCodeInText'] = $remotePrefix . $year . $provinceCode . $this->shortCode . $sCodeKey['maxId'];
            $sCodeKey['sampleCodeFormat'] = $remotePrefix . $provinceCode . $autoFormatedString;
            $sCodeKey['sampleCodeKey'] = $sCodeKey['maxId'];
        } elseif ($sampleCodeFormat == 'YY' || $sampleCodeFormat == 'MMYY') {
            $sCodeKey['sampleCode'] = $remotePrefix . $prefixFromConfig . $sCodeKey['mnthYr'] . $sCodeKey['maxId'];
            $sCodeKey['sampleCodeInText'] = $remotePrefix . $prefixFromConfig . $sCodeKey['mnthYr'] . $sCodeKey['maxId'];
            $sCodeKey['sampleCodeFormat'] = $remotePrefix . $prefixFromConfig . $sCodeKey['mnthYr'];
            $sCodeKey['sampleCodeKey'] = ($sCodeKey['maxId']);
        }

        $checkQuery = "SELECT $sampleCodeCol, $sampleCodeKeyCol FROM " . $this->table . " WHERE $sampleCodeCol='" . $sCodeKey['sampleCode'] . "'";
        $checkResult = $this->db->rawQueryOne($checkQuery);
        if ($checkResult !== null) {
            error_log("DUP::: Sample Code ====== " . $sCodeKey['sampleCode']);
            error_log("DUP::: Sample Key Code ====== " . $maxId);
            error_log('DUP::: ' . $this->db->getLastQuery());
            return $this->generateGenericSampleID($provinceCode, $sampleCollectionDate, $sampleFrom, $provinceId, $maxId, $user);
        }
        return json_encode($sCodeKey);
    }

    public function generateSampleIDGenericTest($provinceCode, $sampleCollectionDate, $sampleFrom = null, $provinceId = '', $maxCodeKeyVal = null, $user = null, $testType = null)
    {

        if (!empty($maxCodeKeyVal)) {
            error_log(" ===== MAXX Code ====== " . $maxCodeKeyVal);
        }


        /** @var CommonService $general */
        $general = ContainerRegistry::get(CommonService::class);

        $globalConfig = $general->getGlobalConfig();
        $vlsmSystemConfig = $general->getSystemConfig();

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

        // if (isset($user['access_type']) && !empty($user['access_type']) && $user['access_type'] != 'testing-lab') {
        //     $remotePrefix = 'R';
        //     $sampleCodeKeyCol = 'remote_sample_code_key';
        //     $sampleCodeCol = 'remote_sample_code';
        // }

        $mnthYr = $month . $year;
        // Checking if sample code format is empty then we set by default 'MMYY'
        $sampleCodeFormat = $globalConfig['sample_code'] ?? 'MMYY';
        $prefixFromConfig = $globalConfig['sample_code_prefix'] ?? '';
        if (isset($testType) && !empty($testType)) {
            $prefixFromConfig = $testType;
        }
        if ($sampleCodeFormat == 'MMYY') {
            $mnthYr = $month . $year;
        } elseif ($sampleCodeFormat == 'YY') {
            $mnthYr = $year;
        }

        $autoFormatedString = $year . $month . $day;


        if ($maxCodeKeyVal === null) {
            // If it is PNG form
            if ($globalConfig['vl_form'] == 5) {

                if (empty($provinceId) && !empty($provinceCode)) {
                    $geoLocations = new GeoLocationsService($this->db);
                    $provinceId = $geoLocations->getProvinceIDFromCode($provinceCode);
                }

                if (!empty($provinceId)) {
                    $this->db->where('province_id', $provinceId);
                }
            }

            $this->db->where('YEAR(sample_collection_date) = ?', array($dateObj->format('Y')));
            $maxCodeKeyVal = $this->db->getValue($this->table, "MAX($sampleCodeKeyCol)");
        }


        if (!empty($maxCodeKeyVal) && $maxCodeKeyVal > 0) {
            $maxId = $maxCodeKeyVal + 1;
        } else {
            $maxId = 1;
        }

        $maxId = sprintf("%04d", (int) $maxId);

        $sCodeKey = (array('maxId' => $maxId, 'mnthYr' => $mnthYr, 'auto' => $autoFormatedString));

        if ($globalConfig['vl_form'] == 5) {
            // PNG format has an additional R in prefix
            $remotePrefix = $remotePrefix . "R";
        }

        if ($sampleCodeFormat == 'auto') {
            if (isset($testType) && !empty($testType)) {
                $remotePrefix = $remotePrefix . $testType;
            }
            $sCodeKey['sampleCode'] = ($remotePrefix . $provinceCode . $autoFormatedString . $sCodeKey['maxId']);
            $sCodeKey['sampleCodeInText'] = ($remotePrefix . $provinceCode . $autoFormatedString . $sCodeKey['maxId']);
            $sCodeKey['sampleCodeFormat'] = ($remotePrefix . $provinceCode . $autoFormatedString);
            $sCodeKey['sampleCodeKey'] = ($sCodeKey['maxId']);
        } elseif ($sampleCodeFormat == 'auto2') {
            if (isset($testType) && !empty($testType)) {
                $remotePrefix = $remotePrefix . $testType;
            }
            $sCodeKey['sampleCode'] = $remotePrefix . $year . $provinceCode . $this->shortCode . $sCodeKey['maxId'];
            $sCodeKey['sampleCodeInText'] = $remotePrefix . $year . $provinceCode . $this->shortCode . $sCodeKey['maxId'];
            $sCodeKey['sampleCodeFormat'] = $remotePrefix . $provinceCode . $autoFormatedString;
            $sCodeKey['sampleCodeKey'] = $sCodeKey['maxId'];
        } elseif ($sampleCodeFormat == 'YY' || $sampleCodeFormat == 'MMYY') {
            $sCodeKey['sampleCode'] = $remotePrefix . $prefixFromConfig . $sCodeKey['mnthYr'] . $sCodeKey['maxId'];
            $sCodeKey['sampleCodeInText'] = $remotePrefix . $prefixFromConfig . $sCodeKey['mnthYr'] . $sCodeKey['maxId'];
            $sCodeKey['sampleCodeFormat'] = $remotePrefix . $prefixFromConfig . $sCodeKey['mnthYr'];
            $sCodeKey['sampleCodeKey'] = ($sCodeKey['maxId']);
        }
        $checkQuery = "SELECT $sampleCodeCol, $sampleCodeKeyCol FROM form_generic WHERE $sampleCodeCol='" . $sCodeKey['sampleCode'] . "'";
        $checkResult = $this->db->rawQueryOne($checkQuery);
        // if ($checkResult !== null) {
        //     $sCodeKey['sampleCode'] = $remotePrefix . $prefixFromConfig . $sCodeKey['mnthYr'] . ($sCodeKey['maxId'] + 1);
        //     $sCodeKey['sampleCodeInText'] = $remotePrefix . $prefixFromConfig . $sCodeKey['mnthYr'] . ($sCodeKey['maxId'] + 1);
        //     $sCodeKey['sampleCodeFormat'] = $remotePrefix . $prefixFromConfig . $sCodeKey['mnthYr'];
        //     $sCodeKey['sampleCodeKey'] = ($sCodeKey['maxId'] + 1);
        // }
        if ($checkResult !== null) {
            error_log("DUP::: Sample Code ====== " . $sCodeKey['sampleCode']);
            error_log("DUP::: Sample Key Code ====== " . $maxId);
            error_log('DUP::: ' . $this->db->getLastQuery());
            return $this->generateGenericSampleID($provinceCode, $sampleCollectionDate, $sampleFrom, $provinceId, $maxId, $user);
        }
        return json_encode($sCodeKey);
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

    public function getGenericSampleTypes($updatedDateTime = null)
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

    public function getGenericResults()
    {
        return array(
            'positive' => 'Positive',
            'negative' => 'Negative',
            'invalid'  => 'Invalid'
        );
    }

    public function insertSampleCodeGenericTest($params)
    {
        try {

            /** @var CommonService $general */
            $general = ContainerRegistry::get(CommonService::class);

            $globalConfig = $general->getGlobalConfig();
            $vlsmSystemConfig = $general->getSystemConfig();

            $testType = $params['testType'] ?? null;
            $provinceCode = $params['provinceCode'] ?? null;
            $provinceId = $params['provinceId'] ?? null;
            $sampleCollectionDate = $params['sampleCollectionDate'] ?? null;

            if (empty($testType) || empty($sampleCollectionDate) || ($globalConfig['vl_form'] == 5 && empty($provinceId))) {
                return 0;
            }

            $oldSampleCodeKey = $params['oldSampleCodeKey'] ?? null;

            $sampleJson = $this->generateSampleIDGenericTest($provinceCode, $sampleCollectionDate, null, $provinceId, $oldSampleCodeKey, null, $testType);
            $sampleData = json_decode($sampleJson, true);
            $sampleCollectionDate = DateUtility::isoDateFormat($sampleCollectionDate, true);

            $vlData = [
                'vlsm_country_id' => $globalConfig['vl_form'],
                'sample_collection_date' => $sampleCollectionDate,
                'vlsm_instance_id' => $_SESSION['instanceId'] ?? $params['instanceId'] ?? null,
                'province_id' => $provinceId,
                'request_created_by' => $_SESSION['userId'] ?? $params['userId'] ?? null,
                'request_created_datetime' => DateUtility::getCurrentDateTime(),
                'last_modified_by' => $_SESSION['userId'] ?? $params['userId'] ?? null,
                'last_modified_datetime' => DateUtility::getCurrentDateTime()
            ];

            $oldSampleCodeKey = null;
            if ($vlsmSystemConfig['sc_user_type'] === 'remoteuser') {
                $vlData['remote_sample_code'] = $sampleData['sampleCode'];
                $vlData['remote_sample_code_format'] = $sampleData['sampleCodeFormat'];
                $vlData['remote_sample_code_key'] = $sampleData['sampleCodeKey'];
                $vlData['remote_sample'] = 'yes';
                $vlData['result_status'] = 9;
                if ($_SESSION['accessType'] === 'testing-lab') {
                    $vlData['sample_code'] = $sampleData['sampleCode'];
                    $vlData['result_status'] = 6;
                }
            } else {
                $vlData['sample_code'] = $sampleData['sampleCode'];
                $vlData['sample_code_format'] = $sampleData['sampleCodeFormat'];
                $vlData['sample_code_key'] = $sampleData['sampleCodeKey'];
                $vlData['remote_sample'] = 'no';
                $vlData['result_status'] = 6;
            }
            $sQuery = "SELECT sample_id, sample_code, sample_code_format, sample_code_key, remote_sample_code, remote_sample_code_format, remote_sample_code_key FROM form_generic ";
            if (isset($sampleData['sampleCode']) && !empty($sampleData['sampleCode'])) {
                $sQuery .= " WHERE (sample_code like '" . $sampleData['sampleCode'] . "' OR remote_sample_code like '" . $sampleData['sampleCode'] . "')";
            }
            $sQuery .= " LIMIT 1";
            $rowData = $this->db->rawQueryOne($sQuery);
            $version = $general->getSystemConfig('sc_version');
            $ipaddress = $general->getClientIpAddress();
            $formAttributes = [
                'applicationVersion'  => $version,
                'ip_address'    => $ipaddress
            ];
            $vlData['form_attributes'] = json_encode($formAttributes);


            $id = 0;
            if (!empty($rowData)) {
                // If this sample code exists, let us regenerate
                $params['oldSampleCodeKey'] = $sampleData['sampleCodeKey'];
                return $this->insertSampleCodeGenericTest($params);
            } else {
                if (isset($params['api']) && $params['api'] = "yes") {
                    $id = $this->db->insert("form_generic", $vlData);
                    $params['GenericSampleId'] = $id;
                } else {
                    if (isset($params['sampleCode']) && $params['sampleCode'] != '' && $params['sampleCollectionDate'] != null && $params['sampleCollectionDate'] != '') {
                        $vlData['unique_id'] = $general->generateUUID();
                        $id = $this->db->insert("form_generic", $vlData);
                        error_log($this->db->getLastError());
                    }
                }
            }

            return $id > 0 ? $id : 0;
        } catch (Exception $e) {
            error_log('Insert lab tests Sample : ' . $this->db->getLastErrno());
            error_log('Insert lab tests Sample : ' . $this->db->getLastError());
            error_log('Insert lab tests Sample : ' . $this->db->getLastQuery());
            error_log('Insert lab tests Sample : ' . $e->getMessage());
            return 0;
        }
    }

    public function getDynamicFields($genericTestId)
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
        if (!isset($result) || empty($result)) {
            return null;
        }
        if (!isset($testType) || empty($testType)) {
            return null;
        }
        $this->db->where('test_type_id', $testType);
        $testTypeResult = $this->db->getOne('r_test_types');
        if (isset($testTypeResult['test_results_config']) && !empty($testTypeResult['test_results_config'])) {
            $resultConfig = json_decode($testTypeResult['test_results_config'], true);
            if (isset($resultConfig['result_type']) && $resultConfig['result_type'] == 'quantitative') {
                if (is_numeric($result)) {
                    if ($result >= $resultConfig['high_value']) {
                        return ucwords($resultConfig['above_threshold']);
                    }
                    if ($result == $resultConfig['threshold_value']) {
                        return ucwords($resultConfig['at_threshold']);
                    }
                    if ($result < $resultConfig['low_value']) {
                        return ucwords($resultConfig['below_threshold']);
                    }
                } else {
                    $resultIndex =  (isset($result) && isset($resultConfig['quantitative_result']) && in_array($result, $resultConfig['quantitative_result'])) ? array_search(strtolower($result), array_map('strtolower', $resultConfig['quantitative_result'])) : '';
                    return ucwords($resultConfig['quantitative_result_interpretation'][$resultIndex]);
                }
            } else if (isset($resultConfig['result_type']) && $resultConfig['result_type'] == 'qualitative') {
                $resultIndex =  (isset($result) && isset($resultConfig['result']) && in_array($result, $resultConfig['result'])) ? array_search(strtolower($result), array_map('strtolower', $resultConfig['result'])) : '';
                return ucwords($resultConfig['result_interpretation'][$resultIndex]);
            }
        }
    }
}
