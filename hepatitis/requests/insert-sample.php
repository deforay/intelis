<?php
ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

#require_once('../../startup.php');


// echo "<pre>";print_r($_POST);
$general = new \Vlsm\Models\General($db);
$hepatitisModel = new \Vlsm\Models\Hepatitis($db);

$globalConfig = $general->getGlobalConfig();
$systemConfig = $general->getSystemConfig();

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
    $sampleJson = $hepatitisModel->generateHepatitisSampleCode($provinceCode, $sampleCollectionDate, null, $provinceId);
    $sampleData = json_decode($sampleJson, true);

    $sampleDate = explode(" ", $_POST['sampleCollectionDate']);
    $_POST['sampleCollectionDate'] = $general->dateFormat($sampleDate[0]) . " " . $sampleDate[1];

    $hepatitisData = array();
    $hepatitisData = array(
        'vlsm_country_id' => $_POST['countryId'],
        'sample_collection_date' => $_POST['sampleCollectionDate'],
        'vlsm_instance_id' => $_SESSION['instanceId'],
        'province_id' => $provinceId,
        'request_created_by' => $_SESSION['userId'],
        'request_created_datetime' => $general->getDateTime(),
        'last_modified_by' => $_SESSION['userId'],
        'last_modified_datetime' => $general->getDateTime()
    );

    if ($systemConfig['user_type'] == 'remoteuser') {
        $hepatitisData['remote_sample_code'] = $sampleData['sampleCode'];
        $hepatitisData['remote_sample_code_format'] = $sampleData['sampleCodeFormat'];
        $hepatitisData['remote_sample_code_key'] = $sampleData['sampleCodeKey'];
        $hepatitisData['remote_sample'] = 'yes';
        $hepatitisData['result_status'] = 9;
    } else {
        $hepatitisData['sample_code'] = $sampleData['sampleCode'];
        $hepatitisData['sample_code_format'] = $sampleData['sampleCodeFormat'];
        $hepatitisData['sample_code_key'] = $sampleData['sampleCodeKey'];
        $hepatitisData['remote_sample'] = 'no';
        $hepatitisData['result_status'] = 6;
    }
    $id = 0;
    if (isset($_POST['sampleCode']) && $_POST['sampleCode'] != '' && $_POST['sampleCollectionDate'] != null && $_POST['sampleCollectionDate'] != '') {
        $id = $db->insert("form_hepatitis", $hepatitisData);
    }
    if ($id > 0) {
        echo $id;
    } else {
        echo 0;
    }
} catch (Exception $e) {
    echo 'Message: ' . $e->getMessage();
}
