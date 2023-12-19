<?php

namespace App\Helpers\ResultPDFHelpers;

use TCPDF;
use App\Utilities\MiscUtility;

class TBResultPDFHelper extends TCPDF
{
    public ?string $logo = null;
    public ?string $text = null;
    public ?string $lab = null;
    public ?string $htitle = null;
    public ?string $labFacilityId = null;
    public ?string $formId = null;
    public array $facilityInfo = [];


    //Page header
    public function setHeading($logo, $text, $lab, $title = null, $labFacilityId = null, $formId = null, $facilityInfo = [])
    {
        $this->logo = $logo;
        $this->text = $text;
        $this->lab = $lab;
        $this->htitle = $title;
        $this->labFacilityId = $labFacilityId;
        $this->formId = $formId;
        $this->facilityInfo = $facilityInfo;
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
                if (file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo)) {
                    $imageFilePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo;
                    if ($this->formId == 3) {
                        $this->Image($imageFilePath, 10, 5, 25, '', '', '', 'T');
                    } else {
                        $this->Image($imageFilePath, 95, 5, 15, '', '', '', 'T');
                    }
                }
            }
            if ($this->formId == 3) {
                $this->SetFont('helvetica', 'B', 16);
                $this->writeHTMLCell(0, 0, 10, 03, $this->text, 0, 0, 0, true, 'C');
                if (!empty($this->lab) && trim($this->lab) != '') {
                    $this->SetFont('helvetica', '', 10);
                    $this->writeHTMLCell(0, 0, 10, 10, strtoupper($this->lab), 0, 0, 0, true, 'C');
                }
                $this->SetFont('helvetica', 'b', 10);
                $this->writeHTMLCell(0, 0, 10, 18, 'Département de Virologie', 0, 0, 0, true, 'C');
                $this->SetFont('helvetica', 'u', 10);
                $this->writeHTMLCell(0, 0, 10, 25, 'Laboratoire National de Reference pour la Grippe et les Virus Respiratoires', 0, 0, 0, true, 'C');
                $this->SetFont('helvetica', 'b', 12);
                $this->writeHTMLCell(0, 0, 10, 33, 'RESULTATS DE LABORATOIRE DES ECHANTIONS RESPIRATOIRES', 0, 0, 0, true, 'C');
                $this->SetFont('helvetica', 'u', 10);
                $this->writeHTMLCell(0, 0, 10, 40, 'TESTES AU COVID-19 PAR RT-PCR en temps réel N°', 0, 0, 0, true, 'C');
                $this->writeHTMLCell(0, 0, 15, 48, '<hr>', 0, 0, 0, true, 'C');
            } else {
                $this->SetFont('helvetica', 'B', 16);
                $this->writeHTMLCell(0, 0, 10, 18, $this->text ?? '', 0, 0, 0, true, 'C');
                if (!empty($this->lab) && trim($this->lab) != '') {
                    $this->SetFont('helvetica', '', 10);
                    $this->writeHTMLCell(0, 0, 10, 25, strtoupper($this->lab), 0, 0, 0, true, 'C');
                }
                $this->SetFont('helvetica', '', 12);
                $this->writeHTMLCell(0, 0, 10, 30, 'COVID-19 TEST - PATIENT REPORT', 0, 0, 0, true, 'C');
                $this->writeHTMLCell(0, 0, 15, 38, '<hr>', 0, 0, 0, true, 'C');
            }
        } else {
            if (!empty($this->logo) && trim($this->logo) != '') {
                if (file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $this->labFacilityId . DIRECTORY_SEPARATOR . $this->logo)) {
                    $imageFilePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'facility-logo' . DIRECTORY_SEPARATOR . $this->labFacilityId . DIRECTORY_SEPARATOR . $this->logo;
                    $this->Image($imageFilePath, 16, 13, 15, '', '', '', 'T');
                } else if (file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo)) {
                    $imageFilePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo;
                    $this->Image($imageFilePath, 20, 13, 15, '', '', '', 'T');
                }
            }
            if (file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . 'drc-logo.png')) {
                $imageFilePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . 'drc-logo.png';
                $this->Image($imageFilePath, 180, 13, 15, '', '', '', 'T');
            }

            // $this->SetFont('helvetica', 'B', 7);
            // $this->writeHTMLCell(30,0,16,28,$this->text, 0, 0, 0, true, 'A', true);(this two lines comment out for drc)
            $this->SetFont('helvetica', '', 14);
            $this->writeHTMLCell(0, 0, 10, 9, 'MINISTERE DE LA SANTE PUBLIQUE', 0, 0, 0, true, 'C');
            if (!empty($this->text) && trim($this->text) != '') {
                $this->SetFont('helvetica', '', 12);
                //        $this->writeHTMLCell(0,0,10,16,'PROGRAMME NATIONAL DE LUTTE CONTRE LE SIDA ET IST', 0, 0, 0, true, 'C', true);
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
            if (!empty($this->lab) && trim($this->lab) != '') {
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
