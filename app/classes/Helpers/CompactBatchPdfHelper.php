<?php

namespace App\Helpers;

use setasign\Fpdi\Tcpdf\Fpdi;
use App\Utilities\DateUtility;

class CompactBatchPdfHelper extends Fpdi
{
    public ?string $text;
    public ?array $batchDetails;

    public function setHeading($batchDetails): void
    {
        $this->batchDetails = $batchDetails;
    }

    //Page header
    public function Header(): void
    {
        // Position at 15 mm from bottom
        //$this->SetX(+15);
        // Set font
        $this->SetFont('helvetica', 'B', 8);
        // Page number
        $text = _translate("Batch Code") . " : " . $this->batchDetails['batch_code'] . ' (' . DateUtility::humanReadableDateFormat($this->batchDetails['request_created_datetime'], true) . ')';
        if (isset($this->batchDetails['lab_assigned_batch_code']) && !empty($this->batchDetails['lab_assigned_batch_code'])) {
            $text .= ' | ' .  _translate("Lab Assigned Batch Code") . ': ' . $this->batchDetails['lab_assigned_batch_code'];
        }
        $text .= ' | ' .  _translate("Generated on") . ': ' . DateUtility::humanReadableDateFormat(DateUtility::getCurrentDateTime(), true);
        $this->Cell(0, 15, $text, 0, false, 'L  ', 0);
    }


    // Page footer
    public function Footer(): void
    {
        $this->Cell(0, 15, 'Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C', 0);
    }
}
