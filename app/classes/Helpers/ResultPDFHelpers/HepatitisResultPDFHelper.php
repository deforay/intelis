<?php

namespace App\Helpers\ResultPDFHelpers;

use TCPDF;
use App\Utilities\MiscUtility;

class HepatitisResultPDFHelper extends TCPDF
{
    public ?string $logo;
    public string $text = '';
    public ?string $lab;
    public ?string $htitle;
    public ?string $labFacilityId = '';
    public string $formId = '';
    //Page header
    public function setHeading($logo, $text, $lab, $title = null, $labFacilityId = null, $formId = null)
    {
        $this->logo = $logo;
        $this->text = $text;
        $this->lab = $lab;
        $this->htitle = $title;
        $this->labFacilityId = $labFacilityId;
        $this->formId = $formId;
    }
    public function imageExists($filePath): bool
    {
        return MiscUtility::imageExists($filePath);
    }
    //Page header
    public function Header()
    {
        if ($this->htitle != '') {
            if (trim($this->logo) != '') {
                if (file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo)) {
                    $imageFilePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo;
                    $this->Image($imageFilePath, 95, 5, 15, '', '', '', 'T');
                }
            }
            $this->SetFont('helvetica', 'B', 16);
            $this->writeHTMLCell(0, 0, 10, 18, $this->text, 0, 0, 0, true, 'C');
            if (trim($this->lab) != '') {
                $this->SetFont('helvetica', '', 10);
                $this->writeHTMLCell(0, 0, 10, 25, strtoupper($this->lab), 0, 0, 0, true, 'C');
            }
            $this->SetFont('helvetica', '', 12);
            $this->writeHTMLCell(0, 0, 10, 30, 'Hepatitis Viral Load Results Report', 0, 0, 0, true, 'C');
            $this->writeHTMLCell(0, 0, 15, 38, '<hr>', 0, 0, 0, true, 'C');
        } else {
            if (trim($this->logo) != '') {
                if (file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $this->labFacilityId . DIRECTORY_SEPARATOR . $this->logo)) {
                    $imageFilePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'facility-logo' . DIRECTORY_SEPARATOR . $this->labFacilityId . DIRECTORY_SEPARATOR . $this->logo;
                    $this->Image($imageFilePath, 16, 13, 15, '', '', '', 'T');
                } elseif (file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo)) {
                    $imageFilePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo;
                    $this->Image($imageFilePath, 20, 13, 15, '', '', '', 'T');
                }
            }
            if (file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . 'drc-logo.png')) {
                $imageFilePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . 'drc-logo.png';
                $this->Image($imageFilePath, 180, 13, 15, '', '', '', 'T');
            }

            $this->SetFont('helvetica', '', 14);
            $this->writeHTMLCell(0, 0, 10, 9, 'MINISTERE DE LA SANTE PUBLIQUE', 0, 0, 0, true, 'C');
            if ($this->text != '') {
                $this->SetFont('helvetica', '', 12);
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
