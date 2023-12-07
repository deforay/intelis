<?php

use App\Services\BatchService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Helpers\BatchPdfHelper;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var BatchService $batchService */
$batchService = ContainerRegistry::get(BatchService::class);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = $GLOBALS['request'];
$_GET = $request->getQueryParams();
$id = (isset($_GET['id'])) ? base64_decode((string) $_GET['id']) : null;


$showPatientName = false;
if (isset($_GET['type'])) {
    switch ($_GET['type']) {
        case 'vl':
            $refTable = "form_vl";
            $refPrimaryColumn = "vl_sample_id";
            $patientIdColumn = 'patient_art_no';
            $patientFirstName = 'patient_first_name';
            $patientLastName = 'patient_last_name';
            $worksheetName = _translate('Viral Load Test Worksheet');
            break;
        case 'eid':
            $refTable = "form_eid";
            $refPrimaryColumn = "eid_id";
            $patientIdColumn = 'child_id';
            $patientFirstName = 'child_name';
            $patientLastName = 'child_surname';
            $worksheetName = _translate('EID Test Worksheet');
            break;
        case 'covid19':
            $refTable = "form_covid19";
            $refPrimaryColumn = "covid19_id";
            $patientIdColumn = 'patient_id';
            $patientFirstName = 'patient_name';
            $patientLastName = 'patient_surname';
            $worksheetName = _translate('Covid-19 Test Worksheet');
            break;
        case 'hepatitis':
            $refTable = "form_hepatitis";
            $refPrimaryColumn = "hepatitis_id";
            $patientIdColumn = 'patient_id';
            $patientFirstName = 'patient_name';
            $patientLastName = 'patient_surname';
            $worksheetName = _translate('Hepatitis Test Worksheet');
            $showPatientName = true;
            break;
        case 'tb':
            $refTable = "form_tb";
            $refPrimaryColumn = "tb_id";
            $patientIdColumn = 'patient_id';
            $patientFirstName = 'patient_name';
            $patientLastName = 'patient_surname';
            $worksheetName = _translate('TB Test Worksheet');
            $showPatientName = true;
            break;
        case 'generic-tests':
            $refTable = "form_generic";
            $refPrimaryColumn = "sample_id";
            $patientIdColumn = 'patient_id';
            $patientFirstName = 'patient_first_name';
            $patientLastName = 'patient_last_name';
            $worksheetName = _translate('Lab Test Worksheet');
            $showPatientName = true;
            break;
        default:
            throw new SystemException('Invalid test type - ' . $_GET['type'], 500);
    }
}

$globalConfig = $general->getGlobalConfig();


$barcodeFormat = $globalConfig['barcode_format'] ?? 'C39';

if (!empty($id)) {

    MiscUtility::makeDirectory(UPLOAD_PATH . DIRECTORY_SEPARATOR . "batches");

    $logo = $globalConfig['logo'] ?? '';
    $headerText = $globalConfig['header'] ?? '';

    $bQuery = "SELECT * FROM batch_details as b_d
                    LEFT JOIN instruments as i_c ON i_c.config_id=b_d.machine
                    LEFT JOIN user_details as u ON u.user_id=b_d.created_by
                    WHERE batch_id=?";
    $bResult = $db->rawQuery($bQuery, [$id]);

    if (isset($_GET['type']) && $_GET['type'] == 'covid19') {
        $dateQuery = "SELECT ct.*,
                        covid19.sample_tested_datetime,
                        covid19.result_reviewed_datetime,
                        ct.test_name,
                        ct.kit_lot_no,
                        ct.kit_expiry_date,
                        covid19.lot_number,
                        CASE
                            WHEN covid19.lot_expiration_date IS NOT NULL AND DATE(covid19.lot_expiration_date) > '0000-00-00' THEN NULL
                            ELSE DATE_FORMAT(covid19.lot_expiration_date, '%d-%b-%Y')
                        END AS lot_expiration_date,
                        covid19.result_printed_datetime
                    FROM form_covid19 as covid19
                    LEFT JOIN covid19_tests as ct on covid19.covid19_id = ct.covid19_id
                    WHERE sample_batch_id= ?
                    GROUP BY covid19.covid19_id";
    } else {
        $dateQuery = "SELECT sample_tested_datetime,
                        result_reviewed_datetime
                        FROM $refTable
                        WHERE sample_batch_id= ?";
    }
    // die($dateQuery);
    $dateResult = $db->rawQueryOne($dateQuery, [$id]);
    $resulted = DateUtility::humanReadableDateFormat($dateResult['sample_tested_datetime'] ?? '', true);
    $reviewed = DateUtility::humanReadableDateFormat($dateResult['result_reviewed_datetime'] ?? '', true);
    if (!empty($bResult)) {
        // create new PDF document
        $pdf = new BatchPdfHelper(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        $pdf->setHeading($logo, $headerText, $bResult[0]['batch_code'], $resulted, $reviewed, $bResult[0]['user_name'], $worksheetName);

        // set document information
        $pdf->SetCreator(_translate('VLSM'));
        $pdf->SetAuthor(_translate('VLSM BATCH'));
        $pdf->SetTitle(_translate('VLSM BATCH'));
        $pdf->SetSubject(_translate('VLSM BATCH'));
        $pdf->SetKeywords(_translate('VLSM BATCH'));

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

            $dateResult['kit_expiry_date'] = DateUtility::humanReadableDateFormat($dateResult['kit_expiry_date'] ?? '');
            $dateResult['lot_expiration_date'] = DateUtility::humanReadableDateFormat($dateResult['lot_expiration_date'] ?? '');
            $dateResult['result_printed_datetime'] = DateUtility::humanReadableDateFormat($dateResult['result_printed_datetime'] ?? '', true);

            $tbl = '<table cellspacing="2" cellpadding="6" style="width:100%;" border="0">
                <tr>
                    <th style="font-weight: bold;">' . _translate('Reagent/Kit Name') . ' :</th><td style="width:20%;">' . ((isset($dateResult['covid19_test_name']) && $dateResult['covid19_test_name'] != "") ? $dateResult['covid19_test_name'] : $dateResult['test_name']) . '</td>
                    <th style="font-weight: bold;">' . _translate('Lot Number') . ' :</th><td>' . ((isset($dateResult['kit_lot_no']) && $dateResult['kit_lot_no'] != "") ? $dateResult['kit_lot_no'] : $dateResult['lot_number']) . '</td>
                    <th style="font-weight: bold;width:20%;">' . _translate('Lot Expiry Date') . ' :</th><td>' . ((isset($dateResult['kit_expiry_date']) && $dateResult['kit_expiry_date'] != "") ? $dateResult['kit_expiry_date'] : $dateResult['lot_expiration_date']) . '</td>
                </tr>
                <tr>
                    <th style="font-weight: bold;">' . _translate('Printed By') . ' :</th><td>' . $_SESSION['userName'] . '</td>
                    <th style="font-weight: bold;">' . _translate('Printed On') . ':</th><td colspan="2">' . date("d-M-Y h:i:A") . '</td>
                </tr>
            </table>
            <br>
            <hr>
            <table nobr="true" cellspacing="0" cellpadding="2" style="width:100%;">
                <tr>
                    <th align="center" width="5%"><strong>' . _translate('Pos.') . '</strong></th>
                    <th align="center" width="20%"><strong>' . _translate('Sample ID') . '</strong></th>
                    <th align="center" width="30%"><strong>' . _translate('BARCODE') . '</strong></th>
                    <th align="center" width="20%"><strong>' . _translate('Remote Sample ID') . '</strong></th>
                    <th align="center" width="12.5%"><strong>' . _translate('Patient Code') . '</strong></th>
                    <th align="center" width="12.5%"><strong>' . _translate('Test Result') . '</strong></th>
                </tr>
            </table><hr>';
        } else {
            $tbl = '<table nobr="true" cellspacing="0" cellpadding="2" style="width:100%;">
                    <tr>
                        <th align="center" width="5%"><strong>' . _translate('Pos.') . '</strong></th>
                        <th align="center" width="20%"><strong>' . _translate('Sample ID') . '</strong></th>
                        <th align="center" width="30%"><strong>' . _translate('BARCODE') . '</strong></th>
                        <th align="center" width="20%"><strong>' . _translate('Patient Code') . '</strong></th>
                        <th align="center" width="12.5%"><strong>' . _translate('Lot Number / <br>Exp. Date') . '</strong></th>
                        <th align="center" width="12.5%"><strong>' . _translate('Test Result') . '</strong></th>
                    </tr>
                </table><hr>';
        }
        if (isset($bResult[0]['label_order']) && trim((string) $bResult[0]['label_order']) != '') {
            $jsonToArray = json_decode((string) $bResult[0]['label_order'], true);
            $sampleCounter = 1;
            if (isset($bResult[0]['position_type']) && $bResult[0]['position_type'] == 'alpha-numeric') {
                foreach ($batchService->excelColumnRange('A', 'H') as $value) {
                    foreach (range(1, 12) as $no) {
                        $alphaNumeric[] = $value . $no;
                    }
                }
                $sampleCounter = $alphaNumeric[0];
            }
            for ($j = 0; $j < count($jsonToArray); $j++) {
                if (isset($bResult[0]['position_type']) && $bResult[0]['position_type'] == 'alpha-numeric') {
                    $xplodJsonToArray = explode("_", (string) $jsonToArray[$alphaNumeric[$j]]);
                    if (count($xplodJsonToArray) > 1 && $xplodJsonToArray[0] == "s") {
                        if (isset($_GET['type']) && $_GET['type'] == 'tb') {
                            $sampleQuery = "SELECT sample_code,
                                                    remote_sample_code,result,is_encrypted,
                                                    $patientIdColumn,
                                                    $patientFirstName,
                                                    $patientLastName
                                                    FROM
                                                    $refTable
                                                    WHERE $refPrimaryColumn = ?";
                        } else {
                            $sampleQuery = "SELECT sample_code,
                                                    remote_sample_code,
                                                    result,
                                                    lot_number,is_encrypted,
                                                    CASE
                                                        WHEN lot_expiration_date IS NULL OR lot_expiration_date = '0000-00-00' THEN NULL
                                                        ELSE DATE_FORMAT(lot_expiration_date, '%d-%b-%Y')
                                                    END AS lot_expiration_date,
                                                    $patientIdColumn,
                                                    $patientFirstName,
                                                    $patientLastName
                                                    FROM
                                                    $refTable
                                                    WHERE $refPrimaryColumn =?";
                        }

                        $sampleResult = $db->rawQuery($sampleQuery, [$xplodJsonToArray[1]]);

                        $lotDetails = '';
                        $lotExpirationDate = '';
                        if (!empty($sampleResult[0]['lot_expiration_date'])) {
                            if (trim((string) $sampleResult[0]['lot_number']) != '') {
                                $lotExpirationDate .= '<br>';
                            }
                            $lotExpirationDate .= DateUtility::humanReadableDateFormat($sampleResult[0]['lot_expiration_date']);
                        }

                        if (!empty($sampleResult[0]['is_encrypted']) && $sampleResult[0]['is_encrypted'] == 'yes') {
                            $key = (string) $general->getGlobalConfig('key');
                            $sampleResult[0][$patientIdColumn] = $general->crypto('decrypt', $sampleResult[0][$patientIdColumn], $key);
                        }


                        $lotDetails = $sampleResult[0]['lot_number'] . $lotExpirationDate;
                        $tbl .= '<p></p>
                                <table nobr="true" cellspacing="0" cellpadding="2" style="width:100%;border-bottom:1px solid black;">';
                        $tbl .= '<tr nobr="true" style="width:100%;">';

                        $tbl .= '<td  align="center" width="5%" style="vertical-align:middle;">' . $sampleCounter . '.</td>';
                        $tbl .= '<td  align="center" width="20%" style="vertical-align:middle;">' . $sampleResult[0]['sample_code'] . '</td>';
                        if ($barcodeFormat == 'QRCODE') {
                            $tbl .= '<td  align="center" width="30%" style="vertical-align:middle !important;"><img style="width:50px;height:50px;" src="' . $general->get2DBarcodeImageContent($sampleResult[0]['sample_code'], $barcodeFormat) . '"></td>';
                        } else {
                            $tbl .= '<td  align="center" width="30%" style="vertical-align:middle !important;line-height:30px;"><img style="width:200px;height:25px;" src="' . $general->getBarcodeImageContent($sampleResult[0]['sample_code'], $barcodeFormat) . '"></td>';
                        }
                        if (isset($_GET['type']) && $_GET['type'] == 'covid19') {
                            $tbl .= '<td  align="center" width="20%" style="vertical-align:middle;">' . $sampleResult[0]['remote_sample_code'] . '</td>';
                            $tbl .= '<td  align="center" width="12.5%" style="vertical-align:middle;font-size:0.9em;">' . $sampleResult[0][$patientIdColumn] . '</td>';
                            $tbl .= '<td  align="center" width="12.5%" style="vertical-align:middle;">' . ucwords((string) $sampleResult[0]['result']) . '</td>';
                        } else {
                            $tbl .= '<td  align="center" width="20%" style="vertical-align:middle;font-size:0.9em;">' . $sampleResult[0][$patientIdColumn] . '</td>';
                            $tbl .= '<td  align="center" width="12.5%" style="vertical-align:middle;">' . $lotDetails . '</td>';
                            $tbl .= '<td  align="center" width="12.5%" style="vertical-align:middle;">' . ucwords((string) $sampleResult[0]['result']) . '</td>';
                        }
                        $tbl .= '</tr>';
                        $tbl .= '</table>';
                    } else {
                        $label = str_replace("_", " ", (string) $jsonToArray[$alphaNumeric[$j]]);
                        $label = str_replace("in house", "In-House", $label);
                        $label = (str_replace("no of ", " ", $label));
                        $tbl .= '<table nobr="true" cellspacing="0" cellpadding="2" style="width:100%;border-bottom:1px solid black;">';
                        $tbl .= '<tr nobr="true" style="width:100%;">';
                        $tbl .= '<td align="center" width="6%" style="vertical-align:middle;">' . $sampleCounter . '.</td>';
                        $tbl .= '<td align="center" width="20%" style="vertical-align:middle;">' . $label . '</td>';
                        $tbl .= '<td align="center" width="35%" style="vertical-align:middle;"></td>';
                        $tbl .= '<td align="center" width="13%" style="vertical-align:middle;"></td>';
                        $tbl .= '<td align="center" width="13%" style="vertical-align:middle;"></td>';
                        $tbl .= '<td align="center" width="13%" style="vertical-align:middle;"></td>';
                        $tbl .= '</tr>';
                        $tbl .= '</table>';
                    }
                    $sampleCounter = $alphaNumeric[($j + 1)];
                } else {
                    $xplodJsonToArray = explode("_", (string) $jsonToArray[$j]);
                    if (count($xplodJsonToArray) > 1 && $xplodJsonToArray[0] == "s") {
                        if (isset($_GET['type']) && $_GET['type'] == 'tb') {
                            $sampleQuery = "SELECT sample_code,
                                            remote_sample_code,
                                            result,is_encrypted,
                                            $patientIdColumn,
                                            $patientFirstName,
                                            $patientLastName
                                            FROM $refTable
                                            WHERE $refPrimaryColumn =?";
                        } else {
                            $sampleQuery = "SELECT sample_code,
                                                remote_sample_code,
                                                result,
                                                lot_number,is_encrypted,
                                                CASE
                                                    WHEN lot_expiration_date IS NULL OR lot_expiration_date = '0000-00-00' THEN NULL
                                                    ELSE DATE_FORMAT(lot_expiration_date, '%d-%b-%Y')
                                                END AS lot_expiration_date,
                                                $patientIdColumn,
                                                $patientFirstName,
                                                $patientLastName
                                                FROM $refTable
                                                WHERE $refPrimaryColumn =?";
                        }

                        $sampleResult = $db->rawQuery($sampleQuery, [$xplodJsonToArray[1]]);

                        $lotDetails = '';
                        $lotExpirationDate = '';
                        if (!empty($sampleResult[0]['lot_expiration_date'])) {
                            if (trim((string) $sampleResult[0]['lot_number']) != '') {
                                $lotExpirationDate .= '<br>';
                            }
                            $lotExpirationDate .= DateUtility::humanReadableDateFormat($sampleResult[0]['lot_expiration_date'] ?? '');
                        }
                        if (!empty($sampleResult[0]['is_encrypted']) && $sampleResult[0]['is_encrypted'] == 'yes') {
                            $key = (string) $general->getGlobalConfig('key');
                            $sampleResult[0][$patientIdColumn] = $general->crypto('decrypt', $sampleResult[0][$patientIdColumn], $key);
                        }

                        $lotDetails = $sampleResult[0]['lot_number'] . $lotExpirationDate;
                        $tbl .= '<p></p><table nobr="true" cellspacing="0" cellpadding="2" style="width:100%;border-bottom:1px solid black;">';
                        $tbl .= '<tr>';

                        $tbl .= '<td  align="center" width="5%" style="vertical-align:middle;">' . $sampleCounter . '.</td>';
                        $tbl .= '<td  align="center" width="20%" style="vertical-align:middle;">' . $sampleResult[0]['sample_code'] . '</td>';
                        if ($barcodeFormat == 'QRCODE') {
                            $tbl .= '<td  align="center" width="30%" style="vertical-align:middle !important;"><img style="width:50px;height:50px;" src="' . $general->get2DBarcodeImageContent($sampleResult[0]['sample_code'], $barcodeFormat) . '"></td>';
                        } else {
                            $tbl .= '<td  align="center" width="30%" style="vertical-align:middle !important;line-height:30px;"><img style="width:200px;height:25px;" src="' . $general->getBarcodeImageContent($sampleResult[0]['sample_code'], $barcodeFormat) . '"></td>';
                        }
                        if (isset($_GET['type']) && $_GET['type'] == 'covid19') {
                            $tbl .= '<td  align="center" width="20%" style="vertical-align:middle;">' . $sampleResult[0]['remote_sample_code'] . '</td>';
                            $tbl .= '<td  align="center" width="12.5%" style="vertical-align:middle;font-size:0.9em;">' . $sampleResult[0][$patientIdColumn] . '</td>';
                            $tbl .= '<td  align="center" width="12.5%" style="vertical-align:middle;">' . ucwords((string) $sampleResult[0]['result']) . '</td>';
                        } else {
                            $tbl .= '<td  align="center" width="20%" style="vertical-align:middle;font-size:0.9em;">' . $sampleResult[0][$patientIdColumn] . '</td>';
                            $tbl .= '<td  align="center" width="12.5%" style="vertical-align:middle;">' . $lotDetails . '</td>';
                            $tbl .= '<td  align="center" width="12.5%" style="vertical-align:middle;">' . ucwords((string) $sampleResult[0]['result']) . '</td>';
                        }
                        $tbl .= '</tr>';
                        $tbl .= '</table>';
                    } else {
                        $label = str_replace("_", " ", (string) $jsonToArray[$j]);
                        $label = str_replace("in house", "In-House", $label);
                        $label = (str_replace("no of ", " ", $label));
                        $tbl .= '<table nobr="true" cellspacing="0" cellpadding="2" style="width:100%;border-bottom:1px solid black;">';
                        $tbl .= '<tr nobr="true" style="width:100%;">';
                        $tbl .= '<td align="center" width="6%" style="vertical-align:middle;">' . $sampleCounter . '.</td>';
                        $tbl .= '<td align="center" width="20%" style="vertical-align:middle;">' . $label . '</td>';
                        $tbl .= '<td align="center" width="35%" style="vertical-align:middle;"></td>';
                        $tbl .= '<td align="center" width="13%" style="vertical-align:middle;"></td>';
                        $tbl .= '<td align="center" width="13%" style="vertical-align:middle;"></td>';
                        $tbl .= '<td align="center" width="13%" style="vertical-align:middle;"></td>';
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
                    $tbl .= '<table nobr="true" cellspacing="0" cellpadding="2" style="width:100%;border-bottom:1px solid black;">';
                    $tbl .= '<tr nobr="true" style="width:100%;">
                            <td align="center" width="6%" style="vertical-align:middle;border-bottom:1px solid #333">' . $i . '.</td>
                            <td align="center" width="20%" style="vertical-align:middle;border-bottom:1px solid #333">' . _translate('In-House Controls') . ' ' . $i . '</td>
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
                    $tbl .= '<table nobr="true" cellspacing="0" cellpadding="2" style="width:100%;border-bottom:1px solid black;">';
                    $tbl .= '<tr nobr="true" style="width:100%;">
                    <td align="center" width="6%" style="vertical-align:middle;">' . $sNo . '.</td>
                    <td align="center" width="20%" style="vertical-align:middle;border-bottom:1px solid #333">' . _translate('Manfacturing Controls') . ' ' . $i . '</td>
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
                    $tbl .= '<table nobr="true" cellspacing="0" cellpadding="2" style="width:100%;border-bottom:1px solid black;">';
                    $tbl .= '<tr nobr="true" style="width:100%;">
                    <td align="center" width="6%" style="vertical-align:middle;">' . $sNo . '.</td>
                    <td align="center" width="20%" style="vertical-align:middle;">' . _translate('Calibrators') . ' ' . $i . '</td>
                    <td align="center" width="35%" style="vertical-align:middle;"></td>
                    <td align="center" width="13%" style="vertical-align:middle;"></td>
                    <td align="center" width="13%" style="vertical-align:middle;"></td>
                    <td align="center" width="13%" style="vertical-align:middle;"></td>
                    </tr>';
                    $tbl .= '</table>';
                }
            }
            $sampleCounter = ($noOfInHouseControls + $noOfManufacturerControls + $noOfCalibrators + 1);
            $sQuery = "SELECT sample_code,
                            remote_sample_code,
                            lot_number,
                            CASE
                                WHEN lot_expiration_date IS NULL OR lot_expiration_date = '0000-00-00' THEN NULL
                                ELSE DATE_FORMAT(lot_expiration_date, '%d-%b-%Y')
                            END AS lot_expiration_date,
                            result,
                            $patientIdColumn
                            FROM $refTable
                            WHERE sample_batch_id=$id";
            $result = $db->query($sQuery);
            $sampleCounter = 1;
            if (isset($bResult[0]['position_type']) && $bResult[0]['position_type'] == 'alpha-numeric') {
                foreach ($batchService->excelColumnRange('A', 'H') as $value) {
                    foreach (range(1, 12) as $no) {
                        $alphaNumeric[] = $value . $no;
                    }
                }
                $sampleCounter = $alphaNumeric[0];
            }
            $j = 0;
            foreach ($result as $sample) {

                $lotDetails = '';
                $lotExpirationDate = '';
                if (!empty($sample['lot_expiration_date'])) {
                    if (trim((string) $sample['lot_number']) != '') {
                        $lotExpirationDate .= '<br>';
                    }
                    $lotExpirationDate .= DateUtility::humanReadableDateFormat($sample['lot_expiration_date']);
                }
                $lotDetails = $sample['lot_number'] . $lotExpirationDate;

                $patientIdentifier = $sample[$patientIdColumn];
                if ($showPatientName) {
                    $patientIdentifier = trim($patientIdentifier . " " . $patientFirstName . " " . $patientLastName);
                }

                $tbl .= '<table nobr="true" cellspacing="0" cellpadding="2" style="width:100%;border-bottom:1px solid black;">';
                $tbl .= '<tr nobr="true">';

                $tbl .= '<td align="center" width="5%" style="vertical-align:middle;">' . $sampleCounter . '.</td>';
                $tbl .= '<td align="center" width="20%" style="vertical-align:middle;">' . $sample['sample_code'] . '</td>';
                if ($barcodeFormat == 'QRCODE') {
                    $tbl .= '<td align="center" width="30%" style="vertical-align:middle;"><img style="width:50px;height:50px;" src="' . $general->get2DBarcodeImageContent($sample['sample_code'], $barcodeFormat) . '"></td>';
                } else {
                    $tbl .= '<td align="center" width="30%" style="vertical-align:middle;line-height:30px;"><img style="width:200px;height:25px;" src="' . $general->getBarcodeImageContent($sample['sample_code'], $barcodeFormat) . '"></td>';
                }
                if (isset($_GET['type']) && $_GET['type'] == 'covid19') {
                    $tbl .= '<td align="center" width="20%" style="vertical-align:middle;">' . $sample['remote_sample_code'] . '</td>';
                    $tbl .= '<td align="center" width="12.5%" style="vertical-align:middle;">' . $patientIdentifier . '</td>';
                    $tbl .= '<td align="center" width="12.5%" style="vertical-align:middle;">' . $sample['result'] . '</td>';
                } else {
                    $tbl .= '<td align="center" width="20%" style="vertical-align:middle;">' . $patientIdentifier . '</td>';
                    $tbl .= '<td align="center" width="12.5%" style="vertical-align:middle;">' . $lotDetails . '</td>';
                    $tbl .= '<td align="center" width="12.5%" style="vertical-align:middle;">' . $sample['result'] . '</td>';
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
        $filename = "VLSM-" . trim((string) $bResult[0]['batch_code']) . '-' . date('d-m-Y-h-i-s') . '-' . $general->generateRandomString(12) . '.pdf';
        $pdf->Output(TEMP_PATH . DIRECTORY_SEPARATOR . 'batches' . DIRECTORY_SEPARATOR . $filename);
        exit;
    }
}
