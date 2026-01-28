<?php

use Psr\Http\Message\ServerRequestInterface;
use const COUNTRY\SIERRA_LEONE;
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
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$id = base64_decode((string) $_POST['id']);

if (isset($_POST['frmSrc']) && trim((string) $_POST['frmSrc']) === 'pk2') {
    $id = $_POST['ids'];
}

if (trim((string) $id) !== '') {

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
                    pd.manifest_code,
                    l.facility_name as lab_name,
                    u_d.user_name as releaser_name,
                    u_d.phone_number as phone,
                    u_d.email as email,
                    DATE_FORMAT(pd.request_created_datetime,'%d-%b-%Y') as created_date
                FROM specimen_manifests as pd
                JOIN form_covid19 as vl ON vl.sample_package_id=pd.manifest_id
                JOIN facility_details as fd ON fd.facility_id=vl.facility_id
                JOIN facility_details as l ON l.facility_id=vl.lab_id
                LEFT JOIN user_details as u_d ON u_d.user_id=pd.added_by
                WHERE pd.manifest_id IN(?)";
    $result = $db->rawQuery($sQuery, [$id]);


    $labname = $result[0]['lab_name'] ?? "";

    $arr = $general->getGlobalConfig();
    $showPatientName = $arr['covid19_show_participant_name_in_manifest'];
    $bQuery = "SELECT * FROM specimen_manifests as pd WHERE manifest_id IN(?)";
    $bResult = $db->rawQuery($bQuery, [$id]);
    if (!empty($bResult)) {

        $oldPrintData = json_decode((string) $bResult[0]['manifest_print_history']);

        $newPrintData = ['printedBy' => $_SESSION['userId'], 'date' => DateUtility::getCurrentDateTime()];
        $oldPrintData[] = $newPrintData;
        $db->where('manifest_id', $id);
        $db->update('specimen_manifests', ['manifest_print_history' => json_encode($oldPrintData)]);

        $reasonHistory = json_decode((string) $bResult[0]['manifest_change_history']);

        // Create and initialize PDF
        $pdf = new ManifestPdfHelper(_translate('Covid-19 Sample Referral Manifest'), PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->initializeManifest($arr['logo'], $arr['header'], $labname);

        // Sierra Leone specific sections
        if ($arr['vl_form'] == SIERRA_LEONE) {
            $pdf->renderCountrySpecificSections($result[0], (int)($result[0]['number_of_samples'] ?? 0));
        }

        // Manifest code and barcode
        $tbl = $pdf->renderManifestCodeSection($result[0]['manifest_code'], $general->getBarcodeImageContent($result[0]['manifest_code']));

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
                $collectionDate = '';
                if (isset($sample['sample_collection_date']) && $sample['sample_collection_date'] != '' && $sample['sample_collection_date'] != null && $sample['sample_collection_date'] != '0000-00-00 00:00:00') {
                    $cDate = explode(" ", (string) $sample['sample_collection_date']);
                    $collectionDate = DateUtility::humanReadableDateFormat($cDate[0]) . " " . $cDate[1];
                }
                $patientDOB = '';
                if (isset($sample['patient_dob']) && $sample['patient_dob'] != '' && $sample['patient_dob'] != null && $sample['patient_dob'] != '0000-00-00') {
                    $patientDOB = DateUtility::humanReadableDateFormat($sample['patient_dob']);
                }
                $tbl .= '<tr style="border:1px solid #333;">';
                $tbl .= '<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;">' . $sampleCounter . '.</td>';
                $tbl .= '<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;">' . $sample['remote_sample_code'] . '</td>';
                $tbl .= '<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;">' . ($sample['clinic_name']) . ', ' . $sample['facility_district'] . '</td>';
                if ($showPatientName == "yes") {
                    $tbl .= '<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;">' . ($sample['patient_fullname']) . '</td>';
                }
                $tbl .= '<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;">' . $sample['patient_id'] . '</td>';
                $tbl .= '<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;">' . $patientDOB . '</td>';
                $tbl .= '<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;">' . (str_replace("_", " ", (string) $sample['patient_gender'])) . '</td>';
                $tbl .= '<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;">' . $collectionDate . '</td>';
                $tbl .= '<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;"><img style="width:180px;height:25px;" src="' . $general->getBarcodeImageContent($sample['remote_sample_code']) . '"/></td>';
                $tbl .= '</tr>';
                $sampleCounter++;
            }
            $tbl .= '</table>';
        }

        // Signature section
        $tbl .= $pdf->renderSignatureSection($_SESSION['userName'], $labname);

        // Change history
        if (!empty($reasonHistory) && count($reasonHistory) > 0) {
            $tbl .= $pdf->renderChangeHistory((array)$reasonHistory, function ($userId) use ($usersService) {
                $userResult = $usersService->findUserByUserId($userId);
                return $userResult['user_name'] ?? '';
            });
        }

        $pdf->writeHTMLCell('', '', 11, $pdf->getY(), $tbl, 0, 1, 0, true, 'C');

        echo $pdf->outputManifest($bResult[0]['manifest_code']);
    }
}
