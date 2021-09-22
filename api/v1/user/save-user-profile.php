<?php

header('Content-Type: application/json');
// require_once('../startup.php');

include_once APPLICATION_PATH . '/includes/ImageResize.php';


session_unset(); // no need of session in json response
$general = new \Vlsm\Models\General($db);

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
$userId = base64_decode($post->userId);
/* echo "<pre>";
print_r($post->userId);
print_r($_FILES['sign']);
die; */
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
    $tableName = "user_details";
    $tableName2 = "vl_user_facility_map";
    $userQuery = "SELECT * from user_details where (user_id='" . $userId . "' OR email = '" . $post->email . "')";
    $aRow = $db->rawQuery($userQuery);

    if (isset($_FILES['sign']['name']) && $_FILES['sign']['name'] != "") {
        if (!file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature") && !is_dir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature")) {
            mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature", 0777);
        }
        $extension = strtolower(pathinfo(UPLOAD_PATH . DIRECTORY_SEPARATOR . $_FILES['sign']['name'], PATHINFO_EXTENSION));
        $string = $general->generateRandomString(10) . ".";
        $imageName = "usign-" . $string . $extension;

        $signatureImagePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature" . DIRECTORY_SEPARATOR . $imageName;
        if (move_uploaded_file($_FILES["sign"]["tmp_name"], $signatureImagePath)) {
            $resizeObj = new ImageResize($signatureImagePath);
            $resizeObj->resizeImage(100, 100, 'auto');
            $resizeObj->saveImage($signatureImagePath, 100);
            $data['user_signature'] = $imageName;
        }
    }

    $password = sha1($post->password . $systemConfig['passwordSalt']);
    $data = array(
        'user_id' => $general->generateUUID(),
        'user_name' => $post->userName,
        'email' => $post->email,
        'login_id' => $post->loginId,
        'phone_number' => $post->phoneNo,
        'password' => $password,
        'role_id' => $post->role,
        'status' => 'active',
        'user_signature' => $imageName
    );

    if ((!isset($userId) || $userId == '') || $aRow) {
        $id = $db->insert($tableName, $data);
    } else {
        $userId = $aRow['user_id'];
        $db->update($tableName, $data);
        $db = $db->where('user_id', $userId);
        $delId = $db->delete($tableName2);
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
                    $db->insert($tableName2, $data);
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
