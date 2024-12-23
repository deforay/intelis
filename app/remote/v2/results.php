<?php

use App\Services\ApiService;
use App\Services\TestsService;
use App\Utilities\JsonUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Utilities\QueryLoggerUtility;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Services\FacilitiesService;
use App\Services\STS\TokensService;
use App\Registries\ContainerRegistry;
use App\Services\STS\ResultsService;

header('Content-Type: application/json');

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var ApiService $apiService */
$apiService = ContainerRegistry::get(ApiService::class);

/** @var ResultsService $stsResultsService */
$stsResultsService = ContainerRegistry::get(ResultsService::class);

/** @var TokensService $stsTokensService */
$stsTokensService = ContainerRegistry::get(TokensService::class);


$payload = [];


try {
        /** @var Laminas\Diactoros\ServerRequest $request */
        $request = AppRegistry::get('request');

        $jsonResponse = $apiService->getJsonFromRequest($request);


    $apiRequestId  = $apiService->getHeader($request, 'X-Request-ID');
    $transactionId = $apiRequestId ?? MiscUtility::generateULID();

      //  $authToken = ApiService::getAuthorizationBearerToken($request);

/*
    $labId = $data['labId'] ?? null;

    if (empty($labId)) {
        throw new SystemException('Lab ID is missing in the request', 400);
    }

    $token = $stsTokensService->validateToken($authToken, $labId);

    if ($token === false || empty($token)) {
        throw new SystemException('Unauthorized Access', 401);
    }

    if (is_string($token)) {
        $payload['token'] = $token;
    }
*/
   
        $testType = $jsonResponse['testType'] ?? null;

        if (empty($testType)) {
            throw new SystemException('Test Type is missing in the request', 400);
        }

        [$sampleCodes, $facilityIds] = $stsResultsService->getResults($testType, $jsonResponse);

        $payload = JsonUtility::encodeUtf8Json($sampleCodes);

        $general->addApiTracking($transactionId, 'vlsm-system', $counter, 'results', $testType, $_SERVER['REQUEST_URI'], $jsonResponse, $payload, 'json', $labId);
        $general->updateResultSyncDateTime($testType, $facilityIds, $labId);

        //$db->commitTransaction();
} catch (Throwable $e) {
    //$db->rollbackTransaction();

    $payload = json_encode([]);

    QueryLoggerUtility::log($e->getFile() . ":" . $e->getLine() . ":" . $db->getLastErrno());
    QueryLoggerUtility::log($e->getFile() . ":" . $e->getLine()  . ":" . $db->getLastError());
    QueryLoggerUtility::log($e->getFile() . ":" . $e->getLine()  . ":" . $db->getLastQuery());

    throw new SystemException($e->getFile() . ":" . $e->getLine() . " - " . $e->getMessage(), $e->getCode(), $e);

}

echo ApiService::sendJsonResponse($payload, $request);
