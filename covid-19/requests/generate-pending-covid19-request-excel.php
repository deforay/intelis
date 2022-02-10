<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
ob_start();

$general = new \Vlsm\Models\General();

$covid19Results = $general->getCovid19Results();
/* Global config data */
$arr = $general->getGlobalConfig();
$sarr = $general->getSystemConfig();
// echo "<pre>";print_r($arr);die;
if (isset($_SESSION['covid19RequestSearchResultQuery']) && trim($_SESSION['covid19RequestSearchResultQuery']) != "") {

    $rResult = $db->rawQuery($_SESSION['covid19RequestSearchResultQuery']);

    $excel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $output = array();
    $sheet = $excel->getActiveSheet();

    $headings = array(
        _("S. No."),
        _("Sample Code"),
        _("Testing Lab Name"),
        _("Testing Point"),
        _("Lab staff Assigned"),
        _("Source Of Alert / POE"),
        _("Health Facility/POE County"),
        _("Health Facility/POE State"),
        _("Health Facility/POE"),
        _("Case ID"),
        _("Patient Name"),
        _("Patient DoB"),
        _("Patient Age"),
        _("Patient Gender"),
        _("Is Patient Pregnant"),
        _("Patient Phone No."),
        _("Patient Email"),
        _("Patient Address"),
        _("Patient State"),
        _("Patient County"),
        _("Patient City/Village"),
        _("Nationality"),
        _("Fever/Temperature"),
        _("Temprature Measurement"),
        _("Symptoms Detected"),
        _("Medical History"),
        _("Comorbidities"),
        _("Recenty Hospitalized?"),
        _("Patient Lives With Children"),
        _("Patient Cares for Children"),
        _("Close Contacts"),
        _("Has Recent Travel History"),
        _("Country Names"),
        _("Travel Return Date"),
        _("Airline"),
        _("Seat No."),
        _("Arrival Date/Time"),
        _("Departure Airport"),
        _("Transit"),
        _("Reason of Visit"),
        _("Number of Days Sick"),
        _("Date of Symptoms Onset"),
        _("Date of Initial Consultation"),
        _("Sample Collection Date"),
        _("Reason for Test Request"),
        _("Date specimen received"),
        _("Date specimen registered"),
        _("Specimen Condition"),
        _("Specimen Status"),
        _("Specimen Type"),
        _("Sample Tested Date"),
        _("Testing Platform"),
        _("Test Method"),
        _("Result"),
        _("Date result released")
    );


    $colNo = 1;

    $styleArray = array(
        'font' => array(
            'bold' => true,
            'size' => 12,
        ),
        'alignment' => array(
            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
        ),
        'borders' => array(
            'outline' => array(
                'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
            ),
        )
    );

    $borderStyle = array(
        'alignment' => array(
            'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
        ),
        'borders' => array(
            'outline' => array(
                'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
            ),
        )
    );

    $sheet->mergeCells('A1:BG1');
    $nameValue = '';
    foreach ($_POST as $key => $value) {
        if (trim($value) != '' && trim($value) != '-- Select --') {
            $nameValue .= str_replace("_", " ", $key) . " : " . $value . "&nbsp;&nbsp;";
        }
    }
    $sheet->getCellByColumnAndRow($colNo, 1)->setValueExplicit(html_entity_decode($nameValue), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    if ($_POST['withAlphaNum'] == 'yes') {
        foreach ($headings as $field => $value) {
            $string = str_replace(' ', '', $value);
            $value = preg_replace('/[^A-Za-z0-9\-]/', '', $string);
            $sheet->getCellByColumnAndRow($colNo, 3)->setValueExplicit(html_entity_decode($value), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $colNo++;
        }
    } else {
        foreach ($headings as $field => $value) {
            $sheet->getCellByColumnAndRow($colNo, 3)->setValueExplicit(html_entity_decode($value), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $colNo++;
        }
    }
    $sheet->getStyle('A3:CG3')->applyFromArray($styleArray);

    $no = 1;
    foreach ($rResult as $aRow) {
        $symptomList = array();
        $squery = "SELECT s.*, ps.* FROM form_covid19 as c19 
        INNER JOIN covid19_patient_symptoms AS ps ON c19.covid19_id = ps.covid19_id 
        INNER JOIN r_covid19_symptoms AS s ON ps.symptom_id = s.symptom_id 
        WHERE ps.symptom_detected like 'yes' AND c19.covid19_id = " . $aRow['covid19_id'];
        $result = $db->rawQuery($squery);
        foreach ($result as $symp) {
            //$symResult = (isset($symptom['symptom_detected']) && $symptom['symptom_detected'] != "") ? ucwords($symptom['symptom_detected']) : "Unknown";
            $symptomList[] = $symp['symptom_name'];
        }

        $comorbiditiesList = array();
        $squery = "SELECT s.*, como.* FROM form_covid19 as c19 
        INNER JOIN covid19_patient_comorbidities AS como ON c19.covid19_id = como.covid19_id 
        INNER JOIN r_covid19_comorbidities AS s ON como.comorbidity_id = s.comorbidity_id 
        WHERE como.comorbidity_detected like 'yes' AND c19.covid19_id = " . $aRow['covid19_id'];
        $result = $db->rawQuery($squery);
        foreach ($result as $como) {
            $comorbiditiesList[] = $como['comorbidity_name'];
        }

        $row = array();
        $testPlatform = null;
        $testMethod = null;
        // Get testing platform and test method 
        $covid19TestQuery = "SELECT * FROM covid19_tests WHERE covid19_id= " . $aRow['covid19_id'] . " ORDER BY test_id DESC LIMIT 1";
        $covid19TestInfo = $db->rawQueryOne($covid19TestQuery);
        foreach ($covid19TestInfo as $indexKey => $rows) {
            $testPlatform = $rows['testing_platform'];
            $testMethod = $rows['test_name'];
        }

        //date of birth
        $dob = '';
        if ($aRow['patient_dob'] != NULL && trim($aRow['patient_dob']) != '' && $aRow['patient_dob'] != '0000-00-00') {
            $dob =  date("d-m-Y", strtotime($aRow['patient_dob']));
        }
        //set gender
        $gender = '';
        if ($aRow['patient_gender'] == 'male') {
            $gender = 'M';
        } else if ($aRow['patient_gender'] == 'female') {
            $gender = 'F';
        } else if ($aRow['patient_gender'] == 'not_recorded') {
            $gender = 'Unreported';
        }
        //sample collecion date
        $sampleCollectionDate = '';
        if ($aRow['sample_collection_date'] != NULL && trim($aRow['sample_collection_date']) != '' && $aRow['sample_collection_date'] != '0000-00-00 00:00:00') {
            $expStr = explode(" ", $aRow['sample_collection_date']);
            $sampleCollectionDate =  date("d-m-Y", strtotime($expStr[0]));
        }

        $sampleTestedOn = '';
        if ($aRow['sample_tested_datetime'] != NULL && trim($aRow['sample_tested_datetime']) != '' && $aRow['sample_tested_datetime'] != '0000-00-00') {
            $sampleTestedOn =  date("d-m-Y", strtotime($aRow['sample_tested_datetime']));
        }


        //set sample rejection
        $sampleRejection = 'No';
        if (trim($aRow['is_sample_rejected']) == 'yes' || ($aRow['reason_for_sample_rejection'] != NULL && trim($aRow['reason_for_sample_rejection']) != '' && $aRow['reason_for_sample_rejection'] > 0)) {
            $sampleRejection = 'Yes';
        }
        //result dispatched date
        $resultDispatchedDate = '';
        if ($aRow['result_printed_datetime'] != NULL && trim($aRow['result_printed_datetime']) != '' && $aRow['result_printed_datetime'] != '0000-00-00 00:00:00') {
            $expStr = explode(" ", $aRow['result_printed_datetime']);
            $resultDispatchedDate =  date("d-m-Y", strtotime($expStr[0]));
        }

        if ($_SESSION['instanceType'] == 'remoteuser') {
            $sampleCode = 'remote_sample_code';
        } else {
            $sampleCode = 'sample_code';
        }

        if ($aRow['patient_name'] != '') {
            $patientFname = ucwords($general->crypto('decrypt', $aRow['patient_name'], $aRow['patient_id']));
        } else {
            $patientFname = '';
        }
        if ($aRow['patient_last_name'] != '') {
            $patientLname = ucwords($general->crypto('decrypt', $aRow['patient_surname'], $aRow['patient_id']));
        } else {
            $patientLname = '';
        }

        if (isset($aRow['source_of_alert']) && $aRow['source_of_alert'] != "others") {
            $sourceOfArtPOE = str_replace("-", " ", $aRow['source_of_alert']);
        } else {
            $sourceOfArtPOE = $aRow['source_of_alert_other'];
        }



        $row[] = $no;
        $row[] = $aRow[$sampleCode];
        $row[] = ucwords($aRow['lab_name']);
        $row[] = ucwords($aRow['testing_point']);
        $row[] = ucwords($aRow['labTechnician']);
        $row[] = ucwords($sourceOfArtPOE);
        $row[] = ucwords($aRow['facility_district']);
        $row[] = ucwords($aRow['facility_state']);
        $row[] = ucwords($aRow['facility_name']);
        $row[] = $aRow['patient_id'];
        $row[] = $patientFname . " " . $patientLname;
        $row[] = $general->humanDateFormat($aRow['patient_dob']);
        $row[] = ($aRow['patient_age'] != NULL && trim($aRow['patient_age']) != '' && $aRow['patient_age'] > 0) ? $aRow['patient_age'] : 0;
        $row[] = ucwords($aRow['patient_gender']);
        $row[] = ucwords($aRow['is_patient_pregnant']);
        $row[] = ucwords($aRow['patient_phone_number']);
        $row[] = ucwords($aRow['patient_email']);
        $row[] = ucwords($aRow['patient_address']);
        $row[] = ucwords($aRow['patient_province']);
        $row[] = ucwords($aRow['patient_district']);
        $row[] = ucwords($aRow['patient_city']);
        $row[] = ucwords($aRow['nationality']);
        $row[] = $aRow['fever_temp'];
        $row[] = $aRow['temperature_measurement_method'];
        $row[] = implode(", ", $symptomList);
        $row[] = $aRow['medical_history'];
        $row[] = implode(", ", $comorbiditiesList);
        $row[] = $aRow['recent_hospitalization'];
        $row[] = $aRow['patient_lives_with_children'];
        $row[] = $aRow['patient_cares_for_children'];
        $row[] = $aRow['close_contacts'];
        $row[] = $aRow['has_recent_travel_history'];
        $row[] = $aRow['travel_country_names'];
        $row[] = $aRow['travel_return_date'];
        $row[] = $aRow['flight_airline'];
        $row[] = $aRow['flight_seat_no'];
        $row[] = $aRow['flight_arrival_datetime'];
        $row[] = $aRow['flight_airport_of_departure'];
        $row[] = $aRow['flight_transit'];
        $row[] = $aRow['reason_of_visit'];
        $row[] = $aRow['number_of_days_sick'];
        $row[] = $general->humanDateFormat($aRow['date_of_symptom_onset']);
        $row[] = $general->humanDateFormat($aRow['date_of_initial_consultation']);
        $row[] = $general->humanDateFormat($aRow['sample_collection_date']);
        $row[] = ucwords($aRow['test_reason_name']);
        $row[] = $general->humanDateFormat($aRow['sample_received_at_vl_lab_datetime']);
        $row[] = $general->humanDateFormat($aRow['request_created_datetime']);
        $row[] = ucwords($aRow['sample_condition']);
        $row[] = ucwords($aRow['status_name']);
        $row[] = ucwords($aRow['sample_name']);
        $row[] = $general->humanDateFormat($aRow['sample_tested_datetime']);
        $row[] = ucwords($aRow['covid19_test_platform']);
        $row[] = ucwords($aRow['covid19_test_name']);
        $row[] = $covid19Results[$aRow['result']];
        $row[] = $general->humanDateFormat($aRow['result_printed_datetime']);

        $output[] = $row;
        $no++;
    }

    $start = (count($output)) + 2;
    foreach ($output as $rowNo => $rowData) {
        $colNo = 1;
        foreach ($rowData as $field => $value) {
            $rRowCount = $rowNo + 4;
            $cellName = $sheet->getCellByColumnAndRow($colNo, $rRowCount)->getColumn();
            $sheet->getStyle($cellName . $rRowCount)->applyFromArray($borderStyle);
            $sheet->getStyle($cellName . $start)->applyFromArray($borderStyle);
            $sheet->getDefaultRowDimension($colNo)->setRowHeight(18);
            $sheet->getColumnDimensionByColumn($colNo)->setWidth(20);
            $sheet->getCellByColumnAndRow($colNo, $rowNo + 4)->setValueExplicit(html_entity_decode($value), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $colNo++;
        }
    }
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($excel, 'Xlsx');
    $filename = 'Covid-19-Export-Data-' . date('d-M-Y-H-i-s') . '.xlsx';
    $writer->save(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
    echo $filename;
}
