<?php

namespace App\Abstracts;

use const COUNTRY\PNG;
use const SAMPLE_STATUS\CANCELLED;
use const SAMPLE_STATUS\EXPIRED;
use const SAMPLE_STATUS\ACCEPTED;
use const SAMPLE_STATUS\PENDING_APPROVAL;
use const SAMPLE_STATUS\REJECTED;
use const SAMPLE_STATUS\TEST_FAILED;
use Throwable;
use DateTimeImmutable;
use App\Services\TestsService;
use App\Services\FacilitiesService;

use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Utilities\TurnaroundTimeUtility;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Utilities\FileCacheUtility;
use App\Services\TestRequestsService;
use App\Registries\ContainerRegistry;

abstract class AbstractTestService
{
    public TestRequestsService $testRequestsService;
    public int $maxTries = 5; // Max tries for generating Sample ID
    public string $table;
    public string $primaryKey;
    public string $testType;
    public string $shortCode;

    public function __construct(public DatabaseService $db, public CommonService $commonService)
    {
        $this->table ??= TestsService::getTestTableName($this->testType);
        $this->primaryKey ??= TestsService::getPrimaryColumn($this->testType);
        $this->shortCode ??= TestsService::getTestShortCode($this->testType);
        $this->testRequestsService = new TestRequestsService($this->db, $this->commonService);
    }
    abstract public function getSampleCode($params);
    abstract public function insertSample($params, $returnSampleData = false);

    private function getMaxId($year, $testType, string $sampleCodeType, $insertOperation): int|float
    {
        if (!$insertOperation) {
            // The preview the request form shows. It predicts the next number rather than
            // reserving one, so two people with the form open see the same value and
            // whoever saves first takes it. Reserving would make the preview exact at the
            // cost of spending a number every time somebody opens a form and thinks
            // better of it, and a gap in the series is worse than a preview that moved.
            //
            // Read-only, deliberately. This used to seed the counter when no row existed,
            // so merely opening the form could write to sequence_counter and, before the
            // subquery was made sargable, scan the whole form table to do it. Seeding
            // belongs to the first real insert of the year, which does it on the claim
            // path below. Until then the preview shows the start of the series -- the
            // same answer seeding would have produced, since a year with no counter row
            // has no samples to have advanced it.
            $sql = "SELECT max_sequence_number FROM sequence_counter
                WHERE year = ? AND
                test_type = ? AND
                code_type = ?";

            $yearData = $this->db->rawQueryOne($sql, [
                $year,
                $testType,
                $sampleCodeType
            ]);

            return ($yearData['max_sequence_number'] ?? 0) + 1;
        }

        // Claim exactly one number, atomically.
        //
        // LAST_INSERT_ID(expr) makes the incremented value readable from the connection
        // afterwards, so a single statement does what a SELECT ... FOR UPDATE, a separate
        // UPDATE and the transaction around them used to. The row lock is taken and
        // released inside that one statement rather than held across three round trips,
        // and the number is still claimed one at a time, so the sequence stays
        // contiguous. Allocating a whole batch's range in one go would be far faster
        // still, but it burns the unused numbers when a batch part-fails, and gaps in a
        // sample-code series are not acceptable to the people reading them.
        $claimSql = "UPDATE sequence_counter
                        SET max_sequence_number = LAST_INSERT_ID(max_sequence_number + 1)
                      WHERE year = ? AND test_type = ? AND code_type = ?";

        $this->db->rawQuery($claimSql, [$year, $testType, $sampleCodeType]);
        $claimed = (int) $this->db->getInsertId();

        if ($claimed > 0) {
            return $claimed;
        }

        // Nothing was claimed, so the counter row does not exist yet -- the first sample
        // of a year. Seed it and claim again.
        $this->resetSequenceCounter($this->table, $year, $testType, $sampleCodeType);
        $this->db->rawQuery($claimSql, [$year, $testType, $sampleCodeType]);
        $claimed = (int) $this->db->getInsertId();

        if ($claimed <= 0) {
            throw new SystemException("Unable to claim a sequence number for {$testType}/{$sampleCodeType} in {$year}");
        }

        return $claimed;
    }

    /**
     * Resolve a testing lab's sample-code postfix component.
     *
     * Returns the lab's facility_code (or a stable md5-derived fallback) ONLY when
     * $labId points to a real testing lab (facility_type = 2); returns '' otherwise.
     * Cached per lab. Used to build the "-<labcode>" postfix appended to sample codes.
     */
    protected function labFacilityCode(int $labId): string
    {
        if ($labId <= 0) {
            return '';
        }
        /** @var FileCacheUtility $fileCache */
        $fileCache = ContainerRegistry::get(FileCacheUtility::class);
        return $fileCache->get("lab_facility_code_$labId", function () use ($labId) {
            $row = $this->db->rawQueryOne(
                "SELECT facility_name, facility_code, facility_type FROM facility_details WHERE facility_id = ?",
                [$labId]
            );
            if (empty($row) || (int) ($row['facility_type'] ?? 0) !== 2) {
                return ''; // not a testing lab -> no postfix
            }
            if (!empty($row['facility_code'])) {
                return $row['facility_code'];
            }

            // No code on record yet: derive a stable, unique one from the lab name and
            // persist it so every later sample code (and the rest of the app) reuses it.
            // This runs from stsLabPostfix() before the sample-code transaction begins,
            // so writing here does not nest inside the minting transaction.
            /** @var FacilitiesService $facilitiesService */
            $facilitiesService = ContainerRegistry::get(FacilitiesService::class);
            $code = $facilitiesService->generateFacilityCode((string) ($row['facility_name'] ?? ''), $labId);
            if ($code !== '') {
                $this->db->where('facility_id', $labId);
                $this->db->update('facility_details', ['facility_code' => $code]);
                return $code;
            }
            return strtoupper(substr(md5((string) $labId), 0, 4));
        }, ['facility']);
    }

    /**
     * STS-only lab-aware sample-code postfix.
     *
     * On STS, when the queued request was made by a testing lab and carries a valid
     * lab id, append "-<labFacilityCode>" so each lab's sample codes are distinguishable.
     * Returns '' on LIS / standalone (so pure-LIS sample-code format is never changed)
     * and whenever the lab cannot be resolved. Session-independent by design: the
     * code-minting paths (API, CLI worker, activation) have no $_SESSION, so the gate
     * trusts the queue row's access_type + lab_id (set server-side at enqueue time).
     */
    protected function stsLabPostfix(array $params): string
    {
        if (!$this->commonService->isSTSInstance()) {
            return '';
        }
        if (($params['accessType'] ?? '') !== 'testing-lab') {
            return '';
        }
        $code = $this->labFacilityCode((int) ($params['labId'] ?? 0));
        return $code !== '' ? "-$code" : '';
    }

    /**
     * Lab-aware sample-code postfix, gated by the test type's referrability
     * (TestsService::isReferrable()). All test services call this; behaviour is
     * driven entirely by the isReferrable flag, not hard-coded per service.
     *
     * Referrable test types (TB, Custom/Generic) are reported across labs, so the
     * testing lab is encoded on BOTH LIS and STS:
     *   - LIS: the instance's own lab from sc_testing_lab_id
     *   - STS: the queued lab_id + access_type (see stsLabPostfix)
     * Non-referrable types only get the postfix on STS, so a pure-LIS sample-code
     * format is never changed. Returns '' when no lab resolves.
     */
    protected function labPostfix(array $params): string
    {
        if (TestsService::isReferrable($this->testType ?? '') && $this->commonService->isLISInstance()) {
            $code = $this->labFacilityCode((int) ($this->commonService->getSystemConfig('sc_testing_lab_id') ?? 0));
            return $code !== '' ? "-$code" : '';
        }
        return $this->stsLabPostfix($params);
    }

    /**
     * $testTable is the table where the sample code is to be generated - form_vl, form_eid etc.
     *
     * By default this owns the transaction around the claim: it commits as soon as a
     * number is taken, which means the number is spent whether or not the caller then
     * manages to write it onto a sample. A write that fails afterwards leaves a hole in
     * the series, and a hole is what a lab notices.
     *
     * A caller that is going to persist the code should therefore pass
     * `$params['manageTransaction'] = false`, open its own transaction, and commit only
     * once the sample carries the code -- then a failure rolls the claim back with it and
     * the number is returned to the series. When the caller owns the transaction this
     * method does not touch it at all -- it neither begins, commits nor rolls back, and
     * it hands a duplicate collision back instead of retrying, because retrying inside a
     * transaction the caller is about to roll back would claim another number for
     * nothing.
     *
     * DatabaseService used to track transactions with a flag rather than a depth, so an
     * inner commit ended the caller's transaction and this flag was the only thing
     * keeping a claim from committing a half-finished batch. It counts nesting depth now
     * and gives each nested scope its own savepoint, so that hazard is gone. Passing the
     * flag is still what ties the claim to the write, and what decides who recovers.
     *
     * The cost of that is a longer hold on the counter row -- it now spans the caller's
     * write rather than just the claim -- so concurrent generation serialises more. That
     * is the price of a contiguous series, and it is the one worth paying.
     */
    public function generateSampleCode($testTable, $params, $tryCount = 0)
    {
        $sampleCodeGenerator = [];
        $insertOperation = $params['insertOperation'] ?? true;
        $manageTransaction = $params['manageTransaction'] ?? true;
        $ownsTransaction = $insertOperation && $manageTransaction;
        // Distinct from !$ownsTransaction, which is also true for the display path -- and
        // the display path holds no transaction at all, so it keeps the retry behaviour.
        $callerOwnsTransaction = $insertOperation && !$manageTransaction;
        $this->testType = $params['testType'] ?? $this->testType ?? 'generic-tests';
        $formId = (int) $this->commonService->getGlobalConfig('vl_form');

        for ($attempt = 0; $attempt < $this->maxTries; $attempt++) {
            if ($ownsTransaction) {
                $this->db->beginTransaction();
            }

            try {
                // Prepare sample code parameters
                $sampleCollectionDate = $params['sampleCollectionDate'] ?? null;
                $provinceCode = $params['provinceCode'] ?? '';
                $sampleCodeFormat = $params['sampleCodeFormat'] ?? 'MMYY';
                $prefix = $params['prefix'] ?? $this->shortCode ?? 'T';

                // postfix can be used for adding additional identifiers like facility code
                // currently used in TB to add facility code at the end of sample code
                $postfix = $params['postfix'] ?? '';

                if (empty($sampleCollectionDate) || DateUtility::isDateValid($sampleCollectionDate) === false) {
                    $sampleCollectionDate = 'now';
                }

                $dateObj = new DateTimeImmutable($sampleCollectionDate);
                $year = $dateObj->format('y');
                $month = $dateObj->format('m');
                $day = $dateObj->format('d');
                $autoFormatedString = "$year$month$day";
                $currentYear = $dateObj->format('Y');

                // A sample can carry up to two codes drawn from two SEPARATE
                // counters:
                //   - "sts" series -> remote_sample_code: ALWAYS R-prefixed, NEVER
                //     a postfix. The network/origin id.
                //   - "lis" series -> sample_code: NEVER R-prefixed, MAY carry a lab
                //     postfix. The testing-lab's working id.
                // The series is normally derived from who is acting (a testing-lab
                // actor mints the lis series; every other actor on STS mints the sts
                // series), but a caller can force one via $params['codeSeries'] --
                // case 3 (testing-lab adds a sample directly on STS) mints BOTH,
                // calling once per series.
                $series = $params['codeSeries'] ?? null;
                if ($series === null) {
                    $actsAsLab = ($params['accessType'] ?? '') === 'testing-lab';
                    $series = ($this->commonService->isSTSInstance() && !$actsAsLab) ? 'sts' : 'lis';
                }

                $remotePrefix = '';
                $sampleCodeType = 'sample_code';
                if ($series === 'sts') {
                    $remotePrefix = 'R';
                    $sampleCodeType = 'remote_sample_code';
                    $postfix = ''; // sts codes are always R and never carry a postfix
                }

                // Get the next sequence number using our improved atomic method
                $maxId = $this->getMaxId($currentYear, $this->testType, $sampleCodeType, $insertOperation);

                // padding with zeroes
                $maxId = sprintf("%04d", (int) $maxId);

                $sampleCodeGenerator = [
                    'sampleCodeFormat' => $sampleCodeFormat,
                    'sampleCodeKey' => $maxId,
                    'maxId' => $maxId,
                    'monthYear' => "$month$year",
                    'year' => $year,
                    'auto' => "$year$month$day"
                ];

                // PNG format has an additional R in prefix
                if ($formId == PNG) {
                    $remotePrefix .= "R";
                }

                // Format the sample code based on the specified format
                if ($sampleCodeFormat == 'auto') {
                    $sampleCodeGenerator['sampleCodeFormat'] = $remotePrefix . $provinceCode . $autoFormatedString . $postfix;
                } elseif ($sampleCodeFormat == 'auto2') {
                    $sampleCodeGenerator['sampleCodeFormat'] = $remotePrefix . $year . $provinceCode . $prefix . $postfix;
                } elseif ($sampleCodeFormat == 'MMYY') {
                    $sampleCodeGenerator['sampleCodeFormat'] = $remotePrefix . $prefix . $sampleCodeGenerator['monthYear'] . $postfix;
                } elseif ($sampleCodeFormat == 'YY') {
                    $sampleCodeGenerator['sampleCodeFormat'] = $remotePrefix . $prefix . $sampleCodeGenerator['year'] . $postfix;
                } else {
                    $sampleCodeGenerator['sampleCodeFormat'] = $remotePrefix . $prefix . $postfix;
                }

                // When a lab postfix is present, separate it from the running number with a
                // hyphen ("RVL0626-NMC-19233") so the lab code never blurs into the sequence.
                // Codes without a postfix are unchanged.
                $sequenceSeparator = ($postfix !== '') ? '-' : '';
                $sampleCodeGenerator['sampleCode'] = $sampleCodeGenerator['sampleCodeFormat'] . $sequenceSeparator . $sampleCodeGenerator['maxId'];
                $sampleCodeGenerator['sampleCodeInText'] = $sampleCodeGenerator['sampleCodeFormat'] . $sequenceSeparator . $sampleCodeGenerator['maxId'];

                // Check for duplication only if we are inserting
                if ($insertOperation) {
                    $checkDuplicateQuery = "SELECT 1 FROM $testTable WHERE $sampleCodeType = ? LIMIT 1";
                    $checkDuplicateResult = $this->db->rawQueryOne($checkDuplicateQuery, [$sampleCodeGenerator['sampleCode']]);

                    if (!empty($checkDuplicateResult)) {
                        // Log the duplicate
                        LoggerUtility::logInfo("DUPLICATE ::: Sample ID/Sample Key Code in $testTable ::: " . $sampleCodeGenerator['sampleCode'] . " / " . $maxId);

                        if ($callerOwnsTransaction) {
                            // The claim belongs to the caller's transaction, so it cannot
                            // be undone from here without ending that transaction. Hand
                            // the collision back and let the caller roll back and retry,
                            // which returns this number to the series.
                            throw new SystemException("Duplicate sample code generated for $testTable : " . $sampleCodeGenerator['sampleCode']);
                        }

                        // Rollback the transaction for this attempt
                        $this->db->rollbackTransaction();

                        // We'll try again with the next iteration
                        continue;
                    }

                    // A number has been claimed and is not a duplicate. When this method
                    // owns the transaction the claim is committed here; when the caller
                    // owns it, it stays uncommitted until the sample carries the code.
                    if ($ownsTransaction) {
                        $this->db->commitTransaction();
                    }
                    return json_encode($sampleCodeGenerator);
                } else {
                    // For display only, no need to check for duplicates
                    return json_encode($sampleCodeGenerator);
                }
            } catch (Throwable $exception) {
                // Only unwind what this method started. Rolling back a transaction the
                // caller opened would discard work this method knows nothing about.
                if ($ownsTransaction) {
                    $this->db->rollbackTransaction();
                }

                if ($callerOwnsTransaction) {
                    // The caller owns the transaction and therefore owns the recovery:
                    // retrying in here would claim another number inside a transaction it
                    // is about to roll back. The display path is unaffected -- it holds no
                    // transaction and keeps the retry behaviour below.
                    throw $exception instanceof SystemException
                        ? $exception
                        : new SystemException("Error while generating Sample ID for $testTable : " . $exception->getMessage(), $exception->getCode(), $exception);
                }

                // For specific database deadlock errors, add a delay and retry
                if (in_array($exception->getCode(), [1205, 1213])) {
                    LoggerUtility::logInfo("DB Lock error encountered during Sample ID generation, retrying (attempt {$attempt}): " . $exception->getMessage());
                    // Add a small delay before retrying with exponential backoff
                    usleep(($attempt + 1) * 100000); // 100-500 milliseconds with backoff
                    continue;
                }

                // For other exceptions, throw after all retries
                if ($attempt === $this->maxTries - 1) {
                    throw new SystemException("Error while generating Sample ID for $testTable : " . $exception->getMessage(), $exception->getCode(), $exception);
                }
            }
        }

        // If we've reached here, we've exceeded max tries
        throw new SystemException("Exceeded maximum number of tries ($this->maxTries) for generating Sample ID");
    }

    private function resetSequenceCounter(string $testTable, $year, $testType, $sampleCodeType): void
    {
        LoggerUtility::logInfo("Resetting sequence counter for $testTable, year = $year, testType = $testType, sampleCodeType = $sampleCodeType");

        $codeKey = "{$sampleCodeType}_key";

        $query = "INSERT INTO sequence_counter (test_type, year, code_type, max_sequence_number)
                    SELECT
                '$testType' AS test_type,
                ? AS year,
                '$sampleCodeType' AS code_type,
                /* Bounded by a date range rather than YEAR(sample_collection_date), which
                   no index can answer: seeding a counter meant a full scan of the form
                   table, and it is seeded on the first sample of a year, so every
                   1 January, for every test type at once, on the largest table there is. */
                COALESCE((SELECT MAX($codeKey) FROM $testTable
                    WHERE sample_collection_date >= ?
                      AND sample_collection_date < ?), 0) AS max_sequence_number
                    ON DUPLICATE KEY UPDATE
                    max_sequence_number = GREATEST(VALUES(max_sequence_number), max_sequence_number)";

        $this->db->rawQuery($query, [
            $year,
            sprintf('%04d-01-01 00:00:00', (int) $year),
            sprintf('%04d-01-01 00:00:00', (int) $year + 1),
        ]);
    }
    public function isSampleCancelled($uniqueId): bool
    {
        try {
            $uneditableStatus = [
                CANCELLED,
                EXPIRED,
            ];

            $this->db->where('unique_id', $uniqueId);
            $this->db->where('result_status', $uneditableStatus, 'NOT IN');
            $sampleIdValue = $this->db->getValue($this->table, 'unique_id');

            return !empty($sampleIdValue);
        } catch (Throwable $e) {
            throw new SystemException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }


    public function cancelSample(string $uniqueId, $userId = null): bool
    {
        try {
            $uncancellableStatus = [
                ACCEPTED,
                PENDING_APPROVAL,
                REJECTED,
                TEST_FAILED,
                CANCELLED,
                EXPIRED,
            ];

            $this->db->where('unique_id', $uniqueId);
            $this->db->where('result_status', $uncancellableStatus, 'NOT IN');
            $sampleRow = $this->db->getValue($this->table, 'unique_id');

            if (empty($sampleRow)) {
                return false;
            }

            $this->db->where('unique_id', $uniqueId);
            return $this->db->update($this->table, [
                'data_sync' => 0,
                'result_status' => CANCELLED,
                'last_modified_by' => $userId ?? ($_SESSION['userId'] ?? null),
                'last_modified_datetime' => DateUtility::getCurrentDateTime(),
            ]);
        } catch (Throwable $e) {
            throw new SystemException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Set one or more attributes in the form_attributes JSON column.
     * Uses MySQL JSON_SET for atomic update without read-then-write.
     *
     * Usage:
     *   $service->setAttributes($id, 'key', 'value');
     *   $service->setAttributes($id, ['key1' => 'val1', 'key2' => 'val2']);
     */
    public function setAttributes(int|string $sampleId, string|array $name, mixed $value = null): bool
    {
        $attributes = \is_array($name) ? $name : [$name => $value];

        if (empty($attributes)) {
            return false;
        }

        $setString = JsonUtility::jsonToSetString(
            json_encode($attributes),
            'form_attributes'
        );

        if (empty($setString)) {
            return false;
        }

        $this->db->where($this->primaryKey, $sampleId);
        return $this->db->update($this->table, [
            'form_attributes' => $this->db->func($setString),
        ]);
    }

    /**
     * Monthly Laboratory Turnaround Time series for this test type.
     *
     * Every form_* table carries the same six milestone columns, so the
     * measurement lives in one place (TurnaroundTimeUtility) and this method
     * only supplies the module's table and result column. Callers pass the
     * filters their page is already applying, so the TAT chart describes the
     * same set of samples as the rest of the page.
     *
     * @param string[] $conditions WHERE conditions, ANDed together
     * @param array    $params     Bind params matching the placeholders in $conditions
     * @param string   $joins      Extra JOIN clauses the conditions rely on
     * @param string   $alias      Alias for this module's form table
     * @param string[] $stages     Subset of TurnaroundTimeUtility::STAGES keys
     *
     * @return array<string, string[]> Series key => JS literals, plus 'months'
     */
    public function getTurnaroundTimeSeries(
        array $conditions = [],
        array $params = [],
        string $joins = '',
        string $alias = 'sample',
        array $stages = []
    ): array {
        $query = TurnaroundTimeUtility::buildQuery(
            $this->table,
            $conditions,
            TestsService::getResultColumn($this->testType),
            $joins,
            $alias,
            $stages
        );

        return TurnaroundTimeUtility::toChartSeries($this->db->rawQuery($query, $params), $stages);
    }

    /**
     * Writes the per-sample turnaround time detail export for this test type.
     *
     * Streams rows straight to the file with OpenSpout so the export holds a
     * constant amount of memory regardless of how many samples match. The
     * previous per-module copies built an entire PhpSpreadsheet workbook in
     * memory first, which is why they fell over on real datasets.
     *
     * @param string   $sql             Query returning the detail rows
     * @param array    $params          Bind params for $sql
     * @param array    $columns         [heading, column name, format as date?] per column
     * @param string[] $appliedFilters  "Label : value" strings for the header row
     * @param string   $fileLabel       Goes into the generated file name
     *
     * @return string URL-encoded base name of the generated file
     */
    public function writeTurnaroundTimeExport(
        string $sql,
        array $params,
        array $columns,
        array $appliedFilters,
        string $fileLabel
    ): string {
        $headings = array_map(static fn(array $c): string => _translate($c[0]), $columns);

        $filename = TEMP_PATH . DIRECTORY_SEPARATOR
            . 'InteLIS-' . $fileLabel . '-TAT-Report-' . date('d-M-Y-H-i-s') . '.xlsx';

        $headerStyle = (new Style())->withFontBold(true);

        $writer = new XlsxWriter();
        $writer->openToFile($filename);

        $writer->addRow(Row::fromValuesWithStyle([$fileLabel . ' ' . _translate('Turnaround Time Report')], $headerStyle));
        if ($appliedFilters !== []) {
            $writer->addRow(Row::fromValues([implode('   ', $appliedFilters)]));
        }
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValuesWithStyle($headings, $headerStyle));

        foreach ($this->db->rawQueryGenerator($sql, $params) as $aRow) {
            $values = [];
            foreach ($columns as [$label, $column, $isDate]) {
                $value = $aRow[$column] ?? '';
                $values[] = $isDate ? DateUtility::humanReadableDateFormat($value ?? '') : $value;
            }
            $writer->addRow(Row::fromValues($values));
        }

        $writer->close();

        return urlencode(basename($filename));
    }

    /**
     * The column layout most modules' turnaround time exports use.
     *
     * @return array<int, array{0: string, 1: string, 2: bool}>
     */
    public static function turnaroundTimeDetailColumns(): array
    {
        return [
            ['Sample ID', 'sample_code', false],
            ['Remote Sample ID', 'remote_sample_code', false],
            ['External Sample ID', 'external_sample_code', false],
            ['Sample Collection Date', 'sample_collection_date', true],
            ['Sample Dispatch Date', 'sample_dispatched_datetime', true],
            ['Sample Received Date in Lab', 'sample_received_at_lab_datetime', true],
            ['Sample Test Date', 'sample_tested_datetime', true],
            ['Result Print Date', 'result_printed_datetime', true],
            ['STS Result Print Date', 'result_printed_on_sts_datetime', true],
            ['LIS Result Print Date', 'result_printed_on_lis_datetime', true],
        ];
    }

    /**
     * Filter summary for the top of a turnaround time export.
     *
     * @param array<string, string> $labels POST key => display label
     * @return string[]
     */
    public static function turnaroundTimeFilterSummary(array $post, array $labels): array
    {
        $summary = [];
        foreach ($labels as $key => $label) {
            $value = trim((string) ($post[$key] ?? ''));
            if ($value !== '' && $value !== '-- Select --') {
                $summary[] = "$label : $value";
            }
        }

        return $summary;
    }
}
