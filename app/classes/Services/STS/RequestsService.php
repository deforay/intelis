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
use App\Abstracts\AbstractTestService;

final class RequestsService
{
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
        $this->dataSyncInterval = (int) $this->commonService->getGlobalConfig('data_sync_interval') ?? 30;
    }

    public function getRequests($testType, $labId, $facilityMapResult = [], $manifestCode = null, $syncSinceDate = null)
    {
        $this->setTestType($testType);

        [$rResult, $resultCount] = $this->runQuery($labId, $facilityMapResult, $manifestCode, $syncSinceDate);
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

        return $requestData;
    }

    private function setTestType(string $testType): void
    {
        $this->testType = $testType;
        $this->tableName = TestsService::getTestTableName($testType);
        $this->primaryKeyName = TestsService::getPrimaryColumn($testType);
        $serviceClass = TestsService::getTestServiceClass($testType);
        $this->testTypeService = ContainerRegistry::get($serviceClass);
    }

    private function runQuery($labId, $facilityMapResult, $manifestCode, $syncSinceDate = null): array
    {
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

        $condition = $this->buildCondition($labId, $facilityMapResult, $manifestCode, $syncSinceDate);

        $sQuery = "SELECT $columnSelection FROM $this->tableName WHERE $condition";

        [$rResult, $resultCount] = $this->db->getDataAndCount($sQuery, returnGenerator: false);

        return [$rResult, $resultCount];
    }
    private function buildCondition($labId, $facilityMapResult = [], $manifestCode = null, $syncSinceDate = null): string
    {
        $condition = empty($facilityMapResult)
            ? "lab_id = $labId"
            : "(lab_id = $labId OR facility_id IN ($facilityMapResult))";

        if ($manifestCode) {
            if ($this->testType === 'tb') {
                $condition .= " AND (sample_package_code like '$manifestCode' OR referral_manifest_code like '$manifestCode')";
            } else {
                $condition .= " AND sample_package_code like '$manifestCode'";
            }
        } elseif ($syncSinceDate) {
            $condition .= " AND DATE(last_modified_datetime) >= '$syncSinceDate'";
        } else {
            // $condition .= " AND data_sync=0 AND last_modified_datetime >= SUBDATE('" . DateUtility::getCurrentDateTime() . "', INTERVAL $this->dataSyncInterval DAY)";
            $condition .= " AND last_modified_datetime >= SUBDATE('" . DateUtility::getCurrentDateTime() . "', INTERVAL $this->dataSyncInterval DAY)";
        }

        return $condition;
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
