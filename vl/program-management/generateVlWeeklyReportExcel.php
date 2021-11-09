<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
ob_start();
#require_once('../../startup.php');   

$general = new \Vlsm\Models\General();
$configQuery = "SELECT * from global_config where name='vl_form'";
$configResult = $db->query($configQuery);
$country = $configResult[0]['value'];
if (isset($_POST['reportedDate']) && trim($_POST['reportedDate']) != '') {
    $s_t_date = explode("to", $_POST['reportedDate']);
    if (isset($s_t_date[0]) && trim($s_t_date[0]) != "") {
        $start_date = $general->dateFormat(trim($s_t_date[0]));
    }
    if (isset($s_t_date[1]) && trim($s_t_date[1]) != "") {
        $end_date = $general->dateFormat(trim($s_t_date[1]));
    }
}
// if(isset($_POST['collectionDate']) && trim($_POST['collectionDate'])!= ''){
//   $s_t_date = explode("to", $_POST['collectionDate']);
//   if (isset($s_t_date[0]) && trim($s_t_date[0]) != "") {
//     $collection_start_date = $general->dateFormat(trim($s_t_date[0]));
//   }
//   if (isset($s_t_date[1]) && trim($s_t_date[1]) != "") {
//     $collection_end_date = $general->dateFormat(trim($s_t_date[1]));
//   }
// }

$systemConfigQuery = "SELECT * from system_config";
$systemConfigResult = $db->query($systemConfigQuery);
$sarr = array();
// now we create an associative array so that we can easily create view variables
for ($i = 0; $i < sizeof($systemConfigResult); $i++) {
    $sarr[$systemConfigResult[$i]['name']] = $systemConfigResult[$i]['value'];
}

//excel code start
$excel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$sheet = $excel->getActiveSheet();
$headingStyle = array(
    'font' => array(
        'bold' => true,
        'size' => '11',
    ),
    'alignment' => array(
        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
    ),
);
$backgroundTitleStyle = array(
    'font' => array(
        'bold' => true,
        'size' => '11',
    ),
    'alignment' => array(
        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
    ),
    'fill' => array(
        'type' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
        'color' => array('rgb' => 'FFFF00'),
    ),
);
$backgroundFieldStyle = array(
    'font' => array(
        'bold' => false,
        'size' => '11',
    ),
    'alignment' => array(
        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
    ),
);
$styleArray = array(
    'font' => array(
        'bold' => true,
        'size' => '13',
    ),
    'alignment' => array(
        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
        'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
        'wrapText' => true,
    ),
    'borders' => array(
        'outline' => array(
            'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
        ),
    ),
);

$borderStyle = array(
    'alignment' => array(
        //  'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
    ),
    'borders' => array(
        'outline' => array(
            'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
        ),
    ),
);

if ($sarr['sc_user_type'] == 'vluser') {
    $vlLabQuery = "SELECT * FROM facility_details where status='active' AND facility_id = " . $sarr['sc_testing_lab_id'];
    $vlLabResult = $db->rawQuery($vlLabQuery);
} else if (isset($_POST['lab']) && trim($_POST['lab']) != '') {
    $vlLabQuery = "SELECT * FROM facility_details where facility_id IN (" . $_POST['lab'] . ") AND status='active'";
    $vlLabResult = $db->rawQuery($vlLabQuery);
} else {
    $vlLabQuery = "SELECT * FROM facility_details where facility_type = 2 AND status='active'";
    $vlLabResult = $db->rawQuery($vlLabQuery);
}
//echo $vlLabQuery;die;

//Statistics sheet start
$sheet->mergeCells('A2:A3');
$sheet->mergeCells('B2:B3');
$sheet->mergeCells('C2:C3');
$sheet->mergeCells('D2:D3');
$sheet->mergeCells('E2:E3');
$sheet->mergeCells('F2:F3');
$sheet->mergeCells('G2:H2');
$sheet->mergeCells('I2:L2');
$sheet->mergeCells('M2:N2');
$sheet->mergeCells('O2:P2');
$sheet->mergeCells('Q2:R2');
$sheet->mergeCells('S2:S3');
$sheet->mergeCells('T2:T3');

$totQuery = "SELECT
 SUM(
   CASE
      WHEN(DATE(sample_collection_date) >= '" . $collection_start_date . "' AND DATE(sample_collection_date) <= '" . $collection_end_date . "') THEN 1 ELSE 0 END) AS collectCount,
 SUM(CASE  WHEN (DATE(sample_collection_date) <= '" . date('Y-m-d') . "') THEN 1 ELSE 0 END) AS totalCount FROM vl_request_form as vl  WHERE  vl.vlsm_country_id = " . $country;
$totalResult = $db->rawQuery($totQuery);
$c = 0;
foreach ($vlLabResult as $vlLab) {
    $sQuery = "SELECT
		 vl.facility_id,f.facility_code,f.facility_state,f.facility_district,f.facility_name,

		SUM(CASE
			WHEN (reason_for_sample_rejection IS NOT NULL AND reason_for_sample_rejection!= '' AND reason_for_sample_rejection!= 0) THEN 1
		             ELSE 0
		           END) AS rejections,
		SUM(CASE
			WHEN ((patient_age_in_years >= 0 AND patient_age_in_years <= 15) AND ((vl.result < 1000 or vl.result = 'Target Not Detected' or vl.result = 'TND' or vl.result = 'tnd' or vl.result= 'Below Detection Level' or vl.result='BDL' or vl.result='bdl' or vl.result= 'Low Detection Level' or vl.result='LDL' or vl.result='ldl') AND vl.result IS NOT NULL AND vl.result!= '' AND vl.result!='Failed' AND vl.result!='failed' AND vl.result!='Fail' AND vl.result!='fail' AND vl.result!='No Sample' AND vl.result!='no sample' AND sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00')) THEN 1
		             ELSE 0
		           END) AS lt15lt1000,
		SUM(CASE
             WHEN ((patient_age_in_years >= 0 AND patient_age_in_years <= 15) AND vl.result IS NOT NULL AND vl.result!= '' AND vl.result >= 1000 AND vl.result!='Failed' AND vl.result!='failed' AND vl.result!='Fail' AND vl.result!='fail' AND vl.result!='No Sample' AND vl.result!='no sample' AND sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00') THEN 1
             ELSE 0
           END) AS lt15gt1000,
		SUM(CASE
             WHEN (patient_age_in_years > 15 AND patient_gender IN ('m','male','M','MALE') AND ((vl.result < 1000 or vl.result = 'Target Not Detected' or vl.result = 'TND' or vl.result = 'tnd' or vl.result= 'Below Detection Level' or vl.result='BDL' or vl.result='bdl' or vl.result= 'Low Detection Level' or vl.result='LDL' or vl.result='ldl') AND vl.result IS NOT NULL AND vl.result!= '' AND vl.result!='Failed' AND vl.result!='failed' AND vl.result!='Fail' AND vl.result!='fail' AND vl.result!='No Sample' AND vl.result!='no sample' AND sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00')) THEN 1
             ELSE 0
           END) AS gt15lt1000M,
		SUM(CASE
             WHEN (patient_age_in_years > 15 AND patient_gender IN ('m','male','M','MALE') AND vl.result IS NOT NULL AND vl.result!= '' AND vl.result >= 1000 AND vl.result!='Failed' AND vl.result!='failed' AND vl.result!='Fail' AND vl.result!='fail' AND vl.result!='No Sample' AND vl.result!='no sample' AND sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00') THEN 1
             ELSE 0
           END) AS gt15gt1000M,
		SUM(CASE
             WHEN (patient_age_in_years > 15 AND patient_gender IN ('f','female','F','FEMALE') AND ((vl.result < 1000 or vl.result = 'Target Not Detected' or vl.result = 'TND' or vl.result = 'tnd' or vl.result= 'Below Detection Level' or vl.result='BDL' or vl.result='bdl' or vl.result= 'Low Detection Level' or vl.result='LDL' or vl.result='ldl') AND vl.result IS NOT NULL AND vl.result!= '' AND vl.result!='Failed' AND vl.result!='failed' AND vl.result!='Fail' AND vl.result!='fail' AND vl.result!='No Sample' AND vl.result!='no sample' AND sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00')) THEN 1
             ELSE 0
           END) AS gt15lt1000F,
		SUM(CASE
             WHEN (patient_age_in_years > 15 AND patient_gender IN ('f','female','F','FEMALE') AND vl.result IS NOT NULL AND vl.result!= '' AND vl.result >= 1000 AND vl.result!='Failed' AND vl.result!='failed' AND vl.result!='Fail' AND vl.result!='fail' AND vl.result!='No Sample' AND vl.result!='no sample' AND sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00') THEN 1
             ELSE 0
           END) AS gt15gt1000F,
		SUM(CASE
             WHEN ((is_patient_pregnant ='Yes' OR is_patient_pregnant ='YES' OR is_patient_pregnant ='yes' OR is_patient_breastfeeding ='Yes' OR is_patient_breastfeeding ='YES' OR is_patient_breastfeeding ='yes') AND ((vl.result < 1000 or vl.result = 'Target Not Detected' or vl.result = 'TND' or vl.result = 'tnd' or vl.result= 'Below Detection Level' or vl.result='BDL' or vl.result='bdl' or vl.result= 'Low Detection Level' or vl.result='LDL' or vl.result='ldl') AND vl.result IS NOT NULL AND vl.result!= '' AND vl.result!='Failed' AND vl.result!='failed' AND vl.result!='Fail' AND vl.result!='fail' AND vl.result!='No Sample' AND vl.result!='no sample' AND sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00')) THEN 1
             ELSE 0
           END) AS preglt1000,
		SUM(CASE
             WHEN ((is_patient_pregnant ='Yes' OR is_patient_pregnant ='YES' OR is_patient_pregnant ='yes' OR is_patient_breastfeeding ='Yes' OR is_patient_breastfeeding ='YES' OR is_patient_breastfeeding ='yes') AND vl.result IS NOT NULL AND vl.result!= '' AND vl.result >= 1000 AND vl.result!='Failed' AND vl.result!='failed' AND vl.result!='Fail' AND vl.result!='fail' AND vl.result!='No Sample' AND vl.result!='no sample' AND sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00') THEN 1
             ELSE 0
           END) AS preggt1000,
		SUM(CASE
             WHEN (((patient_age_in_years = '' OR patient_age_in_years is NULL) OR (patient_gender = '' OR patient_gender is NULL)) AND ((vl.result < 1000 or vl.result = 'Target Not Detected' or vl.result = 'TND' or vl.result = 'tnd' or vl.result= 'Below Detection Level' or vl.result='BDL' or vl.result='bdl' or vl.result= 'Low Detection Level' or vl.result='LDL' or vl.result='ldl') AND vl.result IS NOT NULL AND vl.result!= '' AND vl.result!='Failed' AND vl.result!='failed' AND vl.result!='Fail' AND vl.result!='fail' AND vl.result!='No Sample' AND vl.result!='no sample' AND sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00')) THEN 1
             ELSE 0
           END) AS ult1000,
		SUM(CASE
             WHEN (((patient_age_in_years = '' OR patient_age_in_years is NULL) OR (patient_gender = '' OR patient_gender is NULL)) AND vl.result IS NOT NULL AND vl.result!= '' AND vl.result >= 1000 AND vl.result!='Failed' AND vl.result!='failed' AND vl.result!='Fail' AND vl.result!='fail' AND vl.result!='No Sample' AND vl.result!='no sample' AND sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00') THEN 1
             ELSE 0
           END) AS ugt1000,
		SUM(CASE
             WHEN (((vl.result < 1000 or vl.result = 'Target Not Detected' or vl.result = 'TND' or vl.result = 'tnd' or vl.result= 'Below Detection Level' or vl.result='BDL' or vl.result='bdl' or vl.result= 'Low Detection Level' or vl.result='LDL' or vl.result='ldl') AND vl.result IS NOT NULL AND vl.result!= '' AND vl.result!='Failed' AND vl.result!='failed' AND vl.result!='Fail' AND vl.result!='fail' AND vl.result!='No Sample' AND vl.result!='no sample' AND sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00')) THEN 1
             ELSE 0
           END) AS totalLessThan1000,
		SUM(CASE
             WHEN ((vl.result IS NOT NULL AND vl.result!= '' AND vl.result >= 1000 AND vl.result!='Failed' AND vl.result!='failed' AND vl.result!='Fail' AND vl.result!='fail' AND vl.result!='No Sample' AND vl.result!='no sample' AND sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00')) THEN 1
             ELSE 0
           END) AS totalGreaterThan1000,
		COUNT(result) as total
		 FROM vl_request_form as vl RIGHT JOIN facility_details as f ON f.facility_id=vl.facility_id
       WHERE vl.lab_id = " . $vlLab['facility_id'] . " AND vl.vlsm_country_id = " . $country;

    if (isset($_POST['reportedDate']) && trim($_POST['reportedDate']) != '') {
        if (trim($start_date) == trim($end_date)) {
            $sQuery = $sQuery . ' AND DATE(vl.sample_tested_datetime) = "' . $start_date . '"';
        } else {
            $sQuery = $sQuery . ' AND DATE(vl.sample_tested_datetime) >= "' . $start_date . '" AND DATE(vl.sample_tested_datetime) <= "' . $end_date . '"';
        }
    }

    $sQuery = $sQuery . ' GROUP BY vl.facility_id';
    $sResult = $db->rawQuery($sQuery);
    //error_log($sQuery);
    $sheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($excel, '');
    $excel->addSheet($sheet, $c);
    $vlLab['facility_name'] = preg_replace('/\s+/', '', ($vlLab['facility_name']));
    $sheet->setTitle($vlLab['facility_name']);

    $sheet->setCellValue('B1', html_entity_decode('Reported Date ', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $sheet->setCellValue('C1', html_entity_decode($_POST['reportedDate'], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $sheet->setCellValue('D1', html_entity_decode('Lab Name ', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $sheet->setCellValue('E1', html_entity_decode(ucwords($vlLab['facility_name']), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    //$sheet->setCellValue('F1', html_entity_decode('Collection Date ' , ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    //$sheet->setCellValue('G1', html_entity_decode($_POST['collectionDate'] , ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $sheet->setCellValue('B2', html_entity_decode('Province/State ', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $sheet->setCellValue('C2', html_entity_decode('District/County ', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $sheet->setCellValue('D2', html_entity_decode('Site Name ', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $sheet->setCellValue('E2', html_entity_decode('Facility ID ', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $sheet->setCellValue('F2', html_entity_decode('No. of Rejections ', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $sheet->setCellValue('G2', html_entity_decode('Viral Load Result- Peds ', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $sheet->setCellValue('G3', html_entity_decode('<15 yrs <=1000 copies/ml ', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $sheet->setCellValue('H3', html_entity_decode('<15 yrs >1000 copies/ml ', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $sheet->setCellValue('I2', html_entity_decode('Viral Load Result- Adults ', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $sheet->setCellValue('I3', html_entity_decode('>15yrs Male <=1000 copies/ml ', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $sheet->setCellValue('J3', html_entity_decode('>15yrs Male >1000 copies/ml ', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $sheet->setCellValue('K3', html_entity_decode('>15yrs Female <=1000 copies/ml ', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $sheet->setCellValue('L3', html_entity_decode('>15yrs  Female >1000 copies/ml ', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $sheet->setCellValue('M2', html_entity_decode('Viral Load Results- Pregnant/Breastfeeding Women ', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $sheet->setCellValue('M3', html_entity_decode('<= 1000 copies/ml ', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $sheet->setCellValue('N3', html_entity_decode('> 1000 copies/ml ', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $sheet->setCellValue('O2', html_entity_decode('Age/Sex Unknown ', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $sheet->setCellValue('O3', html_entity_decode('Unknown Age/Sex <= 1000ml ', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $sheet->setCellValue('P3', html_entity_decode('Unknown Age/Sex > 1000ml ', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $sheet->setCellValue('Q2', html_entity_decode('Totals ', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $sheet->setCellValue('Q3', html_entity_decode('<= 1000 copies/ml ', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $sheet->setCellValue('R3', html_entity_decode('> 1000 copies/ml ', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $sheet->setCellValue('S2', html_entity_decode('Total Test per Clinic ', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $sheet->setCellValue('T2', html_entity_decode('Comments ', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

    $sheet->getStyle('B1')->applyFromArray($backgroundTitleStyle);
    $sheet->getStyle('C1')->applyFromArray($backgroundFieldStyle);
    $sheet->getStyle('D1')->applyFromArray($backgroundTitleStyle);
    $sheet->getStyle('E1')->applyFromArray($backgroundFieldStyle);
    //$sheet->getStyle('F1')->applyFromArray($backgroundTitleStyle);
    //$sheet->getStyle('G1')->applyFromArray($backgroundFieldStyle);
    $sheet->getStyle('B2:B3')->applyFromArray($styleArray);
    $sheet->getStyle('C2:C3')->applyFromArray($styleArray);
    $sheet->getStyle('D2:D3')->applyFromArray($styleArray);
    $sheet->getStyle('E2:E3')->applyFromArray($styleArray);
    $sheet->getStyle('F2:F3')->applyFromArray($styleArray);
    $sheet->getStyle('G2:H2')->applyFromArray($styleArray);
    $sheet->getStyle('G3')->applyFromArray($styleArray);
    $sheet->getStyle('H3')->applyFromArray($styleArray);
    $sheet->getStyle('I2:L2')->applyFromArray($styleArray);
    $sheet->getStyle('I3')->applyFromArray($styleArray);
    $sheet->getStyle('J3')->applyFromArray($styleArray);
    $sheet->getStyle('K3')->applyFromArray($styleArray);
    $sheet->getStyle('L3')->applyFromArray($styleArray);
    $sheet->getStyle('M2:N2')->applyFromArray($styleArray);
    $sheet->getStyle('M3')->applyFromArray($styleArray);
    $sheet->getStyle('N3')->applyFromArray($styleArray);
    $sheet->getStyle('O2:P2')->applyFromArray($styleArray);
    $sheet->getStyle('O3')->applyFromArray($styleArray);
    $sheet->getStyle('P3')->applyFromArray($styleArray);
    $sheet->getStyle('Q2:R2')->applyFromArray($styleArray);
    $sheet->getStyle('Q3')->applyFromArray($styleArray);
    $sheet->getStyle('R3')->applyFromArray($styleArray);
    $sheet->getStyle('S2:S3')->applyFromArray($styleArray);
    $sheet->getStyle('T2:T3')->applyFromArray($styleArray);

    $output = array();
    $r = 1;
    if (count($sResult) > 0) {
        foreach ($sResult as $aRow) {
            $row = array();
            $row[] = $r;
            $row[] = ucwords($aRow['facility_state']);
            $row[] = ucwords($aRow['facility_district']);
            $row[] = ucwords($aRow['facility_name']);
            $row[] = $aRow['facility_code'];
            $row[] = $aRow['rejections'];
            $row[] = $aRow['lt15lt1000'];
            $row[] = $aRow['lt15gt1000'];
            $row[] = $aRow['gt15lt1000M'];
            $row[] = $aRow['gt15gt1000M'];
            $row[] = $aRow['gt15lt1000F'];
            $row[] = $aRow['gt15gt1000F'];
            $row[] = $aRow['preglt1000'];
            $row[] = $aRow['preggt1000'];
            $row[] = $aRow['ult1000'];
            $row[] = $aRow['ugt1000'];
            $row[] = $aRow['totalLessThan1000'];
            $row[] = $aRow['totalGreaterThan1000'];
            $row[] = $aRow['total'];
            $row[] = '';
            $output[] = $row;
            $r++;
        }
    } else {
        $row = array();
        $row[] = '';
        $row[] = '';
        $row[] = '';
        $row[] = '';
        $row[] = '';
        $row[] = '';
        $row[] = '';
        $row[] = '';
        $row[] = '';
        $row[] = '';
        $row[] = '';
        $row[] = '';
        $row[] = '';
        $row[] = '';
        $row[] = '';
        $row[] = '';
        $row[] = '';
        $row[] = '';
        $row[] = '';
        $row[] = '';
        $output[] = $row;
        $r++;
    }

    $start = (count($output));
    //$sheet->getDefaultColumnDimension()->setWidth(20);
    foreach ($output as $rowNo => $rowData) {
        $colNo = 1;
        foreach ($rowData as $field => $value) {
            $rRowCount = $rowNo + 4;
            $cellName = $sheet->getCellByColumnAndRow($colNo, $rRowCount)->getColumn();
            $sheet->getStyle($cellName . $rRowCount)->applyFromArray($borderStyle);
            $sheet->getStyle($cellName . $start)->applyFromArray($borderStyle);
            //$sheet->getDefaultRowDimension()->setRowHeight(15);
            if ($colNo > 5 && $colNo <= 19) {
                $cellDataType = \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC;
            } else {
                $cellDataType = \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING;
            }
            $sheet->getCellByColumnAndRow($colNo, $rowNo + 4)->setValueExplicit(html_entity_decode($value), $cellDataType);
            $sheet->getStyleByColumnAndRow($colNo, $rowNo + 4)->getAlignment()->setWrapText(true);
            $colNo++;
        }
    }
    //$firstRowCount = $rRowCount+1;
    //$secondRowCount = $rRowCount+2;
    //$firstCell = $sheet->getCellByColumnAndRow(1, $firstRowCount)->getColumn();
    //$secondCell = $sheet->getCellByColumnAndRow(2, $firstRowCount)->getColumn();
    //$sheet->getStyle($firstCell.$firstRowCount.':'.$firstCell.$firstRowCount)->applyFromArray($styleArray);
    //$sheet->getStyle($secondCell.$firstRowCount.':'.$secondCell.$firstRowCount)->applyFromArray($styleArray);
    //$sheet->setCellValue($firstCell.$firstRowCount, html_entity_decode("Total Sample As On ".$s_t_date[0], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    //$sheet->setCellValue($secondCell.$firstRowCount, html_entity_decode($totalResult[0]['totalCount'], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

    //$firstCell = $sheet->getCellByColumnAndRow(1, $secondRowCount)->getColumn();
    //$secondCell = $sheet->getCellByColumnAndRow(2, $secondRowCount)->getColumn();
    //$sheet->getStyle($firstCell.$secondRowCount.':'.$firstCell.$secondRowCount)->applyFromArray($styleArray);
    //$sheet->getStyle($secondCell.$secondRowCount.':'.$secondCell.$secondRowCount)->applyFromArray($styleArray);
    //$sheet->setCellValue($firstCell.$secondRowCount, html_entity_decode("Samples Collected Between ".$_POST['collectionDate'], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    //$sheet->setCellValue($secondCell.$secondRowCount, html_entity_decode($totalResult[0]['collectCount'], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $c++;
}
//Statistics sheet end
if ($c > 0) {
    //Super lab performance sheet end
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($excel, 'Xlsx');
    $filename = 'VLSM-VL-Lab-Weekly-Report-' . date('d-M-Y-H-i-s') . '.xlsx';
    $writer->save(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
    echo $filename;
} else {
    echo '';
}
