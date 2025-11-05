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

// Services & utilities
use App\Services\TbService;
use App\Services\ApiService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
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

function t0(): float
{
    return microtime(true);
}
function tdone(float $t): string
{
    return number_format(microtime(true) - $t, 2);
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
    ]);

    $io->section('Notes');
    $io->note([
        'Requires internet connectivity to the STS server.',
        'Lab ID must be configured in System Config.',
        'Only results with result_status != RECEIVED_AT_CLINIC are sent.',
        'Results must have a valid sample_code.',
        'By default, only unsynced results (data_sync = 0) are sent.',
        'When a date/days is specified, data_sync is ignored.',
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
    if (empty($selectedRows) || !is_array($selectedRows)) {
        return [];
    }

    // Detect nested form_data rows (['form_data' => [...]]) vs flat rows
    $first = reset($selectedRows);
    $hasNestedFormData = is_array($first) && array_key_exists('form_data', $first);

    // Collect distinct package codes
    $codes = $hasNestedFormData
        ? array_column(array_column($selectedRows, 'form_data'), 'referral_manifest_code')
        : array_column($selectedRows, 'referral_manifest_code');

    $codes = array_values(array_unique(array_filter($codes, static fn($v) => !empty($v))));
    if (empty($codes)) {
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
        throw new \RuntimeException("Failed to decode $testType acknowledgement: $message");
    }

    if (!is_array($decoded)) {
        throw new \RuntimeException("Unexpected acknowledgement format received for $testType results.");
    }

    $filtered = array_filter(
        $decoded,
        static fn($code) => is_string($code) && $code !== ''
    );

    return array_values(array_unique($filtered));
}

// Check for help flag early
if ($cliMode) {
    $args = array_slice($_SERVER['argv'], 1);
    if (in_array('-h', $args) || in_array('--help', $args) || in_array('help', $args)) {
        showHelp($io);
    }
    $io->title("Starting results sending");
}

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var ApiService $apiService */
$apiService = ContainerRegistry::get(ApiService::class);

$labId = $general->getSystemConfig('sc_testing_lab_id');
$version = VERSION;

// putting this into a variable to make this editable
$systemConfig = SYSTEM_CONFIG;

$remoteURL = $general->getRemoteURL();

if (empty($remoteURL)) {
    LoggerUtility::logError("Please check if STS URL is set");
    exit(0);
}

$stsBearerToken = $general->getSTSToken();
$apiService->setBearerToken($stsBearerToken);

$isSilent = false;
$syncSinceDate = null;
$forceSyncModule = null;
$sampleCode = null;
$chunkSize = RESULTS_SENDER_DEFAULT_CHUNK_SIZE;

if ($cliMode) {
    $validModules = TestsService::getActiveTests();
    if (empty($validModules)) {
        $validModules = array_keys(TestsService::getTestTypes());
    }
    $awaitingTestType = false;
    $awaitingChunkSize = false;

    foreach ($argv as $index => $arg) {
        if ($index === 0) {
            continue;
        }

        $arg = trim($arg);

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
            $syncSinceDate ??= DateUtility::daysAgo((int)$arg);
        } elseif (in_array(strtolower($arg), $validModules, true)) {
            $forceSyncModule = strtolower($arg);
        } else {
            $io->error("Invalid argument: $arg");
            exit(1);
        }
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

    $io->text("Chunk size per request: $chunkSize");
}

// Web fallback
$forceSyncModule = $forceSyncModule ? strtolower(trim($forceSyncModule)) : null;
$sampleCode ??= $_GET['sampleCode'] ?? null;

// If module is forced, override modules config
if (!empty($forceSyncModule)) {
    unset($systemConfig['modules']);
    $systemConfig['modules'][$forceSyncModule] = true;
}

// Sending results to /v2/results.php for all test types
$url = "$remoteURL/remote/v2/results.php";

try {
    // Check network
    if ($apiService->checkConnectivity("$remoteURL/api/version.php?labId=$labId&version=$version") === false) {
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
        $t = t0();

        $genericQuery = "SELECT generic.*, a.user_name as 'approved_by_name'
                FROM `form_generic` AS generic
                LEFT JOIN `user_details` AS a ON generic.result_approved_by = a.user_id
                WHERE result_status != " . SAMPLE_STATUS\RECEIVED_AT_CLINIC . "
                AND IFNULL(generic.sample_code, '') != ''";

        if (!empty($forceSyncModule) && trim((string)$forceSyncModule) == "generic-tests" && !empty($sampleCode)) {
            $genericQuery .= " AND generic.sample_code LIKE '$sampleCode'";
        }

        if (null !== $syncSinceDate) {
            $genericQuery .= " AND generic.last_modified_datetime >= '$syncSinceDate'";
        } else {
            $genericQuery .= " AND generic.data_sync = 0";
        }

        $db->reset();
        $genericLabResult = $db->rawQuery($genericQuery);
        $count = count($genericLabResult);

        $acked = 0;
        $totalChunks = 0;

        if ($count === 0) {
            if ($cliMode) {
                $io->text("Nothing to send for Custom Tests.");
            }
        } else {

            if ($cliMode) {
                $io->text("Selected $count row(s) in " . tdone($t) . "s");
            }
            /** @var GenericTestsService $genericService */
            $genericService = ContainerRegistry::get(GenericTestsService::class);

            // Build nested payload
            if ($cliMode) {
                $io->text("Building payload...");
            }
            $tBuild = t0();
            $customTestResultData = [];
            foreach ($genericLabResult as $r) {
                $customTestResultData[$r['unique_id']] = [
                    'form_data' => $r,
                    'data_from_tests' => $genericService->getTestsByGenericSampleIds($r['sample_id']),
                ];
            }
            if ($cliMode) {
                $io->comment("Built payload in " . tdone($tBuild) . "s");
            }

            $chunks = array_chunk($customTestResultData, max(1, $chunkSize), true);
            $totalChunks = count($chunks);

            foreach ($chunks as $chunkIndex => $chunk) {
                $chunkNumber = $chunkIndex + 1;
                $chunkCount = count($chunk);

                if ($cliMode) {
                    $io->text(sprintf(
                        "Posting chunk %d/%d (%d record%s) to %s...",
                        $chunkNumber,
                        $totalChunks,
                        $chunkCount,
                        $chunkCount === 1 ? '' : 's',
                        $remoteURL
                    ));
                }
                $tPost = t0();
                $payload = [
                    "labId" => $labId,
                    "results" => $chunk,
                    "testType" => "generic-tests",
                    'timestamp' => DateUtility::getCurrentTimestamp(),
                    "instanceId" => $general->getInstanceId(),
                    "silent" => $isSilent
                ];
                $jsonResponse = $apiService->post($url, $payload, gzip: true);
                if ($cliMode) {
                    $io->comment("Chunk $chunkNumber POST completed in " . tdone($tPost) . "s");
                }

                $acknowledgedSamples = decodeAcknowledgedSampleCodes($jsonResponse, 'generic-tests');
                $acked += count($acknowledgedSamples);

                if (!empty($acknowledgedSamples)) {
                    if ($cliMode) {
                        $io->text("Updating local sync flags for " . count($acknowledgedSamples) . " row(s)...");
                    }
                    $tUpd = t0();
                    $db->where('sample_code', $acknowledgedSamples, 'IN');
                    $db->update('form_generic', ['data_sync' => 1, 'result_sent_to_source' => 'sent']);
                    if ($cliMode) {
                        $io->comment("DB update done in " . tdone($tUpd) . "s");
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

            if ($cliMode) {
                $io->success("Custom Tests: acknowledged $acked / $count row(s). Total " . tdone($t) . "s");
            }
        }

        $summaryRequest = [
            'recordsSelected' => $count,
            'chunkSize' => $chunkSize,
            'chunksProcessed' => $totalChunks,
        ];
        $summaryResponse = [
            'recordsAcknowledged' => $acked,
        ];
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
            $labId
        );
    }

    // ----------------------- VL -----------------------
    if (isset($systemConfig['modules']['vl']) && $systemConfig['modules']['vl'] === true) {
        if ($cliMode) {
            $io->section("HIV Viral Load");
            $io->text("Selecting rows...");
        }
        $t = t0();

        $vlQuery = "SELECT vl.*, a.user_name as 'approved_by_name'
            FROM `form_vl` AS vl
            LEFT JOIN `user_details` AS a ON vl.result_approved_by = a.user_id
            WHERE result_status != " . SAMPLE_STATUS\RECEIVED_AT_CLINIC . "
            AND IFNULL(vl.sample_code, '') != ''";

        if (!empty($forceSyncModule) && trim((string)$forceSyncModule) == "vl" && !empty($sampleCode)) {
            $vlQuery .= " AND sample_code LIKE '$sampleCode'";
        }
        if (null !== $syncSinceDate) {
            $vlQuery .= " AND vl.last_modified_datetime >= '$syncSinceDate'";
        } else {
            $vlQuery .= " AND vl.data_sync = 0";
        }

        $db->reset();
        $vlLabResult = $db->rawQuery($vlQuery);
        $count = count($vlLabResult);

        $acked = 0;
        $totalChunks = 0;

        if ($count === 0) {
            if ($cliMode) {
                $io->text("Nothing to send for VL.");
            }
        } else {
            if ($cliMode) {
                $io->text("Selected $count row(s) in " . tdone($t) . "s");
            }
            $chunks = array_chunk($vlLabResult, max(1, $chunkSize), true);
            $totalChunks = count($chunks);

            foreach ($chunks as $chunkIndex => $chunk) {
                $chunkNumber = $chunkIndex + 1;
                $chunkCount = count($chunk);

                if ($cliMode) {
                    $io->text(sprintf(
                        "Posting chunk %d/%d (%d record%s) to %s...",
                        $chunkNumber,
                        $totalChunks,
                        $chunkCount,
                        $chunkCount === 1 ? '' : 's',
                        $remoteURL
                    ));
                }

                $tPost = t0();
                $payload = [
                    "labId" => $labId,
                    "results" => $chunk,
                    "testType" => "vl",
                    'timestamp' => DateUtility::getCurrentTimestamp(),
                    "instanceId" => $general->getInstanceId()
                ];
                $jsonResponse = $apiService->post($url, $payload, gzip: true);
                if ($cliMode) {
                    $io->comment("Chunk $chunkNumber POST completed in " . tdone($tPost) . "s");
                }

                $acknowledgedSamples = decodeAcknowledgedSampleCodes($jsonResponse, 'vl');
                $acked += count($acknowledgedSamples);

                if (!empty($acknowledgedSamples)) {
                    if ($cliMode) {
                        $io->text("Updating local sync flags for " . count($acknowledgedSamples) . " row(s)...");
                    }
                    $tUpd = t0();
                    $db->where('sample_code', $acknowledgedSamples, 'IN');
                    $db->update('form_vl', ['data_sync' => 1, 'result_sent_to_source' => 'sent']);
                    if ($cliMode) {
                        $io->comment("DB update done in " . tdone($tUpd) . "s");
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

            if ($cliMode) {
                $io->text("VL: acknowledged $acked / $count row(s). Total " . tdone($t) . "s");
            }
        }

        $summaryRequest = [
            'recordsSelected' => $count,
            'chunkSize' => $chunkSize,
            'chunksProcessed' => $totalChunks,
        ];
        $summaryResponse = [
            'recordsAcknowledged' => $acked,
        ];
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
            $labId
        );
    }

    // ----------------------- EID -----------------------
    if (isset($systemConfig['modules']['eid']) && $systemConfig['modules']['eid'] === true) {
        if ($cliMode) {
            $io->section("EID");
            $io->text("Selecting rows...");
        }
        $t = t0();

        $eidQuery = "SELECT vl.*, a.user_name as 'approved_by_name'
                FROM `form_eid` AS vl
                LEFT JOIN `user_details` AS a ON vl.result_approved_by = a.user_id
                WHERE result_status != " . SAMPLE_STATUS\RECEIVED_AT_CLINIC . "
                AND IFNULL(vl.sample_code, '') != ''";

        if (!empty($forceSyncModule) && trim((string)$forceSyncModule) == "eid" && !empty($sampleCode)) {
            $eidQuery .= " AND sample_code LIKE '$sampleCode'";
        }
        if (null !== $syncSinceDate) {
            $eidQuery .= " AND vl.last_modified_datetime >= '$syncSinceDate'";
        } else {
            $eidQuery .= " AND vl.data_sync = 0";
        }

        $db->reset();
        $eidLabResult = $db->rawQuery($eidQuery);
        $count = count($eidLabResult);

        $acked = 0;
        $totalChunks = 0;

        if ($count === 0) {
            if ($cliMode) {
                $io->text("Nothing to send for EID.");
            }
        } else {

            if ($cliMode) {
                $io->text("Selected $count row(s) in " . tdone($t) . "s");
            }
            $chunks = array_chunk($eidLabResult, max(1, $chunkSize), true);
            $totalChunks = count($chunks);

            foreach ($chunks as $chunkIndex => $chunk) {
                $chunkNumber = $chunkIndex + 1;
                $chunkCount = count($chunk);

                if ($cliMode) {
                    $io->text(sprintf(
                        "Posting chunk %d/%d (%d record%s) to %s...",
                        $chunkNumber,
                        $totalChunks,
                        $chunkCount,
                        $chunkCount === 1 ? '' : 's',
                        $remoteURL
                    ));
                }
                $tPost = t0();
                $payload = [
                    "labId" => $labId,
                    "results" => $chunk,
                    "testType" => "eid",
                    'timestamp' => DateUtility::getCurrentTimestamp(),
                    "instanceId" => $general->getInstanceId()
                ];
                $jsonResponse = $apiService->post($url, $payload, gzip: true);
                if ($cliMode) {
                    $io->comment("Chunk $chunkNumber POST completed in " . tdone($tPost) . "s");
                }

                $acknowledgedSamples = decodeAcknowledgedSampleCodes($jsonResponse, 'eid');
                $acked += count($acknowledgedSamples);

                if (!empty($acknowledgedSamples)) {
                    if ($cliMode) {
                        $io->text("Updating local sync flags for " . count($acknowledgedSamples) . " row(s)...");
                    }
                    $tUpd = t0();
                    $db->where('sample_code', $acknowledgedSamples, 'IN');
                    $db->update('form_eid', ['data_sync' => 1, 'result_sent_to_source' => 'sent']);
                    if ($cliMode) {
                        $io->comment("DB update done in " . tdone($tUpd) . "s");
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

            if ($cliMode) {
                $io->text("EID: acknowledged $acked / $count row(s). Total " . tdone($t) . "s");
            }
        }

        $summaryRequest = [
            'recordsSelected' => $count,
            'chunkSize' => $chunkSize,
            'chunksProcessed' => $totalChunks,
        ];
        $summaryResponse = [
            'recordsAcknowledged' => $acked,
        ];
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
            $labId
        );
    }

    // ----------------------- COVID-19 -----------------------
    if (isset($systemConfig['modules']['covid19']) && $systemConfig['modules']['covid19'] === true) {
        if ($cliMode) {
            $io->section("COVID-19");
            $io->text("Selecting rows...");
        }
        $t = t0();

        $covid19Query = "SELECT c19.*, a.user_name as 'approved_by_name'
                FROM `form_covid19` AS c19
                LEFT JOIN `user_details` AS a ON c19.result_approved_by = a.user_id
                WHERE result_status != " . SAMPLE_STATUS\RECEIVED_AT_CLINIC . "
                AND IFNULL(c19.sample_code, '') != ''";

        if (!empty($forceSyncModule) && trim((string)$forceSyncModule) == "covid19" && !empty($sampleCode)) {
            $covid19Query .= " AND sample_code LIKE '$sampleCode'";
        }
        if (null !== $syncSinceDate) {
            $covid19Query .= " AND c19.last_modified_datetime >= '$syncSinceDate'";
        } else {
            $covid19Query .= " AND c19.data_sync = 0";
        }

        $db->reset();
        $c19LabResult = $db->rawQuery($covid19Query);
        $count = count($c19LabResult);

        $acked = 0;
        $totalChunks = 0;

        if ($count === 0) {
            if ($cliMode) {
                $io->text("Nothing to send for COVID-19.");
            }
        } else {

            if ($cliMode) {
                $io->text("Selected $count row(s) in " . tdone($t) . "s");
            }

            /** @var Covid19Service $covid19Service */
            $covid19Service = ContainerRegistry::get(Covid19Service::class);

            // Build nested payload
            if ($cliMode) {
                $io->text("Building payload...");
            }
            $tBuild = t0();
            $c19ResultData = [];
            foreach ($c19LabResult as $r) {
                $c19ResultData[$r['unique_id']] = [
                    'form_data' => $r,
                    'data_from_tests' => $covid19Service->getCovid19TestsByFormId($r['covid19_id']),
                ];
            }
            if ($cliMode) {
                $io->comment("Built payload in " . tdone($tBuild) . "s");
            }

            $chunks = array_chunk($c19ResultData, max(1, $chunkSize), true);
            $totalChunks = count($chunks);

            foreach ($chunks as $chunkIndex => $chunk) {
                $chunkNumber = $chunkIndex + 1;
                $chunkCount = count($chunk);

                if ($cliMode) {
                    $io->text(sprintf(
                        "Posting chunk %d/%d (%d record%s) to %s...",
                        $chunkNumber,
                        $totalChunks,
                        $chunkCount,
                        $chunkCount === 1 ? '' : 's',
                        $remoteURL
                    ));
                }
                $tPost = t0();
                $payload = [
                    "labId" => $labId,
                    "results" => $chunk,
                    "testType" => "covid19",
                    'timestamp' => DateUtility::getCurrentTimestamp(),
                    "instanceId" => $general->getInstanceId()
                ];
                $jsonResponse = $apiService->post($url, $payload, gzip: true);
                if ($cliMode) {
                    $io->comment("Chunk $chunkNumber POST completed in " . tdone($tPost) . "s");
                }

                $acknowledgedSamples = decodeAcknowledgedSampleCodes($jsonResponse, 'covid19');
                $acked += count($acknowledgedSamples);

                if (!empty($acknowledgedSamples)) {
                    if ($cliMode) {
                        $io->text("Updating local sync flags for " . count($acknowledgedSamples) . " row(s)...");
                    }
                    $tUpd = t0();
                    $db->where('sample_code', $acknowledgedSamples, 'IN');
                    $db->update('form_covid19', ['data_sync' => 1, 'result_sent_to_source' => 'sent']);
                    if ($cliMode) {
                        $io->comment("DB update done in " . tdone($tUpd) . "s");
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

            if ($cliMode) {
                $io->text("COVID-19: acknowledged $acked / $count row(s). Total " . tdone($t) . "s");
            }
        }

        $summaryRequest = [
            'recordsSelected' => $count,
            'chunkSize' => $chunkSize,
            'chunksProcessed' => $totalChunks,
        ];
        $summaryResponse = [
            'recordsAcknowledged' => $acked,
        ];
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
            $labId
        );
    }

    // ----------------------- HEPATITIS -----------------------
    if (isset($systemConfig['modules']['hepatitis']) && $systemConfig['modules']['hepatitis'] === true) {
        if ($cliMode) {
            $io->section("Hepatitis");
            $io->text("Selecting rows...");
        }
        $t = t0();

        $hepQuery = "SELECT hep.*, a.user_name as 'approved_by_name'
                FROM `form_hepatitis` AS hep
                LEFT JOIN `user_details` AS a ON hep.result_approved_by = a.user_id
                WHERE result_status != " . SAMPLE_STATUS\RECEIVED_AT_CLINIC . "
                AND IFNULL(hep.sample_code, '') != ''";

        if (!empty($forceSyncModule) && trim((string)$forceSyncModule) == "hepatitis" && !empty($sampleCode)) {
            $hepQuery .= " AND sample_code LIKE '$sampleCode'";
        }
        if (null !== $syncSinceDate) {
            $hepQuery .= " AND hep.last_modified_datetime >= '$syncSinceDate'";
        } else {
            $hepQuery .= " AND hep.data_sync = 0";
        }

        $db->reset();
        $hepLabResult = $db->rawQuery($hepQuery);
        $count = count($hepLabResult);

        $acked = 0;
        $totalChunks = 0;

        if ($count === 0) {
            if ($cliMode) {
                $io->text("Nothing to send for Hepatitis.");
            }
        } else {
            if ($cliMode) {
                $io->text("Selected $count row(s) in " . tdone($t) . "s");
            }
            $chunks = array_chunk($hepLabResult, max(1, $chunkSize), true);
            $totalChunks = count($chunks);

            foreach ($chunks as $chunkIndex => $chunk) {
                $chunkNumber = $chunkIndex + 1;
                $chunkCount = count($chunk);

                if ($cliMode) {
                    $io->text(sprintf(
                        "Posting chunk %d/%d (%d record%s) to %s...",
                        $chunkNumber,
                        $totalChunks,
                        $chunkCount,
                        $chunkCount === 1 ? '' : 's',
                        $remoteURL
                    ));
                }
                $tPost = t0();
                $payload = [
                    "labId" => $labId,
                    "results" => $chunk,
                    "testType" => "hepatitis",
                    'timestamp' => DateUtility::getCurrentTimestamp(),
                    "instanceId" => $general->getInstanceId()
                ];
                $jsonResponse = $apiService->post($url, $payload, gzip: true);
                if ($cliMode) {
                    $io->comment("Chunk $chunkNumber POST completed in " . tdone($tPost) . "s");
                }

                $acknowledgedSamples = decodeAcknowledgedSampleCodes($jsonResponse, 'hepatitis');
                $acked += count($acknowledgedSamples);

                if (!empty($acknowledgedSamples)) {
                    if ($cliMode) {
                        $io->text("Updating local sync flags for " . count($acknowledgedSamples) . " row(s)...");
                    }
                    $tUpd = t0();
                    $db->where('sample_code', $acknowledgedSamples, 'IN');
                    $db->update('form_hepatitis', ['data_sync' => 1, 'result_sent_to_source' => 'sent']);
                    if ($cliMode) {
                        $io->comment("DB update done in " . tdone($tUpd) . "s");
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

            if ($cliMode) {
                $io->text("Hepatitis: acknowledged $acked / $count row(s). Total " . tdone($t) . "s");
            }
        }

        $summaryRequest = [
            'recordsSelected' => $count,
            'chunkSize' => $chunkSize,
            'chunksProcessed' => $totalChunks,
        ];
        $summaryResponse = [
            'recordsAcknowledged' => $acked,
        ];
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
            $labId
        );
    }

    // ----------------------- TB -----------------------
    if (isset($systemConfig['modules']['tb']) && $systemConfig['modules']['tb'] === true) {
        if ($cliMode) {
            $io->section("TB");
            $io->text("Selecting rows...");
        }
        $t = t0();

        /** @var TbService $tbService */
        $tbService = ContainerRegistry::get(TbService::class);

        $tbQuery = "SELECT tb.*, a.user_name as 'approved_by_name'
            FROM `form_tb` AS tb
            LEFT JOIN `user_details` AS a ON tb.result_approved_by = a.user_id
            WHERE result_status != " . SAMPLE_STATUS\RECEIVED_AT_CLINIC . "
            AND IFNULL(tb.sample_code, '') != ''";

        if (!empty($forceSyncModule) && trim((string)$forceSyncModule) == "tb" && !empty($sampleCode)) {
            $tbQuery .= " AND sample_code LIKE '$sampleCode'";
        }
        if (null !== $syncSinceDate) {
            $tbQuery .= " AND tb.last_modified_datetime >= '$syncSinceDate'";
        } else {
            $tbQuery .= " AND tb.data_sync = 0";
        }

        $db->reset();
        $tbLabResult = $db->rawQuery($tbQuery);
        $count = count($tbLabResult);

        $acked = 0;
        $totalChunks = 0;

        if ($count === 0) {
            if ($cliMode) {
                $io->text("Nothing to send for TB.");
            }
        } else {
            if ($cliMode) {
                $io->text("Selected $count row(s) in " . tdone($t) . "s");
            }
            // Build nested payload
            if ($cliMode) {
                $io->text("Building payload...");
            }
            $tBuild = t0();
            $tbTestResultData = [];
            foreach ($tbLabResult as $r) {
                $tbTestResultData[$r['unique_id']] = [
                    'form_data' => $r,
                    'data_from_tests' => $tbService->getTbTestsByFormId($r['tb_id']),
                ];
            }
            if ($cliMode) {
                $io->comment("Built payload in " . tdone($tBuild) . "s");
            }

            $chunks = array_chunk($tbTestResultData, max(1, $chunkSize), true);
            $totalChunks = count($chunks);

            foreach ($chunks as $chunkIndex => $chunk) {
                $chunkNumber = $chunkIndex + 1;
                $chunkCount = count($chunk);

                $manifests = buildReferralManifestsPayload($db, 'tb', $chunk);

                if ($cliMode) {
                    $io->text(sprintf(
                        "Posting chunk %d/%d (%d record%s) to %s...",
                        $chunkNumber,
                        $totalChunks,
                        $chunkCount,
                        $chunkCount === 1 ? '' : 's',
                        $remoteURL
                    ));
                }
                $tPost = t0();
                $payload = [
                    "labId" => $labId,
                    "results" => $chunk,
                    "testType" => "tb",
                    'manifests' => $manifests,
                    'timestamp' => DateUtility::getCurrentTimestamp(),
                    "instanceId" => $general->getInstanceId()
                ];
                $jsonResponse = $apiService->post($url, $payload, gzip: true);
                if ($cliMode) {
                    $io->comment("Chunk $chunkNumber POST completed in " . tdone($tPost) . "s");
                }

                $acknowledgedSamples = decodeAcknowledgedSampleCodes($jsonResponse, 'tb');
                $acked += count($acknowledgedSamples);

                if (!empty($acknowledgedSamples)) {
                    if ($cliMode) {
                        $io->text("Updating local sync flags for " . count($acknowledgedSamples) . " row(s)...");
                    }
                    $tUpd = t0();
                    $db->where('sample_code', $acknowledgedSamples, 'IN');
                    $db->update('form_tb', ['data_sync' => 1, 'result_sent_to_source' => 'sent']);
                    if ($cliMode) {
                        $io->comment("DB update done in " . tdone($tUpd) . "s");
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

            if ($cliMode) {
                $io->text("TB: acknowledged $acked / $count row(s). Total " . tdone($t) . "s");
            }
        }

        $summaryRequest = [
            'recordsSelected' => $count,
            'chunkSize' => $chunkSize,
            'chunksProcessed' => $totalChunks,
        ];
        $summaryResponse = [
            'recordsAcknowledged' => $acked,
        ];
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
            $labId
        );
    }

    // ----------------------- CD4 -----------------------
    if (isset($systemConfig['modules']['cd4']) && $systemConfig['modules']['cd4'] === true) {
        if ($cliMode) {
            $io->section("CD4");
            $io->text("Selecting rows...");
        }
        $t = t0();

        $cd4Query = "SELECT cd4.*, a.user_name as 'approved_by_name'
            FROM `form_cd4` AS cd4
            LEFT JOIN `user_details` AS a ON cd4.result_approved_by = a.user_id
            WHERE result_status != " . SAMPLE_STATUS\RECEIVED_AT_CLINIC . "
            AND IFNULL(cd4.sample_code, '') != ''";

        if (!empty($forceSyncModule) && trim((string)$forceSyncModule) == "cd4" && !empty($sampleCode)) {
            $cd4Query .= " AND sample_code LIKE '$sampleCode'";
        }
        if (null !== $syncSinceDate) {
            $cd4Query .= " AND cd4.last_modified_datetime >= '$syncSinceDate'";
        } else {
            $cd4Query .= " AND cd4.data_sync = 0";
        }

        $db->reset();
        $cd4LabResult = $db->rawQuery($cd4Query);
        $count = count($cd4LabResult);
        $acked = 0;
        $totalChunks = 0;

        if ($count === 0) {
            if ($cliMode) {
                $io->text("Nothing to send for CD4.");
            }
        } else {

            if ($cliMode) {
                $io->text("Selected $count row(s) in " . tdone($t) . "s");
            }

            $chunks = array_chunk($cd4LabResult, max(1, $chunkSize), true);
            $totalChunks = count($chunks);

            foreach ($chunks as $chunkIndex => $chunk) {
                $chunkNumber = $chunkIndex + 1;
                $chunkCount = count($chunk);

                if ($cliMode) {
                    $io->text(sprintf(
                        "Posting chunk %d/%d (%d record%s) to %s...",
                        $chunkNumber,
                        $totalChunks,
                        $chunkCount,
                        $chunkCount === 1 ? '' : 's',
                        $remoteURL
                    ));
                }
                $tPost = t0();
                $payload = [
                    "labId" => $labId,
                    "results" => $chunk,
                    "testType" => "cd4",
                    'timestamp' => DateUtility::getCurrentTimestamp(),
                    "instanceId" => $general->getInstanceId()
                ];
                $jsonResponse = $apiService->post($url, $payload, gzip: true);
                if ($cliMode) {
                    $io->comment("Chunk $chunkNumber POST completed in " . tdone($tPost) . "s");
                }

                $acknowledgedSamples = decodeAcknowledgedSampleCodes($jsonResponse, 'cd4');
                $acked += count($acknowledgedSamples);

                if (!empty($acknowledgedSamples)) {
                    if ($cliMode) {
                        $io->text("Updating local sync flags for " . count($acknowledgedSamples) . " row(s)...");
                    }
                    $tUpd = t0();
                    $db->where('sample_code', $acknowledgedSamples, 'IN');
                    $db->update('form_cd4', ['data_sync' => 1, 'result_sent_to_source' => 'sent']);
                    if ($cliMode) {
                        $io->comment("DB update done in " . tdone($tUpd) . "s");
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

            if ($cliMode) {
                $io->text("CD4: acknowledged $acked / $count row(s). Total " . tdone($t) . "s");
            }
        }

        $summaryRequest = [
            'recordsSelected' => $count,
            'chunkSize' => $chunkSize,
            'chunksProcessed' => $totalChunks,
        ];
        $summaryResponse = [
            'recordsAcknowledged' => $acked,
        ];
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
            $labId
        );
    }

    // Final sync timestamp update
    if ($cliMode) {
        $io->section("Timestamps");
        $io->text("Updating sync timestamps...");
    }
    $tFinal = t0();
    $instanceId = $general->getInstanceId();
    $db->where('vlsm_instance_id', $instanceId);
    $db->update('s_vlsm_instance', ['last_remote_results_sync' => DateUtility::getCurrentDateTime()]);
    if ($cliMode) {
        $io->text("Updated timestamps in " . tdone($tFinal) . "s");
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
