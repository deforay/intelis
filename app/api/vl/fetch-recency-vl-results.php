<?php

use Slim\Psr7\Request;
use App\Services\ApiService;
use App\Services\UsersService;
use App\Utilities\JsonUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;


// Sanitized values from $request object
/** @var Request $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());
$_GET = _sanitizeInput($request->getQueryParams());

$_REQUEST = array_merge($_GET, $_POST);


session_unset(); // no need of session in json response

// PURPOSE : Fetch Results using external_sample_code field which is used to
// store the recency id from third party apps (for eg. in DRC)

// external_sample_code field in db was unused so we decided to use it to store recency id

ini_set('memory_limit', -1);
set_time_limit(0);
ini_set('max_execution_time', 20000);

/** @var ApiService $apiService */
$apiService = ContainerRegistry::get(ApiService::class);

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

$requestUrl = $_SERVER['HTTP_HOST'];
$requestUrl .= $_SERVER['REQUEST_URI'];

$transactionId = MiscUtility::generateULID();

$authToken = ApiService::extractBearerToken($request);
$user = $usersService->findUserByApiToken($authToken);

$sampleCode = empty($_REQUEST['s']) ? null : explode(",", $db->escape($_REQUEST['s']));
$recencyId = empty($_REQUEST['r']) ? null : explode(",", $db->escape($_REQUEST['r']));
$from = empty($_REQUEST['f']) ? null : $db->escape($_REQUEST['f']);
$to = empty($_REQUEST['t']) ? null : $db->escape($_REQUEST['t']);
$orderSortType = empty($_REQUEST['orderSortType']) ? null : $db->escape($_REQUEST['orderSortType']);

if (!$sampleCode && !$recencyId && (!$from || !$to)) {
    http_response_code(400);
    throw new SystemException("Mandatory request params missing in request. Expected Recency ID(s) or a Date Range", 400);
}

try {

    $sQuery = "SELECT vl.sample_code,
                    vl.remote_sample_code,
                    vl.external_sample_code as `recency_id`,
                    vl.sample_collection_date,
                    vl.sample_received_at_lab_datetime,
                    vl.sample_tested_datetime,
                    vl.is_sample_rejected,
                    vl.result,
                    vl.result_value_log,
                    samptype.sample_name as `specimen_type`,
                    sampstatus.status_name as `sample_status`,
                    f.facility_name as `collection_facility_name`,
                    lab.facility_name as `testing_lab_name`,
                    testreason.test_reason_name as `reason_for_testing`,
                    rejreason.rejection_reason_name as `rejection_reason`
                    FROM form_vl as vl
                    LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
                    LEFT JOIN facility_details as lab ON vl.lab_id=lab.facility_id
                    LEFT JOIN r_vl_sample_type as samptype ON samptype.sample_id=vl.specimen_type
                    INNER JOIN r_sample_status as sampstatus ON sampstatus.status_id=vl.result_status
                    LEFT JOIN r_vl_test_reasons as testreason ON testreason.test_reason_id=vl.reason_for_vl_testing
                    LEFT JOIN r_vl_sample_rejection_reasons as rejreason ON rejreason.rejection_reason_id=vl.reason_for_sample_rejection
                    WHERE (external_sample_code is not null)";



    if ($recencyId !== null && $recencyId !== []) {
        $recencyId = implode("','", $recencyId);
        $sQuery .= " AND external_sample_code IN ('$recencyId') ";
    }

    if ($sampleCode !== null && $sampleCode !== []) {
        $sampleCode = implode("','", $sampleCode);
        $sQuery .= " AND sample_code IN ('$sampleCode') ";
    }

    if (!empty($from) && !empty($to)) {
        $sQuery .= " AND DATE(last_modified_datetime) between '$from' AND '$to' ";
    }

    if (empty($orderSortType)) {
        $orderSortType = 'ASC'; // if Order Sort Type is not defined we treat it as ASC by default
    }

    $sQuery .= " ORDER BY last_modified_datetime $orderSortType ";
    $rowData = $db->rawQuery($sQuery);

    http_response_code(200);
    $payload = [
        'status' => 'success',
        'timestamp' => time(),
        'data' => $rowData ?? []
    ];
} catch (Throwable $exc) {
    $payload = [
        'status' => 'failed',
        'timestamp' => time(),
        'error' => _translate('Failed to process this request. Please contact the system administrator if the problem persists'),
        'data' => []
    ];
    http_response_code(500);
    LoggerUtility::logError($exc->getMessage(), [
        'file' => $exc->getFile(),
        'line' => $exc->getLine(),
        'transactionId' => $transactionId,
        'requestUrl' => $requestUrl,
        'stacktrace' => $exc->getTraceAsString()
    ]);
}

$payload = JsonUtility::encodeUtf8Json($payload);
$general->addApiTracking($transactionId, $user['user_id'], count($rowData ?? []), 'fetch-recency-vl-result', 'vl', $requestUrl, $_REQUEST, $payload, 'json', null, null, $authToken);

//echo $payload
echo ApiService::generateJsonResponse($payload, $request);
