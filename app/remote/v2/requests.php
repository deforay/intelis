<?php

use Psr\Http\Message\ServerRequestInterface;
use App\Services\ApiService;
use App\Services\TestsService;
use App\Utilities\JsonUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Services\FacilitiesService;
use App\Services\STS\TokensService;
use App\Registries\ContainerRegistry;
use App\Services\STS\RequestsService;

header('Content-Type: application/json');

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var ApiService $apiService */
$apiService = ContainerRegistry::get(ApiService::class);

/** @var RequestsService $stsRequestsService */
$stsRequestsService = ContainerRegistry::get(RequestsService::class);

/** @var TokensService $stsTokensService */
$stsTokensService = ContainerRegistry::get(TokensService::class);


/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);


$payload = [];
$procStart = microtime(true);

try {
    $db->beginTransaction();

    /** @var ServerRequestInterface $request */
    $request = AppRegistry::get('request');
    $contentLength = (int) ($request->getHeaderLine('Content-Length') ?: 0);

    $authToken = ApiService::extractBearerToken($request);
    $data      = $apiService->getJsonFromRequest($request, true);

    $apiRequestId  = $apiService->getHeader($request, 'X-Request-ID');
    $transactionId = $apiRequestId ?? MiscUtility::generateULID();

    $labId = $data['labId'] ?? null;
    if (!$labId) {
        // Let the generator choose body/headers for 400
        throw new SystemException('Lab ID is missing in the request', 400);
    }

    $token = $stsTokensService->validateToken($authToken, $labId);
    if ($token === false || empty($token)) {
        http_response_code(401);
        throw new SystemException(
            'Unauthorized Access on request sync: ' . $stsTokensService->getLastValidationFailure(),
            401
        );
    }

    if (is_string($token)) {
        $payload['token'] = $token;
    }

    $syncSinceDate = $data['syncSinceDate'] ?? null;
    $manifestCode  = $data['manifestCode'] ?? null;
    $testType      = $data['testType'] ?? null;
    // A newer lab asks for receipts; older labs never send the key and get the
    // plain window they always have.
    $wantsReceipts = in_array($data['receipts'] ?? null, [1, '1', true], true);
    // Pulling again in the same run: the lab has the window already.
    $pendingOnly = $wantsReceipts && in_array($data['pendingOnly'] ?? null, [1, '1', true], true);

    if (!$testType) {
        throw new SystemException('Test Type is missing in the request', 400);
    }

    $tableName      = TestsService::getTestTableName($testType);
    $primaryKeyName = TestsService::getPrimaryColumn($testType);

    $facilityMapResult = $facilitiesService->getTestingLabFacilityMap($labId);

    $requestsData = $stsRequestsService->getRequests(
        $testType,
        $labId,
        $facilityMapResult ?? [],
        $manifestCode,
        $syncSinceDate,
        $wantsReceipts,
        $pendingOnly
    );
    // Only when the pending rows could be read; otherwise this pull is the plain
    // window and the lab is not told to send a receipt.
    $receiptsEnabled = $requestsData['receiptsEnabled'] ?? false;

    $sampleIds   = $requestsData['sampleIds'] ?? [];
    $facilityIds = $requestsData['facilityIds'] ?? [];
    $requests    = $requestsData['requests'] ?? [];

    $payload = [
        'status'        => 'success',
        'requests'      => $requests,
        'testType'      => $testType,
        'labId'         => $labId,
        'syncSinceDate' => $syncSinceDate,
    ];

    $general->addApiTracking(
        $transactionId,
        'system',
        count($requests),
        'requests',
        $testType,
        $_SERVER['REQUEST_URI'] ?? null,
        JsonUtility::encodeUtf8Json($data),
        $payload,
        'json',
        $labId,
        null,
        $authToken,
        emptyPoll: count($requests) === 0
    );

    if ($facilityIds) {
        $general->updateTestRequestsSyncDateTime($testType, $facilityIds, $labId);
    }

    if ($sampleIds) {
        $sampleIds = array_values(array_unique(array_filter(
            $sampleIds,
            static fn($id): bool => $id !== null && $id !== ''
        )));

        $maxRetries = 5;

        foreach (array_chunk($sampleIds, 100) as $batch) {
            $attempt = 0;

            while (true) {
                $db->where($primaryKeyName, $batch, 'IN');
                // In flight until the lab's receipt confirms it; 1 for labs without receipts.
                $updateResult = $db->update(
                    $tableName,
                    ['data_sync' => $receiptsEnabled ? RequestsService::IN_FLIGHT : 1]
                );

                if ($updateResult !== false) {
                    break;
                }

                $errorCode = (int) $db->getLastErrno();

                if (!in_array($errorCode, [1205, 1213], true)) {
                    throw new SystemException(
                        $db->getLastError() ?: 'Failed to update data_sync flag',
                        $errorCode ?: 500
                    );
                }

                if ($attempt >= $maxRetries) {
                    throw new SystemException('Unable to mark samples as synced due to persistent database locks', 1205);
                }

                $attempt++;
                usleep((int) (100000 * $attempt)); // Back off progressively
                $db->reset(); // Clear state before retrying
            }
        }
    }

    $db->commitTransaction();

    // Success path: produce JSON; Apache adds br/gzip
    $procTimeMs = (int) round((microtime(true) - $procStart) * 1000);
    $responseHeaders = [
        'x-proc-time' => $procTimeMs,
    ];
    if ($contentLength > 0) {
        $responseHeaders['x-bytes-processed'] = $contentLength;
    }
    if ($receiptsEnabled) {
        $responseHeaders['x-request-receipts'] = 1;
        $responseHeaders['x-pending-remaining'] = (int) ($requestsData['pendingRemaining'] ?? 0);
    }

    echo ApiService::generateJsonResponse($payload, $request, $responseHeaders);
} catch (Throwable $e) {
    $db->rollbackTransaction();

    // Optional: set a safe message for prod (error generator reads this)
    $_SESSION['errorDisplayMessage'] = _translate('Unable to process the request');

    // Log extra context if you want
    LoggerUtility::logError($e->getFile() . ":" . $e->getLine() . ":" . $e->getMessage(), [
        'last_db_query' => $db->getLastQuery(),
        'last_db_error' => $db->getLastError(),
        'exception'     => $e,
    ]);

    // Re-throw so ErrorResponseGenerator builds the error JSON + status code
    throw new SystemException($e->getMessage(), ($e->getCode() ?: 500), $e);
}
