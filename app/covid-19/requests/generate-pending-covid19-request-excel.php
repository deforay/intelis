<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


use App\Services\Covid19Service;
use App\Services\CommonService;
use App\Utilities\DateUtils;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

$general = new CommonService();

$covid19Obj = new Covid19Service();
$covid19Results = $covid19Obj->getCovid19Results();

/* Global config data */
$arr = $general->getGlobalConfig();
$sarr = $general->getSystemConfig();
// echo "<pre>";print_r($arr);die;
/*$sQuery = "SELECT SQL_CALC_FOUND_ROWS vl.*, f.*,  ts.status_name, b.batch_code, r.result as resultTxt,
          rtr.test_reason_name,
          rst.sample_name,
          f.facility_name,
          l_f.facility_name as lab_name,
          f.facility_code,
          f.facility_state,
          f.facility_district,
          u_d.user_name as reviewedBy,
          a_u_d.user_name as approvedBy,
          lt_u_d.user_name as labTechnician,
          rs.rejection_reason_name,
          r_f_s.funding_source_name,
          c.iso_name as nationality,
          r_i_p.i_partner_name FROM form_covid19 as vl 
          LEFT JOIN r_countries as c ON vl.patient_nationality=c.id 
          LEFT JOIN r_covid19_results as r ON vl.result=r.result_id 
          LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id 
          LEFT JOIN facility_details as l_f ON vl.lab_id=l_f.facility_id 
          LEFT JOIN r_sample_status as ts ON ts.status_id=vl.result_status 
          LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id
          LEFT JOIN user_details as u_d ON u_d.user_id=vl.result_reviewed_by 
          LEFT JOIN user_details as a_u_d ON a_u_d.user_id=vl.result_approved_by 
          LEFT JOIN user_details as lt_u_d ON lt_u_d.user_id=vl.lab_technician 
          LEFT JOIN r_covid19_test_reasons as rtr ON rtr.test_reason_id=vl.reason_for_covid19_test 
          LEFT JOIN r_covid19_sample_type as rst ON rst.sample_id=vl.specimen_type 
          LEFT JOIN r_covid19_sample_rejection_reasons as rs ON rs.rejection_reason_id=vl.reason_for_sample_rejection 
          LEFT JOIN r_funding_sources as r_f_s ON r_f_s.funding_source_id=vl.funding_source 
          LEFT JOIN r_implementation_partners as r_i_p ON r_i_p.i_partner_id=vl.implementing_partner";

if (isset($_SESSION['covid19RequestData']['sWhere']) && !empty($_SESSION['covid19RequestData']['sWhere'])) {
    $sQuery = $sQuery . ' WHERE ' . $_SESSION['covid19RequestData']['sWhere'];
}

if (isset($_SESSION['covid19RequestData']['sOrder']) && !empty($_SESSION['covid19RequestData']['sOrder'])) {
    $sQuery = $sQuery . " ORDER BY " . $_SESSION['covid19RequestData']['sOrder'];
}*/
// die($sQuery);
$sQuery = $_SESSION['covid19RequestSearchResultQuery'];

$rResult = $db->rawQuery($sQuery);

$excel = new Spreadsheet();
$output = [];
$sheet = $excel->getActiveSheet();
if (isset($_POST['patientInfo']) && $_POST['patientInfo'] == 'yes') {
    $headings = array(_("S. No."), _("Sample Code"), _("Remote Sample Code"), _("Testing Lab Name"), _("Testing Point"), _("Lab staff Assigned"), _("Source Of Alert / POE"), _("Health Facility/POE County"), _("Health Facility/POE State"), _("Health Facility/POE"), _("Case ID"), _("Patient Name"), _("Patient DoB"), _("Patient Age"), _("Patient Gender"), _("Is Patient Pregnant"), _("Patient Phone No."), _("Patient Email"), _("Patient Address"), _("Patient State"), _("Patient County"), _("Patient City/Village"), _("Nationality"), _("Fever/Temperature"), _("Temprature Measurement"), _("Symptoms Detected"), _("Medical History"), _("Comorbidities"), _("Recenty Hospitalized?"), _("Patient Lives With Children"), _("Patient Cares for Children"), _("Close Contacts"), _("Has Recent Travel History"), _("Country Names"), _("Travel Return Date"), _("Airline"), _("Seat No."), _("Arrival Date/Time"), _("Departure Airport"), _("Transit"), _("Reason of Visit"), _("Number of Days Sick"), _("Date of Symptoms Onset"), _("Date of Initial Consultation"), _("Sample Collection Date"), _("Reason for Test Request"), _("Date specimen received"), _("Date specimen registered"), _("Specimen Condition"), _("Specimen Status"), _("Specimen Type"), _("Sample Tested Date"), _("Testing Platform"), _("Test Method"), _("Result"), _("Date result released"));
}
else
{
    $headings = array(_("S. No."), _("Sample Code"), _("Remote Sample Code"), _("Testing Lab Name"), _("Testing Point"), _("Lab staff Assigned"), _("Source Of Alert / POE"), _("Health Facility/POE County"), _("Health Facility/POE State"), _("Health Facility/POE"), _("Patient DoB"), _("Patient Age"), _("Patient Gender"), _("Is Patient Pregnant"), _("Patient Phone No."), _("Patient Email"), _("Patient Address"), _("Patient State"), _("Patient County"), _("Patient City/Village"), _("Nationality"), _("Fever/Temperature"), _("Temprature Measurement"), _("Symptoms Detected"), _("Medical History"), _("Comorbidities"), _("Recenty Hospitalized?"), _("Patient Lives With Children"), _("Patient Cares for Children"), _("Close Contacts"), _("Has Recent Travel History"), _("Country Names"), _("Travel Return Date"), _("Airline"), _("Seat No."), _("Arrival Date/Time"), _("Departure Airport"), _("Transit"), _("Reason of Visit"), _("Number of Days Sick"), _("Date of Symptoms Onset"), _("Date of Initial Consultation"), _("Sample Collection Date"), _("Reason for Test Request"), _("Date specimen received"), _("Date specimen registered"), _("Specimen Condition"), _("Specimen Status"), _("Specimen Type"), _("Sample Tested Date"), _("Testing Platform"), _("Test Method"), _("Result"), _("Date result released"));
}
if ($_SESSION['instanceType'] == 'standalone') {
    if (($key = array_search("Remote Sample Code", $headings)) !== false) {
        unset($headings[$key]);
    }
}

$colNo = 1;

$styleArray = array(
    'font' => array(
        'bold' => true,
        'size' => 12,
    ),
    'alignment' => array(
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
    ),
    'borders' => array(
        'outline' => array(
            'style' => Border::BORDER_THIN,
        ),
    )
);

$borderStyle = array(
    'alignment' => array(
        'vertical' => Alignment::VERTICAL_CENTER,
    ),
    'borders' => array(
        'outline' => array(
            'style' => Border::BORDER_THIN,
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
$sheet->getCell(Coordinate::stringFromColumnIndex($colNo) . '1')
		->setValueExplicit(html_entity_decode($nameValue), DataType::TYPE_STRING);
if ($_POST['withAlphaNum'] == 'yes') {
    foreach ($headings as $field => $value) {
        $string = str_replace(' ', '', $value);
        $value = preg_replace('/[^A-Za-z0-9\-]/', '', $string);
        $sheet->getCell(Coordinate::stringFromColumnIndex($colNo) . '3')
				->setValueExplicit(html_entity_decode($value), DataType::TYPE_STRING);
        $colNo++;
    }
} else {
    foreach ($headings as $field => $value) {
        $sheet->getCell(Coordinate::stringFromColumnIndex($colNo) . '3')
				->setValueExplicit(html_entity_decode($value), DataType::TYPE_STRING);
        $colNo++;
    }
}
$sheet->getStyle('A3:CG3')->applyFromArray($styleArray);

$no = 1;
foreach ($rResult as $aRow) {
    $symptomList = [];
    $squery = "SELECT s.*, ps.* FROM form_covid19 as c19 
        INNER JOIN covid19_patient_symptoms AS ps ON c19.covid19_id = ps.covid19_id 
        INNER JOIN r_covid19_symptoms AS s ON ps.symptom_id = s.symptom_id 
        WHERE ps.symptom_detected like 'yes' AND c19.covid19_id = " . $aRow['covid19_id'];
    $result = $db->rawQuery($squery);
    foreach ($result as $symp) {
        //$symResult = (isset($symptom['symptom_detected']) && $symptom['symptom_detected'] != "") ? ($symptom['symptom_detected']) : "Unknown";
        $symptomList[] = $symp['symptom_name'];
    }

    $comorbiditiesList = [];
    $squery = "SELECT s.*, como.* FROM form_covid19 as c19 
        INNER JOIN covid19_patient_comorbidities AS como ON c19.covid19_id = como.covid19_id 
        INNER JOIN r_covid19_comorbidities AS s ON como.comorbidity_id = s.comorbidity_id 
        WHERE como.comorbidity_detected like 'yes' AND c19.covid19_id = " . $aRow['covid19_id'];
    $result = $db->rawQuery($squery);
    foreach ($result as $como) {
        $comorbiditiesList[] = $como['comorbidity_name'];
    }

    $row = [];
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
    if ($aRow['patient_dob'] != null && trim($aRow['patient_dob']) != '' && $aRow['patient_dob'] != '0000-00-00') {
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
    if ($aRow['sample_collection_date'] != null && trim($aRow['sample_collection_date']) != '' && $aRow['sample_collection_date'] != '0000-00-00 00:00:00') {
        $expStr = explode(" ", $aRow['sample_collection_date']);
        $sampleCollectionDate =  date("d-m-Y", strtotime($expStr[0]));
    }

    $sampleTestedOn = '';
    if ($aRow['sample_tested_datetime'] != null && trim($aRow['sample_tested_datetime']) != '' && $aRow['sample_tested_datetime'] != '0000-00-00') {
        $sampleTestedOn =  date("d-m-Y", strtotime($aRow['sample_tested_datetime']));
    }


    //set sample rejection
    $sampleRejection = 'No';
    if (trim($aRow['is_sample_rejected']) == 'yes' || ($aRow['reason_for_sample_rejection'] != null && trim($aRow['reason_for_sample_rejection']) != '' && $aRow['reason_for_sample_rejection'] > 0)) {
        $sampleRejection = 'Yes';
    }
    //result dispatched date
    $resultDispatchedDate = '';
    if ($aRow['result_printed_datetime'] != null && trim($aRow['result_printed_datetime']) != '' && $aRow['result_printed_datetime'] != '0000-00-00 00:00:00') {
        $expStr = explode(" ", $aRow['result_printed_datetime']);
        $resultDispatchedDate =  date("d-m-Y", strtotime($expStr[0]));
    }

    if ($aRow['patient_name'] != '') {
        $patientFname = ($general->crypto('doNothing', $aRow['patient_name'], $aRow['patient_id']));
    } else {
        $patientFname = '';
    }
    if ($aRow['patient_surname'] != '') {
        $patientLname = ($general->crypto('doNothing', $aRow['patient_surname'], $aRow['patient_id']));
    } else {
        $patientLname = '';
    }

    if (isset($aRow['source_of_alert']) && $aRow['source_of_alert'] != "others") {
        $sourceOfArtPOE = str_replace("-", " ", $aRow['source_of_alert']);
    } else {
        $sourceOfArtPOE = $aRow['source_of_alert_other'];
    }



    $row[] = $no;
    $row[] = $aRow["sample_code"];
    $row[] = $aRow["remote_sample_code"];
    $row[] = ($aRow['lab_name']);
    $row[] = ($aRow['testing_point']);
    $row[] = ($aRow['labTechnician']);
    $row[] = ($sourceOfArtPOE);
    $row[] = ($aRow['facility_district']);
    $row[] = ($aRow['facility_state']);
    $row[] = ($aRow['facility_name']);
    if (isset($_POST['patientInfo']) && $_POST['patientInfo'] == 'yes') {
        $row[] = $aRow['patient_id'];
        $row[] = $patientFname . " " . $patientLname;
    }
    $row[] = DateUtils::humanReadableDateFormat($aRow['patient_dob']);
    $row[] = ($aRow['patient_age'] != null && trim($aRow['patient_age']) != '' && $aRow['patient_age'] > 0) ? $aRow['patient_age'] : 0;
    $row[] = ($aRow['patient_gender']);
    $row[] = ($aRow['is_patient_pregnant']);
    $row[] = ($aRow['patient_phone_number']);
    $row[] = ($aRow['patient_email']);
    $row[] = ($aRow['patient_address']);
    $row[] = ($aRow['patient_province']);
    $row[] = ($aRow['patient_district']);
    $row[] = ($aRow['patient_city']);
    $row[] = ($aRow['nationality']);
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
    $row[] = DateUtils::humanReadableDateFormat($aRow['date_of_symptom_onset']);
    $row[] = DateUtils::humanReadableDateFormat($aRow['date_of_initial_consultation']);
    $row[] = DateUtils::humanReadableDateFormat($aRow['sample_collection_date']);
    $row[] = ($aRow['test_reason_name']);
    $row[] = DateUtils::humanReadableDateFormat($aRow['sample_received_at_vl_lab_datetime']);
    $row[] = DateUtils::humanReadableDateFormat($aRow['request_created_datetime']);
    $row[] = ($aRow['sample_condition']);
    $row[] = ($aRow['status_name']);
    $row[] = ($aRow['sample_name']);
    $row[] = DateUtils::humanReadableDateFormat($aRow['sample_tested_datetime']);
    $row[] = ($aRow['covid19_test_platform']);
    $row[] = ($aRow['covid19_test_name']);
    $row[] = $covid19Results[$aRow['result']];
    $row[] = DateUtils::humanReadableDateFormat($aRow['result_printed_datetime']);

    $output[] = $row;
    $no++;
}

$start = (count($output)) + 2;
foreach ($output as $rowNo => $rowData) {
    $colNo = 1;
    $rRowCount = $rowNo + 4;
    foreach ($rowData as $field => $value) {
        $sheet->setCellValue(
            Coordinate::stringFromColumnIndex($colNo) . $rRowCount,
            html_entity_decode($value)
        );
        $colNo++;
    }
}
$writer = IOFactory::createWriter($excel, 'Xlsx');
$filename = 'Covid-19-Export-Data-' . date('d-M-Y-H-i-s') . '.xlsx';
$writer->save(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
echo base64_encode(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
