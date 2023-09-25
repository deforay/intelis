<?php

namespace App\Helpers;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;

class BatchPdfHelper extends \TCPDF
{
    public $logo;
    public $text;
    public $batch;
    public $resulted;
    public $reviewed;
    public $createdBy;
    public $worksheetName;


    public function setHeading($logo, $text, $batch, $resulted, $reviewed, $createdBy, $worksheetName)
    {
        $this->logo = $logo;
        $this->text = $text;
        $this->batch = $batch;
        $this->resulted = $resulted;
        $this->reviewed = $reviewed;
        $this->createdBy = $createdBy;
        $this->worksheetName = $worksheetName;
    }
    //Page header
    public function Header()
    {
        // Logo
        //$imageFilePath = K_PATH_IMAGES.'logo_example.jpg';
        //$this->Image($imageFilePath, 10, 10, 15, '', 'JPG', '', 'T', false, 300, '', false, false, 0, false, false, false);
        // Set font
        if (trim($this->logo) != "") {
            if (MiscUtility::imageExists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo)) {
                $imageFilePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo;
                $this->Image($imageFilePath, 15, 10, 15, '', '', '', 'T');
            }
        }
        $this->SetFont('helvetica', '', 7);
        $this->writeHTMLCell(30, 0, 10, 26, $this->text, 0, 0, 0, true, 'A');
        $this->SetFont('helvetica', '', 13);
        $this->writeHTMLCell(0, 0, 0, 10, _translate('Batch Number/Code') . ' : ' . $this->batch, 0, 0, 0, true, 'C');
        $this->writeHTMLCell(0, 0, 0, 20, $this->worksheetName, 0, 0, 0, true, 'C');
        $this->SetFont('helvetica', '', 9);
        $this->writeHTMLCell(0, 0, 144, 10, _translate('Result On') . ' : ' . $this->resulted, 0, 0, 0, true, 'C');
        $this->writeHTMLCell(0, 0, 144, 16, _translate('Reviewed On') . ' : ' . $this->reviewed, 0, 0, 0, true, 'C');
        $this->writeHTMLCell(0, 0, 144, 22, _translate('Created By') . ' : ' . $this->createdBy, 0, 0, 0, true, 'C');
        $html = '<hr />';
        $this->writeHTMLCell(0, 0, 10, 32, $html, 0, 0, 0, true, 'J');
    }

    // Page footer
    public function Footer()
    {
        // Position at 15 mm from bottom
        $this->SetY(-15);
        // Set font
        $this->SetFont('helvetica', 'I', 8);
        // Page number
        $text = _translate("Batch file generated on") .' : ' . DateUtility::humanReadableDateFormat(DateUtility::getCurrentDateTime(), true);
        $this->Cell(0, 10, $text, 0, false, 'L  ', 0);
        $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C', 0);
    }
}
