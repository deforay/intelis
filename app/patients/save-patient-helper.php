<?php

use Laminas\Diactoros\ServerRequest;
use App\Registries\AppRegistry;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\VlService;
use App\Services\PatientsService;
use App\Utilities\DateUtility;
use App\Utilities\ValidationUtility;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var PatientsService $patientsService */
$patientsService = ContainerRegistry::get(PatientsService::class);

$tableName = "patients";

// Sanitized values from $request object
/** @var ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

try {

    if (isset($_POST['gender']) && trim((string) $_POST['gender']) === 'male') {
        $_POST['patientPregnant'] = "N/A";
        $_POST['breastfeeding'] = "N/A";
    }

    $patientData = ['patient_province' => $_POST['province'] ?? null, 'patient_district' => $_POST['district'] ?? null, 'patient_code_prefix' => $_POST['patientCodePrefix'] ?? null, 'patient_code_key' => $_POST['patientCodeKey'] ?? null, 'patient_gender' => $_POST['gender'] ?? null, 'patient_dob' => DateUtility::isoDateFormat($_POST['dob'] ?? ''), 'patient_first_name' => $_POST['patientFirstName'] ?? null, 'patient_middle_name' => $_POST['patientMiddleName'] ?? null, 'patient_last_name' => $_POST['patientLastName'] ?? null, 'patient_age_in_years' => $_POST['ageInYears'] ?? null, 'patient_age_in_months' => $_POST['ageInMonths'] ?? null, 'is_patient_pregnant' => $_POST['patientPregnant'] ?? null, 'is_patient_breastfeeding' => $_POST['breastfeeding'] ?? null, 'patient_code' => $_POST['patientCode'] ?? null, 'patient_phone_number' => $_POST['patientPhoneNumber'] ?? null, 'patient_address' => $_POST['patientAddress'] ?? null, 'patient_registered_on' => DateUtility::getCurrentDateTime(), 'status' => $_POST['patientStatus']];

    $patientData['is_encrypted'] = 'no';
    if (isset($_POST['encryptPII']) && $_POST['encryptPII'] == 'yes') {
        $key = (string) $general->getGlobalConfig('key');
        $encryptedPatientId = $general->crypto('encrypt', $patientData['patient_code'], $key);
        $encryptedPatientFirstName = $general->crypto('encrypt', $patientData['patient_first_name'], $key);
        $encryptedPatientMiddleName = $general->crypto('encrypt', $patientData['patient_middle_name'], $key);
        $encryptedPatientLastName = $general->crypto('encrypt', $patientData['patient_last_name'], $key);

        $patientData['patient_code'] = $encryptedPatientId;
        $patientData['patient_first_name'] = $encryptedPatientFirstName;
        $patientData['patient_middle_name'] = $encryptedPatientMiddleName;
        $patientData['patient_last_name'] = $encryptedPatientLastName;
        $patientData['is_encrypted'] = 'yes';
    }

    //    echo '<pre>'; print_r($patientData); die;

    $id = 0;

    if (isset($_POST['patientId'])) {
        $db->where('patient_id', $_POST['patientId']);
        $id = $db->update($tableName, $patientData);
    } else {
        $id = $db->insert($tableName, $patientData);
    }

    //error_log(__FILE__ . ":" . __LINE__ . ":" . $db->getLastError());

    if ($id > 0) {
        $_SESSION['alertMsg'] = _translate("Patient information saved successfully");
        header("Location:/patients/view-patients.php");
    } else {
        $_SESSION['alertMsg'] = _translate("Please try again later");
        header("Location:/patients/view-patients.php");
    }
} catch (Exception $exc) {
    error_log($exc->getMessage());
}
