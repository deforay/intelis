<?php

use App\Services\TestsService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Services\SystemService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Services\PatientsService;
use App\Registries\ContainerRegistry;


require_once __DIR__ . "/../bootstrap.php";

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var PatientsService $patientsService */
$patientsService = ContainerRegistry::get(PatientsService::class);

/** @var CommonService $commonService */
$commonService = ContainerRegistry::get(CommonService::class);

$activeModules = SystemService::getActiveModules(onlyTests: true);
$scriptName = basename(__FILE__);

// Check for force flag (-f or --force)
$forceRun = in_array('-f', $argv) || in_array('--force', $argv);
$scriptSucceeded = false;
if (!$forceRun) {
    // Check if the script has already been run
    $db->where('script_name', $scriptName);
    $executed = $db->getOne('s_run_once_scripts_log');

    if ($executed) {
        // Script has already been run
        echo "Script $scriptName has already been executed. Exiting...";
        exit(0);
    }
}



try {

    foreach ($activeModules as $module) {
        $db->beginTransaction();
        $tableName = TestsService::getTestTableName($module);
        $primaryKey = TestsService::getPrimaryColumn($module);

        $sampleResult = $db->rawQuery("SELECT * FROM $tableName WHERE system_patient_code IS NULL LIMIT 100");

        $data = [];
        $output = [];
        foreach ($sampleResult as $row) {
            if ($tableName == "form_vl" || $tableName == "form_generic") {
                $data['patient_code'] =  $row['patient_art_no'] ?? null;
                $row['patient_gender'] ??= null;
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
                $key = base64_decode((string) $commonService->getGlobalConfig('key'));
                $encryptedPatientId = $commonService->crypto('encrypt', $data['patient_code'], $key);
                $encryptedPatientFirstName = $commonService->crypto('encrypt', $data['patient_first_name'], $key);
                $encryptedPatientMiddleName = $commonService->crypto('encrypt', $data['patient_middle_name'], $key);
                $encryptedPatientLastName = $commonService->crypto('encrypt', $data['patient_last_name'], $key);

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

            $db->where($primaryKey, $row[$primaryKey]);
            $db->update($tableName, ["system_patient_code" => $systemPatientCode]);
        }

        $db->insertMulti("patients", $output);
        $db->commitTransaction();

        echo "$scriptName executed and logged successfully" . PHP_EOL;
        $scriptSucceeded = true;
    }
} catch (Exception $e) {
    $db->rollbackTransaction();
    LoggerUtility::logError($e->getFile() . ':' . $e->getLine() . ":" . $db->getLastError());
    LoggerUtility::logError($e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);
} finally {
    if ($scriptSucceeded || $forceRun) {
        $db->setQueryOption('IGNORE')->insert('s_run_once_scripts_log', [
            'script_name' => $scriptName,
            'execution_date' => DateUtility::getCurrentDateTime(),
            'status' => $scriptSucceeded ? 'executed' : 'forced'
        ]);
    }
}
