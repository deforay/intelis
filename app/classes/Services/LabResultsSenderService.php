<?php

declare(strict_types=1);

namespace App\Services;

use Closure;
use Throwable;
use RuntimeException;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Utilities\LoggerUtility;
use App\Utilities\ResultSyncBatch;
use App\Utilities\ResultSyncPayload;
use App\Registries\ContainerRegistry;
use App\Utilities\ResultSyncBatchSize;
use App\Utilities\ResultSyncAcknowledgement;
use Symfony\Component\Console\Style\SymfonyStyle;

use const SAMPLE_STATUS\RECEIVED_AT_CLINIC;

/**
 * Sends one module's results from the lab to the STS, for results-sender.php.
 *
 * Moved out of the script, which had a copy of this per module, so it can be driven
 * in tests. What it does per module: select the rows not yet synced (or changed since
 * a date), mark each slice in flight just before reading it, post it in chunks, and
 * mark synced only the rows the STS acknowledged and that nothing changed since. An
 * answer that is not an acknowledgment stops the module, leaving its rows pending.
 *
 * The run itself (arguments, the single-sender lock, releasing rows a previous run
 * left in flight, the final timestamp) stays in the script.
 */
final class LabResultsSenderService
{
    /**
     * In the order the script sends them.
     *
     * @var array<string, array{table: string, primaryKey: string, alias: string, section: string, name: string,
     *     keyByUniqueId: bool}>
     */
    public const array MODULES = [
        'generic-tests' => [
            'table' => 'form_generic', 'primaryKey' => 'sample_id', 'alias' => 'generic',
            'section' => 'Custom Tests', 'name' => 'Custom Tests', 'keyByUniqueId' => true,
        ],
        'vl' => [
            'table' => 'form_vl', 'primaryKey' => 'vl_sample_id', 'alias' => 'vl',
            'section' => 'HIV Viral Load', 'name' => 'VL', 'keyByUniqueId' => false,
        ],
        'eid' => [
            'table' => 'form_eid', 'primaryKey' => 'eid_id', 'alias' => 'vl',
            'section' => 'EID', 'name' => 'EID', 'keyByUniqueId' => false,
        ],
        'covid19' => [
            'table' => 'form_covid19', 'primaryKey' => 'covid19_id', 'alias' => 'c19',
            'section' => 'COVID-19', 'name' => 'COVID-19', 'keyByUniqueId' => true,
        ],
        'hepatitis' => [
            'table' => 'form_hepatitis', 'primaryKey' => 'hepatitis_id', 'alias' => 'hep',
            'section' => 'Hepatitis', 'name' => 'Hepatitis', 'keyByUniqueId' => false,
        ],
        'tb' => [
            'table' => 'form_tb', 'primaryKey' => 'tb_id', 'alias' => 'tb',
            'section' => 'TB', 'name' => 'TB', 'keyByUniqueId' => true,
        ],
        'cd4' => [
            'table' => 'form_cd4', 'primaryKey' => 'cd4_id', 'alias' => 'cd4',
            'section' => 'CD4', 'name' => 'CD4', 'keyByUniqueId' => false,
        ],
    ];

    public function __construct(
        private readonly DatabaseService $db,
        private readonly CommonService $general,
        private readonly ApiService $apiService
    ) {
    }

    /**
     * @param array{
     *     labId: int|string, url: string, remoteUrl: string, transactionId: string,
     *     syncSinceDate: ?string, sampleCode: ?string, maxChunkSize: int, maxPayloadBytes: int,
     *     dryRun: bool, silent: bool
     * } $run
     * @param int $chunkSize records per request; the STS's timing hints change it, and the
     *                       change carries on to the next module, as it did in the script
     * @param null|callable(string, string): (array|string|null) $post url and JSON body; the
     *                       answer as ApiService::post(..., returnWithStatusCode: true) gives it
     * @return array{selected: int, acknowledged: int, chunks: int}
     */
    public function sendModule(
        string $testType,
        array $run,
        int &$chunkSize,
        ?SymfonyStyle $io = null,
        ?callable $post = null
    ): array {
        $module = self::MODULES[$testType] ?? null;
        if ($module === null) {
            throw new RuntimeException("Unknown test type: $testType");
        }
        $post ??= fn(string $url, string $json): array|string|null
            => $this->apiService->post($url, $json, gzip: true, returnWithStatusCode: true);

        $db = $this->db;
        $general = $this->general;
        $table = $module['table'];
        $primaryKey = $module['primaryKey'];
        $alias = $module['alias'];
        $url = $run['url'];
        $labId = $run['labId'];
        $isDryRun = $run['dryRun'];

        $io?->section($module['section']);
        $io?->text("Selecting rows...");
        $t = MiscUtility::startTimer();

        $query = "SELECT $alias.*, a.user_name as 'approved_by_name'
            FROM `$table` AS $alias
            LEFT JOIN `user_details` AS a ON $alias.result_approved_by = a.user_id
            WHERE result_status != " . RECEIVED_AT_CLINIC . "
            AND IFNULL($alias.sample_code, '') != ''";

        if (!empty($run['sampleCode'])) {
            // Exact match on a quoted literal: the value can come from the query string.
            $query .= " AND $alias.sample_code = " . $db->quote($run['sampleCode']);
        }
        if (null !== $run['syncSinceDate']) {
            $query .= " AND $alias.last_modified_datetime >= " . $db->quote($run['syncSinceDate']);
        } else {
            $query .= " AND $alias.data_sync IN (0, " . ResultSyncAcknowledgement::IN_FLIGHT . ")";
        }

        $db->reset();
        $resultBatch = new ResultSyncBatch(
            $this->queryResults(...),
            $query,
            "$alias.$primaryKey",
            keyByUniqueId: $module['keyByUniqueId'],
            // Rows are marked in flight just before each chunk is read, so any change
            // after that point sets data_sync back to 0 and keeps the row pending past
            // its acknowledgment. The selection accepts 2 so the marked rows are still read.
            beforeFetch: static function (array $ids) use ($db, $table, $primaryKey): void {
                ResultSyncAcknowledgement::markInFlight($db, $table, $primaryKey, $ids);
            }
        );
        $count = count($resultBatch);

        $acked = 0;
        $chunksProcessed = 0;
        // Keep the operator's requested size as a ceiling throughout adaptive batching.
        $maxChunkSize = $run['maxChunkSize'];
        $nextBatchSize = static function () use (&$chunkSize): int {
            return max(1, $chunkSize);
        };

        if ($count === 0) {
            $io?->text("Nothing to send for {$module['name']}.");
        } else {
            $io?->text("Selected $count row(s) in " . MiscUtility::elapsedTime($t) . "s");

            $totalChunks = (int) ceil($count / max(1, $chunkSize));
            $childLoader = $this->childLoader($testType);
            $chunks = $childLoader === null
                ? $resultBatch->chunks($nextBatchSize)
                : $resultBatch->chunks($nextBatchSize, prepareBatch: $childLoader);

            if ($isDryRun) {
                if ($io !== null) {
                    self::reportDryRunChunks($io, $testType, $resultBatch->preview(), $totalChunks, $count);
                }
                $chunks = [];
            }

            $requests = ResultSyncPayload::requests(
                $chunks,
                fn(array $chunk): array => $this->buildResultPayload($chunk, $testType, $labId, $run['silent']),
                $run['maxPayloadBytes'],
                $nextBatchSize
            );
            foreach ($requests as $chunkIndex => $preparedRequest) {
                $payload = $preparedRequest['payload'];
                $chunk = $payload['results'];
                self::reportPreparedResult($io, $resultBatch, $preparedRequest);
                $chunksProcessed++;
                $chunkNumber = $chunkIndex + 1;
                $chunkCount = count($chunk);

                $io?->text(sprintf(
                    "Posting chunk %d (%d record%s) to %s...",
                    $chunkNumber,
                    $chunkCount,
                    $chunkCount === 1 ? '' : 's',
                    $run['remoteUrl']
                ));
                $tPost = MiscUtility::startTimer();

                $apiResponse = $post($url, $preparedRequest['json']);
                $responseHeaders = self::unpackApiResponse($apiResponse)['headers'];
                self::showServerHints($io, $responseHeaders, $testType);
                $chunkSize = ResultSyncBatchSize::next(
                    $chunkSize,
                    $maxChunkSize,
                    $responseHeaders,
                    microtime(true) - $tPost
                );
                $io?->comment("Chunk $chunkNumber POST completed in " . MiscUtility::elapsedTime($tPost) . "s");

                try {
                    $acknowledgedSamples = ResultSyncAcknowledgement::fromResponse($apiResponse, $testType);
                } catch (RuntimeException $e) {
                    self::reportModuleSyncFailure($io, $testType, $chunkNumber, $e);
                    break;
                }
                $acked += count($acknowledgedSamples);

                if ($acknowledgedSamples !== []) {
                    $tUpd = MiscUtility::startTimer();
                    $marked = self::markAcknowledgedResults(
                        $db,
                        $table,
                        $primaryKey,
                        $chunk,
                        $acknowledgedSamples,
                        $responseHeaders
                    );
                    if ($io !== null) {
                        self::reportMarkedResults(
                            $io,
                            count($acknowledgedSamples),
                            $marked,
                            MiscUtility::elapsedTime($tUpd)
                        );
                    }
                }

                $io?->comment(sprintf('Acknowledged %d/%d so far', $acked, $count));

                $chunkTransactionId = $run['transactionId'] . "-$testType-"
                    . str_pad((string) $chunkNumber, 3, '0', STR_PAD_LEFT);
                $general->addApiTracking(
                    $chunkTransactionId,
                    'intelis-system',
                    $chunkCount,
                    'send-results',
                    $testType,
                    $url,
                    $payload,
                    $acknowledgedSamples,
                    'json',
                    $labId
                );
            }

            if (!$isDryRun) {
                $summary = "{$module['name']}: acknowledged $acked / $count row(s). Total "
                    . MiscUtility::elapsedTime($t) . "s";
                $testType === 'generic-tests' ? $io?->success($summary) : $io?->text($summary);
            }
        }

        if (!$isDryRun) {
            $general->addApiTracking(
                $run['transactionId'],
                'intelis-system',
                $count,
                'send-results',
                $testType,
                $url,
                [
                    'recordsSelected' => $count,
                    'chunkSize' => $chunkSize,
                    'chunksProcessed' => $chunksProcessed,
                ],
                [
                    'recordsAcknowledged' => $acked,
                ],
                'json',
                $labId,
                emptyPoll: $count === 0
            );
        }

        return ['selected' => $count, 'acknowledged' => $acked, 'chunks' => $chunksProcessed];
    }

    /** @return list<array<string, mixed>> */
    private function queryResults(string $sql, array $params): array
    {
        $this->db->reset();
        $rows = $this->db->rawQuery($sql, $params);
        // The in-flight marker is this lab's bookkeeping, not part of the result: send
        // the pending value it replaced, so no STS ever stores a 2.
        foreach ($rows as &$row) {
            if (isset($row['data_sync']) && (int) $row['data_sync'] === ResultSyncAcknowledgement::IN_FLIGHT) {
                $row['data_sync'] = 0;
            }
        }
        unset($row);
        return $rows;
    }

    /** The modules whose results carry child test rows, nested under each parent. */
    private function childLoader(string $testType): ?Closure
    {
        return match ($testType) {
            'generic-tests' => static function (array $rows): array {
                $children = ContainerRegistry::get(GenericTestsService::class)
                    ->getTestsByGenericSampleIds(array_column($rows, 'sample_id'));
                return ResultSyncBatch::nestedPayload($rows, $children ?? [], 'sample_id');
            },
            'covid19' => static function (array $rows): array {
                $children = ContainerRegistry::get(Covid19Service::class)
                    ->getCovid19TestsByFormId(array_column($rows, 'covid19_id'));
                return ResultSyncBatch::nestedPayload($rows, $children ?? [], 'covid19_id', wrapParentId: true);
            },
            'tb' => static function (array $rows): array {
                $children = ContainerRegistry::get(TbService::class)
                    ->getTbTestsByFormId(array_column($rows, 'tb_id'));
                return ResultSyncBatch::nestedPayload($rows, $children ?? [], 'tb_id');
            },
            default => null,
        };
    }

    /** @return array<string, mixed> */
    private function buildResultPayload(array $chunk, string $testType, int|string $labId, bool $isSilent): array
    {
        $payload = [
            'labId' => $labId,
            'results' => $chunk,
            'testType' => $testType,
            'timestamp' => DateUtility::getCurrentTimestamp(),
            'instanceId' => $this->general->getInstanceId(),
            // An updated STS then acknowledges by unique_id; an older one ignores this.
            'ackFormat' => ResultSyncAcknowledgement::UNIQUE_ID,
        ];
        if ($testType === 'generic-tests') {
            $payload['silent'] = $isSilent;
        } elseif ($testType === 'tb') {
            $payload['manifests'] = self::buildReferralManifestsPayload($this->db, 'tb', $chunk);
        }
        return $payload;
    }

    /**
     * Build payload of referral manifests for a given test type based on selected rows.
     */
    private static function buildReferralManifestsPayload(
        DatabaseService $db,
        string $testType,
        ?array $selectedRows
    ): array {
        if ($selectedRows === null || $selectedRows === []) {
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
     * Unpack API responses that include status/headers.
     *
     * @return array{body:string,headers:array<string,string>}
     */
    private static function unpackApiResponse(array|string|null $response): array
    {
        if (is_array($response)) {
            $headers = is_array($response['headers'] ?? null) ? $response['headers'] : [];
            $body = (string) ($response['body'] ?? '');
            return ['body' => $body, 'headers' => array_change_key_case($headers, CASE_LOWER)];
        }
        return ['body' => (string) ($response ?? ''), 'headers' => []];
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
    private static function markAcknowledgedResults(
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

    private static function reportMarkedResults(SymfonyStyle $io, int $acknowledged, int $marked, string $seconds): void
    {
        $io->text("Marked $marked of $acknowledged acknowledged row(s) as synced in {$seconds}s");
        if ($marked < $acknowledged) {
            $io->comment('Unmarked rows stay pending: edited while their request was in flight,'
                . ' or acknowledged under a code this lab did not send.');
        }
    }

    /** An unreadable acknowledgment stops this module only; the next module still runs. */
    private static function reportModuleSyncFailure(
        ?SymfonyStyle $io,
        string $label,
        int $chunkNumber,
        Throwable $e
    ): void {
        LoggerUtility::logError("Results sync for $label stopped at chunk $chunkNumber: " . $e->getMessage());
        $io?->error(strtoupper($label) . " sync stopped at chunk $chunkNumber: " . rtrim($e->getMessage(), '.')
            . '. Unacknowledged rows stay pending; continuing with the next module.');
    }

    /**
     * Display server hint headers when running via CLI for operator awareness.
     */
    private static function showServerHints(?SymfonyStyle $io, array $headers, ?string $label = null): void
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
    private static function reportPreparedResult(
        ?SymfonyStyle $io,
        ResultSyncBatch $resultBatch,
        array $preparedRequest
    ): void {
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
    private static function reportDryRunChunks(
        SymfonyStyle $io,
        string $label,
        array $rows,
        int $totalChunks,
        int $count
    ): void {
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

        $io->text(sprintf(
            'DRY RUN: would send %d %s row(s) in at least %d chunk(s) before payload-size splitting',
            $count,
            strtoupper($label),
            $totalChunks
        ));
        if ($codes !== []) {
            $suffix = $count > count($codes) ? ', ...' : '';
            $io->text('Sample codes: ' . implode(', ', $codes) . $suffix);
        }
    }
}
