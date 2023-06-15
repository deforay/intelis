<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Utilities\ImageResizeUtility;
use GuzzleHttp\Client;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$usersService = ContainerRegistry::get(UsersService::class);
/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = $GLOBALS['request'];
$_POST = $request->getParsedBody();

$uploadedFiles = $request->getUploadedFiles();


$userId = base64_decode($_POST['userId']);

$signatureImagePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature";

if (!file_exists($signatureImagePath) && !is_dir($signatureImagePath)) {
    mkdir($signatureImagePath, 0777, true);
}

$signatureImagePath = realpath($signatureImagePath);

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
        if (!empty($_POST['authToken'])) {
            $data['api_token'] = $_POST['authToken'];
            $data['api_token_generated_datetime'] = DateUtility::getCurrentDateTime();
        } elseif (!empty($_POST['appAccessable']) && $_POST['appAccessable'] == 'yes') {
            $data['api_token'] = $usersService->generateAuthToken();
            $data['api_token_generated_datetime'] = DateUtility::getCurrentDateTime();
        }
        if (isset($_POST['removedSignatureImage']) && trim($_POST['removedSignatureImage']) != "") {
            $fImagePath = $signatureImagePath . DIRECTORY_SEPARATOR . $_POST['removedSignatureImage'];
            if (!empty($fImagePath) && file_exists($fImagePath)) {
                unlink($fImagePath);
            }
            $data['user_signature'] = null;
        }


        if (isset($uploadedFiles['userSignature']) && $uploadedFiles['userSignature']->getError() === UPLOAD_ERR_OK) {
            $file = $uploadedFiles['userSignature'];
            $fileName = $file->getClientFilename();
            $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
            $tmpFilePath = $file->getStream()->getMetadata('uri');
            $fileSize = $file->getSize();
            $fileMimeType = $file->getClientMediaType();
            $newFileName = "usign-" . $userId . "." . $fileExtension;
            $newFilePath = $signatureImagePath . DIRECTORY_SEPARATOR . $newFileName;
            $file->moveTo($newFilePath);

            $resizeObj = new ImageResizeUtility();
            $resizeObj = $resizeObj->setFileName($newFilePath);
            $resizeObj->resizeToWidth(250);
            $resizeObj->save($newFilePath);
            $data['user_signature'] = $newFileName;
        }

        if (isset($_POST['password']) && trim($_POST['password']) != "") {

            /* Recency cross login block */
            if (SYSTEM_CONFIG['recency']['crosslogin'] && !empty(SYSTEM_CONFIG['recency']['url'])) {
                $client = new Client(['http_version' => 2.0]);
                $url = rtrim(SYSTEM_CONFIG['recency']['url'], "/");
                $newCrossLoginPassword = CommonService::encrypt($_POST['password'], base64_decode(SYSTEM_CONFIG['recency']['crossloginSalt']));
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

            $password = $usersService->passwordHash($_POST['password']);
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
            $_POST['userId'] = $userId;
            $_POST['loginId'] = null; // We don't want to unintentionally end up creating admin users on STS
            $_POST['password'] = null; // We don't want to unintentionally end up creating admin users on STS
            $_POST['hashAlgorithm'] = 'phb'; // We don't want to unintentionally end up creating admin users on STS
            $_POST['role'] = 0; // We don't want to unintentionally end up creating admin users on STS
            $_POST['status'] = 'inactive';
            $_POST['userId'] = base64_encode($userId);
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
