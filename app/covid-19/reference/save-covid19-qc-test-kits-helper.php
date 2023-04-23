<?php

use App\Services\CommonService;
use App\Utilities\DateUtils;


if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
  
$general = new CommonService();
$tableName = "r_covid19_qc_testkits";
$primaryKey = "testkit_id";
try {
    if (isset($_POST['testKitName']) && trim($_POST['testKitName']) != "") {
        $data = array(
            'testkit_name'                  => $_POST['testKitName'],
            'status'                        => $_POST['testKitStatus'],
            'labels_and_expected_results'   => json_encode(array("label" => $_POST['qcTestLable'], 'expected' => $_POST['expectedResult'])),
            'updated_datetime'              => DateUtils::getCurrentDateTime(),
        );

        if (isset($_POST['qcTestId']) && $_POST['qcTestId'] != "") {
            $db = $db->where($primaryKey, base64_decode($_POST['qcTestId']));
            $lastId = $db->update($tableName, $data);
        } else {
            $lastId = $db->insert($tableName, $data);
        }

        if ($lastId > 0) {
            $_SESSION['alertMsg'] = _("Covid-19 QC test kit saved successfully");
            $general->activityLog('Covid-19 qc test kit', $_SESSION['userName'] . ' added new qc test kit for ' . $_POST['testKitName'], 'covid19-reference');
        }
    }
    header("Location:covid19-qc-test-kits.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
