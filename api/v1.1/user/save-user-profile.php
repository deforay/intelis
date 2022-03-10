<?php

header('Content-Type: application/json');

session_unset(); // no need of session in json response
$general = new \Vlsm\Models\General();
$jsonResponse = file_get_contents('php://input');

try {
    if (!empty($jsonResponse)) {
        $decode = json_decode($jsonResponse, true);
    } else if (!empty($_REQUEST)) {
        $decode = $_REQUEST;
        $decode['post'] = json_decode($decode['post'], true);
    } else {
        throw new Exception("Invalid request. Please check your request parameters.");
    }

    if (isset($decode['api-type']) && $decode['api-type'] == "sync") {
        $postData = $decode['result'];
        foreach ($postData as $post) {
            $userId = $post['user_id'];
            $aRow = null;
            if (!empty($userId) || !empty($post['email'])) {
                if (!empty($userId)) {
                    $db->where("user_id", $userId);
                }
                if (!empty($post['email'])) {
                    $db->where("login_id", $post['email']);
                }
                $aRow = $db->getOne("user_details");
            }


            $fileUpload = "failed";
            if (isset($post['user_signature']) && $post['user_signature'] != "") {
                $remoteFileUrl = $systemConfig['remoteURL'] . "/uploads/users-signature" . DIRECTORY_SEPARATOR . $post['user_signature'];
                $localFilePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature" . DIRECTORY_SEPARATOR . $post['user_signature'];
                if (file_put_contents($localFilePath, file_get_contents($remoteFileUrl))) {
                    $fileUpload = "success";
                }
            }

            $data = $post;
            $data['data_sync'] = 1;
            unset($data['sync']);
            unset($data['selectedFacility']);

            $id = 0;
            if (empty($userId) || empty($aRow) || $aRow == false) {
                $id = $db->insert("user_details", $data);
            } else {
                $db = $db->where('user_id', $data['user_id']);
                $id = $db->update("user_details", $data);
            }
            if ($id > 0 && trim($post['selectedFacility']) != '') {
                $db = $db->where('user_id', $data['user_id']);
                $delId = $db->delete("vl_user_facility_map");

                $selectedFacility = explode(",", $post['selectedFacility']);
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
    } else {

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

        if (isset($_FILES['sign']['name']) && $_FILES['sign']['name'] != "") {
            if (!file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature") && !is_dir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature")) {
                mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature", 0777);
            }
            $extension = strtolower(pathinfo(UPLOAD_PATH . DIRECTORY_SEPARATOR . $_FILES['sign']['name'], PATHINFO_EXTENSION));
            $string = $userId . ".";
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
                $delId = $db->delete("vl_user_facility_map");
                $selectedFacility = explode(",", $post['selectedFacility']);
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
