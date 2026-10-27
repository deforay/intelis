<?php

ini_set('memory_limit', -1);
set_time_limit(0);
ini_set('max_execution_time', 300000);

use Psr\Http\Message\ServerRequestInterface;

//this file gets the requests from the remote server and updates the local database

$cliMode = php_sapi_name() === 'cli';
if ($cliMode) {
    require_once __DIR__ . "/../../../bootstrap.php";
}

use JsonMachine\Items;
use App\Services\ApiService;
use GuzzleHttp\Promise\Utils;
use App\Services\TestsService;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use App\Services\RequestPageCursor;
use App\Services\RequestReceiptsClient;
use App\Services\LabRequestSyncService;
use JsonMachine\JsonDecoder\ExtJsonDecoder;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Output\ConsoleOutput;

$io = new SymfonyStyle(new ArgvInput(), new ConsoleOutput(MiscUtility::verbosityFromArgv($_SERVER['argv'] ?? [])));


/**
 * Display help/usage information
 */
/**
 * Display help/usage information (SymfonyStyle)
 */
function showHelp(SymfonyStyle $io): void
{
    $io->title('InteLIS / VLSM — Remote Test Requests Sync');

    $io->section('Description');
    $io->text([
        'Synchronizes test requests from the remote STS server to the local database.',
        'Supports modules: VL, EID, COVID-19, Hepatitis, TB, CD4, and Generic Tests.',
    ]);

    $io->section('Usage');
    $io->text('php requests-receiver.php [OPTIONS] [<date>|<days>] [silent]');

    $io->section('Options');
    $io->definitionList(
        ['-t <module>' => 'Force sync for a specific test module. Valid: vl, eid, covid19, hepatitis, tb, cd4, generic-tests'],
        ['-m <manifest_code>' => 'Sync only a specific manifest/package (use with -t).'],
        ['--dry-run, dry-run' => 'Preview the sync: fetch from STS and report what would be inserted/updated, then roll everything back.'],
        ['-v' => 'Also show the STS processing time for each test type.'],
        ['-h, --help, help' => 'Show this help and exit.']
    );

    $io->section('Positionals');
    $io->definitionList(
        ['<date>' => 'Sync since a specific date (YYYY-MM-DD). Example: 2025-01-01'],
        ['<days>' => 'Sync since N days ago (number). Example: 7'],
        ['silent' => 'Run without updating last_modified_datetime (quiet on timestamps).']
    );

    $io->section('Examples');
    $io->listing([
        'php requests-receiver.php',
        'php requests-receiver.php -t vl',
        'php requests-receiver.php -t vl -m PKG123456',
        'php requests-receiver.php 7',
        'php requests-receiver.php 2025-01-01',
        'php requests-receiver.php -t covid19 3 silent',
        'php requests-receiver.php -t vl 2025-01-01 --dry-run',
    ]);

    $io->section('Notes');
    $io->note([
        'Requires internet connectivity to the STS server.',
        'Lab ID must be configured in System Config.',
        'Progress indicators show during sync operations.',
        'All operations are logged.',
        'By default, only unsynced requests (data_sync = 0) are processed.',
        'With --dry-run, every record is processed inside a transaction that is rolled back, so nothing is saved and the sync window does not advance.',
    ]);

    exit(0);
}

/**
 * Display server hint headers (proc time / bytes) when running via CLI.
 */
function showServerHints(?SymfonyStyle $io, array $headers, ?string $label = null): void
{
    // STS timings help diagnose a slow run. They are noise otherwise.
    if ($io === null || !$io->isVerbose()) {
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

// Check for help flag early
if ($cliMode) {
    $args = array_slice($_SERVER['argv'], 1);
    if (in_array('-h', $args) || in_array('--help', $args) || in_array('help', $args)) {
        showHelp($io);
    }
}


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var ApiService $apiService */
$apiService = ContainerRegistry::get(ApiService::class);

/** @var LabRequestSyncService $labRequestSync */
$labRequestSync = ContainerRegistry::get(LabRequestSyncService::class);

$db->rawQuery("SET SESSION wait_timeout=28800"); // 8 hours

function outputSyncResults(SymfonyStyle $io, string $module, int $success, int $failures): void
{
    $io->success($success > 0 ? "Saved $success request(s)." : 'No new requests.');

    if ($failures > 0) {
        $io->error("$failures request(s) could not be saved. The error log has the details.");
    }
}

function spinner(int $loopIndex, int $count, string $label = 'Processed', array $spinnerChars = ['.   ', '..  ', '... ', '....']): void
{
    static $lastSpinnerChar = '';
    $shouldUpdateSpinner = ($loopIndex % 10 === 0);

    if ($shouldUpdateSpinner) {
        $lastSpinnerChar = $spinnerChars[intdiv($loopIndex, 10) % count($spinnerChars)];
    }

    echo "\r$lastSpinnerChar $label: $count";

    if (ob_get_level() > 0) {
        ob_flush();
        flush();
    }
}

function clearSpinner(): void
{
    echo "\r" . str_repeat(' ', 40) . "\r";
}



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


$forceSyncModule = $manifestCode = null;
$syncSinceDate = null;
$isSilent = false;
$isDryRun = false;
if ($cliMode) {

    $io->title('Receiving test requests from the STS');

    $args = array_slice($_SERVER['argv'], 1);

    // Use getopt if present
    $options = getopt("t:m:");

    if (isset($options['t'])) {
        $forceSyncModule = $options['t'];
    }
    if (isset($options['m'])) {
        $manifestCode = $options['m'];
    }

    if (in_array('--dry-run', $args, true) || in_array('dry-run', $args, true)) {
        $isDryRun = true;
        $io->warning('Dry run: nothing is saved. The next run starts from the same point.');
    }

    // Scan all args to find a valid date or number-of-days
    foreach ($args as $arg) {
        if (str_contains(strtolower((string) $arg), 'silent')) {
            $isSilent = true;
            continue;
        }

        if (in_array($arg, [$forceSyncModule, $manifestCode], true)) {
            continue;
        }

        $arg = trim((string) $arg);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $arg) && DateUtility::isDateFormatValid($arg, 'Y-m-d')) {
            $syncSinceDate = DateUtility::getDateTime($arg, 'Y-m-d');
            break;
        } elseif (is_numeric($arg)) {
            $syncSinceDate = DateUtility::daysAgo((int) $arg);
            break;
        }
    }
}

if ($_POST !== []) {
    /** @var ServerRequestInterface $request */
    $request = AppRegistry::get('request');
    $postParams = $request->getParsedBody();

    if (!empty($postParams)) {
        $_POST = _sanitizeInput($postParams);
        $manifestCode = $_POST['manifestCode'] ?? null;
        $forceSyncModule = $_POST['testType'] ?? $_POST['forceSyncModule'] ?? null;
        $syncSinceDate = $_POST['syncSinceDate'] ?? null;
        $isSilent = $_POST['silent'] ?? false;
    }
}

if ($syncSinceDate !== null) {
    $io->text("Checking for new or updated requests since: $syncSinceDate");
}
$transactionId = MiscUtility::generateULID();



$version = VERSION;

$systemConfig = SYSTEM_CONFIG;

$remoteURL = $general->getRemoteURL();

if (empty($remoteURL)) {
    LoggerUtility::logError("Please check if STS URL is set");
    exit(0);
}

if (empty($labId)) {
    if ($cliMode) {
        $io->error("The testing lab is not set in System Config. Exiting.");
    }
    LoggerUtility::logError("No Lab ID set in System Config", [
        'line' => __LINE__,
        'file' => __FILE__,
        'remoteURL' => $remoteURL
    ]);
    exit(0);
}

if (false == CommonService::validateStsUrl($remoteURL, $labId)) {
    LoggerUtility::logError("No internet connectivity while trying remote sync.", [
        'line' => __LINE__,
        'file' => __FILE__,
        'remoteURL' => $remoteURL
    ]);
    if ($cliMode) {
        $io->error("Cannot reach the STS at $remoteURL. Check the internet connection. Exiting.");
    }
    exit(0);
}

// if only one module is getting synced, limit to that
if (!empty($forceSyncModule)) {
    unset($systemConfig['modules']);
    $systemConfig['modules'][$forceSyncModule] = true;
}

// Where the pulls have got to. A dry run keeps nothing. The file is where a lab
// kept its page markers before request_pull_state: they move into the table.
$pageCursor = new RequestPageCursor(
    $isDryRun ? null : $db,
    $cliMode && !$isDryRun
        ? VAR_PATH . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'request-pages.json'
        : null
);

$moduleSyncSinceDates = [];
if ($syncSinceDate === null) {
    $defaultWindowDays = (int) ($general->getGlobalConfig('data_sync_interval') ?? 30);
    if ($defaultWindowDays <= 0) {
        $defaultWindowDays = 30;
    }
    $defaultModuleSyncDate = DateUtility::daysAgo($defaultWindowDays);
    $today = DateUtility::getCurrentDateTime('Y-m-d');

    foreach ($systemConfig['modules'] as $moduleKey => $isEnabled) {
        if ($isEnabled !== true) {
            continue;
        }

        $moduleSyncDate = $defaultModuleSyncDate;
        // track_api_requests only until this lab has recorded a pull of its own.
        $lastSyncDateTime = $pageCursor->lastPull((string) $moduleKey)
            ?? $general->getLastApiSyncByTypeAndModule('receive-requests', $moduleKey);

        if (!empty($lastSyncDateTime) && DateUtility::isDateValid($lastSyncDateTime)) {
            $moduleSyncDate = (new DateTimeImmutable($lastSyncDateTime))
                ->modify('-6 hours')
                ->format('Y-m-d');
        }

        if ($moduleSyncDate > $today) {
            $moduleSyncDate = $today;
        }

        $moduleSyncSinceDates[$moduleKey] = $moduleSyncDate;
    }
}

$stsBearerToken = $general->getSTSToken();
$apiService->setBearerToken($stsBearerToken);

// Define per-module config
$moduleConfigs = LabRequestSyncService::moduleConfigs();

// From the command line a window comes a page at a time from an STS that pages,
// saved page by page, and resumes where it stopped when run again. One response
// for a big window outlasted the request timeout and saved nothing.
//
// A window someone asked for (a number of days, a date, a manifest) is paged to
// its end. The scheduled pull first pulls the requests waiting for this lab, with
// receipts, then pages the window, with receipts, while the run has time. One
// lab's window of 31,000 rows failed on every run. While its window is unfinished,
// its start is held, and the next run carries on from the page it reached.
//
// The sync button (a web run) pulls once, as does a dry run of the scheduled pull.
$scheduledPull = $cliMode && $syncSinceDate === null && empty($manifestCode);
$firstPhase = match (true) {
    !$cliMode, $scheduledPull && $isDryRun => 'plain',
    $scheduledPull => LabRequestSyncService::NEXT_WAITING,
    default => LabRequestSyncService::NEXT_PAGES,
};
$pageKeys = [];
$phases = [];
$announced = [];
if ($firstPhase === LabRequestSyncService::NEXT_WAITING) {
    foreach ($moduleSyncSinceDates as $module => $since) {
        $moduleSyncSinceDates[$module] = $pageCursor->heldStart((string) $module) ?? $since;
    }
}

/**
 * No new pull of the scheduled pull starts after this. Cron runs the receiver
 * through `composer run sync-sts`, and composer stops a script after 300 seconds
 * (its process-timeout) and skips the rest of the chain, so this leaves the last
 * pull time to finish inside that.
 */
const REQUEST_PULL_BUDGET_SECONDS = 180;

/** @var RequestReceiptsClient $receiptsClient */
$receiptsClient = ContainerRegistry::get(RequestReceiptsClient::class);
// Receipts earlier runs could not deliver go first. Never in a dry run, which
// changes nothing on either side.
if (!$isDryRun) {
    $flushed = $receiptsClient->flushOutbox($remoteURL);
    if ($flushed > 0 && $cliMode) {
        $io->text("Sent $flushed confirmation(s) of received requests left over from an earlier run.");
    }
}

// Receipts: a lab that asks gets its waiting requests in batches, and pulls
// again straight away while the STS says more remain, as long as the last
// receipt got through and the run has time left. A plain pull runs once.
$pullModules = null;
$pullDeadline = time() + REQUEST_PULL_BUDGET_SECONDS;
$previousRemaining = [];
do {
    $promises = [];
    $requestInfo = []; // to retain url+payload for tracking
    $startTime = MiscUtility::startTimer();
    $pullAskedAt = DateUtility::getCurrentDateTime();
    $responsePayload = [];
    $moduleResponseHeaders = [];
    $receiptDelivered = [];
    $savedCounts = [];

    foreach ($systemConfig['modules'] as $module => $status) {
        $moduleUrl = "$remoteURL/remote/v2/requests.php";
        $basePayload = [
            'labId' => $labId,
            'transactionId' => $transactionId
        ];
        if ($pullModules !== null && !in_array($module, $pullModules, true)) {
            continue;
        }
        if ($status === true) {
            $basePayload['testType'] = $module;
            $phase = $phases[$module] ??= $firstPhase;
            // Ask the STS for receipts: an older STS ignores the key. Not for a
            // manifest pull, a dry run or a window someone asked for, which
            // confirm nothing.
            $confirms = $phase !== LabRequestSyncService::NEXT_PAGES || $scheduledPull;
            if (!$isDryRun && empty($manifestCode) && $confirms) {
                $basePayload['receipts'] = 1;
                // Pulling again, or the waiting requests alone. An older STS
                // ignores the key and sends the window, which is harmless.
                if ($phase === LabRequestSyncService::NEXT_WAITING || ($phase === 'plain' && $pullModules !== null)) {
                    $basePayload['pendingOnly'] = 1;
                }
            }
            if (!empty($forceSyncModule) && trim((string) $forceSyncModule) == $module && !empty($manifestCode) && trim((string) $manifestCode) !== "") {
                $basePayload['manifestCode'] = $manifestCode;
            }
            $effectiveSyncSinceDate = $syncSinceDate ?? ($moduleSyncSinceDates[$module] ?? null);
            if (!empty($effectiveSyncSinceDate)) {
                $basePayload['syncSinceDate'] = $effectiveSyncSinceDate;
                // Once a run: the start does not move between its pulls.
                $scheduledStart = $cliMode && $syncSinceDate === null && isset($moduleSyncSinceDates[$module]);
                if ($scheduledStart && !isset($announced[$module])) {
                    $announced[$module] = true;
                    $io->text(TestsService::getTestName((string) $module)
                        . ": checking for new or updated requests since $effectiveSyncSinceDate");
                }
            }
            if ($phase !== 'plain') {
                // A manifest is its own window: the date beside it is ignored by
                // the STS and moves as pages are saved, so it is no part of the key.
                $pageKeys[$module] ??= $scheduledPull
                    ? RequestPageCursor::scheduledKey($module, (string) $effectiveSyncSinceDate)
                    : RequestPageCursor::key(
                        $module,
                        empty($basePayload['manifestCode']) ? $effectiveSyncSinceDate : null,
                        $basePayload['manifestCode'] ?? null
                    );
                if ($scheduledPull) {
                    // Held from the first pull: that pull alone moves the start.
                    $pageCursor->begin($pageKeys[$module]);
                }
            }
            if ($phase === LabRequestSyncService::NEXT_PAGES) {
                // An older STS ignores the key and sends the whole window. The
                // scheduled pull asks under its own key, for a page with receipts:
                // an STS that pages but does not know it sends the whole window
                // with receipts, as it always has, not a page without them.
                $basePayload[$scheduledPull ? 'windowPage' : 'page'] = [
                    'size' => RequestPageCursor::PAGE_SIZE,
                    'after' => $pageCursor->get($pageKeys[$module]) ?: null,
                ];
            }

            // preserve for tracking later
            $requestInfo[$module] = [
                'url' => $moduleUrl,
                'payload' => $basePayload
            ];

            $promises[$module] = $apiService->post(
                $moduleUrl,
                $basePayload,
                gzip: true,
                async: true
            )->then(function ($response) use (
                &$responsePayload,
                &$moduleResponseHeaders,
                $module,
                $cliMode,
                $io
            ): void {
                $responsePayload[$module] = $response->getBody()->getContents();
                $headers = [];
                foreach ($response->getHeaders() as $name => $values) {
                    $headers[strtolower($name)] = implode(', ', $values);
                }
                $moduleResponseHeaders[$module] = $headers;
                if ($cliMode) {
                    showServerHints($io, $headers, $module);
                }
            })->otherwise(function (mixed $reason) use ($module, $cliMode, $io): void {
                $reason = $reason instanceof Throwable ? $reason->getMessage() : (string) $reason;
                if ($cliMode) {
                    $io->error(TestsService::getTestName((string) $module) . ': could not get requests from the STS: '
                        . rtrim($reason, '.') . '. The next run tries again.');
                }
                LoggerUtility::logError(__FILE__ . ":" . __LINE__ . ":" . "STS Request sync for $module failed: " . $reason);
            });
        }
    }

    // Wait for all promises
    Utils::settle($promises)->wait();

    if ($cliMode) {
        $io->comment("Download finished in " . MiscUtility::elapsedTime($startTime) . "s.");
    }

    // Process modules
    try {
        foreach ($moduleConfigs as $module => $cfg) {
            if (empty($responsePayload[$module]) || $responsePayload[$module] === '[]' || !JsonUtility::isJSON($responsePayload[$module])) {
                continue;
            }

            if ($cliMode) {
                $io->section(TestsService::getTestName((string) $module));
            }

            $options = [
                'pointer' => '/requests',
                'decoder' => new ExtJsonDecoder(true)
            ];
            $parsedData = Items::fromString($responsePayload[$module], $options);

            $saveResult = $labRequestSync->saveModule(
                $module,
                $parsedData,
                $transactionId,
                (bool) $isSilent,
                $isDryRun,
                $cliMode ? spinner(...) : null
            );
            $successCounter = $saveResult['success'];
            $failureCounter = $saveResult['failures'];
            $insertCounter = $saveResult['inserts'];
            $updateCounter = $saveResult['updates'];
            $receiptSaved = $saveResult['saved'];
            $receiptFailed = $saveResult['failed'];
            $savedCounts[$module] = count($receiptSaved);

            if ($cliMode) {
                clearSpinner();
                echo PHP_EOL;
                if ($isDryRun) {
                    $io->note(sprintf(
                        'Dry run: would add %d and update %d request(s). %d would fail.',
                        $insertCounter,
                        $updateCounter,
                        $failureCounter
                    ));
                } else {
                    outputSyncResults($io, $module, $successCounter, $failureCounter);
                }
            }

            if (!$isDryRun) {
                $general->addApiTracking(
                    $transactionId,
                    'intelis-system',
                    $successCounter,
                    'receive-requests',
                    $module,
                    $requestInfo[$module]['url'] ?? null,
                    $requestInfo[$module]['payload'] ?? null,
                    $responsePayload[$module],
                    'json',
                    $labId,
                    // An empty pull keeps its row but no bodies: the newest
                    // receive-requests row is where the next pull starts from.
                    emptyPoll: $successCounter === 0 && $failureCounter === 0,
                    keepRow: true
                );
            }
            if (($moduleResponseHeaders[$module]['x-request-receipts'] ?? '') === '1' && !$isDryRun) {
                $receiptDelivered[$module] = $receiptsClient->send(
                    $remoteURL,
                    (int) $labId,
                    $module,
                    $receiptSaved,
                    $receiptFailed
                );
                if ($cliMode && !$receiptDelivered[$module]) {
                    $io->warning(
                        TestsService::getTestName((string) $module)
                            . ': could not confirm to the STS that the requests arrived. The next run sends it.'
                    );
                }
            }
        }

        // Special-case generic-tests (preserve its merging logic)
        if (!empty($responsePayload['generic-tests']) && $responsePayload['generic-tests'] !== '[]' && JsonUtility::isJSON($responsePayload['generic-tests'])) {
            $module = 'generic-tests';

            if ($cliMode) {
                $io->section(TestsService::getTestName('generic-tests'));
            }

            $options = [
                'pointer' => '/requests',
                'decoder' => new ExtJsonDecoder(true)
            ];
            $parsedData = Items::fromString($responsePayload['generic-tests'], $options);

            $saveResult = $labRequestSync->saveCustomTests(
                $parsedData,
                $transactionId,
                (bool) $isSilent,
                $isDryRun,
                $cliMode ? spinner(...) : null
            );
            $successCounter = $saveResult['success'];
            $failureCounter = $saveResult['failures'];
            $receiptSaved = $saveResult['saved'];
            $receiptFailed = $saveResult['failed'];
            $savedCounts[$module] = count($receiptSaved);

            if ($cliMode) {
                clearSpinner();
                if ($isDryRun) {
                    $io->note("Dry run: would save $successCounter request(s).");
                } else {
                    outputSyncResults($io, $module, $successCounter, $failureCounter);
                }
            }

            if (!$isDryRun) {
                $general->addApiTracking(
                    $transactionId,
                    'intelis-system',
                    $successCounter,
                    'receive-requests',
                    'generic-tests',
                    $requestInfo['generic-tests']['url'] ?? null,
                    $requestInfo['generic-tests']['payload'] ?? null,
                    $responsePayload['generic-tests'],
                    'json',
                    $labId,
                    // An empty pull keeps its row but no bodies: the newest
                    // receive-requests row is where the next pull starts from.
                    emptyPoll: $successCounter === 0 && $failureCounter === 0,
                    keepRow: true
                );
            }
            if (($moduleResponseHeaders[$module]['x-request-receipts'] ?? '') === '1' && !$isDryRun) {
                $receiptDelivered[$module] = $receiptsClient->send(
                    $remoteURL,
                    (int) $labId,
                    $module,
                    $receiptSaved,
                    $receiptFailed
                );
                if ($cliMode && !$receiptDelivered[$module]) {
                    $io->warning(
                        TestsService::getTestName((string) $module)
                            . ': could not confirm to the STS that the requests arrived. The next run sends it.'
                    );
                }
            }
        }
    } catch (Throwable $e) {
        LoggerUtility::logError($e->getFile() . ":" . $e->getLine() . ":" . $e->getMessage(), [
            'last_db_query' => $db->getLastQuery(),
            'last_db_error' => $db->getLastError(),
            'exception' => $e,
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'stacktrace' => $e->getTraceAsString()
        ]);
    }

    $moreWaiting = LabRequestSyncService::modulesToPullAgain(
        $receiptDelivered,
        $moduleResponseHeaders,
        $previousRemaining,
        $savedCounts
    );
    $waitingAgain = [];
    $pullModules = [];

    // What each module pulls next, once this pull is processed. A pull that did
    // not arrive, or whose processing broke off, keeps its window's marker, so the
    // next run resumes there. A record that fails on its own does not hold the
    // pull: it is logged and counted above (and, with receipts, sent again by the
    // STS). Holding the marker for it would stop a big pull for good on one record.
    foreach (array_keys($requestInfo) as $module) {
        $body = $responsePayload[$module] ?? null;
        // Saved, or an answer of nothing at all ('[]', from an older STS).
        $answered = $body !== null && ($body === '[]' || isset($savedCounts[$module]));
        $phase = $phases[$module];
        if ($answered && $syncSinceDate === null && empty($manifestCode)) {
            // Where the next window starts from. A window someone asked for says
            // nothing about the time before its own start, so it is not recorded.
            $pageCursor->recordPull((string) $module, $pullAskedAt);
        }

        if ($phase === 'plain') {
            if (in_array($module, $moreWaiting, true)) {
                $waitingAgain[] = $module;
            }
            continue;
        }

        if ($phase === LabRequestSyncService::NEXT_WAITING) {
            $next = LabRequestSyncService::afterWaitingPull(
                $answered,
                ($moduleResponseHeaders[$module]['x-request-receipts'] ?? '') === '1',
                in_array($module, $moreWaiting, true)
            );
            if ($next === LabRequestSyncService::NEXT_WAITING) {
                $waitingAgain[] = $module;
            } elseif ($next === LabRequestSyncService::NEXT_PAGES) {
                $phases[$module] = $next;
                $pullModules[] = $module;
                if ($cliMode) {
                    $io->text(TestsService::getTestName((string) $module) . ': getting the requests changed since '
                        . ($moduleSyncSinceDates[$module] ?? '') . ', ' . RequestPageCursor::PAGE_SIZE . ' at a time.');
                }
            } elseif ($next === LabRequestSyncService::NEXT_WHOLE) {
                $pageCursor->finish($pageKeys[$module]);
            }
            continue;
        }

        if (!$answered) {
            continue;
        }
        $paging = $body === '[]' ? null : json_decode((string) $body, true);
        $nextAfter = is_array($paging) ? (string) ($paging['nextAfter'] ?? '') : '';
        if (!is_array($paging) || empty($paging['paged']) || $nextAfter === '') {
            // The whole window (an older STS), or its last page.
            $pageCursor->finish($pageKeys[$module]);
            continue;
        }
        if ($pageCursor->advance($pageKeys[$module], $nextAfter)) {
            $pullModules[] = $module;
            if ($cliMode) {
                $io->text(TestsService::getTestName((string) $module) . ': more requests on the STS. Getting the next page.');
            }
        }
    }
    if ($waitingAgain !== [] && $cliMode) {
        $io->text('More requests are waiting on the STS for '
            . implode(', ', array_map(TestsService::getTestName(...), $waitingAgain)) . '. Getting the next set.');
    }
    $pullModules = array_values(array_unique([...$waitingAgain, ...$pullModules]));
// Web runs (the sync button) pull once: a long loop would outlast the request. A
// window someone asked for runs to its end: they are waiting for it.
} while ($cliMode && $pullModules !== [] && (!$scheduledPull || time() < $pullDeadline));

if ($scheduledPull && $pullModules !== []) {
    $io->text('This run is out of time. The next run carries on with '
        . implode(', ', array_map(TestsService::getTestName(...), $pullModules)) . '.');
}

// Final sync timestamp update
if (!$isDryRun) {
    $db->where('vlsm_instance_id', $general->getInstanceId());
    $db->update('s_vlsm_instance', ['last_remote_requests_sync' => DateUtility::getCurrentDateTime()]);
} elseif ($cliMode) {
    $io->success('Dry run complete. Nothing was saved.');
}

if (
    isset($forceSyncModule) && trim((string) $forceSyncModule) !== ""
    && isset($manifestCode) && trim((string) $manifestCode) !== ""
) {
    $formTable = TestsService::getTestTableName($forceSyncModule);
    $primaryKey = TestsService::getPrimaryColumn($forceSyncModule);
    $db->where("sample_package_code", $manifestCode);
    $sampleData = $db->getValue($formTable, $primaryKey, null);
    echo JsonUtility::encodeUtf8Json($sampleData);
}
