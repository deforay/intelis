<?php
if (session_status() == PHP_SESSION_NONE) {
	session_start();
}
ob_start();




$general = new \Vlsm\Models\General();
//system config
$systemConfigQuery = "SELECT * from system_config";
$systemConfigResult = $db->query($systemConfigQuery);
$sarr = array();
// now we create an associative array so that we can easily create view variables
for ($i = 0; $i < sizeof($systemConfigResult); $i++) {
	$sarr[$systemConfigResult[$i]['name']] = $systemConfigResult[$i]['value'];
}

$sQuery = "SELECT SQL_CALC_FOUND_ROWS 
                        vl.vl_sample_id,
                        vl.sample_code,
                        vl.remote_sample_code,
                        vl.patient_art_no,
                        vl.patient_first_name,
                        vl.patient_middle_name,
                        vl.patient_last_name,
                        vl.patient_dob,
                        vl.patient_gender,
                        vl.patient_age_in_years,
                        vl.sample_collection_date,
                        vl.treatment_initiated_date,
                        vl.date_of_initiation_of_current_regimen,
                        vl.test_requested_on,
                        vl.sample_tested_datetime,
                        vl.arv_adherance_percentage,
                        vl.is_sample_rejected,
                        vl.reason_for_sample_rejection,
                        vl.result_value_log,
                        vl.result_value_absolute,
                        vl.result,
                        vl.current_regimen,
                        vl.is_patient_pregnant,
                        vl.is_patient_breastfeeding,
                        vl.request_clinician_name,
                        vl.approver_comments,
                        vl.sample_received_at_hub_datetime,							
                        vl.sample_received_at_vl_lab_datetime,							
                        vl.result_dispatched_datetime,	
                        vl.result_printed_datetime,	
                        vl.last_modified_datetime,
                        vl.result_status,
                        vl.locked,
                        vl.data_sync,
                        s.sample_name,
                        b.batch_code,
                        ts.status_name,
                        f.facility_name,
                        testingLab.facility_name as lab_name,
                        f.facility_code,
                        f.facility_state,
                        f.facility_district,
                        u_d.user_name as reviewedBy,
                        a_u_d.user_name as approvedBy,
                        rs.rejection_reason_name,
                        tr.test_reason_name,
                        r_f_s.funding_source_name,
                        r_i_p.i_partner_name 
                        
                        FROM form_vl as vl 
                        
                        LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id 
                        LEFT JOIN facility_details as testingLab ON vl.lab_id=testingLab.facility_id 
                        LEFT JOIN r_vl_sample_type as s ON s.sample_id=vl.sample_type 
                        LEFT JOIN r_sample_status as ts ON ts.status_id=vl.result_status 
                        LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id 
                        LEFT JOIN user_details as u_d ON u_d.user_id=vl.result_reviewed_by 
                        LEFT JOIN user_details as a_u_d ON a_u_d.user_id=vl.result_approved_by 
                        LEFT JOIN r_vl_sample_rejection_reasons as rs ON rs.rejection_reason_id=vl.reason_for_sample_rejection 
                        LEFT JOIN r_vl_test_reasons as tr ON tr.test_reason_id=vl.reason_for_vl_testing 
                        LEFT JOIN r_funding_sources as r_f_s ON r_f_s.funding_source_id=vl.funding_source 
                        LEFT JOIN r_implementation_partners as r_i_p ON r_i_p.i_partner_id=vl.implementing_partner";

$start_date = '';
$end_date = '';
if (isset($_POST['sampleCollectionDate']) && trim($_POST['sampleCollectionDate']) != '') {
	$s_c_date = explode("to", $_POST['sampleCollectionDate']);
	if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
		$start_date = $general->dateFormat(trim($s_c_date[0]));
	}
	if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
		$end_date = $general->dateFormat(trim($s_c_date[1]));
	}
}

$sWhere = array();
if (isset($_POST['batchCode']) && trim($_POST['batchCode']) != '') {
	$sWhere[] = ' b.batch_code = "' . $_POST['batchCode'] . '"';
}
if (isset($_POST['sampleCollectionDate']) && trim($_POST['sampleCollectionDate']) != '') {
	if (trim($start_date) == trim($end_date)) {
		$sWhere[] = ' DATE(vl.sample_collection_date) = "' . $start_date . '"';
	} else {
		$sWhere[] = ' DATE(vl.sample_collection_date) >= "' . $start_date . '" AND DATE(vl.sample_collection_date) <= "' . $end_date . '"';
	}
}
if (isset($_POST['sampleType']) && trim($_POST['sampleType']) != '') {
	$sWhere[] = ' s.sample_id = "' . $_POST['sampleType'] . '"';
}
if (isset($_POST['facilityName']) && trim($_POST['facilityName']) != '') {
	$sWhere[] = ' f.facility_id IN (' . $_POST['facilityName'] . ')';
}
if (isset($_POST['vlLab']) && trim($_POST['vlLab']) != '') {
	$sWhere[] = ' vl.lab_id IN (' . $_POST['vlLab'] . ')';
}
if (isset($_POST['gender']) && trim($_POST['gender']) != '') {
	if (trim($_POST['gender']) == "not_recorded") {
		$sWhere[] = ' (vl.patient_gender="not_recorded" OR vl.patient_gender="" OR vl.patient_gender IS NULL)';
	} else {
		$sWhere[] = ' vl.patient_gender IN ("' . $_POST['gender'] . '")';
	}
}
if (isset($_POST['showReordSample']) && trim($_POST['showReordSample']) != '') {
	$sWhere[] = ' vl.sample_reordered IN ("' . $_POST['showReordSample'] . '")';
}

if (isset($_POST['patientPregnant']) && trim($_POST['patientPregnant']) != '') {
	$sWhere[] = ' vl.is_patient_pregnant IN ("' . $_POST['patientPregnant'] . '")';
}

if (isset($_POST['breastFeeding']) && trim($_POST['breastFeeding']) != '') {
	$sWhere[] = ' vl.is_patient_breastfeeding IN ("' . $_POST['breastFeeding'] . '")';
}
if (isset($_POST['fundingSource']) && trim($_POST['fundingSource']) != '') {
	$sWhere[] = ' vl.funding_source IN ("' . base64_decode($_POST['fundingSource']) . '")';
}
if (isset($_POST['implementingPartner']) && trim($_POST['implementingPartner']) != '') {
	$sWhere[] = ' vl.implementing_partner IN ("' . base64_decode($_POST['implementingPartner']) . '")';
}
if (isset($_POST['district']) && trim($_POST['district']) != '') {
	$sWhere[] = " f.facility_district LIKE '%" . $_POST['district'] . "%' ";
}
if (isset($_POST['state']) && trim($_POST['state']) != '') {
	$sWhere[] = " f.facility_state LIKE '%" . $_POST['state'] . "%' ";
}


if (isset($_POST['reqSampleType']) && trim($_POST['reqSampleType']) == 'result') {
	$sWhere[] = ' vl.result != "" ';
} else if (isset($_POST['reqSampleType']) && trim($_POST['reqSampleType']) == 'noresult') {
	$sWhere[] = ' (vl.result IS NULL OR vl.result = "") ';
}
if (isset($_POST['srcOfReq']) && trim($_POST['srcOfReq']) != '') {
	$sWhere[] = ' vl.source_of_request like "' . $_POST['srcOfReq'] . '" ';
}

$sFilter = '';
if ($_SESSION['instanceType'] == 'remoteuser') {
	if (!empty($facilityMap)) {
		$sWhere[] = " vl.facility_id IN (" . $facilityMap . ")  ";
		$sFilter = " AND vl.facility_id IN (" . $facilityMap . ") ";
	}
} else {
	$sWhere[] = ' vl.result_status!=9';
	$sFilter = ' AND result_status!=9';
}
$sQuery = $sQuery . ' where' . implode(" AND ", $sWhere);

if (isset($sOrder) && $sOrder != "") {
	$sOrder = preg_replace('/(\v|\s)+/', ' ', $sOrder);
	$sQuery = $sQuery . " ORDER BY " . $sOrder;
}
$_SESSION['vlRequestSearchResultQuery'] = $sQuery;
if (isset($sLimit) && isset($sOffset)) {
	$sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
}

// echo ($sQuery);die;

$rResult = $db->rawQuery($sQuery);

$excel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$output = array();
$sheet = $excel->getActiveSheet();

$headings = array("No.", "Sample Code", "Testing Lab", "Health Facility Name", "Health Facility Code", "District/County", "Province/State", "Unique ART No.", "Patient Name", "Date of Birth", "Age", "Gender", "Date of Sample Collection", "Sample Type", "Date of Treatment Initiation", "Current Regimen", "Date of Initiation of Current Regimen", "Is Patient Pregnant?", "Is Patient Breastfeeding?", "ARV Adherence", "Indication for Viral Load Testing", "Requesting Clinican", "Request Date", "Is Sample Rejected?", "Sample Tested On", "Result (cp/ml)", "Result (log)", "Sample Receipt Date", "Date Result Dispatched", "Comments", "Funding Source", "Implementing Partner");
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
		'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
	),
	'borders' => array(
		'outline' => array(
			'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
		),
	)
);

$sheet->mergeCells('A1:AG1');
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
$sheet->getStyle('A3:AG3')->applyFromArray($styleArray);

$no = 1;
foreach ($rResult as $aRow) {
	$row = array();
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
	//treatment initiation date
	$treatmentInitiationDate = '';
	if ($aRow['treatment_initiated_date'] != NULL && trim($aRow['treatment_initiated_date']) != '' && $aRow['treatment_initiated_date'] != '0000-00-00') {
		$treatmentInitiationDate =  date("d-m-Y", strtotime($aRow['treatment_initiated_date']));
	}
	//date of initiation of current regimen
	$dateOfInitiationOfCurrentRegimen = '';
	if ($aRow['date_of_initiation_of_current_regimen'] != NULL && trim($aRow['date_of_initiation_of_current_regimen']) != '' && $aRow['date_of_initiation_of_current_regimen'] != '0000-00-00') {
		$dateOfInitiationOfCurrentRegimen =  date("d-m-Y", strtotime($aRow['date_of_initiation_of_current_regimen']));
	}
	//requested date
	$requestedDate = '';
	if ($aRow['test_requested_on'] != NULL && trim($aRow['test_requested_on']) != '' && $aRow['test_requested_on'] != '0000-00-00') {
		$requestedDate =  date("d-m-Y", strtotime($aRow['test_requested_on']));
	}


	$sampleTestedOn = '';
	if ($aRow['sample_tested_datetime'] != NULL && trim($aRow['sample_tested_datetime']) != '' && $aRow['sample_tested_datetime'] != '0000-00-00') {
		$sampleTestedOn =  date("d-m-Y", strtotime($aRow['sample_tested_datetime']));
	}

	$sampleReceivedOn = '';
	if ($aRow['sample_received_at_vl_lab_datetime'] != NULL && trim($aRow['sample_received_at_vl_lab_datetime']) != '' && $aRow['sample_received_at_vl_lab_datetime'] != '0000-00-00') {
		$sampleReceivedOn =  date("d-m-Y", strtotime($aRow['sample_received_at_vl_lab_datetime']));
	}

	//set ARV adherecne
	$arvAdherence = '';
	if (trim($aRow['arv_adherance_percentage']) == 'good') {
		$arvAdherence = 'Good >= 95%';
	} else if (trim($aRow['arv_adherance_percentage']) == 'fair') {
		$arvAdherence = 'Fair 85-94%';
	} else if (trim($aRow['arv_adherance_percentage']) == 'poor') {
		$arvAdherence = 'Poor <85%';
	}
	//set sample rejection
	$sampleRejection = 'No';
	if (trim($aRow['is_sample_rejected']) == 'yes' || ($aRow['reason_for_sample_rejection'] != NULL && trim($aRow['reason_for_sample_rejection']) != '' && $aRow['reason_for_sample_rejection'] > 0)) {
		$sampleRejection = 'Yes';
	}
	//result dispatched date
	$lastViralLoadTest = '';
	if ($aRow['last_viral_load_date'] != NULL && trim($aRow['last_viral_load_date']) != '' && $aRow['last_viral_load_date'] != '0000-00-00 00:00:00') {
		$expStr = explode(" ", $aRow['last_viral_load_date']);
		$lastViralLoadTest =  date("d-m-Y", strtotime($expStr[0]));
	}

	//result dispatched date
	$resultDispatchedDate = '';
	if ($aRow['result_printed_datetime'] != NULL && trim($aRow['result_printed_datetime']) != '' && $aRow['result_dispatched_datetime'] != '0000-00-00 00:00:00') {
		$expStr = explode(" ", $aRow['result_printed_datetime']);
		$resultDispatchedDate =  date("d-m-Y", strtotime($expStr[0]));
	}
	//TAT result dispatched(in days)
	$tatdays = '';
	if (trim($sampleCollectionDate) != '' && trim($resultDispatchedDate) != '') {
		$sample_collection_date = strtotime($sampleCollectionDate);
		$result_dispatched_date = strtotime($resultDispatchedDate);
		$dayDiff = $result_dispatched_date - $sample_collection_date;
		$tatdays = (int)floor($dayDiff / (60 * 60 * 24));
	}
	//set result log value
	$logVal = '';
	if ($aRow['result_value_log'] != NULL && trim($aRow['result_value_log']) != '') {
		$logVal = round($aRow['result_value_log'], 1);
	} else if ($aRow['result_value_absolute'] != NULL && trim($aRow['result_value_absolute']) != '' && $aRow['result_value_absolute'] > 0) {
		$logVal = round(log10((float)$aRow['result_value_absolute']), 1);
	}
	if ($_SESSION['instanceType'] == 'remoteuser') {
		$sampleCode = 'remote_sample_code';
	} else {
		$sampleCode = 'sample_code';
	}

	if ($aRow['patient_first_name'] != '') {
		$patientFname = ucwords($general->crypto('decrypt', $aRow['patient_first_name'], $aRow['patient_art_no']));
	} else {
		$patientFname = '';
	}
	if ($aRow['patient_middle_name'] != '') {
		$patientMname = ucwords($general->crypto('decrypt', $aRow['patient_middle_name'], $aRow['patient_art_no']));
	} else {
		$patientMname = '';
	}
	if ($aRow['patient_last_name'] != '') {
		$patientLname = ucwords($general->crypto('decrypt', $aRow['patient_last_name'], $aRow['patient_art_no']));
	} else {
		$patientLname = '';
	}

	$row[] = $no;
	$row[] = $aRow[$sampleCode];
	$row[] = $aRow['lab_name'];
	$row[] = $aRow['facility_name'];
	$row[] = $aRow['facility_code'];
	$row[] = ($aRow['facility_district']);
	$row[] = ($aRow['facility_state']);
	$row[] = $aRow['patient_art_no'];
	$row[] = ($patientFname . " " . $patientMname . " " . $patientLname);
	$row[] = $dob;
	$row[] = ($aRow['patient_age_in_years'] != NULL && trim($aRow['patient_age_in_years']) != '' && $aRow['patient_age_in_years'] > 0) ? $aRow['patient_age_in_years'] : 0;
	$row[] = $gender;
	$row[] = $sampleCollectionDate;
	$row[] = (isset($aRow['sample_name'])) ? ucwords($aRow['sample_name']) : '';
	$row[] = $treatmentInitiationDate;
	$row[] = $aRow['current_regimen'];
	$row[] = $dateOfInitiationOfCurrentRegimen;
	$row[] = ucfirst($aRow['is_patient_pregnant']);
	$row[] = ucfirst($aRow['is_patient_breastfeeding']);
	$row[] = $arvAdherence;
	$row[] = ucwords(str_replace("_", " ", $aRow['test_reason_name']));
	$row[] = ucwords($aRow['request_clinician_name']);
	$row[] = $requestedDate;
	$row[] = $sampleRejection;
	$row[] = $sampleTestedOn;
	$row[] = $aRow['result'];
	$row[] = $logVal;
	$row[] = $sampleReceivedOn;
	$row[] = $resultDispatchedDate;
	//$row[] = $tatdays;
	$row[] = ucfirst($aRow['approver_comments']);
	$row[] = (isset($aRow['funding_source_name']) && trim($aRow['funding_source_name']) != '') ? ucwords($aRow['funding_source_name']) : '';
	$row[] = (isset($aRow['i_partner_name']) && trim($aRow['i_partner_name']) != '') ? ucwords($aRow['i_partner_name']) : '';
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
$filename = 'VLSM-VIRAL-LOAD-REQUEST-Data-' . date('d-M-Y-H-i-s') . '.xlsx';
$writer->save(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
echo $filename;
