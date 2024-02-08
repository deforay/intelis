<?php

use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

if (!empty($request->getQueryParams())) {
    $_GET = _sanitizeInput($request->getQueryParams());
}

$redirect = "/";

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

$_SESSION['logged'] = false;
$systemInfo = $general->getSystemConfig();
$ipaddress = $general->getClientIpAddress();

try {
    if (isset($_GET['u']) && isset($_GET['t']) && SYSTEM_CONFIG['recency']['crosslogin']) {
        $_POST['username'] = base64_decode((string) $_GET['u']);

        $decryptedPassword = CommonService::decrypt($_GET['t'], base64_decode((string) SYSTEM_CONFIG['recency']['crossloginSalt']));
        $_POST['password'] = $decryptedPassword;
    }

    if (isset($_POST['csrf_token']) && $_POST['csrf_token'] != $_SESSION['csrf_token']) {
        // clear/reset token
        $_SESSION['csrf_token'] = null;
        unset($_SESSION['csrf_token']);
        throw new SystemException(_translate("Request expired. Please try to login again."));
    }
    /* Crosss Login Block End */

    if (!empty($_POST['username']) && !empty($_POST['password'])) {

        $userRow = $db->rawQueryOne(
            "SELECT * FROM user_details as ud
                                        INNER JOIN roles as r ON ud.role_id=r.role_id
                                        WHERE ud.login_id = ? AND ud.status = ?",
            [$_POST['username'], 'active']
        );

        $loginAttemptCount = $db->rawQueryOne(
            "SELECT
                SUM(CASE WHEN ulh.login_id = ? THEN 1 ELSE 0 END) AS LoginIdCount,
                SUM(CASE WHEN ulh.ip_address = ? THEN 1 ELSE 0 END) AS IpCount
            FROM
                user_login_history ulh
            WHERE
                ulh.login_status = 'failed' AND
                ulh.login_attempted_datetime >= DATE_SUB(?, INTERVAL 15 minute)",
            [$_POST['username'], $ipaddress, DateUtility::getCurrentDateTime()]
        );



        $usersService->recordLoginAttempt($_POST['username'], 'failed');

        $maxLoginAttempts = 3;

        if (($loginAttemptCount['LoginIdCount'] >= $maxLoginAttempts
                || $loginAttemptCount['IpCount'] >= $maxLoginAttempts)
            &&
            (empty($_POST['captcha']) || $_POST['captcha'] != $_SESSION['captchaCode'])
        ) {
            throw new SystemException(_translate("You have exhausted the maximum number of login attempts. Please retry login after some time."));
        }

        if (empty($userRow)) {
            throw new SystemException(_translate("Please check your login credentials"));
        } elseif ($userRow['hash_algorithm'] == 'sha1') {
            if (sha1($_POST['password'] . SYSTEM_CONFIG['passwordSalt']) == $userRow['password']) {
                $newPassword = $usersService->passwordHash($_POST['password']);
                $db->where('user_id', $userRow['user_id']);
                $db->update(
                    'user_details',
                    [
                        'hash_algorithm' => 'phb',
                        'password' => $newPassword
                    ]
                );
            } else {
                throw new SystemException(_translate("Please check your login credentials"));
            }
        } elseif (!password_verify((string) $_POST['password'], (string) $userRow['password'])) {
            $usersService->recordLoginAttempt($_POST['username'], 'failed', $userRow['user_id']);
            throw new SystemException(_translate("Please check your login credentials"));
        }

        // regenerate session id
        session_regenerate_id(true);
        $usersService->recordLoginAttempt($_POST['username'], 'successful', $userRow['user_id']);
        $instanceResult = $db->rawQueryOne("SELECT vlsm_instance_id, instance_facility_name FROM s_vlsm_instance");

        if (!empty($instanceResult['vlsm_instance_id'])) {
            $_SESSION['instanceId'] = $instanceResult['vlsm_instance_id'];
            $_SESSION['instanceFacilityName'] = $instanceResult['instance_facility_name'];
        } else {
            $id = $general->generateRandomString();
            $db->insert('s_vlsm_instance', ['vlsm_instance_id' => $id]);
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
        $_SESSION['userLocale'] = $userRow['user_locale'] ?? null;
        $_SESSION['mappedProvinces'] = null;

        if (!empty($_SESSION['facilityMap'])) {
            $provinceResult = $db->rawQuery("SELECT DISTINCT f.facility_state_id
                                                    FROM facility_details as f
                                                    WHERE f.facility_id IN (" . $_SESSION['facilityMap'] . ")");
            $_SESSION['mappedProvinces'] = implode(',', array_column($provinceResult, 'facility_state_id'));
        }
        $_SESSION['crossLoginPass'] = null;
        if (SYSTEM_CONFIG['recency']['crosslogin'] === true && !empty(SYSTEM_CONFIG['recency']['url'])) {
            $_SESSION['crossLoginPass'] = CommonService::encrypt($_POST['password'], base64_decode((string) SYSTEM_CONFIG['recency']['crossloginSalt']));
        }
        //Add event log
        $eventType = 'login';
        $action = ($userRow['user_name']) . ' logged in';
        $resource = 'user-login';
        $general->activityLog($eventType, $action, $resource);

        $modules = $privileges = [];

        [$_SESSION['modules'], $_SESSION['privileges']] = $usersService->getAllPrivileges($userRow['role_id']);
        $_SESSION['landingPage'] = $redirect = !empty($userRow['landing_page']) ? $userRow['landing_page'] : '/dashboard/index.php';

        if (!empty($_SESSION['forcePasswordReset']) && $_SESSION['forcePasswordReset'] == 1) {
            $redirect = "/users/editProfile.php";
            $_SESSION['alertMsg'] = _translate("Please change your password to proceed.");
        }
    } else {
        throw new SystemException(_translate("Please check your login credentials"));
    }
} catch (SystemException $exception) {
    $_SESSION['alertMsg'] = $exception->getMessage();
    LoggerUtility::log('info', $exception->getMessage() . " | " . $ipaddress . " | " . $_POST['username'], [
        'exception' => $exception,
        'file' => $exception->getFile(), // File where the error occurred
        'line' => $exception->getLine(), // Line number of the error
        //'stacktrace' => $exception->getTraceAsString()
    ]);
    $redirect = "/login/login.php";
}

header("Location:" . $redirect);
