<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\PdfOutputTestCase;

/**
 * COVID-19 result PDFs, one per country form and report format. See Tests\Support\PdfOutputTestCase.
 */
final class PdfCovid19ResultOutputTest extends PdfOutputTestCase
{
    public static function pdfProvider(): array
    {
        return [
            'COVID-19 result, South Sudan form' => ['covid-19-result-south-sudan'],
            'COVID-19 result, Sierra Leone form' => ['covid-19-result-sierra-leone'],
            'COVID-19 result, DRC form' => ['covid-19-result-drc-1'],
            'COVID-19 result, Cameroon form' => ['covid-19-result-cameroon'],
            'COVID-19 result, PNG form' => ['covid-19-result-png'],
            'COVID-19 result, Rwanda form' => ['covid-19-result-rwanda'],
            'COVID-19 result, DRC report format' => ['covid-19-result-drc'],
            'COVID-19 result, DRC report format 2' => ['covid-19-result-drc-2'],
            'COVID-19 result, DRC report format 3' => ['covid-19-result-drc-3'],
            'COVID-19 result, DRC report format 4' => ['covid-19-result-drc-4'],
            'COVID-19 result, DRC report format 5' => ['covid-19-result-drc-5'],
            'COVID-19 result, DRC report format 6' => ['covid-19-result-drc-6'],
            'COVID-19 result, DRC report format 7' => ['covid-19-result-drc-7'],
            'COVID-19 result, DRC report format 8' => ['covid-19-result-drc-8'],
            'COVID-19 result, South Sudan default report format' => ['covid-19-result-ssudan-default'],
        ];
    }
}
