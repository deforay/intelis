<?php

header('Content-Type: application/json');

$general = new \Vlsm\Models\General();
$users = new \Vlsm\Models\Users();
$app = new \Vlsm\Models\App();

$vlsmSystemConfig = $general->getSystemConfig();

$input = json_decode(file_get_contents("php://input"), true);
try {
    if (isset($input['userName']) && !empty($input['userName']) && isset($input['password']) && !empty($input['password'])) {

        $username = $db->escape($input['userName']);
        $password = $db->escape($input['password']);
        // $systemConfig['passwordSalt']='PUT-A-RANDOM-STRING-HERE';
        $password = sha1($password . $systemConfig['passwordSalt']);
        $queryParams = array($username, $password);
        $userResult = $db->rawQueryOne("SELECT ud.*, r.*, (CASE WHEN (r.access_type = 'testing-lab') THEN 'yes' ELSE 'no' END) as testing_user FROM user_details as ud INNER JOIN roles as r ON ud.role_id=r.role_id WHERE ud.login_id = ? AND ud.password = ?", $queryParams);
        // print_r($userResult);die;

        if ($vlsmSystemConfig['sc_user_type'] == 'remoteuser') {
            $remoteUser = "yes";
        } else {
            $remoteUser = "no";
        }
        if (count($userResult) > 0) {
            if ($userResult['status'] != 'active') {
                $payload = array(
                    'status' => 2,
                    'message' => 'Login failed. Please contact system administrator.',
                    'timestamp' => $general->getDateTime()
                );
            } else if (isset($userResult['app_access']) && $userResult['app_access'] == "no") {
                $payload = array(
                    'status' => 2,
                    'message' => 'Login failed. Please contact system administrator.',
                    'timestamp' => $general->getDateTime()
                );
            } else {
                $randomString = $general->generateToken();

                $userData['api_token'] = $randomString;
                $userData['api_token_generated_datetime'] = $general->getDateTime();
                $db = $db->where('user_id', $userResult['user_id']);
                $upId = $db->update('user_details', $userData);
                if ($upId) {
                    $data = array();
                    $configFormQuery = "SELECT * FROM global_config WHERE name ='vl_form'";
                    $configFormResult = $db->rawQuery($configFormQuery);
                    $data['user'] = $userResult;
                    $data['form'] = $configFormResult[0]['value'];
                    $data['api_token'] = $randomString;
                    $data['appMenuName'] = $general->getGlobalConfig('app_menu_name');
                    $data['access'] = $users->getUserRolePrivileges($userResult['user_id']);
                    // print_r($data);die;
                    $payload = array(
                        'status' => 1,
                        'message' => 'Login Success',
                        'data' => $data,
                        'timestamp' => $general->getDateTime()
                    );
                } else {
                    $payload = array(
                        'status' => 2,
                        'message' => 'Someting went wrong. Please try again later.',
                        'timestamp' => $general->getDateTime()
                    );
                }
            }
        } else {
            $payload = array(
                'status' => 2,
                'message' => 'Please enter valid credentials',
                'timestamp' => $general->getDateTime()
            );
        }
    } else {
        $payload = array(
            'status' => 0,
            'message' => 'Please enter valid credentials',
            'timestamp' => $general->getDateTime()
        );
    }


    echo json_encode($payload);
    exit(0);
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
    exit(0);
}
