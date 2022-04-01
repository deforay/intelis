<?php
ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
#require_once('../startup.php');  
$general = new \Vlsm\Models\General();
$tableName = "qc_covid19";
$tableName1 = "qc_covid19_tests";
$primaryKey = "qc_id";
$primaryKey1 = "qc_test_id";

// echo "<pre>";print_r($_POST);die;
try {
    if (isset($_POST['qcCode']) && trim($_POST['qcCode']) != "") {
        $data = array(
            'unique_id'             => $general->generateRandomString(32),
            'qc_code'               => $_POST['qcCode'],
            'testkit'               => base64_decode($_POST['testKit']),
            'lot_no'                => $_POST['lotNo'],
            'expiry_date'           => $general->dateFormat($_POST['expiryDate']),
            'lab_id'                => $_POST['labName'],
            'tested_by'             => $_POST['testerName'],
            'qc_tested_datetime'    => date("Y-m-d H:s:i", strtotime($_POST['testedOn'])),
            'created_on'            => $general->getDateTime()
        );

        if (isset($_POST['qcDataId']) && $_POST['qcDataId'] != "") {
            $db = $db->where($primaryKey, base64_decode($_POST['qcDataId']));
            $lastId = $db->update($tableName, $data);
        } else {
            if (isset($_POST['qcKey']) && !empty($_POST['qcKey'])) {
                $data['qc_code_key'] = $_POST['qcKey'];
            }
            $lastId = $db->insert($tableName, $data);
        }

        if ($lastId > 0) {
            foreach ($_POST['testLabel'] as $key => $row) {
                if (isset($_POST['testResults'][$key]) && $_POST['testResults'][$key] != "") {
                    if (isset($_POST['qcTestId'][$key]) && !empty($_POST['qcTestId'][$key])) {
                        $db = $db->where($primaryKey1, $_POST['qcTestId'][$key]);
                        $db->update($tableName1, array(
                            "qc_id"         => $lastId,
                            "test_label"    => $row,
                            "test_result"   => $_POST['testResults'][$key],
                        ));
                    } else {
                        $db->insert($tableName1, array(
                            "qc_id"         => $lastId,
                            "test_label"    => $row,
                            "test_result"   => $_POST['testResults'][$key],
                        ));
                    }
                }
            }

            $_SESSION['alertMsg'] = _("Covid-19 QC test kit saved successfully");
            $general->activityLog('Covid-19 qc data', $_SESSION['userName'] . ' added new qc data for ' . $_POST['qcCode'], 'covid19-results');
        }
    }
    header("location:covid-19-qc-data.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
