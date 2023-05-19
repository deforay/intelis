<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;
use App\Utilities\ImageResizeUtility;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}






/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$tableName = "global_config";
$instanceTableName = "s_vlsm_instance";
try {
    $configQuery = "SELECT `value` FROM global_config where name='sample_code'";
    $configResult = $db->rawQuery($configQuery);
    $configFormQuery = "SELECT `value` FROM global_config where name='vl_form'";
    $configFormResult = $db->rawQuery($configFormQuery);

    //remove instance table data
    if (isset($_POST['removedInstanceLogoImage']) && trim($_POST['removedInstanceLogoImage']) != "" && file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "instance-logo" . DIRECTORY_SEPARATOR . $_POST['removedInstanceLogoImage'])) {
        unlink(realpath(UPLOAD_PATH . DIRECTORY_SEPARATOR . "instance-logo" . DIRECTORY_SEPARATOR . $_POST['removedInstanceLogoImage']));
        $data = array('instance_facility_logo' => null);
        $db = $db->where('vlsm_instance_id', $_SESSION['instanceId']);
        $db->update($instanceTableName, $data);
    }
    $removedImage = realpath(UPLOAD_PATH . DIRECTORY_SEPARATOR . "logo" . DIRECTORY_SEPARATOR . $_POST['removedLogoImage']);
    if (isset($_POST['removedLogoImage']) && trim($_POST['removedLogoImage']) != "" && !empty($removedImage) && file_exists($removedImage)) {
        unlink($removedImage);
        $data = array('value' => null);
        $db = $db->where('name', 'logo');
        $id = $db->update($tableName, $data);
        if ($id) {
            $db = $db->where('name', 'logo');
            $db->update($tableName, array(
                "updated_on" => DateUtility::getCurrentDateTime(),
                "updated_by" => $_SESSION['userId']
            ));
        }
        $_SESSION['alertMsg'] = _("Logo deleted successfully");
    }

    if (isset($_FILES['instanceLogo']['name']) && $_FILES['instanceLogo']['name'] != "") {
        if (!file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "instance-logo") && !is_dir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "instance-logo")) {
            mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "instance-logo", 0777, true);
        }
        $extension = strtolower(pathinfo(UPLOAD_PATH . DIRECTORY_SEPARATOR . $_FILES['instanceLogo']['name'], PATHINFO_EXTENSION));
        $string = $general->generateRandomString(6) . ".";
        $imageName = "logo" . $string . $extension;
        if (move_uploaded_file($_FILES["instanceLogo"]["tmp_name"], UPLOAD_PATH . DIRECTORY_SEPARATOR . "instance-logo" . DIRECTORY_SEPARATOR . $imageName)) {

            $resizeObj = new ImageResizeUtility();
            $resizeObj = $resizeObj->setFileName(UPLOAD_PATH . DIRECTORY_SEPARATOR . "instance-logo" . DIRECTORY_SEPARATOR . $imageName);
            $resizeObj->resizeToWidth(100);
            $resizeObj->save(UPLOAD_PATH . DIRECTORY_SEPARATOR . "instance-logo" . DIRECTORY_SEPARATOR . $imageName);


            $image = array('instance_facility_logo' => $imageName);
            $db = $db->where('vlsm_instance_id', $_SESSION['instanceId']);
            $db->update($instanceTableName, $image);
        }
    }
    $instanceData = array(
        'instance_facility_name' => $_POST['fName'],
        'instance_facility_code' => $_POST['fCode'],
        'instance_facility_type' => $_POST['instance_type'],
        'instance_update_on' => DateUtility::getCurrentDateTime(),
    );
    $db = $db->where('vlsm_instance_id', $_SESSION['instanceId']);
    $updateInstance = $db->update($instanceTableName, $instanceData);
    if ($updateInstance > 0) {
        //Add event log
        $eventType = 'update-instance';
        $action = $_SESSION['userName'] . ' update instance id';
        $resource = 'instance-details';
        $general->activityLog($eventType, $action, $resource);
    }
    if (isset($_FILES['logo']['name']) && $_FILES['logo']['name'] != "") {
        if (!file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "logo") && !is_dir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "logo")) {
            mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "logo", 0777, true);
        }
        $extension = strtolower(pathinfo(UPLOAD_PATH . DIRECTORY_SEPARATOR . $_FILES['logo']['name'], PATHINFO_EXTENSION));
        $string = $general->generateRandomString(6) . ".";
        $imageName = "logo" . $string . $extension;
        if (move_uploaded_file($_FILES["logo"]["tmp_name"], UPLOAD_PATH . DIRECTORY_SEPARATOR . "logo" . DIRECTORY_SEPARATOR . $imageName)) {
            $resizeObj = new ImageResizeUtility();
            $resizeObj = $resizeObj->setFileName(UPLOAD_PATH . DIRECTORY_SEPARATOR . "logo" . DIRECTORY_SEPARATOR . $imageName);
            if ($_POST['vl_form'] == 4) {
                list($width, $height) = getimagesize(UPLOAD_PATH . DIRECTORY_SEPARATOR . "logo" . DIRECTORY_SEPARATOR . $imageName);
                if ($width > 240) {
                    $resizeObj->resizeToBestFit(240, 80);
                }
            } else {
                $resizeObj->resizeToWidth(100);
            }
            $resizeObj->save(UPLOAD_PATH . DIRECTORY_SEPARATOR . "logo" . DIRECTORY_SEPARATOR . $imageName);

            $data = array('value' => $imageName);
            $db = $db->where('name', 'logo');
            $id = $db->update($tableName, $data);
            if ($id) {
                $db = $db->where('name', 'logo');
                $db->update($tableName, array(
                    "updated_on" => DateUtility::getCurrentDateTime(),
                    "updated_by" => $_SESSION['userId']
                ));
            }
        }
    }
    if (!isset($_POST['r_mandatory_fields'])) {
        $data = array('value' => null);
        $db = $db->where('name', 'r_mandatory_fields');
        $id = $db->update($tableName, $data);
        if ($id) {
            $db = $db->where('name', 'logo');
            $db->update($tableName, array(
                "updated_on" => DateUtility::getCurrentDateTime(),
                "updated_by" => $_SESSION['userId']
            ));
        }
    }

    foreach ($_POST as $fieldName => $fieldValue) {
        if ($fieldName != 'removedLogoImage') {
            if ($fieldName == 'r_mandatory_fields') {
                $fieldValue = implode(',', $fieldValue);
            }
            unset($_SESSION['APP_LOCALE']);
            $data = array('value' => $fieldValue);
            $db = $db->where('name', $fieldName);
            $id = $db->update($tableName, $data);
            if ($id) {
                $db = $db->where('name', $fieldName);
                $db->update($tableName, array(
                    "updated_on" => DateUtility::getCurrentDateTime(),
                    "updated_by" => $_SESSION['userId']
                ));
            }
            //Generate syn sub folder
            if ($fieldName == 'sync_path' && trim($fieldValue) != '') {
                //root folder creation
                if (!file_exists($fieldValue)) {
                    mkdir($fieldValue);
                }
                //request folder creation
                if (!file_exists($fieldValue . DIRECTORY_SEPARATOR . "request")) {
                    mkdir($fieldValue . DIRECTORY_SEPARATOR . "request");
                }
                if (!file_exists($fieldValue . DIRECTORY_SEPARATOR . "request" . DIRECTORY_SEPARATOR . "new")) {
                    mkdir($fieldValue . DIRECTORY_SEPARATOR . "request" . DIRECTORY_SEPARATOR . "new");
                }
                if (!file_exists($fieldValue . DIRECTORY_SEPARATOR . "request" . DIRECTORY_SEPARATOR . "synced")) {
                    mkdir($fieldValue . DIRECTORY_SEPARATOR . "request" . DIRECTORY_SEPARATOR . "synced");
                }
                if (!file_exists($fieldValue . DIRECTORY_SEPARATOR . "request" . DIRECTORY_SEPARATOR . "error")) {
                    mkdir($fieldValue . DIRECTORY_SEPARATOR . "request" . DIRECTORY_SEPARATOR . "error");
                }
                //result folder creation
                if (!file_exists($fieldValue . DIRECTORY_SEPARATOR . "result")) {
                    mkdir($fieldValue . DIRECTORY_SEPARATOR . "result");
                }
                if (!file_exists($fieldValue . DIRECTORY_SEPARATOR . "result" . DIRECTORY_SEPARATOR . "new")) {
                    mkdir($fieldValue . DIRECTORY_SEPARATOR . "result" . DIRECTORY_SEPARATOR . "new");
                }
                if (!file_exists($fieldValue . DIRECTORY_SEPARATOR . "result" . DIRECTORY_SEPARATOR . "synced")) {
                    mkdir($fieldValue . DIRECTORY_SEPARATOR . "result" . DIRECTORY_SEPARATOR . "synced");
                }
                if (!file_exists($fieldValue . DIRECTORY_SEPARATOR . "result" . DIRECTORY_SEPARATOR . "error")) {
                    mkdir($fieldValue . DIRECTORY_SEPARATOR . "result" . DIRECTORY_SEPARATOR . "error");
                }
            }
        }
    }


    /* For Lock approve sample updates */
    if (isset($_POST['lockApprovedVlSamples']) && trim($_POST['lockApprovedVlSamples']) != "") {
        $data = array('value' => trim($_POST['lockApprovedVlSamples']));
        $db = $db->where('name', 'lock_approved_vl_samples');
        $id = $db->update($tableName, $data);
    }
    if (isset($_POST['vl_monthly_target']) && trim($_POST['vl_monthly_target']) != "") {
        $data = array('value' => trim($_POST['vl_monthly_target']));
        $db = $db->where('name', 'vl_monthly_target');
        $id = $db->update($tableName, $data);
    }
    if (isset($_POST['vl_suppression_target']) && trim($_POST['vl_suppression_target']) != "") {
        $data = array('value' => trim($_POST['vl_suppression_target']));
        $db = $db->where('name', 'vl_suppression_target');
        $id = $db->update($tableName, $data);
    }
    if (isset($_POST['lockApprovedEidSamples']) && trim($_POST['lockApprovedEidSamples']) != "") {
        $data = array('value' => trim($_POST['lockApprovedEidSamples']));
        $db = $db->where('name', 'lock_approved_eid_samples');
        $id = $db->update($tableName, $data);
    }
    if (isset($_POST['lockApprovedCovid19Samples']) && trim($_POST['lockApprovedCovid19Samples']) != "") {
        $data = array('value' => trim($_POST['lockApprovedCovid19Samples']));
        $db = $db->where('name', 'lock_approved_covid19_samples');
        $id = $db->update($tableName, $data);
    }
    if (isset($_POST['covid19ReportQrCode']) && trim($_POST['covid19ReportQrCode']) != "") {
        $data = array('value' => trim($_POST['covid19ReportQrCode']));
        $db = $db->where('name', 'covid19_report_qr_code');
        $id = $db->update($tableName, $data);
    }

    if (isset($_POST['covid19ReportType']) && trim($_POST['covid19ReportType']) != "") {
        $data = array('value' => trim($_POST['covid19ReportType']));
        $db = $db->where('name', 'covid19_report_type');
        $id = $db->update($tableName, $data);
        if ($id) {
            $db = $db->where('name', 'logo');
            $db->update($tableName, array(
                "updated_on" => DateUtility::getCurrentDateTime(),
                "updated_by" => $_SESSION['userId']
            ));
        }
    }
    if (isset($_POST['covid19PositiveConfirmatoryTestsRequiredByCentralLab']) && trim($_POST['covid19PositiveConfirmatoryTestsRequiredByCentralLab']) != "") {
        $data = array('value' => trim($_POST['covid19PositiveConfirmatoryTestsRequiredByCentralLab']));
        $db = $db->where('name', 'covid19_positive_confirmatory_tests_required_by_central_lab');
        $id = $db->update($tableName, $data);
        if ($id) {
            $db = $db->where('name', 'logo');
            $db->update($tableName, array(
                "updated_on" => DateUtility::getCurrentDateTime(),
                "updated_by" => $_SESSION['userId']
            ));
        }
    }
    if (isset($_POST['covid19TestsTableInResultsPdf']) && trim($_POST['covid19TestsTableInResultsPdf']) != "") {
        $data = array('value' => trim($_POST['covid19TestsTableInResultsPdf']));
        $db = $db->where('name', 'covid19_tests_table_in_results_pdf');
        $id = $db->update($tableName, $data);
        if ($id) {
            $db = $db->where('name', 'logo');
            $db->update($tableName, array(
                "updated_on" => DateUtility::getCurrentDateTime(),
                "updated_by" => $_SESSION['userId']
            ));
        }
    }

    $_SESSION['alertMsg'] = _("Configuration updated successfully");

    //Add event log
    $eventType = 'general-config-update';
    $action = $_SESSION['userName'] . ' updated general config';
    $resource = 'general-config';

    $general->activityLog($eventType, $action, $resource);
    header("Location:editGlobalConfig.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
