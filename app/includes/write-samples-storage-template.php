<?php

use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use PhpOffice\PhpSpreadsheet\IOFactory;

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

    // Define paths
    $originalFile = WEB_ROOT . '/files/storage/storage-bulk-upload.xlsx';
    $fileName = 'storage-bulk-upload-' . time() . '.xlsx';
    $tempFile = TEMP_PATH . DIRECTORY_SEPARATOR . $fileName;

    // Copy original file to a temporary location
    if (copy($originalFile, $tempFile)) {
        $condition = "";

        $query = "SELECT vl.sample_code,vl.patient_art_no  FROM form_vl as vl
                    LEFT JOIN specimen_manifests as pd ON vl.sample_package_code = pd.manifest_code
                    LEFT JOIN batch_details as b ON b.batch_id = vl.sample_batch_id
                    WHERE (pd.manifest_code = '{$_POST['batchOrManifestCodeValue']}'
                            OR b.batch_code = '{$_POST['batchOrManifestCodeValue']}')";

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

        $spreadsheet = IOFactory::load($tempFile);
        $sheet = $spreadsheet->getActiveSheet();

        if (!empty($sampleResult)) {

            foreach ($sampleResult as $rowNo => $data) {
                $rRowCount = $rowNo + 2;
                $sheet->fromArray($data, null, 'A' . $rRowCount);
            }

            $writer = IOFactory::createWriter($spreadsheet, IOFactory::READER_XLSX);
            $writer->save($tempFile);

            // Return the path to the temporary file for download
            echo '/temporary/' . $fileName;
        } else {
            echo false;
        }
    } else {
        echo false;
    }
} else {
    echo '/files/storage/storage-bulk-upload.xlsx';
}
