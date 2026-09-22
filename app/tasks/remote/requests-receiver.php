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
use App\Services\RequestReceiptsClient;
use App\Services\LabRequestSyncService;
use JsonMachine\JsonDecoder\ExtJsonDecoder;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Output\ConsoleOutput;

$io = new SymfonyStyle(new ArgvInput(), new ConsoleOutput());


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
    if ($success > 0) {
        $io->success("Synced $success new " . strtoupper($module) . " record(s)");
    } else {
        $io->success("Synced no new " . strtoupper($module) . " record(s)");
    }

    if ($failures > 0) {
        $io->error("Failed to sync $failures " . strtoupper($module) . " record(s)");
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

    $io->section('Starting test requests sync');

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
        $io->warning('DRY RUN: every change will be rolled back; nothing will be saved and the sync window will not advance.');
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
    $io->text("Filtering requests from: $syncSinceDate");
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
        $io->error("No Lab ID set in System Config");
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
        echo "No internet connectivity while trying remote sync." . PHP_EOL;
    }
    exit(0);
}

// if only one module is getting synced, limit to that
if (!empty($forceSyncModule)) {
    unset($systemConfig['modules']);
    $systemConfig['modules'][$forceSyncModule] = true;
}

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
        $lastSyncDateTime = $general->getLastApiSyncByTypeAndModule('receive-requests', $moduleKey);

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

/**
 * No new pull for waiting requests starts after this. Cron runs the receiver
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
        $io->text("Delivered $flushed receipt(s) kept from an earlier run");
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
            // Ask the STS for receipts: an older STS ignores the key. Not for a
            // manifest pull or a dry run, which confirm nothing.
            if (!$isDryRun && empty($manifestCode)) {
                $basePayload['receipts'] = 1;
                // Pulling again: this run has the window already. An older STS
                // ignores the key and sends the window again, which is harmless.
                if ($pullModules !== null) {
                    $basePayload['pendingOnly'] = 1;
                }
            }
            if (!empty($forceSyncModule) && trim((string) $forceSyncModule) == $module && !empty($manifestCode) && trim((string) $manifestCode) !== "") {
                $basePayload['manifestCode'] = $manifestCode;
            }
            $effectiveSyncSinceDate = $syncSinceDate ?? ($moduleSyncSinceDates[$module] ?? null);
            if (!empty($effectiveSyncSinceDate)) {
                $basePayload['syncSinceDate'] = $effectiveSyncSinceDate;
                if ($cliMode && $syncSinceDate === null && isset($moduleSyncSinceDates[$module])) {
                    $io->text("Requesting " . strtoupper((string) $module) . " records updated since $effectiveSyncSinceDate");
                }
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
                    $io->text("Received server response for $module");
                }
            })->otherwise(function (mixed $reason) use ($module, $cliMode, $io): void {
                $reason = $reason instanceof Throwable ? $reason->getMessage() : (string) $reason;
                if ($cliMode) {
                    $io->error("STS Request sync for $module failed: $reason");
                }
                LoggerUtility::logError(__FILE__ . ":" . __LINE__ . ":" . "STS Request sync for $module failed: " . $reason);
            });
        }
    }

    // Wait for all promises
    Utils::settle($promises)->wait();

    if ($cliMode) {
        $io->comment("Total download time for STS Requests: " . MiscUtility::elapsedTime($startTime) . " seconds");
    }

    // Process modules
    try {
        foreach ($moduleConfigs as $module => $cfg) {
            if (empty($responsePayload[$module]) || $responsePayload[$module] === '[]' || !JsonUtility::isJSON($responsePayload[$module])) {
                continue;
            }

            if ($cliMode) {
                $io->section("Processing for " . strtoupper($module) . "...");
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
                        'DRY RUN %s: would insert %d and update %d record(s); %d would fail',
                        strtoupper($module),
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
                        'Could not deliver the receipt for ' . strtoupper($module) . '; it will be sent next run.'
                    );
                }
            }
        }

        // Special-case generic-tests (preserve its merging logic)
        if (!empty($responsePayload['generic-tests']) && $responsePayload['generic-tests'] !== '[]' && JsonUtility::isJSON($responsePayload['generic-tests'])) {
            $module = 'generic-tests';

            if ($cliMode) {
                $io->section("Processing for CUSTOM TESTS...");
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
                    $io->note("DRY RUN CUSTOM TESTS: would sync $successCounter record(s)");
                } else {
                    $io->success("Synced $successCounter Custom Tests record(s)");
                    if ($failureCounter > 0) {
                        $io->error("Failed to sync $failureCounter Custom Tests record(s)");
                    }
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
                        'Could not deliver the receipt for ' . strtoupper($module) . '; it will be sent next run.'
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

    $pullModules = LabRequestSyncService::modulesToPullAgain(
        $receiptDelivered,
        $moduleResponseHeaders,
        $previousRemaining,
        $savedCounts
    );
    if ($pullModules !== [] && $cliMode) {
        $io->text('More requests are waiting on the STS for: ' . implode(', ', $pullModules) . '. Pulling again.');
    }
// Web runs (the sync button) pull once: a long loop would outlast the request.
} while ($cliMode && $pullModules !== [] && time() < $pullDeadline);

// Final sync timestamp update
if (!$isDryRun) {
    $db->where('vlsm_instance_id', $general->getInstanceId());
    $db->update('s_vlsm_instance', ['last_remote_requests_sync' => DateUtility::getCurrentDateTime()]);
} elseif ($cliMode) {
    $io->success('DRY RUN complete. All changes were rolled back.');
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
