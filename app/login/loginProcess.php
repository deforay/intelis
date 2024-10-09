<?php

use App\Services\UsersService;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Services\SecurityService;
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

    SecurityService::rotateCSRF($request);

    if (isset($_GET['u']) && isset($_GET['t']) && SYSTEM_CONFIG['recency']['crosslogin']) {
        $_POST['username'] = base64_decode((string) $_GET['u']);

        $decryptedPassword = CommonService::decrypt($_GET['t'], base64_decode((string) SYSTEM_CONFIG['recency']['crossloginSalt']));
        $_POST['password'] = $decryptedPassword;
    }

    /* Crosss Login Block End */

    if (!empty($_POST['username']) && !empty($_POST['password'])) {


        if (
            ($usersService->continuousFailedLogins($_POST['username']) === true) &&
            ((!empty($_SESSION['captchaCode']) && empty($_POST['captcha'])) ||
                ($_POST['captcha'] != $_SESSION['captchaCode']))
        ) {
            throw new SystemException(_translate("You have exhausted the maximum number of login attempts. Please retry login after some time."));
        }

        $userRow = $db->rawQueryOne(
            "SELECT * FROM user_details as ud
                                        INNER JOIN roles as r ON ud.role_id=r.role_id
                                        WHERE ud.login_id = ? AND ud.status = ?",
            [$_POST['username'], 'active']
        );


        $usersService->recordLoginAttempt($_POST['username'], 'failed');


        if (empty($userRow) || !password_verify((string) $_POST['password'], (string) $userRow['password'])) {
            $usersService->recordLoginAttempt($_POST['username'], 'failed', $userRow['user_id']);
            throw new SystemException(_translate("Please check your login credentials"));
        }

        // regenerate session id
        session_regenerate_id(true);
        $usersService->recordLoginAttempt($_POST['username'], 'successful', $userRow['user_id']);
        $instanceResult = $db->rawQueryOne("SELECT vlsm_instance_id, instance_facility_name FROM s_vlsm_instance");

        if (!empty($instanceResult['vlsm_instance_id'])) {
            $_SESSION['instanceId'] = $instanceResult['vlsm_instance_id'];
            //$_SESSION['instance']['facilityName'] = $instanceResult['instance_facility_name'];
        } else {
            $id = MiscUtility::generateRandomString();
            $db->insert('s_vlsm_instance', ['vlsm_instance_id' => $id]);
            $_SESSION['instanceId'] = $id;
            //$_SESSION['instance']['facilityName'] = null;
        }

        $_SESSION['formId'] = (int) $general->getGlobalConfig('vl_form');
        $_SESSION['userId'] = $userRow['user_id'];
        $_SESSION['loginId'] = $userRow['login_id'];
        $_SESSION['userName'] = ($userRow['user_name']);
        $_SESSION['roleCode'] = $userRow['role_code'];
        $_SESSION['roleId'] = $userRow['role_id'];
        $_SESSION['accessType'] = $userRow['access_type'];
        $_SESSION['email'] = $userRow['email'];
        $_SESSION['forcePasswordReset'] = $userRow['force_password_reset'] ?? 0;
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
        $redirect = $_SESSION['landingPage'] = !empty($userRow['landing_page']) ? $userRow['landing_page'] : '/dashboard/index.php';

        if (!empty($_SESSION['forcePasswordReset']) && $_SESSION['forcePasswordReset'] == 1) {
            $redirect = "/users/edit-profile.php";
            $_SESSION['alertMsg'] = _translate("Please change your password to proceed.");
        } elseif (isset($_SESSION['requestedURI'])) {
            $redirect = $_SESSION['requestedURI'];
            unset($_SESSION['requestedURI']);
        }
    } else {
        throw new SystemException(_translate("Please check your login credentials"));
    }
} catch (SystemException $exception) {
    $_SESSION['alertMsg'] = $exception->getMessage();
    LoggerUtility::log('error', $exception->getMessage() . " | " . $ipaddress . " | " . $_POST['username'], [
        'exception' => $exception,
        'file' => $exception->getFile(), // File where the error occurred
        'line' => $exception->getLine(), // Line number of the error
        //'stacktrace' => $exception->getTraceAsString()
    ]);
    $redirect = "/login/login.php";
}
header("Location:$redirect");
