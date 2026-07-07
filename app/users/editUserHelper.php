<?php

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
use Psr\Http\Message\ServerRequestInterface;
use App\Registries\ContainerRegistry;
use App\Utilities\ImageResizeUtility;

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

// Only a user who can edit users may reach this helper.
_requirePrivilege('/users/editUser.php');

// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody(), nullifyEmptyStrings: true);
$uploadedFiles = $request->getUploadedFiles();

// Only sanitize/validate the signature when a file was actually uploaded. An
// empty file input still arrives as an UploadedFile with UPLOAD_ERR_NO_FILE,
// which would otherwise log a spurious "No file was uploaded" error on every
// save made without changing the signature.
$uploadedSignature = $uploadedFiles['userSignature'] ?? null;
$sanitizedUserSignature = ($uploadedSignature instanceof UploadedFile && $uploadedSignature->getError() === UPLOAD_ERR_OK)
    ? _sanitizeFiles($uploadedSignature, ['png', 'jpg', 'jpeg', 'gif'])
    : null;

$userId = base64_decode((string) $_POST['userId']);

$userInfo = $usersService->getUserByID($userId);

$signatureImagePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature";
MiscUtility::makeDirectory($signatureImagePath);
$signatureImagePath = realpath($signatureImagePath);

$signatureImage = null;

try {
    if (trim((string) $_POST['userName']) !== '' && trim((string) $_POST['loginId']) !== '' && ($_POST['role']) != '') {
        $submittedLoginId = strtolower(trim((string) $_POST['loginId']));
        $existingLoginId = strtolower(trim((string) ($userInfo['login_id'] ?? '')));

        // Older users may still have login IDs that predate this rule. Keep those
        // unchanged, but validate any newly submitted login ID against the policy.
        if ($submittedLoginId !== $existingLoginId && !preg_match('/^[a-z0-9_-]+$/', $submittedLoginId)) {
            $_SESSION['alertMsg'] = _translate("Login ID can only contain lowercase letters, numbers, hyphens (-), and underscores (_). Spaces are not allowed.");
            header("Location:editUser.php?id=" . rawurlencode((string) $_POST['userId']));
            exit;
        }

        $_POST['loginId'] = $submittedLoginId;

        $data = [
            'user_name' => $_POST['userName'],
            'interface_user_name' => (!empty($_POST['interfaceUserName']) && $_POST['interfaceUserName'] != "") ? json_encode(array_map('trim', explode(",", (string) $_POST['interfaceUserName']))) : null,
            'email' => $_POST['email'],
            'phone_number' => $_POST['phoneNo'],
            'login_id' => $_POST['loginId'],
            'role_id' => $_POST['role'],
            'status' => $_POST['status'],
            'app_access' => $_POST['appAccessable'],
            'updated_datetime' => DateUtility::getCurrentDateTime()
        ];

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
        if (isset($_POST['removedSignatureImage']) && trim((string) $_POST['removedSignatureImage']) !== "") {
            $fImagePath = $signatureImagePath . DIRECTORY_SEPARATOR . $_POST['removedSignatureImage'];
            if ($fImagePath !== '' && $fImagePath !== '0' && file_exists($fImagePath)) {
                MiscUtility::deleteFile($fImagePath);
            }
            $data['user_signature'] = null;
        }

        $signatureImagePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature";
        if ($sanitizedUserSignature instanceof UploadedFile && $sanitizedUserSignature->getError() === UPLOAD_ERR_OK) {
            MiscUtility::makeDirectory($signatureImagePath);
            $extension = MiscUtility::getFileExtension($sanitizedUserSignature->getClientFilename());
            $signatureImage = "usign-$userId.$extension";
            $signatureImagePath = $signatureImagePath . DIRECTORY_SEPARATOR . $signatureImage;

            // Move the uploaded file to the desired location
            $sanitizedUserSignature->moveTo($signatureImagePath);

            $resizeObj = new ImageResizeUtility($signatureImagePath);
            $resizeObj->resizeToWidth(250);
            $resizeObj->save($signatureImagePath);

            $data['user_signature'] = basename($signatureImagePath);
        } else {
            $signatureImagePath = isset($userInfo['user_signature']) ? $signatureImagePath . DIRECTORY_SEPARATOR . $userInfo['user_signature'] : null;
        }

        if (isset($_POST['password']) && trim((string) $_POST['password']) !== "") {

            /* Recency cross login block */
            if (SYSTEM_CONFIG['recency']['crosslogin'] && !empty(SYSTEM_CONFIG['recency']['url'])) {
                $client = new Client();
                $url = rtrim((string) SYSTEM_CONFIG['recency']['url'], "/");
                $newCrossLoginPassword = CommonService::encrypt($_POST['password'], base64_decode((string) SYSTEM_CONFIG['recency']['crossloginSalt']));
                $result = $client->post($url . '/api/update-password', [
                    'form_params' => [
                        'u' => $_POST['loginId'],
                        't' => $newCrossLoginPassword
                    ]
                ]);
                $response = json_decode($result->getBody()->getContents());
                if ($response->status == 'fail') {
                    LoggerUtility::logError('Recency profile not updated! for the user ' . $_POST['userName']);
                }
            }

            $password = $usersService->passwordHash($_POST['password']);
            $data['password'] = $password;
            $data['force_password_reset'] = 1;
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

        // Cloud-LIS lab operators: may only edit users that already belong to their
        // OWN lab, and may only assign non-admin, non-API testing-lab roles. Enforced
        // server-side so a tampered/AJAX POST can never edit another lab's user or
        // escalate one to Admin. No-op for everyone else.
        if ($general->isCloudLisNonAdmin()) {
            $myLab = (int) ($_SESSION['labId'] ?? 0);
            $targetLab = (int) $db->where('user_id', $userId)->getValue('user_details', 'testing_lab_id');
            if ($myLab <= 0 || $targetLab !== $myLab) {
                $_SESSION['alertMsg'] = _translate("You are not allowed to edit this user.");
                header("Location:users.php");
                exit;
            }
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
                header("Location:editUser.php?id=" . rawurlencode((string) ($_POST['userId'] ?? '')));
                exit;
            }
            // Force the operator's own lab; ignore any posted lab.
            $data['testing_lab_id'] = $myLab ?: null;
        }

        // Persist the core user record. A database-level failure here (e.g. a
        // trigger on user_details rejecting the row) must NOT be swallowed by the
        // outer catch and then fall through to a "success" redirect — surface it
        // to the operator and return them to the edit page.
        $db->where('user_id', $userId);
        try {
            $db->update("user_details", $data);
        } catch (Throwable $e) {
            if (str_contains($e->getMessage(), 'Duplicate entry')) {
                $_SESSION['alertMsg'] = _translate("A user with this full name, login ID or email already exists. Please use different details.");
            } else {
                LoggerUtility::log("error", 'User update failed for user_id ' . $userId . ': ' . $e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $_SESSION['alertMsg'] = _translate("The user could not be updated due to a database error. Please contact your administrator.");
            }
            header("Location:editUser.php?id=" . rawurlencode((string) $_POST['userId']));
            exit;
        }

        // Deleting old mapping of user to facilities
        $db->where('user_id', $userId);
        $delId = $db->delete("user_facility_map");

        if ($userId != '' && trim((string) $_POST['selectedFacility']) !== '') {
            $selectedFacility = MiscUtility::desqid($_POST['selectedFacility'], returnArray: true);

            $uniqueFacilityId = array_unique($selectedFacility);

            if ($uniqueFacilityId !== []) {
                $data = [];
                foreach ($uniqueFacilityId as $facilityId) {
                    $data[] = [
                        'facility_id' => $facilityId,
                        'user_id' => $userId,
                    ];
                }
                if ($data !== []) {
                    $db->insertMulti("user_facility_map", $data);
                }
            }
        }

        // Drop cached facility map so the edit page reflects the new mapping on reload.
        ContainerRegistry::get(\App\Services\FacilitiesService::class)->clearUserFacilityMapCache($userId);
        $_SESSION['alertMsg'] = _translate("User updated successfully");


        if (!empty($general->getRemoteURL()) && $general->isLISInstance()) {
            $apiData = $_POST;
            // We don't want to unintentionally end up creating admin users on STS or
            // end up modifying existing user roles or statuses
            foreach (['loginId', 'password', 'hashAlgorithm', 'role'] as $unsetKey) {
                unset($apiData[$unsetKey]);
            }
            $apiData['userId'] = base64_encode($userId);
            $apiUrl = $general->getRemoteURL() . "/api/v1.1/user/save-user-profile.php";

            if ($signatureImagePath !== null && $signatureImagePath !== '' && $signatureImagePath !== '0' && MiscUtility::isImageValid($signatureImagePath)) {
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

            if ($signatureImagePath !== null && $signatureImagePath !== '' && $signatureImagePath !== '0' && MiscUtility::isImageValid($signatureImagePath)) {
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
                $response = $client->post($apiUrl, ['multipart' => $multipart]);
            } catch (Throwable $e) {
                // Handle the exception
                LoggerUtility::log("error", $e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }
    }


    //Add event log
    $eventType = 'user-update';
    $action = $_SESSION['userName'] . ' updated details for user ' . $_POST['userName'];
    $resource = 'user';

    $general->activityLog($eventType, $action, $resource);
} catch (Throwable $e) {
    LoggerUtility::log("error", $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);
}


_invalidateFileCacheByTags(['users_count']);
header("Location:users.php");
