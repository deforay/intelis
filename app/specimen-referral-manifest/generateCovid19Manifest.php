<?php

use App\Utilities\DateUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\UsersService;
use App\Helpers\ManifestPdfHelper;
use App\Registries\ContainerRegistry;
use App\Utilities\MiscUtility;




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

if (isset($_POST['frmSrc']) && trim((string) $_POST['frmSrc']) == 'pk2') {
    $id = $_POST['ids'];
}

if (trim((string) $id) != '') {

    $sQuery = "SELECT remote_sample_code,
                    pd.number_of_samples,
                    fd.facility_name as clinic_name,
                    fd.facility_district,
                    CONCAT(COALESCE(patient_name,''), COALESCE(patient_surname,'')) as `patient_fullname`,
                    vl.patient_dob,
                    vl.patient_age,
                    sample_collection_date,
                    patient_gender,
                    patient_id,
                    pd.package_code,
                    l.facility_name as lab_name,
                    u_d.user_name as releaser_name,
                    u_d.phone_number as phone,
                    u_d.email as email,
                    DATE_FORMAT(pd.request_created_datetime,'%d-%b-%Y') as created_date
                FROM specimen_manifests as pd
                JOIN form_covid19 as vl ON vl.sample_package_id=pd.package_id
                JOIN facility_details as fd ON fd.facility_id=vl.facility_id
                JOIN facility_details as l ON l.facility_id=vl.lab_id
                LEFT JOIN user_details as u_d ON u_d.user_id=pd.added_by
                WHERE pd.package_id IN(?)";
    $result = $db->rawQuery($sQuery, [$id]);


    $labname = $result[0]['lab_name'] ?? "";

    $arr = $general->getGlobalConfig();
    $showPatientName = $arr['covid19_show_participant_name_in_manifest'];
    $bQuery = "SELECT * FROM specimen_manifests as pd WHERE package_id IN(?)";
    $bResult = $db->rawQuery($bQuery, [$id]);
    if (!empty($bResult)) {

        $oldPrintData = json_decode($bResult[0]['manifest_print_history']);

        $newPrintData = array('printedBy' => $_SESSION['userId'],'date' => DateUtility::getCurrentDateTime());
        $oldPrintData[] = $newPrintData;
        $db->where('package_id', $id);
        $db->update('specimen_manifests', array(
            'manifest_print_history' => json_encode($oldPrintData)
        ));

        $reasonHistory = json_decode($bResult[0]['manifest_change_history']);

        // create new PDF document
        $pdf = new ManifestPdfHelper(_translate('Covid-19 Sample Referral Manifest'), PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        $pdf->setHeading($general->getGlobalConfig('logo'), $general->getGlobalConfig('header'), $labname);

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


        if ($arr['vl_form'] == COUNTRY\SIERRA_LEONE) {
            //$pdf->writeHTMLCell(0, 20, 10, 10, 'FACILITY RELEASER INFORMATION ', 0, 0, 0, true, 'C', true);
            $pdf->WriteHTML('<strong>FACILITY RELEASER INFORMATION</strong>');

            $tbl1 = '<br>';
            $tbl1 .= '<table nobr="true" style="width:100%;" border="0" cellpadding="2">';
            $tbl1 .= '<tr>
            <td align="left"> Releaser Name :  ' . $result[0]['releaser_name'] . '</td>
            <td align="left"> Date :  ' . $result[0]['created_date'] . '</td>
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

        $tbl = '<p></p><span style="font-size:1.7em;"> ' . $result[0]['package_code'];
        $tbl .= '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img style="width:200px;height:30px;" src="' . $general->getBarcodeImageContent($result[0]['package_code']) . '">';
        $tbl .=  '</span><br>';
        if (!empty($result)) {

            $tbl .= '<table style="width:100%;border:1px solid #333;">

                    <tr nobr="true">';
            if ($showPatientName == "yes") {

                $tbl .= ' <td align="center" style="font-size:11px;width:3%;border:1px solid #333;" ><strong><em>S. No.</em></strong></td>
                        <td align="center" style="font-size:11px;width:12%;border:1px solid #333;"  ><strong><em>SAMPLE ID</em></strong></td>
                        <td align="center" style="font-size:11px;width:14%;border:1px solid #333;"  ><strong><em>Health facility, District</em></strong></td>
                        <td align="center" style="font-size:11px;width:11%;border:1px solid #333;"  ><strong><em>Patient Name</em></strong></td>
                        <td align="center" style="font-size:11px;width:10%;border:1px solid #333;"  ><strong><em>Patient ID</em></strong></td>
                        <td align="center" style="font-size:11px;width:8%;border:1px solid #333;"  ><strong><em>Date of Birth</em></strong></td>
                        <td align="center" style="font-size:11px;width:7%;border:1px solid #333;"  ><strong><em>Patient Sex</em></strong></td>
                        <td align="center" style="font-size:11px;width:10%;border:1px solid #333;"  ><strong><em>Sample Collection Date</em></strong></td>
                        <!-- <td align="center" style="font-size:11px;width:7%;border:1px solid #333;"  ><strong><em>Test Requested</em></strong></td> -->
                        <td align="center" style="font-size:11px;width:22%;border:1px solid #333;"  ><strong><em>Sample Barcode</em></strong></td>';
            } else {
                $tbl .= ' <td align="center" style="font-size:11px;width:3%;border:1px solid #333;" ><strong><em>S. No.</em></strong></td>
                        <td align="center" style="font-size:11px;width:12%;border:1px solid #333;"  ><strong><em>SAMPLE ID</em></strong></td>
                        <td align="center" style="font-size:11px;width:14%;border:1px solid #333;"  ><strong><em>Health facility, District</em></strong></td>
                        <td align="center" style="font-size:11px;width:10%;border:1px solid #333;"  ><strong><em>Patient ID</em></strong></td>
                        <td align="center" style="font-size:11px;width:8%;border:1px solid #333;"  ><strong><em>Date of Birth</em></strong></td>
                        <td align="center" style="font-size:11px;width:10%;border:1px solid #333;"  ><strong><em>Patient Sex</em></strong></td>
                        <td align="center" style="font-size:11px;width:12%;border:1px solid #333;"  ><strong><em>Sample Collection Date</em></strong></td>
                        <!-- <td align="center" style="font-size:11px;width:10%;border:1px solid #333;"  ><strong><em>Test Requested</em></strong></td> -->
                        <td align="center" style="font-size:11px;width:25%;border:1px solid #333;"  ><strong><em>Sample Barcode</em></strong></td>';
            }
            $tbl .= ' </tr>';

            $sampleCounter = 1;

            foreach ($result as $sample) {
                //var_dump($sample);die;
                $collectionDate = '';
                if (isset($sample['sample_collection_date']) && $sample['sample_collection_date'] != '' && $sample['sample_collection_date'] != null && $sample['sample_collection_date'] != '0000-00-00 00:00:00') {
                    $cDate = explode(" ", (string) $sample['sample_collection_date']);
                    $collectionDate = DateUtility::humanReadableDateFormat($cDate[0]) . " " . $cDate[1];
                }
                $patientDOB = '';
                if (isset($sample['patient_dob']) && $sample['patient_dob'] != '' && $sample['patient_dob'] != null && $sample['patient_dob'] != '0000-00-00') {
                    $patientDOB = DateUtility::humanReadableDateFormat($sample['patient_dob']);
                }
                // $params = $pdf->serializeTCPDFtagParameters(array($sample['remote_sample_code'], 'C39', '', '', '', 9, 0.25, array('border' => false, 'align' => 'C', 'padding' => 1, 'fgcolor' => array(0, 0, 0), 'bgcolor' => array(255, 255, 255), 'text' => false, 'font' => 'helvetica', 'fontsize' => 10, 'stretchtext' => 2), 'N'));
                //$tbl.='<table cellspacing="0" cellpadding="3" style="width:100%">';
                $tbl .= '<tr style="border:1px solid #333;">';
                $tbl .= '<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;">' . $sampleCounter . '.</td>';
                $tbl .= '<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;">' . $sample['remote_sample_code'] . '</td>';
                // $tbl .= '<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;">' . ($sample['facility_district']) . '</td>';
                $tbl .= '<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;">' . ($sample['clinic_name']) . ', ' . $sample['facility_district'] . '</td>';
                if ($showPatientName == "yes") {
                    $tbl .= '<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;">' . ($sample['patient_fullname']) . '</td>';
                }
                $tbl .= '<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;">' . $sample['patient_id'] . '</td>';
                $tbl .= '<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;">' . $patientDOB . '</td>';
                $tbl .= '<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;">' . (str_replace("_", " ", (string) $sample['patient_gender'])) . '</td>';
                $tbl .= '<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;">' . $collectionDate . '</td>';
                // $tbl.='<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;">VIRAL</td>';
                $tbl .= '<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;"><img style="width:180px;height:25px;" src="' . $general->getBarcodeImageContent($sample['remote_sample_code']) . '"/></td>';
                $tbl .= '</tr>';
                //$tbl .='</table>';
                $sampleCounter++;
            }
            $tbl .= '</table>';
        }

        $tbl .= '<br><br><br><br><table cellspacing="0" style="width:100%;">';
        $tbl .= '<tr >';
        $tbl .= '<td align="left" style="vertical-align:middle;font-size:11px;width:33.33%;"><strong>Generated By : </strong><br>' . $_SESSION['userName'] . '</td>';
        $tbl .= '<td align="left" style="vertical-align:middle;font-size:11px;width:33.33%;"><strong>Verified By :  </strong></td>';
        $tbl .= '<td align="left" style="vertical-align:middle;font-size:11px;width:33.33%;"><strong>Received By : </strong><br>(at ' . $labname . ')</td>';
        $tbl .= '</tr>';
        $tbl .= '</table><br><br>';

        if(!empty($reasonHistory) && count($reasonHistory) > 0){
            $tbl .= 'Manifest Change History';
            $tbl .= '<br><br><table nobr="true" style="width:100%;" border="1" cellpadding="2"><tr nobr="true">';
            $tbl .= '<th>Reason for Changes</th>';
            $tbl .= '<th>Changed By </th>';
            $tbl .= '<th>Changed On</th>';
            $tbl .= '</tr>';
            foreach($reasonHistory as $change){
                $userResult = $usersService->findUserByUserId($change->changedBy);
                $userName = $userResult['user_name'];
                $tbl .= '<tr nobr="true">';
                $tbl .= '<td align="left" style="vertical-align:middle;font-size:11px;width:33.33%;">' . $change->reason . '</td>';
                $tbl .= '<td align="left" style="vertical-align:middle;font-size:11px;width:33.33%;">' . $userName . '</td>';
                $tbl .= '<td align="left" style="vertical-align:middle;font-size:11px;width:33.33%;">' . DateUtility::humanReadableDateFormat($change->date) . '</td>';
                $tbl .= '</tr>';
            }
        }
        $tbl .= '</table>';
        //$tbl.='<br/><br/><strong style="text-align:left;">Printed On:  </strong>'.date('d/m/Y H:i:s');
        $pdf->writeHTMLCell('', '', 11, $pdf->getY(), $tbl, 0, 1, 0, true, 'C');

        $filename = trim((string) $bResult[0]['package_code']) . '-' . date('Ymd') . '-' . MiscUtility::generateRandomString(6) . '-Manifest.pdf';
        $manifestsPath = MiscUtility::buildSafePath(TEMP_PATH, ["sample-manifests"]);
        $filename = MiscUtility::cleanFileName($filename);
        $pdf->Output($manifestsPath . DIRECTORY_SEPARATOR . $filename, "F");
        echo $filename;
    }
}
