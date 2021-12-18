<?php

header('Content-Type: application/json');
// require_once('../startup.php');

session_unset(); // no need of session in json response
$general = new \Vlsm\Models\General();

$apiKey = isset($_POST['x-api-key']) && !empty($_POST['x-api-key']) ? $_POST['x-api-key'] : null;

if (!$_POST['post']) {
    $response = array(
        'status' => 'failed',
        'data' => 'Missing post data',
        'timestamp' => $general->getDateTime()
    );
    echo json_encode($response);
    exit(0);
} else {
    $post = json_decode($_POST['post']);
}
$userId = !empty($post->userId) ? base64_decode($post->userId) : null;

if (!$apiKey) {
    $response = array(
        'status' => 'failed',
        'data' => 'API Key invalid',
        'timestamp' => $general->getDateTime()
    );
    echo json_encode($response);
    exit(0);
}

try {
    $aRow = null;
    if (!empty($userId) || !empty($post->email)) {
        if (!empty($userId)) {
            $db->where("user_id", $userId);
        }
        if (!empty($post->loginId)) {
            $db->where("login_id", $post->loginId);
        }
        $aRow = $db->getOne("user_details");
    }

    if (isset($_FILES['sign']['name']) && $_FILES['sign']['name'] != "") {
        if (!file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature") && !is_dir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature")) {
            mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature", 0777);
        }
        $extension = strtolower(pathinfo(UPLOAD_PATH . DIRECTORY_SEPARATOR . $_FILES['sign']['name'], PATHINFO_EXTENSION));
        $string = $general->generateRandomString(10) . ".";
        $imageName = "usign-" . $string . $extension;

        $signatureImagePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature" . DIRECTORY_SEPARATOR . $imageName;
        if (move_uploaded_file($_FILES["sign"]["tmp_name"], $signatureImagePath)) {
            $resizeObj = new \Vlsm\Helpers\ImageResize($signatureImagePath);
            $resizeObj->resizeToWidth(100);
            $resizeObj->save($signatureImagePath);
            $data['user_signature'] = $imageName;
        }
    }


    $data = array(
        'user_id' => !empty($userId) ? $userId : $general->generateUUID(),
        'user_name' => $post->userName,
        'email' => $post->email,
        'login_id' => $post->loginId,
        'phone_number' => $post->phoneNo,
        'user_signature' => $imageName
    );

    if (!empty($post->status)) {
        $data['status'] = $post->status;
    }
    if (!empty($post->role)) {
        $data['role_id'] = $post->role;
    }
    if (!empty($post->password)) {
        $data['password'] = sha1($post->password . $systemConfig['passwordSalt']);
    }

    if (empty($userId) || empty($aRow) || $aRow == false) {
        $id = $db->insert("user_details", $data);
    } else {
        $userId = $data['user_id'] = $aRow['user_id'];
        $db->update("user_details", $data);
        $db = $db->where('user_id', $data['user_id']);
        $delId = $db->delete("vl_user_facility_map");
    }
    if ($id > 0 && trim($post->selectedFacility) != '') {
        if ($id > 0 && trim($post->selectedFacility) != '') {
            $selectedFacility = explode(",", $post->selectedFacility);
            $uniqueFacilityId = array_unique($selectedFacility);
            for ($j = 0; $j <= count($selectedFacility); $j++) {
                if (isset($uniqueFacilityId[$j])) {
                    $data = array(
                        'facility_id' => $selectedFacility[$j],
                        'user_id' => $data['user_id'],
                    );
                    $db->insert("vl_user_facility_map", $data);
                }
            }
        }
    }
    $payload = array(
        'status' => 'success',
        'timestamp' => $general->getDateTime()
    );

    echo json_encode($payload);
    exit(0);
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
    exit(0);
}
