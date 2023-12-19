<?php

namespace App\Helpers\ResultPDFHelpers;

use TCPDF;
use App\Utilities\MiscUtility;


class GenericTestsResultPDFHelper extends TCPDF
{
    public ?string $logo = null;
    public ?string $text = null;
    public ?string $lab = null;
    public ?string $htitle = null;
    public ?string $labFacilityId = null;
    public ?string $labName = null;
    public ?string $testType = null;

    //Page header
    public function setHeading($logo, $text, $lab, $title = null, $labFacilityId = null, $testType = null)
    {
        $this->logo = $logo;
        $this->text = $text;
        $this->lab = $lab;
        $this->htitle = $title;
        $this->labFacilityId = $labFacilityId;
        $this->testType = $testType;
    }

    public function imageExists($filePath): bool
    {
        return MiscUtility::imageExists($filePath);
    }
    //Page header
    public function Header()
    {
        if (!empty($this->htitle) && trim($this->htitle) != '') {
            if (!empty($this->logo) && trim($this->logo) != '') {
                error_log($this->logo);
                if ($this->imageExists($this->logo)) {
                    $this->Image($this->logo, 95, 5, 15, '', '', '', 'T');
                } else if ($this->imageExists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $this->labFacilityId . DIRECTORY_SEPARATOR . $this->logo)) {
                    $imageFilePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $this->labFacilityId . DIRECTORY_SEPARATOR . $this->logo;
                    $this->Image($imageFilePath, 95, 5, 15, '', '', '', 'T');
                } else if ($this->imageExists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo)) {
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
            $this->writeHTMLCell(0, 0, 10, 30, strtoupper($this->testType) . ' PATIENT REPORT', 0, 0, 0, true, 'C');

            $this->writeHTMLCell(0, 0, 15, 38, '<hr>', 0, 0, 0, true, 'C');
        } else {
            if (!empty($this->logo) && trim($this->logo) != '') {
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

            if (!empty($this->text) && trim($this->text) != '') {
                $this->SetFont('helvetica', '', 16);
                $this->writeHTMLCell(0, 0, 10, 12, strtoupper($this->text), 0, 0, 0, true, 'C');
                $thirdHeading = '21';
                $fourthHeading = '28';
                $hrLine = '36';
                $marginTop = '14';
            } else {
                $thirdHeading = '14';
                $fourthHeading = '23';
                $hrLine = '30';
                $marginTop = '9';
            }
            if (!empty($this->lab) && trim($this->lab) != '') {
                $this->SetFont('helvetica', '', 10);
                $this->writeHTMLCell(0, 0, 8, $thirdHeading, strtoupper($this->lab), 0, 0, 0, true, 'C');
            }
            $this->SetFont('helvetica', '', 12);
            $this->writeHTMLCell(0, 0, 10, $fourthHeading, strtoupper($this->testType) . ' - PATIENT REPORT', 0, 0, 0, true, 'C');
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
        $this->Cell(0, 10, 'Page' . $_SESSION['aliasPage'] . ' of ' . $_SESSION['nbPages'], 0, false, 'C', 0);
        //$this->Cell(0, 10, 'Page 1 of 1', 0, false, 'C', 0);
    }
}
