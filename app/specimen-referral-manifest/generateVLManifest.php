<?php

use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Helpers\ManifestPdfHelper;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$id = base64_decode((string) $_POST['id']);

if (!empty($id)) {

    $sQuery = "SELECT remote_sample_code,
                        pd.number_of_samples,
                        fd.facility_name as clinic_name,
                        fd.facility_district,
                        patient_first_name,
                        patient_middle_name,
                        patient_last_name,
                        vl.patient_dob,
                        vl.patient_age_in_years,
                        sample_name,
                        sample_collection_date,
                        patient_gender,
                        patient_art_no,pd.manifest_code,
                        l.facility_name as lab_name,
                        u_d.user_name as releaser_name,
                        u_d.phone_number as phone,
                        u_d.email as email,
                        pd.request_created_datetime as created_date
                FROM specimen_manifests as pd
                LEFT JOIN form_vl as vl ON vl.sample_package_id=pd.manifest_id
                LEFT JOIN facility_details as fd ON fd.facility_id=vl.facility_id
                LEFT JOIN facility_details as l ON l.facility_id=vl.lab_id
                LEFT JOIN r_vl_sample_type as st ON st.sample_id=vl.specimen_type
                LEFT JOIN user_details as u_d ON u_d.user_id=pd.added_by
                WHERE pd.manifest_id IN($id)";

    $result = $db->query($sQuery);

    $labname = $result[0]['lab_name'] ?? "";

    MiscUtility::makeDirectory(TEMP_PATH . DIRECTORY_SEPARATOR . "sample-manifests", 0777, true);

    $globalConfig = $general->getGlobalConfig();
    $showPatientName = $globalConfig['vl_show_participant_name_in_manifest'];

    $db->where('manifest_id', $id);
    $bResult = $db->getOne('specimen_manifests');

    if (!empty($bResult)) {

        $oldPrintData = JsonUtility::decodeJson($bResult['manifest_print_history'], false);

        $newPrintData = array('printedBy' => $_SESSION['userId'], 'date' => DateUtility::getCurrentDateTime());
        $oldPrintData[] = $newPrintData;
        $db->where('manifest_id', $id);
        $db->update('specimen_manifests', array(
            'manifest_print_history' => json_encode($oldPrintData)
        ));

        $reasonHistory = json_decode($bResult['manifest_change_history']);

        // create new PDF document
        $pdf = new ManifestPdfHelper(_translate('Viral Load Sample Referral Manifest'), PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        $pdf->setHeading($globalConfig['logo'], $globalConfig['header'], $labname);

        // set document information
        $pdf->SetCreator('STS');
        $pdf->SetAuthor('STS');
        $pdf->SetTitle('Specimen Referral Manifest');
        $pdf->SetSubject('Specimen Referral Manifest');
        $pdf->SetKeywords('Specimen Referral Manifest');

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
        $pdf->setPageOrientation('L');

        // add a page
        $pdf->AddPage();
        if ($globalConfig['vl_form'] == COUNTRY\SIERRA_LEONE) {
            //$pdf->writeHTMLCell(0, 20, 10, 10, 'FACILITY RELEASER INFORMATION ', 0, 0, 0, true, 'C', true);
            $pdf->WriteHTML('<strong>FACILITY RELEASER INFORMATION</strong>');

            $tbl1 = '<br>';
            $tbl1 .= '<table nobr="true" style="width:100%;" border="0" cellpadding="2">';
            $tbl1 .= '<tr>
        <td align="left"> Releaser Name :  ' . $result[0]['releaser_name'] . '</td>
        <td align="left"> Date :  ' . DateUtility::humanReadableDateFormat($result[0]['created_date']) . '</td>
        </tr>
        <tr>
        <td align="left"> Phone No. :  ' . $result[0]['phone'] . '</td>
        <td align="left"> Email :  ' . $result[0]['email'] . '</td>
        </tr>
        <tr>
        <td align="left"> Facility Name :  ' . $result[0]['clinic_name'] . '</td>
        <td align="left"> District :  ' . $result[0]['facility_district'] . '</td>
        </tr>';
            $tbl1 .= '</table>';
            $pdf->writeHTMLCell('', '', 11, $pdf->getY(), $tbl1, 0, 1, 0, true, 'C');

            $pdf->WriteHTML('<p></p><strong>SPECIMEN PACKAGING</strong>');

            $tbl2 = '<br>';
            $tbl2 .= '<table nobr="true" style="width:100%;" border="0" cellpadding="2">';
            $tbl2 .= '<tr>
        <td align="left"> Number of specimen included :  ' . $result[0]['number_of_samples'] . '</td>
        <td align="left"> Forms completed and included :  Yes / No</td>
        </tr>
        <tr>
        <td align="left"> Packaged By :  ..................</td>
        <td align="left"> Date :  ...................</td>
        </tr>';
            $tbl2 .= '</table>';

            $pdf->writeHTMLCell('', '', 11, $pdf->getY(), $tbl2, 0, 1, 0, true, 'C');

            $pdf->WriteHTML('<p></p><strong>CHAIN OF CUSTODY : </strong>(persons relinquishing and receiving specimen fill their respective sections)');
            $pdf->WriteHTML('<p></p><strong>To be completed at facility in the presence of specimen courier</strong>');
            $tbl3 = '<br>';
            $tbl3 .= '<table border="1">
        <tr>
            <td colspan="2">Relinquished By (Laboratory)</td>
            <td colspan="2">Received By (Courier)</td>
        </tr>
        <tr>
            <td align="left"> Name : <br><br> Sign : <br><br> Phone No. :</td>
            <td align="left"> Date : <br><p></p><br> Time :</td>
            <td align="left"> Name : <br><br> Sign : <br><br> Phone No. :</td>
            <td align="left"> Date : <br><p></p><br> Time :</td>
        </tr>
        </table>';
            $pdf->writeHTMLCell('', '', 11, $pdf->getY(), $tbl3, 0, 1, 0, true, 'C');

            $pdf->WriteHTML('<p></p><strong>To be completed at testing laboratory by specimen reception personnel</strong>');
            $tbl4 = '<br>';
            $tbl4 .= '<table border="1">
            <tr>
                <td colspan="2">Relinquished By (Courier)</td>
                <td colspan="2">Received By (Laboratory)</td>
            </tr>
            <tr>
                <td align="left"> Name : <br><br> Sign : <br><br> Phone No. :</td>
                <td align="left"> Date : <br><p></p><br> Time :</td>
                <td align="left"> Name : <br><br> Sign : <br><br> Phone No. :</td>
                <td align="left"> Date : <br><p></p><br> Time :</td>
            </tr>
        </table>';
            $pdf->writeHTMLCell('', '', 11, $pdf->getY(), $tbl4, 0, 1, 0, true, 'C');
        }


        $tbl = '<p></p><span style="font-size:1.7em;"> ' . $result[0]['manifest_code'];
        $tbl .= '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img style="width:200px;height:30px;" src="' . $general->getBarcodeImageContent($result[0]['manifest_code']) . '">';
        $tbl .=  '</span><br>';

        if (!empty($result)) {

            $tbl .= '<br><table nobr="true" style="width:100%;" border="1" cellpadding="2">';
            $tbl .=     '<tr nobr="true">
                        <td  style="font-size:11px;width:5%;"><strong>' . _translate('S. No.') . '</strong></td>
                        <td  style="font-size:11px;width:12%;"><strong>' . _translate('SAMPLE ID') . '</strong></td>
                        <td  style="font-size:11px;width:15%;"><strong>' . _translate('HEALTH FACILITY, DISTRICT') . '</strong></td>
                        <td  style="font-size:11px;width:15%;"><strong>' . _translate('PATIENT') . '</strong></td>
                        <td  style="font-size:11px;width:5%;"><strong>' . _translate('AGE') . '</strong></td>
                        <td  style="font-size:11px;width:8%;"><strong>' . _translate('DATE OF BIRTH') . '</strong></td>
                        <td  style="font-size:11px;width:8%;"><strong>' . _translate('SEX') . '</strong></td>
                        <td  style="font-size:11px;width:8%;"><strong>' . _translate('SPECIMEN TYPE') . '</strong></td>
                        <td  style="font-size:11px;width:8%;"><strong>' . _translate('COLLECTION DATE') . '</strong></td>
                        <td  style="font-size:11px;width:20%;"><strong>' . _translate('SAMPLE BARCODE') . '</strong></td>
                    </tr>';

            $sampleCounter = 1;

            $tbl .= '</table>';

            foreach ($result as $sample) {

                // $params = $pdf->serializeTCPDFtagParameters(array($sample['remote_sample_code'], 'C39', '', '', 0, 9, 0.25, array('border' => false, 'align' => 'L', 'padding' => 1, 'fgcolor' => array(0, 0, 0), 'bgcolor' => array(255, 255, 255), 'text' => false, 'font' => 'helvetica', 'fontsize' => 9, 'stretchtext' => 2), 'N'));
                $tbl .= '<table nobr="true" style="width:100%;" border="1" cellpadding="2">';
                $tbl .= '<tr nobr="true">';



                $tbl .= '<td style="font-size:11px;width:5%;">' . $sampleCounter . '.</td>';
                $tbl .= '<td style="font-size:11px;width:12%;">' . $sample['remote_sample_code'] . '</td>';
                $tbl .= '<td style="font-size:11px;width:15%;">' . ($sample['clinic_name']) . ', ' . ($sample['facility_district']) . '</td>';
                if (isset($showPatientName) && $showPatientName == "no") {
                    $tbl .= '<td style="font-size:11px;width:15%;">' . $sample['patient_art_no'] . '</td>';
                } else {
                    $tbl .= '<td style="font-size:11px;width:15%;">' . ($sample['patient_first_name'] . " " . $sample['patient_middle_name'] . " " . $sample['patient_last_name']) . '<br>' . $sample['patient_art_no'] . '</td>';
                }
                $tbl .= '<td style="font-size:11px;width:5%;">' . ($sample['patient_age_in_years']) . '</td>';
                $tbl .= '<td style="font-size:11px;width:8%;">' . DateUtility::humanReadableDateFormat($sample['patient_dob'] ?? '') . '</td>';
                $tbl .= '<td style="font-size:11px;width:8%;">' . ucwords(str_replace("_", " ", (string) $sample['patient_gender'])) . '</td>';
                $tbl .= '<td style="font-size:11px;width:8%;">' . ucwords((string) $sample['sample_name']) . '</td>';
                $tbl .= '<td style="font-size:11px;width:8%;">' . DateUtility::humanReadableDateFormat($sample['sample_collection_date'] ?? '') . '</td>';
                $tbl .= '<td style="font-size:11px;width:20%;"><img style="width:180px;height:25px;" src="' . $general->getBarcodeImageContent($sample['remote_sample_code']) . '"/></td>';


                $tbl .= '</tr>';
                $tbl .= '</table>';

                $sampleCounter++;
            }
        }

        $tbl .= '<br><br><table cellspacing="0" style="width:100%;">';
        $tbl .= '<tr>';
        $tbl .= '<td align="left" style="vertical-align:middle;font-size:11px;width:33.33%;"><strong>' . _translate('Generated By') . ' : </strong><br>' . $_SESSION['userName'] . '</td>';
        $tbl .= '<td align="left" style="vertical-align:middle;font-size:11px;width:33.33%;"><strong>' . _translate('Verified By') . ' :  </strong></td>';
        $tbl .= '<td align="left" style="vertical-align:middle;font-size:11px;width:33.33%;"><strong>' . _translate('Received By') . ' : </strong><br>(at ' . $labname . ')</td>';
        $tbl .= '</tr>';
        $tbl .= '</table><br><br>';

        if (!empty($reasonHistory)) {
            $tbl .= _translate('Manifest Change History');
            $tbl .= '<br><br><table nobr="true" style="width:100%;" border="1" cellpadding="2"><tr nobr="true">';
            $tbl .= '<th>' . _translate('Reason for Change') . '</th>';
            $tbl .= '<th>' . _translate('Changed By') . '</th>';
            $tbl .= '<th>' . _translate('Changed On') . '</th>';
            $tbl .= '</tr>';
            foreach ($reasonHistory as $change) {
                $userResult = $usersService->findUserByUserId($change->changedBy);
                $userName = $userResult['user_name'];
                $tbl .= '<tr nobr="true">';
                $tbl .= '<td align="left" style="vertical-align:middle;font-size:11px;width:33.33%;">' . $change->reason . '</td>';
                $tbl .= '<td align="left" style="vertical-align:middle;font-size:11px;width:33.33%;">' . $userName . '</td>';
                $tbl .= '<td align="left" style="vertical-align:middle;font-size:11px;width:33.33%;">' . DateUtility::humanReadableDateFormat($change->date) . '</td>';
                $tbl .= '</tr>';
            }
            $tbl .= '</table>';
        }


        //$tbl.='<br/><br/><strong style="text-align:left;">Printed On:  </strong>'.date('d/m/Y H:i:s');
        $pdf->writeHTMLCell('', '', 11, $pdf->getY(), $tbl, 0, 1, 0, true, 'C');

        $filename = trim((string) $bResult['manifest_code']) . '-' . date('Ymd') . '-' . MiscUtility::generateRandomString(6) . '-Manifest.pdf';
        $manifestsPath = MiscUtility::buildSafePath(TEMP_PATH, ["sample-manifests"]);
        $filename = MiscUtility::cleanFileName($filename);
        $pdf->Output($manifestsPath . DIRECTORY_SEPARATOR . $filename, "F");
        echo $filename;
    }
}
