<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\PdfOutputTestCase;

/**
 * TB, CD4, Hepatitis and Custom Tests result PDFs. See Tests\Support\PdfOutputTestCase.
 */
final class PdfOtherResultOutputTest extends PdfOutputTestCase
{
    public static function pdfProvider(): array
    {
        return [
            'TB result, South Sudan form' => ['tb-result-south-sudan'],
            'TB result, Sierra Leone form' => ['tb-result-sierra-leone'],
            'TB result, Rwanda form' => ['tb-result-rwanda'],
            'TB result, Burkina Faso form' => ['tb-result-burkina-faso'],
            'CD4 result, Rwanda form' => ['cd4-result-rwanda'],
            'Hepatitis result, Rwanda form' => ['hepatitis-result-rwanda'],
            'Custom Tests result' => ['generic-tests-result'],
        ];
    }
}
