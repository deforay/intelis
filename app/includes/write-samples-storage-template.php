<?php

use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use App\Utilities\MiscUtility;
use App\Utilities\SpreadsheetUtility;
use OpenSpout\Common\Entity\Row;

ini_set('memory_limit', -1);
set_time_limit(0);
ini_set('max_execution_time', 20000);

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody(), nullifyEmptyStrings: true);

if (!empty($_POST['batchOrManifestCodeValue'])) {

    $originalFile = WEB_ROOT . '/files/storage/storage-bulk-upload.xlsx';
    $tempFile = TEMP_PATH . '/storage-bulk-upload-' . MiscUtility::generateRandomString(12) . '.xlsx';

    $batchOrManifestCode = $db->escape((string) $_POST['batchOrManifestCodeValue']);
    $query = "SELECT vl.sample_code,vl.patient_art_no  FROM form_vl as vl
                LEFT JOIN specimen_manifests as pd ON vl.sample_package_code = pd.manifest_code
                LEFT JOIN batch_details as b ON b.batch_id = vl.sample_batch_id
                WHERE (pd.manifest_code = '$batchOrManifestCode'
                        OR b.batch_code = '$batchOrManifestCode')";

    // Facility isolation: mapped STS users only see their facilities' samples
    if ($general->isSTSInstance() && !empty($_SESSION['facilityMap'])) {
        $query .= " AND vl.facility_id IN (" . $_SESSION['facilityMap'] . ") ";
    }
    // Lab isolation (cloud-LIS): scope to this user's lab. No-op unless the
    // session carries a lab id, so byte-identical for every existing user.
    if ($labScope = $general->labScopeWhere('vl')) {
        $query .= " AND $labScope";
    }

    $sampleResult = $db->rawQuery($query);

    if (!empty($sampleResult)) {
        $writer = new \OpenSpout\Writer\XLSX\Writer();
        $writer->openToFile($tempFile);
        $writer->addRow(Row::fromValues(SpreadsheetUtility::readHeadingRow($originalFile)));
        foreach ($sampleResult as $data) {
            $writer->addRow(Row::fromValues(array_values($data)));
        }
        $writer->close();

        echo '/download.php?f=' . rawurlencode(_downloadToken($tempFile));
    } else {
        echo false;
    }
} else {
    echo '/files/storage/storage-bulk-upload.xlsx';
}
