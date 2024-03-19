<?php

namespace App\Helpers;

use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use setasign\Fpdi\Tcpdf\Fpdi;

class CustomBatchPdfHelper extends Fpdi
{

    public ?string $text;

    public function setHeading(): void
    {
        $this->Header();
    }
    //Page header
    public function Header(): void
    {

        
    }

    // Page footer
    public function Footer(): void
    {
        // Position at 15 mm from bottom
        $this->SetY(-15);
        // Set font
        $this->SetFont('helvetica', 'I', 8);
        // Page number
        $text = _translate("Batch file generated on") . ' : ' . DateUtility::humanReadableDateFormat(DateUtility::getCurrentDateTime(), true);
        $this->Cell(0, 10, $text, 0, false, 'L  ', 0);
        $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C', 0);
    }
}