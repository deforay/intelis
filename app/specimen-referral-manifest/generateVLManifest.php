<?php

use Psr\Http\Message\ServerRequestInterface;
use const COUNTRY\SIERRA_LEONE;
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
/** @var ServerRequestInterface $request */
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
        $newPrintData = ['printedBy' => $_SESSION['userId'], 'date' => DateUtility::getCurrentDateTime()];
        $oldPrintData[] = $newPrintData;
        $db->where('manifest_id', $id);
        $db->update('specimen_manifests', ['manifest_print_history' => json_encode($oldPrintData)]);

        $reasonHistory = json_decode((string) $bResult['manifest_change_history']);

        // Create and initialize PDF
        $pdf = new ManifestPdfHelper(_translate('Viral Load Sample Referral Manifest'), PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->initializeManifest($globalConfig['logo'], $globalConfig['header'], $labname);

        // Sierra Leone specific sections
        if ($globalConfig['vl_form'] == SIERRA_LEONE) {
            $pdf->renderCountrySpecificSections($result[0], (int)($result[0]['number_of_samples'] ?? 0));
        }

        // Manifest code and barcode
        $tbl = $pdf->renderManifestCodeSection($result[0]['manifest_code'], $general->getBarcodeImageContent($result[0]['manifest_code']));

        if (!empty($result)) {
            $tbl .= '<br><table nobr="true" style="width:100%;" border="1" cellpadding="2">';
            $tbl .= '<tr nobr="true">
                        <td style="font-size:11px;width:5%;"><strong>' . _translate('S. No.') . '</strong></td>
                        <td style="font-size:11px;width:12%;"><strong>' . _translate('SAMPLE ID') . '</strong></td>
                        <td style="font-size:11px;width:15%;"><strong>' . _translate('HEALTH FACILITY, DISTRICT') . '</strong></td>
                        <td style="font-size:11px;width:15%;"><strong>' . _translate('PATIENT') . '</strong></td>
                        <td style="font-size:11px;width:5%;"><strong>' . _translate('AGE') . '</strong></td>
                        <td style="font-size:11px;width:8%;"><strong>' . _translate('DATE OF BIRTH') . '</strong></td>
                        <td style="font-size:11px;width:8%;"><strong>' . _translate('SEX') . '</strong></td>
                        <td style="font-size:11px;width:8%;"><strong>' . _translate('SPECIMEN TYPE') . '</strong></td>
                        <td style="font-size:11px;width:8%;"><strong>' . _translate('COLLECTION DATE') . '</strong></td>
                        <td style="font-size:11px;width:20%;"><strong>' . _translate('SAMPLE BARCODE') . '</strong></td>
                    </tr>';
            $tbl .= '</table>';

            $sampleCounter = 1;
            foreach ($result as $sample) {
                $tbl .= '<table nobr="true" style="width:100%;" border="1" cellpadding="2">';
                $tbl .= '<tr nobr="true">';
                $tbl .= '<td style="font-size:11px;width:5%;">' . $sampleCounter . '.</td>';
                $tbl .= '<td style="font-size:11px;width:12%;">' . ($sample['remote_sample_code'] ?? '') . '</td>';
                $tbl .= '<td style="font-size:11px;width:15%;">' . ($sample['clinic_name'] ?? '') . ', ' . ($sample['facility_district'] ?? '') . '</td>';

                if (isset($showPatientName) && $showPatientName == "no") {
                    $tbl .= '<td style="font-size:11px;width:15%;">' . ($sample['patient_art_no'] ?? '') . '</td>';
                } else {
                    $patientName = trim(($sample['patient_first_name'] ?? '') . " " . ($sample['patient_middle_name'] ?? '') . " " . ($sample['patient_last_name'] ?? ''));
                    $tbl .= '<td style="font-size:11px;width:15%;">' . $patientName . '<br>' . ($sample['patient_art_no'] ?? '') . '</td>';
                }

                $tbl .= '<td style="font-size:11px;width:5%;">' . ($sample['patient_age_in_years'] ?? '') . '</td>';
                $tbl .= '<td style="font-size:11px;width:8%;">' . DateUtility::humanReadableDateFormat($sample['patient_dob'] ?? '') . '</td>';
                $tbl .= '<td style="font-size:11px;width:8%;">' . ucwords(str_replace("_", " ", (string) ($sample['patient_gender'] ?? ''))) . '</td>';
                $tbl .= '<td style="font-size:11px;width:8%;">' . ucwords((string) ($sample['sample_name'] ?? '')) . '</td>';
                $tbl .= '<td style="font-size:11px;width:8%;">' . DateUtility::humanReadableDateFormat($sample['sample_collection_date'] ?? '') . '</td>';
                $tbl .= '<td style="font-size:11px;width:20%;"><img style="width:180px;height:25px;" src="' . $general->getBarcodeImageContent($sample['remote_sample_code']) . '"/></td>';
                $tbl .= '</tr>';
                $tbl .= '</table>';
                $sampleCounter++;
            }
        }

        // Signature section
        $tbl .= $pdf->renderSignatureSection($_SESSION['userName'], $labname);

        // Change history
        if (!empty($reasonHistory)) {
            $tbl .= $pdf->renderChangeHistory((array)$reasonHistory, function ($userId) use ($usersService) {
                $userResult = $usersService->findUserByUserId($userId);
                return $userResult['user_name'] ?? '';
            });
        }

        $pdf->writeHTMLCell('', '', 11, $pdf->getY(), $tbl, 0, 1, 0, true, 'C');

        echo $pdf->outputManifest($bResult['manifest_code']);
    }
}
