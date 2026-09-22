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
use App\Utilities\LoggerUtility;
use App\Abstracts\AbstractTestService;
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
     */
    public function getRequests(
        $testType,
        $labId,
        $facilityMapResult = [],
        $manifestCode = null,
        $syncSinceDate = null,
        bool $withPending = false,
        bool $pendingOnly = false
    ) {
        $this->setTestType($testType);

        $pendingIds = [];
        $pendingRemaining = 0;
        $receiptsEnabled = false;
        if ($withPending && empty($manifestCode)) {
            try {
                [$pendingIds, $pendingRemaining] = $this->pendingRequestIds((int) $labId, $facilityMapResult);
                $receiptsEnabled = true;
            } catch (Throwable $e) {
                LoggerUtility::logError('Could not read pending requests for lab receipts: ' . $e->getMessage(), [
                    'exception_class' => $e::class,
                    'lab' => $labId,
                    'test_type' => $testType,
                ]);
                $this->db->reset();
            }
        }

        [$rResult, $resultCount] = $this->runQuery(
            $labId,
            $facilityMapResult,
            $manifestCode,
            $syncSinceDate,
            $pendingIds,
            // Only when the pending rows were read: else the plain window.
            withWindow: !($pendingOnly && $receiptsEnabled)
        );
        // Handle specific test types with additional logic
        if ($testType === 'covid19') {
            $requestData = $this->returnCovid19Requests($rResult, $resultCount);
        } elseif ($testType === 'hepatitis') {
            $requestData = $this->returnHepatitisRequests($rResult, $resultCount);
        } elseif ($testType === 'tb') {
            $requestData = $this->returnTbRequests($rResult, $resultCount);
        } elseif ($testType === 'generic-tests') {
            $this->commonService->updateNullColumnsWithDefaults($this->tableName, [
                'is_result_mail_sent' => 'no',
                'is_request_mail_sent' => 'no',
                'is_result_sms_sent' => 'no'
            ]);
            $requestData = $this->returnCustomTestsRequests($rResult, $resultCount);
        } else {
            // Default for other test types
            $requestData = $this->returnRequests($rResult, $resultCount);
        }

        if ($withPending) {
            $requestData['receiptsEnabled'] = $receiptsEnabled;
            $requestData['pendingRemaining'] = $pendingRemaining;
        }

        return $requestData;
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
        bool $withWindow = true
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

        $sQuery = "SELECT $columnSelection FROM $this->tableName WHERE $condition";

        [$rResult, $resultCount] = $this->db->getDataAndCount($sQuery, $params, returnGenerator: false);

        // The in-flight marker is this STS's bookkeeping, not part of the request.
        foreach ($rResult as &$row) {
            if (isset($row['data_sync']) && (int) $row['data_sync'] === self::IN_FLIGHT) {
                $row['data_sync'] = 0;
            }
        }
        unset($row);

        return [$rResult, $resultCount];
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
