<?php

namespace App\Helpers;

use App\Utilities\MiscUtility;
use TCPDF;

class ManifestPdfHelper extends TCPDF
{
    public ?string $logo = "";
    public string $text = "";
    public ?string $labname = "";
    public ?string $manifestType;

    public function setHeading($logo, $text, $labname)
    {
        $this->logo = $logo;
        $this->text = $text;
        $this->labname = $labname;
    }

    public function __construct($manifestType = "", $orientation = 'P', $unit = 'mm', $format = 'A4', $unicode = true, $encoding = 'UTF-8', $diskcache = false, $pdfa = false)
    {
        parent::__construct($orientation, $unit, $format, $unicode, $encoding, $diskcache, $pdfa);
        $this->manifestType = $manifestType ?: _translate("Sample Referral Manifest");
    }
    //Page header
    public function Header()
    {
        if (trim($this->logo) != "") {
            if (MiscUtility::imageExists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo)) {
                $imageFilePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo;
                $this->Image($imageFilePath, 15, 10, 15, '', '', '', 'T');
            }
        }
        $this->SetFont('helvetica', '', 7);
        $this->writeHTMLCell(30, 0, 10, 26, $this->text, 0, 0, 0, true, 'A');
        $this->SetFont('helvetica', '', 13);
        $this->writeHTMLCell(0, 0, 0, 10, $this->manifestType, 0, 0, 0, true, 'C');
        $this->SetFont('helvetica', '', 10);
        $this->writeHTMLCell(0, 0, 0, 20, $this->labname, 0, 0, 0, true, 'C');

        if (trim($this->logo) != "") {
            if (file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo)) {
                $imageFilePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo;
                $this->Image($imageFilePath, 262, 10, 15, '', '', '', 'T');
            }
        }
        $this->SetFont('helvetica', '', 7);
        $this->writeHTMLCell(30, 0, 255, 26, $this->text, 0, 0, 0, true, 'A');
        $html = '<hr/>';
        $this->writeHTMLCell(0, 0, 10, 32, $html, 0, 0, 0, true, 'J');
    }

    // Page footer
    public function Footer()
    {
        // Position at 15 mm from bottom
        $this->SetY(-15);
        // Set font
        $this->SetFont('helvetica', '', 8);
        // Page number
        $this->Cell(0, 10,  'Specimen Manifest Generated On : ' . date('d/m/Y H:i:s') . ' | Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C', 0);
    }
}
