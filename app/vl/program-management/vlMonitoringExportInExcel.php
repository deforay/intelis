<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;
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

if (isset($_SESSION['vlMonitoringResultQuery']) && trim($_SESSION['vlMonitoringResultQuery']) != "") {
    $rResult = $db->rawQuery($_SESSION['vlMonitoringResultQuery']);

    //get current quarter total samples tested
    $sQuery = "SELECT vl.facility_id,f.facility_name,f.facility_code,f.facility_state,f.facility_district,

        SUM(CASE WHEN (vl_result_category = 'suppressed') THEN 1 ELSE 0 END) AS suppressed,
        SUM(CASE WHEN (vl_result_category = 'not suppressed') THEN 1 ELSE 0 END) AS NotSuppressed,
        SUM(CASE WHEN (vl_result_category = 'suppressed' AND (patient_gender='male' OR patient_gender='MALE')) THEN 1 ELSE 0 END) AS ltMale1000,
        SUM(CASE WHEN (vl_result_category = 'not suppressed' AND (patient_gender='male' OR patient_gender='MALE')) THEN 1 ELSE 0 END) AS gtMale1000,
        SUM(CASE WHEN (vl_result_category = 'suppressed' AND (patient_gender='female' OR patient_gender='FEMALE')) THEN 1 ELSE 0 END) AS ltFemale1000,
        SUM(CASE WHEN (vl_result_category = 'not suppressed' AND (patient_gender='female' OR patient_gender='FEMALE')) THEN 1 ELSE 0 END) AS gtFemale1000,
        SUM(CASE WHEN (vl_result_category = 'suppressed' AND (patient_gender='not_specified')) THEN 1 ELSE 0 END) AS ltNotSpecified1000,
        SUM(CASE WHEN (vl_result_category = 'not suppressed' AND (patient_gender='not_specified')) THEN 1 ELSE 0 END) AS gtNotSpecified1000,
        SUM(CASE WHEN (vl_result_category = 'suppressed' AND patient_gender!='') THEN 1 ELSE 0 END) AS ltTotalGender1000,
        SUM(CASE WHEN (vl_result_category = 'not suppressed' AND patient_gender!='') THEN 1 ELSE 0 END) AS gtTotalGender1000,
        SUM(CASE WHEN (patient_age_in_years ='' AND patient_age_in_months<=12 AND vl_result_category = 'suppressed') THEN 1 ELSE 0 END) AS ltAgeOne1000,
        SUM(CASE WHEN (patient_age_in_years ='' AND patient_age_in_months<=12 AND vl_result_category = 'not suppressed') THEN 1 ELSE 0 END) AS gtAgeOne1000,
        SUM(CASE WHEN (patient_age_in_years >= 1 AND patient_age_in_years<=9 AND vl_result_category = 'suppressed') THEN 1 ELSE 0 END) AS ltAgeOnetoNine1000,
        SUM(CASE WHEN (patient_age_in_years >= 1 AND patient_age_in_years<=9 AND vl_result_category = 'not suppressed') THEN 1 ELSE 0 END) AS gtAgeOnetoNine1000,
        SUM(CASE WHEN (patient_age_in_years >= 10 AND patient_age_in_years<=14 AND vl_result_category = 'suppressed') THEN 1 ELSE 0 END) AS ltAgeTentoFourteen1000,
        SUM(CASE WHEN (patient_age_in_years >= 10 AND patient_age_in_years<=14 AND vl_result_category = 'not suppressed') THEN 1 ELSE 0 END) AS gtAgeTentoFourteen1000,
        SUM(CASE WHEN (patient_age_in_years<=15 AND vl_result_category = 'suppressed') THEN 1 ELSE 0 END) AS ltAgeTotalFifteen1000,
        SUM(CASE WHEN (patient_age_in_years<=15 AND vl_result_category = 'not suppressed') THEN 1 ELSE 0 END) AS gtAgeTotalFifteen1000,
        SUM(CASE WHEN (patient_age_in_years >= 15 AND patient_age_in_years<=19 AND vl_result_category = 'suppressed') THEN 1 ELSE 0 END) AS ltAgeFifteentoNineteen1000,
        SUM(CASE WHEN (patient_age_in_years >= 15 AND patient_age_in_years<=19 AND vl_result_category = 'not suppressed') THEN 1 ELSE 0 END) AS gtAgeFifteentoNineteen1000,
        SUM(CASE WHEN (patient_age_in_years >= 20 AND patient_age_in_years<=24 AND vl_result_category = 'suppressed') THEN 1 ELSE 0 END) AS ltAgeTwentytoTwentyFour1000,
        SUM(CASE WHEN (patient_age_in_years >= 20 AND patient_age_in_years<=24 AND vl_result_category = 'not suppressed') THEN 1 ELSE 0 END) AS gtAgeTwentytoTwentyFour1000,
        SUM(CASE WHEN (patient_age_in_years >= 15 AND patient_age_in_years<=24 AND vl_result_category = 'suppressed') THEN 1 ELSE 0 END) AS ltAgeFifteentoTwentyFour1000,
        SUM(CASE WHEN (patient_age_in_years >= 15 AND patient_age_in_years<=24 AND vl_result_category = 'not suppressed') THEN 1 ELSE 0 END) AS gtAgeFifteentoTwentyFour1000,
        SUM(CASE WHEN (patient_age_in_years >= 25 AND vl_result_category = 'suppressed') THEN 1 ELSE 0 END) AS ltAgetwentyFive1000,
        SUM(CASE WHEN (patient_age_in_years >= 25 AND vl_result_category = 'not suppressed') THEN 1 ELSE 0 END) AS gtAgetwentyFive1000,
        SUM(CASE WHEN (patient_age_in_years is NULL AND vl_result_category = 'suppressed') THEN 1 ELSE 0 END) AS ltAgeNotSpecified1000,
        SUM(CASE WHEN (patient_age_in_years is NULL AND vl_result_category = 'not suppressed') THEN 1 ELSE 0 END) AS gtAgeNotSpecified1000,
        SUM(CASE WHEN (vl_result_category = 'suppressed') THEN 1 ELSE 0 END) AS ltAgeTotal1000,
        SUM(CASE WHEN (vl_result_category = 'not suppressed') THEN 1 ELSE 0 END) AS gtAgeTotal1000,
        SUM(CASE WHEN (is_patient_pregnant = 'yes' AND vl_result_category = 'suppressed') THEN 1 ELSE 0 END) AS ltPatientPregnant1000,
        SUM(CASE WHEN (patient_age_in_years = 'yes' AND vl_result_category = 'not suppressed') THEN 1 ELSE 0 END) AS gtPatientPregnant1000,
        SUM(CASE WHEN (is_patient_breastfeeding = 'yes' AND vl_result_category = 'suppressed') THEN 1 ELSE 0 END) AS ltPatientBreastFeeding1000,
        SUM(CASE WHEN (is_patient_breastfeeding = 'yes' AND vl_result_category = 'not suppressed') THEN 1 ELSE 0 END) AS gtPatientBreastFeeding1000
		FROM form_vl as vl JOIN facility_details as f ON vl.facility_id=f.facility_id
        WHERE reason_for_vl_testing != 9999";
    $start_date = '';
    $end_date = '';
    if (isset($_POST['sampleCollectionDate']) && trim($_POST['sampleCollectionDate']) != '') {
        $s_c_date = explode("to", $_POST['sampleCollectionDate']);
        if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
            $start_date = DateUtility::isoDateFormat(trim($s_c_date[0]));
        }
        if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
            $end_date = DateUtility::isoDateFormat(trim($s_c_date[1]));
        }
    }
    $sTestDate = '';
    $eTestDate = '';
   /* if (isset($_POST['sampleTestDate']) && trim($_POST['sampleTestDate']) != '') {
        $s_t_date = explode("to", $_POST['sampleTestDate']);
        if (isset($s_t_date[0]) && trim($s_t_date[0]) != "") {
            $sTestDate = \App\Utilities\DateUtility::isoDateFormat(trim($s_t_date[0]));
        }
        if (isset($s_t_date[1]) && trim($s_t_date[1]) != "") {
            $eTestDate = \App\Utilities\DateUtility::isoDateFormat(trim($s_t_date[1]));
        }
    }*/

    $sWhere = '';
    if (isset($_POST['sampleCollectionDate']) && trim($_POST['sampleCollectionDate']) != '') {
        if (trim($start_date) == trim($end_date)) {
            $sWhere = ' DATE(vl.sample_collection_date) = "' . $start_date . '"';
        } else {
            $sWhere = $sWhere . ' AND DATE(vl.sample_collection_date) >= "' . $start_date . '" AND DATE(vl.sample_collection_date) <= "' . $end_date . '"';
        }
    }
   /* if (isset($_POST['sampleTestDate']) && trim($_POST['sampleTestDate']) != '') {
        if (trim($sTestDate) == trim($eTestDate)) {
            $sWhere = $sWhere . ' AND DATE(vl.sample_tested_datetime) = "' . $sTestDate . '"';
        } else {
            $sWhere = $sWhere . ' AND DATE(vl.sample_tested_datetime) >= "' . $sTestDate . '" AND DATE(vl.sample_tested_datetime) <= "' . $eTestDate . '"';
        }
    }*/
    if (isset($_POST['district']) && trim($_POST['district']) != '') {
        $sWhere = $sWhere . " AND f.facility_district LIKE '%" . $_POST['district'] . "%' ";
    }
    if (isset($_POST['state']) && trim($_POST['state']) != '') {
        $sWhere = $sWhere . " AND f.facility_state LIKE '%" . $_POST['state'] . "%' ";
    }
    if (isset($_POST['facilityName']) && trim($_POST['facilityName']) != '') {
        $sWhere = $sWhere . ' AND f.facility_id = "' . $_POST['facilityName'] . '"';
    }
    $sQuery = $sQuery . ' ' . $sWhere . ' AND vl.result!=""';
  
    $sResult = $db->rawQuery($sQuery);

    //question two query
    //first check empty results
    $sWhere = 'where ';
    $checkEmptyResultQuery = "SELECT vl.sample_collection_date,
                                vl.sample_tested_datetime,
                                f.facility_name,
                                f.facility_code,
                                f.facility_state,
                                f.facility_district 
                                FROM form_vl as vl 
                                JOIN facility_details as f ON vl.facility_id=f.facility_id";
    if (isset($_POST['sampleCollectionDate']) && trim($_POST['sampleCollectionDate']) != '') {
        if (trim($start_date) == trim($end_date)) {
            $sWhere = ' DATE(vl.sample_collection_date) = "' . $start_date . '"';
        } else {
            $sWhere = $sWhere . ' DATE(vl.sample_collection_date) >= "' . $start_date . '" AND DATE(vl.sample_collection_date) <= "' . $end_date . '"';
        }
    }
    if (isset($_POST['district']) && trim($_POST['district']) != '') {
        $sWhere = $sWhere . " AND f.facility_district LIKE '%" . $_POST['district'] . "%' ";
    }
    if (isset($_POST['state']) && trim($_POST['state']) != '') {
        $sWhere = $sWhere . " AND f.facility_state LIKE '%" . $_POST['state'] . "%' ";
    }
    if (isset($_POST['facilityName']) && trim($_POST['facilityName']) != '') {
        $sWhere = $sWhere . ' AND f.facility_id = "' . $_POST['facilityName'] . '"';
    }
    $checkEmptyResultQuery = $checkEmptyResultQuery . ' ' . $sWhere . ' AND vl.sample_tested_datetime IS NULL AND vl.sample_type!="" AND vl.sample_collection_date < NOW() - INTERVAL 1 MONTH AND reason_for_vl_testing != 9999';
    $checkEmptyResult = $db->rawQuery($checkEmptyResultQuery);
    //get all sample type
    $sampleType = "Select * from r_vl_sample_type where status='active'";
    $sampleTypeResult = $db->rawQuery($sampleType);
    if (count($checkEmptyResult) > 0) {
        $sWhere = '';
        foreach ($sampleTypeResult as $sample) {
            $checkEmptyResultSampleQuery = 'SELECT vl.sample_collection_date,
                                                vl.sample_tested_datetime,
                                                COUNT(vl_sample_id) as total,
                                                f.facility_name,
                                                f.facility_code,
                                                f.facility_state,
                                                f.facility_district 
                                                FROM form_vl as vl 
                                                JOIN facility_details as f ON vl.facility_id=f.facility_id 
                                                WHERE vl.sample_tested_datetime IS NULL 
                                                AND vl.sample_type="' . $sample['sample_id'] . '" 
                                                AND vl.sample_collection_date < NOW() - INTERVAL 1 MONTH
                                                AND reason_for_vl_testing != 9999';

            if (isset($_POST['sampleCollectionDate']) && trim($_POST['sampleCollectionDate']) != '') {
                if (trim($start_date) == trim($end_date)) {
                    $sWhere = ' AND DATE(vl.sample_collection_date) = "' . $start_date . '"';
                } else {
                    $sWhere = $sWhere . ' AND DATE(vl.sample_collection_date) >= "' . $start_date . '" AND DATE(vl.sample_collection_date) <= "' . $end_date . '"';
                }
            }

            if (isset($_POST['district']) && trim($_POST['district']) != '') {
                $sWhere = $sWhere . " AND f.facility_district LIKE '%" . $_POST['district'] . "%' ";
            }
            if (isset($_POST['state']) && trim($_POST['state']) != '') {
                $sWhere = $sWhere . " AND f.facility_state LIKE '%" . $_POST['state'] . "%' ";
            }
            if (isset($_POST['facilityName']) && trim($_POST['facilityName']) != '') {
                $sWhere = $sWhere . ' AND f.facility_id = "' . $_POST['facilityName'] . '"';
            }
            $checkEmptyResultSampleQuery = $checkEmptyResultSampleQuery . $sWhere;
            $checkEmptySampleResult[$sample['sample_name']] = $db->rawQuery($checkEmptyResultSampleQuery);
        }
    }

    //question three
   
    $s_c_date = explode(" to ", $_POST['sampleCollectionDate']);
    $start_date = DateUtility::isoDateFormat(trim($s_c_date[0]));
    $end_date = DateUtility::isoDateFormat(trim($s_c_date[1]));
    $startMonth = date("Y-m", strtotime($start_date));
    $endMonth = date("Y-m", strtotime($end_date));
    $start = $month = strtotime($startMonth);
    $end = strtotime($endMonth);
    $i = 0;
    $j = 0;
    $avgResult = [];
    while ($month <= $end) {
        $sWhere = '';
        $mnth = date('m', $month);
        $year = date('Y', $month);
        $dFormat = date("M-Y", $month);
        $checkResultAvgQuery = "SELECT vl.sample_collection_date,
                                vl.sample_tested_datetime,
                                f.facility_name,
                                f.facility_code,
                                f.facility_state,
                                f.facility_district,
                                DATEDIFF(sample_tested_datetime,sample_collection_date) as diff 
                                FROM form_vl as vl 
                                JOIN facility_details as f ON vl.facility_id=f.facility_id 
                                WHERE vl.sample_tested_datetime IS NOT NULL 
                                AND MONTH(sample_collection_date)='$mnth' 
                                AND YEAR(sample_collection_date)='$year'";
        if (isset($_POST['sampleCollectionDate']) && trim($_POST['sampleCollectionDate']) != '') {
            if (trim($start_date) == trim($end_date)) {
                $sWhere = ' AND DATE(vl.sample_collection_date) = "' . $start_date . '"';
            } else {
                $sWhere = $sWhere . ' AND DATE(vl.sample_collection_date) >= "' . $start_date . '" AND DATE(vl.sample_collection_date) <= "' . $end_date . '"';
            }
        }
        if (isset($_POST['sampleTestDate']) && trim($_POST['sampleTestDate']) != '') {
            if (trim($sTestDate) == trim($eTestDate)) {
                $sWhere = $sWhere . ' AND DATE(vl.sample_tested_datetime) = "' . $sTestDate . '"';
            } else {
                $sWhere = $sWhere . ' AND DATE(vl.sample_tested_datetime) >= "' . $sTestDate . '" AND DATE(vl.sample_tested_datetime) <= "' . $eTestDate . '"';
            }
        }
        if (isset($_POST['district']) && trim($_POST['district']) != '') {
            $sWhere = $sWhere . " AND f.facility_district LIKE '%" . $_POST['district'] . "%' ";
        }
        if (isset($_POST['state']) && trim($_POST['state']) != '') {
            $sWhere = $sWhere . " AND f.facility_state LIKE '%" . $_POST['state'] . "%' ";
        }

        if (isset($_POST['facilityName']) && trim($_POST['facilityName']) != '') {
            $sWhere = $sWhere . ' AND f.facility_id = "' . $_POST['facilityName'] . '"';
        }
        $checkResultAvgQuery = $checkResultAvgQuery . ' ' . $sWhere . ' AND vl.result=""';
        $checkResultAvgResult = $db->rawQuery($checkResultAvgQuery);
        if (count($checkResultAvgResult) > 0) {
            $total = 0;
            foreach ($checkResultAvgResult as $data) {
                $total = $total + $data['diff'];
            }
            $avgResult[$j] = round($total / count($checkResultAvgResult));
        }
        $month = strtotime("+1 month", $month);
        $i++;
        $j++;
    }
    //total avg
    $totalAvg = 0;
    if (count($avgResult) == 0) {
        $totalAvg = 0;
    } else {
        $totalAvg = round(array_sum($avgResult) / count($avgResult));
    }

    $startMonth = date("M-Y", strtotime($start_date));
    $endMonth = date("M-Y", strtotime($end_date));

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
    if ($_POST['fyName'] == '-- Select --') {
        $_POST['fyName'] = '';
    }
    $atomcolumns = '';
    //$atomcolumns .= "Country:" . ($countryResult[0]['form_name']) . "&nbsp;&nbsp;&nbsp;";
    $atomcolumns .= "Region/Province/State:" . ($_POST['state']) . "&nbsp;&nbsp;&nbsp;";
    $atomcolumns .= "District/County:" . ($_POST['district']) . "\n\n";
    $atomcolumns .= "Laboratory Name:" . ($_POST['fyName']) . "\n\n";
    $atomcolumns .= "Reporting POC Name:________________";
    $atomcolumns .= "Title:________________";
    $atomcolumns .= "Email:________________\n\n";
    $atomcolumns .= "Date(MM/DD/YYYY): " . date('M/d/Y') . "&nbsp;&nbsp;&nbsp;";
    $atomcolumns .= "Reporting Quarter: " . $startMonth . " to " . $endMonth;
    $sheet->getStyle('A1')->applyFromArray($headingStyle);
    $sheet->getStyle('A1')->applyFromArray($backgroundStyle);
    $sheet->getStyle('A3')->applyFromArray($styleArray);
    $sheet->mergeCells('A1:M2');
    $sheet->setCellValue('A1', html_entity_decode('Viral Load Quarterly Monitoring Tool ', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    $sheet->mergeCells('A3:M10');
    $sheet->setCellValue('A3', html_entity_decode($atomcolumns, ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    $sheet->mergeCells('A11:A12');
    $sheet->mergeCells('B11:F12');
    $sheet->setCellValue('B11', html_entity_decode('Question', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    $sheet->mergeCells('G11:I12');
    $sheet->setCellValue('G11', html_entity_decode('Value', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    $sheet->mergeCells('J11:M12');
    $sheet->setCellValue('J11', html_entity_decode('Comments', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    $sheet->getStyle('B11')->applyFromArray($backgroundStyle);
    $sheet->getStyle('G11')->applyFromArray($backgroundStyle);
    $sheet->getStyle('J11')->applyFromArray($backgroundStyle);
    $sheet->getStyle('A11:M12')->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THICK);
    //question one start
    $sheet->mergeCells('A13:A14');
    $sheet->setCellValue('A13', html_entity_decode('Q1', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    $sheet->mergeCells('B13:F14');
    $sheet->setCellValue('B13', html_entity_decode('Number Of Viral Load tests reported by the laboratory during the current quarter', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    $sheet->mergeCells('G13:I14');
    $sheet->setCellValue('G13', html_entity_decode(count($rResult), ENT_QUOTES, 'UTF-8'), DataType::TYPE_NUMERIC);
    $sheet->mergeCells('J13:M14');
    $sheet->getStyle('A13')->applyFromArray($questionStyle);
    $sheet->getStyle('B13')->applyFromArray($questionStyle);
    $sheet->mergeCells('A15:A16');
    $sheet->setCellValue('A15', html_entity_decode('Q1.1', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    $sheet->mergeCells('B15:F17');
    $sheet->setCellValue('B15', html_entity_decode('Of the number of Viral Load test results reported by lab,how many were: ', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    $sheet->mergeCells('G15:G16');
    $sheet->setCellValue('G15', html_entity_decode('Suppressed < 1000 copies/mL ', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    $sheet->mergeCells('H15:I16');
    $sheet->mergeCells('H17:I17');
    $sheet->setCellValue('G17', html_entity_decode($sResult[0]['suppressed'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_NUMERIC);
    $sheet->setCellValue('H17', html_entity_decode($sResult[0]['NotSuppressed'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_NUMERIC);
    $sheet->setCellValue('H15', html_entity_decode('Suppressed Failure >= 1000 copies/mL ', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    $sheet->mergeCells('J15:M17');
    $sheet->getStyle('A15')->applyFromArray($questionStyle);
    $sheet->getStyle('B15')->applyFromArray($questionStyle);
    $sheet->getStyle('G15')->applyFromArray($questionStyle);
    $sheet->getStyle('H15')->applyFromArray($questionStyle);
    $sheet->mergeCells('A18:A18');
    $sheet->setCellValue('A18', html_entity_decode('Q1.2', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    $sheet->mergeCells('B18:M18');
    $sheet->setCellValue('B18', html_entity_decode('Sex', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);

    $sheet->mergeCells('B19:F19');
    $sheet->setCellValue('B19', html_entity_decode('Male', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    $sheet->mergeCells('G19:G19');
    $sheet->setCellValue('G19', html_entity_decode($sResult[0]['ltMale1000'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    $sheet->setCellValue('H19', html_entity_decode($sResult[0]['gtMale1000'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    $sheet->mergeCells('H19:I19');
    $sheet->mergeCells('J19:M19');
    $sheet->mergeCells('B20:F20');
    $sheet->setCellValue('B20', html_entity_decode('Female', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    $sheet->mergeCells('G20:G20');
    $sheet->mergeCells('H20:I20');
    $sheet->setCellValue('G20', html_entity_decode($sResult[0]['ltFemale1000'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_NUMERIC);
    $sheet->setCellValue('H20', html_entity_decode($sResult[0]['gtFemale1000'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_NUMERIC);
    $sheet->mergeCells('J20:M20');
    $sheet->mergeCells('B21:F21');
    $sheet->setCellValue('B21', html_entity_decode('Not Specified', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    $sheet->mergeCells('G21:G21');
    $sheet->mergeCells('H21:I21');
    $sheet->setCellValue('G21', html_entity_decode($sResult[0]['ltNotSpecified1000'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_NUMERIC);
    $sheet->setCellValue('H21', html_entity_decode($sResult[0]['gtNotSpecified1000'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_NUMERIC);
    $sheet->mergeCells('J21:M21');
    $sheet->mergeCells('B22:F22');
    $sheet->setCellValue('B22', html_entity_decode('Total', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    $sheet->mergeCells('G22:G22');
    $sheet->mergeCells('H22:I22');
    $sheet->setCellValue('G22', html_entity_decode($sResult[0]['ltTotalGender1000'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_NUMERIC);
    $sheet->setCellValue('H22', html_entity_decode($sResult[0]['gtTotalGender1000'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_NUMERIC);
    $sheet->mergeCells('J22:M22');

    $sheet->mergeCells('A23:A23');
    $sheet->setCellValue('A23', html_entity_decode('Q1.3', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    $sheet->mergeCells('B23:M23');
    $sheet->setCellValue('B23', html_entity_decode('Age', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);

    $sheet->mergeCells('B24:F24');
    $sheet->setCellValue('B24', html_entity_decode('<1', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    $sheet->mergeCells('G24:G24');
    $sheet->mergeCells('H24:I24');
    $sheet->setCellValue('G24', html_entity_decode($sResult[0]['ltAgeOne1000'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_NUMERIC);
    $sheet->setCellValue('H24', html_entity_decode($sResult[0]['gtAgeOne1000'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_NUMERIC);
    $sheet->mergeCells('J24:M24');
    $sheet->mergeCells('B25:F25');
    $sheet->setCellValue('B25', html_entity_decode('1-9', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    $sheet->mergeCells('G25:G25');
    $sheet->mergeCells('H25:I25');
    $sheet->setCellValue('G25', html_entity_decode($sResult[0]['ltAgeOnetoNine1000'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_NUMERIC);
    $sheet->setCellValue('H25', html_entity_decode($sResult[0]['gtAgeOnetoNine1000'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_NUMERIC);
    $sheet->mergeCells('J25:M25');
    $sheet->mergeCells('B26:F26');
    $sheet->setCellValue('B26', html_entity_decode('10-14', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    $sheet->mergeCells('G26:G26');
    $sheet->mergeCells('H26:I26');
    $sheet->setCellValue('G26', html_entity_decode($sResult[0]['ltAgeTentoFourteen1000'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_NUMERIC);
    $sheet->setCellValue('H26', html_entity_decode($sResult[0]['gtAgeTentoFourteen1000'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_NUMERIC);
    $sheet->mergeCells('J26:M26');
    $sheet->mergeCells('B27:F27');
    $sheet->setCellValue('B27', html_entity_decode('<15(Subtotal)', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    $sheet->mergeCells('G27:G27');
    $sheet->mergeCells('H27:I27');
    $sheet->setCellValue('G27', html_entity_decode($sResult[0]['ltAgeTotalFifteen1000'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_NUMERIC);
    $sheet->setCellValue('H27', html_entity_decode($sResult[0]['gtAgeTotalFifteen1000'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_NUMERIC);
    $sheet->mergeCells('J27:M27');
    $sheet->mergeCells('B28:F28');
    $sheet->setCellValue('B28', html_entity_decode('15-19', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    $sheet->mergeCells('G28:G28');
    $sheet->mergeCells('H28:I28');
    $sheet->setCellValue('G28', html_entity_decode($sResult[0]['ltAgeFifteentoNineteen1000'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_NUMERIC);
    $sheet->setCellValue('H28', html_entity_decode($sResult[0]['gtAgeFifteentoNineteen1000'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_NUMERIC);
    $sheet->mergeCells('J28:M28');
    $sheet->mergeCells('B29:F29');
    $sheet->setCellValue('B29', html_entity_decode('20-24', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    $sheet->mergeCells('G29:G29');
    $sheet->mergeCells('H29:I29');
    $sheet->setCellValue('G29', html_entity_decode($sResult[0]['ltAgeTwentytoTwentyFour1000'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_NUMERIC);
    $sheet->setCellValue('H29', html_entity_decode($sResult[0]['gtAgeTwentytoTwentyFour1000'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_NUMERIC);
    $sheet->mergeCells('J29:M29');
    $sheet->mergeCells('B30:F30');
    $sheet->setCellValue('B30', html_entity_decode('15-24', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    $sheet->mergeCells('G30:G30');
    $sheet->mergeCells('H30:I30');
    $sheet->setCellValue('G30', html_entity_decode($sResult[0]['ltAgeFifteentoTwentyFour1000'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_NUMERIC);
    $sheet->setCellValue('H30', html_entity_decode($sResult[0]['gtAgeFifteentoTwentyFour1000'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_NUMERIC);
    $sheet->mergeCells('J30:M30');
    $sheet->mergeCells('B31:F31');
    $sheet->setCellValue('B31', html_entity_decode('25+', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    $sheet->mergeCells('G31:G31');
    $sheet->mergeCells('H31:I31');
    $sheet->setCellValue('G31', html_entity_decode($sResult[0]['ltAgetwentyFive1000'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_NUMERIC);
    $sheet->setCellValue('H31', html_entity_decode($sResult[0]['gtAgetwentyFive1000'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_NUMERIC);
    $sheet->mergeCells('J31:M31');

    $sheet->mergeCells('B32:F32');
    $sheet->setCellValue('B32', html_entity_decode('Not Specified', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    $sheet->mergeCells('G32:G32');
    $sheet->mergeCells('H32:I32');
    $sheet->setCellValue('G32', html_entity_decode($sResult[0]['ltAgeNotSpecified1000'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_NUMERIC);
    $sheet->setCellValue('H32', html_entity_decode($sResult[0]['gtAgeNotSpecified1000'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_NUMERIC);
    $sheet->mergeCells('J32:M32');

    $sheet->mergeCells('B33:F33');
    $sheet->setCellValue('B33', html_entity_decode('Total', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    $sheet->mergeCells('G33:G33');
    $sheet->mergeCells('H33:I33');
    $sheet->setCellValue('G33', html_entity_decode($sResult[0]['ltAgeTotal1000'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_NUMERIC);
    $sheet->setCellValue('H33', html_entity_decode($sResult[0]['gtAgeTotal1000'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_NUMERIC);
    $sheet->mergeCells('J33:M33');
    $sheet->mergeCells('A34:A34');
    $sheet->setCellValue('A34', html_entity_decode('Q1.4', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    $sheet->mergeCells('B34:F34');
    $sheet->setCellValue('B34', html_entity_decode('Pregnant Women', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    $sheet->mergeCells('G34:G34');
    $sheet->mergeCells('H34:I34');
    $sheet->setCellValue('G34', html_entity_decode($sResult[0]['ltPatientPregnant1000'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_NUMERIC);
    $sheet->setCellValue('H34', html_entity_decode($sResult[0]['gtPatientPregnant1000'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_NUMERIC);
    $sheet->mergeCells('J34:M34');
    $sheet->mergeCells('A35:A35');
    $sheet->setCellValue('A35', html_entity_decode('Q1.5', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    $sheet->mergeCells('B35:F35');
    $sheet->setCellValue('B35', html_entity_decode('Women that are breastfeeding', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    $sheet->mergeCells('G35:G35');
    $sheet->mergeCells('H35:I35');
    $sheet->setCellValue('G35', html_entity_decode($sResult[0]['ltPatientBreastFeeding1000'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_NUMERIC);
    $sheet->setCellValue('H35', html_entity_decode($sResult[0]['gtPatientBreastFeeding1000'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_NUMERIC);
    $sheet->mergeCells('J35:M35');
    $sheet->getStyle('A13:F37')->applyFromArray($questionStyle);
    $sheet->getStyle('B18')->applyFromArray($genderquestionStyle);
    $sheet->getStyle('B23')->applyFromArray($genderquestionStyle);
    $sheet->getStyle('B34')->applyFromArray($genderquestionStyle);
    $sheet->getStyle('B33')->applyFromArray($genderquestionStyle);
    $sheet->getStyle('A35:M35')->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THICK);
    //question two start
    $sheet->mergeCells('A36:A36');
    $sheet->setCellValue('A36', html_entity_decode('Q2', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    $sheet->mergeCells('B36:F37');
    $sheet->setCellValue('B36', html_entity_decode('Is there a backlog for viral load testing? (greater than 1 month testing volume) Choose from list', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    $sheet->mergeCells('G36:I37');
    $sheet->setCellValue('G36', html_entity_decode(count($checkEmptyResult), ENT_QUOTES, 'UTF-8'), DataType::TYPE_NUMERIC);
    $sheet->mergeCells('J36:M37');
    $sheet->setCellValue('J36', html_entity_decode('Reasons:', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    $s = 38;
    $ss = 39;
    if (count($checkEmptyResult) > 0) {
        foreach ($sampleTypeResult as $sampleName) {
            if ($checkEmptySampleResult[$sampleName['sample_name']][0]['total'] != 0) {
                $sheet->mergeCells('B' . $s . ':F' . $ss);
                $sheet->setCellValue('B' . $s, html_entity_decode('If yes,which type of samples?', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
                $sheet->mergeCells('G' . $s . ':I' . $ss);
                $sheet->mergeCells('J' . $s . ':M' . $ss);
                $sheet->setCellValue('G' . $s, html_entity_decode($sampleName['sample_name'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
                $c = $s + 2;
                $cc = $ss + 1;
                $sheet->mergeCells('A' . $c . ':A' . $cc);
                $sheet->setCellValue('A' . $c, html_entity_decode('Q2.1', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
                $sheet->mergeCells('B' . $c . ':F' . $cc);
                $sheet->setCellValue('B' . $c, html_entity_decode('If yes,how many samples?', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
                $sheet->mergeCells('G' . $c . ':I' . $cc);
                $sheet->mergeCells('J' . $c . ':M' . $cc);
                $sheet->setCellValue('G' . $c, html_entity_decode($checkEmptySampleResult[$sampleName['sample_name']][0]['total'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_NUMERIC);
                $s = $c + 1;
                $ss = $cc + 2;
            }
        }
    }
    if (isset($c) && isset($cc)) {
        $sheet->getStyle('A' . $c . ':M' . $cc)->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THICK);
    } else {
        $sheet->getStyle('A37:M37')->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THICK);
    }

    //quetion three start
    $q3 = $s;
    $q33 = $ss;
    $sheet->mergeCells('A' . $q3 . ':A' . $q33);
    $sheet->setCellValue('A' . $q3, html_entity_decode('Q3', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    $sheet->mergeCells('B' . $q3 . ':F' . $q33);
    $sheet->setCellValue('B' . $q3, html_entity_decode('What is the average monthly/quarterly TAT? (sample collection to lab result release)', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    $sheet->mergeCells('G' . $q3 . ':I' . $q33);
    $sheet->setCellValue('G' . $q3, html_entity_decode($totalAvg, ENT_QUOTES, 'UTF-8'), DataType::TYPE_NUMERIC);
    $sheet->mergeCells('J' . $q3 . ':M' . $q33);
    $sheet->getStyle('A' . $q3 . ':M' . $q33)->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THICK);

    //quetion four start
    $q4 = $s + 2;
    $q44 = $ss + 2;
    $sheet->mergeCells('A' . $q4 . ':A' . $q44);
    $sheet->setCellValue('A' . $q4, html_entity_decode('Q4', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    $sheet->mergeCells('B' . $q4 . ':F' . $q44);
    $sheet->setCellValue('B' . $q4, html_entity_decode('Are there planned procurements within this fiscal year? Choose from list', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    $sheet->mergeCells('G' . $q4 . ':I' . $q44);
    $sheet->mergeCells('J' . $q4 . ':M' . $q44);

    $q4 = $s + 4;
    $q44 = $ss + 4;
    $sheet->mergeCells('A' . $q4 . ':A' . $q44);
    $sheet->setCellValue('A' . $q4, html_entity_decode('Q4.1', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    $sheet->mergeCells('B' . $q4 . ':F' . $q44);
    $sheet->setCellValue('B' . $q4, html_entity_decode('If yes, please list:', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    $sheet->mergeCells('G' . $q4 . ':I' . $q44);
    $sheet->mergeCells('J' . $q4 . ':M' . $q44);
    $q4 = $q4 + 2;
    $q44 = $q44 + 2;
    $sheet->mergeCells('B' . $q4 . ':F' . $q44);
    $sheet->setCellValue('B' . $q4, html_entity_decode('Platform Type:', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    $sheet->mergeCells('G' . $q4 . ':I' . $q44);
    $sheet->mergeCells('J' . $q4 . ':M' . $q44);
    $q4 = $q4 + 2;
    $q44 = $q44 + 2;
    $sheet->mergeCells('B' . $q4 . ':F' . $q44);
    $sheet->setCellValue('B' . $q4, html_entity_decode('Quantity:', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    $sheet->mergeCells('G' . $q4 . ':I' . $q44);
    $sheet->mergeCells('J' . $q4 . ':M' . $q44);
    $q4 = $q4 + 2;
    $q44 = $q44 + 2;
    $sheet->mergeCells('B' . $q4 . ':F' . $q44);
    $sheet->setCellValue('B' . $q4, html_entity_decode('Planned location of placement:', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    $sheet->mergeCells('G' . $q4 . ':I' . $q44);
    $sheet->mergeCells('J' . $q4 . ':M' . $q44);
    $sheet->getStyle('A' . $q4 . ':M' . $q44)->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THICK);
    //question five start
    $q5 = $q4 + 2;
    $q55 = $q44 + 2;
    $sheet->mergeCells('A' . $q5 . ':A' . $q55);
    $sheet->setCellValue('A' . $q5, html_entity_decode('Q5', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    $sheet->mergeCells('B' . $q5 . ':F' . $q55);
    $sheet->setCellValue('B' . $q5, html_entity_decode('Number of Early Infant Diagnosis tests reported by the lab:', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    $sheet->mergeCells('G' . $q5 . ':I' . $q55);
    $sheet->mergeCells('J' . $q5 . ':M' . $q55);

    $q5 = $q5 + 2;
    $q55 = $q55 + 2;
    $sheet->mergeCells('A' . $q5 . ':A' . $q55);
    $sheet->setCellValue('A' . $q5, html_entity_decode('Q5.1', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    $sheet->mergeCells('B' . $q5 . ':F' . $q55);
    $sheet->setCellValue('B' . $q5, html_entity_decode('Number of Early Infant Diagnosis tests with a positive result:', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    $sheet->mergeCells('G' . $q5 . ':I' . $q55);
    $sheet->mergeCells('J' . $q5 . ':M' . $q55);
    $sheet->getStyle('A' . $q5 . ':M' . $q55)->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THICK);
    //question six start
    $q6 = $q5 + 2;
    $q66 = $q55 + 2;
    $sheet->mergeCells('A' . $q6 . ':A' . $q66);
    $sheet->setCellValue('A' . $q6, html_entity_decode('Q6', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    $sheet->mergeCells('B' . $q6 . ':F' . $q66);
    $sheet->setCellValue('B' . $q6, html_entity_decode('Is there a backlog for Early Infant Diagnosis testing (greater than 1 month testing volume)? Choose from list', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    $sheet->mergeCells('G' . $q6 . ':I' . $q66);
    $sheet->mergeCells('J' . $q6 . ':M' . $q66);
    $sheet->setCellValue('J' . $q6, html_entity_decode('Reasons:', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);

    $q6 = $q6 + 2;
    $q66 = $q66 + 2;
    $sheet->mergeCells('A' . $q6 . ':A' . $q66);
    $sheet->setCellValue('A' . $q6, html_entity_decode('Q6.1', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    $sheet->mergeCells('B' . $q6 . ':F' . $q66);
    $sheet->setCellValue('B' . $q6, html_entity_decode('If yes, how many samples?', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    $sheet->mergeCells('G' . $q6 . ':I' . $q66);
    $sheet->mergeCells('J' . $q6 . ':M' . $q66);
    $sheet->getStyle('A' . $q6 . ':M' . $q66)->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THICK);

    //question seven start
    $q7 = $q6 + 2;
    $q77 = $q66 + 2;
    $mergeQ7 = $q7;
    $sheet->mergeCells('A' . $q7 . ':A' . $q77);
    $sheet->setCellValue('A' . $q7, html_entity_decode('Q7', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    $sheet->mergeCells('B' . $q7 . ':F' . $q77);
    $sheet->setCellValue('B' . $q7, html_entity_decode('Number of invalid VL and EID tests reported by the lab per month:', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    //check invalid result
    if (isset($_POST['sampleCollectionDate']) && trim($_POST['sampleCollectionDate']) != '') {
        $s_c_date = explode(" to ", $_POST['sampleCollectionDate']);
        $start_date = trim($s_c_date[0]);
        $end_date = trim($s_c_date[1]);
        $startMonth = date("Y-m", strtotime($start_date));
        $endMonth = date("Y-m", strtotime($end_date));
        $start = $month = strtotime($startMonth);
        $end = strtotime($endMonth);
        $i = 0;
        while ($month <= $end) {
            $sheet->getStyle('A38:F' . $q7)->applyFromArray($questionStyle);
            $mnth = date('m', $month);
            $year = date('Y', $month);
            $dFormat = date("M-Y", $month);
            $invalidResultQuery = "SELECT 
                    vl.sample_collection_date,
                    f.facility_name,
                    f.facility_code,
                    f.facility_state,
                    f.facility_district,
                    SUM(CASE WHEN (result!='' AND result!='Target Not Detected') AND (result > 10000000 OR result < 20) THEN 1 ELSE 0 END) AS invalidTotal
                    FROM form_vl as vl 
                    JOIN facility_details as f ON vl.facility_id=f.facility_id 
                    WHERE MONTH(sample_collection_date)='$mnth' 
                    AND YEAR(sample_collection_date)='$year'";
            if (isset($_POST['district']) && trim($_POST['district']) != '') {
                $sWhere = $sWhere . " AND f.facility_district LIKE '%" . $_POST['district'] . "%' ";
            }
            if (isset($_POST['sampleTestDate']) && trim($_POST['sampleTestDate']) != '') {
                if (trim($sTestDate) == trim($eTestDate)) {
                    $sWhere = $sWhere . ' AND DATE(vl.sample_tested_datetime) = "' . $sTestDate . '"';
                } else {
                    $sWhere = $sWhere . ' AND DATE(vl.sample_tested_datetime) >= "' . $sTestDate . '" AND DATE(vl.sample_tested_datetime) <= "' . $eTestDate . '"';
                }
            }
            if (isset($_POST['state']) && trim($_POST['state']) != '') {
                $sWhere = $sWhere . " AND f.facility_state LIKE '%" . $_POST['state'] . "%' ";
            }
            if (isset($_POST['facilityName']) && trim($_POST['facilityName']) != '') {
                $sWhere = $sWhere . ' AND f.facility_id = "' . $_POST['facilityName'] . '"';
            }
            $invalidResult[$dFormat] = $db->rawQuery($invalidResultQuery);

            $sheet->setCellValue('G' . $q7, html_entity_decode($dFormat, ENT_QUOTES, 'UTF-8'), DataType::TYPE_NUMERIC);
            $sheet->mergeCells('H' . $q7 . ':I' . $q7);
            $sheet->setCellValue('H' . $q7, html_entity_decode($invalidResult[$dFormat][0]['invalidTotal'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
            $sheet->mergeCells('J' . $q7 . ':M' . $q7);
            $q7++;
            $q77++;
            $month = strtotime("+1 month", $month);
            $i++;
        }
    }
    $bQ7 = $q7 - 1;
    $sheet->mergeCells('A' . $mergeQ7 . ':A' . $bQ7);
    $sheet->mergeCells('B' . $mergeQ7 . ':F' . $bQ7);
    $sheet->getStyle('A' . $bQ7 . ':M' . $bQ7)->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THICK);
    //question eight start
    $q8 = $q7;
    $q88 = $q77;
    $mergeQ8 = $q8;
    $sheet->mergeCells('A' . $q8 . ':A' . $q88);
    $sheet->setCellValue('A' . $q8, html_entity_decode('Q8', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    $sheet->mergeCells('B' . $q8 . ':F' . $q88);
    $sheet->setCellValue('B' . $q8, html_entity_decode('Duration of equipment breakdown in number of days (provide per instrument type):', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    $sheet->setCellValue('G' . $q8, html_entity_decode('Instrument', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    $sheet->mergeCells('H' . $q8 . ':I' . $q8);
    $sheet->mergeCells('J' . $q8 . ':M' . $q8);
    $sheet->getStyle('G' . $q8 . ':M' . $q8)->applyFromArray($questionStyle);
    $sheet->setCellValue('H' . $q8, html_entity_decode('Days Down', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    $q8 = $q8 + 1;
    $q88 = $q88 + 1;
    $sheet->mergeCells('H' . $q8 . ':I' . $q8);
    $sheet->mergeCells('J' . $q8 . ':M' . $q8);
    $q8 = $q8 + 1;
    $q88 = $q88 + 1;
    $sheet->mergeCells('H' . $q8 . ':I' . $q8);
    $sheet->mergeCells('J' . $q8 . ':M' . $q8);
    $q8 = $q8 + 1;
    $q88 = $q88 + 1;
    $sheet->mergeCells('H' . $q8 . ':I' . $q8);
    $sheet->mergeCells('J' . $q8 . ':M' . $q8);
    $q8 = $q8 + 1;
    $q88 = $q88 + 1;
    $sheet->mergeCells('H' . $q8 . ':I' . $q8);
    $sheet->mergeCells('J' . $q8 . ':M' . $q8);
    $q8 = $q8 + 1;
    $q88 = $q88 + 1;
    $sheet->mergeCells('H' . $q8 . ':I' . $q8);
    $sheet->mergeCells('J' . $q8 . ':M' . $q8);

    $sheet->mergeCells('A' . $mergeQ8 . ':A' . $q8);
    $sheet->mergeCells('B' . $mergeQ8 . ':F' . $q8);
    $q8 = $q8 + 1;
    $q88 = $q88 + 1;

    $sheet->mergeCells('A' . $q8 . ':A' . $q88);
    $sheet->setCellValue('A' . $q8, html_entity_decode('Q8.1', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    $sheet->mergeCells('B' . $q8 . ':F' . $q88);
    $sheet->setCellValue('B' . $q8, html_entity_decode('Reason for instrument breakdown: ', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
    $sheet->mergeCells('G' . $q8 . ':I' . $q88);
    $sheet->mergeCells('J' . $q8 . ':M' . $q88);
    $sheet->getStyle('A37:F' . $q8)->applyFromArray($questionStyle);
    $sheet->getStyle('A' . $mergeQ8 . ':F' . $mergeQ8)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getStyle('A' . $mergeQ7 . ':F' . $mergeQ7)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getStyle('A11:M' . $q88)->applyFromArray($borderStyle);
    $writer = IOFactory::createWriter($excel, 'Xls');
    $filename = 'VLSM-Quarterly-Monitoring-Report-' . date('d-M-Y-H-i-s') . '.xls';
    ob_end_clean();
    $writer->save(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
    echo $filename;
}
