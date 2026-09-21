<?php
use const SAMPLE_STATUS\RECEIVED_AT_CLINIC;

// tasks/remote/results-sender.php
$cliMode = php_sapi_name() === 'cli';

if ($cliMode) {
    require_once __DIR__ . "/../../../bootstrap.php";
}

ini_set('memory_limit', -1);
set_time_limit(0);
ini_set('max_execution_time', 300000);

if (!defined('RESULTS_SENDER_DEFAULT_CHUNK_SIZE')) {
    define('RESULTS_SENDER_DEFAULT_CHUNK_SIZE', 1000);
}

// Bound decompressed JSON as well as record count. A single sample stays intact.
if (!defined('RESULTS_SENDER_MAX_PAYLOAD_BYTES')) {
    define('RESULTS_SENDER_MAX_PAYLOAD_BYTES', 2 * 1024 * 1024);
}

// Exit code when --wait-for-lock gives up: the run did not happen and can be retried.
if (!defined('RESULTS_SENDER_EXIT_LOCKED')) {
    define('RESULTS_SENDER_EXIT_LOCKED', 75);
}

// Services & utilities
use App\Services\TbService;
use App\Services\ApiService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Utilities\ResultSyncBatch;
use App\Utilities\ResultSyncBatchSize;
use App\Utilities\ResultSyncPayload;
use App\Utilities\ResultSyncAcknowledgement;
use App\Services\CommonService;
use App\Services\TestsService;
use App\Services\Covid19Service;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use App\Services\GenericTestsService;

// Symfony Console
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Output\ConsoleOutput;

$io = new SymfonyStyle(new ArgvInput(), new ConsoleOutput());


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var ApiService $apiService */
$apiService = ContainerRegistry::get(ApiService::class);

$labId = $general->getSystemConfig('sc_testing_lab_id');
$isLIS = $general->isLISInstance();

if (false == $isLIS) {
    LoggerUtility::logError("This instance is not configured as LIS. Exiting.");
    if ($cliMode) {
        $io->error("This instance is not configured as LIS. Exiting.");
    }
    exit(0);
}


if (null == $labId || '' == $labId) {
    LoggerUtility::logError("Please check if Testing Lab ID is set");
    if ($cliMode) {
        $io->error("Testing Lab ID is not set in System Config. Exiting.");
    }
    exit(0);
}

/**
 * Display help/usage information
 */
function showHelp(SymfonyStyle $io): void
{
    $io->title('InteLIS / VLSM — Remote Results Sender');

    $io->section('Description');
    $io->text([
        'Sends test results from the local database to the remote STS server.',
        'Supports modules: VL, EID, COVID-19, Hepatitis, TB, CD4, and Generic Tests.',
    ]);

    $io->section('Usage');
    $io->text('php results-sender.php [MODULE] [<date>|<days>] [silent]');
    $io->text('php results-sender.php [--help|-h|help]');

    $io->section('Positionals / Flags');
    $io->definitionList(
        ['MODULE' => 'Optional. One of: vl, eid, covid19, hepatitis, tb, cd4, generic-tests'],
        ['-t, --test' => 'Optional. Same as MODULE positional; accepts a value (e.g. -t vl)'],
        ['<date>' => 'Optional. Send results modified since date (YYYY-MM-DD), e.g. 2025-01-01'],
        ['<days>' => 'Optional. Send results modified in the last N days, e.g. 7'],
        ['silent' => 'Optional. Suppresses certain notifications / timestamp bumps where applicable'],
        ['-c, --chunk-size' => 'Optional. Number of records per request (default ' . RESULTS_SENDER_DEFAULT_CHUNK_SIZE . ')'],
        ['--max-payload-bytes' => 'Optional. Maximum JSON bytes before gzip (default '
            . RESULTS_SENDER_MAX_PAYLOAD_BYTES . '). A single sample stays intact'],
        ['--dry-run, dry-run' => 'Optional. Select and chunk rows, report what would be sent, but send nothing and update nothing'],
        ['--wait-for-lock[=seconds]' => 'Optional. If another sync is running, wait for it (default 900 seconds) instead of exiting.'
            . ' Exits ' . RESULTS_SENDER_EXIT_LOCKED . ' if it is still running after that'],
        ['-h, --help, help' => 'Show this help and exit']
    );

    $io->section('Examples');
    $io->listing([
        'php results-sender.php',
        'php results-sender.php vl',
        'php results-sender.php -t vl',
        'php results-sender.php 7',
        'php results-sender.php 2025-01-01',
        'php results-sender.php covid19 3',
        'php results-sender.php eid silent',
        'php results-sender.php hepatitis 2025-01-01 silent',
        'php results-sender.php vl -c 500',
        'php results-sender.php vl 2025-01-01 --dry-run',
    ]);

    $io->section('Notes');
    $io->note([
        'Requires internet connectivity to the STS server.',
        'Lab ID must be configured in System Config.',
        'Only results with result_status != RECEIVED_AT_CLINIC are sent.',
        'Results must have a valid sample_code.',
        'By default, only unsynced results (data_sync = 0) are sent.',
        'When a date/days is specified, data_sync is ignored.',
        'With --dry-run, rows are selected and chunked but nothing is posted, no sync flags change, and nothing is tracked.',
        'All operations are logged and tracked for audit.',
    ]);

    $io->section('Result Status After Successful Sync');
    $io->text([
        '• data_sync is set to 1',
        '• result_sent_to_source is set to "sent"',
        '• last_remote_results_sync is updated',
    ]);

    exit(0);
}

/**
 * Build payload of referral manifests for a given test type based on selected rows.
 */
function buildReferralManifestsPayload(DatabaseService $db, string $testType, ?array $selectedRows): array
{
    if ($selectedRows === null || $selectedRows === [] || !is_array($selectedRows)) {
        return [];
    }

    // Detect nested form_data rows (['form_data' => [...]]) vs flat rows
    $first = reset($selectedRows);
    $hasNestedFormData = is_array($first) && array_key_exists('form_data', $first);

    // Collect distinct package codes
    $codes = $hasNestedFormData
        ? array_column(array_column($selectedRows, 'form_data'), 'referral_manifest_code')
        : array_column($selectedRows, 'referral_manifest_code');

    $codes = array_values(array_unique(array_filter($codes, static fn($v): bool => !empty($v))));
    if ($codes === []) {
        return [];
    }

    // fetch manifests data matching these manifest codes
    $db->reset();
    $db->where('manifest_type', 'referral');
    $db->where('module', $testType);
    $db->where('manifest_code', $codes, 'IN');

    $rows = $db->get('specimen_manifests');
    return $rows ?: [];
}

/**
 * Decode list of acknowledged sample codes returned by STS.
 *
 * @return array<int,string>
 */
function decodeAcknowledgedSampleCodes(string $jsonResponse, string $testType): array
{
    $decoded = json_decode($jsonResponse, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        $message = json_last_error_msg();
        throw new RuntimeException("Failed to decode $testType acknowledgement: $message");
    }

    if (!is_array($decoded)) {
        throw new RuntimeException("Unexpected acknowledgement format received for $testType results.");
    }

    $filtered = array_filter(
        $decoded,
        static fn($code): bool => is_string($code) && $code !== ''
    );

    return array_values(array_unique($filtered));
}

/**
 * Unpack API responses that include status/headers.
 *
 * @return array{body:string,headers:array<string,string>}
 */
function unpackApiResponse(array|string|null $response): array
{
    if (is_array($response)) {
        $headers = is_array($response['headers'] ?? null) ? $response['headers'] : [];
        $body = (string) ($response['body'] ?? '');
        return ['body' => $body, 'headers' => array_change_key_case($headers, CASE_LOWER)];
    }
    return ['body' => (string) ($response ?? ''), 'headers' => []];
}

/**
 * Unpack an API response, show hints in CLI, and return body, next chunk size and headers.
 *
 * @return array{0:string,1:int,2:array<string,string>}
 */
function handleApiResponse(
    array|string|null $apiResponse,
    bool $cliMode,
    ?SymfonyStyle $io,
    int $currentChunkSize,
    ?string $label = null,
    int $maximum = 1000,
    float $seconds = 0
): array {
    $unpackedResponse = unpackApiResponse($apiResponse);
    if ($cliMode) {
        showServerHints($io, $unpackedResponse['headers'], $label);
    }
    $nextChunkSize = ResultSyncBatchSize::next($currentChunkSize, $maximum, $unpackedResponse['headers'], $seconds);
    return [$unpackedResponse['body'], $nextChunkSize, $unpackedResponse['headers']];
}

/**
 * Mark the sent rows the STS acknowledged, and only while unchanged since read.
 * An updated STS answers with the lab's own identifiers (X-Ack-Format: unique_id);
 * an older one answers with sample codes.
 *
 * @param array<array-key, mixed> $sentResults The results array that was posted.
 * @param list<string> $acknowledged
 * @param array<string, string> $headers
 */
function markAcknowledgedResults(
    DatabaseService $db,
    string $table,
    string $primaryKey,
    array $sentResults,
    array $acknowledged,
    array $headers
): int {
    $byUniqueId = ($headers['x-ack-format'] ?? '') === ResultSyncAcknowledgement::UNIQUE_ID;
    $rows = ResultSyncAcknowledgement::acknowledgedRows(
        ResultSyncAcknowledgement::sentRows($sentResults),
        $acknowledged,
        $byUniqueId
    );
    return ResultSyncAcknowledgement::markSynced($db, $table, $primaryKey, $rows);
}

function reportMarkedResults(SymfonyStyle $io, int $acknowledged, int $marked, string $seconds): void
{
    $io->text("Marked $marked of $acknowledged acknowledged row(s) as synced in {$seconds}s");
    if ($marked < $acknowledged) {
        $io->comment('Unmarked rows stay pending: edited while their request was in flight, or acknowledged under a code this lab did not send.');
    }
}

/** An unreadable acknowledgment stops this module only; the next module still runs. */
function reportModuleSyncFailure(?SymfonyStyle $io, string $label, int $chunkNumber, Throwable $e): void
{
    LoggerUtility::logError("Results sync for $label stopped at chunk $chunkNumber: " . $e->getMessage());
    $io?->error(strtoupper($label) . " sync stopped at chunk $chunkNumber: " . $e->getMessage()
        . '. Unacknowledged rows stay pending; continuing with the next module.');
}

/**
 * Display server hint headers when running via CLI for operator awareness.
 */
function showServerHints(?SymfonyStyle $io, array $headers, ?string $label = null): void
{
    if ($io === null) {
        return;
    }

    $procMs = $headers['x-proc-time'] ?? null;
    $bytes = $headers['x-bytes-processed'] ?? null;

    $messages = [];
    if (is_numeric($procMs)) {
        $messages[] = sprintf('proc-time: %d ms', (int) $procMs);
    }
    if (is_numeric($bytes)) {
        $messages[] = sprintf('bytes-processed: %s', number_format((int) $bytes));
    }

    if ($messages !== []) {
        $prefix = $label ? strtoupper($label) . ' ' : '';
        $io->text($prefix . 'STS ' . implode(' | ', $messages));
    }
}

/** Report preparation work and payload size without exposing patient data. */
function reportPreparedResult(?SymfonyStyle $io, ResultSyncBatch $resultBatch, array $preparedRequest): void
{
    if ($preparedRequest['oversized']) {
        LoggerUtility::logWarning('A single result exceeds the sync payload byte limit', [
            'testType' => $preparedRequest['payload']['testType'],
            'bytes' => $preparedRequest['bytes'],
        ]);
        $io?->warning('One sample exceeds the payload limit. Sending it intact.');
    }
    if ($io !== null) {
        $timings = $resultBatch->timings();
        $io->comment(sprintf(
            'Source batch preparation: parent rows %.1f ms, child data/payload %.1f ms',
            $timings['readMs'],
            $timings['prepareMs']
        ));
        $io->text(sprintf('JSON payload: %s bytes before gzip', number_format($preparedRequest['bytes'])));
    }
}

/**
 * Report what a dry run would have sent for one module.
 * Rows are either flat records or nested ['form_data' => [...]] payload entries.
 */
function reportDryRunChunks(SymfonyStyle $io, string $label, array $rows, int $totalChunks, int $count): void
{
    $codes = [];
    foreach ($rows as $row) {
        $code = $row['sample_code'] ?? ($row['form_data']['sample_code'] ?? null);
        if (!empty($code)) {
            $codes[] = $code;
        }
        if (count($codes) >= 10) {
            break;
        }
    }

    $io->text(sprintf('DRY RUN: would send %d %s row(s) in at least %d chunk(s) before payload-size splitting', $count, strtoupper($label), $totalChunks));
    if ($codes !== []) {
        $suffix = $count > count($codes) ? ', ...' : '';
        $io->text('Sample codes: ' . implode(', ', $codes) . $suffix);
    }
}



// Check for help flag early
if ($cliMode) {
    $args = array_slice($_SERVER['argv'], 1);
    if (in_array('-h', $args) || in_array('--help', $args) || in_array('help', $args)) {
        showHelp($io);
    }
    $io->title("Starting results sending");
}
$remoteURL = $general->getRemoteURL();

if (empty($remoteURL)) {
    LoggerUtility::logError("Please check if STS URL is set");
    if ($cliMode) {
        $io->error("STS URL is not set in System Config. Exiting.");
    }
    exit(0);
}


$version = VERSION;
$systemConfig = SYSTEM_CONFIG; // putting this into a variable to make this editable
$stsBearerToken = $general->getSTSToken();
$apiService->setBearerToken($stsBearerToken);

$isSilent = false;
$isDryRun = false;
$waitForLockSeconds = null;
$syncSinceDate = null;
$forceSyncModule = null;
$sampleCode = null;
$chunkSize = RESULTS_SENDER_DEFAULT_CHUNK_SIZE;
$maxPayloadBytes = RESULTS_SENDER_MAX_PAYLOAD_BYTES;

if ($cliMode) {
    $validModules = TestsService::getActiveTests();
    if ($validModules === []) {
        $validModules = array_keys(TestsService::getTestTypes());
    }
    $awaitingTestType = false;
    $awaitingChunkSize = false;
    $awaitingPayloadBytes = false;

    foreach ($argv as $index => $arg) {
        if ($index === 0) {
            continue;
        }

        $arg = trim($arg);

        if ($awaitingPayloadBytes || str_starts_with($arg, '--max-payload-bytes=')) {
            $value = $awaitingPayloadBytes ? $arg : substr($arg, strlen('--max-payload-bytes='));
            $validated = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($validated === false) {
                $io->error("Payload byte limit must be a positive integer. Received: $value");
                exit(1);
            }
            $maxPayloadBytes = $validated;
            $awaitingPayloadBytes = false;
            continue;
        }

        if ($awaitingTestType) {
            $moduleCandidate = strtolower($arg);
            if (!in_array($moduleCandidate, $validModules, true)) {
                $io->error("Invalid test type specified for -t/--test: $arg");
                exit(1);
            }
            $forceSyncModule = $moduleCandidate;
            $awaitingTestType = false;
            continue;
        }

        if ($awaitingChunkSize) {
            if (!ctype_digit($arg) || (int) $arg < 1) {
                $io->error("Chunk size must be a positive integer. Received: $arg");
                exit(1);
            }
            $chunkSize = max(1, (int) $arg);
            $awaitingChunkSize = false;
            continue;
        }

        if ($arg === 'silent') {
            $isSilent = true;
        } elseif ($arg === '--dry-run' || $arg === 'dry-run') {
            $isDryRun = true;
        } elseif ($arg === '--wait-for-lock') {
            $waitForLockSeconds = 900;
        } elseif (str_starts_with($arg, '--wait-for-lock=')) {
            $value = substr($arg, strlen('--wait-for-lock='));
            $validated = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($validated === false) {
                $io->error("Lock wait must be a positive number of seconds. Received: $value");
                exit(1);
            }
            $waitForLockSeconds = $validated;
        } elseif ($arg === '--max-payload-bytes') {
            $awaitingPayloadBytes = true;
        } elseif ($arg === '-t' || $arg === '--test') {
            $awaitingTestType = true;
        } elseif ($arg === '-c' || $arg === '--chunk-size') {
            $awaitingChunkSize = true;
        } elseif (str_starts_with($arg, '--test=')) {
            $moduleCandidate = strtolower(substr($arg, strlen('--test=')));
            if (!in_array($moduleCandidate, $validModules, true)) {
                $io->error("Invalid test type specified for --test: $moduleCandidate");
                exit(1);
            }
            $forceSyncModule = $moduleCandidate;
        } elseif (str_starts_with($arg, '--chunk-size=')) {
            $value = substr($arg, strlen('--chunk-size='));
            if (!ctype_digit($value) || (int) $value < 1) {
                $io->error("Chunk size must be a positive integer. Received: $value");
                exit(1);
            }
            $chunkSize = max(1, (int) $value);
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $arg) && DateUtility::isDateFormatValid($arg, 'Y-m-d')) {
            $syncSinceDate ??= DateUtility::getDateTime($arg, 'Y-m-d');
        } elseif (is_numeric($arg)) {
            $syncSinceDate ??= DateUtility::daysAgo((int) $arg);
        } elseif (in_array(strtolower($arg), $validModules, true)) {
            $forceSyncModule = strtolower($arg);
        } else {
            $io->error("Invalid argument: $arg");
            exit(1);
        }
    }

    if ($awaitingPayloadBytes) {
        $io->error('Missing value after --max-payload-bytes');
        exit(1);
    }

    if ($awaitingTestType) {
        $io->error("Missing test type value after -t/--test");
        exit(1);
    }

    if ($awaitingChunkSize) {
        $io->error("Missing chunk size value after -c/--chunk-size");
        exit(1);
    }

    if ($syncSinceDate !== null) {
        $syncSinceDate = DateUtility::getDateTime($syncSinceDate, 'Y-m-d H:i:s');
        $io->text("Syncing results since: $syncSinceDate");
    }

    if ($forceSyncModule !== null) {
        $io->text("Forcing module sync for: $forceSyncModule");
    }

    $io->text("Maximum records per request: $chunkSize");
    $io->text("Maximum JSON bytes before gzip: $maxPayloadBytes (except oversized single samples)");

    if ($isDryRun) {
        $io->warning('DRY RUN: nothing will be sent to STS and no local sync flags will be updated.');
    }
}

// One sender at a time: cron, the "sync this sample" button and remote commands
// can all start it. flock() belongs to this process, not a database connection,
// so a reconnect cannot silently release it; the OS releases it when we exit.
// Cron and the button skip a busy run (the next cron run covers them). A remote
// resend passes --wait-for-lock: its command status is final, so it must either
// run or exit non-zero, never report "completed" for work it skipped.
$senderLockPath = VAR_PATH . DIRECTORY_SEPARATOR . 'results-sender.lock';
// Whoever did not create the file (root's cron or the web user) opens it
// read-only; flock() works on a read-only handle, so no chmod is needed.
$senderLock = @fopen($senderLockPath, 'c') ?: @fopen($senderLockPath, 'r');
$lockAcquired = $senderLock !== false && flock($senderLock, LOCK_EX | LOCK_NB);
if ($senderLock !== false && !$lockAcquired && $waitForLockSeconds !== null) {
    if ($cliMode) {
        $io->text("Another results sync is running. Waiting up to {$waitForLockSeconds}s...");
    }
    $deadline = time() + $waitForLockSeconds;
    while (!$lockAcquired && time() < $deadline) {
        sleep(2);
        $lockAcquired = flock($senderLock, LOCK_EX | LOCK_NB);
    }
}
if ($senderLock === false) {
    // Never block syncing over an unwritable lock file; just run unguarded.
    LoggerUtility::logWarning("Results sender could not open its lock file: $senderLockPath");
} elseif (!$lockAcquired) {
    $waited = $waitForLockSeconds !== null;
    LoggerUtility::logInfo('Results sender is already running. ' . ($waited ? 'Gave up waiting.' : 'Exiting.'));
    if ($cliMode) {
        $io->warning($waited
            ? 'Another results sync is still running. Nothing was sent; retry later.'
            : 'Another results sync is already running. Exiting.');
    }
    exit($waited ? RESULTS_SENDER_EXIT_LOCKED : 0);
}

// Keep the operator's requested size as a ceiling throughout adaptive batching.
$maxChunkSize = max(1, $chunkSize);
$nextBatchSize = static function () use (&$chunkSize): int {
    return max(1, $chunkSize);
};

// Web fallback: the request pages' "sync this sample" button passes both values.
// Without the module, the sample filter never applies and every module syncs.
if (!$cliMode) {
    $forceSyncModule ??= is_string($_GET['forceSyncModule'] ?? null) ? $_GET['forceSyncModule'] : null;
    $sampleCode ??= is_string($_GET['sampleCode'] ?? null) ? trim($_GET['sampleCode']) : null;
}
$forceSyncModule = $forceSyncModule ? strtolower(trim($forceSyncModule)) : null;
if ($forceSyncModule !== null && !in_array($forceSyncModule, TestsService::getActiveTests() ?: array_keys(TestsService::getTestTypes()), true)) {
    LoggerUtility::logError("Results sync requested for an inactive or unknown module: $forceSyncModule");
    exit(0);
}
// Exact match on a quoted literal: the value can come from the query string.
$sampleCodeClause = static fn(string $column): string => " AND $column = " . $db->quote($sampleCode);

// If module is forced, override modules config
if ($forceSyncModule !== null && $forceSyncModule !== '' && $forceSyncModule !== '0') {
    unset($systemConfig['modules']);
    $systemConfig['modules'][$forceSyncModule] = true;
}

// Sending results to /v2/results.php for all test types
$url = "$remoteURL/remote/v2/results.php";

$queryResults = static function (string $sql, array $params) use ($db): array {
    $db->reset();
    return $db->rawQuery($sql, $params);
};

$buildResultPayload = static function (array $chunk, string $testType) use ($labId, $general, $isSilent, $db): array {
    $payload = [
        'labId' => $labId,
        'results' => $chunk,
        'testType' => $testType,
        'timestamp' => DateUtility::getCurrentTimestamp(),
        'instanceId' => $general->getInstanceId(),
        // An updated STS then acknowledges by unique_id; an older one ignores this.
        'ackFormat' => ResultSyncAcknowledgement::UNIQUE_ID,
    ];
    if ($testType === 'generic-tests') {
        $payload['silent'] = $isSilent;
    } elseif ($testType === 'tb') {
        $payload['manifests'] = buildReferralManifestsPayload($db, 'tb', $chunk);
    }
    return $payload;
};

try {
    // Check network
    if (false == CommonService::validateStsUrl($remoteURL, $labId)) {
        LoggerUtility::logError("No network connectivity while trying remote sync.");
        return false;
    }

    $transactionId = MiscUtility::generateULID();

    // ----------------------- GENERIC TESTS -----------------------
    if (isset($systemConfig['modules']['generic-tests']) && $systemConfig['modules']['generic-tests'] === true) {
        if ($cliMode) {
            $io->section("Custom Tests");
            $io->text("Selecting rows...");
        }
        $t = MiscUtility::startTimer();

        $genericQuery = "SELECT generic.*, a.user_name as 'approved_by_name'
                FROM `form_generic` AS generic
                LEFT JOIN `user_details` AS a ON generic.result_approved_by = a.user_id
                WHERE result_status != " . RECEIVED_AT_CLINIC . "
                AND IFNULL(generic.sample_code, '') != ''";

        if ($forceSyncModule !== null && $forceSyncModule !== '' && $forceSyncModule !== '0' && trim((string) $forceSyncModule) === "generic-tests" && !empty($sampleCode)) {
            $genericQuery .= $sampleCodeClause('generic.sample_code');
        }

        if (null !== $syncSinceDate) {
            $genericQuery .= " AND generic.last_modified_datetime >= '$syncSinceDate'";
        } else {
            $genericQuery .= " AND generic.data_sync = 0";
        }

        $db->reset();
        $resultBatch = new ResultSyncBatch(
            $queryResults,
            $genericQuery,
            'generic.sample_id',
            keyByUniqueId: true
        );
        $count = count($resultBatch);

        $acked = 0;
        $totalChunks = 0;
        $chunksProcessed = 0;

        if ($count === 0) {
            if ($cliMode) {
                $io->text("Nothing to send for Custom Tests.");
            }
        } else {

            if ($cliMode) {
                $io->text("Selected $count row(s) in " . MiscUtility::elapsedTime($t) . "s");
            }
            /** @var GenericTestsService $genericService */
            $genericService = ContainerRegistry::get(GenericTestsService::class);

            $totalChunks = (int) ceil($count / max(1, $chunkSize));
            $chunks = $resultBatch->chunks(
                $nextBatchSize,
                prepareBatch: static function (array $rows) use ($genericService): array {
                    $children = $genericService->getTestsByGenericSampleIds(array_column($rows, 'sample_id'));
                    return ResultSyncBatch::nestedPayload(
                        $rows,
                        $children ?? [],
                        'sample_id'
                    );
                }
            );

            if ($isDryRun) {
                reportDryRunChunks($io, 'generic-tests', $resultBatch->preview(), $totalChunks, $count);
                $chunks = [];
            }

            $requests = ResultSyncPayload::requests(
                $chunks,
                static fn(array $chunk): array => $buildResultPayload($chunk, 'generic-tests'),
                $maxPayloadBytes,
                $nextBatchSize
            );
            foreach ($requests as $chunkIndex => $preparedRequest) {
                $payload = $preparedRequest['payload'];
                $chunk = $payload['results'];
                reportPreparedResult($cliMode ? $io : null, $resultBatch, $preparedRequest);
                $chunksProcessed++;
                $chunkNumber = $chunkIndex + 1;
                $chunkCount = count($chunk);

                if ($cliMode) {
                    $io->text(sprintf(
                        "Posting chunk %d (%d record%s) to %s...",
                        $chunkNumber,
                        $chunkCount,
                        $chunkCount === 1 ? '' : 's',
                        $remoteURL
                    ));
                }
                $tPost = MiscUtility::startTimer();

                $apiResponse = $apiService->post($url, $preparedRequest['json'], gzip: true, returnWithStatusCode: true);
                [$jsonResponse, $chunkSize, $responseHeaders] = handleApiResponse(
                    $apiResponse, $cliMode, $io, $chunkSize, 'generic-tests', $maxChunkSize, microtime(true) - $tPost
                );
                if ($cliMode) {
                    $io->comment("Chunk $chunkNumber POST completed in " . MiscUtility::elapsedTime($tPost) . "s");
                }

                try {
                    $acknowledgedSamples = decodeAcknowledgedSampleCodes($jsonResponse, 'generic-tests');
                } catch (RuntimeException $e) {
                    reportModuleSyncFailure($cliMode ? $io : null, 'generic-tests', $chunkNumber, $e);
                    break;
                }
                $acked += count($acknowledgedSamples);

                if ($acknowledgedSamples !== []) {
                    $tUpd = MiscUtility::startTimer();
                    $marked = markAcknowledgedResults($db, 'form_generic', 'sample_id', $chunk, $acknowledgedSamples, $responseHeaders);
                    if ($cliMode) {
                        reportMarkedResults($io, count($acknowledgedSamples), $marked, MiscUtility::elapsedTime($tUpd));
                    }
                }

                if ($cliMode) {
                    $io->comment(sprintf('Acknowledged %d/%d so far', $acked, $count));
                }

                $chunkTransactionId = $transactionId . '-generic-tests-' . str_pad((string) $chunkNumber, 3, '0', STR_PAD_LEFT);
                $general->addApiTracking(
                    $chunkTransactionId,
                    'intelis-system',
                    $chunkCount,
                    'send-results',
                    'generic-tests',
                    $url,
                    $payload,
                    $acknowledgedSamples,
                    'json',
                    $labId
                );
            }

            if ($cliMode && !$isDryRun) {
                $io->success("Custom Tests: acknowledged $acked / $count row(s). Total " . MiscUtility::elapsedTime($t) . "s");
            }
        }

        $summaryRequest = [
            'recordsSelected' => $count,
            'chunkSize' => $chunkSize,
            'chunksProcessed' => $chunksProcessed,
        ];
        $summaryResponse = [
            'recordsAcknowledged' => $acked,
        ];
        if (!$isDryRun) {
            $general->addApiTracking(
                $transactionId,
                'intelis-system',
                $count,
                'send-results',
                'generic-tests',
                $url,
                $summaryRequest,
                $summaryResponse,
                'json',
                $labId,
                emptyPoll: $count === 0
            );
        }
    }

    // ----------------------- VL -----------------------
    if (isset($systemConfig['modules']['vl']) && $systemConfig['modules']['vl'] === true) {
        if ($cliMode) {
            $io->section("HIV Viral Load");
            $io->text("Selecting rows...");
        }
        $t = MiscUtility::startTimer();

        $vlQuery = "SELECT vl.*, a.user_name as 'approved_by_name'
            FROM `form_vl` AS vl
            LEFT JOIN `user_details` AS a ON vl.result_approved_by = a.user_id
            WHERE result_status != " . RECEIVED_AT_CLINIC . "
            AND IFNULL(vl.sample_code, '') != ''";

        if ($forceSyncModule !== null && $forceSyncModule !== '' && $forceSyncModule !== '0' && trim((string) $forceSyncModule) === "vl" && !empty($sampleCode)) {
            $vlQuery .= $sampleCodeClause('vl.sample_code');
        }
        if (null !== $syncSinceDate) {
            $vlQuery .= " AND vl.last_modified_datetime >= '$syncSinceDate'";
        } else {
            $vlQuery .= " AND vl.data_sync = 0";
        }

        $db->reset();
        $resultBatch = new ResultSyncBatch(
            $queryResults,
            $vlQuery,
            'vl.vl_sample_id'
        );
        $count = count($resultBatch);

        $acked = 0;
        $totalChunks = 0;
        $chunksProcessed = 0;

        if ($count === 0) {
            if ($cliMode) {
                $io->text("Nothing to send for VL.");
            }
        } else {
            if ($cliMode) {
                $io->text("Selected $count row(s) in " . MiscUtility::elapsedTime($t) . "s");
            }
            $totalChunks = (int) ceil($count / max(1, $chunkSize));
            $chunks = $resultBatch->chunks($nextBatchSize);

            if ($isDryRun) {
                reportDryRunChunks($io, 'vl', $resultBatch->preview(), $totalChunks, $count);
                $chunks = [];
            }

            $requests = ResultSyncPayload::requests(
                $chunks,
                static fn(array $chunk): array => $buildResultPayload($chunk, 'vl'),
                $maxPayloadBytes,
                $nextBatchSize
            );
            foreach ($requests as $chunkIndex => $preparedRequest) {
                $payload = $preparedRequest['payload'];
                $chunk = $payload['results'];
                reportPreparedResult($cliMode ? $io : null, $resultBatch, $preparedRequest);
                $chunksProcessed++;
                $chunkNumber = $chunkIndex + 1;
                $chunkCount = count($chunk);

                if ($cliMode) {
                    $io->text(sprintf(
                        "Posting chunk %d (%d record%s) to %s...",
                        $chunkNumber,
                        $chunkCount,
                        $chunkCount === 1 ? '' : 's',
                        $remoteURL
                    ));
                }

                $tPost = MiscUtility::startTimer();

                $apiResponse = $apiService->post($url, $preparedRequest['json'], gzip: true, returnWithStatusCode: true);
                [$jsonResponse, $chunkSize, $responseHeaders] = handleApiResponse(
                    $apiResponse, $cliMode, $io, $chunkSize, 'vl', $maxChunkSize, microtime(true) - $tPost
                );
                if ($cliMode) {
                    $io->comment("Chunk $chunkNumber POST completed in " . MiscUtility::elapsedTime($tPost) . "s");
                }

                try {
                    $acknowledgedSamples = decodeAcknowledgedSampleCodes($jsonResponse, 'vl');
                } catch (RuntimeException $e) {
                    reportModuleSyncFailure($cliMode ? $io : null, 'vl', $chunkNumber, $e);
                    break;
                }
                $acked += count($acknowledgedSamples);

                if ($acknowledgedSamples !== []) {
                    $tUpd = MiscUtility::startTimer();
                    $marked = markAcknowledgedResults($db, 'form_vl', 'vl_sample_id', $chunk, $acknowledgedSamples, $responseHeaders);
                    if ($cliMode) {
                        reportMarkedResults($io, count($acknowledgedSamples), $marked, MiscUtility::elapsedTime($tUpd));
                    }
                }

                if ($cliMode) {
                    $io->comment(sprintf('Acknowledged %d/%d so far', $acked, $count));
                }

                $chunkTransactionId = $transactionId . '-vl-' . str_pad((string) $chunkNumber, 3, '0', STR_PAD_LEFT);
                $general->addApiTracking(
                    $chunkTransactionId,
                    'intelis-system',
                    $chunkCount,
                    'send-results',
                    'vl',
                    $url,
                    $payload,
                    $acknowledgedSamples,
                    'json',
                    $labId
                );
            }

            if ($cliMode && !$isDryRun) {
                $io->text("VL: acknowledged $acked / $count row(s). Total " . MiscUtility::elapsedTime($t) . "s");
            }
        }

        $summaryRequest = [
            'recordsSelected' => $count,
            'chunkSize' => $chunkSize,
            'chunksProcessed' => $chunksProcessed,
        ];
        $summaryResponse = [
            'recordsAcknowledged' => $acked,
        ];
        if (!$isDryRun) {
            $general->addApiTracking(
                $transactionId,
                'intelis-system',
                $count,
                'send-results',
                'vl',
                $url,
                $summaryRequest,
                $summaryResponse,
                'json',
                $labId,
                emptyPoll: $count === 0
            );
        }
    }

    // ----------------------- EID -----------------------
    if (isset($systemConfig['modules']['eid']) && $systemConfig['modules']['eid'] === true) {
        if ($cliMode) {
            $io->section("EID");
            $io->text("Selecting rows...");
        }
        $t = MiscUtility::startTimer();

        $eidQuery = "SELECT vl.*, a.user_name as 'approved_by_name'
                FROM `form_eid` AS vl
                LEFT JOIN `user_details` AS a ON vl.result_approved_by = a.user_id
                WHERE result_status != " . RECEIVED_AT_CLINIC . "
                AND IFNULL(vl.sample_code, '') != ''";

        if ($forceSyncModule !== null && $forceSyncModule !== '' && $forceSyncModule !== '0' && trim((string) $forceSyncModule) === "eid" && !empty($sampleCode)) {
            $eidQuery .= $sampleCodeClause('vl.sample_code');
        }
        if (null !== $syncSinceDate) {
            $eidQuery .= " AND vl.last_modified_datetime >= '$syncSinceDate'";
        } else {
            $eidQuery .= " AND vl.data_sync = 0";
        }

        $db->reset();
        $resultBatch = new ResultSyncBatch(
            $queryResults,
            $eidQuery,
            'vl.eid_id'
        );
        $count = count($resultBatch);

        $acked = 0;
        $totalChunks = 0;
        $chunksProcessed = 0;

        if ($count === 0) {
            if ($cliMode) {
                $io->text("Nothing to send for EID.");
            }
        } else {

            if ($cliMode) {
                $io->text("Selected $count row(s) in " . MiscUtility::elapsedTime($t) . "s");
            }
            $totalChunks = (int) ceil($count / max(1, $chunkSize));
            $chunks = $resultBatch->chunks($nextBatchSize);

            if ($isDryRun) {
                reportDryRunChunks($io, 'eid', $resultBatch->preview(), $totalChunks, $count);
                $chunks = [];
            }

            $requests = ResultSyncPayload::requests(
                $chunks,
                static fn(array $chunk): array => $buildResultPayload($chunk, 'eid'),
                $maxPayloadBytes,
                $nextBatchSize
            );
            foreach ($requests as $chunkIndex => $preparedRequest) {
                $payload = $preparedRequest['payload'];
                $chunk = $payload['results'];
                reportPreparedResult($cliMode ? $io : null, $resultBatch, $preparedRequest);
                $chunksProcessed++;
                $chunkNumber = $chunkIndex + 1;
                $chunkCount = count($chunk);

                if ($cliMode) {
                    $io->text(sprintf(
                        "Posting chunk %d (%d record%s) to %s...",
                        $chunkNumber,
                        $chunkCount,
                        $chunkCount === 1 ? '' : 's',
                        $remoteURL
                    ));
                }
                $tPost = MiscUtility::startTimer();

                $apiResponse = $apiService->post($url, $preparedRequest['json'], gzip: true, returnWithStatusCode: true);
                [$jsonResponse, $chunkSize, $responseHeaders] = handleApiResponse(
                    $apiResponse, $cliMode, $io, $chunkSize, 'eid', $maxChunkSize, microtime(true) - $tPost
                );
                if ($cliMode) {
                    $io->comment("Chunk $chunkNumber POST completed in " . MiscUtility::elapsedTime($tPost) . "s");
                }

                try {
                    $acknowledgedSamples = decodeAcknowledgedSampleCodes($jsonResponse, 'eid');
                } catch (RuntimeException $e) {
                    reportModuleSyncFailure($cliMode ? $io : null, 'eid', $chunkNumber, $e);
                    break;
                }
                $acked += count($acknowledgedSamples);

                if ($acknowledgedSamples !== []) {
                    $tUpd = MiscUtility::startTimer();
                    $marked = markAcknowledgedResults($db, 'form_eid', 'eid_id', $chunk, $acknowledgedSamples, $responseHeaders);
                    if ($cliMode) {
                        reportMarkedResults($io, count($acknowledgedSamples), $marked, MiscUtility::elapsedTime($tUpd));
                    }
                }

                if ($cliMode) {
                    $io->comment(sprintf('Acknowledged %d/%d so far', $acked, $count));
                }

                $chunkTransactionId = $transactionId . '-eid-' . str_pad((string) $chunkNumber, 3, '0', STR_PAD_LEFT);
                $general->addApiTracking(
                    $chunkTransactionId,
                    'intelis-system',
                    $chunkCount,
                    'send-results',
                    'eid',
                    $url,
                    $payload,
                    $acknowledgedSamples,
                    'json',
                    $labId
                );
            }

            if ($cliMode && !$isDryRun) {
                $io->text("EID: acknowledged $acked / $count row(s). Total " . MiscUtility::elapsedTime($t) . "s");
            }
        }

        $summaryRequest = [
            'recordsSelected' => $count,
            'chunkSize' => $chunkSize,
            'chunksProcessed' => $chunksProcessed,
        ];
        $summaryResponse = [
            'recordsAcknowledged' => $acked,
        ];
        if (!$isDryRun) {
            $general->addApiTracking(
                $transactionId,
                'intelis-system',
                $count,
                'send-results',
                'eid',
                $url,
                $summaryRequest,
                $summaryResponse,
                'json',
                $labId,
                emptyPoll: $count === 0
            );
        }
    }

    // ----------------------- COVID-19 -----------------------
    if (isset($systemConfig['modules']['covid19']) && $systemConfig['modules']['covid19'] === true) {
        if ($cliMode) {
            $io->section("COVID-19");
            $io->text("Selecting rows...");
        }
        $t = MiscUtility::startTimer();

        $covid19Query = "SELECT c19.*, a.user_name as 'approved_by_name'
                FROM `form_covid19` AS c19
                LEFT JOIN `user_details` AS a ON c19.result_approved_by = a.user_id
                WHERE result_status != " . RECEIVED_AT_CLINIC . "
                AND IFNULL(c19.sample_code, '') != ''";

        if ($forceSyncModule !== null && $forceSyncModule !== '' && $forceSyncModule !== '0' && trim((string) $forceSyncModule) === "covid19" && !empty($sampleCode)) {
            $covid19Query .= $sampleCodeClause('c19.sample_code');
        }
        if (null !== $syncSinceDate) {
            $covid19Query .= " AND c19.last_modified_datetime >= '$syncSinceDate'";
        } else {
            $covid19Query .= " AND c19.data_sync = 0";
        }

        $db->reset();
        $resultBatch = new ResultSyncBatch(
            $queryResults,
            $covid19Query,
            'c19.covid19_id',
            keyByUniqueId: true
        );
        $count = count($resultBatch);

        $acked = 0;
        $totalChunks = 0;
        $chunksProcessed = 0;

        if ($count === 0) {
            if ($cliMode) {
                $io->text("Nothing to send for COVID-19.");
            }
        } else {

            if ($cliMode) {
                $io->text("Selected $count row(s) in " . MiscUtility::elapsedTime($t) . "s");
            }

            /** @var Covid19Service $covid19Service */
            $covid19Service = ContainerRegistry::get(Covid19Service::class);

            $totalChunks = (int) ceil($count / max(1, $chunkSize));
            $chunks = $resultBatch->chunks(
                $nextBatchSize,
                prepareBatch: static function (array $rows) use ($covid19Service): array {
                    $children = $covid19Service->getCovid19TestsByFormId(array_column($rows, 'covid19_id'));
                    return ResultSyncBatch::nestedPayload(
                        $rows,
                        $children ?? [],
                        'covid19_id',
                        wrapParentId: true
                    );
                }
            );

            if ($isDryRun) {
                reportDryRunChunks($io, 'covid19', $resultBatch->preview(), $totalChunks, $count);
                $chunks = [];
            }

            $requests = ResultSyncPayload::requests(
                $chunks,
                static fn(array $chunk): array => $buildResultPayload($chunk, 'covid19'),
                $maxPayloadBytes,
                $nextBatchSize
            );
            foreach ($requests as $chunkIndex => $preparedRequest) {
                $payload = $preparedRequest['payload'];
                $chunk = $payload['results'];
                reportPreparedResult($cliMode ? $io : null, $resultBatch, $preparedRequest);
                $chunksProcessed++;
                $chunkNumber = $chunkIndex + 1;
                $chunkCount = count($chunk);

                if ($cliMode) {
                    $io->text(sprintf(
                        "Posting chunk %d (%d record%s) to %s...",
                        $chunkNumber,
                        $chunkCount,
                        $chunkCount === 1 ? '' : 's',
                        $remoteURL
                    ));
                }
                $tPost = MiscUtility::startTimer();

                $apiResponse = $apiService->post($url, $preparedRequest['json'], gzip: true, returnWithStatusCode: true);
                [$jsonResponse, $chunkSize, $responseHeaders] = handleApiResponse(
                    $apiResponse, $cliMode, $io, $chunkSize, 'covid19', $maxChunkSize, microtime(true) - $tPost
                );
                if ($cliMode) {
                    $io->comment("Chunk $chunkNumber POST completed in " . MiscUtility::elapsedTime($tPost) . "s");
                }

                try {
                    $acknowledgedSamples = decodeAcknowledgedSampleCodes($jsonResponse, 'covid19');
                } catch (RuntimeException $e) {
                    reportModuleSyncFailure($cliMode ? $io : null, 'covid19', $chunkNumber, $e);
                    break;
                }
                $acked += count($acknowledgedSamples);

                if ($acknowledgedSamples !== []) {
                    $tUpd = MiscUtility::startTimer();
                    $marked = markAcknowledgedResults($db, 'form_covid19', 'covid19_id', $chunk, $acknowledgedSamples, $responseHeaders);
                    if ($cliMode) {
                        reportMarkedResults($io, count($acknowledgedSamples), $marked, MiscUtility::elapsedTime($tUpd));
                    }
                }

                if ($cliMode) {
                    $io->comment(sprintf('Acknowledged %d/%d so far', $acked, $count));
                }

                $chunkTransactionId = $transactionId . '-covid19-' . str_pad((string) $chunkNumber, 3, '0', STR_PAD_LEFT);
                $general->addApiTracking(
                    $chunkTransactionId,
                    'intelis-system',
                    $chunkCount,
                    'send-results',
                    'covid19',
                    $url,
                    $payload,
                    $acknowledgedSamples,
                    'json',
                    $labId
                );
            }

            if ($cliMode && !$isDryRun) {
                $io->text("COVID-19: acknowledged $acked / $count row(s). Total " . MiscUtility::elapsedTime($t) . "s");
            }
        }

        $summaryRequest = [
            'recordsSelected' => $count,
            'chunkSize' => $chunkSize,
            'chunksProcessed' => $chunksProcessed,
        ];
        $summaryResponse = [
            'recordsAcknowledged' => $acked,
        ];
        if (!$isDryRun) {
            $general->addApiTracking(
                $transactionId,
                'intelis-system',
                $count,
                'send-results',
                'covid19',
                $url,
                $summaryRequest,
                $summaryResponse,
                'json',
                $labId,
                emptyPoll: $count === 0
            );
        }
    }

    // ----------------------- HEPATITIS -----------------------
    if (isset($systemConfig['modules']['hepatitis']) && $systemConfig['modules']['hepatitis'] === true) {
        if ($cliMode) {
            $io->section("Hepatitis");
            $io->text("Selecting rows...");
        }
        $t = MiscUtility::startTimer();

        $hepQuery = "SELECT hep.*, a.user_name as 'approved_by_name'
                FROM `form_hepatitis` AS hep
                LEFT JOIN `user_details` AS a ON hep.result_approved_by = a.user_id
                WHERE result_status != " . RECEIVED_AT_CLINIC . "
                AND IFNULL(hep.sample_code, '') != ''";

        if ($forceSyncModule !== null && $forceSyncModule !== '' && $forceSyncModule !== '0' && trim((string) $forceSyncModule) === "hepatitis" && !empty($sampleCode)) {
            $hepQuery .= $sampleCodeClause('hep.sample_code');
        }
        if (null !== $syncSinceDate) {
            $hepQuery .= " AND hep.last_modified_datetime >= '$syncSinceDate'";
        } else {
            $hepQuery .= " AND hep.data_sync = 0";
        }

        $db->reset();
        $resultBatch = new ResultSyncBatch(
            $queryResults,
            $hepQuery,
            'hep.hepatitis_id'
        );
        $count = count($resultBatch);

        $acked = 0;
        $totalChunks = 0;
        $chunksProcessed = 0;

        if ($count === 0) {
            if ($cliMode) {
                $io->text("Nothing to send for Hepatitis.");
            }
        } else {
            if ($cliMode) {
                $io->text("Selected $count row(s) in " . MiscUtility::elapsedTime($t) . "s");
            }
            $totalChunks = (int) ceil($count / max(1, $chunkSize));
            $chunks = $resultBatch->chunks($nextBatchSize);

            if ($isDryRun) {
                reportDryRunChunks($io, 'hepatitis', $resultBatch->preview(), $totalChunks, $count);
                $chunks = [];
            }

            $requests = ResultSyncPayload::requests(
                $chunks,
                static fn(array $chunk): array => $buildResultPayload($chunk, 'hepatitis'),
                $maxPayloadBytes,
                $nextBatchSize
            );
            foreach ($requests as $chunkIndex => $preparedRequest) {
                $payload = $preparedRequest['payload'];
                $chunk = $payload['results'];
                reportPreparedResult($cliMode ? $io : null, $resultBatch, $preparedRequest);
                $chunksProcessed++;
                $chunkNumber = $chunkIndex + 1;
                $chunkCount = count($chunk);

                if ($cliMode) {
                    $io->text(sprintf(
                        "Posting chunk %d (%d record%s) to %s...",
                        $chunkNumber,
                        $chunkCount,
                        $chunkCount === 1 ? '' : 's',
                        $remoteURL
                    ));
                }
                $tPost = MiscUtility::startTimer();

                $apiResponse = $apiService->post($url, $preparedRequest['json'], gzip: true, returnWithStatusCode: true);
                [$jsonResponse, $chunkSize, $responseHeaders] = handleApiResponse(
                    $apiResponse, $cliMode, $io, $chunkSize, 'hepatitis', $maxChunkSize, microtime(true) - $tPost
                );
                if ($cliMode) {
                    $io->comment("Chunk $chunkNumber POST completed in " . MiscUtility::elapsedTime($tPost) . "s");
                }

                try {
                    $acknowledgedSamples = decodeAcknowledgedSampleCodes($jsonResponse, 'hepatitis');
                } catch (RuntimeException $e) {
                    reportModuleSyncFailure($cliMode ? $io : null, 'hepatitis', $chunkNumber, $e);
                    break;
                }
                $acked += count($acknowledgedSamples);

                if ($acknowledgedSamples !== []) {
                    $tUpd = MiscUtility::startTimer();
                    $marked = markAcknowledgedResults($db, 'form_hepatitis', 'hepatitis_id', $chunk, $acknowledgedSamples, $responseHeaders);
                    if ($cliMode) {
                        reportMarkedResults($io, count($acknowledgedSamples), $marked, MiscUtility::elapsedTime($tUpd));
                    }
                }

                if ($cliMode) {
                    $io->comment(sprintf('Acknowledged %d/%d so far', $acked, $count));
                }

                $chunkTransactionId = $transactionId . '-hepatitis-' . str_pad((string) $chunkNumber, 3, '0', STR_PAD_LEFT);
                $general->addApiTracking(
                    $chunkTransactionId,
                    'intelis-system',
                    $chunkCount,
                    'send-results',
                    'hepatitis',
                    $url,
                    $payload,
                    $acknowledgedSamples,
                    'json',
                    $labId
                );
            }

            if ($cliMode && !$isDryRun) {
                $io->text("Hepatitis: acknowledged $acked / $count row(s). Total " . MiscUtility::elapsedTime($t) . "s");
            }
        }

        $summaryRequest = [
            'recordsSelected' => $count,
            'chunkSize' => $chunkSize,
            'chunksProcessed' => $chunksProcessed,
        ];
        $summaryResponse = [
            'recordsAcknowledged' => $acked,
        ];
        if (!$isDryRun) {
            $general->addApiTracking(
                $transactionId,
                'intelis-system',
                $count,
                'send-results',
                'hepatitis',
                $url,
                $summaryRequest,
                $summaryResponse,
                'json',
                $labId,
                emptyPoll: $count === 0
            );
        }
    }

    // ----------------------- TB -----------------------
    if (isset($systemConfig['modules']['tb']) && $systemConfig['modules']['tb'] === true) {
        if ($cliMode) {
            $io->section("TB");
            $io->text("Selecting rows...");
        }
        $t = MiscUtility::startTimer();

        /** @var TbService $tbService */
        $tbService = ContainerRegistry::get(TbService::class);

        $tbQuery = "SELECT tb.*, a.user_name as 'approved_by_name'
            FROM `form_tb` AS tb
            LEFT JOIN `user_details` AS a ON tb.result_approved_by = a.user_id
            WHERE result_status != " . RECEIVED_AT_CLINIC . "
            AND IFNULL(tb.sample_code, '') != ''";

        if ($forceSyncModule !== null && $forceSyncModule !== '' && $forceSyncModule !== '0' && trim((string) $forceSyncModule) === "tb" && !empty($sampleCode)) {
            $tbQuery .= $sampleCodeClause('tb.sample_code');
        }
        if (null !== $syncSinceDate) {
            $tbQuery .= " AND tb.last_modified_datetime >= '$syncSinceDate'";
        } else {
            $tbQuery .= " AND tb.data_sync = 0";
        }

        $db->reset();
        $resultBatch = new ResultSyncBatch(
            $queryResults,
            $tbQuery,
            'tb.tb_id',
            keyByUniqueId: true
        );
        $count = count($resultBatch);

        $acked = 0;
        $totalChunks = 0;
        $chunksProcessed = 0;

        if ($count === 0) {
            if ($cliMode) {
                $io->text("Nothing to send for TB.");
            }
        } else {
            if ($cliMode) {
                $io->text("Selected $count row(s) in " . MiscUtility::elapsedTime($t) . "s");
            }
            $totalChunks = (int) ceil($count / max(1, $chunkSize));
            $chunks = $resultBatch->chunks(
                $nextBatchSize,
                prepareBatch: static function (array $rows) use ($tbService): array {
                    $children = $tbService->getTbTestsByFormId(array_column($rows, 'tb_id'));
                    return ResultSyncBatch::nestedPayload(
                        $rows,
                        $children ?? [],
                        'tb_id'
                    );
                }
            );

            if ($isDryRun) {
                reportDryRunChunks($io, 'tb', $resultBatch->preview(), $totalChunks, $count);
                $chunks = [];
            }

            $requests = ResultSyncPayload::requests(
                $chunks,
                static fn(array $chunk): array => $buildResultPayload($chunk, 'tb'),
                $maxPayloadBytes,
                $nextBatchSize
            );
            foreach ($requests as $chunkIndex => $preparedRequest) {
                $payload = $preparedRequest['payload'];
                $chunk = $payload['results'];
                reportPreparedResult($cliMode ? $io : null, $resultBatch, $preparedRequest);
                $chunksProcessed++;
                $chunkNumber = $chunkIndex + 1;
                $chunkCount = count($chunk);


                if ($cliMode) {
                    $io->text(sprintf(
                        "Posting chunk %d (%d record%s) to %s...",
                        $chunkNumber,
                        $chunkCount,
                        $chunkCount === 1 ? '' : 's',
                        $remoteURL
                    ));
                }
                $tPost = MiscUtility::startTimer();

                $apiResponse = $apiService->post($url, $preparedRequest['json'], gzip: true, returnWithStatusCode: true);
                [$jsonResponse, $chunkSize, $responseHeaders] = handleApiResponse(
                    $apiResponse, $cliMode, $io, $chunkSize, 'tb', $maxChunkSize, microtime(true) - $tPost
                );
                if ($cliMode) {
                    $io->comment("Chunk $chunkNumber POST completed in " . MiscUtility::elapsedTime($tPost) . "s");
                }

                try {
                    $acknowledgedSamples = decodeAcknowledgedSampleCodes($jsonResponse, 'tb');
                } catch (RuntimeException $e) {
                    reportModuleSyncFailure($cliMode ? $io : null, 'tb', $chunkNumber, $e);
                    break;
                }
                $acked += count($acknowledgedSamples);

                if ($acknowledgedSamples !== []) {
                    $tUpd = MiscUtility::startTimer();
                    $marked = markAcknowledgedResults($db, 'form_tb', 'tb_id', $chunk, $acknowledgedSamples, $responseHeaders);
                    if ($cliMode) {
                        reportMarkedResults($io, count($acknowledgedSamples), $marked, MiscUtility::elapsedTime($tUpd));
                    }
                }

                if ($cliMode) {
                    $io->comment(sprintf('Acknowledged %d/%d so far', $acked, $count));
                }

                $chunkTransactionId = $transactionId . '-tb-' . str_pad((string) $chunkNumber, 3, '0', STR_PAD_LEFT);
                $general->addApiTracking(
                    $chunkTransactionId,
                    'intelis-system',
                    $chunkCount,
                    'send-results',
                    'tb',
                    $url,
                    $payload,
                    $acknowledgedSamples,
                    'json',
                    $labId
                );
            }

            if ($cliMode && !$isDryRun) {
                $io->text("TB: acknowledged $acked / $count row(s). Total " . MiscUtility::elapsedTime($t) . "s");
            }
        }

        $summaryRequest = [
            'recordsSelected' => $count,
            'chunkSize' => $chunkSize,
            'chunksProcessed' => $chunksProcessed,
        ];
        $summaryResponse = [
            'recordsAcknowledged' => $acked,
        ];
        if (!$isDryRun) {
            $general->addApiTracking(
                $transactionId,
                'intelis-system',
                $count,
                'send-results',
                'tb',
                $url,
                $summaryRequest,
                $summaryResponse,
                'json',
                $labId,
                emptyPoll: $count === 0
            );
        }
    }

    // ----------------------- CD4 -----------------------
    if (isset($systemConfig['modules']['cd4']) && $systemConfig['modules']['cd4'] === true) {
        if ($cliMode) {
            $io->section("CD4");
            $io->text("Selecting rows...");
        }
        $t = MiscUtility::startTimer();

        $cd4Query = "SELECT cd4.*, a.user_name as 'approved_by_name'
            FROM `form_cd4` AS cd4
            LEFT JOIN `user_details` AS a ON cd4.result_approved_by = a.user_id
            WHERE result_status != " . RECEIVED_AT_CLINIC . "
            AND IFNULL(cd4.sample_code, '') != ''";

        if ($forceSyncModule !== null && $forceSyncModule !== '' && $forceSyncModule !== '0' && trim((string) $forceSyncModule) === "cd4" && !empty($sampleCode)) {
            $cd4Query .= $sampleCodeClause('cd4.sample_code');
        }
        if (null !== $syncSinceDate) {
            $cd4Query .= " AND cd4.last_modified_datetime >= '$syncSinceDate'";
        } else {
            $cd4Query .= " AND cd4.data_sync = 0";
        }

        $db->reset();
        $resultBatch = new ResultSyncBatch(
            $queryResults,
            $cd4Query,
            'cd4.cd4_id'
        );
        $count = count($resultBatch);
        $acked = 0;
        $totalChunks = 0;
        $chunksProcessed = 0;

        if ($count === 0) {
            if ($cliMode) {
                $io->text("Nothing to send for CD4.");
            }
        } else {

            if ($cliMode) {
                $io->text("Selected $count row(s) in " . MiscUtility::elapsedTime($t) . "s");
            }

            $totalChunks = (int) ceil($count / max(1, $chunkSize));
            $chunks = $resultBatch->chunks($nextBatchSize);

            if ($isDryRun) {
                reportDryRunChunks($io, 'cd4', $resultBatch->preview(), $totalChunks, $count);
                $chunks = [];
            }

            $requests = ResultSyncPayload::requests(
                $chunks,
                static fn(array $chunk): array => $buildResultPayload($chunk, 'cd4'),
                $maxPayloadBytes,
                $nextBatchSize
            );
            foreach ($requests as $chunkIndex => $preparedRequest) {
                $payload = $preparedRequest['payload'];
                $chunk = $payload['results'];
                reportPreparedResult($cliMode ? $io : null, $resultBatch, $preparedRequest);
                $chunksProcessed++;
                $chunkNumber = $chunkIndex + 1;
                $chunkCount = count($chunk);

                if ($cliMode) {
                    $io->text(sprintf(
                        "Posting chunk %d (%d record%s) to %s...",
                        $chunkNumber,
                        $chunkCount,
                        $chunkCount === 1 ? '' : 's',
                        $remoteURL
                    ));
                }
                $tPost = MiscUtility::startTimer();

                $apiResponse = $apiService->post($url, $preparedRequest['json'], gzip: true, returnWithStatusCode: true);
                [$jsonResponse, $chunkSize, $responseHeaders] = handleApiResponse(
                    $apiResponse, $cliMode, $io, $chunkSize, 'cd4', $maxChunkSize, microtime(true) - $tPost
                );
                if ($cliMode) {
                    $io->comment("Chunk $chunkNumber POST completed in " . MiscUtility::elapsedTime($tPost) . "s");
                }

                try {
                    $acknowledgedSamples = decodeAcknowledgedSampleCodes($jsonResponse, 'cd4');
                } catch (RuntimeException $e) {
                    reportModuleSyncFailure($cliMode ? $io : null, 'cd4', $chunkNumber, $e);
                    break;
                }
                $acked += count($acknowledgedSamples);

                if ($acknowledgedSamples !== []) {
                    $tUpd = MiscUtility::startTimer();
                    $marked = markAcknowledgedResults($db, 'form_cd4', 'cd4_id', $chunk, $acknowledgedSamples, $responseHeaders);
                    if ($cliMode) {
                        reportMarkedResults($io, count($acknowledgedSamples), $marked, MiscUtility::elapsedTime($tUpd));
                    }
                }

                if ($cliMode) {
                    $io->comment(sprintf('Acknowledged %d/%d so far', $acked, $count));
                }

                $chunkTransactionId = $transactionId . '-cd4-' . str_pad((string) $chunkNumber, 3, '0', STR_PAD_LEFT);
                $general->addApiTracking(
                    $chunkTransactionId,
                    'intelis-system',
                    $chunkCount,
                    'send-results',
                    'cd4',
                    $url,
                    $payload,
                    $acknowledgedSamples,
                    'json',
                    $labId
                );
            }

            if ($cliMode && !$isDryRun) {
                $io->text("CD4: acknowledged $acked / $count row(s). Total " . MiscUtility::elapsedTime($t) . "s");
            }
        }

        $summaryRequest = [
            'recordsSelected' => $count,
            'chunkSize' => $chunkSize,
            'chunksProcessed' => $chunksProcessed,
        ];
        $summaryResponse = [
            'recordsAcknowledged' => $acked,
        ];
        if (!$isDryRun) {
            $general->addApiTracking(
                $transactionId,
                'intelis-system',
                $count,
                'send-results',
                'cd4',
                $url,
                $summaryRequest,
                $summaryResponse,
                'json',
                $labId,
                emptyPoll: $count === 0
            );
        }
    }

    // Final sync timestamp update
    if ($isDryRun) {
        if ($cliMode) {
            $io->success("DRY RUN complete. Nothing was sent and nothing was updated.");
        }
    } else {
        if ($cliMode) {
            $io->section("Timestamps");
            $io->text("Updating sync timestamps...");
        }
        $tFinal = MiscUtility::startTimer();
        $instanceId = $general->getInstanceId();
        $db->where('vlsm_instance_id', $instanceId);
        $db->update('s_vlsm_instance', ['last_remote_results_sync' => DateUtility::getCurrentDateTime()]);
        if ($cliMode) {
            $io->text("Updated timestamps in " . MiscUtility::elapsedTime($tFinal) . "s");
        }
    }
} catch (Exception $e) {
    LoggerUtility::logError($e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
        'last_db_error' => $db->getLastError(),
        'last_db_query' => $db->getLastQuery(),
    ]);
}
