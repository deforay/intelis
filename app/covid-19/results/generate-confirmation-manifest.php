<?php


use Psr\Http\Message\ServerRequestInterface;
use setasign\Fpdi\Tcpdf\Fpdi;
use App\Utilities\DateUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());
$id = (isset($_GET['id'])) ? base64_decode((string) $_GET['id']) : null;

$globalConfig = $general->getGlobalConfig();

$barcodeFormat = $globalConfig['barcode_format'] ?? 'C39';

class Covid19ConfirmationManifestPDF extends Fpdi
{
    public ?string $logo = null;
    public ?string $text = null;
    public ?string $labname = null;

    public function setHeading(?string $logo, ?string $text, ?string $labname): void
    {
        $this->logo = $logo;
        $this->text = $text;
        $this->labname = $labname;
    }
    //Page header
    #[Override]
    public function Header(): void
    {

        if (trim((string) $this->logo) != "" && file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo)) {
            $imageFilePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo;
            $this->Image($imageFilePath, 15, 10, 15, '', '', '', 'T');
        }
        $this->SetFont('helvetica', '', 7);
        $this->writeHTMLCell(30, 0, 10, 26, $this->text, 0, 0, 0, true, 'A');
        $this->SetFont('helvetica', '', 13);
        $this->writeHTMLCell(0, 0, 0, 10, 'Covid-19 Positive Confirmation Manifest ', 0, 0, 0, true, 'C');
        $this->SetFont('helvetica', '', 10);
        $this->writeHTMLCell(0, 0, 0, 20, $this->labname, 0, 0, 0, true, 'C');

        if (trim((string) $this->logo) != "" && file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo)) {
            $imageFilePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo;
            $this->Image($imageFilePath, 262, 10, 15, '', '', '', 'T');
        }
        $this->SetFont('helvetica', '', 7);
        $this->writeHTMLCell(30, 0, 255, 26, $this->text, 0, 0, 0, true, 'A');
        $html = '<hr/>';
        $this->writeHTMLCell(0, 0, 10, 32, $html, 0, 0, 0, true, 'J');
    }

    // Page footer
    #[Override]
    public function Footer(): void
    {
        // Position at 15 mm from bottom
        $this->SetY(-15);
        // Set font
        $this->SetFont('helvetica', '', 8);
        // Page number
        $this->Cell(0, 10, 'Confirmation Manifest Generated On : ' . date('d/m/Y H:i:s') . ' | Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C', 0);
    }
}


if (trim((string) $id) !== '') {

    $sQuery = "SELECT sample_code,
                    remote_sample_code,
                    fd.facility_name as clinic_name,
                    fd.facility_district,
                    CONCAT(COALESCE(patient_name,''), COALESCE(patient_surname,'')) as `patient_fullname`,
                    patient_dob,patient_age,
                    sample_collection_date,
                    patient_gender,patient_id,
                    cpcm.manifest_code,
                    l.facility_name as lab_name
                FROM covid19_positive_confirmation_manifest as cpcm
                JOIN form_covid19 as vl ON vl.positive_test_manifest_id=cpcm.manifest_id
                JOIN facility_details as fd ON fd.facility_id=vl.facility_id
                JOIN facility_details as l ON l.facility_id=vl.lab_id
                WHERE cpcm.manifest_code LIKE '%" . $db->escape($id) . "%'";
    $result = $db->query($sQuery);

    $labname = $result[0]['lab_name'] ?? "";

    $arr = $general->getGlobalConfig();

    $bQuery = "SELECT * FROM covid19_positive_confirmation_manifest AS cpcm
                WHERE manifest_code LIKE '%" . $db->escape($id) . "%'";
    $bResult = $db->query($bQuery);
    if (!empty($bResult)) {


        // create new PDF document
        $pdf = new Covid19ConfirmationManifestPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        $pdf->setHeading($general->getGlobalConfig('logo'), $general->getGlobalConfig('header'), $labname);

        // set document information
        $pdf->SetCreator('STS');
        $pdf->SetAuthor('STS');
        $pdf->SetTitle('Positive Confirmation Manifest');
        $pdf->SetSubject('Positive Confirmation Manifest');
        $pdf->SetKeywords('Positive Confirmation Manifest');

        // set default header data
        $pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, PDF_HEADER_TITLE, PDF_HEADER_STRING);

        // set header and footer fonts
        $pdf->setHeaderFont([PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN]);
        $pdf->setFooterFont([PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA]);

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

        // set some language-dependent strings (optional)
        if (@file_exists(__DIR__ . '/lang/eng.php')) {
            require_once(__DIR__ . '/lang/eng.php');
            $pdf->setLanguageArray($l);
        }

        // set font
        $pdf->SetFont('helvetica', '', 10);
        $pdf->setPageOrientation('L');
        // add a page
        $pdf->AddPage();
        $tbl = '<span style="font-size:1.7em;"> ' . $result[0]['manifest_code'] . ' <img style="width:200px;height:30px;" src="' . $general->getBarcodeImageContent($sampleResult[0]['sample_code'], $barcodeFormat) . '"> </span>';
        $tbl .= '<br>';
        $tbl .= '<table style="width:100%;border:1px solid #333;">
                    <tr nobr="true">
                        <td align="center" style="font-size:11px;width:3%;border:1px solid #333;" ><strong><em>S. No.</em></strong></td>
                        <td align="center" style="font-size:11px;width:12%;border:1px solid #333;"  ><strong><em>SAMPLE ID</em></strong></td>
                        <td align="center" style="font-size:11px;width:14%;border:1px solid #333;"  ><strong><em>Health facility, District</em></strong></td>
                        <td align="center" style="font-size:11px;width:11%;border:1px solid #333;"  ><strong><em>Patient Name</em></strong></td>
                        <td align="center" style="font-size:11px;width:10%;border:1px solid #333;"  ><strong><em>Patient ID</em></strong></td>
                        <td align="center" style="font-size:11px;width:8%;border:1px solid #333;"  ><strong><em>Date of Birth</em></strong></td>
                        <td align="center" style="font-size:11px;width:7%;border:1px solid #333;"  ><strong><em>Patient Sex</em></strong></td>
                        <td align="center" style="font-size:11px;width:10%;border:1px solid #333;"  ><strong><em>Sample Collection Date</em></strong></td>
                        <!-- <td align="center" style="font-size:11px;width:7%;border:1px solid #333;"  ><strong><em>Test Requested</em></strong></td> -->
                        <td align="center" style="font-size:11px;width:22%;border:1px solid #333;"  ><strong><em>Sample Barcode</em></strong></td>
                    </tr>';

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

            $tbl .= '<tr style="border:1px solid #333;">';
            $tbl .= '<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;">' . $sampleCounter . '.</td>';
            $tbl .= '<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;">' . $sample['sample_code'] . '</td>';
            $tbl .= '<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;">' . ($sample['clinic_name']) . ', ' . $sample['facility_district'] . '</td>';
            $tbl .= '<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;">' . ($sample['patient_fullname']) . '</td>';
            $tbl .= '<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;">' . $sample['patient_id'] . '</td>';
            $tbl .= '<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;">' . $patientDOB . '</td>';
            $tbl .= '<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;">' . (str_replace("_", " ", (string) $sample['patient_gender'])) . '</td>';
            $tbl .= '<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;">' . $collectionDate . '</td>';
            // $tbl.='<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;">VIRAL</td>';
            $tbl .= '<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;"><br><img style="width:200px;height:30px;" src="' . $general->getBarcodeImageContent($sampleResult[0]['sample_code'], $barcodeFormat) . '"></td>';
            $tbl .= '</tr>';
            $sampleCounter++;
        }
        $tbl .= '</table>';

        $tbl .= '<table cellspacing="0" style="width:100%;">';
        $tbl .= '<tr >';
        $tbl .= '<td align="left" style="vertical-align:middle;font-size:11px;width:33.33%;"><strong>Generated By : </strong><br>' . $_SESSION['userName'] . '</td>';
        $tbl .= '<td align="left" style="vertical-align:middle;font-size:11px;width:33.33%;"><strong>Verified By :  </strong></td>';
        $tbl .= '<td align="left" style="vertical-align:middle;font-size:11px;width:33.33%;"><strong>Received By : </strong><br>(at ' . $labname . ')</td>';
        $tbl .= '</tr>';
        $tbl .= '</table>';
        $filename = trim((string) $bResult[0]['manifest_code']) . '-' . date('Y-m-d') . '-Manifest.pdf';
        $pdf->writeHTML($tbl);
        $pdf->Output($filename);
        exit;
    }
}
