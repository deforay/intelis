<?php

use App\Models\General;
use App\Models\Users;
use App\Utilities\DateUtils;
use App\Utilities\ImageResize;
use GuzzleHttp\Client;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$userDb = new Users();
$general = new General();


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
            $data['api_token_generated_datetime'] = DateUtils::getCurrentDateTime();
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
                mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature", 0777, true);
            }
            $extension = strtolower(pathinfo(UPLOAD_PATH . DIRECTORY_SEPARATOR . $_FILES['userSignature']['name'], PATHINFO_EXTENSION));
            $imageName = "usign-" . $userId . "." . $extension;
            $signatureImagePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature" . DIRECTORY_SEPARATOR . $imageName;
            if (move_uploaded_file($_FILES["userSignature"]["tmp_name"], $signatureImagePath)) {
                $resizeObj = new ImageResize($signatureImagePath);
                $resizeObj->resizeToWidth(100);
                $resizeObj->save($signatureImagePath);
                $data['user_signature'] = $imageName;
            }
        }

        if (isset($_POST['password']) && trim($_POST['password']) != "") {

            /* Recency cross login block */
            if (SYSTEM_CONFIG['recency']['crosslogin'] && !empty(SYSTEM_CONFIG['recency']['url'])) {
                $client = new Client();
                $url = rtrim(SYSTEM_CONFIG['recency']['url'], "/");
                $newCrossLoginPassword = General::encrypt($_POST['password'], base64_decode(SYSTEM_CONFIG['recency']['crossloginSalt']));
                $result = $client->post($url . '/api/update-password', [
                    'form_params' => [
                        'u' => $_POST['loginId'],
                        't' => $newCrossLoginPassword
                    ]
                ]);
                $response = json_decode($result->getBody()->getContents());
                if ($response->status == 'fail') {
                    error_log('Recency profile not updated! for the user ' . $_POST['userName']);
                }
            }

            $password = $userDb->passwordHash($_POST['password']);
            $data['password'] = $password;
            $data['hash_algorithm'] = 'phb';
            $data['force_password_reset'] = 1;
        }

        $db = $db->where('user_id', $userId);
        $db->update("user_details", $data);

        // Deleting old mapping of user to facilities
        $db = $db->where('user_id', $userId);
        $delId = $db->delete("user_facility_map");

        if ($userId != '' && trim($_POST['selectedFacility']) != '') {
            $selectedFacility = explode(",", $_POST['selectedFacility']);
            $uniqueFacilityId = array_unique($selectedFacility);
            for ($j = 0; $j <= count($uniqueFacilityId); $j++) {
                if (isset($uniqueFacilityId[$j])) {
                    $data = array(
                        'facility_id' => $uniqueFacilityId[$j],
                        'user_id' => $userId,
                    );
                    $db->insert("user_facility_map", $data);
                }
            }
        }
        $_SESSION['alertMsg'] = _("User updated successfully");

        $systemType = $general->getSystemConfig('sc_user_type');
        if (!empty(SYSTEM_CONFIG['remoteURL']) && $systemType == 'vluser') {
            // $nUser = [];
            // $_POST['userId'] = $userId;
            // $nUser['userName'] = $_POST['userName']; 
            // $nUser['email'] = $_POST['email']; 
            // $nUser['phoneNo'] = $_POST['phoneNo']; 
            // $nUser['interfaceUserName'] = $_POST['interfaceUserName']; 
            // $nUser['loginId'] = null; // We don't want to unintentionally end up creating admin users on VLSTS
            // $nUser['password'] = $general->generateRandomString(); // We don't want to unintentionally end up creating admin users on VLSTS
            // $nUser['hash_algorithm'] = 'phb'; // We don't want to unintentionally end up creating admin users on VLSTS
            // $nUser['role'] = 0; // We don't want to unintentionally end up creating admin users on VLSTS
            // $nUser['status'] = 'inactive';
            // $nUser['userId'] = base64_encode($data['user_id']);

            $_POST['userId'] = $userId;
            $_POST['loginId'] = null; // We don't want to unintentionally end up creating admin users on VLSTS
            $_POST['password'] = $general->generateRandomString(); // We don't want to unintentionally end up creating admin users on VLSTS
            $_POST['hashAlgorithm'] = 'phb'; // We don't want to unintentionally end up creating admin users on VLSTS
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
            curl_setopt($ch, CURLOPT_POSTFIELDS, ($post));
            $result = curl_exec($ch);
            curl_close($ch);
            $deResult = json_decode($result, true);
        }
    }


    //Add event log
    $eventType = 'user-update';
    $action = $_SESSION['userName'] . ' updated details for user ' . $_POST['userName'];
    $resource = 'user';

    $general->activityLog($eventType, $action, $resource);

    header("Location:users.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
