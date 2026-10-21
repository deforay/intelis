<?php

namespace App\Helpers;

use Override;
use App\Utilities\DateUtility;

class CompactBatchPdfHelper extends BasePdf
{
    public ?string $text = null;
    public ?array $batchDetails = null;

    public function setHeading(?array $batchDetails): void
    {
        $this->batchDetails = $batchDetails;
    }

    /**
     * Close the worksheet table. The cell loops start a new row after every
     * second cell, so a batch with an even count ends on an empty row, which
     * TCPDF cannot lay out and warns about.
     */
    public static function closeTable(string $html): string
    {
        return (preg_replace('~</tr><tr>$~', '', $html) ?? $html) . '</tr></table>';
    }

    //Page header
    #[Override]
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
    #[Override]
    public function Footer(): void
    {
        $this->Cell(0, 15, _translate('Page') . ' ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C', 0);
    }
}
