<?php

use App\Utilities\DateUtility;
use App\Services\ActivityLogService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

$tableName = "r_covid19_qc_testkits";
$primaryKey = "testkit_id";
// Read before the save, so the activity log lists what changed.
/** @var ActivityLogService $activityLog */
$activityLog = ContainerRegistry::get(ActivityLogService::class);
$activityId = (int) base64_decode((string) ($_POST['qcTestId'] ?? ''));
$activityBefore = $activityLog->row('r_covid19_qc_testkits', 'testkit_id', $activityId);

try {
    if (isset($_POST['testKitName']) && trim((string) $_POST['testKitName']) !== "") {
        $data = ['testkit_name'                  => $_POST['testKitName'], 'status'                        => $_POST['testKitStatus'], 'labels_and_expected_results'   => json_encode(["label" => $_POST['qcTestLable'], 'expected' => $_POST['expectedResult']]), 'updated_datetime'              => DateUtility::getCurrentDateTime()];

        if (isset($_POST['qcTestId']) && $_POST['qcTestId'] != "") {
            $db->where($primaryKey, base64_decode((string) $_POST['qcTestId']));
            $lastId = $db->update($tableName, $data);
        } else {
            $lastId = $db->insert($tableName, $data);
        }

        if ($lastId > 0) {
            $_SESSION['alertMsg'] = _translate("Covid-19 QC test kit saved successfully");
            $activityLog->recordRowSave(
                'covid19-qc-test-kit-save',
                ($activityBefore === null ? 'Added ' : 'Updated ') . 'COVID-19 QC test kit ' . $_POST['testKitName'],
                'covid19-qc-test-kit',
                'r_covid19_qc_testkits',
                'testkit_id',
                $activityBefore === null ? (int) $db->getInsertId() : $activityId,
                'testkit_name',
                $activityBefore
            );
        }
    }
    header("Location:covid19-qc-test-kits.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    throw $exc;
}
