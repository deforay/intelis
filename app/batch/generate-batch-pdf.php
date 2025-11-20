<?php

use Psr\Http\Message\ServerRequestInterface;
use App\Services\BatchService;
use App\Services\TestsService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Helpers\BatchPdfHelper;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var BatchService $batchService */
$batchService = ContainerRegistry::get(BatchService::class);

// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());


if (empty($_GET['type'])) {
    throw new SystemException(_translate('Invalid test type') . ' - ' . ($_GET['type'] ?? _translate("Unspecified")), 500);
} elseif (empty($_GET['id'])) {
    throw new SystemException(_translate('Invalid Batch'), 500);
}

$id = MiscUtility::desqid((string) $_GET['id']);

$testTableData = TestsService::getAllData($_GET['type']);

$testName = $testTableData['testName'];
$table = $testTableData['tableName'];
$patientIdColumn = $testTableData['patientId'];
$primaryKey = $testTableData['primaryKey'];
$patientFirstName = $testTableData['patientFirstName'];
$patientLastName = $testTableData['patientLastName'];
$resultColumn = 'result';
if ($_GET['type'] == 'cd4') {
    $resultColumn = 'cd4_result';
}

$worksheetName = $testName . " " . _translate('Test Worksheet');

$showPatientName = false;

if (in_array($_GET['type'], ['hepatitis', 'tb', 'generic-tests'])) {
    $showPatientName = true;
}

$globalConfig = $general->getGlobalConfig();
$key = (string) $general->getGlobalConfig('key');

$barcodeFormat = $globalConfig['barcode_format'] ?? 'C39';

if (!empty($id)) {

    $logo = $globalConfig['logo'] ?? '';
    $headerText = $globalConfig['header'] ?? '';

    $bQuery = "SELECT * FROM batch_details as b_d
                    LEFT JOIN instruments as i_c ON i_c.instrument_id=b_d.machine
                    LEFT JOIN user_details as u ON u.user_id=b_d.created_by
                    WHERE batch_id=?";
    $bResult = $db->rawQueryOne($bQuery, [$id]);

    if (isset($_GET['type']) && $_GET['type'] == 'covid19') {
        $dateQuery = "SELECT ct.*,
                        covid19.sample_tested_datetime,
                        covid19.result_reviewed_datetime,
                        ct.test_name,
                        ct.kit_lot_no,
                        ct.kit_expiry_date,
                        covid19.lot_number,
                        CASE
                            WHEN covid19.lot_expiration_date IS NULL THEN NULL
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
                        FROM $table
                        WHERE sample_batch_id= ?";
    }
    // die($dateQuery);
    $dateResult = $db->rawQueryOne($dateQuery, [$id]);
    $resulted = DateUtility::humanReadableDateFormat($dateResult['sample_tested_datetime'] ?? '', true);
    $reviewed = DateUtility::humanReadableDateFormat($dateResult['result_reviewed_datetime'] ?? '', true);
    if (!empty($bResult)) {
        // create new PDF document
        $pdf = new BatchPdfHelper(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        $pdf->setHeading($logo, $headerText, $bResult['batch_code'], $resulted, $reviewed, $bResult['user_name'], $worksheetName);

        // set document information
        $pdf->SetCreator('InteLIS');
        $pdf->SetAuthor('InteLIS');
        $pdf->SetTitle(_translate('LIS BATCH PDF'));
        $pdf->SetSubject(_translate('LIS BATCH'));
        $pdf->SetKeywords(_translate('LIS BATCH'));

        $pdf->SetMargins(0, 0, 0);
        $pdf->SetHeaderMargin(0);
        $pdf->SetFooterMargin(0);

        // set default header data
        $pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, PDF_HEADER_TITLE, PDF_HEADER_STRING);

        // set header and footer fonts
        $pdf->setHeaderFont(['freesans', '', PDF_FONT_SIZE_MAIN]);
        $pdf->setFooterFont(['freesans', '', PDF_FONT_SIZE_DATA]);

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
        $pdf->SetFont('freesans', '', 10);

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
        if (isset($bResult['label_order']) && trim((string) $bResult['label_order']) !== '') {
            $jsonToArray = json_decode((string) $bResult['label_order'], true);
            $sampleCounter = 1;
            if (isset($bResult['position_type']) && $bResult['position_type'] == 'alpha-numeric') {
                foreach ($batchService->excelColumnRange('A', 'H') as $value) {
                    foreach (range(1, 12) as $no) {
                        $alphaNumeric[] = $value . $no;
                    }
                }
                $sampleCounter = $alphaNumeric[0];
            }
            $counter = count($jsonToArray);
            for ($j = 0; $j < $counter; $j++) {
                if (isset($bResult['position_type']) && $bResult['position_type'] == 'alpha-numeric') {
                    $xplodJsonToArray = explode("_", (string) $jsonToArray[$alphaNumeric[$j]]);
                    if (count($xplodJsonToArray) > 1 && $xplodJsonToArray[0] === "s") {
                        if ((isset($_GET['type']) && $_GET['type'] == 'tb') || (isset($_GET['type']) && $_GET['type'] == 'cd4')) {
                            $sampleQuery = "SELECT sample_code,
                                                    remote_sample_code,
                                                    lab_assigned_code,
                                                    $resultColumn,
                                                    is_encrypted,
                                                    $patientIdColumn,
                                                    $patientFirstName,
                                                    $patientLastName
                                                    FROM
                                                    $table
                                                    WHERE $primaryKey = ?";
                        } else {
                            $sampleQuery = "SELECT sample_code,
                                                    remote_sample_code,
                                                    lab_assigned_code,
                                                    $resultColumn,
                                                    lot_number,
                                                    is_encrypted,
                                                    CASE
                                                        WHEN lot_expiration_date IS NULL THEN NULL
                                                        ELSE DATE_FORMAT(lot_expiration_date, '%d-%b-%Y')
                                                    END AS lot_expiration_date,
                                                    $patientIdColumn,
                                                    $patientFirstName,
                                                    $patientLastName
                                                    FROM
                                                    $table
                                                    WHERE $primaryKey =?";
                        }

                        $sampleResult = $db->rawQuery($sampleQuery, [$xplodJsonToArray[1]]);

                        $lotDetails = '';
                        $lotExpirationDate = '';
                        $labAssignedCode = '';
                        if (!empty($sampleResult[0]['lot_expiration_date'])) {
                            if (trim((string) $sampleResult[0]['lot_number']) !== '') {
                                $lotExpirationDate .= '<br>';
                            }
                            $lotExpirationDate .= DateUtility::humanReadableDateFormat($sampleResult[0]['lot_expiration_date']);
                        }

                        if (!empty($sampleResult[0]['is_encrypted']) && $sampleResult[0]['is_encrypted'] == 'yes') {
                            $sampleResult[0][$patientIdColumn] = $general->crypto('decrypt', $sampleResult[0][$patientIdColumn], $key);
                        }


                        $lotDetails = $sampleResult[0]['lot_number'] . $lotExpirationDate;
                        if (!empty($sampleResult[0]['lab_assigned_code'])) {
                            $labAssignedCode = $sampleResult[0]['lab_assigned_code'];
                        }
                        $tbl .= '<p></p>
                                <table nobr="true" cellspacing="0" cellpadding="2" style="width:100%;border-bottom:1px solid black;">';
                        $tbl .= '<tr nobr="true" style="width:100%;">';

                        $tbl .= '<td  align="center" width="5%" style="vertical-align:middle;">' . $sampleCounter . '.</td>';
                        $tbl .= '<td  align="center" width="20%" style="vertical-align:middle;">' . $sampleResult[0]['sample_code'] . '--' . $labAssignedCode . '</td>';
                        if ($barcodeFormat == 'QRCODE') {
                            $tbl .= '<td  align="center" width="30%" style="vertical-align:middle !important;">' . BatchPdfHelper::buildBarcodeImageTag($general, $sampleResult[0]['sample_code'], $barcodeFormat) . '</td>';
                        } else {
                            $tbl .= '<td  align="center" width="30%" style="vertical-align:middle !important;line-height:30px;">' . BatchPdfHelper::buildBarcodeImageTag($general, $sampleResult[0]['sample_code'], $barcodeFormat) . '</td>';
                        }
                        if (isset($_GET['type']) && $_GET['type'] == 'covid19') {
                            $tbl .= '<td  align="center" width="20%" style="vertical-align:middle;">' . $sampleResult[0]['remote_sample_code'] . '</td>';
                            $tbl .= '<td  align="center" width="12.5%" style="vertical-align:middle;font-size:0.9em;">' . $sampleResult[0][$patientIdColumn] . '</td>';
                            $tbl .= '<td  align="center" width="12.5%" style="vertical-align:middle;">' . ucwords((string) $sampleResult[0][$resultColumn]) . '</td>';
                        } else {
                            $tbl .= '<td  align="center" width="20%" style="vertical-align:middle;font-size:0.9em;">' . $sampleResult[0][$patientIdColumn] . '</td>';
                            $tbl .= '<td  align="center" width="12.5%" style="vertical-align:middle;">' . $lotDetails . '</td>';
                            $tbl .= '<td  align="center" width="12.5%" style="vertical-align:middle;">' . ucwords((string) $sampleResult[0][$resultColumn]) . '</td>';
                        }
                        $tbl .= '</tr>';
                        $tbl .= '</table>';
                    } else {

                        $controlNamesArr = json_decode((string) $bResult['control_names'], true);
                        if (!empty($controlNamesArr) && array_key_exists($jsonToArray[$alphaNumeric[$j]], $controlNamesArr) && $controlNamesArr[$jsonToArray[$alphaNumeric[$j]]] != "") {
                            $label = $controlNamesArr[$jsonToArray[$alphaNumeric[$j]]];
                        } else {
                            $label = str_replace("_", " ", (string) $jsonToArray[$alphaNumeric[$j]]);
                            $label = str_replace("in house", "In-House", $label);
                            $label = (str_replace("no of ", " ", $label));
                        }

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
                    if ($xplodJsonToArray !== [] && count($xplodJsonToArray) > 1 && $xplodJsonToArray[0] === "s") {
                        if ((isset($_GET['type']) && $_GET['type'] == 'tb') || (isset($_GET['type']) && $_GET['type'] == 'cd4')) {
                            $sampleQuery = "SELECT sample_code,
                                            remote_sample_code,
                                            $resultColumn,is_encrypted,lab_assigned_code,
                                            $patientIdColumn,
                                            $patientFirstName,
                                            $patientLastName
                                            FROM $table
                                            WHERE $primaryKey =?";
                        } else {
                            $sampleQuery = "SELECT sample_code,
                                                remote_sample_code,
                                                $resultColumn,
                                                lot_number,is_encrypted,lab_assigned_code,
                                                CASE
                                                    WHEN lot_expiration_date IS NULL THEN NULL
                                                    ELSE DATE_FORMAT(lot_expiration_date, '%d-%b-%Y')
                                                END AS lot_expiration_date,
                                                $patientIdColumn,
                                                $patientFirstName,
                                                $patientLastName
                                                FROM $table
                                                WHERE $primaryKey =?";
                        }

                        $sampleResult = $db->rawQuery($sampleQuery, [$xplodJsonToArray[1]]);

                        $lotDetails = '';
                        $lotExpirationDate = '';
                        $labAssignedCode = '';
                        if (!empty($sampleResult[0]['lot_expiration_date'])) {
                            if (trim((string) $sampleResult[0]['lot_number']) !== '') {
                                $lotExpirationDate .= '<br>';
                            }
                            $lotExpirationDate .= DateUtility::humanReadableDateFormat($sampleResult[0]['lot_expiration_date'] ?? '');
                        }
                        if (!empty($sampleResult[0]['is_encrypted']) && $sampleResult[0]['is_encrypted'] == 'yes') {
                            $sampleResult[0][$patientIdColumn] = $general->crypto('decrypt', $sampleResult[0][$patientIdColumn], $key);
                        }

                        $lotDetails = $sampleResult[0]['lot_number'] . $lotExpirationDate;
                        if (!empty($sampleResult[0]['lab_assigned_code'])) {
                            $labAssignedCode = $sampleResult[0]['lab_assigned_code'];
                        }
                        $tbl .= '<p></p><table nobr="true" cellspacing="0" cellpadding="2" style="width:100%;border-bottom:1px solid black;">';
                        $tbl .= '<tr>';

                        $tbl .= '<td  align="center" width="5%" style="vertical-align:middle;">' . $sampleCounter . '.</td>';
                        $tbl .= '<td  align="center" width="20%" style="vertical-align:middle;">' . $sampleResult[0]['sample_code'] . '<br>' . $labAssignedCode . '</td>';
                        if ($barcodeFormat == 'QRCODE') {
                            $tbl .= '<td  align="center" width="30%" style="vertical-align:middle !important;">' . BatchPdfHelper::buildBarcodeImageTag($general, $sampleResult[0]['sample_code'], $barcodeFormat) . '</td>';
                        } else {
                            $tbl .= '<td  align="center" width="30%" style="vertical-align:middle !important;line-height:30px;">' . BatchPdfHelper::buildBarcodeImageTag($general, $sampleResult[0]['sample_code'], $barcodeFormat) . '</td>';
                        }
                        if (isset($_GET['type']) && $_GET['type'] == 'covid19') {
                            $tbl .= '<td  align="center" width="20%" style="vertical-align:middle;">' . $sampleResult[0]['remote_sample_code'] . '</td>';
                            $tbl .= '<td  align="center" width="12.5%" style="vertical-align:middle;font-size:0.9em;">' . $sampleResult[0][$patientIdColumn] . '</td>';
                            $tbl .= '<td  align="center" width="12.5%" style="vertical-align:middle;">' . ucwords((string) $sampleResult[0][$resultColumn]) . '</td>';
                        } else {
                            $tbl .= '<td  align="center" width="20%" style="vertical-align:middle;font-size:0.9em;">' . $sampleResult[0][$patientIdColumn] . '</td>';
                            $tbl .= '<td  align="center" width="12.5%" style="vertical-align:middle;">' . $lotDetails . '</td>';
                            $tbl .= '<td  align="center" width="12.5%" style="vertical-align:middle;">' . ucwords((string) $sampleResult[0][$resultColumn]) . '</td>';
                        }
                        $tbl .= '</tr>';
                        $tbl .= '</table>';
                    } else {
                        //  echo $bResult['control_names']; die;
                        $controlNamesArr = json_decode((string) $bResult['control_names'], true);
                        if (!empty($controlNamesArr) && array_key_exists($jsonToArray[$j], $controlNamesArr) && $controlNamesArr[$jsonToArray[$j]] != "") {
                            $label = $controlNamesArr[$jsonToArray[$j]];
                        } else {
                            $label = str_replace("_", " ", (string) $jsonToArray[$j]);
                            $label = str_replace("in house", "In-House", $label);
                            $label = (str_replace("no of ", " ", $label));
                        }
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
            if (isset($bResult['number_of_in_house_controls']) && $bResult['number_of_in_house_controls'] != '' && $bResult['number_of_in_house_controls'] != null) {
                $noOfInHouseControls = $bResult['number_of_in_house_controls'];
                for ($i = 1; $i <= $bResult['number_of_in_house_controls']; $i++) {
                    $tbl .= '<table nobr="true" cellspacing="0" cellpadding="2" style="width:100%;border-bottom:1px solid black;">';
                    $tbl .= '<tr nobr="true" style="width:100%;">
                            <td align="center" width="6%" style="vertical-align:middle;border-bottom:1px solid #333">' . $i . '.</td>
                            <td align="center" width="20%" style="vertical-align:middle;border-bottom:1px solid #333">' . _translate('In-House Control') . ' ' . $i . '</td>
                            <td align="center" width="35%" style="vertical-align:middle;border-bottom:1px solid #333"></td>
                            <td align="center" width="13%" style="vertical-align:middle;border-bottom:1px solid #333"></td>
                            <td align="center" width="13%" style="vertical-align:middle;border-bottom:1px solid #333"></td>
                            <td align="center" width="13%" style="vertical-align:middle;border-bottom:1px solid #333"></td>
                        </tr>';
                    $tbl .= '</table>';
                }
            }
            $noOfManufacturerControls = 0;
            if (isset($bResult['number_of_manufacturer_controls']) && $bResult['number_of_manufacturer_controls'] != '' && $bResult['number_of_manufacturer_controls'] != null) {
                $noOfManufacturerControls = $bResult['number_of_manufacturer_controls'];
                for ($i = 1; $i <= $bResult['number_of_manufacturer_controls']; $i++) {
                    $sNo = $noOfInHouseControls + $i;
                    $tbl .= '<table nobr="true" cellspacing="0" cellpadding="2" style="width:100%;border-bottom:1px solid black;">';
                    $tbl .= '<tr nobr="true" style="width:100%;">
                    <td align="center" width="6%" style="vertical-align:middle;">' . $sNo . '.</td>
                    <td align="center" width="20%" style="vertical-align:middle;border-bottom:1px solid #333">' . _translate('Manufacturer Control') . ' ' . $i . '</td>
                    <td align="center" width="35%" style="vertical-align:middle;border-bottom:1px solid #333"></td>
                    <td align="center" width="13%" style="vertical-align:middle;border-bottom:1px solid #333"></td>
                    <td align="center" width="13%" style="vertical-align:middle;border-bottom:1px solid #333"></td>
                    <td align="center" width="13%" style="vertical-align:middle;border-bottom:1px solid #333"></td>
                    </tr>';
                    $tbl .= '</table>';
                }
            }
            $noOfCalibrators = 0;
            if (isset($bResult['number_of_calibrators']) && $bResult['number_of_calibrators'] != '' && $bResult['number_of_calibrators'] != null) {
                $noOfCalibrators = $bResult['number_of_calibrators'];
                for ($i = 1; $i <= $bResult['number_of_calibrators']; $i++) {
                    $sNo = $noOfInHouseControls + $noOfManufacturerControls + $i;
                    $tbl .= '<table nobr="true" cellspacing="0" cellpadding="2" style="width:100%;border-bottom:1px solid black;">';
                    $tbl .= '<tr nobr="true" style="width:100%;">
                    <td align="center" width="6%" style="vertical-align:middle;">' . $sNo . '.</td>
                    <td align="center" width="20%" style="vertical-align:middle;">' . _translate('Calibrator') . ' ' . $i . '</td>
                    <td align="center" width="35%" style="vertical-align:middle;"></td>
                    <td align="center" width="13%" style="vertical-align:middle;"></td>
                    <td align="center" width="13%" style="vertical-align:middle;"></td>
                    <td align="center" width="13%" style="vertical-align:middle;"></td>
                    </tr>';
                    $tbl .= '</table>';
                }
            }
            $sampleCounter = $noOfInHouseControls + $noOfManufacturerControls + $noOfCalibrators + 1;
            $sQuery = "SELECT sample_code,
                            remote_sample_code,
                            lot_number,
                            CASE
                                WHEN lot_expiration_date IS NULL THEN NULL
                                ELSE DATE_FORMAT(lot_expiration_date, '%d-%b-%Y')
                            END AS lot_expiration_date,
                            $resultColumn,
                            $patientIdColumn
                            FROM $table
                            WHERE sample_batch_id=$id";
            $result = $db->query($sQuery);
            $sampleCounter = 1;
            if (isset($bResult['position_type']) && $bResult['position_type'] == 'alpha-numeric') {
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
                    if (trim((string) $sample['lot_number']) !== '') {
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
                    $tbl .= '<td align="center" width="30%" style="vertical-align:middle;">' . BatchPdfHelper::buildBarcodeImageTag($general, $sample['sample_code'], $barcodeFormat) . '</td>';
                } else {
                    $tbl .= '<td align="center" width="30%" style="vertical-align:middle;line-height:30px;">' . BatchPdfHelper::buildBarcodeImageTag($general, $sample['sample_code'], $barcodeFormat) . '</td>';
                }
                if (isset($_GET['type']) && $_GET['type'] == 'covid19') {
                    $tbl .= '<td align="center" width="20%" style="vertical-align:middle;">' . $sample['remote_sample_code'] . '</td>';
                    $tbl .= '<td align="center" width="12.5%" style="vertical-align:middle;">' . $patientIdentifier . '</td>';
                    $tbl .= '<td align="center" width="12.5%" style="vertical-align:middle;">' . $sample[$resultColumn] . '</td>';
                } else {
                    $tbl .= '<td align="center" width="20%" style="vertical-align:middle;">' . $patientIdentifier . '</td>';
                    $tbl .= '<td align="center" width="12.5%" style="vertical-align:middle;">' . $lotDetails . '</td>';
                    $tbl .= '<td align="center" width="12.5%" style="vertical-align:middle;">' . $sample[$resultColumn] . '</td>';
                }
                $tbl .= '</tr>';
                $tbl .= '</table>';
                if (isset($bResult['position_type']) && $bResult['position_type'] == 'alpha-numeric') {
                    $sampleCounter = $alphaNumeric[($j + 1)];
                    $J++;
                } else {
                    $sampleCounter++;
                }
            }
        }

        $pdf->writeHTML($tbl);
        $filename = "BATCH-" . trim((string) $bResult['batch_code']) . '-' . date('d-m-Y-h-i-s') . '-' . MiscUtility::generateRandomString(5) . '.pdf';
        $batchesPath = MiscUtility::buildSafePath(TEMP_PATH, ["batches"]);
        $filename = MiscUtility::cleanFileName($filename);
        $pdf->Output($batchesPath . DIRECTORY_SEPARATOR . $filename);
        if ($bResult['printed_datetime'] == '') {
            $printedDatetime = DateUtility::getCurrentDateTime();
            $update = "UPDATE batch_details SET printed_datetime = ? WHERE batch_id = ?";
            $bResult = $db->rawQueryOne($update, [$printedDatetime, $id]);
        }
        exit;
    }
}
