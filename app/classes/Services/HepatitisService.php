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
 * General functions
 *
 * @author Amit
 */

class HepatitisService
{

    protected ?MysqliDb $db = null;
    protected string $table = 'form_hepatitis';
    protected string $shortCode = 'H';
    public array $suppressedArray = array(
        'target not detected',
        'tnd',
        'not detected',
        'below detection limit',
        'below detection level',
        'bdl',
        'suppressed',
        '< 20',
        '<20',
        '< 40',
        '<40',
        '< 839',
        '<839',
        '< Titer min',
        'negative',
        'negat'
    );

    public function __construct($db = null)
    {
        $this->db = $db ?? ContainerRegistry::get('db');
    }

    public function generateHepatitisSampleCode($prefix, $provinceCode, $sampleCollectionDate, $sampleFrom = null, $provinceId = '', $maxCodeKeyVal = null, $user = null)
    {

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
        // if (isset($user) && isset($user['access_type']) && !empty($user['access_type']) && $user['access_type'] != 'testing-lab') {
        //     $remotePrefix = 'R';
        //     $sampleCodeKeyCol = 'remote_sample_code_key';
        //     $sampleCodeCol = 'remote_sample_code';
        // }

        $mnthYr = $month . $year;
        // Checking if sample code format is empty then we set by default 'MMYY'
        $sampleCodeFormat = $globalConfig['hepatitis_sample_code'] ?? 'MMYY';
        $prefixFromConfig = $globalConfig['hepatitis_sample_code_prefix'] ?? '';

        if ($sampleCodeFormat == 'MMYY') {
            $mnthYr = $month . $year;
        } else if ($sampleCodeFormat == 'YY') {
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

        //error_log($maxCodeKeyVal);

        $sCodeKey = (array('maxId' => $maxId, 'mnthYr' => $mnthYr, 'auto' => $autoFormatedString));



        if ($globalConfig['vl_form'] == 5) {
            // PNG format has an additional R in prefix
            $remotePrefix = $remotePrefix . "R";
        }
        if (isset($prefix) && $prefix != "") {
            $prefixFromConfig = $prefix;
        }
        if ($sampleCodeFormat == 'auto') {
            $sCodeKey['sampleCode'] = ($remotePrefix . $prefixFromConfig . $provinceCode . $autoFormatedString . $sCodeKey['maxId']);
            $sCodeKey['sampleCodeInText'] = ($remotePrefix . $prefixFromConfig . $provinceCode . $autoFormatedString . $sCodeKey['maxId']);
            $sCodeKey['sampleCodeFormat'] = ($remotePrefix . $prefixFromConfig . $provinceCode . $autoFormatedString);
            $sCodeKey['sampleCodeKey'] = ($sCodeKey['maxId']);
        } else if ($sampleCodeFormat == 'auto2') {
            $sCodeKey['sampleCode'] = $remotePrefix . $prefixFromConfig . $year . $provinceCode . $this->shortCode . $sCodeKey['maxId'];
            $sCodeKey['sampleCodeInText'] = $remotePrefix . $prefixFromConfig . $year . $provinceCode . $this->shortCode . $sCodeKey['maxId'];
            $sCodeKey['sampleCodeFormat'] = $remotePrefix . $prefixFromConfig . $provinceCode . $autoFormatedString;
            $sCodeKey['sampleCodeKey'] = $sCodeKey['maxId'];
        } else if ($sampleCodeFormat == 'YY' || $sampleCodeFormat == 'MMYY') {
            $sCodeKey['sampleCode'] = $remotePrefix . $prefixFromConfig . $sCodeKey['mnthYr'] . $sCodeKey['maxId'];
            $sCodeKey['sampleCodeInText'] = $remotePrefix . $prefixFromConfig . $sCodeKey['mnthYr'] . $sCodeKey['maxId'];
            $sCodeKey['sampleCodeFormat'] = $remotePrefix . $prefixFromConfig . $sCodeKey['mnthYr'];
            $sCodeKey['sampleCodeKey'] = ($sCodeKey['maxId']);
        }

        $checkQuery = "SELECT $sampleCodeCol, $sampleCodeKeyCol FROM " . $this->table . " where $sampleCodeCol='" . $sCodeKey['sampleCode'] . "'";
        $checkResult = $this->db->rawQueryOne($checkQuery);
        if ($checkResult !== null) {
            return $this->generateHepatitisSampleCode($prefix, $provinceCode, $sampleCollectionDate, $sampleFrom, $provinceId, $maxId, $user);
        }

        return json_encode($sCodeKey);
    }

    public function getComorbidityByHepatitisId($formId, $allData = false)
    {

        if (empty($formId)) {
            return null;
        }

        $response = [];
        // Using this in sync requests/results
        if (is_array($formId)) {

            $results = $this->db->rawQuery("SELECT * FROM hepatitis_patient_comorbidities WHERE `hepatitis_id` IN (" . implode(",", $formId) . ")");
            if ($allData) return $results;
            foreach ($results as $row) {
                $response[$row['hepatitis_id']][$row['comorbidity_id']] = $row['comorbidity_detected'];
            }
        } else {

            $results = $this->db->rawQuery("SELECT * FROM hepatitis_patient_comorbidities WHERE `hepatitis_id` = $formId");
            if ($allData) return $results;
            foreach ($results as $row) {
                $response[$row['comorbidity_id']] = $row['comorbidity_detected'];
            }
        }
        return $response;
    }

    public function getRiskFactorsByHepatitisId($formId, $allData = false)
    {

        if (empty($formId)) {
            return null;
        }

        $response = [];
        // Using this in sync requests/results
        if (is_array($formId)) {

            $results = $this->db->rawQuery("SELECT * FROM hepatitis_risk_factors WHERE `hepatitis_id` IN (" . implode(",", $formId) . ")");
            if ($allData) return $results;
            foreach ($results as $row) {
                $response[$row['hepatitis_id']][$row['riskfactors_id']] = $row['riskfactors_detected'];
            }
        } else {

            $results = $this->db->rawQuery("SELECT * FROM hepatitis_risk_factors WHERE `hepatitis_id` = $formId");
            if ($allData) return $results;
            foreach ($results as $row) {
                $response[$row['riskfactors_id']] = $row['riskfactors_detected'];
            }
        }
        return $response;
    }

    public function getHepatitisResults()
    {
        $results = $this->db->rawQuery("SELECT result_id,result FROM r_hepatitis_results where status='active' ORDER BY result_id DESC");
        $response = [];
        foreach ($results as $row) {
            $response[$row['result_id']] = $row['result'];
        }
        return $response;
    }

    public function getHepatitisSampleTypes()
    {
        $results = $this->db->rawQuery("SELECT * FROM r_hepatitis_sample_type where status='active'");
        $response = [];
        foreach ($results as $row) {
            $response[$row['sample_id']] = $row['sample_name'];
        }
        return $response;
    }

    public function getHepatitisReasonsForTesting()
    {
        $results = $this->db->rawQuery("SELECT test_reason_id,test_reason_name FROM r_hepatitis_test_reasons WHERE `test_reason_status` LIKE 'active'");
        $response = [];
        foreach ($results as $row) {
            $response[$row['test_reason_id']] = $row['test_reason_name'];
        }
        return $response;
    }

    public function getHepatitisComorbidities()
    {
        $comorbidityData = [];
        $comorbidityQuery = "SELECT DISTINCT comorbidity_id, comorbidity_name FROM r_hepatitis_comorbidities WHERE comorbidity_status ='active'";
        $comorbidityResult = $this->db->rawQuery($comorbidityQuery);
        foreach ($comorbidityResult as $comorbidity) {
            $comorbidityData[$comorbidity['comorbidity_id']] = ($comorbidity['comorbidity_name']);
        }

        return $comorbidityData;
    }

    public function getHepatitisRiskFactors()
    {
        $riskFactorsData = [];
        $riskFactorsQuery = "SELECT DISTINCT riskfactor_id, riskfactor_name FROM r_hepatitis_risk_factors WHERE riskfactor_status ='active'";
        $riskFactorsResult = $this->db->rawQuery($riskFactorsQuery);
        foreach ($riskFactorsResult as $riskFactors) {
            $riskFactorsData[$riskFactors['riskfactor_id']] = ($riskFactors['riskfactor_name']);
        }

        return $riskFactorsData;
    }

    public function insertSampleCode($params)
    {
        /** @var CommonService $general */
        $general = ContainerRegistry::get(CommonService::class);

        $globalConfig = $general->getGlobalConfig();
        $vlsmSystemConfig = $general->getSystemConfig();

        try {
            $provinceCode = (isset($params['provinceCode']) && !empty($params['provinceCode'])) ? $params['provinceCode'] : null;
            $provinceId = (isset($params['provinceId']) && !empty($params['provinceId'])) ? $params['provinceId'] : null;
            $sampleCollectionDate = (isset($params['sampleCollectionDate']) && !empty($params['sampleCollectionDate'])) ? $params['sampleCollectionDate'] : null;
            $prefix = (isset($params['prefix']) && !empty($params['prefix'])) ? $params['prefix'] : null;

            if (empty($sampleCollectionDate)) {
                echo 0;
                exit();
            }

            // PNG FORM CANNOT HAVE PROVINCE EMPTY
            if ($globalConfig['vl_form'] == 5 && empty($provinceId)) {
                echo 0;
                exit();
            }

            $oldSampleCodeKey = $params['oldSampleCodeKey'] ?? null;
            $sampleJson = $this->generateHepatitisSampleCode($prefix, $provinceCode, $sampleCollectionDate, null, $provinceId, $oldSampleCodeKey);
            $sampleData = json_decode($sampleJson, true);

            $sampleDate = explode(" ", $params['sampleCollectionDate']);
            $sampleCollectionDate = DateUtility::isoDateFormat($sampleDate[0]) . " " . $sampleDate[1];

            if (!isset($params['countryId']) || empty($params['countryId'])) {
                $params['countryId'] = null;
            }


            $hepatitisData = array(
                'vlsm_country_id' => $params['countryId'],
                'sample_collection_date' => $sampleCollectionDate,
                'vlsm_instance_id' => $_SESSION['instanceId'],
                'hepatitis_test_type' => $prefix,
                'province_id' => $provinceId,
                'request_created_by' => $_SESSION['userId'],
                'request_created_datetime' => DateUtility::getCurrentDateTime(),
                'last_modified_by' => $_SESSION['userId'],
                'last_modified_datetime' => DateUtility::getCurrentDateTime()
            );
            $oldSampleCodeKey = null;
            if ($vlsmSystemConfig['sc_user_type'] === 'remoteuser') {
                $hepatitisData['remote_sample_code'] = $sampleData['sampleCode'];
                $hepatitisData['remote_sample_code_format'] = $sampleData['sampleCodeFormat'];
                $hepatitisData['remote_sample_code_key'] = $sampleData['sampleCodeKey'];
                $hepatitisData['remote_sample'] = 'yes';
                $hepatitisData['result_status'] = 9;
                if ($_SESSION['accessType'] == 'testing-lab') {
                    $hepatitisData['sample_code'] = $sampleData['sampleCode'];
                    $hepatitisData['result_status'] = 6;
                }
            } else {
                $hepatitisData['sample_code'] = $sampleData['sampleCode'];
                $hepatitisData['sample_code_format'] = $sampleData['sampleCodeFormat'];
                $hepatitisData['sample_code_key'] = $sampleData['sampleCodeKey'];
                $hepatitisData['remote_sample'] = 'no';
                $hepatitisData['result_status'] = 6;
            }
            $sQuery = "SELECT hepatitis_id, sample_code, sample_code_format, sample_code_key, remote_sample_code, remote_sample_code_format, remote_sample_code_key FROM form_hepatitis ";
            if (isset($sampleData['sampleCode']) && !empty($sampleData['sampleCode'])) {
                $sQuery .= " WHERE (sample_code like '" . $sampleData['sampleCode'] . "' OR remote_sample_code like '" . $sampleData['sampleCode'] . "')";
            }
            $sQuery .= " LIMIT 1";
            $rowData = $this->db->rawQueryOne($sQuery);

            /* Update version in form attributes */
            $version = $general->getSystemConfig('sc_version');
            $ipaddress = $general->getClientIpAddress();
            $formAttributes = [
                'applicationVersion'  => $version,
                'ip_address'    => $ipaddress
            ];
            $hepatitisData['form_attributes'] = json_encode($formAttributes);

            $id = 0;
            if ($rowData) {
                // $this->db = $this->db->where('hepatitis_id', $rowData['hepatitis_id']);
                // $id = $this->db->update("form_hepatitis", $hepatitisData);

                // If this sample code exists, let us regenerate
                $params['oldSampleCodeKey'] = $sampleData['sampleCodeKey'];
                return $this->insertSampleCode($params);
            } else {
                if (isset($params['sampleCode']) && $params['sampleCode'] != '' && $params['sampleCollectionDate'] != null && $params['sampleCollectionDate'] != '') {
                    $hepatitisData['unique_id'] = $general->generateUUID();
                    $id = $this->db->insert("form_hepatitis", $hepatitisData);
                }
            }
            if ($id > 0) {
                return  $id;
            } else {
                return 0;
            }
        } catch (Exception $e) {

            error_log('Insert Hepatitis Sample : ' . $this->db->getLastErrno());
            error_log('Insert Hepatitis Sample : ' . $this->db->getLastError());
            error_log('Insert Hepatitis Sample : ' . $this->db->getLastQuery());
            error_log('Insert Hepatitis Sample : ' . $e->getMessage());
            return 0;
        }
    }
}
