<?php


use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use App\Utilities\ExportJobUtility;

// The page asked for a background export: queue it and answer at once, so the
// user can move on while bin/export-worker.php runs this same script.
if (ExportJobUtility::queueRequested(__FILE__, null, ['eidMonitoringThresholdReportQuery'])) {
	return;
}

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

if (isset($_SESSION['eidMonitoringThresholdReportQuery']) && trim((string) $_SESSION['eidMonitoringThresholdReportQuery']) !== "") {
    $rResult = $db->rawQuery($_SESSION['eidMonitoringThresholdReportQuery']);

    $res = [];
    foreach ($rResult as $aRow) {
        ExportJobUtility::tick();
        $row = [];
        if (isset($res[$aRow['facility_id']])) {
            if (isset($res[$aRow['facility_id']][$aRow['monthrange']])) {
                if (trim((string) $aRow['is_sample_rejected']) === 'yes') {
                    $row['totalRejected'] = $res[$aRow['facility_id']][$aRow['monthrange']]['totalRejected']  + 1;
                } else {
                    $row['totalRejected'] = $res[$aRow['facility_id']][$aRow['monthrange']]['totalRejected'];
                }
                if (trim((string) $aRow['sample_tested_datetime'])  == null  && trim((string) $aRow['sample_collection_date']) !== '') {
                    $row['totalReceived'] = $res[$aRow['facility_id']][$aRow['monthrange']]['totalReceived']  + 1;
                } else {
                    $row['totalReceived'] = $res[$aRow['facility_id']][$aRow['monthrange']]['totalReceived'];
                }
                $row['facility_name'] = ($aRow['facility_name']);
                $row['monthrange'] = $aRow['monthrange'];
                $row['monthly_target'] = $aRow['monthly_target'];
                $row['totalCollected'] = $res[$aRow['facility_id']][$aRow['monthrange']]['totalCollected']  + 1;
                $res[$aRow['facility_id']][$aRow['monthrange']] = $row;
            } else {
                if (trim((string) $aRow['is_sample_rejected']) === 'yes') {
                    $row['totalRejected'] = $res[$aRow['facility_id']][$aRow['monthrange']]['totalRejected']  + 1;
                } else {
                    $row['totalRejected'] = 0;
                }
                if (trim((string) $aRow['sample_tested_datetime'])  == null  && trim((string) $aRow['sample_collection_date']) !== '') {
                    $row['totalReceived'] = $res[$aRow['facility_id']][$aRow['monthrange']]['totalReceived']  + 1;
                } else {
                    $row['totalReceived'] = 0;
                }
                $row['facility_name'] = ($aRow['facility_name']);
                $row['monthrange'] = $aRow['monthrange'];
                $row['monthly_target'] = $aRow['monthly_target'];
                $row['totalCollected'] = $res[$aRow['facility_id']][$aRow['monthrange']]['totalCollected']  + 1;
                $res[$aRow['facility_id']][$aRow['monthrange']] = $row;
            }
        } else {
            if (trim((string) $aRow['is_sample_rejected']) === 'yes') {
                $row['totalRejected'] = $res[$aRow['facility_id']][$aRow['monthrange']]['totalRejected']  + 1;
            } else {
                $row['totalRejected'] = 0;
            }
            if (trim((string) $aRow['sample_tested_datetime'])  == null  && trim((string) $aRow['sample_collection_date']) !== '') {
                $row['totalReceived'] = $res[$aRow['facility_id']][$aRow['monthrange']]['totalReceived']  + 1;
            } else {
                $row['totalReceived'] = 0;
            }
            $row['facility_name'] = ($aRow['facility_name']);
            $row['monthrange'] = $aRow['monthrange'];
            $row['monthly_target'] = $aRow['monthly_target'];
            $row['totalCollected'] = 1;
            $res[$aRow['facility_id']][$aRow['monthrange']] = $row;
        }
    }

    $filename = 'InteLIS-EID-Testing-Target-Report-' . date('d-M-Y-H-i-s') . '.xlsx';

    $writer = new Writer();
    $writer->openToFile(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
    $writer->addRow(Row::fromValues([html_entity_decode('EARLY INFANT DIAGNOSIS - Testing Target ', ENT_QUOTES, 'UTF-8')]));
    $writer->addRow(Row::fromValues(['']));
    $writer->addRow(Row::fromValues(array_map(static fn($value): string => html_entity_decode($value, ENT_QUOTES, 'UTF-8'), ['Facility Name', 'Month', 'Number of Samples Received', 'Number of Samples Rejected', 'Number of Samples Tested', 'Monthly Test Target'])));
    foreach ($res as $resultData) {
        foreach ($resultData as $rowData) {
            if ($_POST['targetType'] == 1) {
                if ($rowData['monthly_target'] > $rowData['totalCollected']) {
                    $writer->addRow(Row::fromValues([
                        html_entity_decode(((string) $rowData['facility_name']), ENT_QUOTES, 'UTF-8'),
                        html_entity_decode((string) $rowData['monthrange'], ENT_QUOTES, 'UTF-8'),
                        $rowData['totalReceived'],
                        $rowData['totalRejected'],
                        $rowData['totalCollected'],
                        is_numeric($rowData['monthly_target']) ? (int) $rowData['monthly_target'] : $rowData['monthly_target'],
                    ]));
                }
            } elseif ($_POST['targetType'] == 2) {
                if ($rowData['monthly_target'] < $rowData['totalCollected']) {
                    $writer->addRow(Row::fromValues([
                        html_entity_decode(((string) $rowData['facility_name']), ENT_QUOTES, 'UTF-8'),
                        html_entity_decode((string) $rowData['monthrange'], ENT_QUOTES, 'UTF-8'),
                        $rowData['totalReceived'],
                        $rowData['totalRejected'],
                        $rowData['totalCollected'],
                        is_numeric($rowData['monthly_target']) ? (int) $rowData['monthly_target'] : $rowData['monthly_target'],
                    ]));
                }
            } else {
                $writer->addRow(Row::fromValues([
                    html_entity_decode(((string) $rowData['facility_name']), ENT_QUOTES, 'UTF-8'),
                    html_entity_decode((string) $rowData['monthrange'], ENT_QUOTES, 'UTF-8'),
                    $rowData['totalReceived'],
                    $rowData['totalRejected'],
                    $rowData['totalCollected'],
                    is_numeric($rowData['monthly_target']) ? (int) $rowData['monthly_target'] : $rowData['monthly_target'],
                ]));
            }
        }
    }
    $writer->close();

    echo _downloadToken($filename);
}
