<?php

declare(strict_types=1);

use Throwable;
use App\Services\TestsService;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;
use App\Services\TestRequestsService;

// Run only via CLI
if (php_sapi_name() !== 'cli') {
    exit(0);
}

require_once __DIR__ . '/../bootstrap.php';

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $commonService */
$commonService = ContainerRegistry::get(CommonService::class);

/** @var TestRequestsService $testRequestsService */
$testRequestsService = ContainerRegistry::get(TestRequestsService::class);

$scriptName = basename(__FILE__);
$forceRun = in_array('-f', $argv ?? [], true) || in_array('--force', $argv ?? [], true);
$scriptSucceeded = false;
if (!$forceRun) {
    $db->where('script_name', $scriptName);
    $alreadyExecuted = $db->getOne('s_run_once_scripts_log');
    if (!empty($alreadyExecuted)) {
        echo("Script $scriptName has already been executed. Use --force to run again.");
        exit(0);
    }
}

$batchSize = 200;
$offset = 0;
$totalManifests = 0;
$totalUpdated = 0;
$totalSamplesUpdated = 0;
$scriptSucceeded = false;

try {

    // This script applies only on STS instance
    if (!$commonService->isSTSInstance()) {
        $scriptSucceeded = true;
        throw new SystemException("$scriptName is intended for STS instances only." . PHP_EOL);
    }

    while (true) {
        $manifests = $db->rawQuery(
            "SELECT package_id, package_code, module, manifest_hash, number_of_samples, last_modified_datetime
                FROM specimen_manifests
                ORDER BY package_id
                LIMIT $batchSize OFFSET $offset"
        );

        if (empty($manifests)) {
            break;
        }

        foreach ($manifests as $manifest) {
            $totalManifests++;

            $module = trim((string) ($manifest['module'] ?? ''));
            $manifestCode = trim((string) ($manifest['package_code'] ?? ''));
            if ($module === '' || $manifestCode === '') {
                continue;
            }

            try {
                $tableName = TestsService::getTestTableName($module);
                $primaryKey = TestsService::getPrimaryColumn($module);
            } catch (Throwable $e) {
                LoggerUtility::logError('Unable to resolve test table for manifest module', [
                    'module' => $module,
                    'manifestCode' => $manifestCode,
                    'exception' => $e->getMessage(),
                ]);
                continue;
            }

            $db->reset();
            $db->where('sample_package_code', $manifestCode);
            $sampleIds = $db->getValue($tableName, $primaryKey, null);

            if (empty($sampleIds)) {
                continue;
            }

            $sampleIds = array_filter(array_unique((array) $sampleIds));
            $numberOfSamples = count($sampleIds);
            if ($numberOfSamples === 0) {
                continue;
            }

            $computedHash = $testRequestsService->getManifestHash($sampleIds);
            if (empty($computedHash)) {
                continue;
            }

            $currentDateTime = DateUtility::getCurrentDateTime();

            // Update specimen_manifests table when values differ
            $manifestUpdates = [];
            if ($manifest['manifest_hash'] !== $computedHash) {
                $manifestUpdates['manifest_hash'] = $computedHash;
            }
            if ((int) $manifest['number_of_samples'] !== $numberOfSamples) {
                $manifestUpdates['number_of_samples'] = $numberOfSamples;
            }
            if (!empty($manifestUpdates)) {
                $manifestUpdates['last_modified_datetime'] = $currentDateTime;

                $db->reset();
                $db->where('package_id', $manifest['package_id']);
                if ($db->update('specimen_manifests', $manifestUpdates)) {
                    $totalUpdated++;
                } else {
                    LoggerUtility::logError('Failed updating manifest record', [
                        'manifestCode' => $manifestCode,
                        'module' => $module,
                        'dbError' => $db->getLastError(),
                    ]);
                }
            }

            // Update linked test requests form_attributes manifest block
            $manifestAttributes = [
                'manifest' => [
                    'number_of_samples' => $numberOfSamples,
                    'manifest_hash' => $computedHash,
                    'last_modified_datetime' => $currentDateTime,
                ],
            ];

            $formAttributesExpr = JsonUtility::jsonToSetString(null, 'form_attributes', $manifestAttributes);

            if ($formAttributesExpr !== null) {
                $updateData = [
                    'form_attributes' => $db->func($formAttributesExpr),
                    'data_sync' => 0,
                ];

                $db->reset();
                $db->where('sample_package_code', $manifestCode);
                $updatedRows = $db->update($tableName, $updateData);
                if ($updatedRows !== false) {
                    $totalSamplesUpdated += $updatedRows;
                } else {
                    LoggerUtility::logError('Failed updating manifest form attributes on test records', [
                        'manifestCode' => $manifestCode,
                        'module' => $module,
                        'dbError' => $db->getLastError(),
                    ]);
                }
            }
        }

        $offset += $batchSize;
    }

    $scriptSucceeded = true;

    echo sprintf(
        "%s completed. Processed: %d, Manifests updated: %d, Samples updated: %d" . PHP_EOL,
        $scriptName,
        $totalManifests,
        $totalUpdated,
        $totalSamplesUpdated
    );
} catch (Throwable $e) {
    LoggerUtility::logError('Manifest hash update script failed', [
        'exception' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'last_db_query' => $db->getLastQuery(),
        'last_db_error' => $db->getLastError(),
    ]);
} finally {
    if ($scriptSucceeded || $forceRun) {
        $db->setQueryOption('IGNORE')->insert('s_run_once_scripts_log', [
            'script_name' => $scriptName,
            'execution_date' => DateUtility::getCurrentDateTime(),
            'status' => $scriptSucceeded ? 'executed' : 'forced'
        ]);
    }
}