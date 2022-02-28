<?php
ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
#require_once('../../startup.php');

$general = new \Vlsm\Models\General();
$covid19Model = new \Vlsm\Models\Covid19();
$patientsModel = new \Vlsm\Models\Patients();

$globalConfig = $general->getGlobalConfig();
$vlsmSystemConfig = $general->getSystemConfig();

$i;
try {
    $provinceCode = (isset($_POST['provinceCode']) && !empty($_POST['provinceCode'])) ? $_POST['provinceCode'] : null;
    $provinceId = (isset($_POST['provinceId']) && !empty($_POST['provinceId'])) ? $_POST['provinceId'] : null;
    $sampleCollectionDate = (isset($_POST['sampleCollectionDate']) && !empty($_POST['sampleCollectionDate'])) ? $_POST['sampleCollectionDate'] : null;

    if (empty($sampleCollectionDate)) {
        echo 0;
        exit();
    }

    // PNG FORM CANNOT HAVE PROVINCE EMPTY
    if ($globalConfig['vl_form'] == 5) {
        if (empty($provinceId)) {
            echo 0;
            exit();
        }
    }


    
    $sampleDate = explode(" ", $_POST['sampleCollectionDate']);

    $_POST['sampleCollectionDate'] = $general->dateFormat($sampleDate[0]) . " " . $sampleDate[1];
    if (!isset($_POST['countryId']) || $_POST['countryId'] == '') {
        $_POST['countryId'] = '';
    }

    $covid19Data = array(
        'vlsm_country_id' => $_POST['countryId'],
        'sample_collection_date' => $_POST['sampleCollectionDate'],
        'vlsm_instance_id' => $_SESSION['instanceId'],
        'province_id' => $provinceId,
        'request_created_by' => $_SESSION['userId'],
        'request_created_datetime' => $general->getDateTime(),
        'last_modified_by' => $_SESSION['userId'],
        'last_modified_datetime' => $general->getDateTime()
    );

    $sampleJson = $covid19Model->generateCovid19SampleCode($provinceCode, $sampleCollectionDate, null, $provinceId);
    $sampleData = json_decode($sampleJson, true);

    if ($vlsmSystemConfig['sc_user_type'] == 'remoteuser') {
        $covid19Data['remote_sample_code'] = $sampleData['sampleCode'];
        $covid19Data['remote_sample_code_format'] = $sampleData['sampleCodeFormat'];
        $covid19Data['remote_sample_code_key'] = $sampleData['sampleCodeKey'];
        $covid19Data['remote_sample'] = 'yes';
        $covid19Data['result_status'] = 9;
        if ($_SESSION['accessType'] == 'testing-lab') {
            $covid19Data['sample_code'] = $sampleData['sampleCode'];
            $covid19Data['sample_code_format'] = $sampleData['sampleCodeFormat'];
            $covid19Data['sample_code_key'] = $sampleData['sampleCodeKey'];
            $covid19Data['result_status'] = 6;
        }
    } else {
        $covid19Data['sample_code'] = $sampleData['sampleCode'];
        $covid19Data['sample_code_format'] = $sampleData['sampleCodeFormat'];
        $covid19Data['sample_code_key'] = $sampleData['sampleCodeKey'];
        $covid19Data['remote_sample'] = 'no';
        $covid19Data['result_status'] = 6;
    }


    $generateAutomatedPatientCode = $general->getGlobalConfig('covid19_generate_patient_code');
    if (!empty($generateAutomatedPatientCode) && $generateAutomatedPatientCode == 'yes') {
        $patientCodePrefix = $general->getGlobalConfig('covid19_patient_code_prefix');
        if (empty($patientCodePrefix)) $patientCodePrefix = 'P';
        $generateAutomatedPatientCode = true;
        $patientCodeJson = $patientsModel->generatePatientId($patientCodePrefix);
        $patientCodeArray = json_decode($patientCodeJson, true);
    } else {
        $generateAutomatedPatientCode = false;
    }
    
    //saving this patient into patients table
    if ($generateAutomatedPatientCode && !empty($patientCodeArray['patientCodeKey'])) {
        $patientData['patientCodePrefix'] = $patientCodePrefix;
        $patientData['patientCodeKey'] = $patientCodeArray['patientCodeKey'];
        $patientCode = $patientCodeArray['patientCode'];
    }else{
        $patientCode = $_POST['patientId'];
    }
    
    $patientData['patientId'] = $patientCode;
    $patientData['patientFirstName'] = $_POST['firstName'];
    $patientData['patientLastName'] = $_POST['lastName'];
    $patientData['patientGender'] = $_POST['patientGender'];
    $patientData['registeredBy'] = $_SESSION['userId'];
    $patientsModel->savePatient($patientData);


    $covid19Data['patient_id'] = $patientCode;

    // echo "<pre>";
    // print_r($covid19Data);die;
    $id = 0;
    if ($rowData) {
        $db = $db->where('covid19_id', $rowData['covid19_id']);
        $id = $db->update("form_covid19", $covid19Data);
        $_POST['covid19SampleId'] = $rowData['covid19_id'];
    } else {
        if (isset($_POST['sampleCode']) && $_POST['sampleCode'] != '' && $_POST['sampleCollectionDate'] != null && $_POST['sampleCollectionDate'] != '') {
            $covid19Data['unique_id'] = $general->generateRandomString(32);
            $id = $db->insert("form_covid19", $covid19Data);
        }
    }

    if ($id > 0) {
        echo $id;
    } else {
        echo 0;
    }
} catch (Exception $e) {
    error_log('Insert Covid-19 Sample : ' . $db->getLastError());
    error_log('Insert Covid-19 Sample : ' . $e->getMessage());
}
