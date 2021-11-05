<?php
ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
#require_once('../../startup.php');

$general = new \Vlsm\Models\General($db);
$tbModel = new \Vlsm\Models\Tb($db);

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


    $sampleJson = $tbModel->generateTbSampleCode($provinceCode, $sampleCollectionDate, null, $provinceId);
    $sampleData = json_decode($sampleJson, true);
    $sampleDate = explode(" ", $_POST['sampleCollectionDate']);

    $_POST['sampleCollectionDate'] = $general->dateFormat($sampleDate[0]) . " " . $sampleDate[1];
    if (!isset($_POST['countryId']) || $_POST['countryId'] == '') {
        $_POST['countryId'] = '';
    }

    $tbData = array(
        'vlsm_country_id' => $_POST['countryId'],
        'sample_collection_date' => $_POST['sampleCollectionDate'],
        'vlsm_instance_id' => $_SESSION['instanceId'],
        'province_id' => $provinceId,
        'request_created_by' => $_SESSION['userId'],
        'request_created_datetime' => $general->getDateTime(),
        'last_modified_by' => $_SESSION['userId'],
        'last_modified_datetime' => $general->getDateTime()
    );

    if ($vlsmSystemConfig['sc_user_type'] == 'remoteuser') {
        $tbData['remote_sample_code'] = $sampleData['sampleCode'];
        $tbData['remote_sample_code_format'] = $sampleData['sampleCodeFormat'];
        $tbData['remote_sample_code_key'] = $sampleData['sampleCodeKey'];
        $tbData['remote_sample'] = 'yes';
        $tbData['result_status'] = 9;
        if ($_SESSION['accessType'] == 'testing-lab') {
            $tbData['sample_code'] = $sampleData['sampleCode'];
            $tbData['sample_code_format'] = $sampleData['sampleCodeFormat'];
            $tbData['sample_code_key'] = $sampleData['sampleCodeKey'];
            $tbData['result_status'] = 6;
        }
    } else {
        $tbData['sample_code'] = $sampleData['sampleCode'];
        $tbData['sample_code_format'] = $sampleData['sampleCodeFormat'];
        $tbData['sample_code_key'] = $sampleData['sampleCodeKey'];
        $tbData['remote_sample'] = 'no';
        $tbData['result_status'] = 6;
    }
    // echo "<pre>";
    // print_r($tbData);die;
    $id = 0;
    if ($rowData) {
        $db = $db->where('tb_id', $rowData['tb_id']);
        $id = $db->update("form_tb", $tbData);
        $_POST['tbSampleId'] = $rowData['tb_id'];
    } else {


        if (isset($_POST['sampleCode']) && $_POST['sampleCode'] != '' && $_POST['sampleCollectionDate'] != null && $_POST['sampleCollectionDate'] != '') {
            $tbData['unique_id'] = $general->generateRandomString(32);
            $id = $db->insert("form_tb", $tbData);
        }
    }

    if ($id > 0) {
        echo $id;
    } else {
        echo 0;
    }
} catch (Exception $e) {
    echo 'Message: ' . $e->getMessage();
}
