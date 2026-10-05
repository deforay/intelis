<?php

use const COUNTRY\DRC;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use App\Utilities\ExportJobUtility;

// The page asked for a background export: queue it and answer at once, so the
// user can move on while bin/export-worker.php runs this same script.
if (ExportJobUtility::queueRequested(__FILE__, null, ['resultNotAvailable'])) {
	return;
}

ini_set('memory_limit', '512M');
// A background job has no request to time out; a direct request keeps its limit.
if (!ExportJobUtility::inBackground()) {
	set_time_limit(300);
	ini_set('max_execution_time', 300);
}

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$key = (string) $general->getGlobalConfig('key');
$formId = (int) $general->getGlobalConfig('vl_form');

if (isset($_SESSION['resultNotAvailable']) && trim((string) $_SESSION['resultNotAvailable']) !== "") {

    $headings = ['Sample ID', 'Remote Sample ID', "Facility Name", "Child ID", "Child's Name", "Sample Collection Date", "Sample Received at Testing Lab", "Lab Name", "Sample Status", "Implementing Partner"];
    if ($general->isStandaloneInstance()) {
        $headings = MiscUtility::removeMatchingElements($headings, ['Remote Sample ID']);
    }
    if ($formId == DRC) {
        $headings = MiscUtility::removeMatchingElements($headings, ["Child's Name"]);
    }

    $filename = TEMP_PATH . DIRECTORY_SEPARATOR . 'InteLIS-Results-Not-Available-Report-' . date('d-M-Y-H-i-s') . '.xlsx';

    $writer = new Writer();
    $writer->openToFile($filename);
    $writer->addRow(Row::fromValues($headings));

    $resultSet = $db->rawQueryGenerator($_SESSION['resultNotAvailable']);
    foreach ($resultSet as $aRow) {
        ExportJobUtility::tick();
        if (!empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes') {
            $aRow['child_id'] = $general->crypto('decrypt', $aRow['child_id'], $key);
            $aRow['child_name'] = $general->crypto('decrypt', $aRow['child_name'], $key);
        }
        $row = [];
        $row[] = $aRow['sample_code'];
        if (!$general->isStandaloneInstance()) {
            $row[] = $aRow['remote_sample_code'];
        }
        $row[] = $aRow['facility_name'];
        $row[] = $aRow['child_id'];
        if ($formId != DRC) {
            $row[] = trim(($aRow['child_name'] ?? '') . ' ' . ($aRow['child_surname'] ?? ''));
        }
        $row[] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '');
        $row[] = DateUtility::humanReadableDateFormat($aRow['sample_received_at_lab_datetime'] ?? '');
        $row[] = $aRow['labName'];
        $row[] = $aRow['status_name'];
        $row[] = $aRow['i_partner_name'];

        $writer->addRow(Row::fromValues($row));
    }

    $writer->close();
    echo _downloadToken($filename);
}
