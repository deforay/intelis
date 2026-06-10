<?php

use Psr\Http\Message\ServerRequestInterface;
use App\Services\UsersService;
use App\Utilities\DateUtility;
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
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$id = base64_decode((string) $_POST['id']);
if (isset($_POST['frmSrc']) && trim((string) $_POST['frmSrc']) === 'pk2') {
    $id = $_POST['ids'];
}
if (trim((string) $id) !== '') {

    $sQuery = "SELECT vl.tb_id, COALESCE(NULLIF(vl.remote_sample_code, ''), vl.sample_code) as remote_sample_code, fd.facility_name as clinic_name, fd.facility_district,
                TRIM(CONCAT(COALESCE(vl.patient_name, ''), ' ', COALESCE(vl.patient_surname, ''))) as `patient_fullname`,
                patient_dob, patient_age, sample_collection_date, patient_gender, patient_id, 
                pd.manifest_code, l.facility_name as lab_name 
                FROM specimen_manifests as pd 
                JOIN form_tb as vl ON vl.referral_manifest_code = pd.manifest_code 
                JOIN facility_details as fd ON fd.facility_id = vl.facility_id 
                JOIN facility_details as l ON l.facility_id = vl.lab_id 
                WHERE pd.manifest_code IN('$id')";
    $result = $db->query($sQuery);

    $labname = $result[0]['lab_name'] ?? "";

    $showPatientName = $general->getGlobalConfig('tb_show_participant_name_in_manifest');
    $bQuery = "SELECT * FROM specimen_manifests as pd WHERE manifest_code IN('$id')";

    $bResult = $db->query($bQuery);
    if (!empty($bResult)) {

        $oldPrintData = json_decode((string) $bResult[0]['manifest_print_history']);
        $newPrintData = ['printedBy' => $_SESSION['userId'], 'date' => DateUtility::getCurrentDateTime()];
        $oldPrintData[] = $newPrintData;
        $db->where('manifest_code', $id);
        $db->update('specimen_manifests', ['manifest_print_history' => json_encode($oldPrintData)]);

        $reasonHistory = json_decode((string) $bResult[0]['manifest_change_history']);

        // Tests done on this sample so far. TB allows multiple tests per sample,
        // so a sample can have several rows in tb_tests.
        $sampleTests = [];
        foreach ($result as $sample) {
            $sampleTestQuery = "SELECT ss.sample_name, l.facility_name as testing_lab,
                                tt.test_type, tt.test_result, tt.sample_tested_datetime, tt.comments
                            FROM tb_tests as tt
                            LEFT JOIN facility_details as l ON l.facility_id = tt.lab_id
                            LEFT JOIN r_tb_sample_type as ss ON ss.sample_id = tt.specimen_type
                            WHERE tt.tb_id = ?
                            ORDER BY tt.sample_tested_datetime ASC, tt.tb_test_id ASC";
            $rows = $db->rawQuery($sampleTestQuery, [$sample['tb_id']]);
            if (!empty($rows)) {
                $sampleTests[$sample['tb_id']] = $rows;
            }
        }
        // Create new PDF document
        $pdf = new ManifestPdfHelper(_translate('TB Sample Referral Manifest'), PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        $pdf->setHeading($general->getGlobalConfig('logo'), $general->getGlobalConfig('header'), $labname);

        // Set document information
        $pdf->SetCreator('STS');
        $pdf->SetAuthor('STS');
        $pdf->SetTitle('Specimen Referral Manifest');
        $pdf->SetSubject('Specimen Referral Manifest');
        $pdf->SetKeywords('Specimen Referral Manifest');

        // Set default header data
        $pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, PDF_HEADER_TITLE, PDF_HEADER_STRING);

        // Set header and footer fonts
        $pdf->setHeaderFont([PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN]);
        $pdf->setFooterFont([PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA]);

        // Set default monospaced font
        $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

        // Set margins
        $pdf->SetMargins(PDF_MARGIN_LEFT, 36, PDF_MARGIN_RIGHT);
        $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
        $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

        // Set auto page breaks
        $pdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);

        // Set image scale factor
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

        // Set font
        $pdf->SetFont('helvetica', '', 10);
        $pdf->setPageOrientation('L');

        // Add a page
        $pdf->AddPage();

        $tbl = '<span style="font-size:1.7em;"> ' . $result[0]['manifest_code'];
        $tbl .= '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img style="width:200px;height:30px;" src="' . $general->getBarcodeImageContent($result[0]['manifest_code']) . '">';
        $tbl .= '</span><br><br>';

        if (!empty($result)) {
            $sampleCounter = 1;

            $tbl .= '<table style="width:100%;border:1px solid #333;" cellpadding="4">';
            // Main sample information table
            $tbl .= '<tr nobr="true" style="background-color:#e8e8e8;">';
            $tbl .= '<td style="font-size:10px;width:4%;border:1px solid #333;"><strong>S.No</strong></td>';
            $tbl .= '<td style="font-size:10px;width:12%;border:1px solid #333;"><strong>Sample ID</strong></td>';
            $tbl .= '<td style="font-size:10px;width:18%;border:1px solid #333;"><strong>Health Facility, District</strong></td>';

            if ($showPatientName == "yes") {
                $tbl .= '<td style="font-size:10px;width:15%;border:1px solid #333;"><strong>Patient Name, ID</strong></td>';
            } else {
                $tbl .= '<td style="font-size:10px;width:15%;border:1px solid #333;"><strong>Patient ID</strong></td>';
            }

            $tbl .= '<td style="font-size:10px;width:15%;border:1px solid #333;"><strong>DOB, Gender</strong></td>';
            $tbl .= '<td style="font-size:10px;width:18%;border:1px solid #333;"><strong>Sample Collection Date</strong></td>';
            $tbl .= '<td style="font-size:10px;width:18%;border:1px solid #333;"><strong>Sample Barcode</strong></td>';
            $tbl .= '</tr>';
            foreach ($result as $sample) {
                $collectionDate = '';
                if (isset($sample['sample_collection_date']) && $sample['sample_collection_date'] != '' && $sample['sample_collection_date'] != null && $sample['sample_collection_date'] != '0000-00-00 00:00:00') {
                    $cDate = explode(" ", (string) $sample['sample_collection_date']);
                    $collectionDate = DateUtility::humanReadableDateFormat($cDate[0]) . " " . $cDate[1];
                }

                $patientDOB = '';
                if (isset($sample['patient_dob']) && $sample['patient_dob'] != '' && $sample['patient_dob'] != null && $sample['patient_dob'] != '0000-00-00') {
                    $patientDOB = DateUtility::humanReadableDateFormat($sample['patient_dob']);
                }

                // Main sample row
                $tbl .= '<tr nobr="true">';
                $tbl .= '<td align="center" style="font-size:10px;border:1px solid #333;">' . $sampleCounter . '</td>';
                $tbl .= '<td align="center" style="font-size:10px;border:1px solid #333;">' . $sample['remote_sample_code'] . '</td>';
                $tbl .= '<td style="font-size:10px;border:1px solid #333;">' . ucwords((string) $sample['clinic_name']) . ', ' . $sample['facility_district'] . '</td>';

                if ($showPatientName == "yes") {
                    $tbl .= '<td style="font-size:10px;border:1px solid #333;">' . $sample['patient_fullname'] . '<br>' . $sample['patient_id'] . '</td>';
                } else {
                    $tbl .= '<td style="font-size:10px;border:1px solid #333;">' . $sample['patient_id'] . '</td>';
                }

                $tbl .= '<td style="font-size:10px;border:1px solid #333;">' . $patientDOB . '<br>' . ucwords(str_replace("_", " ", (string) $sample['patient_gender'])) . '</td>';
                $tbl .= '<td align="center" style="font-size:10px;border:1px solid #333;">' . $collectionDate . '</td>';
                $tbl .= '<td align="center" style="font-size:10px;border:1px solid #333;"><img style="width:140px;height:22px;" src="' . $general->getBarcodeImageContent($sample['remote_sample_code']) . '"/></td>';
                $tbl .= '</tr>';

                // Tests done on this sample, shown as a simple list in one full-width
                // cell directly under the sample row it belongs to.
                if (!empty($sampleTests[$sample['tb_id']])) {
                    $list = '<strong style="font-size:9px;">Tests Done</strong>';

                    foreach ($sampleTests[$sample['tb_id']] as $key => $testRow) {
                        $testedDate = '';
                        if (!empty($testRow['sample_tested_datetime'])) {
                            $tDate = explode(" ", (string) $testRow['sample_tested_datetime']);
                            $testedDate = DateUtility::humanReadableDateFormat($tDate[0]);
                        }

                        $tbTitle = (string) ($testRow['test_type'] ?? '');
                        $line = ($key + 1) . '. <strong>' . ($tbTitle !== '' ? $tbTitle : 'Test') . '</strong> &nbsp; Sample Type: ' . ($testRow['sample_name'] ?? '-') . ' &nbsp; Lab: ' . ($testRow['testing_lab'] ?? '-') . ' &nbsp; Result: ' . (($testRow['test_result'] ?? '') !== '' ? $testRow['test_result'] : '-') . ' &nbsp; Tested: ' . ($testedDate !== '' ? $testedDate : '-');
                        if (!empty($testRow['comments'])) {
                            $line .= ' &nbsp; Comments: ' . $testRow['comments'];
                        }
                        $list .= '<br><span style="font-size:9px;">' . $line . '</span>';
                    }

                    $tbl .= '<tr nobr="true">';
                    $tbl .= '<td colspan="7" style="font-size:9px;border:1px solid #333;padding-left:18px;background-color:#f7f9fb;">' . $list . '</td>';
                    $tbl .= '</tr>';
                }

                // Gap between samples. Replaces a stray <br><br> that TCPDF rendered
                // as an oversized empty band inside the table.
                $tbl .= '<tr nobr="true"><td colspan="7" style="font-size:8px;"> </td></tr>';
                $sampleCounter++;
            }
            $tbl .= '</table>';
        }

        $tbl .= '<br><table cellspacing="0" style="width:100%;">';
        $tbl .= '<tr>';
        $tbl .= '<td align="left" style="vertical-align:middle;font-size:11px;width:33.33%;"><strong>Generated By : </strong><br>' . $_SESSION['userName'] . '</td>';
        $tbl .= '<td align="left" style="vertical-align:middle;font-size:11px;width:33.33%;"><strong>Verified By :  </strong></td>';
        $tbl .= '<td align="left" style="vertical-align:middle;font-size:11px;width:33.33%;"><strong>Received By : </strong><br>(at ' . $labname . ')</td>';
        $tbl .= '</tr>';
        $tbl .= '</table><br><br>';

        if (!empty($reasonHistory) && count($reasonHistory) > 0) {
            $tbl .= '<strong>Manifest Change History</strong>';
            $tbl .= '<br><br><table nobr="true" style="width:100%;" border="1" cellpadding="2"><tr nobr="true">';
            $tbl .= '<th>Reason for Changes</th>';
            $tbl .= '<th>Changed By </th>';
            $tbl .= '<th>Changed On</th>';
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

        $pdf->writeHTMLCell('', '', 11, $pdf->getY(), $tbl, 0, 1, 0, true, 'C');
        echo $pdf->outputManifest($bResult[0]['manifest_code']);
    }
}
