<?php

namespace App\Helpers\ResultPDFHelpers;

use Override;
use App\Utilities\MiscUtility;
use setasign\Fpdi\Tcpdf\Fpdi;

class EIDResultPDFHelper extends Fpdi
{
    public ?string $logo = null;
    public ?string $text = null;
    public ?string $lab = null;
    public ?string $htitle = null;
    public ?string $labFacilityId = null;
    public ?string $formId = null;
    public ?string $trainingTxt = null;
    private ?string $pdfTemplatePath = null;
    private bool $templateImported = false; // Default is true to render footer

    public function __construct($orientation = 'P', $unit = 'mm', $format = 'A4', $unicode = true, $encoding = 'UTF-8', $diskCache = false, $pdfTemplatePath = null, private readonly bool $enableFooter = true)
    {
        parent::__construct($orientation, $unit, $format, $unicode, $encoding, $diskCache);
        $this->pdfTemplatePath = $pdfTemplatePath ?? null;
    }

    //Page header
    public function setHeading(?string $logo, ?string $text, ?string $lab, ?string $title = null, ?string $labFacilityId = null, ?string $formId = null): void
    {
        $this->logo = $logo;
        $this->text = $text;
        $this->lab = $lab;
        $this->htitle = $title;
        $this->labFacilityId = $labFacilityId;
        $this->formId  = $formId;
    }

    //Page header
    #[Override]
    public function Header(): void
    {
        if ($this->pdfTemplatePath !== null && $this->pdfTemplatePath !== '' && $this->pdfTemplatePath !== '0' && MiscUtility::fileExists($this->pdfTemplatePath)) {
            if (!$this->templateImported) {
                $this->setSourceFile($this->pdfTemplatePath);
                $this->templateImported = true;
            }
            $tplIdx = $this->importPage(1);
            $this->useTemplate($tplIdx);
        } elseif ($this->htitle !== null && $this->htitle !== '' && $this->htitle !== '0' && $this->htitle !== '') {
            if ($this->logo !== null && $this->logo !== '' && $this->logo !== '0' && trim($this->logo) !== '') {
                if (MiscUtility::isImageValid($this->logo)) {
                    $imageFilePath = $this->logo;
                } elseif (MiscUtility::isImageValid(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $this->labFacilityId . DIRECTORY_SEPARATOR . $this->logo)) {
                    $imageFilePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'facility-logo' . DIRECTORY_SEPARATOR . $this->labFacilityId . DIRECTORY_SEPARATOR . $this->logo;
                } elseif (MiscUtility::isImageValid(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo)) {
                    $imageFilePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo;
                }
                if ($imageFilePath !== '' && $imageFilePath !== '0') {
                    $this->Image($imageFilePath, 95, 5, 15, '', '', '', 'T');
                }
            }
            $this->SetFont('helvetica', 'B', 8);
            $this->writeHTMLCell(0, 0, 10, 22, $this->text, 0, 0, 0, true, 'C');
            if ($this->lab !== null && $this->lab !== '' && $this->lab !== '0' && trim($this->lab) !== '') {
                $this->SetFont('helvetica', '', 9);
                $this->writeHTMLCell(0, 0, 10, 26, _toUpperCase($this->lab), 0, 0, 0, true, 'C');
            }
            $this->SetFont('helvetica', '', 14);
            $this->writeHTMLCell(0, 0, 10, 30, 'EARLY INFANT DIAGNOSIS TEST - PATIENT REPORT', 0, 0, 0, true, 'C');
            $this->writeHTMLCell(0, 0, 15, 38, '<hr>', 0, 0, 0, true, 'C');
        } else {
            if ($this->logo !== null && $this->logo !== '' && $this->logo !== '0' && trim($this->logo) !== '') {
                if (MiscUtility::isImageValid($this->logo)) {
                    $imageFilePath = $this->logo;
                } elseif (MiscUtility::isImageValid(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $this->labFacilityId . DIRECTORY_SEPARATOR . $this->logo)) {
                    $imageFilePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'facility-logo' . DIRECTORY_SEPARATOR . $this->labFacilityId . DIRECTORY_SEPARATOR . $this->logo;
                } elseif (MiscUtility::isImageValid(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo)) {
                    $imageFilePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo;
                }
                if ($imageFilePath !== '' && $imageFilePath !== '0') {
                    $this->Image($imageFilePath, 20, 13, 15, '', '', '', 'T');
                }
            }

            // $this->SetFont('helvetica', 'B', 7);
            // $this->writeHTMLCell(30,0,16,28,$this->text, 0, 0, 0, true, 'A', true);(this two lines comment out for drc)
            $this->SetFont('helvetica', '', 14);
            $this->writeHTMLCell(0, 0, 10, 9, 'MINISTERE DE LA SANTE PUBLIQUE', 0, 0, 0, true, 'C');
            if ($this->text !== null && $this->text !== '' && $this->text !== '0' && trim($this->text) !== '') {
                $this->SetFont('helvetica', '', 12);
                //        $this->writeHTMLCell(0,0,10,16,'PROGRAMME NATIONAL DE LUTTE CONTRE LE SIDA ET IST', 0, 0, 0, true, 'C', true);
                $this->writeHTMLCell(0, 0, 10, 16, _toUpperCase($this->text), 0, 0, 0, true, 'C');
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
            if ($this->lab !== null && $this->lab !== '' && $this->lab !== '0' && trim($this->lab) !== '') {
                $this->SetFont('helvetica', '', 9);
                $this->writeHTMLCell(0, 0, 10, $thirdHeading, _toUpperCase($this->lab), 0, 0, 0, true, 'C');
            }
            $this->SetFont('helvetica', '', 12);
            if ($this->formId == 3) {
                $this->writeHTMLCell(0, 0, 10, $fourthHeading, 'DIAGNOSTIC PRÉCOCE DU NOURRISSON', 0, 0, 0, true, 'C');
            } else {
                $this->writeHTMLCell(0, 0, 10, $fourthHeading, 'RESULTATS CHARGE VIRALE', 0, 0, 0, true, 'C');
            }
            $this->writeHTMLCell(0, 0, 15, $hrLine, '<hr>', 0, 0, 0, true, 'C');
        }
    }

    // Page footer
    #[Override]
    public function Footer(): void
    {

        $this->SetY(-15);
        // Set font
        $this->SetFont('helvetica', '', 8);
        if ($this->enableFooter) {
            // Position at 15 mm from bottom
            // Page number
            $this->Cell(0, 10, _translate('Page') . ' ' . $this->getAliasNumPage() . ' ' . _translate('of') . ' ' . $this->getAliasNbPages(), 0, false, 'C', 0);
        }
        if ($this->trainingTxt !== null && $this->trainingTxt !== '' && $this->trainingTxt !== '0') {
            $this->writeHTML('<span style="color:red">' . _toUpperCase((string) $this->trainingTxt) . '</span>', true, false, true, false, 'M');
        }
    }
}
