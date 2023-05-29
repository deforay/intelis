<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;



/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);


// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = $GLOBALS['request'];
$_GET = $request->getQueryParams();
$id = (isset($_GET['id'])) ? base64_decode($_GET['id']) : null;


$showPatientName = false;
if (isset($_GET['type']) && $_GET['type'] == 'vl') {
    $refTable = "form_vl";
    $refPrimaryColumn = "vl_sample_id";
    $patientIdColumn = 'patient_art_no';
    $patientFirstName = 'patient_first_name';
    $patientLastName = 'patient_last_name';
    $worksheetName = 'Viral Load Test Worksheet';
} elseif (isset($_GET['type']) && $_GET['type'] == 'eid') {
    $refTable = "form_eid";
    $refPrimaryColumn = "eid_id";
    $patientIdColumn = 'child_id';
    $patientFirstName = 'child_name';
    $patientLastName = 'child_surname';
    $worksheetName = 'EID Test Worksheet';
} elseif (isset($_GET['type']) && $_GET['type'] == 'covid19') {
    $refTable = "form_covid19";
    $refPrimaryColumn = "covid19_id";
    $patientIdColumn = 'patient_id';
    $patientFirstName = 'patient_name';
    $patientLastName = 'patient_surname';
    $worksheetName = 'Covid-19 Test Worksheet';
} elseif (isset($_GET['type']) && $_GET['type'] == 'hepatitis') {
    $refTable = "form_hepatitis";
    $refPrimaryColumn = "hepatitis_id";
    $patientIdColumn = 'patient_id';
    $patientFirstName = 'patient_name';
    $patientLastName = 'patient_surname';
    $worksheetName = 'Hepatitis Test Worksheet';
    $showPatientName = true;
} elseif (isset($_GET['type']) && $_GET['type'] == 'tb') {
    $refTable = "form_tb";
    $refPrimaryColumn = "tb_id";
    $patientIdColumn = 'patient_id';
    $patientFirstName = 'patient_name';
    $patientLastName = 'patient_surname';
    $worksheetName = 'TB Test Worksheet';
    $showPatientName = true;
}


$barcodeFormat = $general->getGlobalConfig('barcode_format');

$barcodeFormat = isset($barcodeFormat) && $barcodeFormat != null ? $barcodeFormat : 'C39';

if ($id > 0) {

    if (!file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "barcode") && !is_dir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "barcode")) {
        mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "barcode", 0777, true);
    }
    $lQuery = "SELECT * from global_config where name='logo'";
    $lResult = $db->query($lQuery);

    $tQuery = "SELECT * from global_config where name='header'";
    $tResult = $db->query($tQuery);

    $bQuery = "SELECT * FROM batch_details as b_d
                    LEFT JOIN instruments as i_c ON i_c.config_id=b_d.machine
                    LEFT JOIN user_details as u ON u.user_id=b_d.created_by
                    WHERE batch_id=?";
    $bResult = $db->rawQuery($bQuery, [$id]);

    if (isset($_GET['type']) && $_GET['type'] == 'covid19') {
        $dateQuery = "SELECT ct.*,
                        covid19.sample_tested_datetime,
                        result_reviewed_datetime, lot_number,
                        lot_expiration_date, result_printed_datetime
                    FROM $refTable as covid19
                    LEFT JOIN covid19_tests as ct on covid19.covid19_id = ct.covid19_id
                    WHERE sample_batch_id='" . $id . "' AND (covid19.sample_tested_datetime IS NOT NULL AND covid19.sample_tested_datetime not like '' AND covid19.sample_tested_datetime  not like '0000-00-00 00:00:00') GROUP BY covid19.covid19_id LIMIT 1";
    } else {
        $dateQuery = "SELECT sample_tested_datetime,result_reviewed_datetime from $refTable where sample_batch_id='" . $id . "' AND (sample_tested_datetime IS NOT NULL AND sample_tested_datetime not like '' AND sample_tested_datetime  not like '0000-00-00 00:00:00') LIMIT 1";
    }
    $dateResult = $db->query($dateQuery);
    $resulted = '';
    $reviewed = '';
    $createdBy = '';
    if (isset($dateResult[0]['sample_tested_datetime']) && $dateResult[0]['sample_tested_datetime'] != '' && $dateResult[0]['sample_tested_datetime'] != null && $dateResult[0]['sample_tested_datetime'] != '0000-00-00 00:00:00') {
        $sampleTestedDate = explode(" ", $dateResult[0]['sample_tested_datetime']);
        $resulted = DateUtility::humanReadableDateFormat($sampleTestedDate[0]) . " " . $sampleTestedDate[1];
    }
    if (isset($dateResult[0]['result_reviewed_datetime']) && $dateResult[0]['result_reviewed_datetime'] != '' && $dateResult[0]['result_reviewed_datetime'] != null && $dateResult[0]['result_reviewed_datetime'] != '0000-00-00 00:00:00') {
        $resultReviewdDate = explode(" ", $dateResult[0]['result_reviewed_datetime']);
        $reviewed = DateUtility::humanReadableDateFormat($resultReviewdDate[0]) . " " . $resultReviewdDate[1];
    }

    if (!empty($bResult)) {
        // Extend the TCPDF class to create custom Header and Footer
        class BatchPdf extends TCPDF
        {
            public $logo;
            public $text;
            public $batch;
            public $resulted;
            public $reviewed;
            public $createdBy;
            public $worksheetName;


            public function setHeading($logo, $text, $batch, $resulted, $reviewed, $createdBy, $worksheetName)
            {
                $this->logo = $logo;
                $this->text = $text;
                $this->batch = $batch;
                $this->resulted = $resulted;
                $this->reviewed = $reviewed;
                $this->createdBy = $createdBy;
                $this->worksheetName = $worksheetName;
            }
            public function imageExists($filePath): bool
            {
                return (!empty($filePath) && file_exists($filePath) && !is_dir($filePath) && filesize($filePath) > 0 && false !== getimagesize($filePath));
            }
            //Page header
            public function Header()
            {
                // Logo
                //$imageFilePath = K_PATH_IMAGES.'logo_example.jpg';
                //$this->Image($imageFilePath, 10, 10, 15, '', 'JPG', '', 'T', false, 300, '', false, false, 0, false, false, false);
                // Set font
                if (trim($this->logo) != "") {
                    if ($this->imageExists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo)) {
                        $imageFilePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo;
                        $this->Image($imageFilePath, 15, 10, 15, '', '', '', 'T');
                    }
                }
                $this->SetFont('helvetica', '', 7);
                $this->writeHTMLCell(30, 0, 10, 26, $this->text, 0, 0, 0, true, 'A');
                $this->SetFont('helvetica', '', 13);
                $this->writeHTMLCell(0, 0, 0, 10, 'Batch Number/Code : ' . $this->batch, 0, 0, 0, true, 'C');
                $this->writeHTMLCell(0, 0, 0, 20, $this->worksheetName, 0, 0, 0, true, 'C');
                $this->SetFont('helvetica', '', 9);
                $this->writeHTMLCell(0, 0, 144, 10, 'Result On : ' . $this->resulted, 0, 0, 0, true, 'C');
                $this->writeHTMLCell(0, 0, 144, 16, 'Reviewed On : ' . $this->reviewed, 0, 0, 0, true, 'C');
                $this->writeHTMLCell(0, 0, 144, 22, 'Created By : ' . $this->createdBy, 0, 0, 0, true, 'C');
                $html = '<hr/>';
                $this->writeHTMLCell(0, 0, 10, 32, $html, 0, 0, 0, true, 'J');
            }

            // Page footer
            public function Footer()
            {
                // Position at 15 mm from bottom
                $this->SetY(-15);
                // Set font
                $this->SetFont('helvetica', 'I', 8);
                // Page number
                $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C', 0);
            }
        }

        // create new PDF document
        $pdf = new BatchPdf(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        $pdf->setHeading($lResult[0]['value'], $tResult[0]['value'], $bResult[0]['batch_code'], $resulted, $reviewed, $bResult[0]['user_name'], $worksheetName);

        // set document information
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('VLSM');
        $pdf->SetTitle('VLSM BATCH');
        $pdf->SetSubject('VLSM BATCH');
        $pdf->SetKeywords('VLSM BATCH');

        $pdf->SetMargins(0, 0, 0);
        $pdf->SetHeaderMargin(0);
        $pdf->SetFooterMargin(0);

        // set default header data
        $pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, PDF_HEADER_TITLE, PDF_HEADER_STRING);

        // set header and footer fonts
        $pdf->setHeaderFont(array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
        $pdf->setFooterFont(array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

        // set default monospaced font
        $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

        // set margins
        $pdf->SetMargins(PDF_MARGIN_LEFT, 36, PDF_MARGIN_RIGHT);
        $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
        $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

        // set auto page breaks
        $pdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);

        // set image scale factor
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);



        // set font
        $pdf->SetFont('helvetica', '', 10);

        // add a page
        $pdf->AddPage();
        if (isset($_GET['type']) && $_GET['type'] == 'covid19') {
            if (isset($dateResult['kit_expiry_date']) && $dateResult['kit_expiry_date'] != "" && $dateResult['kit_expiry_date'] != null) {
                $dateResult['kit_expiry_date'] = date("d-M-Y", strtotime($dateResult['kit_expiry_date']));
            }
            if (isset($dateResult['lot_expiration_date']) && $dateResult['lot_expiration_date'] != "" && $dateResult['lot_expiration_date'] != null) {
                $dateResult['lot_expiration_date'] = date("d-M-Y", strtotime($dateResult['lot_expiration_date']));
            }
            if (isset($dateResult['result_printed_datetime']) && $dateResult['result_printed_datetime'] != "" && $dateResult['result_printed_datetime'] != null) {
                $dateResult['result_printed_datetime'] = date("d-M-Y", strtotime($dateResult['result_printed_datetime']));
            }
            $tbl = '<table cellspacing="2" cellpadding="6" style="width:100%;" border="0">
                <tr>
                    <th style="font-weight: bold;">Reagent/Kit Name :</th><td>' . ((isset($dateResult['test_name']) && $dateResult['test_name'] != "") ? $dateResult['test_name'] : "") . '</td>
                    <th style="font-weight: bold;">Lot Number :</th><td>' . ((isset($dateResult['kit_lot_no']) && $dateResult['kit_lot_no'] != "") ? $dateResult['kit_lot_no'] : $dateResult['lot_number']) . '</td>
                </tr>
                <tr>
                    <th style="font-weight: bold;">Lot Expiry Date :</th><td>' . ((isset($dateResult['kit_expiry_date']) && $dateResult['kit_expiry_date'] != "") ? $dateResult['kit_expiry_date'] : $dateResult['lot_expiration_date']) . '</td>
                    <th style="font-weight: bold;">Printed By :</th><td>' . $_SESSION['userName'] . '</td>
                    </tr>
                    <tr>
                    <th style="font-weight: bold;">Printed Date/Time :</th><td colspan="3">' . date("d-M-Y h:i:A") . '</td>
                </tr>
            </table>
            <hr>
            <table nobr="true" cellspacing="0" cellpadding="2" style="width:100%;">
                <tr style="border-bottom:1px solid #333 !important;">
                    <th align="center" width="5%"><strong>' . _('Pos.') . '</strong></th>
                    <th align="center" width="20%"><strong>' . _('Sample Code') . '</strong></th>
                    <th align="center" width="30%"><strong>' . _('BARCODE') . '</strong></th>
                    <th align="center" width="20%"><strong>' . _('Remote Sample Code') . '</strong></th>
                    <th align="center" width="12.5%"><strong>' . _('Patient Code') . '</strong></th>
                    <th align="center" width="12.5%"><strong>' . _('Test Result') . '</strong></th>
                </tr>';
            $tbl .= '</table>';
        } else {
            $tbl = '<table nobr="true" cellspacing="0" cellpadding="2" style="width:100%;">
                    <tr style="border-bottom:1px solid #333 !important;">
                        <th align="center" width="6%"><strong>Pos.</strong></th>
                        <th align="center" width="20%"><strong>Sample ID</strong></th>
                        <th align="center" width="35%"><strong>BARCODE</strong></th>
                        <th align="center" width="13%"><strong>Patient Code</strong></th>
                        <th align="center" width="13%"><strong>Lot No. / <br>Exp. Date</strong></th>
                        <th align="center" width="13%"><strong>Test Result</strong></th>
                    </tr>';
            $tbl .= '</table>';
        }
        if (isset($bResult[0]['label_order']) && trim($bResult[0]['label_order']) != '') {
            $jsonToArray = json_decode($bResult[0]['label_order'], true);
            $sampleCounter = 1;
            if (isset($bResult[0]['position_type']) && $bResult[0]['position_type'] == 'alpha-numeric') {
                foreach ($general->excelColumnRange('A', 'H') as $value) {
                    foreach (range(1, 12) as $no) {
                        $alphaNumeric[] = $value . $no;
                    }
                }
                $sampleCounter = $alphaNumeric[0];
            }
            for ($j = 0; $j < count($jsonToArray); $j++) {
                // if($pdf->getY()>=250){
                //     $pdf->AddPage();
                // }
                if (isset($bResult[0]['position_type']) && $bResult[0]['position_type'] == 'alpha-numeric') {
                    $xplodJsonToArray = explode("_", $jsonToArray[$alphaNumeric[$j]]);
                    if (count($xplodJsonToArray) > 1 && $xplodJsonToArray[0] == "s") {
                        if (isset($_GET['type']) && $_GET['type'] == 'tb') {
                            $sampleQuery = "SELECT sample_code,remote_sample_code,result,$patientIdColumn, $patientFirstName, $patientLastName from $refTable where $refPrimaryColumn = ?";
                        } else {
                            $sampleQuery = "SELECT sample_code,remote_sample_code,result,lot_number,lot_expiration_date,$patientIdColumn, $patientFirstName, $patientLastName from $refTable where $refPrimaryColumn =?";
                        }
                        $sampleResult = $db->rawQuery($sampleQuery, [$xplodJsonToArray[1]]);

                        // $params = $pdf->serializeTCPDFtagParameters(array($sampleResult[0]['sample_code'], $barcodeFormat, '', '', '', 7, 0.25, array('border' => false, 'align' => 'C', 'padding' => 1, 'fgcolor' => array(0, 0, 0), 'bgcolor' => array(255, 255, 255), 'text' => false, 'font' => 'helvetica', 'fontsize' => 7, 'stretchtext' => 2), 'N'));
                        $lotDetails = '';
                        $lotExpirationDate = '';
                        if (isset($sampleResult[0]['lot_expiration_date']) && $sampleResult[0]['lot_expiration_date'] != '' && $sampleResult[0]['lot_expiration_date'] != null && $sampleResult[0]['lot_expiration_date'] != '0000-00-00') {
                            if (trim($sampleResult[0]['lot_number']) != '') {
                                $lotExpirationDate .= '<br>';
                            }
                            $lotExpirationDate .= DateUtility::humanReadableDateFormat($sampleResult[0]['lot_expiration_date']);
                        }
                        $lotDetails = $sampleResult[0]['lot_number'] . $lotExpirationDate;
                        $tbl .= '<table nobr="true" cellspacing="0" cellpadding="2" style="width:100%;">';
                        $tbl .= '<tr nobr="true" style="border-bottom:1px solid #333;width:100%;">';
                        if (isset($_GET['type']) && $_GET['type'] == 'covid19') {

                            $tbl .= '<td  align="center" width="5%" style="vertical-align:middle;border-bottom:1px solid #333;">' . $sampleCounter . '.</td>';
                            $tbl .= '<td  align="center" width="20%" style="vertical-align:middle;border-bottom:1px solid #333;">' . $sampleResult[0]['sample_code'] . '</td>';
                            $tbl .= '<td  align="center" width="30%" style="vertical-align:middle !important;border-bottom:1px solid #333;"><img style="width:200px;height:30px;" src="' . $general->getBarcodeImageContent($sampleResult[0]['sample_code'], $barcodeFormat) . '"></td>';
                            $tbl .= '<td  align="center" width="20%" style="vertical-align:middle;border-bottom:1px solid #333;">' . $sampleResult[0]['remote_sample_code'] . '</td>';
                            $tbl .= '<td  align="center" width="12.5%" style="vertical-align:middle;border-bottom:1px solid #333;font-size:0.9em;">' . $sampleResult[0][$patientIdColumn] . '</td>';
                            $tbl .= '<td  align="center" width="12.5%" style="vertical-align:middle;border-bottom:1px solid #333;">' . $sampleResult[0]['result'] . '</td>';
                        } else {

                            $tbl .= '<td  align="center" width="6%" style="vertical-align:middle;border-bottom:1px solid #333;">' . $sampleCounter . '.</td>';
                            $tbl .= '<td  align="center" width="18%" style="vertical-align:middle;border-bottom:1px solid #333;">' . $sampleResult[0]['sample_code'] . '</td>';
                            $tbl .= '<td  align="center" width="35%" style="vertical-align:middle !important;border-bottom:1px solid #333;"><img style="width:200px;height:30px;" src="' . $general->getBarcodeImageContent($sampleResult[0]['sample_code'], $barcodeFormat) . '"></td>';
                            $tbl .= '<td  align="center" width="15%" style="vertical-align:middle;border-bottom:1px solid #333;font-size:0.9em;">' . $sampleResult[0][$patientIdColumn] . '</td>';
                            $tbl .= '<td  align="center" width="13%" style="vertical-align:middle;border-bottom:1px solid #333;">' . $lotDetails . '</td>';
                            $tbl .= '<td  align="center" width="13%" style="vertical-align:middle;border-bottom:1px solid #333;">' . $sampleResult[0]['result'] . '</td>';
                        }
                        $tbl .= '</tr>';
                        $tbl .= '</table>';
                    } else {
                        $label = str_replace("_", " ", $jsonToArray[$alphaNumeric[$j]]);
                        $label = str_replace("in house", "In-House", $label);
                        $label = (str_replace("no of ", " ", $label));
                        $tbl .= '<table nobr="true" cellspacing="0" cellpadding="2" style="width:100%;">';
                        $tbl .= '<tr nobr="true" style="border-bottom:1px solid #333;width:100%;">';
                        $tbl .= '<td align="center" width="6%" style="vertical-align:middle;border-bottom:1px solid #333;">' . $sampleCounter . '.</td>';
                        $tbl .= '<td align="center" width="20%" style="vertical-align:middle;border-bottom:1px solid #333;">' . $label . '</td>';
                        $tbl .= '<td align="center" width="35%" style="vertical-align:middle;border-bottom:1px solid #333;"></td>';
                        $tbl .= '<td align="center" width="13%" style="vertical-align:middle;border-bottom:1px solid #333;"></td>';
                        $tbl .= '<td align="center" width="13%" style="vertical-align:middle;border-bottom:1px solid #333;"></td>';
                        $tbl .= '<td align="center" width="13%" style="vertical-align:middle;border-bottom:1px solid #333;"></td>';
                        $tbl .= '</tr>';
                        $tbl .= '</table>';
                    }
                    $sampleCounter = $alphaNumeric[($j + 1)];
                } else {
                    $xplodJsonToArray = explode("_", $jsonToArray[$j]);
                    if (count($xplodJsonToArray) > 1 && $xplodJsonToArray[0] == "s") {
                        if (isset($_GET['type']) && $_GET['type'] == 'tb') {
                            $sampleQuery = "SELECT sample_code,remote_sample_code,result,$patientIdColumn, $patientFirstName, $patientLastName from $refTable where $refPrimaryColumn =?";
                        } else {
                            $sampleQuery = "SELECT sample_code,remote_sample_code,result,lot_number,lot_expiration_date,$patientIdColumn, $patientFirstName, $patientLastName from $refTable where $refPrimaryColumn =?";
                        }
                        $sampleResult = $db->rawQuery($sampleQuery, [$xplodJsonToArray[1]]);

                        $lotDetails = '';
                        $lotExpirationDate = '';
                        if (isset($sampleResult[0]['lot_expiration_date']) && $sampleResult[0]['lot_expiration_date'] != '' && $sampleResult[0]['lot_expiration_date'] != null && $sampleResult[0]['lot_expiration_date'] != '0000-00-00') {
                            if (trim($sampleResult[0]['lot_number']) != '') {
                                $lotExpirationDate .= '<br>';
                            }
                            $lotExpirationDate .= DateUtility::humanReadableDateFormat($sampleResult[0]['lot_expiration_date']);
                        }
                        $lotDetails = $sampleResult[0]['lot_number'] . $lotExpirationDate;
                        $tbl .= '<table nobr="true" cellspacing="0" cellpadding="2" style="width:100%;">';
                        $tbl .= '<tr nobr="true" style="border-bottom:1px solid #333;width:100%;">';
                        if (isset($_GET['type']) && $_GET['type'] == 'covid19') {

                            $tbl .= '<td  align="center" width="6%" style="vertical-align:middle;border-bottom:1px solid #333;">' . $sampleCounter . '.</td>';
                            $tbl .= '<td  align="center" width="18%" style="vertical-align:middle;border-bottom:1px solid #333;">' . $sampleResult[0]['sample_code'] . '</td>';
                            $tbl .= '<td  align="center" width="35%" style="vertical-align:middle !important;border-bottom:1px solid #333;"><img style="width:200px;height:30px;" src="' . $general->getBarcodeImageContent($sampleResult[0]['sample_code'], $barcodeFormat) . '"></td>';
                            $tbl .= '<td  align="center" width="18%" style="vertical-align:middle;border-bottom:1px solid #333;">' . $sampleResult[0]['remote_sample_code'] . '</td>';
                            $tbl .= '<td  align="center" width="15%" style="vertical-align:middle;border-bottom:1px solid #333;font-size:0.9em;">' . $sampleResult[0][$patientIdColumn] . '</td>';
                            $tbl .= '<td  align="center" width="13%" style="vertical-align:middle;border-bottom:1px solid #333;">' . $sampleResult[0]['result'] . '</td>';
                        } else {

                            $tbl .= '<td  align="center" width="6%" style="vertical-align:middle;border-bottom:1px solid #333;">' . $sampleCounter . '.</td>';
                            $tbl .= '<td  align="center" width="18%" style="vertical-align:middle;border-bottom:1px solid #333;">' . $sampleResult[0]['sample_code'] . '</td>';
                            $tbl .= '<td  align="center" width="35%" style="vertical-align:middle !important;border-bottom:1px solid #333;"><img style="width:200px;height:30px;" src="' . $general->getBarcodeImageContent($sampleResult[0]['sample_code'], $barcodeFormat) . '"></td>';
                            $tbl .= '<td  align="center" width="15%" style="vertical-align:middle;border-bottom:1px solid #333;font-size:0.9em;">' . $sampleResult[0][$patientIdColumn] . '</td>';
                            $tbl .= '<td  align="center" width="13%" style="vertical-align:middle;border-bottom:1px solid #333;">' . $lotDetails . '</td>';
                            $tbl .= '<td  align="center" width="13%" style="vertical-align:middle;border-bottom:1px solid #333;">' . $sampleResult[0]['result'] . '</td>';
                        }
                        $tbl .= '</tr>';
                        $tbl .= '</table>';
                    } else {
                        $label = str_replace("_", " ", $jsonToArray[$j]);
                        $label = str_replace("in house", "In-House", $label);
                        $label = (str_replace("no of ", " ", $label));
                        $tbl .= '<table nobr="true" cellspacing="0" cellpadding="2" style="width:100%;">';
                        $tbl .= '<tr nobr="true" style="border-bottom:1px solid #333;width:100%;">';
                        $tbl .= '<td align="center" width="6%" style="vertical-align:middle;border-bottom:1px solid #333;">' . $sampleCounter . '.</td>';
                        $tbl .= '<td align="center" width="20%" style="vertical-align:middle;border-bottom:1px solid #333;">' . $label . '</td>';
                        $tbl .= '<td align="center" width="35%" style="vertical-align:middle;border-bottom:1px solid #333;"></td>';
                        $tbl .= '<td align="center" width="13%" style="vertical-align:middle;border-bottom:1px solid #333;"></td>';
                        $tbl .= '<td align="center" width="13%" style="vertical-align:middle;border-bottom:1px solid #333;"></td>';
                        $tbl .= '<td align="center" width="13%" style="vertical-align:middle;border-bottom:1px solid #333;"></td>';
                        $tbl .= '</tr>';
                        $tbl .= '</table>';
                    }
                    $sampleCounter++;
                }
            }
        } else {
            $noOfInHouseControls = 0;
            if (isset($bResult[0]['number_of_in_house_controls']) && $bResult[0]['number_of_in_house_controls'] != '' && $bResult[0]['number_of_in_house_controls'] != null) {
                $noOfInHouseControls = $bResult[0]['number_of_in_house_controls'];
                for ($i = 1; $i <= $bResult[0]['number_of_in_house_controls']; $i++) {
                    $tbl .= '<table nobr="true" cellspacing="0" cellpadding="2" style="width:100%;">';
                    $tbl .= '<tr nobr="true" style="border-bottom:1px solid #333;width:100%;">
                            <td align="center" width="6%" style="vertical-align:middle;border-bottom:1px solid #333">' . $i . '.</td>
                            <td align="center" width="20%" style="vertical-align:middle;border-bottom:1px solid #333">In-House Controls ' . $i . '</td>
                            <td align="center" width="35%" style="vertical-align:middle;border-bottom:1px solid #333"></td>
                            <td align="center" width="13%" style="vertical-align:middle;border-bottom:1px solid #333"></td>
                            <td align="center" width="13%" style="vertical-align:middle;border-bottom:1px solid #333"></td>
                            <td align="center" width="13%" style="vertical-align:middle;border-bottom:1px solid #333"></td>
                        </tr>';
                    $tbl .= '</table>';
                }
            }
            $noOfManufacturerControls = 0;
            if (isset($bResult[0]['number_of_manufacturer_controls']) && $bResult[0]['number_of_manufacturer_controls'] != '' && $bResult[0]['number_of_manufacturer_controls'] != null) {
                $noOfManufacturerControls = $bResult[0]['number_of_manufacturer_controls'];
                for ($i = 1; $i <= $bResult[0]['number_of_manufacturer_controls']; $i++) {
                    $sNo = $noOfInHouseControls + $i;
                    $tbl .= '<table nobr="true" cellspacing="0" cellpadding="2" style="width:100%;">';
                    $tbl .= '<tr nobr="true" style="border-bottom:1px solid #333;width:100%;">
                    <td align="center" width="6%" style="vertical-align:middle;border-bottom:1px solid #333;">' . $sNo . '.</td>
                    <td align="center" width="20%" style="vertical-align:middle;border-bottom:1px solid #333">Manfacturing Controls ' . $i . '</td>
                    <td align="center" width="35%" style="vertical-align:middle;border-bottom:1px solid #333"></td>
                    <td align="center" width="13%" style="vertical-align:middle;border-bottom:1px solid #333"></td>
                    <td align="center" width="13%" style="vertical-align:middle;border-bottom:1px solid #333"></td>
                    <td align="center" width="13%" style="vertical-align:middle;border-bottom:1px solid #333"></td>
                    </tr>';
                    $tbl .= '</table>';
                }
            }
            $noOfCalibrators = 0;
            if (isset($bResult[0]['number_of_calibrators']) && $bResult[0]['number_of_calibrators'] != '' && $bResult[0]['number_of_calibrators'] != null) {
                $noOfCalibrators = $bResult[0]['number_of_calibrators'];
                for ($i = 1; $i <= $bResult[0]['number_of_calibrators']; $i++) {
                    $sNo = $noOfInHouseControls + $noOfManufacturerControls + $i;
                    $tbl .= '<table nobr="true" cellspacing="0" cellpadding="2" style="width:100%;">';
                    $tbl .= '<tr nobr="true" style="border-bottom:1px solid #333;width:100%;">
                    <td align="center" width="6%" style="vertical-align:middle;border-bottom:1px solid #333;">' . $sNo . '.</td>
                    <td align="center" width="20%" style="vertical-align:middle;border-bottom:1px solid #333;">Calibrators ' . $i . '</td>
                    <td align="center" width="35%" style="vertical-align:middle;border-bottom:1px solid #333;"></td>
                    <td align="center" width="13%" style="vertical-align:middle;border-bottom:1px solid #333;"></td>
                    <td align="center" width="13%" style="vertical-align:middle;border-bottom:1px solid #333;"></td>
                    <td align="center" width="13%" style="vertical-align:middle;border-bottom:1px solid #333;"></td>
                    </tr>';
                    $tbl .= '</table>';
                }
            }
            $sampleCounter = ($noOfInHouseControls + $noOfManufacturerControls + $noOfCalibrators + 1);
            $sQuery = "SELECT sample_code,remote_sample_code,lot_number,lot_expiration_date,result,$patientIdColumn from $refTable where sample_batch_id=$id";
            $result = $db->query($sQuery);
            $sampleCounter = 1;
            if (isset($bResult[0]['position_type']) && $bResult[0]['position_type'] == 'alpha-numeric') {
                foreach ($general->excelColumnRange('A', 'H') as $value) {
                    foreach (range(1, 12) as $no) {
                        $alphaNumeric[] = $value . $no;
                    }
                }
                $sampleCounter = $alphaNumeric[0];
            }
            $j = 0;
            foreach ($result as $sample) {
                // if($pdf->getY()>=250){
                //   $pdf->AddPage();
                // }
                // $params = $pdf->serializeTCPDFtagParameters(array($sample['sample_code'], $barcodeFormat, '', '', '', 7, 0.25, array('border' => false, 'align' => 'C', 'padding' => 1, 'fgcolor' => array(0, 0, 0), 'bgcolor' => array(255, 255, 255), 'text' => false, 'font' => 'helvetica', 'fontsize' => 7, 'stretchtext' => 2), 'N'));
                $lotDetails = '';
                $lotExpirationDate = '';
                if (isset($sample['lot_expiration_date']) && $sample['lot_expiration_date'] != '' && $sample['lot_expiration_date'] != null && $sample['lot_expiration_date'] != '0000-00-00') {
                    if (trim($sample['lot_number']) != '') {
                        $lotExpirationDate .= '<br>';
                    }
                    $lotExpirationDate .= DateUtility::humanReadableDateFormat($sample['lot_expiration_date']);
                }
                $lotDetails = $sample['lot_number'] . $lotExpirationDate;

                $patientIdentifier = $sample[$patientIdColumn];
                if ($showPatientName) {
                    $patientIdentifier = trim($patientIdentifier . " " . $patientFirstName . " " . $patientLastName);
                }

                $tbl .= '<table nobr="true" cellspacing="0" cellpadding="2" style="width:100%;">';
                $tbl .= '<tr nobr="true" style="border-bottom:1px solid #333 !important;width:100%;">';
                if (isset($_GET['type']) && $_GET['type'] == 'covid19') {
                    $tbl .= '<td align="center" width="5%" style="vertical-align:middle;border-bottom:1px solid #333;">' . $sampleCounter . '.</td>';
                    $tbl .= '<td align="center" width="20%" style="vertical-align:middle;border-bottom:1px solid #333;">' . $sample['sample_code'] . '</td>';
                    $tbl .= '<td align="center" width="30%" style="vertical-align:middle;border-bottom:1px solid #333;"><img style="width:200px;height:30px;" src="' . $general->getBarcodeImageContent($sample['sample_code'], $barcodeFormat) . '"></td>';
                    $tbl .= '<td align="center" width="20%" style="vertical-align:middle;border-bottom:1px solid #333;">' . $sample['remote_sample_code'] . '</td>';
                    $tbl .= '<td align="center" width="12.5%" style="vertical-align:middle;border-bottom:1px solid #333;">' . $patientIdentifier . '</td>';
                    $tbl .= '<td align="center" width="12.5%" style="vertical-align:middle;border-bottom:1px solid #333;">' . $sample['result'] . '</td>';
                } else {

                    $tbl .= '<td align="center" width="6%" style="vertical-align:middle;border-bottom:1px solid #333;">' . $sampleCounter . '.</td>';
                    $tbl .= '<td align="center" width="20%" style="vertical-align:middle;border-bottom:1px solid #333;">' . $sample['sample_code'] . '</td>';
                    $tbl .= '<td align="center" width="35%" style="vertical-align:middle;border-bottom:1px solid #333;"><img style="width:200px;height:30px;" src="' . $general->getBarcodeImageContent($sample['sample_code'], $barcodeFormat) . '"></td>';
                    $tbl .= '<td align="center" width="13%" style="vertical-align:middle;border-bottom:1px solid #333;">' . $patientIdentifier . '</td>';
                    $tbl .= '<td align="center" width="13%" style="vertical-align:middle;border-bottom:1px solid #333;">' . $lotDetails . '</td>';
                    $tbl .= '<td align="center" width="13%" style="vertical-align:middle;border-bottom:1px solid #333;">' . $sample['result'] . '</td>';
                }
                $tbl .= '</tr>';
                $tbl .= '</table>';
                if (isset($bResult[0]['position_type']) && $bResult[0]['position_type'] == 'alpha-numeric') {
                    $sampleCounter = $alphaNumeric[($j + 1)];
                    $J++;
                } else {
                    $sampleCounter++;
                }
            }
        }

        $pdf->writeHTML($tbl);
        $filename = "VLSM-" . trim($bResult[0]['batch_code']) . '-' . date('d-m-Y-h-i-s') . '-' . $general->generateRandomString(12) . '.pdf';
        $pdf->Output(TEMP_PATH . DIRECTORY_SEPARATOR . 'barcode' . DIRECTORY_SEPARATOR . $filename);
        exit;
    }
}
