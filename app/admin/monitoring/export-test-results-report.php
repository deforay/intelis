<?php


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
if (ExportJobUtility::queueRequested(__FILE__, null, ['testResultReportsQuery'])) {
	return;
}


ini_set('memory_limit', -1);
set_time_limit(0);
ini_set('max_execution_time', 300000);

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$sQuery = $_SESSION['testResultReportsQuery'];
[$rResult, $resultCount] = $db->getDataAndCount($sQuery);


$headings = [
    _translate('Sample ID'),
    _translate('Remote Sample ID'),
    _translate('Sample Collection Date'),
    _translate('Sample Recieved On'),
    _translate('Sample Tested On'),
    _translate('Result'),
    _translate('Tested By'),
    _translate('Test Platform/Instrument'),
    _translate('Result Status'),
    _translate('Manual Result Entry'),
    _translate('Is Sample Rejected'),
    _translate('Rejection Reason'),
    _translate('Was Result Changed?'),
    _translate('Reason for Changing'),
    _translate('File Link')
];


$output = [];


$nameValue = '';
foreach ($_POST as $key => $value) {
    if (trim((string) $value) !== '' && trim((string) $value) !== '-- Select --') {
        $nameValue .= str_replace("_", " ", $key) . " : " . $value . "&nbsp;&nbsp;";
    }
}

$no = 1;
foreach ($rResult as $aRow) {
    ExportJobUtility::tick();
    // The reason column holds a history of changes (and, on older records, legacy formats),
    // not the single object this once assumed -- export the most recent recorded change. EID,
    // COVID and hepatitis keep the same history under reason_for_changing.
    $latestChange = MiscUtility::latestResultChangeReason(
        $aRow['reason_for_result_changes'] ?? $aRow['reason_for_changing'] ?? null
    );
    $row = [];
    $row[] = $aRow['sample_code'];
    $row[] = $aRow['remote_sample_code'];
    $row[] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '', true);
    $row[] = DateUtility::humanReadableDateFormat($aRow['sample_received_at_lab_datetime'] ?? '', true);
    $row[] = DateUtility::humanReadableDateFormat($aRow['sample_tested_datetime'] ?? '', true);
    $row[] = $aRow['result'] ?? $aRow['cd4_result'] ?? '';
    $row[] = html_entity_decode($aRow['testedByName'] ?? '');
    $row[] = html_entity_decode($aRow['machine_name'] ?? '');
    $row[] = $aRow['status_name'];
    $row[] = $aRow['manual_result_entry'];
    $row[] = $aRow['is_sample_rejected'];
    $row[] = $aRow['rejection_reason_name'];
    $row[] = $aRow["result_modified"];
    $row[] = html_entity_decode((string) ($latestChange['msg'] ?? ''));
    $row[] = $aRow['import_machine_file_name'];
    $output[] = $row;
    $no++;
}

$filename = 'InteLIS-SAMPLEWISE-REPORT-' . date('d-M-Y-H-i-s') . '-' . MiscUtility::generateRandomNumber(6) . '.xlsx';

$writer = new Writer();
$writer->openToFile(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
$writer->addRow(Row::fromValues([html_entity_decode($nameValue)]));
$writer->addRow(Row::fromValues(['']));
$writer->addRow(Row::fromValues(array_map('html_entity_decode', $headings)));
foreach ($output as $rowData) {
    $writer->addRow(Row::fromValues($rowData));
}
$writer->close();

echo _downloadToken($filename);
