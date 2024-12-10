<?php

use App\Utilities\MiscUtility;
use App\Utilities\DateUtility;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Services\SystemService;
use App\Services\TestsService;
use App\Services\PatientsService;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;


require_once(__DIR__ . "/../../bootstrap.php");

    /** @var DatabaseService $db */
    $db = ContainerRegistry::get(DatabaseService::class);

    /** @var PatientsService $patientsService */
    $patientsService = ContainerRegistry::get(PatientsService::class);

    /** @var CommonService $commonService */
    $commonService = ContainerRegistry::get(CommonService::class);

   $activeModules = SystemService::getActiveModules(onlyTests: true);


   function implodeValues($a){
    return implode("," , $a);
   }

try {

    foreach($activeModules as $module){
        $tableName = TestsService::getTestTableName($module);
        $primaryKey = TestsService::getTestPrimaryKeyColumn($input['testType']);

        $sampleResult = $db->rawQuery("SELECT * FROM $tableName WHERE system_patient_code IS NULL");

        $data = [];
        $output = [];
        foreach($sampleResult as $row){
            if ($tableName == "form_vl" || $tableName == "form_generic") {
                $data['patient_code'] =  $row['patient_art_no'] ?? null;
                $row['patient_gender'] = $row['patient_gender'] ?? null;
            } elseif ($tableName == "form_eid") {
                $data['patient_code'] =  $row['child_id'] ?? null;
                $row['patientFirstName'] = $row['child_name'] ?? null;
                $row['dob'] = $row['child_dob'] ?? null;
                $row['patient_gender'] = $row['child_gender'] ?? null;
                $row['patientPhoneNumber'] = $row['caretaker_phone_number'] ?? null;
                $row['patientAddress'] = $row['caretaker_address'] ?? null;
                $row['ageInMonths'] = $row['child_age'] ?? null;
            } else {
                $row['patientFirstName'] = $row['patient_first_name'] ?? null;
                $row['patientLastName'] = $row['patient_last_name'] ?? null;
                $row['dob'] ??= null;
                $data['patient_code'] = $row['patient_id'] ?? null;
            }

            $systemPatientCode = $patientsService->getSystemPatientId($data['patient_code'], $row['patient_gender'], DateUtility::isoDateFormat($row['dob'] ?? ''));

            if (empty($systemPatientCode) || $systemPatientCode === '') {
                $systemPatientCode = MiscUtility::generateULID();
            }

            $data['system_patient_code'] = $systemPatientCode;
            $data['patient_first_name'] = $row['patient_first_name'] ?? null;
            $data['patient_middle_name'] = $row['patient_middle_name'] ?? null;
            $data['patient_last_name'] = $row['patient_last_name'] ?? null;

            $data['is_encrypted'] = 'no';
            if (isset($row['encryptPII']) && $row['encryptPII'] == 'yes') {
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

            $data['patient_province'] = $row['patient_province'] ?? null;
            $data['patient_district'] = $row['patient_district'] ?? null;
            $data['patient_gender'] = $row['patient_gender'] ?? null;
            $data['patient_age_in_years'] = $row['patient_age_in_years'] ?? null;
            $data['patient_age_in_months'] = $row['patient_age_in_months'] ?? null;
            $data['patient_dob'] = DateUtility::isoDateFormat($row['dob'] ?? null);
            $data['patient_phone_number'] = $row['patient_phone_number'] ?? null;
            $data['is_patient_pregnant'] = $row['is_patient_pregnant'] ?? null;
            $data['is_patient_breastfeeding'] = $row['is_patient_breastfeeding'] ?? null;
            $data['patient_address'] = $row['patient_address'] ?? null;
            $data['updated_datetime'] = DateUtility::getCurrentDateTime();
            $data['patient_registered_on'] = DateUtility::getCurrentDateTime();
            $data['patient_registered_by'] = $row['request_created_by'] ?? null;
            
            $output[] =  $data;

            $db->where($primaryKey,$row[$primaryKey]);
            $db->update($tableName, array("system_patient_code" => $systemPatientCode));
    
        }

        $db->insertMulti("patients", $output);

    }

} catch (Exception $e) {
    LoggerUtility::logError($e->getFile() . ':' . $e->getLine() . ":" . $db->getLastError());
    LoggerUtility::logError($e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);
}
