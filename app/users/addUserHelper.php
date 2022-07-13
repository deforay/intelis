<?php
ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$userDb = new \Vlsm\Models\Users();
$general = new \Vlsm\Models\General();
// 
// require_once(APPLICATION_PATH . '/header.php');
$tableName = "user_details";
$tableName2 = "user_facility_map";
try {
    if (trim($_POST['userName']) != '' && trim($_POST['loginId']) != '' && ($_POST['role']) != '' && ($_POST['password']) != '') {
        $data = array(
            'user_id'               => $general->generateUUID(),
            'user_name'             => $_POST['userName'],
            'interface_user_name'   => (!empty($_POST['interfaceUserName']) && $_POST['interfaceUserName'] != "") ? json_encode(array_map('trim', explode(",", $_POST['interfaceUserName']))) : null,
            'email'                 => $_POST['email'],
            'login_id'              => $_POST['loginId'],
            'phone_number'          => $_POST['phoneNo'],
            'role_id'               => $_POST['role'],
            'status'                => 'active',
            'app_access'            => $_POST['appAccessable'],
            'user_signature'        => $imageName,
            'force_password_reset'  => 1
        );

        $password = $userDb->passwordHash($db->escape($_POST['password']));
        $data['password'] = $password;
        $data['hash_algorithm'] = 'phb';

        if (isset($_FILES['userSignature']['name']) && $_FILES['userSignature']['name'] != "") {
            if (!file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature") && !is_dir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature")) {
                mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature");
            }
            $extension = strtolower(pathinfo(UPLOAD_PATH . DIRECTORY_SEPARATOR . $_FILES['userSignature']['name'], PATHINFO_EXTENSION));
            $imageName = "usign-" . $data['user_id'] . "." . $extension;
            $signatureImagePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature" . DIRECTORY_SEPARATOR . $imageName;
            if (move_uploaded_file($_FILES["userSignature"]["tmp_name"], $signatureImagePath)) {
                $resizeObj = new \Vlsm\Helpers\ImageResize($signatureImagePath);
                $resizeObj->resizeToWidth(100);
                $resizeObj->save($signatureImagePath);
                $data['user_signature'] = $imageName;
            }
        }
        if (isset($_POST['authToken']) && !empty($_POST['authToken'])) {
            $data['api_token'] = $_POST['authToken'];
            // $data['testing_user'] = $_POST['testingUser'];
            $data['api_token_generated_datetime'] = $general->getDateTime();
        }

        $id = $db->insert($tableName, $data);


        if ($id > 0 && trim($_POST['selectedFacility']) != '') {
            if ($id > 0 && trim($_POST['selectedFacility']) != '') {
                $selectedFacility = explode(",", $_POST['selectedFacility']);
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

        $_SESSION['alertMsg'] = _("User saved successfully!");
    }
    $userType = $general->getSystemConfig('sc_user_type');
    if (isset(SYSTEM_CONFIG['remoteURL']) && SYSTEM_CONFIG['remoteURL'] != "" && $userType == 'vluser') {
        $_POST['login_id'] = null; // We don't want to unintentionally end up creating admin users on VLSTS
        $_POST['password'] = $general->generateRandomString(); // We don't want to unintentionally end up creating admin users on VLSTS
        $_POST['hash_algorithm'] = 'phb'; // We don't want to unintentionally end up creating admin users on VLSTS
        $_POST['role'] = 0; // We don't want to unintentionally end up creating admin users on VLSTS
        $_POST['status'] = 'inactive';
        $_POST['userId'] = base64_encode($data['user_id']);
        $apiUrl = SYSTEM_CONFIG['remoteURL'] . "/api/v1.1/user/save-user-profile.php";
        $post = array(
            'post' => json_encode($_POST),
            'sign' => (isset($signatureImagePath) && $signatureImagePath != "") ? curl_file_create($signatureImagePath) : null,
            'x-api-key' => $general->generateRandomString(18)
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        $result = curl_exec($ch);
        curl_close($ch);
        $deResult = json_decode($result, true);
    }
    //Add event log
    $eventType = 'user-add';
    $action = ucwords($_SESSION['userName']) . ' added user ' . $_POST['userName'];
    $resource = 'user';

    $general->activityLog($eventType, $action, $resource);

    header("location:users.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
