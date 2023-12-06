<?php

use App\Services\DatabaseService;
use App\Services\TbService;
use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Helpers\PdfConcatenateHelper;
use App\Registries\ContainerRegistry;
use App\Services\GeoLocationsService;

ini_set('memory_limit', -1);
set_time_limit(0);
ini_set('max_execution_time', 300000);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = $GLOBALS['request'];
$_POST = $request->getParsedBody();

$tableName1 = "activity_log";
$tableName2 = "form_tb";
/** @var DatabaseService $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var UsersService $users */
$usersService = ContainerRegistry::get(UsersService::class);

/** @var GeoLocationsService $geolocationService */
$geolocationService = ContainerRegistry::get(GeoLocationsService::class);

/** @var TbService $tbService */
$tbService = ContainerRegistry::get(TbService::class);

$formId = $general->getGlobalConfig('vl_form');

//set print time
$printedTime = date('Y-m-d H:i:s');
$expStr = explode(" ", $printedTime);
$printDate = DateUtility::humanReadableDateFormat($expStr[0]);
$printDateTime = $expStr[1];
//set query
$allQuery = $_SESSION['tbPrintQuery'];
if (isset($_POST['id']) && trim((string) $_POST['id']) != '') {

    $searchQuery = "SELECT tb.*,f.*,
				l.facility_name as labName,
				l.facility_emails as labEmail,
				l.address as labAddress,
				l.facility_mobile_numbers as labPhone,
				l.facility_state as labState,
				l.facility_district as labCounty,
				l.facility_logo as facilityLogo,
				l.report_format as reportFormat,
				rip.i_partner_name,
				rsrr.rejection_reason_name ,
				u_d.user_name as reviewedBy,
				a_u_d.user_name as approvedBy,
				r_u_d.user_name as requestedBy,
				rfs.funding_source_name,
				rst.sample_name,
				testres.test_reason_name as reasonForTesting,
                r_c_a.recommended_corrective_action_name

				FROM form_tb as tb
				LEFT JOIN facility_details as f ON tb.facility_id=f.facility_id
				LEFT JOIN facility_details as l ON l.facility_id=tb.lab_id
				LEFT JOIN user_details as u_d ON u_d.user_id=tb.result_reviewed_by
				LEFT JOIN user_details as a_u_d ON a_u_d.user_id=tb.result_approved_by
				LEFT JOIN user_details as r_u_d ON r_u_d.user_id=tb.request_created_by
				LEFT JOIN r_tb_test_reasons as testres ON testres.test_reason_id=tb.reason_for_tb_test
				LEFT JOIN r_tb_sample_rejection_reasons as rsrr ON rsrr.rejection_reason_id=tb.reason_for_sample_rejection
                LEFT JOIN r_recommended_corrective_actions as r_c_a ON r_c_a.recommended_corrective_action_id=tb.recommended_corrective_action
				LEFT JOIN r_implementation_partners as rip ON rip.i_partner_id=tb.implementing_partner
				LEFT JOIN r_funding_sources as rfs ON rfs.funding_source_id=tb.funding_source
				LEFT JOIN r_tb_sample_type as rst ON rst.sample_id=tb.specimen_type
				WHERE tb.tb_id IN(" . $_POST['id'] . ")";
} else {
    $searchQuery = $allQuery;
}

$requestResult = $db->query($searchQuery);
/* Test Results */

if (($_SESSION['instanceType'] == 'vluser') && empty($requestResult[0]['result_printed_on_lis_datetime'])) {
    $pData = array('result_printed_on_lis_datetime' => date('Y-m-d H:i:s'));
    $db->where('tb_id', $_POST['id']);
    $id = $db->update('form_tb', $pData);
} elseif (($_SESSION['instanceType'] == 'remoteuser') && empty($requestResult[0]['result_printed_on_sts_datetime'])) {
    $pData = array('result_printed_on_sts_datetime' => date('Y-m-d H:i:s'));
    $db->where('tb_id', $_POST['id']);
    $id = $db->update('form_tb', $pData);
}

if (isset($_POST['type']) && $_POST['type'] == "qr") {
    try {
        $general->trackQRPageViews('tb', $requestResult[0]['tb_id'], $requestResult[0]['sample_code']);
    } catch (Exception $exc) {
        error_log($exc->getMessage());
        error_log($exc->getTraceAsString());
    }
}

$_SESSION['nbPages'] = sizeof($requestResult);
$_SESSION['aliasPage'] = 1;
//print_r($requestResult);die;
//header and footer
class MYPDF extends TCPDF
{
    public string $logo = '';
    public string $text = '';
    public string $lab = '';
    public string $htitle = '';
    public string $labFacilityId = '';
    public string $formId = '';
    public array $facilityInfo = [];


    //Page header
    public function setHeading($logo, $text, $lab, $title = null, $labFacilityId = null, $formId = null, $facilityInfo = [])
    {
        $this->logo = $logo;
        $this->text = $text;
        $this->lab = $lab;
        $this->htitle = $title;
        $this->labFacilityId = $labFacilityId;
        $this->formId = $formId;
        $this->facilityInfo = $facilityInfo;
    }
    public function imageExists($filePath): bool
    {
        return (!empty($filePath) && file_exists($filePath) && !is_dir($filePath) && filesize($filePath) > 0 && false !== getimagesize($filePath));
    }
    //Page header
    public function Header()
    {
        if ($this->htitle != '') {
            if (trim($this->logo) != '') {
                if (file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo)) {
                    $imageFilePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo;
                    if ($this->formId == 3) {
                        $this->Image($imageFilePath, 10, 5, 25, '', '', '', 'T');
                    } else {
                        $this->Image($imageFilePath, 95, 5, 15, '', '', '', 'T');
                    }
                }
            }
            if ($this->formId == 3) {
                $this->SetFont('helvetica', 'B', 16);
                $this->writeHTMLCell(0, 0, 10, 03, $this->text, 0, 0, 0, true, 'C');
                if (trim($this->lab) != '') {
                    $this->SetFont('helvetica', '', 10);
                    $this->writeHTMLCell(0, 0, 10, 10, strtoupper($this->lab), 0, 0, 0, true, 'C');
                }
                $this->SetFont('helvetica', 'b', 10);
                $this->writeHTMLCell(0, 0, 10, 18, 'Département de Virologie', 0, 0, 0, true, 'C');
                $this->SetFont('helvetica', 'u', 10);
                $this->writeHTMLCell(0, 0, 10, 25, 'Laboratoire National de Reference pour la Grippe et les Virus Respiratoires', 0, 0, 0, true, 'C');
                $this->SetFont('helvetica', 'b', 12);
                $this->writeHTMLCell(0, 0, 10, 33, 'RESULTATS DE LABORATOIRE DES ECHANTIONS RESPIRATOIRES', 0, 0, 0, true, 'C');
                $this->SetFont('helvetica', 'u', 10);
                $this->writeHTMLCell(0, 0, 10, 40, 'TESTES AU COVID-19 PAR RT-PCR en temps réel N°', 0, 0, 0, true, 'C');
                $this->writeHTMLCell(0, 0, 15, 48, '<hr>', 0, 0, 0, true, 'C');
            } else {
                $this->SetFont('helvetica', 'B', 16);
                $this->writeHTMLCell(0, 0, 10, 18, $this->text, 0, 0, 0, true, 'C');
                if (trim($this->lab) != '') {
                    $this->SetFont('helvetica', '', 10);
                    $this->writeHTMLCell(0, 0, 10, 25, strtoupper($this->lab), 0, 0, 0, true, 'C');
                }
                $this->SetFont('helvetica', '', 12);
                $this->writeHTMLCell(0, 0, 10, 30, 'COVID-19 TEST - PATIENT REPORT', 0, 0, 0, true, 'C');
                $this->writeHTMLCell(0, 0, 15, 38, '<hr>', 0, 0, 0, true, 'C');
            }
        } else {
            if (trim($this->logo) != '') {
                if (file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $this->labFacilityId . DIRECTORY_SEPARATOR . $this->logo)) {
                    $imageFilePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'facility-logo' . DIRECTORY_SEPARATOR . $this->labFacilityId . DIRECTORY_SEPARATOR . $this->logo;
                    $this->Image($imageFilePath, 16, 13, 15, '', '', '', 'T');
                } else if (file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo)) {
                    $imageFilePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo;
                    $this->Image($imageFilePath, 20, 13, 15, '', '', '', 'T');
                }
            }
            if (file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . 'drc-logo.png')) {
                $imageFilePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . 'drc-logo.png';
                $this->Image($imageFilePath, 180, 13, 15, '', '', '', 'T');
            }

            // $this->SetFont('helvetica', 'B', 7);
            // $this->writeHTMLCell(30,0,16,28,$this->text, 0, 0, 0, true, 'A', true);(this two lines comment out for drc)
            $this->SetFont('helvetica', '', 14);
            $this->writeHTMLCell(0, 0, 10, 9, 'MINISTERE DE LA SANTE PUBLIQUE', 0, 0, 0, true, 'C');
            if ($this->text != '') {
                $this->SetFont('helvetica', '', 12);
                //        $this->writeHTMLCell(0,0,10,16,'PROGRAMME NATIONAL DE LUTTE CONTRE LE SIDA ET IST', 0, 0, 0, true, 'C', true);
                $this->writeHTMLCell(0, 0, 10, 16, strtoupper($this->text), 0, 0, 0, true, 'C');
                $thirdHeading = '23';
                $fourthHeading = '28';
                $hrLine = '36';
                $marginTop = '14';
            } else {
                $thirdHeading = '17';
                $fourthHeading = '23';
                $hrLine = '30';
                $marginTop = '9';
            }
            if (trim($this->lab) != '') {
                $this->SetFont('helvetica', '', 9);
                $this->writeHTMLCell(0, 0, 10, $thirdHeading, strtoupper($this->lab), 0, 0, 0, true, 'C');
            }
            $this->SetFont('helvetica', '', 12);
            $this->writeHTMLCell(0, 0, 10, $fourthHeading, 'RESULTATS CHARGE VIRALE', 0, 0, 0, true, 'C');
            $this->writeHTMLCell(0, 0, 15, $hrLine, '<hr>', 0, 0, 0, true, 'C');
        }
    }

    // Page footer
    public function Footer()
    {
        // Position at 15 mm from bottom
        $this->SetY(-15);
        // Set font
        $this->SetFont('helvetica', '', 8);
        // Page number
        $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . ' of ' . $this->getAliasNbPages(), 0, false, 'C', 0);
    }
}


$fileArray = array(
    1 => 'pdf/result-pdf-ssudan.php',
    2 => 'pdf/result-pdf-sierraleone.php',
    3 => 'pdf/result-pdf-drc.php',
    4 => 'pdf/result-pdf-cameroon.php',
    5 => 'pdf/result-pdf-png.php',
    6 => 'pdf/result-pdf-who.php',
    7 => 'pdf/result-pdf-rwanda.php',
);


$resultFilename = '';
if (!empty($requestResult)) {
    $_SESSION['rVal'] = $general->generateRandomString(6);
    $pathFront = TEMP_PATH . DIRECTORY_SEPARATOR .  $_SESSION['rVal'];
    MiscUtility::makeDirectory($pathFront);
    $pages = [];
    $page = 1;
    foreach ($requestResult as $result) {
        //set print time
        if (isset($result['result_printed_datetime']) && $result['result_printed_datetime'] != "") {
            $printedTime = date('Y-m-d H:i:s', strtotime((string) $result['result_printed_datetime']));
        } else {
            $printedTime = DateUtility::getCurrentDateTime();
        }
        $expStr = explode(" ", $printedTime);
        $printDate = DateUtility::humanReadableDateFormat($expStr[0]);
        $printDateTime = $expStr[1];

        $tbTestQuery = "SELECT * from tb_tests where tb_id= " . $result['tb_id'] . " ORDER BY tb_test_id ASC";
        $tbTestInfo = $db->rawQuery($tbTestQuery);
        // Lab Details
        $facilityQuery = "SELECT * from form_tb as c19 INNER JOIN facility_details as fd ON c19.facility_id=fd.facility_id where tb_id= " . $result['tb_id'] . " GROUP BY fd.facility_id LIMIT 1";
        $facilityInfo = $db->rawQueryOne($facilityQuery);

        $patientFname = ($general->crypto('doNothing', $result['patient_name'], $result['patient_id']));
        $patientLname = ($general->crypto('doNothing', $result['patient_surname'], $result['patient_id']));

        if (!empty($result['is_encrypted']) && $result['is_encrypted'] == 'yes') {
            $key = (string) $general->getGlobalConfig('key');
            $result['patient_id'] = $general->crypto('decrypt', $result['patient_id'], $key);
            $patientFname = $general->crypto('decrypt', $patientFname, $key);
            $patientLname = $general->crypto('decrypt', $patientLname, $key);
        }

        $signQuery = "SELECT * from lab_report_signatories where lab_id=? AND test_types like '%tb%' AND signatory_status like 'active' ORDER BY display_order ASC";
        $signResults = $db->rawQuery($signQuery, array($result['lab_id']));
        $currentDateTime = DateUtility::getCurrentDateTime();
        $_SESSION['aliasPage'] = $page;

        if (!isset($result['labName'])) {
            $result['labName'] = '';
        }
        $draftTextShow = false;
        //Set watermark text


        $selectedReportFormats = [];
        if (isset($result['reportFormat']) && $result['reportFormat'] != "") {
            $selectedReportFormats = json_decode((string) $result['reportFormat'], true);
        }

        if (!empty($selectedReportFormats) && !empty($selectedReportFormats['tb'])) {
            require($selectedReportFormats['tb']);
        } else {
            require($fileArray[$formId]);
        }
    }
    if (!empty($pages)) {
        $resultPdf = new PdfConcatenateHelper();
        $resultPdf->setFiles($pages);
        $resultPdf->setPrintHeader(false);
        $resultPdf->setPrintFooter(false);
        $resultPdf->concat();
        $resultFilename = 'VLSM-TB-Test-result-' . date('d-M-Y-H-i-s') . "-" . $general->generateRandomString(6) . '.pdf';
        $resultPdf->Output(TEMP_PATH . DIRECTORY_SEPARATOR . $resultFilename, "F");
        MiscUtility::removeDirectory($pathFront);
        unset($_SESSION['rVal']);
    }
}
echo base64_encode(TEMP_PATH . DIRECTORY_SEPARATOR . $resultFilename);
