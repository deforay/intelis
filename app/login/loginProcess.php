<?php

use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\UsersService;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


$tableName = "user_details";
$userName = ($_POST['username']);
$password = ($_POST['password']);


/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);
$user = ContainerRegistry::get(UsersService::class);



$_SESSION['logged'] = false;
$systemInfo = $general->getSystemConfig();

$_SESSION['instanceType'] = $systemInfo['sc_user_type'];
$_SESSION['instanceLabId'] = !empty($systemInfo['sc_testing_lab_id']) ? $systemInfo['sc_testing_lab_id'] : null;


try {
    if (isset($_GET['u']) && isset($_GET['t']) && SYSTEM_CONFIG['recency']['crosslogin']) {
        $_POST['username'] = base64_decode($_GET['u']);

        $decryptedPassword = CommonService::decrypt($_GET['t'], base64_decode(SYSTEM_CONFIG['recency']['crossloginSalt']));
        $_POST['password'] = $decryptedPassword;
    }
    //  else {
    //     if (!SYSTEM_CONFIG['recency']['crosslogin'] && !isset($_POST['username']) && !empty($_POST['username'])) {
    //         throw new Exception(_("Please check your login credentials"));
    //     }
    // }


    if (isset($_POST['csrf_token']) && $_POST['csrf_token'] != $_SESSION['csrf_token']) {
        // clear/reset token
        $_SESSION['csrf_token'] = null;
        unset($_SESSION['csrf_token']);
        //unset($_SESSION);
        throw new Exception(_("Request expired. Please try to login again."));
    }

    /* Crosss Login Block End */

    $adminCount = $db->getValue("user_details", "count(*)");
    if ($adminCount != 0) {
        if (isset($_POST['username']) && !empty($_POST['username']) && isset($_POST['password']) && !empty($_POST['password'])) {

            $userName = ($_POST['username']);
            $password = ($_POST['password']);

            $ipaddress = '';
            if (isset($_SERVER['HTTP_CLIENT_IP'])) {
                $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
            } else if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
            } else if (isset($_SERVER['HTTP_X_FORWARDED'])) {
                $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
            } else if (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
                $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
            } else if (isset($_SERVER['HTTP_FORWARDED'])) {
                $ipaddress = $_SERVER['HTTP_FORWARDED'];
            } else $ipaddress = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
            $queryParams = array($userName, 'active');
            $userRow = $db->rawQueryOne(
                "SELECT * FROM user_details as ud 
                                        INNER JOIN roles as r ON ud.role_id=r.role_id 
                                        WHERE ud.login_id = ? AND ud.status = ?",
                $queryParams
            );
            $loginAttemptCount = $db->rawQueryOne(
                "SELECT SUM(CASE WHEN login_id = ? THEN 1 ELSE 0 END) AS LoginIdCount,
                    SUM(CASE WHEN ip_address = ? THEN 1 ELSE 0 END) AS IpCount
                    FROM user_login_history
                    WHERE login_status='failed' 
                    AND login_attempted_datetime >= DATE_SUB(NOW(), INTERVAL 15 minute)",
                array($userName, $ipaddress)
            );
            if ($loginAttemptCount['LoginIdCount'] >= 3 || $loginAttemptCount['IpCount'] >= 3) {
                if (!isset($_POST['captcha']) || empty($_POST['captcha']) || $_POST['captcha'] != $_SESSION['captchaCode']) {
                    $user->userHistoryLog($userName, 'failed');
                    $_SESSION['alertMsg'] = _("You have exhausted maximum number of login attempts. Please retry login after sometime.");
                    header("Location:/login/login.php");
                }
            }

            if ($userRow['hash_algorithm'] == 'sha1') {
                $password = sha1($password . SYSTEM_CONFIG['passwordSalt']);
                if ($password == $userRow['password']) {
                    $newPassword = $user->passwordHash($_POST['password']);
                    $db->where('user_id', $userRow['user_id']);
                    $db->update(
                        'user_details',
                        array(
                            'hash_algorithm' => 'phb',
                            'password' => $newPassword
                        )
                    );
                } else {
                    throw new Exception(_("Please checkss your login credentials"));
                }
            } else if ($userRow['hash_algorithm'] == 'phb') {
                if (!password_verify($_POST['password'], $userRow['password'])) {
                    $user->userHistoryLog($userName, 'failed', $userRow['user_id']);

                    throw new Exception(_("Please check your login credentials"));
                }
            }

            if (isset($userRow) && !empty($userRow)) {

                // regenerate session id
                session_regenerate_id(true);


                $user->userHistoryLog($userName, 'successful', $userRow['user_id']);
                //add random key
                $instanceResult = $db->rawQueryOne("SELECT vlsm_instance_id, instance_facility_name FROM s_vlsm_instance");

                if ($instanceResult) {
                    $_SESSION['instanceId'] = $instanceResult['vlsm_instance_id'];
                    $_SESSION['instanceFacilityName'] = $instanceResult['instance_facility_name'];
                } else {
                    $id = $general->generateRandomString();
                    // deleting just in case there is a row already inserted
                    $db->delete('s_vlsm_instance');
                    $db->insert('s_vlsm_instance', array('vlsm_instance_id' => $id));
                    $_SESSION['instanceId'] = $id;
                    $_SESSION['instanceFacilityName'] = null;

                    //Update instance ID in facility and form_vl tbl
                    $data = array('vlsm_instance_id' => $id);
                    $db->update('facility_details', $data);
                }


                $_SESSION['userId'] = $userRow['user_id'];
                $_SESSION['loginId'] = $userRow['login_id'];
                $_SESSION['userName'] = ($userRow['user_name']);
                $_SESSION['roleCode'] = $userRow['role_code'];
                $_SESSION['roleId'] = $userRow['role_id'];
                $_SESSION['accessType'] = $userRow['access_type'];
                $_SESSION['email'] = $userRow['email'];
                $_SESSION['forcePasswordReset'] = $userRow['force_password_reset'];
                $_SESSION['facilityMap'] = $facilitiesService->getUserFacilityMap($userRow['user_id']);
                $_SESSION['mappedProvinces'] = null;
                if (!empty($_SESSION['facilityMap'])) {
                    $provinceResult = $db->rawQuery("SELECT DISTINCT f.facility_state_id FROM facility_details as f WHERE f.facility_id IN (" . $_SESSION['facilityMap'] . ")");
                    $_SESSION['mappedProvinces'] = implode(',', array_column($provinceResult, 'facility_state_id'));
                }
                $_SESSION['crossLoginPass'] = null;
                if (SYSTEM_CONFIG['recency']['crosslogin'] === true && !empty(SYSTEM_CONFIG['recency']['url'])) {
                    $_SESSION['crossLoginPass'] = CommonService::encrypt($_POST['password'], base64_decode(SYSTEM_CONFIG['recency']['crossloginSalt']));
                }
                //Add event log
                $eventType = 'login';
                $action = ($userRow['user_name']) . ' logged in';
                $resource = 'user-login';
                $general->activityLog($eventType, $action, $resource);

                $redirect = '/error/401.php';
                //set role and privileges
                $priQuery = "SELECT p.privilege_name, rp.privilege_id, r.module FROM roles_privileges_map as rp INNER JOIN privileges as p ON p.privilege_id=rp.privilege_id INNER JOIN resources as r ON r.resource_id=p.resource_id  where rp.role_id='" . $userRow['role_id'] . "'";
                $priInfo = $db->query($priQuery);
                $module = $priId = [];
                if ($priInfo) {
                    foreach ($priInfo as $id) {
                        $priId[] = $id['privilege_name'];
                        $module[$id['module']] = $id['module'];
                    }

                    if ($userRow['landing_page'] != '') {
                        $redirect = $userRow['landing_page'];
                    } else {
                        $fileNameList = array('index.php', 'addVlRequest.php', 'vlRequest.php', 'batchcode.php', 'vlRequestMail.php', 'addImportResult.php', 'vlPrintResult.php', 'vlTestResult.php', 'vl-sample-status.php', 'vl-export-data.php', 'highViralLoad.php', 'roles.php', 'users.php', 'facilities.php', 'globalConfig.php', 'importConfig.php');
                        $fileName = array('/dashboard/index.php', '/vl/requests/addVlRequest.php', '/vl/requests/vlRequest.php', '/vl/batch/batchcode.php', 'mail/vlRequestMail.php', 'import-result/addImportResult.php', '/vl/results/vlPrintResult.php', '/vl/results/vlTestResult.php', 'program-management/vl-sample-status.php', 'program-management/vl-export-data.php', 'program-management/highViralLoad.php', 'roles/roles.php', 'users/$user.php', 'facilities/facilities.php', 'global-config/globalConfig.php', 'import-configs/importConfig.php');
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
                $_SESSION['module'] = $module ?: [];

                if (!empty($_SESSION['forcePasswordReset']) && $_SESSION['forcePasswordReset'] == 1) {
                    $redirect = "/users/editProfile.php";
                    $_SESSION['alertMsg'] = _("Please change your password to proceed.");
                }

                header("Location:" . $redirect);
            } else {
                $user->userHistoryLog($userName, 'failed');

                throw new Exception(_("Please check your login credentials"));
            }
        } else {
            throw new Exception(_("Please check your login credentials"));
        }
    }
} catch (Exception $exc) {
    //$_SESSION['alertMsg'] = _("Please check your login credentials");
    $_SESSION['alertMsg'] = $exc->getMessage();
    error_log($exc->getMessage() . " | " . $ipaddress . " | " . $userName);
    error_log($exc->getTraceAsString());
    header("Location:/login/login.php");
}
