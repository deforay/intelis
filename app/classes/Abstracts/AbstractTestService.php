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

use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Services\TestRequestsService;

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
            // For display only, no need to lock or increment
            $sql = "SELECT max_sequence_number FROM sequence_counter
                WHERE year = ? AND
                test_type = ? AND
                code_type = ?";

            $yearData = $this->db->rawQueryOne($sql, [
                $year,
                $testType,
                $sampleCodeType
            ]);

            // If no counter exists, initialize it
            if (empty($yearData)) {
                $this->resetSequenceCounter($this->table, $year, $testType, $sampleCodeType);
                $yearData = $this->db->rawQueryOne($sql, [
                    $year,
                    $testType,
                    $sampleCodeType
                ]);
            }

            return ($yearData['max_sequence_number'] ?? 0) + 1;
        }

        // For insert operations, use a direct approach without creating new transactions
        // First, check if we need to initialize the counter
        $checkSql = "SELECT max_sequence_number FROM sequence_counter
                WHERE year = ? AND test_type = ? AND code_type = ? FOR UPDATE";

        $current = $this->db->rawQueryOne($checkSql, [
            $year,
            $testType,
            $sampleCodeType
        ]);

        if (empty($current)) {
            // Counter doesn't exist, initialize it
            $this->resetSequenceCounter($this->table, $year, $testType, $sampleCodeType);
            $current = $this->db->rawQueryOne($checkSql, [
                $year,
                $testType,
                $sampleCodeType
            ]);
        }

        // Increment the counter
        $nextValue = ($current['max_sequence_number'] ?? 0) + 1;

        // Update the counter
        $updateSql = "UPDATE sequence_counter
                        SET max_sequence_number = ?
                            WHERE year = ? AND test_type = ? AND code_type = ?";

        $this->db->rawQuery($updateSql, [
            $nextValue,
            $year,
            $testType,
            $sampleCodeType
        ]);

        return $nextValue;
    }

    // $testTable is the table where the sample code is to be generated - form_vl, form_eid etc.
    public function generateSampleCode($testTable, $params, $tryCount = 0)
    {
        $sampleCodeGenerator = [];
        $insertOperation = $params['insertOperation'] ?? true;
        $this->testType = $params['testType'] ?? $this->testType ?? 'generic-tests';
        $formId = (int) $this->commonService->getGlobalConfig('vl_form');

        for ($attempt = 0; $attempt < $this->maxTries; $attempt++) {
            // For insert operations, we need a transaction
            if ($insertOperation) {
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

                $remotePrefix = '';
                $sampleCodeType = 'sample_code';
                if ($this->commonService->isSTSInstance()) {
                    $remotePrefix = 'R';
                    $sampleCodeType = 'remote_sample_code';
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

                $sampleCodeGenerator['sampleCode'] = $sampleCodeGenerator['sampleCodeFormat'] . $sampleCodeGenerator['maxId'];
                $sampleCodeGenerator['sampleCodeInText'] = $sampleCodeGenerator['sampleCodeFormat'] . $sampleCodeGenerator['maxId'];

                // Check for duplication only if we are inserting
                if ($insertOperation) {
                    $checkDuplicateQuery = "SELECT 1 FROM $testTable WHERE $sampleCodeType = ? LIMIT 1";
                    $checkDuplicateResult = $this->db->rawQueryOne($checkDuplicateQuery, [$sampleCodeGenerator['sampleCode']]);

                    if (!empty($checkDuplicateResult)) {
                        // Log the duplicate
                        LoggerUtility::logInfo("DUPLICATE ::: Sample ID/Sample Key Code in $testTable ::: " . $sampleCodeGenerator['sampleCode'] . " / " . $maxId);

                        // Rollback the transaction for this attempt
                        $this->db->rollbackTransaction();

                        // We'll try again with the next iteration
                        continue;
                    }

                    // Successfully generated a non-duplicate code
                    $this->db->commitTransaction();
                    return json_encode($sampleCodeGenerator);
                } else {
                    // For display only, no need to check for duplicates
                    return json_encode($sampleCodeGenerator);
                }
            } catch (Throwable $exception) {
                // Rollback the transaction on error
                if ($insertOperation) {
                    $this->db->rollbackTransaction();
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
                COALESCE((SELECT MAX($codeKey) FROM $testTable
                    WHERE YEAR(sample_collection_date) = ?), 0) AS max_sequence_number
                    ON DUPLICATE KEY UPDATE
                    max_sequence_number = GREATEST(VALUES(max_sequence_number), max_sequence_number)";

        $this->db->rawQuery($query, [$year, $year]);
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
}
