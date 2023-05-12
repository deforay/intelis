<?php

namespace App\Helpers;

use setasign\Fpdi\Tcpdf\Fpdi;


class PdfWatermarkHelper extends Fpdi
{

    public $tplIdx;
    public int $angle = 0;
    public $fullPathToFile;
    public string $watermarkText = 'DRAFT';

    public function setFullPathToFile($fullPathToFile)
    {
        $this->fullPathToFile = $fullPathToFile;
    }

    public function setWatermarkText($watermarkText)
    {
        $this->watermarkText = $watermarkText;
    }

    public function Rotate($angle, $x = -1, $y = -1)
    {
        if ($x == -1) {
            $x = $this->x;
        }
        if ($y == -1) {
            $y = $this->y;
        }
        if ($this->angle != 0) {
            $this->_out('Q');
        }
        $this->angle = $angle;
        if ($angle != 0) {
            $angle *= M_PI / 180;
            $c = cos($angle);
            $s = sin($angle);
            $cx = $x * $this->k;
            $cy = ($this->h - $y) * $this->k;
            $this->_out(sprintf('q %.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm', $c, $s, -$s, $c, $cx, $cy, -$cx, -$cy));
        }
    }

    public function _endpage()
    {
        if ($this->angle != 0) {
            $this->angle = 0;
            $this->_out('Q');
        }
        parent::_endpage();
    }

    public function Header()
    {

        //Put the watermark
        $this->SetFont('helvetica', 'B', 50);
        $this->SetTextColor(148, 162, 204);
        $this->rotatedText(67, 109, $this->watermarkText, 45);

        if (is_null($this->tplIdx)) {
            // THIS IS WHERE YOU GET THE NUMBER OF PAGES
            $this->numPages = $this->setSourceFile($this->fullPathToFile);
            $this->tplIdx = $this->importPage(1);
        }
        $this->useTemplate($this->tplIdx, 0, 0, 200);
    }

    public function rotatedText($x, $y, $txt, $angle)
    {
        //Text rotated around its origin
        $this->Rotate($angle, $x, $y);
        $this->Text($x, $y, $txt);
        $this->Rotate(0);
        //$this->SetAlpha(0.7);
    }
}
