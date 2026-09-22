<?php

use Psr\Http\Message\ServerRequestInterface;
use JsonMachine\Items;
use App\Services\ApiService;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Services\STS\TokensService;
use App\Services\STS\LabMetadataService;
use App\Registries\ContainerRegistry;
use App\Services\InstrumentActivityService;
use App\Services\InstrumentUsageStatisticsService;
use App\Services\RejectionReasonMappingService;
use JsonMachine\JsonDecoder\ExtJsonDecoder;

require_once __DIR__ . "/../../../bootstrap.php";

ini_set('memory_limit', -1);
set_time_limit(0);
ini_set('max_execution_time', 300000);

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var ApiService $apiService */
$apiService = ContainerRegistry::get(ApiService::class);

/** @var TokensService $stsTokensService */
$stsTokensService = ContainerRegistry::get(TokensService::class);

try {
    $db->beginTransaction();

    /** @var ServerRequestInterface $request */
    $request = AppRegistry::get('request');
    $jsonResponse = $apiService->getJsonFromRequest($request);

    $apiRequestId  = $apiService->getHeader($request, 'X-Request-ID');
    $transactionId = $apiRequestId ?? MiscUtility::generateULID();

    $counter = 0;

    $labId = null;

    // Instrument activity and daily usage volume are not table-mapped like the rest.
    // They are stored through the same services the API and importer use, so a relayed
    // row, a direct API post and the importer all land on one row keyed by its own
    // identifier rather than by a local auto-increment. Collected here, applied once
    // labId is known.
    $instrumentActivityRows = [];
    $instrumentUsageRows = [];
    $rejectionReasonRows = [];

    $tableMap = [
        'labStorage' => [
            'primaryKey' => 'storage_id',
            'table' => 'lab_storage'
        ],
        'labStorageHistory' => [
            'primaryKey' => 'history_id',
            'table' => 'lab_storage_history'
        ],
        'instruments' => [
            'primaryKey' => 'instrument_id',
            'table' => 'instruments'
        ],
        'instrumentMachines' => [
            'primaryKey' => 'config_machine_id',
            'table' => 'instrument_machines'
        ],
        'instrumentControls' => [
            'primaryKey' => 'instrument_id',
            'table' => 'instrument_controls'
        ],
        'users' => [
            'primaryKey' => 'user_id',
            'table' => 'user_details'
        ],
    ];

    if (!empty($jsonResponse) && $jsonResponse != '[]' && JsonUtility::isJSON($jsonResponse)) {

        $data = [];
        $options = [
            'decoder' => new ExtJsonDecoder(true, 512, JSON_THROW_ON_ERROR)
        ];
        $parsedData = Items::fromString($jsonResponse, $options);
        $tableInfo = [];
        $i = 1;
        foreach ($parsedData as $name => $data) {
            if ($name === 'labId') {
                $labId = $data;
                continue;
            }
            if ($name === 'instrumentActivity') {
                $instrumentActivityRows = is_array($data) ? $data : [];
                continue;
            }
            if ($name === 'instrumentUsageStatistics') {
                $instrumentUsageRows = is_array($data) ? $data : [];
                continue;
            }
            // Reason tables are NOT in $tableMap on purpose: every table there is
            // upserted on its own primary key, and rejection_reason_id is a per-install
            // auto-increment. Upserting one would overwrite this server's reason 26 with
            // whatever a lab happens to call its 26. They are resolved by name instead,
            // below, once the payload's lab is authenticated.
            if (str_starts_with((string) $name, 'rejectionReasons:')) {
                $reasonTestType = substr((string) $name, strlen('rejectionReasons:'));
                if (RejectionReasonMappingService::reasonTableFor($reasonTestType) !== null) {
                    $rejectionReasonRows[$reasonTestType] = is_array($data) ? $data : [];
                }
                continue;
            }
            if (isset($tableMap[$name])) {
                $tableInfo['primaryKey'][$i] = $tableMap[$name]['primaryKey'];
                $tableInfo['table'][$i] = $tableMap[$name]['table'];
                $tableInfo['data'][$i] = $data;
                $i++;
            }
        }

        // The payload is fully read but nothing is written yet: authenticate before any
        // of it lands. The token must belong to the facility the payload claims, so a
        // lab cannot file another lab's metadata -- or the relayed activity and usage
        // below -- under a labId that is not its own. Same check the result and request
        // receivers already apply.
        $authToken = ApiService::extractBearerToken($request);
        if (!$stsTokensService->validateToken($authToken, (int) $labId)) {
            http_response_code(401);
            throw new SystemException(
                'Unauthorized Access on lab metadata sync: ' . $stsTokensService->getLastValidationFailure(),
                401
            );
        }


        /** @var LabMetadataService $labMetadataService */
        $labMetadataService = ContainerRegistry::get(LabMetadataService::class);
        $counter += $labMetadataService->storeTables($tableInfo);
    }

    // The lab is the payload's labId -- the same source every table above is stored
    // under on this endpoint. Storing is idempotent on each row's own identifier, so a
    // row already held (from an earlier relay, a direct API post, or the importer) is a
    // no-op rather than a duplicate. The services read only known fields, so a relayed
    // row's local activity_id / usage_statistic_id and its lab_id are ignored: the lab
    // written is the one passed here.
    if (!empty($labId)) {
        if ($instrumentActivityRows !== []) {
            /** @var InstrumentActivityService $instrumentActivityService */
            $instrumentActivityService = ContainerRegistry::get(InstrumentActivityService::class);
            $instrumentActivityService->store(
                $instrumentActivityRows,
                (int) $labId,
                InstrumentActivityService::VIA_RELAY
            );
            $counter += count($instrumentActivityRows);
        }
        if ($rejectionReasonRows !== []) {
            // What each of this lab's reason ids means here. Matching is by name, so a
            // wording already on the national list resolves to it and only a genuinely
            // new one is added. Nothing the lab holds is changed -- its ids stay its own;
            // this only records how to read them.
            /** @var RejectionReasonMappingService $rejectionReasonMappingService */
            $rejectionReasonMappingService = ContainerRegistry::get(RejectionReasonMappingService::class);
            foreach ($rejectionReasonRows as $reasonTestType => $reasonRows) {
                if ($reasonRows === []) {
                    continue;
                }
                $stats = $rejectionReasonMappingService->ingestLabReasons(
                    $reasonTestType,
                    (int) $labId,
                    $reasonRows
                );
                // Only reasons new to this server count. Labs send their whole
                // reason list on every run, so counting what was mapped made every
                // metadata sync look like it moved data.
                $counter += $stats['created'];
                if ($stats['created'] > 0) {
                    LoggerUtility::logInfo('Lab contributed new rejection reasons', [
                        'labId' => (int) $labId,
                        'testType' => $reasonTestType,
                        'created' => $stats['created'],
                        'mapped' => $stats['mapped'],
                    ]);
                }
            }
        }
        if ($instrumentUsageRows !== []) {
            /** @var InstrumentUsageStatisticsService $instrumentUsageStatisticsService */
            $instrumentUsageStatisticsService = ContainerRegistry::get(InstrumentUsageStatisticsService::class);
            $instrumentUsageStatisticsService->store(
                $instrumentUsageRows,
                (int) $labId,
                InstrumentUsageStatisticsService::VIA_RELAY
            );
            $counter += count($instrumentUsageRows);
        }
    }

    $payload = json_encode([
        'status' => 'success',
        'message' => 'Metadata synced successfully'
    ]);

    $general->addApiTracking($transactionId, 'intelis-system', $counter, 'system-metadata-sync', 'common', $_SERVER['REQUEST_URI'], $jsonResponse, $payload, 'json', $labId, emptyPoll: $counter === 0);
    $db->commitTransaction();
} catch (Throwable $e) {
    $db->rollbackTransaction();

    // An empty body told the sender nothing about why the upload failed. Client errors
    // (a bad or expired token above all) carry their reason back so the lab sees it in
    // its own sync output instead of only in this server's log.
    $statusCode = (int) $e->getCode();
    if ($statusCode >= 400 && $statusCode <= 499) {
        http_response_code($statusCode);
        $payload = json_encode([
            'status' => 'error',
            'error' => $e->getMessage(),
        ]);
    } else {
        http_response_code(500);
        $payload = json_encode([
            'status' => 'error',
            'error' => 'Metadata sync failed on the server',
        ]);
    }

    LoggerUtility::logError($e->getMessage(), [
        'labId' => $labId ?? null,
        'transactionId' => $transactionId ?? null,
        'clientIp' => CommonService::getClientIpAddress($request ?? null),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'last_db_errno' => $db->getLastErrno(),
        'last_db_query' => $db->getLastQuery(),
        'last_db_error' => $db->getLastError(),
        'trace' => $e->getTraceAsString(),
    ]);
}
header('Content-Type: application/json');
echo ApiService::generateJsonResponse($payload, $request);
