<?php

namespace App\Helpers\ResultPDFHelpers\CountrySpecificHelpers;

use App\Utilities\MiscUtility;
use App\Helpers\ResultPDFHelpers\TBResultPDFHelper;

class RwandaTBResultPDFHelper extends TBResultPDFHelper
{
    public ?string $logo = null;
    public ?string $text = null;
    public ?string $lab = null;
    public ?string $htitle = null;
    public ?string $labFacilityId = null;
    public ?string $formId = null;
    public array $facilityInfo = [];
    public ?string $trainingTxt = null;
    private ?string $pdfTemplatePath = null;
    private bool $templateImported = false;
    private bool $enableFooter = true; // Default is true to render footer

    public function setHeading($logo, $text, $lab, $title = null, $labFacilityId = null, $formId = null, $facilityInfo = [], $pdfTemplatePath)
    {
        $this->logo = $logo;
        $this->text = $text;
        $this->lab = $lab;
        $this->htitle = $title;
        $this->labFacilityId = $labFacilityId;
        $this->formId = $formId;
        $this->facilityInfo = $facilityInfo;
        $this->pdfTemplatePath = $pdfTemplatePath ?? null;
    }
    //Page header
    public function Header()
    {
        // die($this->pdfTemplatePath);
        // die;
        // Logo
        if (!empty($this->pdfTemplatePath) && MiscUtility::fileExists($this->pdfTemplatePath)) {
            if (!$this->templateImported) {
                $this->setSourceFile($this->pdfTemplatePath);
                $this->templateImported = true;
            }
            $tplIdx = $this->importPage(1);
            $this->useTemplate($tplIdx, 0, 0);
        } else if (!empty($this->htitle) && trim($this->htitle) != '') {

            if (isset($this->formId) && $this->formId == 1) {
                if (!empty($this->logo) && trim($this->logo) != '') {
                    if (file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo)) {
                        $imageFilePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo;
                        $this->Image($imageFilePath, 10, 5, 25, '', '', '', 'T');
                    }
                }
                $this->SetFont('helvetica', 'B', 15);
                $this->writeHTMLCell(0, 0, 15, 7, $this->text, 0, 0, 0, true, 'C');
                if (!empty($this->lab) && trim($this->lab) != '') {
                    $this->SetFont('helvetica', 'B', 11);
                    // $this->writeHTMLCell(0, 0, 40, 15, strtoupper($this->lab), 0, 0, 0, true, 'L', true);
                    $this->writeHTMLCell(0, 0, 15, 15, 'Public Health Laboratory', 0, 0, 0, true, 'C');
                }

                $this->SetFont('helvetica', '', 9);
                $this->writeHTMLCell(0, 0, 15, 21, $this->facilityInfo['address'], 0, 0, 0, true, 'C');

                $this->SetFont('helvetica', '', 9);

                $emil = (isset($this->facilityInfo['report_email']) && $this->facilityInfo['report_email'] != "") ? 'E-mail : ' . $this->facilityInfo['report_email'] : "";
                $phone = (isset($this->facilityInfo['facility_mobile_numbers']) && $this->facilityInfo['facility_mobile_numbers'] != "") ? 'Phone : ' . $this->facilityInfo['facility_mobile_numbers'] : "";
                if (isset($this->facilityInfo['report_email']) && $this->facilityInfo['report_email'] != "" && isset($this->facilityInfo['facility_mobile_numbers']) && $this->facilityInfo['facility_mobile_numbers'] != "") {
                    $space = '&nbsp;&nbsp;|&nbsp;&nbsp;';
                } else {
                    $space = "";
                }
                $this->writeHTMLCell(0, 0, 15, 26, $emil . $space . $phone, 0, 0, 0, true, 'L');


                $this->writeHTMLCell(0, 0, 10, 33, '<hr>', 0, 0, 0, true, 'C');
                $this->writeHTMLCell(0, 0, 10, 34, '<hr>', 0, 0, 0, true, 'C');
                $this->SetFont('helvetica', 'B', 12);
                $this->writeHTMLCell(0, 0, 20, 35, 'SOUTH SUDAN TB SAMPLES REFERRAL SYSTEM (SS)', 0, 0, 0, true, 'C');

                // $this->writeHTMLCell(0, 0, 25, 35, '<hr>', 0, 0, 0, true, 'C', true);
            } else {
                if (!empty($this->logo) && trim($this->logo) != '') {
                    if (file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo)) {
                        $imageFilePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo;
                        $this->Image($imageFilePath, 95, 5, 15, '', '', '', 'T');
                    }
                }

                $this->SetFont('helvetica', 'B', 8);
                $this->writeHTMLCell(0, 0, 10, 22, $this->text, 0, 0, 0, true, 'C');
                if (!empty($this->lab) && trim($this->lab) != '') {
                    $this->SetFont('helvetica', '', 9);
                    $this->writeHTMLCell(0, 0, 10, 26, strtoupper($this->lab), 0, 0, 0, true, 'C');
                }

                $this->SetFont('helvetica', '', 14);
                $this->writeHTMLCell(0, 0, 10, 30, 'PATIENT REPORT FOR TB TEST', 0, 0, 0, true, 'C');

                $this->writeHTMLCell(0, 0, 15, 38, '<hr>', 0, 0, 0, true, 'C');
            }
        }
    }

    // Page footer
    public function Footer(): void
    {
        // Position at 15 mm from bottom
        $this->SetY(-15);
        // Set font
        $this->SetFont('helvetica', '', 8);
        // Page number
        // $this->Cell(0, 10, "", 0, false, 'L  ', 0);
        $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . ' of ' . $this->getAliasNbPages(), 0, false, 'R', 0);
    }
}
