<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\PdfOutputTestCase;

/**
 * Batch worksheets and manifests. See Tests\Support\PdfOutputTestCase.
 */
final class PdfManifestOutputTest extends PdfOutputTestCase
{
    public static function pdfProvider(): array
    {
        return [
            'VL batch worksheet' => ['vl-batch-worksheet'],
            'VL compact batch worksheet' => ['vl-batch-worksheet-compact'],
            'VL manifest' => ['vl-manifest'],
            'EID manifest' => ['eid-manifest'],
            'TB manifest' => ['tb-manifest'],
            'CD4 manifest' => ['cd4-manifest'],
            'Hepatitis manifest' => ['hepatitis-manifest'],
            'Custom Tests manifest' => ['generic-tests-manifest'],
            'COVID-19 manifest' => ['covid-19-manifest'],
            'TB referral manifest' => ['tb-referral-manifest'],
            'Custom Tests referral manifest' => ['generic-tests-referral-manifest'],
        ];
    }
}
