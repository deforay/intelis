<?php

use Laminas\Diactoros\ServerRequest;
use App\Services\BatchService;
use App\Services\TestsService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Helpers\BatchPdfHelper;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;
use App\Helpers\CompactBatchPdfHelper;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var BatchService $batchService */
$batchService = ContainerRegistry::get(BatchService::class);

// Sanitized values from $request object
/** @var ServerRequest $request */
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());

try {
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


        $bQuery = "SELECT * FROM batch_details as b_d
                    LEFT JOIN instruments as i_c ON i_c.instrument_id=b_d.machine
                    LEFT JOIN user_details as u ON u.user_id=b_d.created_by
                    WHERE batch_id=?";
        $bResult = $db->rawQueryOne($bQuery, [$id]);

        if (isset($_GET['type']) && $_GET['type'] == 'covid19') {
            $dateQuery = "SELECT ct.*,
                        covid19.sample_tested_datetime,
                        covid19.lab_assigned_code,
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
            $dateQuery = "SELECT sample_tested_datetime, lab_assigned_code
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
            $pdf = new CompactBatchPdfHelper(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

            //$pdf->setHeading($bResult);
            $pdf->setHeading($bResult);


            // set document information
            $pdf->SetCreator('InteLIS');
            $pdf->SetAuthor('InteLIS');
            $pdf->SetTitle(_translate('LIS BATCH'));
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
            $pdf->SetMargins(PDF_MARGIN_LEFT, 20, PDF_MARGIN_RIGHT);
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
            $tbl = '';
            if (isset($_GET['type']) && $_GET['type'] == 'covid19') {

                $dateResult['kit_expiry_date'] = DateUtility::humanReadableDateFormat($dateResult['kit_expiry_date'] ?? '');
                $dateResult['lot_expiration_date'] = DateUtility::humanReadableDateFormat($dateResult['lot_expiration_date'] ?? '');
                $dateResult['result_printed_datetime'] = DateUtility::humanReadableDateFormat($dateResult['result_printed_datetime'] ?? '', true);

                $tbl = '<table cellspacing="2" cellpadding="6" style="width:100%;" border="0">
                <tr>
                    <th style="font-weight: bold;">' . _translate('Reagent/Kit Name') . ' :</th><td style="width:20%;">' . ((isset($dateResult['covid19_test_name']) && $dateResult['covid19_test_name'] != "") ? $dateResult['covid19_test_name'] : $dateResult['test_name']) . '</td>';
                if ((isset($dateResult['kit_lot_no']) && !empty($dateResult['kit_lot_no'])) || isset($dateResult['lot_number']) && !empty($dateResult['lot_number'])) {
                    $tbl .= '<th style="font-weight: bold;">' . _translate('Lot Number') . ' :</th><td>' . ((isset($dateResult['kit_lot_no']) && $dateResult['kit_lot_no'] != "") ? $dateResult['kit_lot_no'] : $dateResult['lot_number']) . '</td>';
                } else {
                    $tbl .= '<th></th>';
                }
                if ((isset($dateResult['kit_expiry_date']) && !empty($dateResult['kit_expiry_date'])) || isset($dateResult['lot_expiration_date']) && !empty($dateResult['lot_expiration_date'])) {
                    $tbl .= '<th style="font-weight: bold;width:20%;">' . _translate('Lot Expiry Date') . ' :</th><td>' . ((isset($dateResult['kit_expiry_date']) && $dateResult['kit_expiry_date'] != "") ? $dateResult['kit_expiry_date'] : $dateResult['lot_expiration_date']) . '</td>';
                } else {
                    $tbl .= '<th></th>';
                }
                $tbl .= '</tr>
                <tr>
                    <th style="font-weight: bold;">' . _translate('Printed By') . ' :</th><td>' . $_SESSION['userName'] . '</td>
                    <th style="font-weight: bold;">' . _translate('Printed On') . ':</th><td>' . date("d-M-Y h:i:A") . '</td>
                </tr>
            </table>
            <br>
            <hr>';
            }

            if (isset($bResult['label_order']) && trim((string) $bResult['label_order']) !== '') {
                $jsonToArray = json_decode((string) $bResult['label_order'], true);
                $sampleCounter = 1;
                $tbl .= '<table border="1" style="width:100%;border-bottom:1px solid black;"><tr nobr="true" style="width:100%;">';
                if (isset($bResult['position_type']) && $bResult['position_type'] == 'alpha-numeric') {
                    foreach ($batchService->excelColumnRange('A', 'H') as $value) {
                        foreach (range(1, 12) as $no) {
                            $alphaNumeric[] = $value . $no;
                        }
                    }
                    $sampleCounter = $alphaNumeric[0];
                }
                $a = 1;
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
                                                    remote_sample_code, lab_assigned_code,
                                                    $resultColumn,
                                                    lot_number,is_encrypted,
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
                            $tbl .= '<td align="center">';
                            $tbl .= $a . '. Sample ID : ' . $sampleResult[0]['sample_code'] . '<br>';
                            if (isset($sampleResult[0]['lab_assigned_code']) && !empty($sampleResult[0]['lab_assigned_code'])) {
                                $tbl .= '(' . $sampleResult[0]['lab_assigned_code'] . ')<br>';
                            }
                            if ($barcodeFormat == 'QRCODE') {
                                $tbl .= BatchPdfHelper::buildBarcodeImageTag($general, $sampleResult[0]['sample_code'], $barcodeFormat) . '<br>';
                            } else {
                                $tbl .= BatchPdfHelper::buildBarcodeImageTag($general, $sampleResult[0]['sample_code'], $barcodeFormat) . '<br>';
                            }

                            if (isset($_GET['type']) && $_GET['type'] == 'covid19') {
                                $tbl .= 'Remote Sample ID : ' . $sampleResult[0]['remote_sample_code'] . '<br>';
                                $tbl .= 'Patient Code : ' . $sampleResult[0][$patientIdColumn] . '<br>';
                            } else {
                                $tbl .= 'Patient Code : ' . $sampleResult[0][$patientIdColumn] . '<br>';
                                if (isset($lotDetails) && ($lotDetails !== '' && $lotDetails !== '0')) {
                                    $tbl .= 'Lot Number / Exp. Date : ' . $lotDetails . '<br>';
                                }
                            }
                            if (isset($sampleResult[0][$resultColumn]) && !empty($sampleResult[0][$resultColumn])) {
                                $tbl .= 'Test Result : ' . ucwords((string) $sampleResult[0][$resultColumn]);
                            }
                            $tbl .= '</td>';

                            if ($sampleCounter % 2 == 0) {
                                $tbl .= '</tr><tr>'; // Close the current row and start a new row
                            }
                        } else {
                            $label = str_replace("_", " ", (string) $jsonToArray[$alphaNumeric[$j]]);
                            $label = str_replace("in house", "In-House", $label);
                            $label = (str_replace("no of ", " ", $label));
                            $tbl .= '<td align="center">';
                            $tbl .= $label . '<br>';
                            $tbl .= '<br>';
                            $tbl .= '<br>';
                            $tbl .= '<br>';
                            $tbl .= '<br>';
                            $tbl .= '</td>';
                            if ($sampleCounter % 2 == 0) {
                                $tbl .= '</tr><tr>'; // Close the current row and start a new row
                            }
                        }
                        $sampleCounter = $alphaNumeric[($j + 1)];
                    } else {
                        $xplodJsonToArray = explode("_", (string) $jsonToArray[$j]);
                        if (count($xplodJsonToArray) > 1 && $xplodJsonToArray[0] === "s") {
                            if ((isset($_GET['type']) && $_GET['type'] == 'tb') || (isset($_GET['type']) && $_GET['type'] == 'cd4')) {
                                $sampleQuery = "SELECT sample_code,
                                            remote_sample_code, lab_assigned_code,
                                            $resultColumn,is_encrypted,
                                            $patientIdColumn,
                                            $patientFirstName,
                                            $patientLastName
                                            FROM $table
                                            WHERE $primaryKey =?";
                            } else {
                                $sampleQuery = "SELECT sample_code,
                                                remote_sample_code, lab_assigned_code,
                                                $resultColumn,
                                                lot_number,is_encrypted,
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
                            // echo '<pre>'; print_r($sampleResult);

                            $lotDetails = '';
                            $lotExpirationDate = '';
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

                            $tbl .= '<td height="80" align="center">';
                            $tbl .= $a . '.Sample ID : ' . $sampleResult[0]['sample_code'] . '<br>';
                            if (isset($sampleResult[0]['lab_assigned_code']) && !empty($sampleResult[0]['lab_assigned_code'])) {
                                $tbl .= '(' . $sampleResult[0]['lab_assigned_code'] . ')<br>';
                            }
                            if ($barcodeFormat == 'QRCODE') {
                                $tbl .= BatchPdfHelper::buildBarcodeImageTag($general, $sampleResult[0]['sample_code'], $barcodeFormat) . '<br>';
                            } else {
                                $tbl .= BatchPdfHelper::buildBarcodeImageTag($general, $sampleResult[0]['sample_code'], $barcodeFormat) . '<br>';
                            }
                            if (isset($_GET['type']) && $_GET['type'] == 'covid19') {
                                $tbl .= 'Remote Sample ID : ' . $sampleResult[0]['remote_sample_code'] . '<br>';
                                $tbl .= 'Patient Code : ' . $sampleResult[0][$patientIdColumn] . '<br>';
                            } else {
                                $tbl .= 'Patient Code : ' . $sampleResult[0][$patientIdColumn] . '<br>';
                                if (isset($lotDetails) && ($lotDetails !== '' && $lotDetails !== '0')) {
                                    $tbl .= 'Lot Number / Exp. Date : ' . $lotDetails . '<br>';
                                }
                            }
                            if (isset($sampleResult[0][$resultColumn]) && !empty($sampleResult[0][$resultColumn])) {
                                $tbl .= 'Test Result : ' . ucwords((string) $sampleResult[0][$resultColumn]);
                            }

                            $tbl .= '</td>';
                            // Start a new row after every third sample code
                            if ($sampleCounter % 2 == 0) {
                                $tbl .= '</tr><tr>'; // Close the current row and start a new row
                            }
                        } else {
                            $label = str_replace("_", " ", (string) $jsonToArray[$j]);
                            $label = str_replace("in house", "In-House", $label);
                            $label = (str_replace("no of ", " ", $label));
                            $tbl .= '<td align="center">';
                            $tbl .= $a . '.' . $label . '<br>';
                            $tbl .= '<br>';
                            $tbl .= '<br>';
                            $tbl .= '<br>';
                            $tbl .= '<br>';
                            $tbl .= '</td>';
                            // Start a new row after every third sample code
                            if ($sampleCounter % 2 == 0) {
                                $tbl .= '</tr><tr>'; // Close the current row and start a new row
                            }
                        }
                        $sampleCounter++;
                    }
                    $a++;
                }
                $tbl .= '</tr></table>';
            } else {
                $tbl .= '<table border="1" style="width:100%;border-bottom:1px solid black;"><tr nobr="true" style="width:100%;">';
                $noOfInHouseControls = 0;
                if (isset($bResult['number_of_in_house_controls']) && $bResult['number_of_in_house_controls'] != '' && $bResult['number_of_in_house_controls'] != null) {
                    $noOfInHouseControls = $bResult['number_of_in_house_controls'];
                    for ($i = 1; $i <= $bResult['number_of_in_house_controls']; $i++) {
                        $tbl .= '<tdalign="center">' . _translate('In-House Control') . ' ' . $i . '<br>
                            <br>
                            <br>
                            <br>
                            <br>
                            </td>';
                        if ($i % 2 == 0) {
                            $tbl .= '</tr><tr>'; // Close the current row and start a new row
                        }
                    }
                }
                $noOfManufacturerControls = 0;
                if (isset($bResult['number_of_manufacturer_controls']) && $bResult['number_of_manufacturer_controls'] != '' && $bResult['number_of_manufacturer_controls'] != null) {
                    $noOfManufacturerControls = $bResult['number_of_manufacturer_controls'];
                    for ($i = 1; $i <= $bResult['number_of_manufacturer_controls']; $i++) {
                        $sNo = $noOfInHouseControls + $i;
                        $tbl .= '<td align="center">' . _translate('Manfacturing Control') . ' ' . $i . '<br>
                            <br>
                            <br>
                            <br>
                            <br>
                            </td>';
                        if ($i % 2 == 0) {
                            $tbl .= '</tr><tr>'; // Close the current row and start a new row
                        }
                    }
                }
                $noOfCalibrators = 0;
                if (isset($bResult['number_of_calibrators']) && $bResult['number_of_calibrators'] != '' && $bResult['number_of_calibrators'] != null) {
                    $noOfCalibrators = $bResult['number_of_calibrators'];
                    for ($i = 1; $i <= $bResult['number_of_calibrators']; $i++) {
                        $sNo = $noOfInHouseControls + $noOfManufacturerControls + $i;
                        $tbl .= '<td align="center">' . _translate('Calibrator') . ' ' . $i . '<br>
                            <br>
                            <br>
                            <br>
                            <br>
                            </td>';
                        if ($i % 2 == 0) {
                            $tbl .= '</tr><tr>'; // Close the current row and start a new row
                        }
                    }
                }
                $sampleCounter = ($noOfInHouseControls + $noOfManufacturerControls + $noOfCalibrators + 1);
                $sQuery = "SELECT sample_code,
                            remote_sample_code, lab_assigned_code,
                            lot_number,
                            CASE
                                WHEN lot_expiration_date IS NULL THEN NULL
                                ELSE DATE_FORMAT(lot_expiration_date, '%d-%b-%Y')
                            END AS lot_expiration_date,
                            $resultColumn,
                            $patientIdColumn
                            FROM $table
                            WHERE sample_batch_id=$id  AND $resultColumn IS NULL";

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
                $b = 0;
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

                    $tbl .= '<td align="center">';
                    $tbl .= 'Sample ID : ' . $sample['sample_code'] . '<br>';
                    if (isset($sampleResult[0]['lab_assigned_code']) && !empty($sampleResult[0]['lab_assigned_code'])) {
                        $tbl .= '(' . $sampleResult[0]['lab_assigned_code'] . ')<br>';
                    }
                    if ($barcodeFormat == 'QRCODE') {
                        $tbl .= BatchPdfHelper::buildBarcodeImageTag($general, $sample['sample_code'], $barcodeFormat) . '<br>';
                    } else {
                        $tbl .= BatchPdfHelper::buildBarcodeImageTag($general, $sample['sample_code'], $barcodeFormat) . '<br>';
                    }
                    if (isset($_GET['type']) && $_GET['type'] == 'covid19') {
                        $tbl .= 'Remote Sample ID : ' . $sample['remote_sample_code'] . '<br>';
                        $tbl .= 'Patient Code : ' . $patientIdentifier . '<br>';
                    } else {
                        $tbl .= 'Patient Code : ' . $patientIdentifier . '<br>';
                        if (isset($lotDetails) && ($lotDetails !== '' && $lotDetails !== '0')) {
                            $tbl .= 'Lot Number / Exp. Date : ' . $lotDetails . '<br>';
                        }
                    }
                    if (isset($sample[$resultColumn]) && !empty($sample[$resultColumn])) {
                        $tbl .= 'Test Result : ' . $sample[$resultColumn] . '<br>';
                    }
                    $tbl .= '</td>';

                    if (isset($bResult['position_type']) && $bResult['position_type'] == 'alpha-numeric') {
                        $sampleCounter = $alphaNumeric[($b + 1)];
                        $b++;
                    } else {
                        $sampleCounter++;
                    }
                    if ($sampleCounter % 2 == 0) {
                        $tbl .= '</tr><tr>'; // Close the current row and start a new row
                    }
                }
                $tbl .= '</tr></table>';
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
        }
    }
} catch (Throwable $e) {
    LoggerUtility::logError($e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);
}
