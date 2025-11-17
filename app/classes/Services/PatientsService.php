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

    public function __construct(?DatabaseService $db, protected CommonService $commonService)
    {
        $this->db = $db ?? ContainerRegistry::get(DatabaseService::class);
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


        $patientCodeKey = $res && $res['max_key'] !== null ? $res['max_key'] + 1 : 1;

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
                $data['patient_code'] =  $params['patient_art_no'] ?? null;
                $params['patient_gender'] ??= null;
            } elseif ($testTable == "form_eid") {
                $data['patient_code'] =  $params['child_id'] ?? null;
                $params['patientFirstName'] = $params['child_name'] ?? null;
                $params['dob'] = $params['child_dob'] ?? null;
                $params['patient_gender'] = $params['child_gender'] ?? null;
                $params['patientPhoneNumber'] = $params['caretaker_phone_number'] ?? null;
                $params['patientAddress'] = $params['caretaker_address'] ?? null;
                $params['ageInMonths'] = $params['child_age'] ?? null;
            } else {
                $params['patientFirstName'] = $params['patient_first_name'] ?? null;
                $params['patientLastName'] = $params['patient_last_name'] ?? null;
                $params['dob'] ??= null;
                $data['patient_code'] = $params['patient_id'] ?? null;
            }

            $systemPatientCode = $this->getSystemPatientId($data['patient_code'], $params['patient_gender'], DateUtility::isoDateFormat($params['dob'] ?? ''));

            if (empty($systemPatientCode) || $systemPatientCode === '') {
                $systemPatientCode = MiscUtility::generateULID();
            }

            $data['system_patient_code'] = $systemPatientCode;
            $data['patient_first_name'] = $params['patient_first_name'] ?? null;
            $data['patient_middle_name'] = $params['patient_middle_name'] ?? null;
            $data['patient_last_name'] = $params['patient_last_name'] ?? null;

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

            $data['patient_province'] = $params['patient_province'] ?? null;
            $data['patient_district'] = $params['patient_district'] ?? null;
            $data['patient_gender'] = $params['patient_gender'] ?? null;
            $data['patient_age_in_years'] = $params['patient_age_in_years'] ?? null;
            $data['patient_age_in_months'] = $params['patient_age_in_months'] ?? null;
            $data['patient_dob'] = DateUtility::isoDateFormat($params['dob'] ?? null);
            $data['patient_phone_number'] = $params['patient_phone_number'] ?? null;
            $data['is_patient_pregnant'] = $params['is_patient_pregnant'] ?? null;
            $data['is_patient_breastfeeding'] = $params['is_patient_breastfeeding'] ?? null;
            $data['patient_address'] = $params['patient_address'] ?? null;
            $data['updated_datetime'] = DateUtility::getCurrentDateTime();
            $data['patient_registered_on'] = DateUtility::getCurrentDateTime();
            $data['patient_registered_by'] = $params['request_created_by'] ?? null;

            $updateColumns = array_keys($data);
            unset($updateColumns['patient_registered_on']);
            unset($updateColumns['patient_registered_by']);
            unset($updateColumns['system_patient_code']);
            $id = $this->db->upsert($this->table, $data, $updateColumns, ['system_patient_code']);

            if ($id === false) {
                // Error handling
                LoggerUtility::logError($this->db->getLastError());
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
        return null;
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
        return null;
    }

    public function getLastRequestForPatientID(string $testType, string $patientId)
    {
        try {
            $tableName = TestsService::getTestTableName($testType);
            $patientIdColumn = TestsService::getPatientIdColumn($testType);
        } catch (SystemException) {
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
