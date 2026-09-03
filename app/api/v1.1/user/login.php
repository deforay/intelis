<?php

use Slim\Psr7\Request;
use App\Services\ApiService;
use App\Services\UsersService;
use App\Utilities\JsonUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;

/** @var Request $request */
$request = AppRegistry::get('request');

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

/** @var ApiService $apiService */
$apiService = ContainerRegistry::get(ApiService::class);

$origJson = $apiService->getJsonFromRequest($request);
$input = JsonUtility::decodeJson($origJson);

$transactionId = MiscUtility::generateULID();

// Three outcomes, each with its own words, so a broken table never reads as a
// wrong password in the field:
//   status 2        wrong login, wrong password, or an account without app access
//   status 'failed' a request the server could not act on, with the reason
// HTTP stays 200 on v1.1; the app reads the body.
$invalidRequest = 400;
$credentialsRejected = 401;

try {
    if (empty($input) || !is_array($input)) {
        throw new SystemException(_translate('The request body must be JSON with userName and password'), $invalidRequest);
    }
    if (empty($input['userName'])) {
        throw new SystemException(_translate('userName is required'), $invalidRequest);
    }
    if (empty($input['password'])) {
        throw new SystemException(_translate('password is required'), $invalidRequest);
    }

    $userQuery = "SELECT ud.user_id,
                    ud.user_name,
                    ud.email,
                    ud.phone_number,
                    ud.login_id,
                    ud.status,
                    ud.password,
                    ud.api_token,
                    r.role_id,
                    r.role_name,
                    r.role_code,
                    r.access_type,
                    r.landing_page,
                    (CASE WHEN (r.access_type = 'testing-lab') THEN 'yes' ELSE 'no' END) as testing_user
                    FROM user_details as ud
                    INNER JOIN roles as r ON ud.role_id=r.role_id
                    WHERE IFNULL(ud.app_access, 'no') = 'yes'
                    AND ud.status = 'active'
                    AND ud.login_id = ?";
    $userResult = $db->rawQueryOne($userQuery, [$input['userName']]);

    if (empty($userResult) || !$usersService->passwordVerify($input['userName'], (string) $input['password'], (string) $userResult['password'])) {
        throw new SystemException(_translate('Login failed. Please contact system administrator.'), $credentialsRejected);
    }

    // Not needed anymore in the following code
    unset($userResult['password']);

    $tokenData = $usersService->handleTokenAuthentication($userResult['api_token'], $userResult['user_id']);

    if (empty($tokenData)) {
        throw new SystemException(_translate('Authentication failed. Please contact system administrator.'), $credentialsRejected);
    }

    $data = [];

    $data['user'] = $userResult;
    $data['form'] = (int) $general->getGlobalConfig('vl_form');
    $data['api_token'] = $tokenData['token'];
    $data['new_token'] = $tokenData['token_updated'];
    $data['appMenuName'] = $general->getGlobalConfig('app_menu_name');
    $data['access'] = $usersService->getUserRolePrivileges($userResult['user_id']);

    $payload = [
        'status' => 1,
        'message' => 'Login Success',
        'timestamp' => time(),
        'transactionId' => $transactionId,
        'data' => $data
    ];
} catch (Throwable $exc) {
    $code = $exc instanceof SystemException ? (int) $exc->getCode() : 0;

    if ($code === $credentialsRejected) {
        // A mistyped password is not a server fault: warning, no trace.
        LoggerUtility::logWarning('Login rejected for ' . ($input['userName'] ?? '') . ': ' . $exc->getMessage(), [
            'transactionId' => $transactionId,
        ]);
        $payload = [
            'status' => 2,
            'message' => _translate('Login failed. Please contact system administrator.'),
            'timestamp' => time(),
            'transactionId' => $transactionId
        ];
    } elseif ($code === $invalidRequest) {
        $payload = [
            'status' => 'failed',
            'message' => $exc->getMessage(),
            'timestamp' => time(),
            'transactionId' => $transactionId
        ];
    } else {
        LoggerUtility::logError('Login could not be processed: ' . $exc->getMessage(), [
            'transactionId' => $transactionId,
            'file' => $exc->getFile(),
            'line' => $exc->getLine(),
            'trace' => $exc->getTraceAsString()
        ]);
        $payload = [
            'status' => 'failed',
            'message' => _translate('InteLIS could not process the login. Please try again in a few minutes. If the problem continues, contact the system administrator.'),
            'timestamp' => time(),
            'transactionId' => $transactionId
        ];
    }
}
$payload = JsonUtility::encodeUtf8Json($payload);

$trackId = $general->addApiTracking($transactionId, $data['user']['user_id'] ?? null, 1, 'login', 'common', $_SERVER['REQUEST_URI'], $origJson, $payload, 'json');

//echo $payload
echo ApiService::generateJsonResponse($payload, $request);
