<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

if (isset($_SESSION['covid19MonitoringThresholdReportQuery']) && trim($_SESSION['covid19MonitoringThresholdReportQuery']) != "") {
    $rResult = $db->rawQuery($_SESSION['covid19MonitoringThresholdReportQuery']);

    $res = [];
    foreach ($rResult as $aRow) {
        $row = [];
        if (isset($res[$aRow['facility_id']])) {
            if (isset($res[$aRow['facility_id']][$aRow['monthrange']])) {
                if (trim($aRow['is_sample_rejected'])  == 'yes') {
                    $row['totalRejected'] = $res[$aRow['facility_id']][$aRow['monthrange']]['totalRejected']  + 1;
                } else {
                    $row['totalRejected'] = $res[$aRow['facility_id']][$aRow['monthrange']]['totalRejected'];
                }
                if (trim($aRow['sample_tested_datetime'])  == null  && trim($aRow['sample_collection_date']) != '') {
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
                if (trim($aRow['is_sample_rejected'])  == 'yes') {
                    $row['totalRejected'] = $res[$aRow['facility_id']][$aRow['monthrange']]['totalRejected']  + 1;
                } else {
                    $row['totalRejected'] = 0;
                }
                if (trim($aRow['sample_tested_datetime'])  == null  && trim($aRow['sample_collection_date']) != '') {
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
            if (trim($aRow['is_sample_rejected'])  == 'yes') {
                $row['totalRejected'] = $res[$aRow['facility_id']][$aRow['monthrange']]['totalRejected']  + 1;
            } else {
                $row['totalRejected'] = 0;
            }
            if (trim($aRow['sample_tested_datetime'])  == null  && trim($aRow['sample_collection_date']) != '') {
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

    $excel = new Spreadsheet();
    $output = [];
    $sheet = $excel->getActiveSheet();

    $colNo = 1;

    $headingStyle = array(
        'font' => array(
            'bold' => true,
            'size' => '11',
        ),
        'alignment' => array(
            'horizontal' => Alignment::HORIZONTAL_LEFT,
        ),
    );
    $backgroundStyle = array(
        'font' => array(
            'bold' => true,
            'size' => '13',
            'color' => array('rgb' => 'FFFFFF'),
        ),
        'alignment' => array(
            'horizontal' => Alignment::HORIZONTAL_CENTER,
        ),
        'fill' => array(
            'fillType' => Fill::FILL_SOLID,
            'color' => array('rgb' => '5c5c5c'),
        ),
    );
    $questionStyle = array(
        'font' => array(
            //'bold' => true,
            'size' => '11',
        ),
        'alignment' => array(
            //'wrapText' => true
            //'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
        ),
        'fill' => array(
            'fillType' => Fill::FILL_SOLID,
            'color' => array('rgb' => 'A9A9A9'),
        ),
    );
    $genderquestionStyle = array(
        'font' => array(
            //'bold' => true,
            'size' => '11',
        ),
        'alignment' => array(
            'horizontal' => Alignment::HORIZONTAL_LEFT,
        ),
        'fill' => array(
            'fillType' => Fill::FILL_SOLID,
            'color' => array('rgb' => 'A9A9A9'),
        ),
    );
    $styleArray = array(
        'font' => array(
            //'bold' => true,
            'size' => '11',
        ),
        'alignment' => array(
            'horizontal' => Alignment::HORIZONTAL_LEFT,
            'vertical' => Alignment::VERTICAL_CENTER,
        ),
        'borders' => array(
            'outline' => array(
                'borderStyle' => Border::BORDER_THIN,
            ),
        ),
    );

    $sheet->setCellValue('A1', html_entity_decode('COVID-19 - Testing Target ', ENT_QUOTES, 'UTF-8'));
    $sheet->setCellValue('A4', html_entity_decode('Facility Name', ENT_QUOTES, 'UTF-8'));
    $sheet->setCellValue('B4', html_entity_decode('Month', ENT_QUOTES, 'UTF-8'));
    $sheet->setCellValue('C4', html_entity_decode('Number of Samples Received', ENT_QUOTES, 'UTF-8'));
    $sheet->setCellValue('D4', html_entity_decode('Number of Samples Rejected', ENT_QUOTES, 'UTF-8'));
    $sheet->setCellValue('E4', html_entity_decode('Number of Samples Tested', ENT_QUOTES, 'UTF-8'));
    $sheet->setCellValue('F4', html_entity_decode('Monthly Test Target', ENT_QUOTES, 'UTF-8'));
    $cnt = 4;
    foreach ($res as $resultData) {
        foreach ($resultData as $rowData) {
            if ($_POST['targetType'] == 1) {
                if ($rowData['monthly_target'] > $rowData['totalCollected']) {
                    $cnt++;
                    $sheet->setCellValue('A' . $cnt, html_entity_decode(($rowData['facility_name']), ENT_QUOTES, 'UTF-8'));
                    $sheet->setCellValue('B' . $cnt, html_entity_decode($rowData['monthrange'], ENT_QUOTES, 'UTF-8'));
                    $sheet->setCellValue('C' . $cnt, html_entity_decode($rowData['totalReceived'], ENT_QUOTES, 'UTF-8'));
                    $sheet->setCellValue('D' . $cnt, html_entity_decode($rowData['totalRejected'], ENT_QUOTES, 'UTF-8'));
                    $sheet->setCellValue('E' . $cnt, html_entity_decode($rowData['totalCollected'], ENT_QUOTES, 'UTF-8'));
                    $sheet->setCellValue('F' . $cnt, html_entity_decode($rowData['monthly_target'], ENT_QUOTES, 'UTF-8'));
                }
            } elseif ($_POST['targetType'] == 2) {
                if ($rowData['monthly_target'] < $rowData['totalCollected']) {
                    $cnt++;
                    $sheet->setCellValue('A' . $cnt, html_entity_decode(($rowData['facility_name']), ENT_QUOTES, 'UTF-8'));
                    $sheet->setCellValue('B' . $cnt, html_entity_decode($rowData['monthrange'], ENT_QUOTES, 'UTF-8'));
                    $sheet->setCellValue('C' . $cnt, html_entity_decode($rowData['totalReceived'], ENT_QUOTES, 'UTF-8'));
                    $sheet->setCellValue('D' . $cnt, html_entity_decode($rowData['totalRejected'], ENT_QUOTES, 'UTF-8'));
                    $sheet->setCellValue('E' . $cnt, html_entity_decode($rowData['totalCollected'], ENT_QUOTES, 'UTF-8'));
                    $sheet->setCellValue('F' . $cnt, html_entity_decode($rowData['monthly_target'], ENT_QUOTES, 'UTF-8'));
                }
            } else {
                $cnt++;
                $sheet->setCellValue('A' . $cnt, html_entity_decode(($rowData['facility_name']), ENT_QUOTES, 'UTF-8'));
                $sheet->setCellValue('B' . $cnt, html_entity_decode($rowData['monthrange'], ENT_QUOTES, 'UTF-8'));
                $sheet->setCellValue('C' . $cnt, html_entity_decode($rowData['totalReceived'], ENT_QUOTES, 'UTF-8'));
                $sheet->setCellValue('D' . $cnt, html_entity_decode($rowData['totalRejected'], ENT_QUOTES, 'UTF-8'));
                $sheet->setCellValue('E' . $cnt, html_entity_decode($rowData['totalCollected'], ENT_QUOTES, 'UTF-8'));
                $sheet->setCellValue('F' . $cnt, html_entity_decode($rowData['monthly_target'], ENT_QUOTES, 'UTF-8'));
            }
        }
    }

    $writer = IOFactory::createWriter($excel, 'Xlsx');
    $filename = 'VLSM-covid19-Testing-Target-Report-' . date('d-M-Y-H-i-s') . '.xlsx';
    $writer->save(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
    echo base64_encode(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
}
