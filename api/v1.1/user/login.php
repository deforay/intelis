<?php
// Allow from any origin
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');    // cache for 1 day
}
// Access-Control headers are received during OPTIONS requests
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    exit(0);
}
header('Content-Type: application/json');

$general = new \Vlsm\Models\General($db);
$app = new \Vlsm\Models\App($db);

$input = json_decode(file_get_contents("php://input"), true);
try {
    if (isset($input['userName']) && !empty($input['userName']) && isset($input['password']) && !empty($input['password'])) {

        $username = $db->escape($input['userName']);
        $password = $db->escape($input['password']);
        // $systemConfig['passwordSalt']='PUT-A-RANDOM-STRING-HERE';
        $password = sha1($password . $systemConfig['passwordSalt']);
        $queryParams = array($username, $password);
        $admin = $db->rawQueryOne("SELECT ud.*, (CASE WHEN (r.access_type = 'testing-lab') THEN 'yes' ELSE 'no' END) as testing_user FROM user_details as ud INNER JOIN roles as r ON ud.role_id=r.role_id WHERE ud.login_id = ? AND ud.password = ?", $queryParams);
        // print_r($admin);die;

        if ($systemConfig['sc_user_type'] == 'remoteuser') {
            $remoteUser = "yes";
        } else {
            $remoteUser = "no";
        }
        if (count($admin) > 0) {
            if ($admin['status'] != 'active') {
                $payload = array(
                    'status' => 2,
                    'message' => 'Login failed. Please contact system administrator.',
                    'timestamp' => $general->getDateTime()
                );
            } else if (isset($admin['app_access']) && $admin['app_access'] == "no") {
                $payload = array(
                    'status' => 2,
                    'message' => 'Login failed. Please contact system administrator.',
                    'timestamp' => $general->getDateTime()
                );
            } else {
                $randomString = $general->generateToken();

                $userData['api_token'] = $randomString;
                $userData['api_token_generated_datetime'] = $general->getDateTime();
                $db = $db->where('user_id', $admin['user_id']);
                $upId = $db->update('user_details', $userData);
                if ($upId) {
                    $data = array();
                    $configFormQuery = "SELECT * FROM global_config WHERE name ='vl_form'";
                    $configFormResult = $db->rawQuery($configFormQuery);
                    $data['user'] = $admin;
                    $data['form'] = $configFormResult[0]['value'];
                    $data['api_token'] = $randomString;
                    $data['appMenuName'] = $general->getGlobalConfig('app_menu_name');;
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
                'message' => 'Please check your login credentials',
                'timestamp' => $general->getDateTime()
            );
        }
    } else {
        $payload = array(
            'status' => 0,
            'message' => 'Please enter the credentials',
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
