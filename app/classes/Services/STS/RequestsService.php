<?php

namespace App\Services\STS;

use App\Services\TbService;
use App\Services\TestsService;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Services\Covid19Service;
use App\Services\DatabaseService;
use App\Services\HepatitisService;
use App\Registries\ContainerRegistry;
use App\Services\GenericTestsService;
use App\Utilities\JsonUtility;
use App\Utilities\LoggerUtility;
use App\Abstracts\AbstractTestService;
use RuntimeException;
use Throwable;

final class RequestsService
{
    /**
     * Lab receipts. A lab that asks for them gets, besides the usual window, the
     * requests still waiting for it (data_sync 0, or 2 while its receipt is out)
     * whatever their timestamp: an API upload that arrived late, a device clock
     * that was behind, a request that failed on the lab. Sent rows are marked 2
     * (in flight) and its receipt confirms them. See RequestReceiptsService.
     */
    public const int IN_FLIGHT = 2;
    /** Pending requests added to one pull; the lab pulls again while more remain. */
    public const int PENDING_LIMIT = 500;
    public const int PENDING_MAX_AGE_DAYS = 90;
    /** A request that keeps failing on a lab stops being forced into its pulls after this. */
    public const int FAILURE_GIVE_UP_DAYS = 7;
    /** ...and is not retried within the same run, which pulls again straight away. */
    public const int FAILURE_RETRY_AFTER_MINUTES = 30;

    protected DatabaseService $db;
    protected int $dataSyncInterval;
    protected string $testType;
    protected string $tableName;
    protected string $primaryKeyName;
    /** This call's $skipOwnResults: see getRequests(). */
    protected bool $skipOwnResults = false;

    /** @var AbstractTestService $testTypeService */
    protected $testTypeService;

    public function __construct(DatabaseService $db, protected CommonService $commonService)
    {
        $this->db = $db ?? ContainerRegistry::get(DatabaseService::class);
        // The cast ran before ??, so a missing setting became 0 and the window
        // started now, sending nothing. Same default as the lab side: 30 days.
        $interval = (int) ($this->commonService->getGlobalConfig('data_sync_interval') ?? 30);
        $this->dataSyncInterval = $interval > 0 ? $interval : 30;
    }

    /**
     * With $withPending the result also carries receiptsEnabled and pendingRemaining.
     * receiptsEnabled is false when the pending rows could not be read, and the pull
     * is then exactly the plain window it always was.
     *
     * $pendingOnly (with $withPending) leaves the window out: a lab pulling again in
     * the same run already has it, and wants only the requests still waiting.
     *
     * $page (a lab that asks, on a pull over a date or manifest) returns the window
     * a page at a time: up to $page['size'] rows in (last modified, id) order after
     * $page['after'], with nextAfter set while more remain, and remaining, how many
     * rows of the window follow the page. One response for a big
     * window outlasted the lab's timeout and nothing was saved. A lab that does not
     * ask gets the whole window, as it always has.
     *
     * A page is the window alone: waiting requests come with the lab's pulls for
     * them. With $withPending (a lab paging its scheduled window, "windowPage")
     * the page is receipted like any other pull: receiptsEnabled is set, and
     * pendingRemaining is 0, as the page holds none of them.
     *
     * $skipOwnResults (a lab that asks, on its cron pull) leaves out of a date
     * window the rows whose latest change is this lab's own result upload. A lab
     * that does not ask, and every pull it starts by hand to recover rows, gets them.
     *
     * @param array{size: int, after: ?string}|null $page
     */
    public function getRequests(
        $testType,
        $labId,
        $facilityMapResult = [],
        $manifestCode = null,
        $syncSinceDate = null,
        bool $withPending = false,
        bool $pendingOnly = false,
        ?array $page = null,
        bool $skipOwnResults = false
    ) {
        $this->setTestType($testType);
        $this->skipOwnResults = $skipOwnResults;

        if ($page === null) {
            [$pendingIds, $pendingRemaining, $receiptsEnabled] = $this->pendingFor(
                $withPending,
                $labId,
                $facilityMapResult,
                $manifestCode
            );
        } else {
            $pendingOnly = false;
            [$pendingIds, $pendingRemaining] = [[], 0];
            // A receipt is recorded in request_sync_failures: without the table
            // the page is marked sent at once, as for a lab without receipts.
            // requests.php passes $withPending for a manifest only when the lab
            // asked to confirm it (manifestReceipts).
            $receiptsEnabled = $withPending && $this->db->tableExists('request_sync_failures');
        }

        [$rResult, $resultCount] = $this->runQuery(
            $labId,
            $facilityMapResult,
            $manifestCode,
            $syncSinceDate,
            $pendingIds,
            // Only when the pending rows were read: else the plain window.
            withWindow: !($pendingOnly && $receiptsEnabled),
            page: $page
        );
        $nextAfter = null;
        if ($page !== null && count($rResult) > $page['size']) {
            $rResult = array_slice($rResult, 0, $page['size']);
            $last = $rResult[count($rResult) - 1];
            $nextAfter = self::pageMarker($last['last_modified_datetime'] ?? null, $last[$this->primaryKeyName]);
            $resultCount = count($rResult);
        }
        $this->fillCustomTestsDefaults();
        $requestData = $this->shapeRequests($rResult, $resultCount);

        if ($withPending) {
            $requestData['receiptsEnabled'] = $receiptsEnabled;
            $requestData['pendingRemaining'] = $pendingRemaining;
        }
        if ($page !== null) {
            $requestData['paged'] = true;
            $requestData['nextAfter'] = $nextAfter;
            $requestData['remaining'] = $nextAfter === null
                ? 0
                : $this->countAfter($labId, $facilityMapResult, $manifestCode, $syncSinceDate, $nextAfter);
        }

        return $requestData;
    }

    /** Rows read per query when the whole window is written out. */
    public const int WRITE_CHUNK = 500;

    /**
     * The whole window, as getRequests() returns it without a page, written to $out
     * as the JSON value of "requests" instead of returned.
     *
     * getRequests() holds every row in memory, and a form_vl row carries about 250
     * columns. A lab whose window held 31,000 rows (a results resend touches every
     * row it sends) ran this STS out of its 512 MB, and the lab got a 500 on every
     * pull until the window moved past them. Here the rows are read WRITE_CHUNK at
     * a time, in primary key order, and encoded as they come, so memory stays flat.
     *
     * The bytes are the ones JsonUtility::encodeUtf8Json() makes of getRequests()'s
     * "requests", so a lab of any version reads them as before. The order of the
     * rows is the primary key's; a lab does not depend on it.
     *
     * @param resource $out
     * @return array{count: int, sampleIds: list<int|string>, facilityIds: list<int|string>,
     *     receiptsEnabled?: bool, pendingRemaining?: int}
     */
    public function writeRequests(
        $out,
        $testType,
        $labId,
        $facilityMapResult = [],
        $manifestCode = null,
        $syncSinceDate = null,
        bool $withPending = false,
        bool $pendingOnly = false,
        int $chunkSize = self::WRITE_CHUNK,
        bool $skipOwnResults = false
    ): array {
        $this->setTestType($testType);
        $this->skipOwnResults = $skipOwnResults;
        [$pendingIds, $pendingRemaining, $receiptsEnabled] = $this->pendingFor(
            $withPending,
            $labId,
            $facilityMapResult,
            $manifestCode
        );
        [$sQuery, $params] = $this->windowQuery(
            $labId,
            $facilityMapResult,
            $manifestCode,
            $syncSinceDate,
            $pendingIds,
            withWindow: !($pendingOnly && $receiptsEnabled)
        );
        $pk = $this->primaryKeyName;
        // TB, COVID-19, hepatitis and Custom Tests send an object keyed by id.
        $keyed = in_array($testType, ['tb', 'covid19', 'hepatitis', 'generic-tests'], true);

        $count = 0;
        $sampleIds = [];
        $facilityIds = [];
        $after = null;
        do {
            $chunkQuery = $sQuery . ($after === null ? '' : " AND `$pk` > ?") . " ORDER BY `$pk` LIMIT $chunkSize";
            $rows = $this->db->rawQuery($chunkQuery, $after === null ? $params : [...$params, $after]) ?: [];
            if ($rows === []) {
                break;
            }
            $after = $rows[count($rows) - 1][$pk];
            $shaped = $this->shapeRequests($this->withoutInFlightMarker($rows), count($rows));
            array_push($sampleIds, ...$shaped['sampleIds']);
            array_push($facilityIds, ...$shaped['facilityIds']);
            foreach ($shaped['requests'] as $id => $request) {
                $json = JsonUtility::encodeUtf8Json($request);
                if ($json === null) {
                    throw new RuntimeException("Request $id could not be encoded as JSON");
                }
                $opening = $count === 0 ? ($keyed ? '{' : '[') : ',';
                // json_encode, not encodeUtf8Json: that keeps a numeric string as a number.
                $key = $keyed ? json_encode((string) $id) . ':' : '';
                self::write($out, $opening . $key . $json);
                $count++;
            }
        } while (count($rows) === $chunkSize);
        // An empty array encodes as [] whichever shape it would have had.
        self::write($out, $count === 0 ? '[]' : ($keyed ? '}' : ']'));

        // After the rows are read, as getRequests() does: they are sent as they were.
        $this->fillCustomTestsDefaults();

        $written = [
            'count' => $count,
            'sampleIds' => array_values(array_unique($sampleIds)),
            'facilityIds' => array_values(array_unique($facilityIds)),
        ];
        if ($withPending) {
            $written['receiptsEnabled'] = $receiptsEnabled;
            $written['pendingRemaining'] = $pendingRemaining;
        }
        return $written;
    }

    /**
     * Writes all of $data or throws. A short write, from a full disk say, must stop
     * the pull before its rows are marked as sent: the lab would get cut-off JSON
     * while the STS recorded them as delivered.
     *
     * @param resource $out
     */
    public static function write($out, string $data): void
    {
        $length = strlen($data);
        $written = 0;
        while ($written < $length) {
            $bytes = fwrite($out, $written === 0 ? $data : substr($data, $written));
            if ($bytes === false || $bytes === 0) {
                throw new RuntimeException('Could not write the requests answer to its file');
            }
            $written += $bytes;
        }
    }

    /**
     * The requests still waiting for a lab that asked for receipts.
     *
     * @return array{0: list<int|string>, 1: int, 2: bool} ids, how many more wait, and
     *     whether they could be read (receipts work only then)
     */
    private function pendingFor(bool $withPending, $labId, $facilityMapResult, $manifestCode): array
    {
        if (!$withPending) {
            return [[], 0, false];
        }
        if (!empty($manifestCode)) {
            // A manifest pull the lab confirms: its rows, receipted, and none of the
            // lab's other waiting requests, which come with its own pulls.
            return [[], 0, $this->db->tableExists('request_sync_failures')];
        }
        try {
            [$pendingIds, $pendingRemaining] = $this->pendingRequestIds((int) $labId, $facilityMapResult);
            return [$pendingIds, $pendingRemaining, true];
        } catch (Throwable $e) {
            LoggerUtility::logError('Could not read pending requests for lab receipts: ' . $e->getMessage(), [
                'exception_class' => $e::class,
                'lab' => $labId,
                'test_type' => $this->testType,
            ]);
            $this->db->reset();
            return [[], 0, false];
        }
    }

    private function fillCustomTestsDefaults(): void
    {
        if ($this->testType === 'generic-tests') {
            $this->commonService->updateNullColumnsWithDefaults($this->tableName, [
                'is_result_mail_sent' => 'no',
                'is_request_mail_sent' => 'no',
                'is_result_sms_sent' => 'no'
            ]);
        }
    }

    /**
     * Rows as the lab receives them, with the ids to mark as sent.
     *
     * @return array{sampleIds: list<int|string>, facilityIds: list<int|string>, requests: array<int|string, mixed>}
     */
    private function shapeRequests(array $rows, int $count): array
    {
        return match ($this->testType) {
            'covid19' => $this->returnCovid19Requests($rows, $count),
            'hepatitis' => $this->returnHepatitisRequests($rows, $count),
            'tb' => $this->returnTbRequests($rows, $count),
            'generic-tests' => $this->returnCustomTestsRequests($rows, $count),
            default => $this->returnRequests($rows, $count),
        };
    }

    /**
     * What a paged pull adds to the response body, next to "requests": that it is
     * a page, and the marker of the next one while more remain. Nothing for a lab
     * that did not ask, so an older lab sees no change; it reads "requests" only.
     *
     * remaining gives the lab's progress bar a total. A lab that pages but does
     * not read it sees no change.
     *
     * @return array{paged?: true, nextAfter?: ?string, remaining?: int}
     */
    public static function pagingFields(array $requestsData): array
    {
        if (empty($requestsData['paged'])) {
            return [];
        }
        return [
            'paged' => true,
            'nextAfter' => $requestsData['nextAfter'] ?? null,
            'remaining' => (int) ($requestsData['remaining'] ?? 0),
        ];
    }

    /** Rows with no modification time sort first, as the oldest. */
    private const NO_MODIFIED = '1970-01-01 00:00:00';

    private const MODIFIED_SORT = "COALESCE(last_modified_datetime, '" . self::NO_MODIFIED . "')";

    /**
     * The rows after a page marker, in page order.
     *
     * @return array{0: string, 1: list<string|int>}
     */
    private function afterMarker(string $after): array
    {
        [$afterModified, $afterId] = explode('|', $after);
        $modified = self::MODIFIED_SORT;
        return [
            "($modified > ? OR ($modified = ? AND `$this->primaryKeyName` > ?))",
            [$afterModified, $afterModified, (int) $afterId],
        ];
    }

    /** How many rows of the window follow the page marker. */
    private function countAfter($labId, $facilityMapResult, $manifestCode, $syncSinceDate, string $after): int
    {
        [$condition, $params] = $this->buildCondition($labId, $facilityMapResult, $manifestCode, $syncSinceDate);
        [$afterSql, $afterParams] = $this->afterMarker($after);
        $row = $this->db->rawQueryOne(
            "SELECT COUNT(*) AS n FROM $this->tableName WHERE ($condition) AND $afterSql",
            [...$params, ...$afterParams]
        );
        return (int) ($row['n'] ?? 0);
    }

    /** Where a page ended: the last row's modification time and id. */
    public static function pageMarker(?string $modified, int|string $id): string
    {
        return ($modified ?: self::NO_MODIFIED) . '|' . (int) $id;
    }

    /**
     * A page request from a lab, or null for none or one that cannot be read.
     *
     * @return array{size: int, after: ?string}|null
     */
    public static function readPage(mixed $page): ?array
    {
        if (!is_array($page)) {
            return null;
        }
        $size = filter_var(
            $page['size'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 1000]]
        );
        if ($size === false) {
            return null;
        }
        $after = $page['after'] ?? null;
        $marker = '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\|\d+$/';
        if ($after !== null && $after !== '' && !preg_match($marker, (string) $after)) {
            return null;
        }
        return ['size' => $size, 'after' => ($after === '' ? null : $after)];
    }

    /**
     * The oldest requests still waiting for this lab, up to PENDING_LIMIT, and how
     * many more there are.
     *
     * @return array{0: list<int|string>, 1: int}
     */
    private function pendingRequestIds(int $labId, $facilityMapResult): array
    {
        $scope = $this->labScope($labId, $facilityMapResult);
        $now = DateUtility::getCurrentDateTime();
        $where = "$scope
            AND t.data_sync IN (0, " . self::IN_FLIGHT . ")
            AND t.last_modified_datetime >= SUBDATE(?, INTERVAL " . self::PENDING_MAX_AGE_DAYS . " DAY)
            AND NOT EXISTS (
                SELECT 1 FROM request_sync_failures f
                 WHERE f.lab_id = ? AND f.test_type = ? AND f.unique_id = t.unique_id
                   AND (f.first_failed_datetime < SUBDATE(?, INTERVAL " . self::FAILURE_GIVE_UP_DAYS . " DAY)
                        OR f.last_failed_datetime >
                            SUBDATE(?, INTERVAL " . self::FAILURE_RETRY_AFTER_MINUTES . " MINUTE))
            )";
        $params = [$now, $labId, $this->testType, $now, $now];

        $rows = $this->db->rawQuery(
            "SELECT t.`$this->primaryKeyName` AS id FROM `$this->tableName` t WHERE $where
                ORDER BY t.last_modified_datetime, t.`$this->primaryKeyName` LIMIT " . self::PENDING_LIMIT,
            $params
        );
        $ids = array_column($rows, 'id');
        if (count($ids) < self::PENDING_LIMIT) {
            return [$ids, 0];
        }
        $count = $this->db->rawQueryOne("SELECT COUNT(*) AS n FROM `$this->tableName` t WHERE $where", $params);
        $total = (int) ($count['n'] ?? 0);
        return [$ids, max(0, $total - count($ids))];
    }

    /** The rows a lab pulls: its own, and those of the facilities mapped to it. */
    private function labScope(int $labId, $facilityMapResult, string $alias = 't.'): string
    {
        $facilityIds = [];
        $facilityMap = is_array($facilityMapResult) ? implode(',', $facilityMapResult) : (string) $facilityMapResult;
        foreach (explode(',', $facilityMap) as $id) {
            if (ctype_digit(trim($id))) {
                $facilityIds[] = (int) trim($id);
            }
        }
        return $facilityIds === []
            ? "{$alias}lab_id = $labId"
            : "({$alias}lab_id = $labId OR {$alias}facility_id IN (" . implode(',', $facilityIds) . '))';
    }

    private function setTestType(string $testType): void
    {
        $this->testType = $testType;
        $this->tableName = TestsService::getTestTableName($testType);
        $this->primaryKeyName = TestsService::getPrimaryColumn($testType);
        $serviceClass = TestsService::getTestServiceClass($testType);
        $this->testTypeService = ContainerRegistry::get($serviceClass);
    }

    private function runQuery(
        $labId,
        $facilityMapResult,
        $manifestCode,
        $syncSinceDate = null,
        array $pendingIds = [],
        bool $withWindow = true,
        ?array $page = null
    ): array {
        [$sQuery, $params] = $this->windowQuery(
            $labId,
            $facilityMapResult,
            $manifestCode,
            $syncSinceDate,
            $pendingIds,
            $withWindow
        );
        if ($page !== null) {
            if ($page['after'] !== null) {
                [$afterSql, $afterParams] = $this->afterMarker($page['after']);
                $sQuery .= " AND $afterSql";
                array_push($params, ...$afterParams);
            }
            // One more than a page: whether another page follows.
            $sQuery .= ' ORDER BY ' . self::MODIFIED_SORT . ", `$this->primaryKeyName` LIMIT " . ($page['size'] + 1);
        }

        if ($page !== null) {
            // A page needs no count of the whole window.
            $rResult = $this->db->rawQuery($sQuery, $params) ?: [];
            $resultCount = count($rResult);
        } else {
            [$rResult, $resultCount] = $this->db->getDataAndCount($sQuery, $params, returnGenerator: false);
        }

        return [$this->withoutInFlightMarker($rResult), $resultCount];
    }

    /**
     * The in-flight marker is this STS's bookkeeping, not part of the request.
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function withoutInFlightMarker(array $rows): array
    {
        foreach ($rows as &$row) {
            if (isset($row['data_sync']) && (int) $row['data_sync'] === self::IN_FLIGHT) {
                $row['data_sync'] = 0;
            }
        }
        unset($row);
        return $rows;
    }

    /**
     * The SELECT for a lab's window and its waiting requests, before any paging.
     *
     * @return array{0: string, 1: list<mixed>}
     */
    private function windowQuery(
        $labId,
        $facilityMapResult,
        $manifestCode,
        $syncSinceDate,
        array $pendingIds,
        bool $withWindow
    ): array {
        // Start with selecting all columns
        $columnSelection = "*";

        if ($this->testType === 'vl') {
            // Alias and constant column logic specific to VL
            $aliasColumns = [
                'sample_type' => 'specimen_type',
                //'patient_art_no' => 'patient_id'
            ];

            $constantColumns = [
                'sample_code_title' => "'auto'"
            ];

            // Add alias columns
            foreach ($aliasColumns as $oldName => $newName) {
                $columnSelection .= ", $newName AS $oldName";
            }

            // Add constant columns
            foreach ($constantColumns as $columnName => $constantValue) {
                $columnSelection .= ", $constantValue AS $columnName";
            }
        }

        [$condition, $params] = $this->buildCondition($labId, $facilityMapResult, $manifestCode, $syncSinceDate);
        if (!$withWindow) {
            // Nothing matches until the pending rows are ORed in below.
            [$condition, $params] = ['1 = 0', []];
        }
        if ($pendingIds !== []) {
            // Same lab scope; the pending rows ride along with the window.
            $placeholders = implode(', ', array_fill(0, count($pendingIds), '?'));
            $condition = "($condition) OR ({$this->labScope((int) $labId, $facilityMapResult, '')}"
                . " AND `$this->primaryKeyName` IN ($placeholders))";
            $params = [...$params, ...$pendingIds];
        }

        return ["SELECT $columnSelection FROM $this->tableName WHERE ($condition)", $params];
    }
    /**
     * Build the WHERE clause the sync selects on, with its bound values.
     *
     * @return array{0: string, 1: array<int, string>} The clause and its parameters.
     */
    private function buildCondition($labId, $facilityMapResult = [], $manifestCode = null, $syncSinceDate = null): array
    {
        $params = [];

        $condition = empty($facilityMapResult)
            ? "lab_id = $labId"
            : "(lab_id = $labId OR facility_id IN ($facilityMapResult))";

        if ($manifestCode) {
            if ($this->testType === 'tb' || $this->testType === 'generic-tests') {
                $condition .= " AND (sample_package_code like ? OR referral_manifest_code like ?)";
                $params[] = $manifestCode;
                $params[] = $manifestCode;
            } else {
                $condition .= " AND sample_package_code like ?";
                $params[] = $manifestCode;
            }
        } elseif ($syncSinceDate) {
            // Compared as a datetime rather than through DATE(), which wraps the
            // column in a function and puts every index on it out of reach. On a
            // 1.4M row form_vl that is the difference between a full scan and a
            // range read: measured 1,410,608 rows examined in 0.96s against 49,488
            // in 0.05s, for the same answer. A caller passing a bare date still
            // means midnight that day, which is what DATE(col) >= 'date' meant.
            $condition .= " AND last_modified_datetime >= ?";
            $params[] = strlen((string) $syncSinceDate) <= 10
                ? $syncSinceDate . ' 00:00:00'
                : $syncSinceDate;
        } else {
            $condition .= " AND last_modified_datetime >= SUBDATE(?, INTERVAL $this->dataSyncInterval DAY)";
            $params[] = DateUtility::getCurrentDateTime();
        }

        if ($this->skipOwnResults && !$manifestCode) {
            // A row whose latest change is this lab's own result upload holds what
            // the lab sent: the lab has it. Left out of the window, so a lab that
            // resends its results does not pull them all back. One resend put 26,000
            // rows in a lab's window. A later change on the STS moves
            // last_modified_datetime past the recorded time, and the row is sent
            // again. Each <=> is false on NULL, so a row with nothing recorded stays.
            // Only on the lab's cron pull: a lab restored from an older backup
            // pulls by hand to get its rows back.
            $key = ResultsService::RESULTS_FROM_LAB;
            $condition .= " AND NOT (data_sync <=> 1
                AND form_attributes->>'$.$key.labId' <=> ?
                AND CAST(form_attributes->>'$.$key.at' AS DATETIME) <=> last_modified_datetime)";
            $params[] = (string) $labId;
        }

        return [$condition, $params];
    }
    private function returnRequests(array $rResult, int $resultCount): array
    {
        $syncMeta = $resultCount > 0 ? $this->collectSampleAndFacilityIds($rResult) : ['sampleIds' => [], 'facilityIds' => []];

        return [
            'sampleIds' => $syncMeta['sampleIds'],
            'facilityIds' => $syncMeta['facilityIds'],
            'requests' => $rResult
        ];
    }

    private function returnTbRequests(array $rResult, int $resultCount): array
    {
        $requests = $sampleIds = $facilityIds = [];

        if ($resultCount > 0) {
            $syncMeta = $this->collectSampleAndFacilityIds($rResult);
            $sampleIds = $syncMeta['sampleIds'];
            $facilityIds = $syncMeta['facilityIds'];
            /** @var TbService $tbService */
            $tbService = $this->testTypeService;
            foreach ($rResult as $r) {
                $requests[$r[$this->primaryKeyName]] = $r;
                $requests[$r[$this->primaryKeyName]]['data_from_tests'] = $tbService->getTbTestsByFormId($r[$this->primaryKeyName]);
            }
        }
        return [
            'sampleIds' => $sampleIds,
            'facilityIds' => $facilityIds,
            'requests' => $requests
        ];
    }
    private function returnCovid19Requests(array $rResult, int $resultCount): array
    {
        $requests = $sampleIds = $facilityIds = [];

        if ($resultCount > 0) {
            $syncMeta = $this->collectSampleAndFacilityIds($rResult);
            $sampleIds = $syncMeta['sampleIds'];
            $facilityIds = $syncMeta['facilityIds'];

            /** @var Covid19Service $covid19Service */
            $covid19Service = $this->testTypeService;
            foreach ($rResult as $r) {
                $requests[$r[$this->primaryKeyName]] = $r;
                $requests[$r[$this->primaryKeyName]]['data_from_comorbidities'] = $covid19Service->getCovid19ComorbiditiesByFormId($r[$this->primaryKeyName], false, true);
                $requests[$r[$this->primaryKeyName]]['data_from_symptoms'] = $covid19Service->getCovid19SymptomsByFormId($r[$this->primaryKeyName], false, true);
                $requests[$r[$this->primaryKeyName]]['data_from_tests'] = $covid19Service->getCovid19TestsByFormId($r[$this->primaryKeyName]);
            }
        }

        return [
            'sampleIds' => $sampleIds,
            'facilityIds' => $facilityIds,
            'requests' => $requests
        ];
    }

    private function returnHepatitisRequests(array $rResult, int $resultCount): array
    {
        $requests = $sampleIds = $facilityIds = [];

        if ($resultCount > 0) {
            $syncMeta = $this->collectSampleAndFacilityIds($rResult);
            $sampleIds = $syncMeta['sampleIds'];
            $facilityIds = $syncMeta['facilityIds'];

            /** @var HepatitisService $hepatitisService */
            $hepatitisService = $this->testTypeService;
            foreach ($rResult as $r) {
                $requests[$r[$this->primaryKeyName]] = $r;
                $requests[$r[$this->primaryKeyName]]['data_from_comorbidities'] = $hepatitisService->getComorbidityByHepatitisId($r[$this->primaryKeyName]);
                $requests[$r[$this->primaryKeyName]]['data_from_risks'] = $hepatitisService->getRiskFactorsByHepatitisId($r[$this->primaryKeyName]);
            }
        }

        return [
            'sampleIds' => $sampleIds,
            'facilityIds' => $facilityIds,
            'requests' => $requests
        ];
    }
    private function returnCustomTestsRequests(array $rResult, int $resultCount): array
    {
        $requests = $sampleIds = $facilityIds = [];

        if ($resultCount > 0) {
            $syncMeta = $this->collectSampleAndFacilityIds($rResult);
            $sampleIds = $syncMeta['sampleIds'];
            $facilityIds = $syncMeta['facilityIds'];

            /** @var GenericTestsService $customTestsService */
            $customTestsService = $this->testTypeService;

            foreach ($rResult as $r) {
                $requests[$r[$this->primaryKeyName]] = $r;
                $requests[$r[$this->primaryKeyName]]['data_from_tests'] = $customTestsService->getTestsByGenericSampleIds($r[$this->primaryKeyName]);
            }
        }

        return [
            'sampleIds' => $sampleIds,
            'facilityIds' => $facilityIds,
            'requests' => $requests
        ];
    }

    private function isUnsynced(array $row): bool
    {
        $status = $row['data_sync'] ?? null;

        if (is_bool($status)) {
            return $status === false;
        }

        if (is_numeric($status)) {
            return (int) $status === 0;
        }

        if ($status === null || $status === '') {
            return true;
        }

        $normalized = strtolower((string) $status);
        return !in_array($normalized, ['1', 'true', 'yes'], true);
    }

    private function collectSampleAndFacilityIds(array $rows): array
    {
        $sampleIds = [];
        $facilityIds = [];

        foreach ($rows as $row) {
            if (array_key_exists('facility_id', $row) && $row['facility_id'] !== null && $row['facility_id'] !== '') {
                $facilityIds[] = $row['facility_id'];
            }

            if (
                $this->isUnsynced($row)
                && array_key_exists($this->primaryKeyName, $row)
                && $row[$this->primaryKeyName] !== null
                && $row[$this->primaryKeyName] !== ''
            ) {
                $sampleIds[] = $row[$this->primaryKeyName];
            }
        }

        return [
            'sampleIds' => array_values(array_unique($sampleIds)),
            'facilityIds' => array_values(array_unique($facilityIds)),
        ];
    }
}
