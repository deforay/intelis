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
use App\HttpHandlers\LegacyRequestHandler;
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
// The whole-window answer, written to a file (see below).
$bodyFile = null;
$body = null;

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
    // A number or a numeric string; anything else failed as a TypeError below (500).
    if (filter_var($labId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
        throw new SystemException('Lab ID in the request is not a lab id', 400);
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
    // A newer lab pulling a date or manifest window asks for it in pages; older
    // labs never send the key and get the whole window, as they always have.
    $page = RequestsService::readPage($data['page'] ?? null);

    if (!$testType) {
        throw new SystemException('Test Type is missing in the request', 400);
    }

    $tableName      = TestsService::getTestTableName($testType);
    $primaryKeyName = TestsService::getPrimaryColumn($testType);

    $facilityMapResult = $facilitiesService->getTestingLabFacilityMap($labId);

    // The fields around "requests", in the order the lab has always had them.
    $head = ['status' => 'success'];
    $tail = ['testType' => $testType, 'labId' => $labId, 'syncSinceDate' => $syncSinceDate];

    if ($page === null) {
        // The whole window, which every lab that does not page asks for. It is
        // written to a file as it is read, never held: one lab's window of 31,000
        // rows ran this STS out of memory, and the lab got a 500 on every pull.
        // The same bytes as before, so a lab of any version reads them unchanged.
        $bodyFile = tempnam(sys_get_temp_dir(), 'sts-requests-');
        $body = $bodyFile === false ? false : fopen($bodyFile, 'w+b');
        if ($body === false) {
            throw new SystemException('Could not open a file for the requests answer', 500);
        }
        RequestsService::write($body, substr((string) JsonUtility::encodeUtf8Json($head), 0, -1) . ',"requests":');
        $requestsData = $stsRequestsService->writeRequests(
            $body,
            $testType,
            $labId,
            $facilityMapResult ?? [],
            $manifestCode,
            $syncSinceDate,
            $wantsReceipts,
            $pendingOnly
        );
        RequestsService::write($body, ',' . substr((string) JsonUtility::encodeUtf8Json($tail), 1));
        $requestCount = $requestsData['count'];
        $payload = null;
    } else {
        $requestsData = $stsRequestsService->getRequests(
            $testType,
            $labId,
            $facilityMapResult ?? [],
            $manifestCode,
            $syncSinceDate,
            $wantsReceipts,
            $pendingOnly,
            $page
        );
        $requestCount = count($requestsData['requests'] ?? []);
        $payload = $head + ['requests' => $requestsData['requests'] ?? []] + $tail
            // Fields an older lab never reads: this is one page, and where the next starts.
            + RequestsService::pagingFields($requestsData);
    }
    // Only when the pending rows could be read; otherwise this pull is the plain
    // window and the lab is not told to send a receipt.
    $receiptsEnabled = $requestsData['receiptsEnabled'] ?? false;

    $sampleIds   = $requestsData['sampleIds'] ?? [];
    $facilityIds = $requestsData['facilityIds'] ?? [];

    $general->addApiTracking(
        $transactionId,
        'system',
        $requestCount,
        'requests',
        $testType,
        $_SERVER['REQUEST_URI'] ?? null,
        JsonUtility::encodeUtf8Json($data),
        $payload,
        'json',
        $labId,
        null,
        $authToken,
        emptyPoll: $requestCount === 0,
        // Request rows hold no secret, so the file is kept as it is.
        responseFile: $bodyFile
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

    if ($body === null) {
        echo ApiService::generateJsonResponse($payload, $request, $responseHeaders);
    } else {
        // The file is the response body: printing it would put the whole answer
        // in the page buffer. The emitter copies it out a piece at a time, after
        // the middleware has added its headers. Every database write is
        // committed above, so from the first byte on only the copy is left.
        ApiService::sendJsonHeaders($request, $responseHeaders);
        rewind($body);
        AppRegistry::set(LegacyRequestHandler::RESPONSE_BODY_FILE, $body);
        // The response owns the handle now. The name can go: the open handle
        // keeps the file readable until the emitter has copied it.
        $body = null;
    }
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
} finally {
    if (is_resource($body)) {
        fclose($body);
    }
    if (is_string($bodyFile)) {
        @unlink($bodyFile);
    }
}
