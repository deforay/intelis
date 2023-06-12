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
 * TB Service
 *
 * @author Amit
 */

class TbService
{

    protected ?MysqliDb $db = null;
    protected string $table = 'form_tb';
    protected string $shortCode = 'TB';
    protected CommonService $commonService;

    public function __construct($db = null, $commonService = null)
    {
        $this->db = $db ?? ContainerRegistry::get('db');
        $this->commonService = $commonService;
    }

    public function generateTbSampleCode($provinceCode, $sampleCollectionDate, $sampleFrom = null, $provinceId = '', $maxCodeKeyVal = null, $user = null)
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
        // if (isset($user['access_type']) && !empty($user['access_type']) && $user['access_type'] != 'testing-lab') {
        //     $remotePrefix = 'R';
        //     $sampleCodeKeyCol = 'remote_sample_code_key';
        //     $sampleCodeCol = 'remote_sample_code';
        // }

        $mnthYr = $month . $year;
        // Checking if sample code format is empty then we set by default 'MMYY'
        $sampleCodeFormat = $globalConfig['tb_sample_code'] ?? 'MMYY';
        $prefixFromConfig = $globalConfig['tb_sample_code_prefix'] ?? '';

        if ($sampleCodeFormat == 'MMYY') {
            $mnthYr = $month . $year;
        } else if ($sampleCodeFormat == 'YY') {
            $mnthYr = $year;
        }

        $autoFormatedString = $year . $month . $day;


        if (empty($maxCodeKeyVal)) {
            // If it is PNG form
            if ($globalConfig['vl_form'] == 5) {

                if (empty($provinceId) && !empty($provinceCode)) {
                    /** @var GeoLocationsService $geoLocations */
                    $geoLocationsService = ContainerRegistry::get(GeoLocationsService::class);
                    $provinceId = $geoLocationsService->getProvinceIDFromCode($provinceCode);
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
            $sampleCodeGenerator['sampleCode'] = ($remotePrefix . $provinceCode . $autoFormatedString . $sampleCodeGenerator['maxId']);
            $sampleCodeGenerator['sampleCodeInText'] = ($remotePrefix . $provinceCode . $autoFormatedString . $sampleCodeGenerator['maxId']);
            $sampleCodeGenerator['sampleCodeFormat'] = ($remotePrefix . $provinceCode . $autoFormatedString);
            $sampleCodeGenerator['sampleCodeKey'] = ($sampleCodeGenerator['maxId']);
        } else if ($sampleCodeFormat == 'auto2') {
            $sampleCodeGenerator['sampleCode'] = $remotePrefix . date('y', strtotime($sampleCollectionDate)) . $provinceCode . $this->shortCode . $sampleCodeGenerator['maxId'];
            $sampleCodeGenerator['sampleCodeInText'] = $remotePrefix . date('y', strtotime($sampleCollectionDate)) . $provinceCode . $this->shortCode . $sampleCodeGenerator['maxId'];
            $sampleCodeGenerator['sampleCodeFormat'] = $remotePrefix . $provinceCode . $autoFormatedString;
            $sampleCodeGenerator['sampleCodeKey'] = $sampleCodeGenerator['maxId'];
        } else if ($sampleCodeFormat == 'YY' || $sampleCodeFormat == 'MMYY') {
            $sampleCodeGenerator['sampleCode'] = $remotePrefix . $prefixFromConfig . $sampleCodeGenerator['mnthYr'] . $sampleCodeGenerator['maxId'];
            $sampleCodeGenerator['sampleCodeInText'] = $remotePrefix . $prefixFromConfig . $sampleCodeGenerator['mnthYr'] . $sampleCodeGenerator['maxId'];
            $sampleCodeGenerator['sampleCodeFormat'] = $remotePrefix . $prefixFromConfig . $sampleCodeGenerator['mnthYr'];
            $sampleCodeGenerator['sampleCodeKey'] = ($sampleCodeGenerator['maxId']);
        }

        $checkQuery = "SELECT $sampleCodeCol, $sampleCodeKeyCol FROM " . $this->table . " where $sampleCodeCol='" . $sampleCodeGenerator['sampleCode'] . "'";
        $checkResult = $this->db->rawQueryOne($checkQuery);
        if (!empty($checkResult)) {
            return $this->generateTbSampleCode($provinceCode, $sampleCollectionDate, $sampleFrom, $provinceId, $maxId, $user);
        }

        return json_encode($sampleCodeGenerator);
    }


    public function getTbSampleTypes($updatedDateTime = null): array
    {
        $query = "SELECT * FROM r_tb_sample_type where status='active' ";
        if ($updatedDateTime) {
            $query .= " AND updated_datetime >= '$updatedDateTime' ";
        }
        $results = $this->db->rawQuery($query);
        $response = [];
        foreach ($results as $row) {
            $response[$row['sample_id']] = $row['sample_name'];
        }
        return $response;
    }

    public function getTbSampleTypesByName($name = "")
    {
        $where = "";
        if (!empty($name)) {
            $where = " AND sample_name LIKE '$name%'";
        }
        $query = "SELECT * FROM r_tb_sample_type where status='active'$where";
        return $this->db->rawQuery($query);
    }

    public function insertTbTests($tbSampleId, $testKitName = null, $labId = null, $sampleTestedDatetime = null, $result = null)
    {
        $tbTestData = array(
            'tb_id'            => $tbSampleId,
            'test_name'                => $testKitName,
            'facility_id'           => $labId,
            'sample_tested_datetime' => $sampleTestedDatetime,
            'result'                => $result
        );
        return $this->db->insert("tb_tests", $tbTestData);
    }

    public function checkAllTbTestsForPositive($tbSampleId): bool
    {
        $response = $this->db->rawQuery("SELECT * FROM tb_tests WHERE `tb_id` = $tbSampleId ORDER BY test_id ASC");

        foreach ($response as $row) {
            if ($row['result'] == 'positive') return true;
        }

        return false;
    }



    public function getTbResults($type = null, $updatedDateTime = null): array
    {
        $query = "SELECT result_id,result FROM r_tb_results where status='active' ";
        if (!empty($type)) {
            $query .= " AND result_type = '" . $type . "' ";
        }
        if ($updatedDateTime) {
            $query .= " AND updated_datetime >= '$updatedDateTime' ";
        }
        $query .= " ORDER BY result_id DESC";
        $results = $this->db->rawQuery($query);
        $response = [];
        foreach ($results as $row) {
            $response[$row['result_id']] = $row['result'];
        }
        return $response;
    }

    public function getTbReasonsForTesting($updatedDateTime = null): array
    {
        $query = "SELECT test_reason_id,test_reason_name FROM r_tb_test_reasons WHERE `test_reason_status` LIKE 'active' ";
        if ($updatedDateTime) {
            $query .= " AND updated_datetime >= '$updatedDateTime' ";
        }
        $results = $this->db->rawQuery($query);
        $response = [];
        foreach ($results as $row) {
            $response[$row['test_reason_id']] = $row['test_reason_name'];
        }
        return $response;
    }

    public function getTbReasonsForTestingDRC(): array
    {
        $results = $this->db->rawQuery("SELECT test_reason_id,test_reason_name FROM r_tb_test_reasons WHERE `test_reason_status` LIKE 'active' AND (parent_reason IS NULL OR parent_reason = 0)");
        $response = [];
        foreach ($results as $row) {
            $response[$row['test_reason_id']] = $row['test_reason_name'];
        }
        return $response;
    }

    public function getTbTestsByFormId($tbId = "")
    {
        $response = [];

        // Using this in sync requests/results
        if (isset($tbId) && is_array($tbId) && !empty($tbId)) {
            $results = $this->db->rawQuery("SELECT * FROM tb_tests WHERE `tb_id` IN (" . implode(",", $tbId) . ") ORDER BY tb_test_id ASC");

            foreach ($results as $row) {
                $response[$row['tb_id']][$row['tb_test_id']] = $row;
            }
        } else if (isset($tbId) && $tbId != "" && !is_array($tbId)) {
            $response = $this->db->rawQuery("SELECT * FROM tb_tests WHERE `tb_id` = $tbId ORDER BY tb_test_id ASC");
        } else if (!is_array($tbId)) {
            $response = $this->db->rawQuery("SELECT * FROM tb_tests ORDER BY tb_test_id ASC");
        }

        return $response;
    }

    public function fetchAllDetailsBySampleCode($sampleCode)
    {
        if (empty($sampleCode)) {
            return null;
        }
        $sQuery = "SELECT * FROM form_tb WHERE sample_code like '$sampleCode%' OR remote_sample_code LIKE '$sampleCode%'";
        return $this->db->rawQueryOne($sQuery);
    }

    public function insertSampleCode($params, $returnSampleData = false)
    {
        try {
            $formId = $this->commonService->getGlobalConfig('vl_form');
            $provinceCode = $params['provinceCode'] ?? null;
            $provinceId = $params['provinceId'] ?? null;
            $sampleCollectionDate = $params['sampleCollectionDate'] ?? null;

            // PNG FORM (formId = 5) CANNOT HAVE PROVINCE EMPTY
            // Sample Collection Date Cannot be Empty
            if (empty($sampleCollectionDate) || ($formId == 5 && empty($provinceId))) {
                return 0;
            }

            $oldSampleCodeKey = $params['oldSampleCodeKey'] ?? null;
            $sampleJson = $this->generateTbSampleCode($provinceCode, $sampleCollectionDate, null, $provinceId, $oldSampleCodeKey);
            $sampleData = json_decode($sampleJson, true);


            // echo "<pre>";
            // print_r($tesRequestData);die;

            $sQuery = "SELECT tb_id FROM form_tb ";
            if (isset($sampleData['sampleCode']) && !empty($sampleData['sampleCode'])) {
                $sQuery .= " WHERE (sample_code like '" . $sampleData['sampleCode'] . "' OR remote_sample_code like '" . $sampleData['sampleCode'] . "')";
            }
            $sQuery .= " LIMIT 1";
            $rowData = $this->db->rawQueryOne($sQuery);

            $id = 0;
            if (empty($rowData) && !empty($sampleData['sampleCode'])) {
                $tesRequestData = [
                    'vlsm_country_id' => $formId,
                    'sample_collection_date' => DateUtility::isoDateFormat($sampleCollectionDate, true),
                    'unique_id' => $params['uniqueId'] ?? $this->commonService->generateUUID(),
                    'facility_id' => $params['facilityId'] ?? null,
                    'lab_id' => $params['labId'] ?? null,
                    'app_sample_code' => $params['appSampleCode'] ?? null,
                    'vlsm_instance_id' => $_SESSION['instanceId'] ?? $this->commonService->getInstanceId() ?? null,
                    'province_id' => $provinceId,
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
                $this->db->insert("form_tb", $tesRequestData);
                $id = $this->db->getInsertId();
                if ($this->db->getLastErrno() > 0) {
                    error_log($this->db->getLastError());
                }
            } else {
                // If this sample code exists, let us regenerate the sample code and insert
                $params['oldSampleCodeKey'] = $sampleData['sampleCodeKey'];
                return $this->insertSampleCode($params);
            }
        } catch (Exception $e) {
            error_log('Insert TB Sample : ' . $this->db->getLastError());
            error_log('Insert TB Sample : ' . $e->getMessage());
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
}
