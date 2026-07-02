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

// Only a user who can add users may reach this helper.
_requirePrivilege('/users/addUser.php');

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
        if (!preg_match('/^[a-z0-9_-]+$/', $_POST['loginId'])) {
            $_SESSION['alertMsg'] = _translate("Login ID can only contain lowercase letters, numbers, hyphens (-), and underscores (_). Spaces are not allowed.");
            header("Location:addUser.php");
            exit;
        }

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


        // Resolve the user's testing-lab assignment (distinct from facilityMap).
        // The role's access_type is read from the DB, never trusted from POST.
        $roleAccessType = $db->where('role_id', $_POST['role'])->getValue('roles', 'access_type');
        if ($general->isSTSInstance()) {
            // STS: only a testing-lab user gets a lab, and only a real
            // facility_type=2 facility is accepted; everything else is null.
            $data['testing_lab_id'] = null;
            if ($roleAccessType === 'testing-lab' && !empty($_POST['testingLabId'])) {
                $validLab = $db->where('facility_id', (int) $_POST['testingLabId'])
                    ->where('facility_type', 2)
                    ->getValue('facility_details', 'facility_id');
                $data['testing_lab_id'] = !empty($validLab) ? (int) $validLab : null;
            }
        } else {
            // LIS / standalone: single-lab install. Force the install's lab
            // server-side so the form cannot spoof a different lab.
            $scLab = (int) ($general->getSystemConfig('sc_testing_lab_id') ?? 0);
            $data['testing_lab_id'] = $scLab > 0 ? $scLab : null;
        }

        // Cloud-LIS lab operators are confined to their own lab and to non-admin,
        // non-API testing-lab roles. Enforced here server-side (the dropdown filter
        // is cosmetic) so a tampered or AJAX POST can NEVER escalate -- e.g. mint an
        // Admin user -- or assign a user to another lab. No-op for everyone else.
        if ($general->isCloudLisNonAdmin()) {
            $role = $db->rawQueryOne(
                "SELECT role_id, access_type, role_code, status FROM roles WHERE role_id = ?",
                [(int) ($_POST['role'] ?? 0)]
            );
            $roleOk = !empty($role)
                && $role['status'] === 'active'
                && $role['access_type'] === 'testing-lab'
                && (int) $role['role_id'] !== 1
                && strtoupper((string) ($role['role_code'] ?? '')) !== 'API';
            if (!$roleOk) {
                $_SESSION['alertMsg'] = _translate("You are not allowed to assign this role.");
                header("Location:addUser.php");
                exit;
            }
            // Force the operator's own lab; ignore any posted lab.
            $data['testing_lab_id'] = (int) ($_SESSION['labId'] ?? 0) ?: null;
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

            // Drop any cached facility map for this new user so lookups are fresh.
            ContainerRegistry::get(\App\Services\FacilitiesService::class)->clearUserFacilityMapCache($data['user_id']);
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

        if (!empty($signatureImagePath) && MiscUtility::isImageValid($signatureImagePath)) {
            // Mirror the signature into JSON so the cloud API can still persist it
            // when multipart file parsing is altered by proxies or PHP config.
            $apiData['signature_image_content'] = base64_encode(file_get_contents($signatureImagePath));
            $apiData['signature_image_filename'] = basename($signatureImagePath);
        }


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
                'contents' => fopen($signatureImagePath, 'r'),
                'filename' => basename($signatureImagePath),
                'headers' => [
                    'Content-Type' => mime_content_type($signatureImagePath) ?: 'application/octet-stream',
                ],
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
