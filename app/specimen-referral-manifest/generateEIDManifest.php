<?php

use Psr\Http\Message\ServerRequestInterface;
use const COUNTRY\SIERRA_LEONE;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\UsersService;
use App\Helpers\ManifestPdfHelper;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

$arr = $general->getGlobalConfig();

// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());
$_GET = _sanitizeInput($request->getQueryParams());

$id = base64_decode((string) $_POST['id']);
if (isset($_POST['frmSrc']) && trim((string) $_POST['frmSrc']) === 'pk2') {
    $id = $_POST['ids'];
}

if (trim((string) $id) !== '') {

    $sQuery = "SELECT remote_sample_code,pd.number_of_samples,fd.facility_name as clinic_name,fd.facility_district,child_name,vl.child_dob,vl.child_age,vl.mother_name,sample_collection_date,child_gender,child_id,pd.manifest_code, l.facility_name as lab_name, u_d.user_name as releaser_name,
                u_d.phone_number as phone,u_d.email as email,DATE_FORMAT(pd.request_created_datetime,'%d-%b-%Y') as created_date
                from specimen_manifests as pd Join form_eid as vl ON vl.sample_package_id=pd.manifest_id
                Join facility_details as fd ON fd.facility_id=vl.facility_id
                Join facility_details as l ON l.facility_id=vl.lab_id
                LEFT JOIN user_details as u_d ON u_d.user_id=pd.added_by
                where pd.manifest_id IN(?)";
    $result = $db->rawQuery($sQuery, [$id]);

    $labname = $result[0]['lab_name'] ?? "";
    $showPatientName = $arr['eid_show_participant_name_in_manifest'];

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
        $pdf = new ManifestPdfHelper(_translate('EID Sample Referral Manifest'), PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->initializeManifest($general->getGlobalConfig('logo'), $general->getGlobalConfig('header'), $labname);

        // Sierra Leone specific sections
        if ($arr['vl_form'] == SIERRA_LEONE) {
            $pdf->renderCountrySpecificSections($result[0], (int)($result[0]['number_of_samples'] ?? 0));
        }

        // Manifest code and barcode
        $tbl = $pdf->renderManifestCodeSection($result[0]['manifest_code'], $general->getBarcodeImageContent($result[0]['manifest_code']));

        if (!empty($result) && count($result) > 0) {
            $tbl .= '<table style="width:100%;border:1px solid #333;">';
            if ($showPatientName == "yes") {
                $tbl .= '<tr nobr="true">
                        <td align="center" style="font-size:11px;width:3%;border:1px solid #333;" ><strong><em>S. No.</em></strong></td>
                        <td align="center" style="font-size:11px;width:11%;border:1px solid #333;"  ><strong><em>SAMPLE ID</em></strong></td>
                        <td align="center" style="font-size:11px;width:10%;border:1px solid #333;"  ><strong><em>Health facility, District</em></strong></td>
                        <td align="center" style="font-size:11px;width:10%;border:1px solid #333;"  ><strong><em>Child Name</em></strong></td>
                       <td align="center" style="font-size:11px;width:10%;border:1px solid #333;"  ><strong><em>Child ID</em></strong></td>
                        <td align="center" style="font-size:11px;width:8%;border:1px solid #333;"  ><strong><em>Date of Birth</em></strong></td>
                        <td align="center" style="font-size:11px;width:7%;border:1px solid #333;"  ><strong><em>Child Sex</em></strong></td>
                        <td align="center" style="font-size:11px;width:7%;border:1px solid #333;"  ><strong><em>Mother Name</em></strong></td>
                        <td align="center" style="font-size:11px;width:7%;border:1px solid #333;"  ><strong><em>Sample Collection Date</em></strong></td>
                        <td align="center" style="font-size:11px;width:20%;border:1px solid #333;"  ><strong><em>Sample Barcode</em></strong></td>
                    </tr>';
            } else {
                $tbl .= '<tr nobr="true">
                        <td align="center" style="font-size:11px;width:3%;border:1px solid #333;" ><strong><em>S. No.</em></strong></td>
                        <td align="center" style="font-size:11px;width:11%;border:1px solid #333;"  ><strong><em>SAMPLE ID</em></strong></td>
                        <td align="center" style="font-size:11px;width:12%;border:1px solid #333;"  ><strong><em>Health facility, District</em></strong></td>
                       <td align="center" style="font-size:11px;width:12%;border:1px solid #333;"  ><strong><em>Child ID</em></strong></td>
                        <td align="center" style="font-size:11px;width:8%;border:1px solid #333;"  ><strong><em>Date of Birth</em></strong></td>
                        <td align="center" style="font-size:11px;width:7%;border:1px solid #333;"  ><strong><em>Child Sex</em></strong></td>
                        <td align="center" style="font-size:11px;width:10%;border:1px solid #333;"  ><strong><em>Mother Name</em></strong></td>
                        <td align="center" style="font-size:11px;width:10%;border:1px solid #333;"  ><strong><em>Sample Collection Date</em></strong></td>
                        <td align="center" style="font-size:11px;width:20%;border:1px solid #333;"  ><strong><em>Sample Barcode</em></strong></td>
                    </tr>';
            }

            $sampleCounter = 1;
            foreach ($result as $sample) {
                $collectionDate = '';
                if (isset($sample['sample_collection_date']) && $sample['sample_collection_date'] != '' && $sample['sample_collection_date'] != null && $sample['sample_collection_date'] != '0000-00-00 00:00:00') {
                    $cDate = explode(" ", (string) $sample['sample_collection_date']);
                    $collectionDate = DateUtility::humanReadableDateFormat($cDate[0]) . " " . $cDate[1];
                }
                $patientDOB = '';
                if (isset($sample['child_dob']) && $sample['child_dob'] != '' && $sample['child_dob'] != null && $sample['child_dob'] != '0000-00-00') {
                    $patientDOB = DateUtility::humanReadableDateFormat($sample['child_dob']);
                }

                $tbl .= '<tr style="border:1px solid #333;">';
                $tbl .= '<td align="center" style="vertical-align:middle;font-size:11px;border:1px solid #333;">' . $sampleCounter . '.</td>';
                $tbl .= '<td align="center" style="vertical-align:middle;font-size:11px;border:1px solid #333;">' . ($sample['remote_sample_code'] ?? '') . '</td>';
                $tbl .= '<td align="center" style="vertical-align:middle;font-size:11px;border:1px solid #333;">' . ($sample['clinic_name'] ?? '') . ', ' . ($sample['facility_district'] ?? '') . '</td>';
                if ($showPatientName == "yes") {
                    $tbl .= '<td align="center" style="vertical-align:middle;font-size:11px;border:1px solid #333;">' . ($sample['child_name'] ?? '') . '</td>';
                }
                $tbl .= '<td align="center" style="vertical-align:middle;font-size:11px;border:1px solid #333;">' . ($sample['child_id'] ?? '') . '</td>';
                $tbl .= '<td align="center" style="vertical-align:middle;font-size:11px;border:1px solid #333;">' . $patientDOB . '</td>';
                $tbl .= '<td align="center" style="vertical-align:middle;font-size:11px;border:1px solid #333;">' . str_replace("_", " ", (string) ($sample['child_gender'] ?? '')) . '</td>';
                $tbl .= '<td align="center" style="vertical-align:middle;font-size:11px;border:1px solid #333;">' . ($sample['mother_name'] ?? '') . '</td>';
                $tbl .= '<td align="center" style="vertical-align:middle;font-size:11px;border:1px solid #333;">' . $collectionDate . '</td>';
                $tbl .= '<td align="center" style="vertical-align:middle;font-size:11px;border:1px solid #333;"><img style="width:180px;height:25px;" src="' . $general->getBarcodeImageContent($sample['remote_sample_code']) . '"/></td>';
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
