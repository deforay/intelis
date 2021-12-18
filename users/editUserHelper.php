<?php
ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


$general = new \Vlsm\Models\General();
$tableName = "user_details";
$tableName2 = "vl_user_facility_map";
$userId = base64_decode($_POST['userId']);

try {
    if (trim($_POST['userName']) != '' && trim($_POST['loginId']) != '' && ($_POST['role']) != '') {


        if (!empty($_POST['interfaceUserName'])) {
            
            $_POST['interfaceUserName'] = json_encode(array_map('trim', explode(",", $_POST['interfaceUserName'])));
        } else {
            $_POST['interfaceUserName'] = null;
        }

        $data = array(
            'user_name'     => $_POST['userName'],
            'interface_user_name'     => $_POST['interfaceUserName'],
            'email'         => $_POST['email'],
            'phone_number'  => $_POST['phoneNo'],
            'login_id'      => $_POST['loginId'],
            'role_id'       => $_POST['role'],
            'status'        => $_POST['status'],
            'app_access'    => $_POST['appAccessable']
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
            $string = $general->generateRandomString(10) . ".";
            $imageName = "usign-" . $string . $extension;
            $signatureImagePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature" . DIRECTORY_SEPARATOR . $imageName;
            if (move_uploaded_file($_FILES["userSignature"]["tmp_name"], $signatureImagePath)) {
                $resizeObj = new \Vlsm\Helpers\ImageResize($signatureImagePath);
                $resizeObj->resizeToWidth(100);
                $resizeObj->save($signatureImagePath);
                $data['user_signature'] = $imageName;
            }
        }

        if (isset($_POST['password']) && trim($_POST['password']) != "") {
            if ($recencyConfig['crosslogin'] && !empty($recencyConfig['url'])) {
                $client = new \GuzzleHttp\Client();
                $url = rtrim($recencyConfig['url'], "/");
                $result = $client->post($url . '/api/update-password', [
                    'form_params' => [
                        'u' => $_POST['loginId'],
                        't' => sha1($_POST['password'] . $systemConfig['passwordSalt'])
                    ]
                ]);
                $response = json_decode($result->getBody()->getContents());
                if ($response->status == 'fail') {
                    error_log('Recency profile not updated! for the user ' . $_POST['userName']);
                }
            }
            $data['password'] = sha1($_POST['password'] . $systemConfig['passwordSalt']);
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
        $_SESSION['alertMsg'] = "User saved successfully!";

        $userType = $general->getSystemConfig('sc_user_type');
        if (isset($systemConfig['remoteURL']) && $systemConfig['remoteURL'] != "" && $userType == 'vluser') {
            // We don't want to end up creating admin users on VLSTS
            $_POST['role'] = ($_POST['role'] == 1 ) ? 2 : $_POST['role']; 
            $_POST['password'] = sha1($general->generateRandomString() . $systemConfig['passwordSalt']);
            $_POST['status'] = 'inactive';
            $apiUrl = $systemConfig['remoteURL'] . "/api/v1.1/user/save-user-profile.php";
            $post = array('post' => json_encode($_POST), 'sign' => (isset($signatureImagePath) && $signatureImagePath != "") ? curl_file_create($signatureImagePath) : null, 'x-api-key' => $general->generateRandomString(18));

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiUrl);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
            $result = curl_exec($ch);
            curl_close($ch);

            $deResult = json_decode($result, true);
            // echo "<pre>";print_r($deResult);die;
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
