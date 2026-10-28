<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\PdfOutputTestCase;

/**
 * EID result PDFs, one per country form. See Tests\Support\PdfOutputTestCase.
 */
final class PdfEidResultOutputTest extends PdfOutputTestCase
{
    public static function pdfProvider(): array
    {
        return [
            'EID result, South Sudan form' => ['eid-result-south-sudan'],
            'EID result, Sierra Leone form' => ['eid-result-sierra-leone'],
            'EID result, DRC form' => ['eid-result-drc'],
            'EID result, Cameroon form' => ['eid-result-cameroon'],
            'EID result, Rwanda form' => ['eid-result-rwanda'],
            'EID result, Burkina Faso form' => ['eid-result-burkina-faso'],
        ];
    }
}
