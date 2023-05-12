<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
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
$formConfigQuery = "SELECT * from global_config where name='vl_form'";
$configResult = $db->query($formConfigQuery);
$arr = [];
// now we create an associative array so that we can easily create view variables
for ($i = 0; $i < sizeof($configResult); $i++) {
    $arr[$configResult[$i]['name']] = $configResult[$i]['value'];
}
if (isset($_SESSION['vlMonitoringThresholdReportQuery']) && trim($_SESSION['vlMonitoringThresholdReportQuery']) != "") {
    $rResult = $db->rawQuery($_SESSION['vlMonitoringThresholdReportQuery']);

    $res = [];
    foreach ($rResult as $aRow) {   
        $row = [];
        if( isset($res[$aRow['facility_id']]))
        {
            if(isset($res[$aRow['facility_id']][$aRow['monthrange']]))
            {
                if(trim($aRow['is_sample_rejected'])  == 'yes')
                        $row['totalRejected'] = $res[$aRow['facility_id']][$aRow['monthrange']]['totalRejected']  + 1;
                else
                        $row['totalRejected'] = $res[$aRow['facility_id']][$aRow['monthrange']]['totalRejected'];
                if(trim($aRow['sample_tested_datetime'])  == null  && trim($aRow['sample_collection_date']) != '')
                        $row['totalReceived'] = $res[$aRow['facility_id']][$aRow['monthrange']]['totalReceived']  + 1;
                else
                        $row['totalReceived'] = $res[$aRow['facility_id']][$aRow['monthrange']]['totalReceived'];
                $row['facility_name'] = ($aRow['facility_name']);
                $row['monthrange'] = $aRow['monthrange'];
                    $row['monthly_target'] = $aRow['monthly_target'];
                $row['totalCollected'] = $res[$aRow['facility_id']][$aRow['monthrange']]['totalCollected']  + 1;
                $res[$aRow['facility_id']][$aRow['monthrange']] = $row;
            }
            else
            {
                if(trim($aRow['is_sample_rejected'])  == 'yes')
                        $row['totalRejected'] = $res[$aRow['facility_id']][$aRow['monthrange']]['totalRejected']  + 1;
                else
                        $row['totalRejected'] = 0;
                if(trim($aRow['sample_tested_datetime'])  == null  && trim($aRow['sample_collection_date']) != '')
                        $row['totalReceived'] = $res[$aRow['facility_id']][$aRow['monthrange']]['totalReceived']  + 1;
                else
                        $row['totalReceived'] = 0;
                $row['facility_name'] = ($aRow['facility_name']);
                $row['monthrange'] = $aRow['monthrange'];
                    $row['monthly_target'] = $aRow['monthly_target'];
                $row['totalCollected'] = $res[$aRow['facility_id']][$aRow['monthrange']]['totalCollected']  + 1;
                $res[$aRow['facility_id']][$aRow['monthrange']] = $row;
            }
        }
        else
        {
            if(trim($aRow['is_sample_rejected'])  == 'yes')
                        $row['totalRejected'] = $res[$aRow['facility_id']][$aRow['monthrange']]['totalRejected']  + 1;
                else
                        $row['totalRejected'] = 0;
            if(trim($aRow['sample_tested_datetime'])  == null  && trim($aRow['sample_collection_date']) != '')
                        $row['totalReceived'] = $res[$aRow['facility_id']][$aRow['monthrange']]['totalReceived']  + 1;
                else
                        $row['totalReceived'] = 0;
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

    $borderStyle = array(
        //'alignment' => array(
        //    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
        //),
        'borders' => array(
            'outline' => array(
                'borderStyle' => Border::BORDER_THICK,
            ),
        ),
    );
    $sheet->getStyle('A1')->applyFromArray($headingStyle);
    $sheet->getStyle('A1')->applyFromArray($backgroundStyle);
    $sheet->getStyle('A3')->applyFromArray($styleArray);
    $sheet->mergeCells('A1:F2');
    $sheet->setCellValue('A1', html_entity_decode('Viral Load Testing Target ', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    // $sheet->mergeCells('A3:M10');
    $sheet->setCellValue('A4', html_entity_decode('Facility Name', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    // $sheet->mergeCells('A11:A12');
    // $sheet->mergeCells('B11:F12');
    $sheet->setCellValue('B4', html_entity_decode('Month', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    // $sheet->mergeCells('G11:I12');
    $sheet->setCellValue('C4', html_entity_decode('Number of Samples Received', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    $sheet->setCellValue('D4', html_entity_decode('Number of Samples Rejected', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    // $sheet->mergeCells('J11:M12');
    $sheet->setCellValue('E4', html_entity_decode('Number of Samples Tested', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    $sheet->setCellValue('F4', html_entity_decode('Monthly Test Target', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    $cnt = 4;
    foreach($res as $resultData)
    {
        foreach($resultData as $rowData)
        {
            if($_POST['targetType'] == 1)
            { 
                if($rowData['monthly_target'] > $rowData['totalCollected'])
                { 
                    // print_r("Prasath");die;
                    $cnt++;
                    //    $data = [];
                    //    $data[] = ($rowData['facility_name']);
                    //    $data[] = $rowData['monthrange'];
                    //    $data[] = $rowData['totalReceived'];
                    //    $data[] = $rowData['totalRejected'];
                    //    $data[] = $rowData['totalCollected'];
                    //    $data[] = $rowData['monthly_target'];
                    //    // print_r($data);die;
                    //    $output['aaData'][] = $data;
                    // $sheet->mergeCells('A3:M10');
                    $sheet->setCellValue('A'.$cnt, html_entity_decode(($rowData['facility_name']), ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
                    // $sheet->mergeCells('A11:A12');
                    // $sheet->mergeCells('B11:F12');
                    $sheet->setCellValue('B'.$cnt, html_entity_decode($rowData['monthrange'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
                    // $sheet->mergeCells('G11:I12');
                    $sheet->setCellValue('C'.$cnt, html_entity_decode($rowData['totalReceived'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_NUMERIC);
                    $sheet->setCellValue('D'.$cnt, html_entity_decode($rowData['totalRejected'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_NUMERIC);
                    // $sheet->mergeCells('J11:M12');
                    $sheet->setCellValue('E'.$cnt, html_entity_decode($rowData['totalCollected'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_NUMERIC);
                    $sheet->setCellValue('F'.$cnt, html_entity_decode($rowData['monthly_target'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_NUMERIC);
                }
            }
            else if($_POST['targetType'] == 2)
            { 
                if($rowData['monthly_target'] < $rowData['totalCollected'])
                { 
                    // print_r("Prasath");die;
                    $cnt++;
                    //    $data = [];
                    //    $data[] = ($rowData['facility_name']);
                    //    $data[] = $rowData['monthrange'];
                    //    $data[] = $rowData['totalReceived'];
                    //    $data[] = $rowData['totalRejected'];
                    //    $data[] = $rowData['totalCollected'];
                    //    $data[] = $rowData['monthly_target'];
                    //    // print_r($data);die;
                    //    $output['aaData'][] = $data;
                    // $sheet->mergeCells('A3:M10');
                    $sheet->setCellValue('A'.$cnt, html_entity_decode(($rowData['facility_name']), ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
                    // $sheet->mergeCells('A11:A12');
                    // $sheet->mergeCells('B11:F12');
                    $sheet->setCellValue('B'.$cnt, html_entity_decode($rowData['monthrange'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
                    // $sheet->mergeCells('G11:I12');
                    $sheet->setCellValue('C'.$cnt, html_entity_decode($rowData['totalReceived'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_NUMERIC);
                    $sheet->setCellValue('D'.$cnt, html_entity_decode($rowData['totalRejected'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_NUMERIC);
                    // $sheet->mergeCells('J11:M12');
                    $sheet->setCellValue('E'.$cnt, html_entity_decode($rowData['totalCollected'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_NUMERIC);
                    $sheet->setCellValue('F'.$cnt, html_entity_decode($rowData['monthly_target'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_NUMERIC);
                }
            }
            else 
            { 
                $cnt++;
                //    $data = [];
                //    $data[] = ($rowData['facility_name']);
                //    $data[] = $rowData['monthrange'];
                //    $data[] = $rowData['totalReceived'];
                //    $data[] = $rowData['totalRejected'];
                //    $data[] = $rowData['totalCollected'];
                //    $data[] = $rowData['monthly_target'];
                //    // print_r($data);die;
                //    $output['aaData'][] = $data;
                // $sheet->mergeCells('A3:M10');
                $sheet->setCellValue('A'.$cnt, html_entity_decode(($rowData['facility_name']), ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
                // $sheet->mergeCells('A11:A12');
                // $sheet->mergeCells('B11:F12');
                $sheet->setCellValue('B'.$cnt, html_entity_decode($rowData['monthrange'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
                // $sheet->mergeCells('G11:I12');
                $sheet->setCellValue('C'.$cnt, html_entity_decode($rowData['totalReceived'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_NUMERIC);
                $sheet->setCellValue('D'.$cnt, html_entity_decode($rowData['totalRejected'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_NUMERIC);
                // $sheet->mergeCells('J11:M12');
                $sheet->setCellValue('E'.$cnt, html_entity_decode($rowData['totalCollected'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_NUMERIC);
                $sheet->setCellValue('F'.$cnt, html_entity_decode($rowData['monthly_target'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_NUMERIC);
            }
        }

} 
    // $sheet->getStyle('B11')->applyFromArray($backgroundStyle);
    // $sheet->getStyle('G11')->applyFromArray($backgroundStyle);
    // $sheet->getStyle('J11')->applyFromArray($backgroundStyle);
    // $sheet->getStyle('A11:M12')->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
    //question one start
    
    $writer = IOFactory::createWriter($excel, 'Xls');
    $filename = 'VLSM-Vl-Testing-Target-Report-' . date('d-M-Y-H-i-s') . '.xls';
    ob_end_clean();
    $writer->save(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
    echo $filename;
}
