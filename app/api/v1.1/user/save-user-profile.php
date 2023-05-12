<?php

use App\Exceptions\SystemException;
use App\Services\ApiService;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\UsersService;
use App\Utilities\ImageResizeUtility;


session_unset(); // no need of session in json response

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

/** @var ApiService $app */
$app = ContainerRegistry::get(ApiService::class);

$jsonResponse = file_get_contents('php://input');

// error_log("------ USER API START-----");
// error_log($jsonResponse);
// error_log("------ USER API END -----");
$transactionId = $general->generateUUID();

try {
    ini_set('memory_limit', -1);
    $authToken = $general->getAuthorizationBearerToken();
    $user = $usersService->getUserFromToken($authToken);
    if (!empty($jsonResponse)) {
        $decode = json_decode($jsonResponse, true);
        //http_response_code(501);
    } elseif (!empty($_REQUEST)) {
        $decode = $_REQUEST;
        $decode['post'] = json_decode($decode['post'], true);
    } else {
        //$general->elog($decode);
        throw new SystemException("2 Invalid request. Please check your request parameters.");
    }
    $apiKey = isset($decode['x-api-key']) && !empty($decode['x-api-key']) ? $decode['x-api-key'] : null;

    if ((empty($decode['post']) || $decode['post'] === false) && !isset($user)) {
        //$general->elog($decode);
        throw new SystemException("3 Invalid request. Please check your request parameters.");
    } else {
        if (isset($user)) {
            $post = $decode;
        } else {
            $post = $decode['post'];
        }
    }
    $post['loginId'] = $post['loginId'] ?: $post['login_id'] ?: null;
    $post['role'] = $post['role'] ?: $post['role_id'] ?: null;
    $post['hashAlgorithm'] = $post['hashAlgorithm'] ?: $post['hash_algorithm'] ?: 'phb';

    if (!isset($user)) {
        if (!$apiKey) {
            throw new SystemException("Invalid API Key. Please check your request parameters.");
        }
        $userId = !empty($post['userId']) ? base64_decode($db->escape($post['userId'])) : null;
    } else {
        $userId = !empty($post['userId']) ? $db->escape($post['userId']) : null;
    }

    $aRow = null;
    if (!empty($userId) || !empty($post['email'])) {
        if (!empty($userId)) {
            $db->where("user_id", $userId);
        } else if (!empty($post['email'])) {
            $db->where("email", $db->escape($post['email']));
        }
        $aRow = $db->getOne("user_details");
    }
    $data = array(
        'user_id' => (!empty($userId) && $userId != "") ? $userId : $general->generateUUID(),
        'user_name' => $db->escape($post['userName']),
        'email' => $db->escape($post['email']),
        'interface_user_name' => json_encode(array_map('trim', explode(",", $db->escape($post['interfaceUserName'])))),
        'phone_number' => $db->escape($post['phoneNo'])
    );

    if (!empty($post['status'])) {
        $data['status'] = $post['status'];
    }

    if (!empty($post['password'])) {
        $data['hash_algorithm'] = $post['hashAlgorithm'];
        $data['password'] = $usersService->passwordHash($post['password']);
    }
    if (!empty($post['role'])) {
        $data['role_id'] =  $db->escape($post['role']);
    }
    if (!empty($post['login_id'])) {
        $data['login_id'] =  $db->escape($post['login_id']);
    }

    if (isset($_FILES['sign']['name']) && $_FILES['sign']['name'] != "") {
        if (!file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature") && !is_dir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature")) {
            mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature", 0777, true);
        }
        $extension = strtolower(pathinfo(UPLOAD_PATH . DIRECTORY_SEPARATOR . $_FILES['sign']['name'], PATHINFO_EXTENSION));
        $imageName = "usign-" . $data['user_id'] . "." . $extension;

        $signatureImagePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature" . DIRECTORY_SEPARATOR . $imageName;
        if (move_uploaded_file($_FILES["sign"]["tmp_name"], $signatureImagePath)) {
            $resizeObj = new ImageResizeUtility();
            $resizeObj = $resizeObj->setFileName($signatureImagePath);
            $resizeObj->resizeToWidth(100);
            $resizeObj->save($signatureImagePath);
            $data['user_signature'] = $imageName;
        }
    }

    $id = 0;
    if (isset($aRow['user_id']) && $aRow['user_id'] != "") {
        $db = $db->where('user_id', $aRow['user_id']);
        $id = $db->update("user_details", $data);
    } else {
        $data['status'] = 'inactive';
        $id = $db->insert("user_details", $data);
    }

    if ($id > 0 && trim($post['selectedFacility']) != '') {
        if ($id > 0 && trim($post['selectedFacility']) != '') {
            $db = $db->where('user_id', $data['user_id']);
            $delId = $db->delete("user_facility_map");
            $selectedFacility = explode(",", $post['selectedFacility']);
            $uniqueFacilityId = array_unique($selectedFacility);
            for ($j = 0; $j <= count($selectedFacility); $j++) {
                if (isset($uniqueFacilityId[$j])) {
                    $insertData = array(
                        'facility_id' => $selectedFacility[$j],
                        'user_id' => $data['user_id'],
                    );
                    $db->insert("user_facility_map", $insertData);
                }
            }
        }
    }
    if ($id > 0) {
        $payload = array(
            'status' => 'success',
            'timestamp' => time(),
        );
    } else {
        $payload = array(
            'status' => 'failed',
            'message' => 'Something went wrong!',
            'timestamp' => time(),
        );
    }

    $payload = json_encode($payload);
} catch (SystemException $exc) {
    $payload = array(
        'status' => 'failed',
        'error' => $exc->getMessage(),
        'timestamp' => time(),
    );

    $payload = json_encode($payload);
    error_log(print_r($data['post'], true));

    error_log("Save User Profile API : " . $exc->getMessage());
    error_log($exc->getTraceAsString());
}

$trackId = $general->addApiTracking($transactionId, $data['user_id'], count($data), 'save-user', 'common', $_SERVER['REQUEST_URI'], $decode, $payload, 'json');

echo $payload;
