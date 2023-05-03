<?php

use App\Services\ApiService;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\UsersService;

session_unset(); // no need of session in json response


/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var ApiService $app */
$app = ContainerRegistry::get(ApiService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

$vlsmSystemConfig = $general->getSystemConfig();
$transactionId = $general->generateUUID();
$input = json_decode(file_get_contents("php://input"), true);
try {
    if (isset($input['userName']) && !empty($input['userName']) && isset($input['password']) && !empty($input['password'])) {

        $queryParams = array($input['userName']);
        $userResult = $db->rawQueryOne(
            "SELECT ud.user_id, ud.user_name, ud.email, ud.phone_number, ud.login_id, ud.status, ud.app_access, ud.password, 
                                        ud.hash_algorithm, r.*, 
                                        (CASE WHEN (r.access_type = 'testing-lab') THEN 'yes' ELSE 'no' END) as testing_user 
                                        FROM user_details as ud 
                                        INNER JOIN roles as r ON ud.role_id=r.role_id 
                                        WHERE ud.login_id = ?",
            $queryParams
        );

        if ($userResult['testing_user'] == 'yes') {
            $remoteUser = "yes";
        } else {
            $remoteUser = "no";
        }
        if (count($userResult) > 0) {
            /* Update Phb hash password */


            if ($userResult['hash_algorithm'] == 'sha1') {
                $password = sha1($input['password'] . SYSTEM_CONFIG['passwordSalt']);
                if ($password == $userResult['password']) {
                    $passwordCheck = true;
                    $newPassword = $usersService->passwordHash($input['password']);
                    $db->where('user_id', $userResult['user_id']);
                    $db->update(
                        'user_details',
                        array(
                            'password' => $newPassword,
                            'hash_algorithm' => 'phb'
                        )
                    );
                } else {
                    $passwordCheck = false;
                }
            } else if ($userResult['hash_algorithm'] == 'phb') {
                if (!password_verify($input['password'], $userResult['password'])) {
                    $passwordCheck = false;
                } else {
                    $passwordCheck = true;
                }
            }

            if ($userResult['status'] != 'active' || $passwordCheck === false) {
                $payload = array(
                    'status' => 2,
                    'message' => 'Login failed. Please contact system administrator.',
                    'timestamp' => time(),
                );
            } else if (isset($userResult['app_access']) && $userResult['app_access'] == "no") {
                $payload = array(
                    'status' => 2,
                    'message' => 'Login failed. Please contact system administrator.',
                    'timestamp' => time(),
                );
            } else {

                $tokenData = $usersService->getAuthToken(null, $userResult['user_id']);

                if (!empty($tokenData)) {
                    $data = [];

                    unset($userResult['password']);
                    unset($userResult['hash_algorithm']);
                    unset($userResult['app_access']);

                    $data['user'] = $userResult;
                    $data['form'] = $general->getGlobalConfig('vl_form');
                    $data['api_token'] = $tokenData['token'];
                    $data['new_token'] = $tokenData['token_updated'];
                    $data['appMenuName'] = $general->getGlobalConfig('app_menu_name');
                    $data['access'] = $usersService->getUserRolePrivileges($userResult['user_id']);
                    // print_r($data);die;
                    $payload = array(
                        'status' => 1,
                        'message' => 'Login Success',
                        'data' => $data,
                        'timestamp' => time(),
                    );
                } else {
                    $payload = array(
                        'status' => 2,
                        'message' => 'Someting went wrong. Please try again later.',
                        'timestamp' => time(),
                    );
                }
            }
        } else {
            $payload = array(
                'status' => 2,
                'message' => 'Login failed. Please contact system administrator.',
                'timestamp' => time(),
            );
        }
    } else {
        $payload = array(
            'status' => 0,
            'message' => 'Please enter valid credentials',
            'timestamp' => time(),
        );
    }
    $trackId = $general->addApiTracking($transactionId, $data['user']['user_id'], count($userResult), 'login', 'common', $_SERVER['REQUEST_URI'], $input, $payload, 'json');

    echo json_encode($payload);
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
