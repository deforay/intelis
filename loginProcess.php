<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
ob_start();
#require_once('../startup.php');  



$general = new \Vlsm\Models\General($db);

$user = new \Vlsm\Models\Users($db);


$systemInfo = $general->getSystemConfig();
$systemType = $systemLabId = null;
if ($systemInfo != false) {
    $systemType = $systemInfo['sc_user_type'];
    $systemLabId = $systemInfo['sc_testing_lab_id'];
}

if ($_POST["csrf_token"] != $_SESSION["csrf_token"]) {
    // Reset token
    unset($_SESSION["csrf_token"]);
    $_SESSION['alertMsg'] = "Validation token has expired. Please try to login again.";
    header("location:/login.php");
}
//$dashboardUrl = $general->getGlobalConfig('vldashboard_url');
/* Crosss Login Block Start */
$_SESSION['logged'] = false;
if (isset($_GET['u']) && isset($_GET['t']) && $recencyConfig['crosslogin']) {

    $_GET['u'] = filter_var($_GET['u'], FILTER_SANITIZE_STRING);
    $_GET['t'] = filter_var($_GET['t'], FILTER_SANITIZE_STRING);

    $_POST['username'] = base64_decode($_GET['u']);
    $crossLoginQuery = "SELECT `login_id`,`password`,`user_name` FROM user_details WHERE `login_id` = ?";
    $check = $db->rawQueryOne($crossLoginQuery, array($db->escape($_POST['username'])));
    if ($check) {
        $passwordCrossLoginSalt = $check['password'] . $recencyConfig['crossloginSalt'];
        $_POST['password'] = hash('sha256', $passwordCrossLoginSalt);
        if ($_POST['password'] == $_GET['t']) {
            $password = $check['password'];
            $_SESSION['logged'] = true;
        } else {
            $password = "";
            $_POST['password'] = "";
        }
    } else {
        $_POST['password'] = "";
    }
} else {
    if (!$recencyConfig['crosslogin'] && !isset($_POST['username']) && !empty($_POST['username'])) {
        $_SESSION['alertMsg'] = "Sorry! Recency cross-login has not been activated. Please contact system administrator.";
    }
}
/* Crosss Login Block End */
try {
    if (isset($_POST['username']) && !empty($_POST['username']) && isset($_POST['password']) && !empty($_POST['password'])) {

        $username = $db->escape($_POST['username']);
        $password = $db->escape($_POST['password']);

        /* Crosss Login Block Start */
        if (empty($_GET) || empty($_GET['u']) || empty($_GET['t'])) {
            $password = sha1($password . $systemConfig['passwordSalt']);
        }
        /* Crosss Login Block End */

        $queryParams = array($username, $password, 'active');
        $admin = $db->rawQuery("SELECT * FROM user_details as ud INNER JOIN roles as r ON ud.role_id=r.role_id WHERE ud.login_id = ? AND ud.password = ? AND ud.status = ?", $queryParams);

        if (count($admin) > 0) {
            //add random key
            $instanceResult = $db->rawQueryOne("SELECT vlsm_instance_id, instance_facility_name FROM s_vlsm_instance");

            if ($instanceResult) {
                $_SESSION['instanceId'] = $instanceResult['vlsm_instance_id'];
                $_SESSION['instanceFacilityName'] = $instanceResult['instance_facility_name'];
            } else {
                $id = $general->generateRandomString(32);
                // deleting just in case there is a row already inserted
                $db->delete('s_vlsm_instance');
                $db->insert('s_vlsm_instance', array('vlsm_instance_id' => $id));
                $_SESSION['instanceId'] = $id;
                $_SESSION['instanceFacilityName'] = null;

                //Update instance ID in facility and vl_request_form tbl
                $data = array('vlsm_instance_id' => $id);
                $db->update('facility_details', $data);
            }
            //Add event log
            $eventType = 'login';
            $action = ucwords($admin[0]['user_name']) . ' logged in';
            $resource = 'user-login';

            $general->activityLog($eventType, $action, $resource);

            // Add user_login_history
            $user->userHistoryLog($admin[0]['login_id']);

            $_SESSION['userId'] = $admin[0]['user_id'];
            $_SESSION['userName'] = ucwords($admin[0]['user_name']);
            $_SESSION['roleCode'] = $admin[0]['role_code'];
            $_SESSION['roleId'] = $admin[0]['role_id'];
            $_SESSION['accessType'] = $admin[0]['access_type'];
            $_SESSION['email'] = $admin[0]['email'];

            $redirect = '/error/401.php';
            //set role and privileges
            $priQuery = "SELECT p.privilege_name, rp.privilege_id, r.module FROM roles_privileges_map as rp INNER JOIN privileges as p ON p.privilege_id=rp.privilege_id INNER JOIN resources as r ON r.resource_id=p.resource_id  where rp.role_id='" . $admin[0]['role_id'] . "'";
            $priInfo = $db->query($priQuery);
            $priId = array();
            if ($priInfo) {
                foreach ($priInfo as $id) {
                    $priId[] = $id['privilege_name'];
                    $module[$id['module']] = $id['module'];
                }

                if ($admin[0]['landing_page'] != '') {
                    $redirect = $admin[0]['landing_page'];
                } else {
                    $fileNameList = array('index.php', 'addVlRequest.php', 'vlRequest.php', 'batchcode.php', 'vlRequestMail.php', 'addImportResult.php', 'vlPrintResult.php', 'vlTestResult.php', 'vl-sample-status.php', 'vlResult.php', 'highViralLoad.php', 'roles.php', 'users.php', 'facilities.php', 'globalConfig.php', 'importConfig.php');
                    $fileName = array('dashboard/index.php', '/vl/requests/addVlRequest.php', '/vl/requests/vlRequest.php', '/vl/batch/batchcode.php', 'mail/vlRequestMail.php', 'import-result/addImportResult.php', '/vl/results/vlPrintResult.php', '/vl/results/vlTestResult.php', 'program-management/vl-sample-status.php', 'program-management/vlResult.php', 'program-management/highViralLoad.php', 'roles/roles.php', 'users/users.php', 'facilities/facilities.php', 'global-config/globalConfig.php', 'import-configs/importConfig.php');
                    foreach ($fileNameList as $redirectFile) {
                        if (in_array($redirectFile, $priId)) {
                            $arrIndex = array_search($redirectFile, $fileNameList);
                            $redirect = $fileName[$arrIndex];
                            break;
                        }
                    }
                }
            }
            //check clinic or lab user
            $_SESSION['userType']   = '';
            $_SESSION['privileges'] = $priId;
            $_SESSION['module']     = $module;


            if ($systemType != null && ($systemType == 'vluser' || $systemType == 'remoteuser') && $systemLabId != '' && $systemLabId != null) {
                $_SESSION['system'] = $systemType;
            } else {
                $_SESSION['system'] = null;
            }
            // if ($dashboardUrl != null && $dashboardUrl != '') {
            //     $_SESSION['vldashboard_url'] = $dashboardUrl;
            // } else {
            //     $_SESSION['vldashboard_url'] = null;
            // }


            header("location:" . $redirect);
        } else {
            $_SESSION['user_name'] = $username;
            $_SESSION['password'] = $password;

            // Add user_login_history
            if ($_SESSION['user_name'] == $username && $_SESSION['password'] == $password) {
                $_SESSION['attembt'] += 1;
                if ($_SESSION['attembt'] < 3) {
                    $user->userHistoryLog($admin[0]['login_id']);
                    header("location:/login.php");
                    $_SESSION['alertMsg'] = "Please check your login credentials";
                } else {
                    header("location:/login.php");
                    $_SESSION['alertMsg'] = "You are exhausted maximum attempts. Please try to login after sometime";
                }
            }
        }
    } else {
        header("location:/login.php");
    }
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
