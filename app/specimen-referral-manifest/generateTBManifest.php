<?php

use Psr\Http\Message\ServerRequestInterface;
use App\Services\UsersService;
use App\Utilities\DateUtility;
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

    $sQuery = "SELECT remote_sample_code,fd.facility_name as clinic_name,fd.facility_district,TRIM(CONCAT(COALESCE(vl.patient_name, ''), ' ', COALESCE(vl.patient_surname, ''))) as `patient_fullname`,patient_dob,patient_age,sample_collection_date,patient_gender,patient_id,pd.manifest_code, l.facility_name as lab_name from specimen_manifests as pd Join form_tb as vl ON vl.sample_package_id=pd.manifest_id Join facility_details as fd ON fd.facility_id=vl.facility_id Join facility_details as l ON l.facility_id=vl.lab_id where pd.manifest_id IN($id)";
    $result = $db->query($sQuery);


    $labname = $result[0]['lab_name'] ?? "";

    $showPatientName = $general->getGlobalConfig('tb_show_participant_name_in_manifest');
    $bQuery = "SELECT * from specimen_manifests as pd where manifest_id IN($id)";

    $bResult = $db->query($bQuery);
    if (!empty($bResult)) {

        $oldPrintData = json_decode((string) $bResult[0]['manifest_print_history']);

        $newPrintData = ['printedBy' => $_SESSION['userId'], 'date' => DateUtility::getCurrentDateTime()];
        $oldPrintData[] = $newPrintData;
        $db->where('manifest_id', $id);
        $db->update('specimen_manifests', ['manifest_print_history' => json_encode($oldPrintData)]);

        $reasonHistory = json_decode((string) $bResult[0]['manifest_change_history']);

        // Create and initialize PDF
        $pdf = new ManifestPdfHelper(_translate('TB Sample Referral Manifest'), PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->initializeManifest($general->getGlobalConfig('logo'), $general->getGlobalConfig('header'), $labname);

        // Manifest code and barcode
        $tbl = $pdf->renderManifestCodeSection($result[0]['manifest_code'], $general->getBarcodeImageContent($result[0]['manifest_code']));

        if (!empty($result)) {
            $tbl .= '<table style="width:100%;border:1px solid #333;">

                    <tr nobr="true">';
            if ($showPatientName == "yes") {
                $tbl .= '<td align="center" style="font-size:11px;width:3%;border:1px solid #333;" ><strong><em>S. No.</em></strong></td>
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
                $tbl .= '<td align="center" style="font-size:11px;width:3%;border:1px solid #333;" ><strong><em>S. No.</em></strong></td>
                            <td align="center" style="font-size:11px;width:12%;border:1px solid #333;"  ><strong><em>SAMPLE ID</em></strong></td>
                            <td align="center" style="font-size:11px;width:14%;border:1px solid #333;"  ><strong><em>Health facility, District</em></strong></td>
                            <td align="center" style="font-size:11px;width:10%;border:1px solid #333;"  ><strong><em>Patient ID</em></strong></td>
                            <td align="center" style="font-size:11px;width:11%;border:1px solid #333;"  ><strong><em>Date of Birth</em></strong></td>
                            <td align="center" style="font-size:11px;width:7%;border:1px solid #333;"  ><strong><em>Patient Sex</em></strong></td>
                            <td align="center" style="font-size:11px;width:12%;border:1px solid #333;"  ><strong><em>Sample Collection Date</em></strong></td>
                            <!-- <td align="center" style="font-size:11px;width:10%;border:1px solid #333;"  ><strong><em>Test Requested</em></strong></td> -->
                            <td align="center" style="font-size:11px;width:25%;border:1px solid #333;"  ><strong><em>Sample Barcode</em></strong></td>';
            }
            $tbl .= '</tr>';

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
                $tbl .= '<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;">' . ucwords((string) $sample['clinic_name']) . ', ' . $sample['facility_district'] . '</td>';
                if ($showPatientName == "yes") {
                    $tbl .= '<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;">' . ($sample['patient_fullname']) . '</td>';
                }
                $tbl .= '<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;">' . $sample['patient_id'] . '</td>';
                $tbl .= '<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;">' . $patientDOB . '</td>';
                $tbl .= '<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;">' . ucwords(str_replace("_", " ", (string) $sample['patient_gender'])) . '</td>';
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
