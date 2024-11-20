<?php

namespace App\Abstracts;

use COUNTRY;
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
    public DatabaseService $db;
    public CommonService $commonService;
    public TestRequestsService $testRequestsService;
    public int $maxTries = 5; // Max tries for generating Sample ID
    public string $table;
    public string $primaryKey;
    public string $testType;
    public string $shortCode;

    public function __construct(DatabaseService $db, CommonService $commonService)
    {
        $this->db = $db;
        $this->commonService = $commonService;
        $this->table ??= TestsService::getTestTableName($this->testType);
        $this->primaryKey ??= TestsService::getTestPrimaryKeyColumn($this->testType);
        $this->shortCode ??= TestsService::getTestShortCode($this->testType);
        $this->testRequestsService = new TestRequestsService($db, $commonService);
    }
    abstract public function getSampleCode($params);
    abstract public function insertSample($params, $returnSampleData = false);

    private function getMaxId($year, $testType, $sampleCodeType, $insertOperation)
    {
        $sql = "SELECT max_sequence_number FROM sequence_counter
        WHERE year = ? AND
        test_type = ? AND
        code_type = ?";

        if ($insertOperation) {
            $sql .= " FOR UPDATE";
        }
        $yearData = $this->db->rawQueryOne($sql, [
            $year,
            $testType,
            $sampleCodeType
        ]);

        $maxSeqNum = $yearData['max_sequence_number'] ?? 0;

        if ($maxSeqNum == 0) {
            $this->resetSequenceCounter($this->table, $year, $testType, $sampleCodeType);
            $yearData = $this->db->rawQueryOne($sql, [
                $year,
                $testType,
                $sampleCodeType
            ]);
            $maxSeqNum = $yearData['max_sequence_number'] ?? 0;
        }

        return max((int) $maxSeqNum, 0);
    }

    public function generateSampleCode($testTable, $params, $tryCount = 0)
    {

        $sampleCodeGenerator = [];
        // We use this flag to determine if we are generating Sample ID for inserting
        // or just displaging on the form
        $insertOperation = $params['insertOperation'] ?? true;

        if ($insertOperation) {
            // Start a new transaction (this starts a new transaction if not already started)
            // see the beginTransaction() function implementation to understand how this works
            $this->db->beginTransaction();
        }

        $this->testType = $params['testType'] ?? $this->testType ?? 'generic-tests';

        $formId = (int) $this->commonService->getGlobalConfig('vl_form');

        try {
            while ($tryCount < $this->maxTries) {

                $sampleCollectionDate = $params['sampleCollectionDate'] ?? null;
                $provinceCode = $params['provinceCode'] ?? '';
                //$provinceId = $params['provinceId'] ?? null;
                $sampleCodeFormat = $params['sampleCodeFormat'] ?? 'MMYY';
                $prefix = $params['prefix'] ?? $this->shortCode ?? 'T';
                $existingMaxId = $params['existingMaxId'] ?? 0;

                if (empty($sampleCollectionDate) || DateUtility::isDateValid($sampleCollectionDate) === false) {
                    $sampleCollectionDate = 'now';
                }

                $dateObj = new DateTimeImmutable($sampleCollectionDate);

                $year = $dateObj->format('y');
                $month = $dateObj->format('m');
                $day = $dateObj->format('d');

                $autoFormatedString = $year . $month . $day;

                $remotePrefix = '';
                $sampleCodeType = 'sample_code';
                if ($this->commonService->isSTSInstance()) {
                    $remotePrefix = 'R';
                    $sampleCodeType = 'remote_sample_code';
                }

                $currentYear = $dateObj->format('Y');
                $latestSequenceId = $this->getMaxId($currentYear, $this->testType, $sampleCodeType, $insertOperation);

                if (!empty($existingMaxId) && $existingMaxId > 0) {
                    $maxId = max($existingMaxId, $latestSequenceId) + 1;
                } else {
                    $maxId = $latestSequenceId + 1;
                }

                // padding with zeroes
                $maxId = sprintf("%04d", (int) $maxId);

                $sampleCodeGenerator = [
                    'sampleCodeFormat' => $sampleCodeFormat,
                    'sampleCodeKey' => $maxId,
                    'maxId' => $maxId,
                    'monthYear' => $month . $year,
                    'year' => $year,
                    'auto' => $year . $month . $day
                ];

                // PNG format has an additional R in prefix
                if ($formId == COUNTRY\PNG) {
                    $remotePrefix .= "R";
                }

                if ($sampleCodeFormat == 'auto') {
                    $sampleCodeGenerator['sampleCodeFormat'] = $remotePrefix . $provinceCode . $autoFormatedString;
                } elseif ($sampleCodeFormat == 'auto2') {
                    $sampleCodeGenerator['sampleCodeFormat'] = $remotePrefix . $year . $provinceCode . $prefix;
                } elseif ($sampleCodeFormat == 'MMYY') {
                    $sampleCodeGenerator['sampleCodeFormat'] = $remotePrefix . $prefix . $sampleCodeGenerator['monthYear'];
                } elseif ($sampleCodeFormat == 'YY') {
                    $sampleCodeGenerator['sampleCodeFormat'] = $remotePrefix . $prefix . $sampleCodeGenerator['year'];
                } else {
                    $sampleCodeGenerator['sampleCodeFormat'] = $remotePrefix . $prefix;
                }

                $sampleCodeGenerator['sampleCode'] = $sampleCodeGenerator['sampleCodeFormat'] . $sampleCodeGenerator['maxId'];
                $sampleCodeGenerator['sampleCodeInText'] = $sampleCodeGenerator['sampleCodeFormat'] . $sampleCodeGenerator['maxId'];

                // We check for duplication only if we are inserting a new record
                if ($insertOperation) {
                    $checkDuplicateQuery = "SELECT 1 FROM $testTable WHERE $sampleCodeType = ? LIMIT 1";
                    $checkDuplicateResult = $this->db->rawQueryOne($checkDuplicateQuery, [$sampleCodeGenerator['sampleCode']]);
                    if (!empty($checkDuplicateResult)) {
                        // Rollback the current transaction to release locks and undo changes
                        $this->db->rollbackTransaction();

                        LoggerUtility::logInfo("DUPLICATE ::: Sample ID/Sample Key Code in $testTable ::: " . $sampleCodeGenerator['sampleCode'] . " / " . $maxId);
                        $params['existingMaxId'] = $maxId;

                        if ($insertOperation) {
                            // Add a small delay before retrying to avoid immediate retries
                            usleep($tryCount * 50000); // 50 milliseconds
                        }

                        return $this->generateSampleCode($testTable, $params, $tryCount + 1);
                    }

                    //Insert or update the sequence counter for this test type and year
                    $data = [
                        'test_type' => $this->testType,
                        'year' => $currentYear,
                        'max_sequence_number' => $maxId,
                        'code_type' => $sampleCodeType
                    ];

                    $updateColumns = [
                        'max_sequence_number' => $maxId
                    ];

                    $this->db->upsert('sequence_counter', $data, $updateColumns);
                }

                return json_encode($sampleCodeGenerator);
            }

            throw new SystemException("Exceeded maximum number of tries ($this->maxTries) for generating Sample ID");
        } catch (Throwable $exception) {
            // Rollback the current transaction to release locks and undo changes
            if ($insertOperation) {
                $this->db->rollbackTransaction();
            }

            if ($tryCount < $this->maxTries) {

                if ($insertOperation || in_array($exception->getCode(), [1205, 1213])) {
                    // Add a small delay before retrying to avoid immediate retries
                    usleep($tryCount * 50000); // 50 milliseconds
                }

                return $this->generateSampleCode($testTable, $params, $tryCount + 1);
            }

            throw new SystemException("Error while generating Sample ID for $testTable (try count = $tryCount) : " . $exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    private function resetSequenceCounter($testTable, $year, $testType, $sampleCodeType)
    {
        $this->db->rawQuery(
            "DELETE FROM sequence_counter WHERE year = ? AND test_type = ? AND code_type = ?",
            [$year, $testType, $sampleCodeType]
        );

        $codeKey = "{$sampleCodeType}_key";
        $query = "INSERT INTO sequence_counter (test_type, year, code_type, max_sequence_number)
                        SELECT
                        '$testType' AS test_type,
                        COALESCE(YEAR(sample_collection_date), YEAR(CURDATE())) AS year,
                        '$sampleCodeType' AS code_type,
                        COALESCE(MAX($codeKey), 0) AS max_sequence_number
                    FROM $testTable
                    WHERE YEAR(sample_collection_date) <= YEAR(CURDATE())
                    GROUP BY YEAR(sample_collection_date)
                    ON DUPLICATE KEY UPDATE
                    max_sequence_number = GREATEST(VALUES(max_sequence_number), max_sequence_number)";

        $this->db->rawQuery($query);
    }
}
