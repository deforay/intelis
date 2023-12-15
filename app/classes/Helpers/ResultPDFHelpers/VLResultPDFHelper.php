<?php

namespace App\Helpers\ResultPDFHelpers;

use TCPDF;
use App\Utilities\MiscUtility;

class VLResultPDFHelper extends TCPDF
{
    public ?string $logo;
    public ?string $text;
    public ?string $lab;
    public ?string $htitle;
    public ?string $formId;
    public ?string $labFacilityId = null;
    public ?string $trainingTxt = null;

    //Page header
    public function setHeading($logo, $text, $lab, $title = null, $labFacilityId = null, $trainingTxt = null)
    {
        $this->logo = $logo;
        $this->text = $text;
        $this->lab = $lab;
        $this->htitle = $title;
        $this->labFacilityId = $labFacilityId;
        $this->trainingTxt = $trainingTxt;
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
                if ($this->imageExists($this->logo)) {
                    $this->Image($this->logo, 10, 5, 15, '', '', '', 'T');
                } else if ($this->imageExists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $this->labFacilityId . DIRECTORY_SEPARATOR . $this->logo)) {
                    $imageFilePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $this->labFacilityId . DIRECTORY_SEPARATOR . $this->logo;
                    $this->Image($imageFilePath, 95, 5, 15, '', '', '', 'T');
                } elseif ($this->imageExists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo)) {
                    $imageFilePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo;
                    $this->Image($imageFilePath, 95, 5, 15, '', '', '', 'T');
                }
            }
            $this->SetFont('helvetica', 'B', 8);
            $this->writeHTMLCell(0, 0, 10, 22, $this->text, 0, 0, 0, true, 'C');
            if (trim($this->lab) != '') {
                $this->SetFont('helvetica', '', 9);
                $this->writeHTMLCell(0, 0, 10, 26, strtoupper($this->lab), 0, 0, 0, true, 'C');
            }
            $this->SetFont('helvetica', '', 14);
            $this->writeHTMLCell(0, 0, 10, 30, 'HIV VIRAL LOAD PATIENT REPORT', 0, 0, 0, true, 'C');

            $this->writeHTMLCell(0, 0, 15, 38, '<hr>', 0, 0, 0, true, 'C');
        } else {
            if (trim($this->logo) != '') {
                if ($this->imageExists($this->logo)) {
                    $this->Image($this->logo, 20, 13, 15, '', '', '', 'T');
                } else if ($this->imageExists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $this->labFacilityId . DIRECTORY_SEPARATOR . $this->logo)) {
                    $imageFilePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'facility-logo' . DIRECTORY_SEPARATOR . $this->labFacilityId . DIRECTORY_SEPARATOR . $this->logo;
                    $this->Image($imageFilePath, 20, 13, 15, '', '', '', 'T');
                } else if ($this->imageExists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo)) {
                    $imageFilePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo;
                    $this->Image($imageFilePath, 20, 13, 15, '', '', '', 'T');
                }
            }
            if ($this->imageExists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . 'drc-logo.png')) {
                $imageFilePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . 'drc-logo.png';
                $this->Image($imageFilePath, 180, 13, 15, '', '', '', 'T');
            }

            if ($this->text != '') {
                $this->SetFont('helvetica', '', 16);
                $this->writeHTMLCell(0, 0, 10, 12, strtoupper($this->text), 0, 0, 0, true, 'C');
                $thirdHeading = '21';
            } else {
                $thirdHeading = '14';
            }
            if (trim($this->lab) != '') {
                $this->SetFont('helvetica', '', 10);
                $this->writeHTMLCell(0, 0, 8, $thirdHeading, strtoupper($this->lab), 0, 0, 0, true, 'C');
            }
            $this->SetFont('helvetica', '', 12);
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
        $this->writeHTML('<span Style="color:red">' . strtoupper((string) $this->trainingTxt) . '</span>', true, false, true, false, 'M');
    }
}
