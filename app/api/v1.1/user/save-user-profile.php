<?php

use Slim\Psr7\Request;
use Slim\Psr7\UploadedFile;
use App\Services\ApiService;
use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Utilities\ImageResizeUtility;

try {


    ini_set('memory_limit', -1);
    set_time_limit(0);
    ini_set('max_execution_time', 20000);

    $data = [];

    /** @var DatabaseService $db */
    $db = ContainerRegistry::get(DatabaseService::class);

    /** @var CommonService $general */
    $general = ContainerRegistry::get(CommonService::class);

    /** @var UsersService $usersService */
    $usersService = ContainerRegistry::get(UsersService::class);

    /** @var FacilitiesService $facilitiesService */
    $facilitiesService = ContainerRegistry::get(FacilitiesService::class);

    /** @var ApiService $apiService */
    $apiService = ContainerRegistry::get(ApiService::class);

    /** @var Request $request */
    $request = AppRegistry::get('request');

    $origJson = $apiService->getJsonFromRequest($request);

    $transactionId = MiscUtility::generateULID();

    $uploadedFiles = $request->getUploadedFiles();

    $sanitizedSignFile = _sanitizeFiles($uploadedFiles['sign'], ['png', 'jpg', 'jpeg', 'gif']);

    $authToken = ApiService::extractBearerToken($request);
    $user = $usersService->findUserByApiToken($authToken);
    if ($_REQUEST !== [] && !empty($_REQUEST['post']) && JsonUtility::isJSON($_REQUEST['post'])) {
        $input = _sanitizeInput($_REQUEST);
        $input['post'] = json_decode((string) $input['post'], true);
    } elseif (!empty($origJson) && JsonUtility::isJSON($origJson)) {
        $input = _sanitizeInput($request->getParsedBody());
    } else {
        throw new SystemException("2 Invalid request. Please check your request parameters.");
    }
    $apiKey = empty($input['x-api-key']) ? null : $input['x-api-key'];

    if ((empty($input['post']) || $input['post'] === false) && empty($user)) {
        throw new SystemException("3 Invalid request. Please check your request parameters.");
    } elseif (!empty($user)) {
        $post = $input;
    } else {
        $post = $input['post'];
    }

    if (JsonUtility::isJSON($post)) {
        $post = json_decode((string) $post, true);
    }
    $post['loginId'] ??= null;
    $post['role'] ??= null;
    $post['hashAlgorithm'] ??= 'phb';

    if (!isset($user)) {
        if (!$apiKey) {
            throw new SystemException(_translate("Please check your request parameters."));
        }
        $userId = $post['userId'] ?? null;
        if (MiscUtility::isBase64($userId)) {
            $userId = base64_decode((string) $userId);
        }
    } else {
        $userId = empty($post['userId']) ? null : $db->escape($post['userId']);
    }

    $aRow = null;
    if (!empty($userId) || !empty($post['email'])) {
        if (!empty($userId)) {
            $db->where("user_id", $userId);
        } elseif (!empty($post['email'])) {
            $db->where("email", $db->escape($post['email']));
        }
        $aRow = $db->getOne("user_details");
    }

    $data = [
        'user_id' => (!empty($userId) && $userId != "") ? $userId : MiscUtility::generateUUID(),
        'user_name' => $db->escape($post['userName']),
        'email' => $db->escape($post['email']),
        'interface_user_name' => empty($post['interfaceUserName']) ? null : json_encode(array_map('trim', explode(",", (string) $post['interfaceUserName']))),
        'phone_number' => $db->escape($post['phoneNo']),
        'updated_datetime' => DateUtility::getCurrentDateTime()
    ];

    if (!empty($post['status'])) {
        $data['status'] = $post['status'];
    }

    if (!empty($post['password'])) {
        $data['password'] = $usersService->passwordHash($post['password']);
    }
    if (!empty($post['role'])) {
        $data['role_id'] = $db->escape($post['role']);
    }
    if (!empty($post['loginId'])) {
        $data['login_id'] = $db->escape($post['loginId']);
    }

    if ($sanitizedSignFile instanceof UploadedFile && $sanitizedSignFile->getError() === UPLOAD_ERR_OK && $sanitizedSignFile->getSize() > 0) {
        $signatureImagePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature";
        MiscUtility::makeDirectory($signatureImagePath);

        $extension = MiscUtility::getFileExtension($sanitizedSignFile->getClientFilename());

        $imageName = "usign-" . htmlspecialchars((string) $data['user_id']) . "." . $extension;

        $signatureImagePath = realpath($signatureImagePath) . DIRECTORY_SEPARATOR . $imageName;

        // Move the uploaded file to the desired location
        $sanitizedSignFile->moveTo($signatureImagePath);

        // Resize the image
        $resizeObj = new ImageResizeUtility($signatureImagePath);
        $resizeObj->resizeToWidth(250);
        $resizeObj->save($signatureImagePath);

        $data['user_signature'] = basename($signatureImagePath);
    }
    $id = false;
    $data = MiscUtility::arrayEmptyStringsToNull($data);

    foreach (['login_id', 'role_id', 'password'] as $unsetKey) {
        unset($data[$unsetKey]);
    }

    if (isset($aRow['user_id']) && !empty($aRow['user_id']) && $aRow['user_id'] != "") {
        unset($data['status']);
        $db->where('user_id', $aRow['user_id']);
        $id = $db->update("user_details", $data);
    } else {
        $data['status'] ??= 'inactive';
        $id = $db->insert("user_details", $data);
    }

    if ($id != false) {
        // if (trim((string) $post['selectedFacility']) !== '') {
        //     $db->where('user_id', $data['user_id']);
        //     $delId = $db->delete("user_facility_map");
        //     $selectedFacility = explode(",", (string) $post['selectedFacility']);
        //     $uniqueFacilityId = array_unique($selectedFacility);
        //     $counter = count($selectedFacility);
        //     for ($j = 0; $j <= $counter; $j++) {
        //         if (isset($uniqueFacilityId[$j])) {
        //             $insertData = [
        //                 'facility_id' => $selectedFacility[$j],
        //                 'user_id' => $data['user_id'],
        //             ];
        //             $db->insert("user_facility_map", $insertData);
        //         }
        //     }
        // }

        $payload = [
            'status' => 'success',
            'timestamp' => time(),
        ];
    } else {
        $payload = [
            'status' => 'failed',
            'message' => _translate("Something went wrong. Please try again later."),
            'timestamp' => time(),
            'transactionId' => $transactionId,
        ];
    }

    $payload = JsonUtility::encodeUtf8Json($payload);
} catch (Throwable $exc) {
    $payload = [
        'status' => 'failed',
        'timestamp' => time(),
        'transactionId' => $transactionId,
        'error' => _translate("Something went wrong. Please try again later."),
    ];

    $payload = JsonUtility::encodeUtf8Json($payload);

    LoggerUtility::log("error", "Save User Profile API : " . $exc->getMessage(), [
        'file' => $exc->getFile(),
        'line' => $exc->getLine(),
        'trace' => $exc->getTraceAsString(),
    ]);
}

$trackId = $general->addApiTracking($transactionId, $data['user_id'], count($data ?? []), 'save-user', 'common', $_SERVER['REQUEST_URI'], $input, $payload, 'json');

//echo $payload
echo ApiService::generateJsonResponse($payload, $request);

_invalidateFileCacheByTags(['users_count']);
