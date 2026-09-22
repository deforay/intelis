<?php

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
use App\Services\ApiService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Utilities\ResultSyncAcknowledgement;
use App\Services\CommonService;
use App\Services\TestsService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use App\Services\LabResultsSenderService;

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

// A previous run that stopped between sending and its acknowledgment left rows in
// flight (data_sync = 2). The STS may not have them, so they go back to pending.
// Only while holding the lock: with it, no other run can have rows out.
if ($lockAcquired && !$isDryRun) {
    foreach (ResultSyncAcknowledgement::TABLES as $syncTable => $syncPrimaryKey) {
        try {
            $released = ResultSyncAcknowledgement::releaseInFlight($db, $syncTable);
            if ($released > 0) {
                LoggerUtility::logInfo("Results sender released $released row(s) a previous run left in flight in $syncTable");
            }
        } catch (Throwable $e) {
            LoggerUtility::logError("Results sender could not release in-flight rows in $syncTable: " . $e->getMessage());
        }
    }
}

// Keep the operator's requested size as a ceiling throughout adaptive batching.
$maxChunkSize = max(1, $chunkSize);

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
// If module is forced, override modules config
if ($forceSyncModule !== null && $forceSyncModule !== '' && $forceSyncModule !== '0') {
    unset($systemConfig['modules']);
    $systemConfig['modules'][$forceSyncModule] = true;
}

// Sending results to /v2/results.php for all test types
$url = "$remoteURL/remote/v2/results.php";

try {
    // Check network
    if (false == CommonService::validateStsUrl($remoteURL, $labId)) {
        LoggerUtility::logError("No network connectivity while trying remote sync.");
        return false;
    }

    $transactionId = MiscUtility::generateULID();

    /** @var LabResultsSenderService $resultsSender */
    $resultsSender = ContainerRegistry::get(LabResultsSenderService::class);
    foreach (array_keys(LabResultsSenderService::MODULES) as $testType) {
        if (!isset($systemConfig['modules'][$testType]) || $systemConfig['modules'][$testType] !== true) {
            continue;
        }
        // One module failing, a missing table on an older schema say, leaves the
        // others to send. Its rows stay pending for the next run.
        try {
            $resultsSender->sendModule($testType, [
                'labId' => $labId,
                'url' => $url,
                'remoteUrl' => $remoteURL,
                'transactionId' => $transactionId,
                'syncSinceDate' => $syncSinceDate,
                // The "sync this sample" button names the module; the sample filter applies to that one.
                'sampleCode' => $forceSyncModule === $testType ? $sampleCode : null,
                'maxChunkSize' => $maxChunkSize,
                'maxPayloadBytes' => $maxPayloadBytes,
                'dryRun' => $isDryRun,
                'silent' => $isSilent,
            ], $chunkSize, $cliMode ? $io : null);
        } catch (Throwable $e) {
            LoggerUtility::logError("Results sync for $testType failed: " . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'last_db_error' => $db->getLastError(),
            ]);
            if ($cliMode) {
                $io->error(strtoupper($testType) . ' sync failed: ' . $e->getMessage()
                    . '. Continuing with the next module.');
            }
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
