<?php

namespace App\Helpers\ResultPDFHelpers\CountrySpecificHelpers;

use App\Utilities\MiscUtility;
use App\Helpers\ResultPDFHelpers\VLResultPDFHelper;
use App\Utilities\LoggerUtility;

class DrcVlPDFHelper extends VLResultPDFHelper
{
    //Page header
    public function Header()
    {
        $imageFilePath = null;
        if (!empty($this->logo) && trim($this->logo) != '') {
            if (MiscUtility::isImageValid($this->logo)) {
                $imageFilePath = $this->logo;
            } elseif (MiscUtility::isImageValid(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $this->labFacilityId . DIRECTORY_SEPARATOR . $this->logo)) {
                $imageFilePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'facility-logo' . DIRECTORY_SEPARATOR . $this->labFacilityId . DIRECTORY_SEPARATOR . $this->logo;
            } elseif (MiscUtility::isImageValid(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo)) {
                $imageFilePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo;
            }
            if (!empty($imageFilePath) && MiscUtility::isImageValid($imageFilePath)) {
                $this->Image($imageFilePath, 20, 13, 15, '', '', '', 'T');
            }
        }
        if (MiscUtility::isImageValid(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . 'drc-logo.png')) {
            $imageFilePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . 'drc-logo.png';
            $this->Image($imageFilePath, 180, 13, 15, '', '', '', 'T');
        }

        $this->SetFont('helvetica', '', 14);
        $this->writeHTMLCell(0, 0, 10, 9, 'MINISTERE DE LA SANTE PUBLIQUE', 0, 0, 0, true, 'C');
        if (!empty($this->text) && trim($this->text) != '') {
            $this->SetFont('helvetica', '', 12);
            $this->writeHTMLCell(0, 0, 10, 16, strtoupper($this->text), 0, 0, 0, true, 'C');
            $thirdHeading = '23';
            $fourthHeading = '28';
            $hrLine = '36';
        } else {
            $thirdHeading = '17';
            $fourthHeading = '23';
            $hrLine = '30';
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
