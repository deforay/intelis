<?php
session_unset(); // no need of session in json response
header('Content-Type: application/json');

$general = new \Vlsm\Models\General();
$users = new \Vlsm\Models\Users();
$app = new \Vlsm\Models\App();

$vlsmSystemConfig = $general->getSystemConfig();

$input = json_decode(file_get_contents("php://input"), true);
try {
    if (isset($input['userName']) && !empty($input['userName']) && isset($input['password']) && !empty($input['password'])) {

        /* Check hash login id exist */
        $hashCheckQuery = "SELECT `user_id`, `login_id`, `hash_algorithm` FROM user_details WHERE `login_id` = ?";
        $hashCheck = $db->rawQueryOne($hashCheckQuery, array($db->escape($_POST['userName'])));

        $username = $db->escape($input['userName']);
        $password = $db->escape($input['password']);
        $sha1protect = false;
        if (isset($hashCheck) && !empty($hashCheck['user_id']) && !empty($hashCheck['hash_algorithm'])) {
            if ($hashCheck['hash_algorithm'] == 'sha1') {
                $password = sha1($password . SYSTEM_CONFIG['passwordSalt']);
                $sha1protect = true;
            }
            if ($hashCheck['hash_algorithm'] == 'phb') {
                $password = $user->passwordHash($db->escape($_POST['password']), $hashCheck['user_id']);
                if (!password_verify($db->escape($_POST['password']), $hashCheck['password'])) {
                    $_SESSION['alertMsg'] = _("Something went wrong!");
                    $payload = array(
                        'status' => 2,
                        'message' => 'Invalid password.',
                        'timestamp' => time(),
                    );
                    exit(0);
                }
            }
        } else {
            $password = sha1($password . SYSTEM_CONFIG['passwordSalt']);
        }
        $queryParams = array($username, $password);
        $userResult = $db->rawQueryOne("SELECT ud.user_id, ud.user_name, ud.email, ud.phone_number, ud.login_id, ud.status, ud.app_access, r.*, (CASE WHEN (r.access_type = 'testing-lab') THEN 'yes' ELSE 'no' END) as testing_user FROM user_details as ud INNER JOIN roles as r ON ud.role_id=r.role_id WHERE ud.login_id = ? AND ud.password = ?", $queryParams);
        // print_r($userResult);die;

        if ($userResult['testing_user'] == 'yes') {
            $remoteUser = "yes";
        } else {
            $remoteUser = "no";
        }
        if (count($userResult) > 0) {
            /* Update Phb hash password */
            if ($sha1protect) {
                $password = $users->passwordHash($db->escape($_POST['password']), $userResult['user_id']);
                $db = $db->where('user_id', $userResult['user_id']);
                $db->update('user_details', array('password' => $password, 'hash_algorithm' => 'phb'));
            }
            if ($userResult['status'] != 'active') {
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
                $randomString = base64_encode($result['user_id'] . "-" . $general->generateToken(3));

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
                'message' => 'Please enter valid credentials',
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


    echo json_encode($payload);
    exit(0);
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
    exit(0);
}
