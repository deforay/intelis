<?php

header('Content-Type: application/json');

session_unset(); // no need of session in json response
$general = new \Vlsm\Models\General();
$userDb = new \Vlsm\Models\Users();
$app = new \Vlsm\Models\App();
$jsonResponse = file_get_contents('php://input');

try {
    ini_set('memory_limit', -1);
    $auth = $general->getHeader('Authorization');
    if (!empty($auth)) {
        $authToken = str_replace("Bearer ", "", $auth);
        /* Check if API token exists */
        $user = $userDb->getAuthToken($authToken);
        // If authentication fails then do not proceed
        if (empty($user) || empty($user['user_id'])) {
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
    } else if (!empty($_REQUEST)) {
        $decode = $_REQUEST;
        $decode['post'] = json_decode($decode['post'], true);
    } else {
        throw new Exception("Invalid request. Please check your request parameters.");
    }
    $apiKey = isset($decode['x-api-key']) && !empty($decode['x-api-key']) ? $decode['x-api-key'] : null;

    if (!$decode['post']) {
        throw new Exception("Invalid request. Please check your request parameters.");
    } else {
        $post = ($decode['post']);
    }
    $userId = !empty($post['userId']) ? base64_decode($post['userId']) : null;
    if (!$apiKey) {
        throw new Exception("Invalid API Key. Please check your request parameters.");
    }
    $aRow = null;
    if (!empty($userId) || !empty($post['email'])) {
        if (!empty($userId)) {
            $db->where("user_id", $userId);
        } else if (!empty($post['email'])) {
            $db->where("email", $post['email']);
        }
        $aRow = $db->getOne("user_details");
    }

    $data = array(
        'user_id' => (!empty($userId) && $userId != "") ? $userId : $general->generateUUID(),
        'user_name' => $post['userName'],
        'email' => $post['email'],
        'interface_user_name' => $post['interfaceUserName'],
        'login_id' => $post['loginId'],
        'phone_number' => $post['phoneNo'],
        'user_signature' => !empty($imageName) ? $imageName : null
    );

    if (!empty($post['status'])) {
        $data['status'] = $post['status'];
    }

    if (!empty($post['password'])) {
        $data['password'] = sha1($post['password'] . $systemConfig['passwordSalt']);
    }
    if (!empty($post['role'])) {
        $data['role_id'] = $post['role'];
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
                    $data = array(
                        'facility_id' => $selectedFacility[$j],
                        'user_id' => $data['user_id'],
                    );
                    $db->insert("user_facility_map", $data);
                }
            }
        }
    }

    $payload = array(
        'status' => 'success',
        'timestamp' => time(),
    );

    error_log($db->getLastError());
    echo json_encode($payload);
} catch (Exception $exc) {
    $payload = array(
        'status' => 'failed',
        'error' => $exc->getMessage(),
        'timestamp' => time(),
    );

    echo json_encode($payload);
    error_log("Save User Profile API : " . $exc->getMessage());
    error_log($exc->getTraceAsString());
}
exit(0);
