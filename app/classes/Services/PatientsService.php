<?php

namespace App\Services;

use MysqliDb;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;


class PatientsService
{

    protected ?MysqliDb $db = null;
    protected string $table = 'patients';
    protected CommonService $commonService;

    public function __construct($db = null, $commonService = null)
    {
        $this->db = $db ?? ContainerRegistry::get('db');
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
                                        FROM {$this->table} WHERE `patient_code_prefix` = '$prefix' $forUpdate");


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

    public function savePatient($params, $testTable)
    {
        try {
            $this->db->startTransaction();

            $data = [];

            if ($testTable == "form_vl" || $testTable == "form_generic") {
                $data['patient_code'] =  $params['artNo'];
            } elseif ($testTable == "form_eid") {
                $data['patient_code'] =  $params['childId'];
                $params['patientFirstName'] = $params['childName'];
                $params['dob'] = $params['childDob'];
                $params['patientGender'] = $params['childGender'];
                $params['patientPhoneNumber'] = $params['caretakerPhoneNumber'];
                $params['patientAddress'] = $params['caretakerAddress'];
                $params['ageInMonths'] = $params['childAge'];
            } else {
                $params['patientFirstName'] = $params['firstName'];
                $params['patientLastName'] = $params['lastName'];
                $data['patient_code'] = $params['patientId'];
            }

            $systemPatientCode = $this->getSystemPatientId($data['patient_code'], $params['patientGender'], DateUtility::isoDateFormat($params['dob'] ?? ''));

            if (!empty($systemPatientCode)) {
                $data['system_patient_code'] = $systemPatientCode;
            } else {
                $data['patient_code_prefix'] = $params['patientCodePrefix'] ?? 'P';
                $newCode = $this->generatePatientId($data['patient_code_prefix'], true);
                $newCode = json_decode($newCode, true);

                $data['system_patient_code'] = $newCode['patientCode'];
                $data['patient_code_key'] = $newCode['patientCodeKey'];
            }

            $data['patient_first_name'] = (!empty($params['patientFirstName']) ? $params['patientFirstName'] : null);
            $data['patient_middle_name'] = (!empty($params['patientMiddleName']) ? $params['patientMiddleName'] : null);
            $data['patient_last_name'] = (!empty($params['patientLastName']) ? $params['patientLastName'] : null);

            $data['patient_first_name'] = $params['patientFirstName'] ?? '';
            $data['patient_middle_name'] = $params['patientMiddleName'] ?? '';
            $data['patient_last_name'] = $params['patientLastName'] ?? '';

            $data['is_encrypted'] = 'no';
            if (isset($params['encryptPII']) && $params['encryptPII'] == 'yes') {
                $key = base64_decode($this->commonService->getGlobalConfig('key'));
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
            $data['patient_registered_by'] = $params['registeredBy'];

            $updateColumns = array_keys($data);
            $this->db->onDuplicate($updateColumns, 'patient_id');

            // Insert the data
            $id = $this->db->insert($this->table, $data);

            if ($id === false) {
                // Error handling
                error_log($this->db->getLastError());
            }

            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function updatePatient($params, $testTable)
    {
        $oldPatientCode = $this->getPatientCodeBySampleId($params['sampleCode'], $testTable);

        if ($testTable == "form_vl" || $testTable == "form_generic") {
            $patientId = $params['artNo'];
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
            $key = base64_decode($this->commonService->getGlobalConfig('key'));
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
        $data['patient_registered_by'] = $params['registeredBy'];

        $this->db->where("patient_code", $oldPatientCode);
        return $this->db->update($this->table, $data);
    }

    public function getSystemPatientId($patientCode, $patientGender, $patientDob)
    {
        // get system_patient_code for the patient using the above parameters
        $this->db->where("patient_code", $patientCode);
        $this->db->where("patient_gender", $patientGender);
        $this->db->where("patient_dob", $patientDob);
        $result = $this->db->getOne($this->table, "system_patient_code");
        return $result['system_patient_code'];
    }
}
