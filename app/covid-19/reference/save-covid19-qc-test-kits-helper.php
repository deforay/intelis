<?php

use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$tableName = "r_covid19_qc_testkits";
$primaryKey = "testkit_id";
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
            $general->activityLog('Covid-19 qc test kit', $_SESSION['userName'] . ' added new qc test kit for ' . $_POST['testKitName'], 'covid19-reference');
        }
    }
    header("Location:covid19-qc-test-kits.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
}
