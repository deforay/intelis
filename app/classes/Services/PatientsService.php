<?php

namespace App\Services;

use Exception;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
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
        $prefix = $prefix ?? 'P';

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
        $col = "system_patient_code";
        $this->db->where("sample_code", $sampleId);
        $result = $this->db->getOne($testTable, $col);
        return $result[$col];
    }


    public function savePatient($params, $testTable)
    {
        try {
            $this->db->beginTransaction();

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
                $params['dob'] = $params['dob'] ?? null;
                $data['patient_code'] = $params['patientId'] ?? null;
            }

            $systemPatientCode = $this->getSystemPatientId($data['patient_code'], $params['patientGender'], DateUtility::isoDateFormat($params['dob'] ?? ''));

            if (!empty($systemPatientCode)) {
                $data['system_patient_code'] = $systemPatientCode;
            } else {
                $data['system_patient_code'] = $this->commonService->generateUUID();
            }

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

            $data['patient_province'] = (!empty($params['patientProvince']) ? $params['patientProvince'] : null);
            $data['patient_district'] = (!empty($params['patientDistrict']) ? $params['patientDistrict'] : null);
            $data['patient_gender'] = (!empty($params['patientGender']) ? $params['patientGender'] : null);
            $data['patient_age_in_years'] = (!empty($params['ageInYears']) ? $params['ageInYears'] : null);
            $data['patient_age_in_months'] = (!empty($params['ageInMonths']) ? $params['ageInMonths'] : null);
            $data['patient_dob'] = DateUtility::isoDateFormat($params['dob'] ?? '');
            $data['patient_phone_number'] = (!empty($params['patientPhoneNumber']) ? $params['patientPhoneNumber'] : null);
            $data['is_patient_pregnant'] = (!empty($params['patientPregnant']) ? $params['patientPregnant'] : null);
            $data['is_patient_breastfeeding'] = (!empty($params['breastfeeding']) ? $params['breastfeeding'] : null);
            $data['patient_address'] = (!empty($params['patientAddress']) ? $params['patientAddress'] : null);
            $data['updated_datetime'] = DateUtility::getCurrentDateTime();
            $data['patient_registered_on'] = DateUtility::getCurrentDateTime();
            $data['patient_registered_by'] = $params['registeredBy'] ?? null;

            $updateColumns = array_keys($data);
            $id = $this->db->upsert($this->table, $data, $updateColumns, ['system_patient_code']);

            if ($id === false) {
                // Error handling
                LoggerUtility::log('error', $this->db->getLastError());
            }

            $this->db->commitTransaction();
        } catch (Exception $e) {
            $this->db->rollbackTransaction();
            throw $e;
        }
    }

    public function updatePatient($params, $testTable)
    {
        $systemPatientCode = $this->getSystemPatientCodeBySampleId($params['sampleCode'], $testTable);
        if ($testTable == "form_vl" || $testTable == "form_generic") {
            $patientId = $params['artNo'];
            $params['patientGender'] = $params['gender'];
        } elseif ($testTable == "form_eid") {
            $patientId = $params['childId'];
            $params['patientFirstName'] = $params['childName'];
            $params['dob'] = $params['childDob'];
            $params['patientGender'] = $params['childGender'];
            $params['patientPhoneNumber'] = $params['caretakerPhoneNumber'];
            $params['patientAddress'] = $params['caretakerAddress'];
            $params['ageInMonths'] = $params['childAge'];
        } else {
            $params['patientFirstName'] = $params['firstName'];
            $params['patientLastName'] = $params['lastName'];
            $patientId = $params['patientId'];
        }
        $data['patient_code'] = $patientId;


        $data['patient_first_name'] = (!empty($params['patientFirstName']) ? $params['patientFirstName'] : null);
        $data['patient_middle_name'] = (!empty($params['patientMiddleName']) ? $params['patientMiddleName'] : null);
        $data['patient_last_name'] = (!empty($params['patientLastName']) ? $params['patientLastName'] : null);

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

        $data['patient_province'] = (!empty($params['patientProvince']) ? $params['patientProvince'] : null);
        $data['patient_district'] = (!empty($params['patientDistrict']) ? $params['patientDistrict'] : null);
        $data['patient_gender'] = (!empty($params['patientGender']) ? $params['patientGender'] : null);
        $data['patient_age_in_years'] = (!empty($params['ageInYears']) ? $params['ageInYears'] : null);
        $data['patient_age_in_months'] = (!empty($params['ageInMonths']) ? $params['ageInMonths'] : null);
        $data['patient_dob'] = DateUtility::isoDateFormat($params['dob'] ?? '');
        $data['patient_phone_number'] = (!empty($params['patientPhoneNumber']) ? $params['patientPhoneNumber'] : null);
        $data['is_patient_pregnant'] = (!empty($params['patientPregnant']) ? $params['patientPregnant'] : null);
        $data['is_patient_breastfeeding'] = (!empty($params['breastfeeding']) ? $params['breastfeeding'] : null);
        $data['patient_address'] = (!empty($params['patientAddress']) ? $params['patientAddress'] : null);
        $data['updated_datetime'] = DateUtility::getCurrentDateTime();
        $data['patient_registered_on'] = DateUtility::getCurrentDateTime();
        $data['patient_registered_by'] = $params['registeredBy'] ?? null;

        $this->db->where("system_patient_code", $systemPatientCode);
        return $this->db->update($this->table, $data);
    }

    public function getSystemPatientId($patientCode, $patientGender, $patientDob)
    {
        // get system_patient_code for the patient using the above parameters
        $this->db->where("patient_code", $patientCode);
        $this->db->where("patient_gender", $patientGender);
        $this->db->where("patient_dob", $patientDob);
        $result = $this->db->getOne($this->table, "system_patient_code");
        return $result['system_patient_code'] ?? null;
    }

    public function getLastRequestForPatientID(string $testType, string $patientId)
    {

        $sQuery = "";
        if ($testType == 'vl') {
            $sQuery = "SELECT DATE_FORMAT(DATE(request_created_datetime),'%d-%b-%Y') as request_created_datetime,
                        DATE_FORMAT(DATE(sample_collection_date),'%d-%b-%Y') as sample_collection_date,
                        (SELECT COUNT(unique_id) FROM `form_vl` WHERE `patient_art_no` like ?) as no_of_req_time,
                        (SELECT COUNT(unique_id) FROM `form_vl` WHERE `patient_art_no` like ? AND DATE(sample_tested_datetime) > '1970-01-01') as no_of_tested_time
                        FROM form_vl
                        WHERE `patient_art_no`=? ORDER by DATE(request_created_datetime) DESC limit 1";
            return $this->db->rawQueryOne($sQuery, [$patientId, $patientId, $patientId]);
        } elseif ($testType == 'eid') {
            $sQuery = "SELECT DATE_FORMAT(DATE(request_created_datetime),'%d-%b-%Y') as request_created_datetime,
                        DATE_FORMAT(DATE(sample_collection_date),'%d-%b-%Y') as sample_collection_date,
                        (SELECT COUNT(unique_id) FROM `form_eid` WHERE `child_id` like ?) as no_of_req_time,
                        (SELECT COUNT(unique_id) FROM `form_eid` WHERE `child_id` like ? AND DATE(sample_tested_datetime) > '1970-01-01') as no_of_tested_time
                        FROM form_eid
                        WHERE `child_id` like ? ORDER by DATE(request_created_datetime) DESC limit 1";
            return $this->db->rawQueryOne($sQuery, [$patientId, $patientId, $patientId]);
        } elseif ($testType == 'covid19') {
            $sQuery = "SELECT DATE_FORMAT(DATE(request_created_datetime),'%d-%b-%Y') as request_created_datetime,
                        DATE_FORMAT(DATE(sample_collection_date),'%d-%b-%Y') as sample_collection_date,
                        (SELECT COUNT(unique_id) FROM `form_covid19` WHERE `patient_id` like ?) as no_of_req_time,
                        (SELECT COUNT(unique_id) FROM `form_covid19` WHERE `patient_id` like ? AND DATE(sample_tested_datetime) > '1970-01-01') as no_of_tested_time
                        FROM form_covid19
                        WHERE `patient_id` like ?
                        ORDER by DATE(request_created_datetime) DESC limit 1";
            return $this->db->rawQueryOne($sQuery, [$patientId, $patientId, $patientId]);
        } elseif ($testType == 'hepatitis') {
            $sQuery = "SELECT DATE_FORMAT(DATE(request_created_datetime),'%d-%b-%Y') as request_created_datetime,
                        DATE_FORMAT(DATE(sample_collection_date),'%d-%b-%Y') as sample_collection_date,
                        (SELECT COUNT(unique_id) FROM `form_hepatitis` WHERE `patient_id` like ?) as no_of_req_time,
                        (SELECT COUNT(unique_id) FROM `form_hepatitis` WHERE `patient_id` like ? AND DATE(sample_tested_datetime) > '1970-01-01') as no_of_tested_time
                        FROM form_hepatitis
                        WHERE `patient_id` like ?
                        ORDER by DATE(request_created_datetime) DESC limit 1";
            return $this->db->rawQueryOne($sQuery, [$patientId, $patientId, $patientId]);
        } else {
            return null;
        }
    }
}
