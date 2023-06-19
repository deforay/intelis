<?php

namespace App\Services;

use MysqliDb;
use Exception;
use DateTimeImmutable;
use App\Utilities\DateUtility;
use App\Interfaces\TestInterface;
use App\Registries\ContainerRegistry;
use App\Services\GeoLocationsService;

/**
 * EID functions
 *
 * @author Amit
 */

class EidService implements TestInterface
{

    protected ?MysqliDb $db = null;
    protected string $table = 'form_eid';
    protected string $shortCode = 'EID';
    protected CommonService $commonService;

    public function __construct($db = null, $commonService = null)
    {
        $this->db = $db ?? ContainerRegistry::get('db');
        $this->commonService = $commonService;
    }

    public function generateSampleCode($params)
    {

        $globalConfig = $this->commonService->getGlobalConfig();
        $vlsmSystemConfig = $this->commonService->getSystemConfig();

        $sampleCollectionDate = $params['sampleCollectionDate'] ?? null;
        $provinceCode = $params['provinceCode'] ?? null;
        $provinceId = $params['provinceId'] ?? null;
        $maxCodeKeyVal = $params['maxCodeKeyVal'] ?? null;

        if (empty($sampleCollectionDate) || DateUtility::verifyIfDateValid($sampleCollectionDate) === false) {
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
        $sampleCodeFormat = $globalConfig['eid_sample_code'] ?? 'MMYY';
        $prefixFromConfig = $globalConfig['eid_sample_code_prefix'] ?? '';

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
                    $params['provinceId'] = $provinceId = $geoLocationsService->getProvinceIDFromCode($provinceCode);
                }

                if (!empty($provinceId)) {
                    $this->db->where('province_id', $provinceId);
                }
            }

            $this->db->where('YEAR(sample_collection_date) = ?', [$dateObj->format('Y')]);
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
            $sampleCodeGenerator['sampleCode'] = ($remotePrefix . $provinceCode . $autoFormatedString . $sampleCodeGenerator['maxId']);
            $sampleCodeGenerator['sampleCodeInText'] = ($remotePrefix . $provinceCode . $autoFormatedString . $sampleCodeGenerator['maxId']);
            $sampleCodeGenerator['sampleCodeFormat'] = ($remotePrefix . $provinceCode . $autoFormatedString);
            $sampleCodeGenerator['sampleCodeKey'] = ($sampleCodeGenerator['maxId']);
        } elseif ($sampleCodeFormat == 'auto2') {
            $sampleCodeGenerator['sampleCode'] = $remotePrefix . date('y', strtotime($sampleCollectionDate)) . $provinceCode . $this->shortCode . $sampleCodeGenerator['maxId'];
            $sampleCodeGenerator['sampleCodeInText'] = $remotePrefix . date('y', strtotime($sampleCollectionDate)) . $provinceCode . $this->shortCode . $sampleCodeGenerator['maxId'];
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
            $params['maxCodeKeyVal'] = $maxId;
            return $this->generateSampleCode($params);
        }
        return json_encode($sampleCodeGenerator);
    }


    public function getEidResults($updatedDateTime = null): array
    {
        $query = "SELECT * FROM r_eid_results WHERE status='active' ";
        if ($updatedDateTime) {
            $query .= " AND updated_datetime >= '$updatedDateTime' ";
        }
        $query .= " ORDER BY result_id";
        $results = $this->db->rawQuery($query);
        $response = [];
        foreach ($results as $row) {
            $response[$row['result_id']] = $row['result'];
        }
        return $response;
    }

    public function getEidSampleTypes($updatedDateTime = null): array
    {
        $query = "SELECT * FROM r_eid_sample_type where status='active' ";
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

    public function insertSample($params, $returnSampleData = false)
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

            $sampleCodeParams = [];
            $sampleCodeParams['sampleCollectionDate'] = $params['sampleCollectionDate'] ?? null;
            $sampleCodeParams['provinceCode'] = $params['provinceCode'] ?? null;
            $sampleCodeParams['provinceId'] = $params['provinceId'] ?? null;
            $sampleCodeParams['maxCodeKeyVal'] = $params['oldSampleCodeKey'] ?? null;

            $sampleJson = $this->generateSampleCode($sampleCodeParams);
            $sampleData = json_decode($sampleJson, true);

            $sQuery = "SELECT eid_id FROM form_eid ";
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
                $this->db->insert("form_eid", $tesRequestData);
                $id = $this->db->getInsertId();
                if ($this->db->getLastErrno() > 0) {
                    error_log($this->db->getLastError());
                }
            } else {
                // If this sample code exists, let us regenerate the sample code and insert
                $params['oldSampleCodeKey'] = $sampleData['sampleCodeKey'];
                return $this->insertSample($params);
            }
        } catch (Exception $e) {
            error_log('Insert EID Sample : ' . $this->db->getLastErrno());
            error_log('Insert EID Sample : ' . $this->db->getLastError());
            error_log('Insert EID Sample : ' . $this->db->getLastQuery());
            error_log('Insert EID Sample : ' . $e->getMessage());
            $id =  0;
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
