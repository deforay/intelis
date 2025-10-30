<?php

use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;

require_once(__DIR__ . '/../../bootstrap.php');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

$testingLabs = $facilitiesService->getTestingLabs('tb');

try {
    // 1. Check if script is running in CLI mode
    if (php_sapi_name() !== 'cli') {
        echo ("This script can only be run from the command line interface (CLI).");
        exit(0);
    }
    // 2. Check if script is running in STS mode
    if (!$general->isSTSInstance()) {
        echo ("This script can only be run in STS mode.");
        exit(0);
    }
    /** @var DatabaseService $db */
    $db = ContainerRegistry::get(DatabaseService::class);

    // 3. Select samples where lab_id != referred_to_lab_id
    $db->where("lab_id != referred_to_lab_id");
    $db->where("referred_to_lab_id IS NOT NULL");
    $samplesToUpdate = $db->get('form_tb');
    if (!empty($samplesToUpdate)) {
        foreach ($samplesToUpdate as $sample) {
            $tbId = $sample['tb_id'];
            $oldLabId = $sample['lab_id'];
            $newLabId = $sample['referred_to_lab_id'];

            // Update the form_tb table - set lab_id to referred_to_lab_id
            $updateData = [
                'lab_id' => $newLabId,
                'result_status' => 13,
                'last_modified_datetime' => DateUtility::getCurrentDateTime(),
                'data_sync' => 0
            ];

            $db->where("tb_id", $tbId);
            $updated = $db->update('form_tb', $updateData);

            if ($updated) {
                // 4. Insert into tb_referral_history table
                $historyData = [
                    'tb_id' => $tbId,
                    'from_lab_id' => $oldLabId,
                    'to_lab_id' => $newLabId,
                    'reason_for_referral' => $sample['reason_for_referral'],
                    'referred_on_datetime' => $sample['referred_on_datetime'] ?? DateUtility::getCurrentDateTime(),
                    'referred_by' => $sample['referred_by']
                ];

                $db->insert('tb_referral_history', $historyData);
            }
        }
    }
} catch (Exception $e) {
    LoggerUtility::logError($e->getFile() . ':' . $e->getLine() . ":" . ($db->getLastError() ?? 'N/A'));
    LoggerUtility::logError($e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);
    exit(1);
}
