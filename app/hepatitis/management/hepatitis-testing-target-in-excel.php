<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use App\Utilities\ExportJobUtility;

// The page asked for a background export: queue it and answer at once, so the
// user can move on while bin/export-worker.php runs this same script.
if (ExportJobUtility::queueRequested(__FILE__, null, ['hepatitisMonitoringThresholdReportQuery'])) {
	return;
}




/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

if (isset($_SESSION['hepatitisMonitoringThresholdReportQuery']) && trim((string) $_SESSION['hepatitisMonitoringThresholdReportQuery']) !== "") {
    $rResult = $db->rawQuery($_SESSION['hepatitisMonitoringThresholdReportQuery']);

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
                if (trim((string) $aRow['sample_tested_datetime'])  == null  && trim((string) $aRow['sample_collection_date']) !== '') { {
                        $row['totalReceived'] = $res[$aRow['facility_id']][$aRow['monthrange']]['totalReceived']  + 1;
                    }
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
    // print_r($res);die;
    //get current quarter total samples tested

    $filename = 'InteLIS-hepatitis-Testing-Target-Report-' . date('d-M-Y-H-i-s') . '.xlsx';

    $writer = new Writer();
    $writer->openToFile(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
    $writer->addRow(Row::fromValues([html_entity_decode('Hepatitis - Testing Target ', ENT_QUOTES, 'UTF-8')]));
    $writer->addRow(Row::fromValues(['']));
    $writer->addRow(Row::fromValues(array_map(static fn($value): string => html_entity_decode($value, ENT_QUOTES, 'UTF-8'), ['Facility Name', 'Month', 'Number of Samples Received', 'Number of Samples Rejected', 'Number of Samples Tested', 'Monthly Test Target'])));
    foreach ($res as $resultData) {
        foreach ($resultData as $rowData) {
            if ($rowData['monthly_target'] > $rowData['totalCollected']) {
                $writer->addRow(Row::fromValues([
                    html_entity_decode(((string) $rowData['facility_name']), ENT_QUOTES, 'UTF-8'),
                    html_entity_decode((string) $rowData['monthrange'], ENT_QUOTES, 'UTF-8'),
                    html_entity_decode($rowData['totalReceived'], ENT_QUOTES, 'UTF-8'),
                    html_entity_decode($rowData['totalRejected'], ENT_QUOTES, 'UTF-8'),
                    html_entity_decode($rowData['totalCollected'], ENT_QUOTES, 'UTF-8'),
                    html_entity_decode((string) $rowData['monthly_target'], ENT_QUOTES, 'UTF-8'),
                ]));
            }
        }
    }
    $writer->close();

    echo _downloadToken($filename);
}
