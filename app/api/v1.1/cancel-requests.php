<?php
// Allow from any origin
use App\Exceptions\SystemException;
use App\Services\ApiService;
use App\Services\DatabaseService;
use App\Services\UsersService;
use App\Services\CommonService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Services\GeoLocationsService;

/** @var Slim\Psr7\Request $request */
$request = $GLOBALS['request'];
$origJson = $request->getBody()->getContents();
$input = $request->getParsedBody();

$applicationConfig = ContainerRegistry::get('applicationConfig');

/** @var DatabaseService $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var ApiService $app */
$app = ContainerRegistry::get(ApiService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);


$transactionId = $general->generateUUID();

/* For API Tracking params */
$requestUrl = $_SERVER['HTTP_HOST'];
$requestUrl .= $_SERVER['REQUEST_URI'];
$authToken = $general->getAuthorizationBearerToken();
$user = $usersService->getUserByToken($authToken);

$testType = [
    'vl' => 'form_vl',
    'eid' => 'form_eid',
    'covid19' => 'form_covid19',
    'hepatitis' => 'form_hepatitis',
    'tb' => 'form_tb',
    'generic-tests' => 'form_generic'
];
$testTypePrimary = [
    'vl' => 'vl_sample_id',
    'eid' => 'eid_id',
    'covid19' => 'covid19_id',
    'hepatitis' => 'hepatitis_id',
    'tb' => 'tb_id',
    'generic-tests' => 'sample_id'
];
try {
    $sQuery = 'SELECT * FROM ' . $testType[$input['testType']] . ' as vl
    LEFT JOIN r_sample_status as ts ON ts.status_id=vl.result_status';

    $where = [];
    /* To check the uniqueId filter */
    $uniqueId = $input['uniqueId'] ?? [];
    if (!empty($uniqueId)) {
        $uniqueId = implode("','", $uniqueId);
        $where[] = " vl.unique_id IN ('$uniqueId')";
    }
    /* To check the sample id filter */
    $sampleCode = $input['sampleCode'] ?? [];
    if (!empty($sampleCode)) {
        $sampleCode = implode("','", $sampleCode);
        $where[] = " (vl.sample_code IN ('$sampleCode') OR vl.remote_sample_code IN ('$sampleCode') ) ";
    }

    /* To skip some status */
    // $where[] = " (vl.result_status NOT IN (4, 7, 8)) ";
    $where = ' WHERE ' . implode(' AND ', $where);
    $rowData = $db->rawQuery($sQuery);
    $response = [];
    foreach ($rowData as $key => $row) {
        if (!empty($row['result_status'])) {
            if (!in_array($row['result_status'], [4, 7, 8])) {
                $db->where($testTypePrimary[$input['testType']], $row[$testTypePrimary[$input['testType']]]);
                $status = $db->update($testType[$input['testType']], array('result_status' => 12));
                if ($status) {
                    $response[$key]['status'] = 'success';
                } else {
                    $response[$key]['status'] = 'fail';
                    $response[$key]['message'] = 'Already cancelled';
                }
            } else {
                $response[$key]['status'] = 'fail';
                $response[$key]['message'] = 'Cancellation not allowed';
            }
        }
        $response[$key]['sampleCode'] = $row['remote_sample_code'] ??  $row['sample_code'];
    }
    $payload = [
        'status' => 'success',
        'timestamp' => time(),
        'data' => $response
    ];
} catch (SystemException $exc) {

    // http_response_code(500);
    $payload = [
        'status' => 'failed',
        'timestamp' => time(),
        'error' => $exc->getMessage(),
        'data' => []
    ];
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
$payload = json_encode($payload);
$general->addApiTracking($transactionId, $user['user_id'], count($rowData), 'cancel-requests', $input['testType'], $_SERVER['REQUEST_URI'], $origJson, $payload, 'json');
echo $payload;
