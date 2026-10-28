<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\PdfOutputTestCase;

/**
 * VL result PDFs, one per country form and report format. See Tests\Support\PdfOutputTestCase.
 */
final class PdfVlResultOutputTest extends PdfOutputTestCase
{
    public static function pdfProvider(): array
    {
        return [
            'VL result, South Sudan form' => ['vl-result-south-sudan'],
            'VL result, Sierra Leone form' => ['vl-result-sierra-leone'],
            'VL result, DRC form' => ['vl-result-drc'],
            'VL result, Cameroon form' => ['vl-result-cameroon'],
            'VL result, PNG form' => ['vl-result-png'],
            'VL result, Rwanda form' => ['vl-result-rwanda'],
            'VL result, Burkina Faso form' => ['vl-result-burkina-faso'],
            'VL result, Cameroon CRESAR report format' => ['vl-result-cameroon-cresar'],
        ];
    }
}
