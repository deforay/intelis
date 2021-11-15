<?php
ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
#require_once('../../startup.php');


$general = new \Vlsm\Models\General();
$eidModel = new \Vlsm\Models\Eid();

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

    $rowData = false;
    if (isset($_POST['api']) && $_POST['api'] = "yes") {
        if ($_POST['sampleCode'] != "" && !empty($_POST['sampleCode'])) {
            $sQuery = "SELECT eid_id, sample_code, sample_code_format, sample_code_key, remote_sample_code, remote_sample_code_format, remote_sample_code_key FROM eid_form where sample_code like '%" . $_POST['sampleCode'] . "%' or remote_sample_code like '%" . $_POST['sampleCode'] . "%' limit 1";
            // die($sQuery);
            $rowData = $db->rawQueryOne($sQuery);
            if ($rowData) {
                $sampleData['sampleCode'] = (!empty($rowData['sample_code'])) ? $rowData['sample_code'] : $rowData['remote_sample_code'];
                $sampleData['sampleCodeFormat'] = (!empty($rowData['sample_code_format'])) ? $rowData['sample_code_format'] : $rowData['remote_sample_code_format'];
                $sampleData['sampleCodeKey'] = (!empty($rowData['sample_code_key'])) ? $rowData['sample_code_key'] : $rowData['remote_sample_code_key'];
            } else {
                $sampleJson = $eidModel->generateEIDSampleCode($provinceCode, $sampleCollectionDate, null, $provinceId);
                $sampleData = json_decode($sampleJson, true);
            }
        } else {
            $sampleJson = $eidModel->generateEIDSampleCode($provinceCode, $sampleCollectionDate, null, $provinceId);
            $sampleData = json_decode($sampleJson, true);
        }
    } else {
        $sampleJson = $eidModel->generateEIDSampleCode($provinceCode, $sampleCollectionDate, null, $provinceId);
        $sampleData = json_decode($sampleJson, true);
        $sampleDate = explode(" ", $_POST['sampleCollectionDate']);
        $_POST['sampleCollectionDate'] = $general->dateFormat($sampleDate[0]) . " " . $sampleDate[1];
    }

    if (!isset($_POST['countryId']) || $_POST['countryId'] == '')
        $_POST['countryId'] = '';

    $eidData = array();
    if (isset($_POST['api']) && $_POST['api'] = "yes") {
        $eidData = array(
            'vlsm_country_id' => $_POST['formId'],
            'sample_collection_date' => $_POST['sampleCollectionDate'],
            'vlsm_instance_id' => $_POST['instanceId'],
            'province_id' => $provinceId,
            'request_created_by' => '',
            'request_created_datetime' => $general->getDateTime(),
            'last_modified_by' => '',
            'last_modified_datetime' => $general->getDateTime()
        );
    } else {
        $eidData = array(
            'vlsm_country_id' => $_POST['countryId'],
            'sample_collection_date' => $_POST['sampleCollectionDate'],
            'province_id' => $provinceId,
            'vlsm_instance_id' => $_SESSION['instanceId'],
            'request_created_by' => $_SESSION['userId'],
            'request_created_datetime' => $general->getDateTime(),
            'last_modified_by' => $_SESSION['userId'],
            'last_modified_datetime' => $general->getDateTime()
        );
    }

    if ($vlsmSystemConfig['sc_user_type'] == 'remoteuser') {
        $eidData['remote_sample_code'] = $sampleData['sampleCode'];
        $eidData['remote_sample_code_format'] = $sampleData['sampleCodeFormat'];
        $eidData['remote_sample_code_key'] = $sampleData['sampleCodeKey'];
        $eidData['remote_sample'] = 'yes';
        $eidData['result_status'] = 9;
        if ($_SESSION['accessType'] == 'testing-lab') {
            $eidData['sample_code'] = $sampleData['sampleCode'];
            $eidData['sample_code_format'] = $sampleData['sampleCodeFormat'];
            $eidData['sample_code_key'] = $sampleData['sampleCodeKey'];
            $eidData['result_status'] = 6;
        }
    } else {
        $eidData['sample_code'] = $sampleData['sampleCode'];
        $eidData['sample_code_format'] = $sampleData['sampleCodeFormat'];
        $eidData['sample_code_key'] = $sampleData['sampleCodeKey'];
        $eidData['remote_sample'] = 'no';
        $eidData['result_status'] = 6;
    }
    // echo "<br>".$eidData['result_status'];
    $id = 0;
    if ($rowData) {
        $db = $db->where('eid_id', $rowData['eid_id']);
        $id = $db->update("eid_form", $eidData);
        $_POST['eidSampleId'] = $rowData['eid_id'];
    } else {

        if (isset($_POST['api']) && $_POST['api'] = "yes") {
            $id = $db->insert("eid_form", $eidData);
            $_POST['eidSampleId'] = $id;
        } else {
            if (isset($_POST['sampleCode']) && $_POST['sampleCode'] != '' && $_POST['sampleCollectionDate'] != null && $_POST['sampleCollectionDate'] != '') {
                $eidData['unique_id'] = $general->generateRandomString(32);
                $id = $db->insert("eid_form", $eidData);
            }
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
