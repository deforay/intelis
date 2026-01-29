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
use Psr\Http\Message\ServerRequestInterface;
use App\Registries\ContainerRegistry;

// Sanitized values from $request object
/** @var ServerRequestInterface $request */
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
$ipAddress = $general->getClientIpAddress($request);

SecurityService::checkLoginAttempts($ipAddress);

try {

    if (!empty($_POST['labname'])) {
        http_response_code(403);
        throw new SystemException(_translate("Invalid Login Credentials"));
    }

    if (isset($_GET['u']) && isset($_GET['t']) && SYSTEM_CONFIG['recency']['crosslogin']) {
        $_POST['username'] = base64_decode((string) $_GET['u']);

        $decryptedPassword = CommonService::decrypt($_GET['t'], base64_decode((string) SYSTEM_CONFIG['recency']['crossloginSalt']));
        $_POST['password'] = $decryptedPassword;
    }

    /* Crosss Login Block End */

    if (!empty($_POST['username']) && !empty($_POST['password'])) {

        if (_isPotentiallyMalicious($_POST['username'])) {
            LoggerUtility::logError("Potential malicious login attempt blocked for user: " . $_POST['username']);
            throw new SystemException(_translate("Invalid Login Credentials"));
        }


        if (
            ($usersService->continuousFailedLogins($_POST['username']) >= 3) &&
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


        if (empty($userRow) || !$usersService->passwordVerify((string) $_POST['username'], (string) $_POST['password'], (string) $userRow['password'])) {
            $usersService->recordLoginAttempt($_POST['username'], 'failed', $userRow['user_id']);
            throw new SystemException(_translate("Please check your login credentials"));
        }

        // regenerate session id
        $appLocale = $_SESSION['APP_LOCALE'] ?? 'en_US';
        session_regenerate_id(true);
        $_SESSION = [];
        $_SESSION['APP_LOCALE'] = $appLocale;
        $usersService->recordLoginAttempt($_POST['username'], 'successful', $userRow['user_id']);
        $instanceResult = $db->rawQueryOne("SELECT vlsm_instance_id, instance_facility_name FROM s_vlsm_instance");

        if (!empty($instanceResult['vlsm_instance_id'])) {
            $_SESSION['instanceId'] = $instanceResult['vlsm_instance_id'];
        } else {
            $id = MiscUtility::generateRandomString();
            $db->insert('s_vlsm_instance', ['vlsm_instance_id' => $id]);
            $_SESSION['instanceId'] = $id;
        }

        $_SESSION['formId'] = (int) $general->getGlobalConfig('vl_form');
        $_SESSION['userId'] = $userRow['user_id'];
        $_SESSION['loginId'] = $userRow['login_id'];
        $_SESSION['userName'] = $userRow['user_name'];
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
        $action = $userRow['user_name'] . ' logged in';
        $resource = 'user-login';
        $general->activityLog($eventType, $action, $resource);

        $modules = $privileges = [];


        [$_SESSION['modules'], $_SESSION['privileges']] = $usersService->getAllPrivileges($userRow['role_id']);
        $redirect = $_SESSION['landingPage'] = empty($userRow['landing_page']) ? '/dashboard/index.php' : $userRow['landing_page'];

        if (!empty($_SESSION['forcePasswordReset']) && $_SESSION['forcePasswordReset'] == 1) {
            $redirect = "/users/edit-profile.php";
            $_SESSION['alertMsg'] = _translate("Please change your password to proceed.");
        } elseif (isset($_SESSION['requestedURI'])) {
            if (_isAllowed($_SESSION['requestedURI'])) {
                $redirect = $_SESSION['requestedURI'];
            }
            unset($_SESSION['requestedURI']);
        }
    } else {
        $_SESSION['alertMsg'] = _translate("Please check your login credentials");
        throw new SystemException(_translate("Please check your login credentials"));
    }
} catch (Throwable $e) {
    $_SESSION['alertMsg'] = $e->getMessage();
    LoggerUtility::logError($e->getMessage() . " | " . $ipAddress . " | " . $_POST['username'], [
        'errorType' => 'login',
        'exception' => $e,
        'code' => $e->getCode(),
        'last_db_error' => $db->getLastError(),
        'last_db_query' => $db->getLastQuery(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
    $redirect = "/login/login.php";
}

SecurityService::redirect($redirect);
exit;
