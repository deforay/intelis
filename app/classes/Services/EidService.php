<?php

namespace App\Services;

use MysqliDb;
use Exception;
use DateTimeImmutable;
use App\Utilities\DateUtility;
use App\Registries\ContainerRegistry;
use App\Services\GeoLocationsService;

/**
 * EID functions
 *
 * @author Amit
 */

class EidService
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

    public function generateEIDSampleCode($provinceCode, $sampleCollectionDate, $sampleFrom = null, $provinceId = '', $maxCodeKeyVal = null, $user = null)
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
        $sampleCodeFormat = $globalConfig['eid_sample_code'] ?? 'MMYY';
        $prefixFromConfig = $globalConfig['eid_sample_code_prefix'] ?? '';

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
                    /** @var GeoLocations $geoLocations */
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

        $sampleCodeGenerator = (array('maxId' => $maxId, 'mnthYr' => $mnthYr, 'auto' => $autoFormatedString));

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

        $checkQuery = "SELECT $sampleCodeCol, $sampleCodeKeyCol FROM " . $this->table . " WHERE $sampleCodeCol='" . $sampleCodeGenerator['sampleCode'] . "'";
        $checkResult = $this->db->rawQueryOne($checkQuery);
        if (!empty($checkResult)) {
            error_log("DUP::: Sample Code ====== " . $sampleCodeGenerator['sampleCode']);
            error_log("DUP::: Sample Key Code ====== " . $maxId);
            error_log('DUP::: ' . $this->db->getLastQuery());
            return $this->generateEIDSampleCode($provinceCode, $sampleCollectionDate, $sampleFrom, $provinceId, $maxId, $user);
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

    public function insertSampleCode($params)
    {
        try {
            $globalConfig = $this->commonService->getGlobalConfig();
            $vlsmSystemConfig = $this->commonService->getSystemConfig();
            
            $provinceCode = $params['provinceCode'] ?? null;
            $provinceId = $params['provinceId'] ?? null;
            $sampleCollectionDate = $params['sampleCollectionDate'] ?? null;

            if (empty($sampleCollectionDate) || ($globalConfig['vl_form'] == 5 && empty($provinceId))) {
                return 0;
            }

            $oldSampleCodeKey = $params['oldSampleCodeKey'] ?? null;
            $sampleJson = $this->generateEIDSampleCode($provinceCode, $sampleCollectionDate, null, $provinceId, $oldSampleCodeKey);
            $sampleData = json_decode($sampleJson, true);
            $sampleCollectionDate = DateUtility::isoDateFormat($sampleCollectionDate, true);

            $tesRequestData = [
                'vlsm_country_id' => $globalConfig['vl_form'],
                'unique_id' => $params['uniqueId'] ?? $this->commonService->generateUUID(),
                'facility_id' => $params['facilityId'] ?? null,
                'lab_id' => $params['labId'] ?? null,
                'app_sample_code' => $params['appSampleCode'] ?? null,
                'sample_collection_date' => $sampleCollectionDate,
                'vlsm_instance_id' => $_SESSION['instanceId'] ?? $this->commonService->getInstanceId() ?? null,
                'province_id' => $provinceId,
                'request_created_by' => $_SESSION['userId'] ?? $params['userId'] ?? null,
                'form_attributes' => $params['formAttributes'] ?? "{}",
                'request_created_datetime' => DateUtility::getCurrentDateTime(),
                'last_modified_by' => $_SESSION['userId'] ?? $params['userId'] ?? null,
                'last_modified_datetime' => DateUtility::getCurrentDateTime()
            ];

            $oldSampleCodeKey = null;

            if ($vlsmSystemConfig['sc_user_type'] === 'remoteuser') {
                $tesRequestData['remote_sample_code'] = $sampleData['sampleCode'];
                $tesRequestData['remote_sample_code_format'] = $sampleData['sampleCodeFormat'];
                $tesRequestData['remote_sample_code_key'] = $sampleData['sampleCodeKey'];
                $tesRequestData['remote_sample'] = 'yes';
                $tesRequestData['result_status'] = 9;
                if ($_SESSION['accessType'] === 'testing-lab') {
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

            $sQuery = "SELECT eid_id, sample_code, sample_code_format, sample_code_key, remote_sample_code, remote_sample_code_format, remote_sample_code_key FROM form_eid ";
            if (isset($sampleData['sampleCode']) && !empty($sampleData['sampleCode'])) {
                $sQuery .= " WHERE (sample_code like '" . $sampleData['sampleCode'] . "' OR remote_sample_code like '" . $sampleData['sampleCode'] . "')";
            }
            $sQuery .= " LIMIT 1";

            $rowData = $this->db->rawQueryOne($sQuery);


            $id = 0;
            if (empty($rowData) && !empty($sampleData['sampleCode'])) {
                $formAttributes = [
                    'applicationVersion'  => $this->commonService->getSystemConfig('sc_version'),
                    'ip_address'    => $this->commonService->getClientIpAddress()
                ];
                $tesRequestData['form_attributes'] = json_encode($formAttributes);
                $id = $this->db->insert("form_eid", $tesRequestData);
                if ($this->db->getLastErrno() > 0) {
                    error_log($this->db->getLastError());
                }
            } else {
                // If this sample code exists, let us regenerate the sample code and insert
                $params['oldSampleCodeKey'] = $sampleData['sampleCodeKey'];
                return $this->insertSampleCode($params);
            }
        } catch (Exception $e) {
            error_log('Insert EID Sample : ' . $this->db->getLastErrno());
            error_log('Insert EID Sample : ' . $this->db->getLastError());
            error_log('Insert EID Sample : ' . $this->db->getLastQuery());
            error_log('Insert EID Sample : ' . $e->getMessage());
            $id =  0;
        }
        return $id > 0 ? $id : 0;
    }
}
