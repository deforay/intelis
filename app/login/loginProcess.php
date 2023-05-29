<?php

use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Exceptions\SystemException;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = $GLOBALS['request'];
$_POST = $request->getParsedBody();


$tableName = "user_details";
$userName = ($_POST['username']);
$password = ($_POST['password']);


/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);



$_SESSION['logged'] = false;
$systemInfo = $general->getSystemConfig();
$ipaddress = $general->getClientIpAddress();

$_SESSION['instanceType'] = $systemInfo['sc_user_type'];
$_SESSION['instanceLabId'] = !empty($systemInfo['sc_testing_lab_id']) ? $systemInfo['sc_testing_lab_id'] : null;


try {
    if (isset($_GET['u']) && isset($_GET['t']) && SYSTEM_CONFIG['recency']['crosslogin']) {
        $_POST['username'] = base64_decode($_GET['u']);

        $decryptedPassword = CommonService::decrypt($_GET['t'], base64_decode(SYSTEM_CONFIG['recency']['crossloginSalt']));
        $_POST['password'] = $decryptedPassword;
    }

    if (isset($_POST['csrf_token']) && $_POST['csrf_token'] != $_SESSION['csrf_token']) {
        // clear/reset token
        $_SESSION['csrf_token'] = null;
        unset($_SESSION['csrf_token']);
        throw new SystemException(_("Request expired. Please try to login again."));
    }

    /* Crosss Login Block End */


    if (!empty($_POST['username']) && !empty($_POST['password'])) {

        $userName = ($_POST['username']);
        $password = ($_POST['password']);

        $userRow = $db->rawQueryOne(
            "SELECT * FROM user_details as ud
                                        INNER JOIN roles as r ON ud.role_id=r.role_id
                                        WHERE ud.login_id = ? AND ud.status = ?",
            [$userName, 'active']
        );

        $loginAttemptCount = $db->rawQueryOne(
            "SELECT
                SUM(CASE WHEN ulh.login_id = ? THEN 1 ELSE 0 END) AS LoginIdCount,
                SUM(CASE WHEN ulh.ip_address = ? THEN 1 ELSE 0 END) AS IpCount
            FROM
                user_login_history ulh
            WHERE
                ulh.login_status = 'failed'
                AND ulh.login_attempted_datetime >= DATE_SUB(?, INTERVAL 15 minute)",
            [$userName, $ipaddress, DateUtility::getCurrentDateTime()]
        );
        $maxLoginAttempts = 3;

        if (($loginAttemptCount['LoginIdCount'] >= $maxLoginAttempts
                || $loginAttemptCount['IpCount'] >= $maxLoginAttempts)
            &&
            (empty($_POST['captcha']) || $_POST['captcha'] != $_SESSION['captchaCode'])
        ) {
            $usersService->userHistoryLog($userName, 'failed');
            $_SESSION['alertMsg'] = _("You have exhausted the maximum number of login attempts. Please retry login after some time.");
            header("Location:/login/login.php");
        }

        if ($userRow['hash_algorithm'] == 'sha1') {
            if (sha1($password . SYSTEM_CONFIG['passwordSalt']) == $userRow['password']) {
                $newPassword = $usersService->passwordHash($_POST['password']);
                $db->where('user_id', $userRow['user_id']);
                $db->update(
                    'user_details',
                    array(
                        'hash_algorithm' => 'phb',
                        'password' => $newPassword
                    )
                );
            } else {
                throw new SystemException(_("Please checkss your login credentials"));
            }
        } elseif ($userRow['hash_algorithm'] == 'phb' && !password_verify($_POST['password'], $userRow['password'])) {
            $usersService->userHistoryLog($userName, 'failed', $userRow['user_id']);
            throw new SystemException(_("Please check your login credentials"));
        }

        if (!empty($userRow)) {

            // regenerate session id
            session_regenerate_id(true);
            $usersService->userHistoryLog($userName, 'successful', $userRow['user_id']);
            $instanceResult = $db->rawQueryOne("SELECT vlsm_instance_id, instance_facility_name FROM s_vlsm_instance");

            if (!empty($instanceResult['instanceId'])) {
                $_SESSION['instanceId'] = $instanceResult['vlsm_instance_id'];
                $_SESSION['instanceFacilityName'] = $instanceResult['instance_facility_name'];
            } else {
                $id = $general->generateRandomString();
                $db->insert('s_vlsm_instance', array('vlsm_instance_id' => $id));
                $_SESSION['instanceId'] = $id;
                $_SESSION['instanceFacilityName'] = null;
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
                $provinceResult = $db->rawQuery("SELECT DISTINCT f.facility_state_id
                                                    FROM facility_details as f
                                                WHERE f.facility_id IN (" . $_SESSION['facilityMap'] . ")");
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

            //set role and privileges
            $privilegesQuery = "SELECT p.privilege_name, rp.privilege_id, r.module
                                FROM roles_privileges_map as rp
                                INNER JOIN privileges as p ON p.privilege_id=rp.privilege_id
                                INNER JOIN resources as r ON r.resource_id=p.resource_id
                                WHERE rp.role_id= ?";
            $privilegesResult = $db->rawQuery($privilegesQuery, [$userRow['role_id']]);
            $module = $privileges = [];
            if (!empty($privilegesResult)) {
                foreach ($privilegesResult as $id) {
                    $privileges[] = $id['privilege_name'];
                    $module[$id['module']] = $id['module'];
                }
            }

            $redirect = !empty($userRow['landing_page']) ? $userRow['landing_page'] : '/dashboard/index.php';

            $_SESSION['privileges'] = $privileges ?? [];
            $_SESSION['module'] = $module ?? [];

            if (!empty($_SESSION['forcePasswordReset']) && $_SESSION['forcePasswordReset'] == 1) {
                $redirect = "/users/editProfile.php";
                $_SESSION['alertMsg'] = _("Please change your password to proceed.");
            }

            header("Location:" . $redirect);
        } else {
            $usersService->userHistoryLog($userName, 'failed');
            throw new SystemException(_("Please check your login credentials"));
        }
    } else {
        throw new SystemException(_("Please check your login credentials"));
    }
} catch (SystemException $exc) {
    $_SESSION['alertMsg'] = $exc->getMessage();
    error_log($exc->getMessage() . " | " . $ipaddress . " | " . $userName);
    error_log($exc->getTraceAsString());
    header("Location:/login/login.php");
}
