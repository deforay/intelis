<?php

namespace App\Models;

use App\Utilities\DateUtils;
use MysqliDb;

/**
 * General functions
 *
 * @author Amit
 */

class Patients
{

    protected $db = null;
    protected $table = 'patients';

    public function __construct($db = null)
    {
        $this->db = !empty($db) ? $db : MysqliDb::getInstance();
    }

    public function generatePatientId($prefix)
    {
        $this->db->where("patient_code_prefix", $prefix);
        $this->db->orderBy("patient_code_key", "DESC");
        $res = $this->db->getOne($this->table, array("patient_code_key"));

        if ($res) {
            $patientCodeKey = $res['patient_code_key'] + 1;
        } else {
            $patientCodeKey = 1;
        }
        $patientCode = $prefix . str_pad($patientCodeKey, 7, "0", STR_PAD_LEFT);
        return json_encode(array(
            'patientCode' => $patientCode,
            'patientCodeKey' => $patientCodeKey
        ));
    }

    public function getPatient($patientCode)
    {
        if (empty($patientCode)) return null;
        $this->db->where("patient_code", $patientCode);
        return $this->db->getOne($this->table);
    }

    public function savePatient($params)
    {
        $general = new General();

        $data['patient_code'] = $params['patientId'];

        if (!empty($params['patientCodeKey'])) {
            $data['patient_code_key'] = $params['patientCodeKey'];
        }
        if (!empty($params['patientCodePrefix'])) {
            $data['patient_code_prefix'] = $params['patientCodePrefix'];
        }

        $data['patient_first_name'] = (!empty($params['patientFirstName']) ? $params['patientFirstName'] : null);
        $data['patient_middle_name'] = (!empty($params['patientMiddleName']) ? $params['patientMiddleName'] : null);
        $data['patient_last_name'] = (!empty($params['patientLastName']) ? $params['patientLastName'] : null);
        $data['patient_province'] = (!empty($params['patientProvince']) ? $params['patientProvince'] : null);
        $data['patient_district'] = (!empty($params['patientDistrict']) ? $params['patientDistrict'] : null);
        $data['patient_gender'] = (!empty($params['patientGender']) ? $params['patientGender'] : null);
        $data['updated_datetime'] = DateUtils::getCurrentDateTime();
        $data['patient_registered_on'] = DateUtils::getCurrentDateTime();
        $data['patient_registered_by'] = $params['registeredBy'];

        // $updateColumns = $data;
        // unset($updateColumns['patient_registered_on']);
        // unset($updateColumns['patient_registered_by']);
        // $updateColumns = array_keys($updateColumns);

        // $lastInsertId = "patient_id";
        // $this->db->onDuplicate($updateColumns, $lastInsertId);
        return $this->db->insert($this->table, $data);
    }

    public function updatePatient($params)
    {
        $general = new General();

        $data['patient_code'] = $params['patientId'];

        if (!empty($params['patientCodeKey'])) {
            $data['patient_code_key'] = $params['patientCodeKey'];
        }
        if (!empty($params['patientCodePrefix'])) {
            $data['patient_code_prefix'] = $params['patientCodePrefix'];
        }

        $data['patient_first_name'] = (!empty($params['patientFirstName']) ? $params['patientFirstName'] : null);
        $data['patient_middle_name'] = (!empty($params['patientMiddleName']) ? $params['patientMiddleName'] : null);
        $data['patient_last_name'] = (!empty($params['patientLastName']) ? $params['patientLastName'] : null);
        $data['patient_province'] = (!empty($params['patientProvince']) ? $params['patientProvince'] : null);
        $data['patient_district'] = (!empty($params['patientDistrict']) ? $params['patientDistrict'] : null);
        $data['patient_gender'] = (!empty($params['patientGender']) ? $params['patientGender'] : null);
        $data['updated_datetime'] = DateUtils::getCurrentDateTime();
        $data['patient_registered_on'] = DateUtils::getCurrentDateTime();
        $data['patient_registered_by'] = $params['registeredBy'];

        $this->db->where("patient_code", $params['patientId']);
        return $this->db->update($this->table, $data);
    }
}
