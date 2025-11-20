<?php

use Psr\Http\Message\ServerRequestInterface;
use GuzzleHttp\Client;
use App\Services\ApiService;
use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use Slim\Psr7\UploadedFile;
use App\Registries\ContainerRegistry;
use App\Utilities\ImageResizeUtility;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody(), nullifyEmptyStrings: true);
$_POST = array_map('trim', $_POST);

$uploadedFiles = $request->getUploadedFiles();

$sanitizedUserSignature = _sanitizeFiles($uploadedFiles['userSignature'], ['png', 'jpg', 'jpeg', 'gif']);

$signatureImage = null;

try {
    if (trim((string) $_POST['userName']) !== '' && trim((string) $_POST['loginId']) !== '' && ($_POST['role']) != '' && ($_POST['password']) != '') {
        $userId = MiscUtility::generateUUID();

        $_POST['loginId'] = strtolower(trim((string) $_POST['loginId']));
        $_POST['loginId'] = str_replace(' ', '', $_POST['loginId']);
        $_POST['loginId'] = preg_replace('/[^a-z0-9_-]/', '', $_POST['loginId']);

        $data = [
            'user_id' => $userId,
            'user_name' => $_POST['userName'],
            'interface_user_name' => (!empty($_POST['interfaceUserName']) && $_POST['interfaceUserName'] != "") ? json_encode(array_map('trim', explode(",", (string) $_POST['interfaceUserName']))) : null,
            'email' => $_POST['email'],
            'login_id' => $_POST['loginId'],
            'phone_number' => $_POST['phoneNo'],
            'role_id' => $_POST['role'],
            'status' => 'active',
            'app_access' => $_POST['appAccessable'],
            'force_password_reset' => 1
        ];

        $password = $usersService->passwordHash($_POST['password']);
        $data['password'] = $password;

        $signatureImagePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature";
        if ($sanitizedUserSignature instanceof UploadedFile && $sanitizedUserSignature->getError() === UPLOAD_ERR_OK) {
            MiscUtility::makeDirectory($signatureImagePath);
            $extension = MiscUtility::getFileExtension($sanitizedUserSignature->getClientFilename());
            $signatureImage = "usign-" . $userId . "." . $extension;
            $signatureImagePath = $signatureImagePath . DIRECTORY_SEPARATOR . $signatureImage;

            // Move the uploaded file to the desired location
            $sanitizedUserSignature->moveTo($signatureImagePath);

            $resizeObj = new ImageResizeUtility($signatureImagePath);
            $resizeObj->resizeToWidth(250);
            $resizeObj->save($signatureImagePath);

            $data['user_signature'] = basename($signatureImagePath);
        }

        if ($_POST['status'] == 'inactive') {
            unset($_POST['password']);
            unset($_POST['authToken']);
            $data['force_password_reset'] = 0;
            $data['password'] = null;
            $data['api_token'] = null;
            $data['api_token_generated_datetime'] = null;
        }

        if (!empty($_POST['authToken'])) {
            $data['api_token'] = $_POST['authToken'];
            $data['api_token_generated_datetime'] = DateUtility::getCurrentDateTime();
        } elseif (!empty($_POST['appAccessable']) && $_POST['appAccessable'] == 'yes') {
            $data['api_token'] = ApiService::generateAuthToken();
            $data['api_token_generated_datetime'] = DateUtility::getCurrentDateTime();
        }


        $id = $db->insert('user_details', $data);


        if ($id === true && trim((string) $_POST['selectedFacility']) !== '') {
            $selectedFacility = MiscUtility::desqid($_POST['selectedFacility'], returnArray: true);
            $uniqueFacilityId = array_unique($selectedFacility);
            if ($uniqueFacilityId !== []) {
                $facilityUser = [];
                foreach ($uniqueFacilityId as $facilityId) {
                    $facilityUser[] = [
                        'facility_id' => $facilityId,
                        'user_id' => $data['user_id'],
                    ];
                }

                if ($facilityUser !== []) {
                    $db->insertMulti('user_facility_map', $facilityUser);
                }
            }
        }

        $_SESSION['alertMsg'] = _translate("User saved successfully!");
    }

    if (!empty($general->getRemoteURL()) && $general->isLISInstance()) {
        $apiData = $_POST;
        // We don't want to unintentionally end up creating admin users on STS or
        // end up modifying existing user roles or statuses
        foreach (['loginId', 'password', 'hashAlgorithm', 'role'] as $unsetKey) {
            unset($apiData[$unsetKey]);
        }
        $apiData['userId'] = base64_encode((string) $data['user_id']);
        $apiUrl = $general->getRemoteURL() . "/api/v1.1/user/save-user-profile.php";


        $multipart = [
            [
                'name' => 'post',
                'contents' => json_encode($apiData)
            ],
            [
                'name' => 'x-api-key',
                'contents' => MiscUtility::generateRandomString(18)
            ]
        ];

        if (!empty($signatureImagePath) && MiscUtility::isImageValid($signatureImagePath)) {
            $multipart[] = [
                'name' => 'sign',
                'contents' => fopen($signatureImagePath, 'r')
            ];
        }

        $client = new Client();
        try {
            $response = $client->post($apiUrl, [
                'multipart' => $multipart
            ]);
        } catch (Throwable $e) {
            // Handle the exception
            LoggerUtility::log("error", $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
    //Add event log
    $eventType = 'user-add';
    $action = $_SESSION['userName'] . ' added user ' . $_POST['userName'];
    $resource = 'user';

    $general->activityLog($eventType, $action, $resource);
} catch (Throwable $exc) {
    LoggerUtility::logError($exc->getMessage(), [
        'exception' => $exc->getMessage(),
        'file' => $exc->getFile(),
        'line' => $exc->getLine()
    ]);
}
_invalidateFileCacheByTags(['users_count']);
header("Location:users.php");
