<?php
if (session_status() == PHP_SESSION_NONE) {
	session_start();
}
ob_start();



$general = new \Vlsm\Models\General();
$hepatitisDb = new \Vlsm\Models\Hepatitis();

$hepatitisResults = $hepatitisDb->getHepatitisResults();
//system config
$systemConfigQuery = "SELECT * from system_config";
$systemConfigResult = $db->query($systemConfigQuery);
$sarr = array();
// now we create an associative array so that we can easily create view variables
for ($i = 0; $i < sizeof($systemConfigResult); $i++) {
	$sarr[$systemConfigResult[$i]['name']] = $systemConfigResult[$i]['value'];
}
// die($_SESSION['hepatitisResultQuery']);
if (isset($_SESSION['hepatitisResultQuery']) && trim($_SESSION['hepatitisResultQuery']) != "") {

	$rResult = $db->rawQuery($_SESSION['hepatitisResultQuery']);

	$excel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
	$output = array();
	$sheet = $excel->getActiveSheet();

	$headings = array("S.No.", "Sample Code", "Health Facility Name", "Health Facility Code", "District/County", "Province/State", "Patient ID", "Patient Name", "Patient DoB", "Patient Age", "Patient Gender", "Sample Collection Date", "Is Sample Rejected?", "Sample Tested On", "Result", "Sample Received On", "Date Result Dispatched", "Comments", "Funding Source", "Implementing Partner");

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
	$sysmtomsArr = array();
	$comorbiditiesArr = array();
	foreach ($rResult as $aRow) {
		$row = array();
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
		if ($aRow['result_printed_datetime'] != null && trim($aRow['result_printed_datetime']) != '' && $aRow['result_dispatched_datetime'] != '0000-00-00 00:00:00') {
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

		$row[] = $no;
		$row[] = $aRow[$sampleCode];
		$row[] = ucwords($aRow['facility_name']);
		$row[] = $aRow['facility_code'];
		$row[] = ucwords($aRow['facility_district']);
		$row[] = ucwords($aRow['facility_state']);
		$row[] = $aRow['patient_id'];
		$row[] = $patientFname . " " . $patientLname;
		$row[] = $dob;
		$row[] = ($aRow['patient_age'] != null && trim($aRow['patient_age']) != '' && $aRow['patient_age'] > 0) ? $aRow['patient_age'] : 0;
		$row[] = $gender;
		$row[] = $sampleCollectionDate;
		$row[] = $sampleRejection;
		$row[] = $sampleTestedOn;
		$row[] = $hepatitisResults[$aRow['result']];
		$row[] = $general->humanReadableDateFormat($aRow['sample_received_at_vl_lab_datetime']);
		$row[] = $resultDispatchedDate;
		$row[] = ucfirst($aRow['lab_tech_comments']);
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
	$filename = 'Hepatitis-Export-Data-' . date('d-M-Y-H-i-s') . '.xlsx';
	$writer->save(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
	echo $filename;
}
