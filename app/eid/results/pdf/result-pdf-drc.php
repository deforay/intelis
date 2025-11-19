<?php

// this file is included in eid/results/generate-result-pdf.php

use App\Services\EidService;
use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Helpers\PdfWatermarkHelper;
use App\Registries\ContainerRegistry;
use App\Helpers\ResultPDFHelpers\EIDResultPDFHelper;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

/** @var EidService $eidService */
$eidService = ContainerRegistry::get(EidService::class);
$eidResults = $eidService->getEidResults();


if (!empty($result)) {

    $signQuery = "SELECT * from lab_report_signatories where lab_id=? AND test_types like '%eid%' AND signatory_status like 'active' ORDER BY display_order ASC";
    $signResults = $db->rawQuery($signQuery, [$result['lab_id']]);

    $_SESSION['aliasPage'] = $page;
    if (!isset($result['labName'])) {
        $result['labName'] = '';
    }
    $draftTextShow = false;
    //Set watermark text
    $counter = count($mFieldArray);
    //Set watermark text
    for ($m = 0; $m < $counter; $m++) {
        if (!isset($result[$mFieldArray[$m]]) || trim((string) $result[$mFieldArray[$m]]) === '' || $result[$mFieldArray[$m]] == null || $result[$mFieldArray[$m]] == '0000-00-00 00:00:00') {
            $draftTextShow = true;
            break;
        }
    }
    // create new PDF document
    $pdf = new EIDResultPDFHelper(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    if (file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $result['lab_id'] . DIRECTORY_SEPARATOR . $result['facilityLogo'])) {
        $logoPrintInPdf = UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $result['lab_id'] . DIRECTORY_SEPARATOR . $result['facilityLogo'];
    } else {
        $logoPrintInPdf = $arr['logo'];
    }
    $headerText = isset($result['headerText']) && $result['headerText'] != '' ? $result['headerText'] : $arr['header'];

    $pdf->setHeading($logoPrintInPdf, $headerText, $result['labName'], '', $result['lab_id'], $arr['vl_form']);
    // set document information
    $pdf->SetCreator('InteLIS');

    $pdf->SetTitle('PROGRAMME NATIONAL DE LUTTE CONTRE LE SIDA ET IST');
    //$pdf->SetSubject('TCPDF Tutorial');
    //$pdf->SetKeywords('TCPDF, PDF, example, test, guide');

    // set default header data
    $pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, PDF_HEADER_TITLE, PDF_HEADER_STRING);

    // set header and footer fonts
    $pdf->setHeaderFont([PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN]);
    $pdf->setFooterFont([PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA]);

    // set default monospaced font
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

    // set margins
    if (isset($headerText) && $headerText != '') {
        $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP + 14, PDF_MARGIN_RIGHT);
    } else {
        $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP + 7, PDF_MARGIN_RIGHT);
    }
    $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
    $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

    // set auto page breaks
    $pdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);

    // set image scale factor
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

    // set some language-dependent strings (optional)
    //if (@file_exists(dirname(__FILE__).'/lang/eng.php')) {
    //    require_once(dirname(__FILE__).'/lang/eng.php');
    //    $pdf->setLanguageArray($l);
    //}

    // ---------------------------------------------------------

    // set font
    $pdf->SetFont('helvetica', '', 18);
    $pdf->AddPage();

    if (!isset($result['facility_code']) || trim((string) $result['facility_code']) === '') {
        $result['facility_code'] = '';
    }
    if (!isset($result['facility_state']) || trim((string) $result['facility_state']) === '') {
        $result['facility_state'] = '';
    }
    if (!isset($result['facility_district']) || trim((string) $result['facility_district']) === '') {
        $result['facility_district'] = '';
    }
    if (!isset($result['facility_name']) || trim((string) $result['facility_name']) === '') {
        $result['facility_name'] = '';
    }
    if (!isset($result['labName']) || trim((string) $result['labName']) === '') {
        $result['labName'] = '';
    }
    //Set Age
    $age = 'Unknown';
    if (isset($result['child_age']) && trim((string) $result['child_age']) !== '' && trim((string) $result['child_age']) > 0) {
        $age = $result['child_age'];
    } elseif (isset($result['child_dob']) && trim((string) $result['child_dob']) !== '' && $result['child_dob'] != '0000-00-00') {
        $todayDate = strtotime(date('Y-m-d'));
        $dob = strtotime((string) $result['child_dob']);
        $difference = $todayDate - $dob;
        $seconds_per_year = 60 * 60 * 24 * 365;
        $age = round($difference / $seconds_per_year);
    }


    $result['result_printed_datetime'] = DateUtility::humanReadableDateFormat($result['result_printed_datetime'] ?? DateUtility::getCurrentDateTime(), true);
    $result['sample_tested_datetime'] = DateUtility::humanReadableDateFormat($result['sample_tested_datetime'] ?? '', true);
    $result['sample_received_at_lab_datetime'] = DateUtility::humanReadableDateFormat($result['sample_received_at_lab_datetime'] ?? '', true);
    $result['sample_collection_date'] = DateUtility::humanReadableDateFormat($result['sample_collection_date'] ?? '', true);

    if (!isset($result['child_gender']) || trim((string) $result['child_gender']) === '') {
        $result['child_gender'] = _translate('Unreported');
    }


    $resultApprovedBy = $result['approvedBy'] ?? null;
    if (empty($resultApprovedBy)) {
        $approvedByInfo = $usersService->getUserNameAndSignature($result['defaultApprovedBy']);
        $resultApprovedBy = $approvedByInfo['user_name'];
        $result['approvedBySignature'] = $approvedByInfo['user_signature'];
    }

    if (empty($result['result_approved_datetime']) && !empty($result['sample_tested_datetime'])) {
        $result['result_approved_datetime'] = $result['sample_tested_datetime'];
    }

    if (empty($result['result_reviewed_datetime']) && !empty($result['sample_tested_datetime'])) {
        $result['result_reviewed_datetime'] = $result['sample_tested_datetime'];
    }

    if (!empty($result['approvedBySignature'])) {
        $approvedBySignaturePath = MiscUtility::getFullImagePath($result['approvedBySignature'], UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature");
    }


    $vlResult = '';
    $smileyContent = '';
    $showMessage = '';
    $tndMessage = '';
    $smileyShow = false;
    $messageTextSize = '12px';
    if ($result['result'] != null && trim((string) $result['result']) !== '') {
        $resultType = is_numeric($result['result']);
        $vlResult = $eidResults[$result['result']];
        if ($vlResult == 'negative') {
            if (isset($smileyShow) && $smileyShow != '') {
                $smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="/assets/img/smiley_smile.png" alt="smile_face"/>';
            }
            $showMessage = "";
            $tndMessage = '';
        } elseif ($vlResult == 'positive') {
            if (isset($smileyShow) && $smileyShow != '') {
                $smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="/assets/img/smiley_frown.png" alt="frown_face"/>';
            }
            $showMessage = '';
            $messageTextSize = '15px';
        } elseif ($vlResult == 'indeterminate') {
            //if (isset($smileyShow) && $smileyShow != '') {
            //$smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="/assets/img/cross.png" alt="frown_face"/>';
            //}
            $showMessage = '';
            $messageTextSize = '15px';
        }
    }

    if (!empty($result['is_encrypted']) && $result['is_encrypted'] == 'yes') {
        $key = (string) $general->getGlobalConfig('key');
        $result['child_id'] = $general->crypto('decrypt', $result['child_id'], $key);
    }

    $html = '<table style="padding:0px 2px 2px 2px;">';
    $html .= '<tr>';
    $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Échantillon id</td>';
    $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Date du prélèvement</td>';
    $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Code de l’enfant (Patient)</td>';
    $html .= '</tr>';
    $html .= '<tr>';
    $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $result['sample_code'] . '</td>';
    $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $result['sample_collection_date'] . '</td>';

    $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $result['child_id'] . '</td>';
    $html .= '</tr>';
    $html .= '<tr>';
    $html .= '<td colspan="3" style="line-height:10px;"></td>';
    $html .= '</tr>';
    //$html .='<tr>';
    // $html .='<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Pr�nom du patient</td>';
    // $html .='<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Nom de famille du patient</td>';
    // $html .='<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Mobile No.</td>';
    //$html .='</tr>';
    //$html .='<tr>';
    //  $html .='<td style="line-height:11px;font-size:11px;text-align:left;">'.($result['patient_first_name']).'</td>';
    //  $html .='<td style="line-height:11px;font-size:11px;text-align:left;">'.($result['patient_last_name']).'</td>';
    //  $html .='<td style="line-height:11px;font-size:11px;text-align:left;">'.$result['patient_mobile_number'].'</td>';
    //$html .='</tr>';
    //$html .='<tr>';
    //$html .='<td colspan="3" style="line-height:10px;"></td>';
    //$html .='</tr>';
    $html .= '<tr>';
    $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Âge en mois</td>';
    $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Sexe</td>';
    $implementationPartner = "Partnaire d'appui";
    $html .= '<td colspan="2" style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . $implementationPartner . '</td>';
    $html .= '</tr>';
    $html .= '<tr>';
    $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $age . '</td>';
    $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . _translate(_capitalizeFirstLetter($result['child_gender'])) . '</td>';
    $html .= '<td colspan="2" style="line-height:11px;font-size:11px;text-align:left;">' . $result['i_partner_name'] . '</td>';
    $html .= '</tr>';
    $html .= '<tr>';
    $html .= '<td colspan="3" style="line-height:10px;"></td>';
    $html .= '</tr>';
    $html .= '<tr>';
    $html .= '<td colspan="3" style="line-height:2px;border-bottom:2px solid #d3d3d3;"></td>';
    $html .= '</tr>';
    $html .= '<tr>';
    $html .= '<td colspan="3" style="line-height:10px;"></td>';
    $html .= '</tr>';
    $html .= '<tr>';
    $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Code Clinique</td>';
    $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Province</td>';
    $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Zone de santé</td>';
    $html .= '</tr>';
    $html .= '<tr>';
    $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $result['facility_code'] . '</td>';
    $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . ($result['facility_state']) . '</td>';
    $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . ($result['facility_district']) . '</td>';
    $html .= '</tr>';
    $html .= '<tr>';
    $html .= '<td colspan="3" style="line-height:10px;"></td>';
    $html .= '</tr>';
    $html .= '<tr>';
    $healthCenter = "POINT DE COLLECT";
    $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . $healthCenter . '</td>';
    $html .= '<td colspan="2" style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;"></td>';
    $html .= '</tr>';
    $html .= '<tr>';
    $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . ($result['facility_name']) . '</td>';
    $html .= '<td colspan="2" style="line-height:11px;font-size:11px;text-align:left;"></td>';
    $html .= '</tr>';
    $html .= '<tr>';
    $html .= '<td colspan="3" style="line-height:10px;"></td>';
    $html .= '</tr>';
    $html .= '<tr>';
    $html .= '<td colspan="3" style="line-height:2px;border-bottom:2px solid #d3d3d3;"></td>';
    $html .= '</tr>';
    $html .= '<tr>';
    $html .= '<td colspan="3" style="line-height:10px;"></td>';
    $html .= '</tr>';
    $html .= '<tr>';
    $html .= '<td colspan="3">';
    $html .= '<table style="padding:2px;">';
    $html .= '<tr>';
    $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Date de réception de l\'échantillon</td>';
    $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Date de remise du résultat</td>';
    $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Date de réalisation de la charge virale</td>';


    $html .= '</tr>';
    $html .= '<tr>';
    $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $result['sample_received_at_lab_datetime'] . '</td>';
    $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $result['result_printed_datetime'] . '</td>';
    $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $result['sample_tested_datetime'] . '</td>';
    $html .= '</tr>';

    $html .= '<tr>';
    $html .= '<td colspan="3" style="line-height:16px;"></td>';
    $html .= '</tr>';
    $html .= '<tr>';
    $html .= '<td colspan="3" style="line-height:16px;"></td>';
    $html .= '</tr>';


    $html .= '<tr><td colspan="2" style="line-height:70px;font-size:18px;text-align:left;background-color:#dbdbdb;">&nbsp;&nbsp;Résultat&nbsp;&nbsp;&nbsp;&nbsp;:&nbsp;&nbsp;&nbsp;&nbsp;' . $vlResult . '</td>';
    $html .= '<td colspan="1">' . $smileyContent . '</td></tr>';
    $html .= '</table>';
    $html .= '</td>';
    $html .= '</tr>';


    $html .= '<tr>';
    $html .= '<td colspan="3" style="line-height:16px;"></td>';
    $html .= '</tr>';

    $html .= '<tr>';
    $html .= '<td colspan="3" style="line-height:16px;"></td>';
    $html .= '</tr>';

    if (empty($signResults)) {
        if (!empty($approvedBySignaturePath) && file_exists($approvedBySignaturePath) && !empty($resultApprovedBy)) {
            $html .= '<tr>';
            $html .= '<td colspan="3" style="line-height:11px;font-size:11px;font-weight:bold;"><img src="' . $approvedBySignaturePath . '" style="width:70px;margin-top:-20px;" /><br></td>';
            $html .= '</tr>';
        }
        $html .= '<tr>';
        $html .= '<td colspan="3" style="line-height:11px;font-size:11px;font-weight:bold;">Approuvé par&nbsp;&nbsp;:&nbsp;&nbsp;<span style="font-weight:normal;">' . $resultApprovedBy . '</span></td>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td colspan="3" style="line-height:10px;"></td>';
        $html .= '</tr>';
        if (trim((string) $result['lab_tech_comments']) !== '') {
            $html .= '<tr>';
            $html .= '<td colspan="3" style="line-height:11px;font-size:11px;font-weight:bold;">Commentaires du laboratoire&nbsp;&nbsp;:&nbsp;&nbsp;<span style="font-weight:normal;">' . ($result['lab_tech_comments']) . '</span></td>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<td colspan="3" style="line-height:10px;"></td>';
            $html .= '</tr>';
        }
    }

    $html .= '<tr>';
    $html .= '<td colspan="3" style="line-height:14px;"></td>';
    $html .= '</tr>';

    $html .= '<tr>';
    $html .= '<td colspan="3">';
    if (!empty($signResults)) {
        $html .= '<table style="width:100%;padding:3px;border:1px solid gray;">';
        $html .= '<tr>';
        $html .= '<td style="line-height:17px;font-size:13px;font-weight:bold;text-align:left;border-bottom:1px solid gray;">AUTORISÉ PAR</td>';
        $html .= '<td style="line-height:17px;font-size:13px;font-weight:bold;text-align:left;border-bottom:1px solid gray;border-left:1px solid gray;">IMPRIMER LE NOM</td>';
        $html .= '<td style="line-height:17px;font-size:13px;font-weight:bold;text-align:left;border-bottom:1px solid gray;border-left:1px solid gray;">SIGNATURE</td>';
        $html .= '<td style="line-height:17px;font-size:13px;font-weight:bold;text-align:left;border-bottom:1px solid gray;border-left:1px solid gray;">DATE & HEURE</td>';
        $html .= '</tr>';
        foreach ($signResults as $key => $row) {
            $lmSign = UPLOAD_PATH . "/labs/" . $row['lab_id'] . "/signatures/" . $row['signature'];
            $signature = '';
            if (MiscUtility::isImageValid($lmSign)) {
                $signature = '<img src="' . $lmSign . '" style="width:40px;" />';
            }
            $html .= '<tr>';
            $html .= '<td style="line-height:17px;font-size:11px;text-align:left;font-weight:bold;border-bottom:1px solid gray;">' . $row['designation'] . '</td>';
            $html .= '<td style="line-height:17px;font-size:11px;text-align:left;border-bottom:1px solid gray;border-left:1px solid gray;">' . $row['name_of_signatory'] . '</td>';
            $html .= '<td style="line-height:17px;font-size:11px;text-align:left;border-bottom:1px solid gray;border-left:1px solid gray;">' . $signature . '</td>';
            $html .= '<td style="line-height:17px;font-size:11px;text-align:left;border-bottom:1px solid gray;border-left:1px solid gray;">' . date('d-M-Y H:i:s a') . '</td>';
            $html .= '</tr>';
        }
        $html .= '</table>';
    }
    $html .= '</td>';
    $html .= '</tr>';
    $html .= '<tr>';
    $html .= '<td colspan="3" style="line-height:12px;"></td>';
    $html .= '</tr>';
    $html .= '<tr>';
    $html .= '<td colspan="3" style="line-height:2px;border-bottom:2px solid #d3d3d3;"></td>';
    $html .= '</tr>';
    $html .= '<tr>';
    $html .= '<td colspan="3" style="line-height:12px;"></td>';
    $html .= '</tr>';
    $html .= '<tr>';
    $html .= '<td colspan="3">';
    $html .= '<table>';
    $html .= '<tr>';
    $html .= '<td style="font-size:10px;text-align:left;">Printed on : ' . $printDate . '&nbsp;&nbsp;' . $printDateTime . '</td>';
    $html .= '<td style="font-size:10px;text-align:left;width:60%;"></td>';
    $html .= '</tr>';
    $html .= '</table>';
    $html .= '</td>';
    $html .= '</tr>';
    $html .= '</table>';
    if ($result['result'] != '') {
        $pdf->writeHTML($html);
        $pdf->lastPage();
        $filename = $pathFront . DIRECTORY_SEPARATOR . 'p' . $page . '.pdf';
        $pdf->Output($filename, "F");
        if ($draftTextShow) {
            //Watermark section
            $watermark = new PdfWatermarkHelper();
            $watermark->setFullPathToFile($filename);
            $fullPathToFile = $filename;
            $watermark->Output($filename, "F");
        }
        $pages[] = $filename;
        $page++;
    }
    if (isset($_POST['source']) && trim((string) $_POST['source']) === 'print') {
        //Add event log
        $eventType = 'print-result';
        $action = $_SESSION['userName'] . ' print the EID test result with patient code ' . $result['child_id'];
        $resource = 'print-test-result';
        $data = ['event_type' => $eventType, 'action' => $action, 'resource' => $resource, 'date_time' => DateUtility::getCurrentDateTime()];
        $db->insert($tableName1, $data);
        //Update print datetime in VL tbl.
        $vlQuery = "SELECT result_printed_datetime FROM form_eid as vl WHERE vl.eid_id ='" . $result['eid_id'] . "'";
        $vlResult = $db->query($vlQuery);
        if ($vlResult[0]['result_printed_datetime'] == null || trim((string) $vlResult[0]['result_printed_datetime']) === '' || $vlResult[0]['result_printed_datetime'] == '0000-00-00 00:00:00') {
            $db->where('eid_id', $result['eid_id']);
            $db->update($tableName2, ['result_printed_datetime' => DateUtility::getCurrentDateTime()]);
        }
    }
}
