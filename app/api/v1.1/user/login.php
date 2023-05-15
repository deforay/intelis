<?php

use App\Exceptions\SystemException;
use App\Services\ApiService;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\UsersService;


/** @var Slim\Psr7\Request $request */
$request = $GLOBALS['request'];

//$origJson = (string) $request->getBody();
$input = $request->getParsedBody();

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var ApiService $app */
$app = ContainerRegistry::get(ApiService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

$transactionId = $general->generateUUID();
try {
    if (
        isset($input['userName']) && !empty($input['userName']) &&
        isset($input['password']) && !empty($input['password'])
    ) {

        $queryParams = array($input['userName']);
        $userResult = $db->rawQueryOne(
            "SELECT ud.user_id,
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
                    AND ud.login_id = ?",
            $queryParams
        );

        if (empty($userResult) || !password_verify($input['password'], $userResult['password'])) {
            throw new SystemException('Login failed. Please contact system administrator.');
        } else {
            if ($userResult['testing_user'] == 'yes') {
                $remoteUser = "yes";
            } else {
                $remoteUser = "no";
            }

            $tokenData = $usersService->getAuthToken($userResult['api_token']);


            $data = [];

            unset($userResult['password']);

            $data['user'] = $userResult;
            $data['form'] = $general->getGlobalConfig('vl_form');
            $data['api_token'] = $tokenData['token'];
            $data['new_token'] = $tokenData['token_updated'];
            $data['appMenuName'] = $general->getGlobalConfig('app_menu_name');
            $data['access'] = $usersService->getUserRolePrivileges($userResult['user_id']);

            $payload = array(
                'status' => 1,
                'message' => 'Login Success',
                'data' => $data,
                'timestamp' => time(),
            );
        }
    } else {
        throw new SystemException('Login failed. Please contact system administrator.');
    }
} catch (SystemException $exc) {
    http_response_code(400);
    $payload = array(
        'status' => 2,
        'message' => 'Login failed. Please contact system administrator.',
        'timestamp' => time(),
    );
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
$payload = json_encode($payload);
$trackId = $general->addApiTracking($transactionId, $data['user']['user_id'], count($userResult), 'login', 'common', $_SERVER['REQUEST_URI'], $input, $payload, 'json');
echo $payload;
