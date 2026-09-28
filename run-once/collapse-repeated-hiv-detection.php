<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Services\VlService;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Utilities\LoggerUtility;
use App\Utilities\RunOnceUtility;
use App\Registries\ContainerRegistry;

/*
 * One-time repair for VL results that carry the HIV detection more than once, such as
 * "HIV-1 Detected HIV-1 Detected HIV-1 Detected 121".
 *
 * A GeneXpert result is stored as the detection followed by the figure, and the
 * detection is also kept on its own in result_value_hiv_detection. An API client that
 * posted a saved record back sent that whole result with hivDetection, and the save put
 * the detection in front once more on every re-post. The save now removes a detection
 * already at the front before adding it (VlService::stripHivDetectionPrefix); this
 * repairs what was stored before that.
 *
 * Rows whose result_value_hiv_detection is not a detection at all (some old rows hold
 * "0") are left alone: there is no detection to collapse them onto.
 *
 * Only `result` changes, plus data_sync on a lab. vl_result_category was worked out
 * after removing every detection phrase, so the repeats never changed it, and
 * last_modified_datetime is left alone.
 *
 * The lab and STS each run this, but not at the same time: labs upgrade on their own
 * schedule. An STS repaired first can take the repeated value again from a lab that has
 * not upgraded yet, and once that result is acknowledged the lab never sends it again.
 * So a lab marks each row it repairs as unsent, and its corrected result goes up with
 * the next result sync.
 */

RunOnceUtility::run(__FILE__, function (DatabaseService $db): void {
    /** @var CommonService $general */
    $general = ContainerRegistry::get(CommonService::class);
    $isLab = $general->isLISInstance();

    $rows = $db->rawQuery(
        "SELECT vl_sample_id, result, result_value_hiv_detection
           FROM form_vl
          WHERE result_value_hiv_detection LIKE '%detected%'
            AND result LIKE '%detected%detected%'"
    ) ?: [];

    $repaired = 0;
    foreach ($rows as $row) {
        $detection = trim((string) $row['result_value_hiv_detection']);
        $collapsed = trim($detection . ' ' . VlService::stripHivDetectionPrefix((string) $row['result'], $detection));

        if ($collapsed === $row['result']) {
            continue;
        }

        $update = ['result' => $collapsed];
        if ($isLab) {
            $update['data_sync'] = 0;
        }

        $db->where('vl_sample_id', $row['vl_sample_id']);
        if ($db->update('form_vl', $update)) {
            $repaired++;
        }
    }

    LoggerUtility::logInfo('collapse-repeated-hiv-detection: repaired ' . $repaired . ' VL results');
});
