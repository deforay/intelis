<?php

/**
 * Renders the Sample Testing tab. Included by each module's endpoint after it
 * has built $report with SampleTestingReportUtility::fetch().
 *
 * @var array{rows: list<array<string, mixed>>, startDate: string, endDate: string} $report
 */

use App\Utilities\DateUtility;
use App\Utilities\SampleTestingReportUtility;

$payload = [
    'rows' => $report['rows'],
    'period' => DateUtility::humanReadableDateFormat($report['startDate'])
        . ' - ' . DateUtility::humanReadableDateFormat($report['endDate']),
    'locale' => str_replace('_', '-', (string) ($_SESSION['APP_LOCALE'] ?? 'en_US')),
    // Column headings come from the utility, so the export's match the table's.
    'labels' => SampleTestingReportUtility::columns() + [
        'title' => _translate('Sample Testing by Facility'),
        'topFacilities' => _translate('Top %s of %s facilities by samples collected'),
        'allFacilities' => _translate('%s facilities'),
        'samples' => _translate('Samples'),
        'pending' => _translate('Pending'),
        'testedRate' => _translate('% Tested'),
        'ofCollected' => _translate('%s of samples collected'),
        'details' => _translate('Facility Breakdown'),
        'noData' => _translate('No samples were collected in the selected period for these filters.'),
    ],
];
?>
<div class="sample-testing-report"></div>
<script>
    ClinicReports.renderSampleTesting(
        $('#sampleTestingResultDetails .sample-testing-report'),
        <?= json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE); ?>
    );
</script>
