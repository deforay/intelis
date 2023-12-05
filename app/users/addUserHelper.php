<?php

use App\Services\DatabaseService;
use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;
use App\Utilities\ImageResizeUtility;


if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = $GLOBALS['request'];
$_POST = $request->getParsedBody();

$uploadedFiles = $request->getUploadedFiles();

/** @var DatabaseService $db */
$db = ContainerRegistry::get('db');

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$tableName = "user_details";
$tableName2 = "user_facility_map";

$signatureImagePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature";

if (!file_exists($signatureImagePath) && !is_dir($signatureImagePath)) {
    mkdir($signatureImagePath, 0777, true);
}

$signatureImagePath = realpath($signatureImagePath);

$signatureImage = null;

try {
    if (trim((string) $_POST['userName']) != '' && trim((string) $_POST['loginId']) != '' && ($_POST['role']) != '' && ($_POST['password']) != '') {
        $userId = $general->generateUUID();
        $data = array(
            'user_id'               => $userId,
            'user_name'             => $_POST['userName'],
            'interface_user_name'   => (!empty($_POST['interfaceUserName']) && $_POST['interfaceUserName'] != "") ? json_encode(array_map('trim', explode(",", (string) $_POST['interfaceUserName']))) : null,
            'email'                 => $_POST['email'],
            'login_id'              => $_POST['loginId'],
            'phone_number'          => $_POST['phoneNo'],
            'role_id'               => $_POST['role'],
            'status'                => 'active',
            'app_access'            => $_POST['appAccessable'],
            'user_signature'        => $imageName,
            'force_password_reset'  => 1
        );

        $password = $usersService->passwordHash($_POST['password']);
        $data['password'] = $password;
        $data['hash_algorithm'] = 'phb';

        if (isset($uploadedFiles['userSignature']) && $uploadedFiles['userSignature']->getError() === UPLOAD_ERR_OK) {
            $file = $uploadedFiles['userSignature'];
            $fileName = $file->getClientFilename();
            $fileExtension = pathinfo((string) $fileName, PATHINFO_EXTENSION);
            $tmpFilePath = $file->getStream()->getMetadata('uri');
            $fileSize = $file->getSize();
            $fileMimeType = $file->getClientMediaType();
            $signatureImage = "usign-" . $userId . "." . $fileExtension;
            $newFilePath = $signatureImagePath . DIRECTORY_SEPARATOR . $signatureImage;
            $file->moveTo($newFilePath);

            $resizeObj = new ImageResizeUtility($newFilePath);
            $resizeObj->resizeToWidth(250);
            $resizeObj->save($newFilePath);
            $data['user_signature'] = $signatureImage;
        }

        if (!empty($_POST['authToken'])) {
            $data['api_token'] = $_POST['authToken'];
            $data['api_token_generated_datetime'] = DateUtility::getCurrentDateTime();
        } elseif (!empty($_POST['appAccessable']) && $_POST['appAccessable'] == 'yes') {
            $data['api_token'] = $usersService->generateAuthToken();
            $data['api_token_generated_datetime'] = DateUtility::getCurrentDateTime();
        }

        $id = $db->insert($tableName, $data);

        if ($id === true && trim((string) $_POST['selectedFacility']) != '') {
            $selectedFacility = explode(",", (string) $_POST['selectedFacility']);
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

        $_SESSION['alertMsg'] = _translate("User saved successfully!");
    }
    $systemType = $general->getSystemConfig('sc_user_type');
    if (isset(SYSTEM_CONFIG['remoteURL']) && SYSTEM_CONFIG['remoteURL'] != "" && $systemType == 'vluser') {
        $_POST['userId'] = $userId;
        $_POST['loginId'] = null; // We don't want to unintentionally end up creating admin users on STS
        $_POST['password'] = null; // We don't want to unintentionally end up creating admin users on STS
        $_POST['hashAlgorithm'] = 'phb'; // We don't want to unintentionally end up creating admin users on STS
        $_POST['role'] = 0; // We don't want to unintentionally end up creating admin users on STS
        $_POST['status'] = 'inactive';
        $_POST['userId'] = base64_encode((string) $data['user_id']);
        $apiUrl = SYSTEM_CONFIG['remoteURL'] . "/api/v1.1/user/save-user-profile.php";
        $post = array(
            'post' => json_encode($_POST),
            'sign' => (!empty($signatureImage) && MiscUtility::imageExists($signatureImage)) ? curl_file_create($signatureImage) : null,
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
    $action = $_SESSION['userName'] . ' added user ' . $_POST['userName'];
    $resource = 'user';

    $general->activityLog($eventType, $action, $resource);

    header("Location:users.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
