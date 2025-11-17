<?php

namespace App\Helpers;

use Override;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use setasign\Fpdi\Tcpdf\Fpdi;

class BatchPdfHelper extends Fpdi
{
    public ?string $logo = null;
    public ?string $text = null;
    public ?string $batch = null;
    public ?string $resulted = null;
    public ?string $reviewed = null;
    public ?string $createdBy = null;
    public ?string $worksheetName = null;


    public function setHeading(?string $logo, ?string $text, ?string $batch, ?string $resulted, ?string $reviewed, ?string $createdBy, ?string $worksheetName): void
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
    #[Override]
    public function Header(): void
    {

        if (trim((string) $this->logo) != "" && MiscUtility::isImageValid(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo)) {
            $imageFilePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo;
            $this->Image($imageFilePath, 15, 10, 15, '', '', '', 'T');
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
    #[Override]
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

    public static function buildBarcodeImageTag(
        CommonService $general,
        ?string $code,
        string $format,
        array $options = []
    ): string {
        $code = trim((string) $code);
        if ($code === '') {
            return '';
        }

        $format = strtoupper($format);
        $linearWidth = $options['linear_width'] ?? '200px';
        $linearHeight = $options['linear_height'] ?? '25px';
        $qrSize = $options['qr_size'] ?? '50px';

        $altText = htmlspecialchars($code, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        if (stripos($format, 'QR') === 0) {
            $src = $general->get2DBarcodeImageContent($code, $format);
            return sprintf(
                '<img style="width:%1$s;height:%1$s;" src="%2$s" alt="%3$s">',
                $qrSize,
                $src,
                $altText
            );
        }

        $src = $general->getBarcodeImageContent($code, $format);
        return sprintf(
            '<img style="width:%1$s;height:%2$s;" src="%3$s" alt="%4$s">',
            $linearWidth,
            $linearHeight,
            $src,
            $altText
        );
    }
}
