<?php

if (php_sapi_name() === 'cli') {
    require_once(__DIR__ . "/../../bootstrap.php");
}

use App\Services\TestsService;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use App\Abstracts\AbstractTestService;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$maxTries = 5; // Maximum number of tries to generate sample code
$interval = 5; // Interval in seconds to wait between checks

$lockFile = __DIR__ . '/sample_code_generation.lock';

// Check if another instance is already running
if (file_exists($lockFile) && (filemtime($lockFile) > (time() - $interval * 2))) {
    //LoggerUtility::log('error', "Another instance is already running");
    exit(0);
}

// Create or update the lock file
touch($lockFile);

$sampleCodeColumn = $general->isSTSInstance() ? 'remote_sample_code' : 'sample_code';

try {
    $queueItems = $db->rawQuery("SELECT * FROM queue_sample_code_generation WHERE processed = 0 LIMIT 100");

    if (!empty($queueItems)) {
        foreach ($queueItems as $item) {

            // Touch the lock file to keep it live
            touch($lockFile);

            if (empty($item['test_type']) || empty($item['sample_collection_date']) || empty($item['unique_id'])) {
                continue;
            }

            try {
                $formTable = TestsService::getTestTableName($item['test_type']);
                $primaryKey = TestsService::getTestPrimaryKeyColumn($item['test_type']);
                $serviceClass = TestsService::getTestServiceClass($item['test_type']);

                /** @var AbstractTestService $testTypeService */
                $testTypeService = ContainerRegistry::get($serviceClass);

                // Check if sample code already exists
                $sQuery = "SELECT $sampleCodeColumn FROM $formTable WHERE unique_id = ?";
                $rowData = $db->rawQueryOne($sQuery, [$item['unique_id']]);

                if (!empty($rowData) && !empty($rowData[$sampleCodeColumn])) {
                    continue;
                }

                $db->beginTransaction();

                $sampleCodeParams = [
                    'sampleCollectionDate' => $item['sample_collection_date'],
                    'provinceCode' => $item['province_code'] ?? null,
                    'testType' => $item['test_type'],
                    'sampleCodeFormat' => $item['sample_code_format'] ?? 'MMYY',
                    'prefix' => $item['prefix'] ?? 'T',
                    'insertOperation' => true,
                ];

                $tries = 0;
                $sampleData = [];

                do {
                    $sampleCodeParams['tries'] = $tries;
                    $sampleJson = $testTypeService->getSampleCode($sampleCodeParams);
                    $sampleData = json_decode((string)$sampleJson, true);

                    $rowData = [];
                    if (!empty($sampleData) && !empty($sampleData['sampleCode'])) {
                        $sQuery = "SELECT $primaryKey FROM $formTable WHERE $sampleCodeColumn = ?";
                        $rowData = $db->rawQueryOne($sQuery, [$sampleData['sampleCode']]);
                    }

                    $tries++;
                } while (!empty($rowData) && $tries < $maxTries);

                if ($tries >= $maxTries) {
                    throw new Exception("Maximum tries for generating sample code for {$item['unique_id']} exceeded");
                }

                $accessType = $item['access_type'] ?? null;
                $tesRequestData = [];

                if ($general->isSTSInstance()) {
                    $tesRequestData['remote_sample_code'] = $sampleData['sampleCode'];
                    $tesRequestData['remote_sample_code_format'] = $sampleData['sampleCodeFormat'];
                    $tesRequestData['remote_sample_code_key'] = $sampleData['sampleCodeKey'];
                    $tesRequestData['remote_sample'] = 'yes';
                    $tesRequestData['result_status'] = SAMPLE_STATUS\RECEIVED_AT_CLINIC;
                    if ($accessType === 'testing-lab') {
                        $tesRequestData['sample_code'] = $sampleData['sampleCode'];
                    }
                } else {
                    $tesRequestData['sample_code'] = $sampleData['sampleCode'];
                    $tesRequestData['sample_code_format'] = $sampleData['sampleCodeFormat'];
                    $tesRequestData['sample_code_key'] = $sampleData['sampleCodeKey'];
                    $tesRequestData['remote_sample'] = 'no';
                }

                if (!empty($sampleData['sampleCode'])) {
                    $db->where('unique_id', $item['unique_id']);
                    $db->update($formTable, $tesRequestData);

                    $db->where('id', $item['id']);
                    $db->update('queue_sample_code_generation', ['processed' => 1]);
                }

                $db->commitTransaction();
            } catch (Throwable $e) {
                $db->rollbackTransaction();
                LoggerUtility::log('error', $e->getFile() . ":" . $e->getLine() . " - " . $e->getMessage(), [
                    'exception' => $e,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'stacktrace' => $e->getTraceAsString()
                ]);
            }
        }
    }
} finally {
    // Remove the lock file when the script ends
    unlink($lockFile);
}
