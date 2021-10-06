<?php

$general = new \Vlsm\Models\General($db);
$vlModel = new \Vlsm\Models\Vl($db);


$globalConfig = $general->getGlobalConfig();
$systemConfig = $general->getSystemConfig();

$id = 0;


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
$rowData = false;
if (isset($_POST['api']) && $_POST['api'] = "yes") {
    if ($_POST['sampleCode'] != "" && !empty($_POST['sampleCode'])) {
        $sQuery = "SELECT vl_sample_id, sample_code, sample_code_format, sample_code_key, remote_sample_code, remote_sample_code_format, remote_sample_code_key FROM vl_request_form where sample_code like '%" . $_POST['sampleCode'] . "%' or remote_sample_code like '%" . $_POST['sampleCode'] . "%' limit 1";
        // die($sQuery);
        $rowData = $db->rawQueryOne($sQuery);
        if ($rowData) {
            $sampleData['sampleCode'] = (!empty($rowData['sample_code'])) ? $rowData['sample_code'] : $rowData['remote_sample_code'];
            $sampleData['sampleCodeFormat'] = (!empty($rowData['sample_code_format'])) ? $rowData['sample_code_format'] : $rowData['remote_sample_code_format'];
            $sampleData['sampleCodeKey'] = (!empty($rowData['sample_code_key'])) ? $rowData['sample_code_key'] : $rowData['remote_sample_code_key'];
        } else {
            $sampleJson = $vlModel->generateVLSampleID($provinceCode, $sampleCollectionDate, null, $provinceId);
            $sampleData = json_decode($sampleJson, true);
        }
    } else {
        $sampleJson = $vlModel->generateVLSampleID($provinceCode, $sampleCollectionDate, null, $provinceId);
        $sampleData = json_decode($sampleJson, true);
    }
} else {
    $sampleJson = $vlModel->generateVLSampleID($provinceCode, $sampleCollectionDate, null, $provinceId);
    $sampleData = json_decode($sampleJson, true);
    $sampleDate = explode(" ", $_POST['sampleCollectionDate']);
    $_POST['sampleCollectionDate'] = $general->dateFormat($sampleDate[0]) . " " . $sampleDate[1];
}

if (!isset($_POST['countryId']) || $_POST['countryId'] == '') {
    $_POST['countryId'] = null;
}
$vlData = array();

if (isset($_POST['api']) && $_POST['api'] = "yes") {
    $vlData = array(
        'vlsm_country_id' => $_POST['countryId'],
        'sample_collection_date' => $_POST['sampleCollectionDate'],
        'vlsm_instance_id' => $_POST['instanceId'],
        'province_id' => $provinceId,
        'request_created_by' => '',
        'request_created_datetime' => $general->getDateTime(),
        'last_modified_by' => '',
        'last_modified_datetime' => $general->getDateTime()
    );
} else {
    $vlData = array(
        'vlsm_country_id' => $_POST['countryId'],
        'sample_collection_date' => $_POST['sampleCollectionDate'],
        'vlsm_instance_id' => $_SESSION['instanceId'],
        'province_id' => $provinceId,
        'request_created_by' => $_SESSION['userId'],
        'request_created_datetime' => $general->getDateTime(),
        'last_modified_by' => $_SESSION['userId'],
        'last_modified_datetime' => $general->getDateTime()
    );
}

if ($systemConfig['sc_user_type'] == 'remoteuser') {
    $vlData['remote_sample_code'] = $sampleData['sampleCode'];
    $vlData['remote_sample_code_format'] = $sampleData['sampleCodeFormat'];
    $vlData['remote_sample_code_key'] = $sampleData['sampleCodeKey'];
    $vlData['remote_sample'] = 'yes';
    $vlData['result_status'] = 9;
} else {
    $vlData['sample_code'] = $sampleData['sampleCode'];
    $vlData['sample_code_format'] = $sampleData['sampleCodeFormat'];
    $vlData['sample_code_key'] = $sampleData['sampleCodeKey'];
    $vlData['remote_sample'] = 'no';
    $vlData['result_status'] = 6;
}
// echo "<pre>";
// print_r($rowData);die;
$id = 0;
if ($rowData) {
    $db = $db->where('vl_sample_id', $rowData['vl_sample_id']);
    $id = $db->update("vl_request_form", $vlData);
    $_POST['vlSampleId'] = $rowData['vl_sample_id'];
} else {
    if (isset($_POST['api']) && $_POST['api'] = "yes") {
        $id = $db->insert("vl_request_form", $vlData);
        $_POST['vlSampleId'] = $id;
    } else {
        if (isset($_POST['sampleCode']) && $_POST['sampleCode'] != '' && $_POST['sampleCollectionDate'] != null && $_POST['sampleCollectionDate'] != '') {
            $vlData['unique_id'] = $general->generateRandomString(32);
            $id = $db->insert("vl_request_form", $vlData);
        }
    }
}

if ($id > 0) {
    echo $id;
} else {
    echo 0;
}
