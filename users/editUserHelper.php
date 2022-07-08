<?php
ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$userDb = new \Vlsm\Models\Users();
$general = new \Vlsm\Models\General();
$tableName = "user_details";
$tableName2 = "user_facility_map";
$userId = base64_decode($_POST['userId']);

try {
    if (trim($_POST['userName']) != '' && trim($_POST['loginId']) != '' && ($_POST['role']) != '') {

        $data = array(
            'user_name'             => $_POST['userName'],
            'interface_user_name'   => (!empty($_POST['interfaceUserName']) && $_POST['interfaceUserName'] != "") ? json_encode(array_map('trim', explode(",", $_POST['interfaceUserName']))) : null,
            'email'                 => $_POST['email'],
            'phone_number'          => $_POST['phoneNo'],
            'login_id'              => $_POST['loginId'],
            'role_id'               => $_POST['role'],
            'status'                => $_POST['status'],
            'app_access'            => $_POST['appAccessable']
        );
        if (isset($_POST['authToken']) && !empty($_POST['authToken'])) {
            $data['api_token'] = $_POST['authToken'];
            // $data['testing_user'] = $_POST['testingUser'];
            $data['api_token_generated_datetime'] = $general->getDateTime();
        }
        if (isset($_POST['removedSignatureImage']) && trim($_POST['removedSignatureImage']) != "") {
            $signatureImagePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature" . DIRECTORY_SEPARATOR . $_POST['removedSignatureImage'];
            if (file_exists($signatureImagePath)) {
                unlink($signatureImagePath);
            }
            $data['user_signature'] = null;
        }

        if (isset($_FILES['userSignature']['name']) && $_FILES['userSignature']['name'] != "") {
            if (!file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature") && !is_dir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature")) {
                mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature");
            }
            $extension = strtolower(pathinfo(UPLOAD_PATH . DIRECTORY_SEPARATOR . $_FILES['userSignature']['name'], PATHINFO_EXTENSION));
            $imageName = "usign-" . $userId . "." . $extension;
            $signatureImagePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature" . DIRECTORY_SEPARATOR . $imageName;
            if (move_uploaded_file($_FILES["userSignature"]["tmp_name"], $signatureImagePath)) {
                $resizeObj = new \Vlsm\Helpers\ImageResize($signatureImagePath);
                $resizeObj->resizeToWidth(100);
                $resizeObj->save($signatureImagePath);
                $data['user_signature'] = $imageName;
            }
        }

        if (isset($_POST['password']) && trim($_POST['password']) != "") {
            /* Check hash login id exist */
            $password = sha1($password . SYSTEM_CONFIG['passwordSalt']);
            $sha1protect = false;
            $hashCheckQuery = "SELECT `user_id`, `login_id`, `hash_algorithm` FROM user_details WHERE `login_id` = ?";
            $hashCheck = $db->rawQueryOne($hashCheckQuery, array($db->escape($_POST['userName'])));
            if (isset($hashCheck) && !empty($hashCheck['user_id']) && !empty($hashCheck['hash_algorithm'])) {
                if ($hashCheck['hash_algorithm'] == 'sha1') {
                    $password = sha1($password . SYSTEM_CONFIG['passwordSalt']);
                    $sha1protect = true;
                }
                if ($hashCheck['hash_algorithm'] == 'phb') {
                    $password = $userDb->passwordHash($db->escape($_POST['password']), $hashCheck['user_id']);
                    if (!password_verify($db->escape($_POST['password']), $hashCheck['password'])) {
                        $_SESSION['alertMsg'] = _("Invalid password!");
                        header("location:users.php");
                    }
                }
            } else {
                $password = sha1($password . SYSTEM_CONFIG['passwordSalt']);
            }

            /* Recency cross login block */
            if (SYSTEM_CONFIG['recency']['crosslogin'] && !empty(SYSTEM_CONFIG['recency']['url'])) {
                $client = new \GuzzleHttp\Client();
                $url = rtrim(SYSTEM_CONFIG['recency']['url'], "/");
                $result = $client->post($url . '/api/update-password', [
                    'form_params' => [
                        'u' => $_POST['loginId'],
                        't' => $password
                    ]
                ]);
                $response = json_decode($result->getBody()->getContents());
                if ($response->status == 'fail') {
                    error_log('Recency profile not updated! for the user ' . $_POST['userName']);
                }
            }
            $data['password'] = $password;
            /* Update Phb hash password */
            if ($sha1protect) {
                $data['password'] = $userDb->passwordHash($db->escape($_POST['password']), $userId);
                $data['hash_algorithm'] = 'phb';
            }
            $data['force_password_reset'] = 1;
        }

        $db = $db->where('user_id', $userId);
        //print_r($data);die;
        $db->update($tableName, $data);
        $db = $db->where('user_id', $userId);
        $delId = $db->delete($tableName2);
        if ($userId != '' && trim($_POST['selectedFacility']) != '') {
            $selectedFacility = explode(",", $_POST['selectedFacility']);
            $uniqueFacilityId = array_unique($selectedFacility);
            for ($j = 0; $j <= count($uniqueFacilityId); $j++) {
                if (isset($uniqueFacilityId[$j])) {
                    $data = array(
                        'facility_id' => $uniqueFacilityId[$j],
                        'user_id' => $userId,
                    );
                    $db->insert($tableName2, $data);
                }
            }
        }
        $_SESSION['alertMsg'] = _("User saved successfully!");

        $userType = $general->getSystemConfig('sc_user_type');
        if (!empty(SYSTEM_CONFIG['remoteURL']) && $userType == 'vluser') {
            $_POST['login_id'] = null; // We don't want to unintentionally end up creating admin users on VLSTS
            $_POST['password'] = $general->generateRandomString(); // We don't want to unintentionally end up creating admin users on VLSTS
            $_POST['role'] = 0; // We don't want to unintentionally end up creating admin users on VLSTS
            $_POST['status'] = 'inactive'; // so that we can retain whatever status is on server
            $apiUrl = SYSTEM_CONFIG['remoteURL'] . "/api/v1.1/user/save-user-profile.php";
            $post = array(
                'post' => json_encode($_POST),
                'sign' => (isset($signatureImagePath) && $signatureImagePath != "") ? curl_file_create($signatureImagePath) : null,
                'x-api-key' => $general->generateRandomString(18)
            );

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiUrl);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, ($post));
            $result = curl_exec($ch);
            curl_close($ch);

            $deResult = json_decode($result, true);
        }
    }


    //Add event log
    $eventType = 'user-update';
    $action = ucwords($_SESSION['userName']) . ' updated details for user ' . $_POST['userName'];
    $resource = 'user';

    $general->activityLog($eventType, $action, $resource);

    header("location:users.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
