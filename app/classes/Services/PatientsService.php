<?php

namespace App\Services;

use Throwable;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;

final class PatientsService
{
    protected ?DatabaseService $db;
    protected string $table = 'patients';
    protected CommonService $commonService;

    public function __construct(?DatabaseService $db, CommonService $commonService)
    {
        $this->db = $db ?? ContainerRegistry::get(DatabaseService::class);
        $this->commonService = $commonService;
    }

    public function generatePatientId($prefix = null, $insertMode = false)
    {
        $prefix ??= 'P';

        $forUpdate = '';
        if ($insertMode) {
            $forUpdate = ' FOR UPDATE ';
        }

        $res = $this->db->rawQueryOne("SELECT MAX(`patient_code_key`) AS `max_key`
                                        FROM $this->table WHERE `patient_code_prefix` = '$prefix' $forUpdate");


        if ($res && $res['max_key'] !== null) {
            // Increment the maximum key by 1
            $patientCodeKey = $res['max_key'] + 1;
        } else {
            // If no existing key is found, start with 1
            $patientCodeKey = 1;
        }

        // Generate the full patient code
        $patientCode = $prefix . str_pad($patientCodeKey, 7, "0", STR_PAD_LEFT);

        return json_encode([
            'patientCode' => $patientCode,
            'patientCodeKey' => $patientCodeKey
        ]);
    }

    public function getPatientCodeBySampleId($sampleId, $testTable)
    {
        if ($testTable == "form_vl") {
            $col = "patient_art_no";
        } elseif ($testTable == "form_eid") {
            $col = "child_id";
        } else {
            $col = "patient_id";
        }
        $this->db->where("sample_code", $sampleId);
        $result = $this->db->getOne($testTable, $col);
        return $result[$col];
    }
    public function getSystemPatientCodeBySampleId($sampleId, $testTable)
    {
        $this->db->where("sample_code", $sampleId);
        $result = $this->db->getOne($testTable, 'system_patient_code');
        return $result['system_patient_code'];
    }

    public function savePatient($params, $testTable)
    {
        try {

            $data = [];

            if ($testTable == "form_vl" || $testTable == "form_generic") {
                $data['patient_code'] =  $params['artNo'] ?? null;
                $params['patientGender'] = $params['gender'] ?? null;
            } elseif ($testTable == "form_eid") {
                $data['patient_code'] =  $params['childId'] ?? null;
                $params['patientFirstName'] = $params['childName'] ?? null;
                $params['dob'] = $params['childDob'] ?? null;
                $params['patientGender'] = $params['childGender'] ?? null;
                $params['patientPhoneNumber'] = $params['caretakerPhoneNumber'] ?? null;
                $params['patientAddress'] = $params['caretakerAddress'] ?? null;
                $params['ageInMonths'] = $params['childAge'] ?? null;
            } else {
                $params['patientFirstName'] = $params['firstName'] ?? null;
                $params['patientLastName'] = $params['lastName'] ?? null;
                $params['dob'] ??= null;
                $data['patient_code'] = $params['patientId'] ?? null;
            }

            $systemPatientCode = $this->getSystemPatientId($data['patient_code'], $params['patientGender'], DateUtility::isoDateFormat($params['dob'] ?? ''));

            if (empty($systemPatientCode) || $systemPatientCode === '') {
                $systemPatientCode = MiscUtility::generateULID();
            }

            $data['system_patient_code'] = $systemPatientCode;
            $data['patient_first_name'] = $params['patientFirstName'] ?? null;
            $data['patient_middle_name'] = $params['patientMiddleName'] ?? null;
            $data['patient_last_name'] = $params['patientLastName'] ?? null;

            $data['is_encrypted'] = 'no';
            if (isset($params['encryptPII']) && $params['encryptPII'] == 'yes') {
                $key = base64_decode((string) $this->commonService->getGlobalConfig('key'));
                $encryptedPatientId = $this->commonService->crypto('encrypt', $data['patient_code'], $key);
                $encryptedPatientFirstName = $this->commonService->crypto('encrypt', $data['patient_first_name'], $key);
                $encryptedPatientMiddleName = $this->commonService->crypto('encrypt', $data['patient_middle_name'], $key);
                $encryptedPatientLastName = $this->commonService->crypto('encrypt', $data['patient_last_name'], $key);

                $data['patient_code'] = $encryptedPatientId;
                $data['patient_first_name'] = $encryptedPatientFirstName;
                $data['patient_middle_name'] = $encryptedPatientMiddleName;
                $data['patient_last_name'] = $encryptedPatientLastName;
                $data['is_encrypted'] = 'yes';
            }

            $data['patient_province'] = $params['patientProvince'] ?? null;
            $data['patient_district'] = $params['patientDistrict'] ?? null;
            $data['patient_gender'] = $params['patientGender'] ?? null;
            $data['patient_age_in_years'] = $params['ageInYears'] ?? null;
            $data['patient_age_in_months'] = $params['ageInMonths'] ?? null;
            $data['patient_dob'] = DateUtility::isoDateFormat($params['dob'] ?? null);
            $data['patient_phone_number'] = $params['patientPhoneNumber'] ?? null;
            $data['is_patient_pregnant'] = $params['patientPregnant'] ?? null;
            $data['is_patient_breastfeeding'] = $params['breastfeeding'] ?? null;
            $data['patient_address'] = $params['patientAddress'] ?? null;
            $data['updated_datetime'] = DateUtility::getCurrentDateTime();
            $data['patient_registered_on'] = DateUtility::getCurrentDateTime();
            $data['patient_registered_by'] = $params['registeredBy'] ?? null;

            $updateColumns = array_keys($data);
            unset($updateColumns['patient_registered_on']);
            unset($updateColumns['patient_registered_by']);
            unset($updateColumns['system_patient_code']);
            $id = $this->db->upsert($this->table, $data, $updateColumns, ['system_patient_code']);

            if ($id === false) {
                // Error handling
                LoggerUtility::log('error', $this->db->getLastError());
            }
            return $systemPatientCode;
        } catch (Throwable $e) {
            LoggerUtility::logError($e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'last_db_query' => $this->db->getLastQuery(),
                'last_db_error' => $this->db->getLastError(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    public function getSystemPatientId($patientCode, $patientGender, $patientDob)
    {
        try {
            // get system_patient_code for the patient using the above parameters
            $this->db->where("patient_code", $patientCode);
            $this->db->where("patient_gender", $patientGender);
            $this->db->where("patient_dob", $patientDob);
            $result = $this->db->getOne($this->table, "system_patient_code");
            return $result['system_patient_code'] ?? null;
        } catch (Throwable $e) {
            LoggerUtility::logError($e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'last_db_query' => $this->db->getLastQuery(),
                'last_db_error' => $this->db->getLastError(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    public function getLastRequestForPatientID(string $testType, string $patientId)
    {
        try {
            $tableName = TestsService::getTestTableName($testType);
            $patientIdColumn = TestsService::getPatientIdColumn($testType);
        } catch (SystemException $e) {
            return null; // Invalid test type
        }

        $sQuery = "
        SELECT
            DATE_FORMAT(DATE(request_created_datetime), '%d-%b-%Y') AS request_created_datetime,
            DATE_FORMAT(DATE(sample_collection_date), '%d-%b-%Y') AS sample_collection_date,
            (SELECT COUNT(unique_id) FROM `$tableName` WHERE `$patientIdColumn` LIKE ?) AS no_of_req_time,
            (SELECT COUNT(unique_id) FROM `$tableName` WHERE `$patientIdColumn` LIKE ? AND DATE(sample_tested_datetime) > '1970-01-01') AS no_of_tested_time
        FROM `$tableName`
        WHERE `$patientIdColumn` LIKE ?
        ORDER BY DATE(request_created_datetime) DESC
        LIMIT 1";

        return $this->db->rawQueryOne($sQuery, [$patientId, $patientId, $patientId]);
    }
}
