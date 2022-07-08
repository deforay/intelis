<?php

header('Content-Type: application/json');

use Aura\Filter\FilterFactory;

session_unset(); // no need of session in json response
$general = new \Vlsm\Models\General();
$userDb = new \Vlsm\Models\Users();
$app = new \Vlsm\Models\App();
$jsonResponse = file_get_contents('php://input');

// error_log("------ USER API START-----");
// error_log($jsonResponse);
// error_log("------ USER API END -----");

try {
    ini_set('memory_limit', -1);
    $auth = $general->getHeader('Authorization');
    if (!empty($auth)) {
        $authToken = str_replace("Bearer ", "", $auth);
        /* Check if API token exists */
        $user = $userDb->getAuthToken($authToken);
        // If authentication fails then do not proceed
        if (!isset($user) || empty($user) || empty($user['user_id'])) {
            $response = array(
                'status' => 'failed',
                'timestamp' => time(),
                'error' => 'Bearer Token Invalid',
                'data' => array()
            );
            http_response_code(401);
            echo json_encode($response);
            exit(0);
        }
    }
    if (!empty($jsonResponse)) {
        $decode = json_decode($jsonResponse, true);
        http_response_code(501);
        exit(0);
    } else if (!empty($_REQUEST)) {
        $decode = $_REQUEST;
        $decode['post'] = json_decode($decode['post'], true);
    } else {
        throw new Exception("Invalid request. Please check your request parameters.");
    }
    $apiKey = isset($decode['x-api-key']) && !empty($decode['x-api-key']) ? $decode['x-api-key'] : null;

    if ((empty($decode['post']) || $decode['post'] === false) && !isset($user)) {
        throw new Exception("Invalid request. Please check your request parameters.");
    } else {
        if (isset($user)) {
            $post = $decode;
        } else {
            $post = $decode['post'];
        }
    }

    $filter_factory = new FilterFactory();
    $filter = $filter_factory->newSubjectFilter();

    $filter->validate('userId')->isNotBlank();
    $filter->validate('email')->is('email');
    if (!isset($user)) {
        $filter->sanitize('interfaceUserName')->to('regex', '/^[a-zA-Z0-9_]+$/', $post['interfaceUserName']);
        $filter->sanitize('userId')->to('regex', '/^[a-zA-Z0-9-]+$/', '');
        $filter->sanitize('loginId')->to('regex', '/^[a-zA-Z0-9_]+$/', $post['loginId']);
    } else {
        $filter->sanitize('interfaceUserName')->to('regex', '/^[a-zA-Z0-9_]+$/', $post['interfaceUserName']);
        $filter->sanitize('userId')->to('regex', '/^[a-zA-Z0-9-]+$/', '');
        $filter->sanitize('loginId')->to('regex', '/^[a-zA-Z0-9_]+$/', $post['loginId']);
    }
    $filter->sanitize('password')->to('alnum');
    $filter->sanitize('role')->to('int');
    // filter the object and see if there were failures
    $success = $filter->apply($post);
    if (!$success) {
        // get the failures
        $failures = $filter->getFailures();
        error_log($failures->getMessages());
        throw new Exception("Invalid request. Please check your request parameters.");
    }
    if (!isset($user)) {
        if (!$apiKey) {
            throw new Exception("Invalid API Key. Please check your request parameters.");
        }
        $userId = !empty($post['userId']) ? base64_decode($db->escape($post['userId'])) : null;
    } else {
        $userId = !empty($post['userId']) ? $db->escape($post['userId']) : null;
    }

    $aRow = null;
    if (!empty($userId) || !empty($post['email'])) {
        if (!empty($userId)) {
            $db->where("user_id", $userId);
        } else if (!empty($post['email'])) {
            $db->where("email", $db->escape($post['email']));
        }
        $aRow = $db->getOne("user_details");
    }
    $data = array(
        'user_id' => (!empty($userId) && $userId != "") ? $userId : $general->generateUUID(),
        'user_name' => $db->escape($post['userName']),
        'email' => $db->escape($post['email']),
        'interface_user_name' => json_encode(array_map('trim', explode(",", $db->escape($post['interfaceUserName'])))),
        'phone_number' => $db->escape($post['phoneNo'])
    );

    if (!empty($post['status'])) {
        $data['status'] = $post['status'];
    }

    if (!empty($post['password'])) {
        /* Check hash login id exist */
        $sha1protect = false;
        $hashCheckQuery = "SELECT `user_id`, `login_id`, `hash_algorithm` FROM user_details WHERE `user_id` = ?";
        $hashCheck = $db->rawQueryOne($hashCheckQuery, array($userId));
        if (isset($hashCheck) && !empty($hashCheck['user_id']) && !empty($hashCheck['hash_algorithm'])) {
            if ($hashCheck['hash_algorithm'] == 'sha1') {
                $data['password'] = sha1($db->escape($post['password']) . SYSTEM_CONFIG['passwordSalt']);
                $sha1protect = true;
            }
            if ($hashCheck['hash_algorithm'] == 'phb') {
                $password = $user->passwordHash($db->escape($_POST['password']), $hashCheck['user_id']);
                if (!password_verify($db->escape($post['password']), $hashCheck['password'])) {
                    $user->userHistoryLog($username, $loginStatus = 'failed');
                    $payload = array(
                        'status' => 'failed',
                        'message' => 'Invalid password!',
                        'timestamp' => time(),
                    );
                    exit(0);
                }
            }
        } else {
            $data['password'] = sha1($db->escape($post['password']) . SYSTEM_CONFIG['passwordSalt']);
        }
    }
    if (!empty($post['role'])) {
        $data['role_id'] =  $db->escape($post['role']);
    }
    if (!empty($post['login_id'])) {
        $data['login_id'] =  $db->escape($post['login_id']);
    }

    if (isset($_FILES['sign']['name']) && $_FILES['sign']['name'] != "") {
        if (!file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature") && !is_dir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature")) {
            mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature", 0777);
        }
        $extension = strtolower(pathinfo(UPLOAD_PATH . DIRECTORY_SEPARATOR . $_FILES['sign']['name'], PATHINFO_EXTENSION));
        $imageName = "usign-" . $userId . "." . $extension;

        $signatureImagePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature" . DIRECTORY_SEPARATOR . $imageName;
        if (move_uploaded_file($_FILES["sign"]["tmp_name"], $signatureImagePath)) {
            $resizeObj = new \Vlsm\Helpers\ImageResize($signatureImagePath);
            $resizeObj->resizeToWidth(100);
            $resizeObj->save($signatureImagePath);
            $data['user_signature'] = $imageName;
        }
    }
    $id = 0;
    if (isset($aRow['user_id']) && $aRow['user_id'] != "") {
        $db = $db->where('user_id', $aRow['user_id']);
        $id = $db->update("user_details", $data);

        /* Update Phb hash password */
        if ($sha1protect) {
            $password = $userDb->passwordHash($db->escape($post['password']), $aRow['user_id']);
            $db = $db->where('user_id', $aRow['user_id']);
            $db->update('user_details', array('password' => $password, 'hash_algorithm' => 'phb'));
        }
    } else {
        $data['status'] = 'inactive';
        $id = $db->insert("user_details", $data);
    }

    if ($id > 0 && trim($post['selectedFacility']) != '') {
        if ($id > 0 && trim($post['selectedFacility']) != '') {
            $db = $db->where('user_id', $data['user_id']);
            $delId = $db->delete("user_facility_map");
            $selectedFacility = explode(",", $post['selectedFacility']);
            $uniqueFacilityId = array_unique($selectedFacility);
            for ($j = 0; $j <= count($selectedFacility); $j++) {
                if (isset($uniqueFacilityId[$j])) {
                    $insertData = array(
                        'facility_id' => $selectedFacility[$j],
                        'user_id' => $data['user_id'],
                    );
                    $db->insert("user_facility_map", $insertData);
                }
            }
        }
    }
    if ($id > 0) {
        $payload = array(
            'status' => 'success',
            'timestamp' => time(),
        );
    } else {
        $payload = array(
            'status' => 'failed',
            'message' => 'Something went wrong!',
            'timestamp' => time(),
        );
    }

    echo json_encode($payload);
} catch (Exception $exc) {
    $payload = array(
        'status' => 'failed',
        'error' => $exc->getMessage(),
        'timestamp' => time(),
    );

    echo json_encode($payload);
    error_log(print_r($data['post'], TRUE));

    error_log("Save User Profile API : " . $exc->getMessage());
    error_log($exc->getTraceAsString());
}
$trackId = $app->addApiTracking($data['user_id'], count($data), 'save-user', 'common', $_SERVER['REQUEST_URI'], json_encode($decode), 'REST');
exit(0);
