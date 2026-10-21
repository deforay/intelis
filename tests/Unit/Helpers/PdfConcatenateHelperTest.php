<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use App\Helpers\BasePdf;
use App\Helpers\PdfConcatenateHelper;
use PHPUnit\Framework\TestCase;
use setasign\Fpdi\Tcpdf\Fpdi;

/**
 * Merging result PDFs keeps each page's size and orientation. concat() read the
 * page size under FPDI 1 key names, which FPDI 2 does not return, so every merged
 * page was added with no size, in portrait.
 */
final class PdfConcatenateHelperTest extends TestCase
{
    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
    }

    private function pdf(string $orientation, string $format): string
    {
        $pdf = new BasePdf($orientation, 'mm', $format);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();
        $pdf->Write(0, "$orientation $format");
        $file = tempnam(sys_get_temp_dir(), 'concat-') . '.pdf';
        $pdf->Output($file, 'F');
        $this->files[] = $file;
        return $file;
    }

    public function testMergedPagesKeepTheirSizeAndOrientation(): void
    {
        $merged = tempnam(sys_get_temp_dir(), 'concat-merged-') . '.pdf';
        $this->files[] = $merged;

        $helper = new PdfConcatenateHelper();
        $helper->mergeFiles([$this->pdf('P', 'A4'), $this->pdf('L', 'A4'), $this->pdf('P', 'A5')], $merged);

        $reader = new Fpdi();
        $this->assertSame(3, $reader->setSourceFile($merged));
        $sizes = [];
        foreach ([1, 2, 3] as $page) {
            $size = $reader->getTemplateSize($reader->importPage($page));
            $sizes[] = [$size['orientation'], round($size['width']), round($size['height'])];
        }

        $this->assertSame([['P', 210.0, 297.0], ['L', 297.0, 210.0], ['P', 148.0, 210.0]], $sizes);
    }
}
