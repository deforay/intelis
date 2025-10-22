<?php

use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

if (isset($_SESSION['vlStatisticsFemaleQuery']) && trim((string) $_SESSION['vlStatisticsFemaleQuery']) != "") {
    $filename = '';
    $rResult = $db->rawQuery($_SESSION['vlStatisticsFemaleQuery']);

    $excel = new Spreadsheet();
    $output = [];
    $sheet = $excel->getActiveSheet();

    $headings = array("Province/State", "District/County", "Site Name", "Total Female", "Pregnant <=1000 cp/mL", "Pregnant >1000 cp/mL", "Breastfeeding <=1000 cp/mL", "Breastfeeding >1000 cp/mL", "Age > 15 <=1000 cp/mL", "Age > 15 >1000 cp/mL", "Age Unknown <=1000 cp/mL", "Age Unknown >1000 cp/mL", "Age <=15 <=1000 cp/mL", "Age <=15 >1000 cp/mL");

    $colNo = 1;

    $sheet->fromArray($headings, null, 'A1'); // Write headings

    foreach ($rResult as $aRow) {

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

    $rowNo = 2;
    foreach ($output as $rowData) {
        $rRowCount = $rowNo++;
        $sheet->fromArray($rowData, null, 'A' . $rRowCount);
    }

    $writer = IOFactory::createWriter($excel, IOFactory::READER_XLSX);
    $filename = 'InteLIS-VL-Lab-Female-Weekly-Report-' . date('d-M-Y-H-i-s') . '.xlsx';
    $writer->save(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
    echo $filename;
} else {
    echo '';
}
