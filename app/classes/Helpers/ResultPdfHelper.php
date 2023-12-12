<?php

namespace App\Helpers;

use App\Utilities\MiscUtility;
use TCPDF;

class ResultPdfHelper
{
    protected TCPDF $pdf;
    public function __construct()
    {
    }
    public function setPdfObject(TCPDF $pdf)
    {
        $this->pdf = $pdf;
    }
    public function setImage(string|array $imagePaths, $x = null, $y = null, $w = 0, $h = 0, $type = '', $link = '', $align = '', $resize = false, $dpi = 300, $palign = '', $ismask = false, $imgmask = false, $border = 0, $fitbox = false, $hidden = false, $fitonpage = false, $alt = false, $altimgs = [])
    {
        if (!empty($imagePaths)) {
            $imagePaths = is_array($imagePaths) ? $imagePaths : [$imagePaths];

            foreach ($imagePaths as $path) {
                if (MiscUtility::imageExists($path)) {
                    $this->pdf->Image($path, $x, $y, $w, $h, $type, $link, $align, $resize, $dpi, $palign, $ismask, $imgmask, $border, $fitbox, $hidden, $fitonpage, $alt, $altimgs);
                    break;
                }
            }
        }
    }
}
