<?php

use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use App\Utilities\ExportJobUtility;

// The page asked for a background export: queue it and answer at once, so the
// user can move on while bin/export-worker.php runs this same script.
if (ExportJobUtility::queueRequested(__FILE__, null, ['vlStatisticsFemaleQuery'])) {
	return;
}

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

if (isset($_SESSION['vlStatisticsFemaleQuery']) && trim((string) $_SESSION['vlStatisticsFemaleQuery']) !== "") {
    $filename = '';
    $rResult = $db->rawQuery($_SESSION['vlStatisticsFemaleQuery']);

    $output = [];

    $headings = ["Province/State", "District/County", "Site Name", "Total Female", "Pregnant <=1000 cp/mL", "Pregnant >1000 cp/mL", "Breastfeeding <=1000 cp/mL", "Breastfeeding >1000 cp/mL", "Age > 15 <=1000 cp/mL", "Age > 15 >1000 cp/mL", "Age Unknown <=1000 cp/mL", "Age Unknown >1000 cp/mL", "Age <=15 <=1000 cp/mL", "Age <=15 >1000 cp/mL"];

    foreach ($rResult as $aRow) {
ExportJobUtility::tick();

        $row = [];
        $row[] = ($aRow['facility_state']);
        $row[] = ($aRow['facility_district']);
        $row[] = ($aRow['facility_name']);
        $row[] = $aRow['totalFemale'];
        $row[] = $aRow['pregSuppressed'];
        $row[] = $aRow['pregNotSuppressed'];
        $row[] = $aRow['bfsuppressed'];
        $row[] = $aRow['bfNotSuppressed'];
        $row[] = $aRow['gt15suppressedF'];
        $row[] = $aRow['gt15NotSuppressedF'];
        $row[] = $aRow['ltUnKnownAgesuppressed'];
        $row[] = $aRow['ltUnKnownAgeNotSuppressed'];
        $row[] = $aRow['lt15suppressed'];
        $row[] = $aRow['lt15NotSuppressed'];
        $output[] = $row;
    }

    $filename = 'InteLIS-VL-Lab-Female-Weekly-Report-' . date('d-M-Y-H-i-s') . '.xlsx';

    $writer = new Writer();
    $writer->openToFile(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
    $writer->addRow(Row::fromValues($headings));
    foreach ($output as $rowData) {
        $writer->addRow(Row::fromValues($rowData));
    }
    $writer->close();

    echo _downloadToken($filename);
} else {
    echo '';
}
