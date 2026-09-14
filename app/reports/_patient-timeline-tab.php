<?php

/**
 * The Patient Test History tab, shared by every clinic report page. Include it
 * as the tab pane; clinic-reports.js must be on the page.
 */

use App\Services\TestsService;
use App\Utilities\PatientTimelineUtility;

$ptTypes = [];
foreach (PatientTimelineUtility::visibleTypes() as $ptType) {
    $ptTypes[$ptType] = [
        'name' => html_entity_decode((string) TestsService::getTestName($ptType), ENT_QUOTES),
        'short' => TestsService::getTestShortCode($ptType),
    ];
}
// Custom Tests use "T" as their code, which reads as nothing on a badge.
if (isset($ptTypes['generic-tests'])) {
    $ptTypes['generic-tests']['short'] = _translate('Custom Test');
}

$ptConfig = [
    'endpoint' => '/reports/get-patient-timeline.php',
    'locale' => str_replace('_', '-', (string) ($_SESSION['APP_LOCALE'] ?? 'en_US')),
    'types' => $ptTypes,
    'labels' => [
        'searchPlaceholder' => _translate('Patient ID, ART number, child ID or name'),
        'searchHint' => _translate('Type at least 2 characters. Records are matched across every test type by patient identifier.'),
        'search' => _translate('Search'),
        'searching' => _translate('Searching...'),
        'loading' => _translate('Loading patient history...'),
        'noPatients' => _translate('No patients match this search.'),
        'tooMany' => _translate('Showing the %s most recent matches. Type more of the ID or name to narrow the list.'),
        'error' => _translate('Unable to load the patient history right now'),
        'back' => _translate('Back to search results'),
        'print' => _translate('Print'),
        'unnamed' => _translate('Name not recorded'),
        'patientId' => _translate('Patient ID'),
        'sex' => _translate('Sex'),
        'dob' => _translate('Date of Birth'),
        'age' => _translate('Age'),
        'ageAt' => _translate('%s years on %s'),
        'facilities' => _translate('Facilities'),
        'firstSample' => _translate('First Sample'),
        'lastSample' => _translate('Last Sample'),
        'samples' => _translate('Samples'),
        'lastSampleOn' => _translate('Last sample %s'),
        'alsoRecordedAs' => _translate('Also recorded as'),
        'overview' => _translate('All Tests Over Time'),
        'vlTrend' => _translate('Viral Load Trend'),
        'cd4Trend' => _translate('CD4 Trend'),
        'copies' => _translate('copies/mL'),
        'cells' => _translate('cells/mm³'),
        'threshold' => _translate('Threshold (%s)'),
        'notDetected' => _translate('Below detection'),
        'history' => _translate('Test History'),
        'resultIn' => _translate('Result in %s days'),
        'resultInOne' => _translate('Result in 1 day'),
        'resultSameDay' => _translate('Result the same day'),
        'openFor' => _translate('Open for %s days since collection'),
        'openForMonths' => _translate('Open for %s months since collection'),
        'sampleCode' => _translate('Sample ID'),
        'sampleType' => _translate('Sample Type'),
        'facility' => _translate('Facility'),
        'lab' => _translate('Testing Lab'),
        'reason' => _translate('Reason'),
        'rejectionReason' => _translate('Rejection Reason'),
        'awaitingResult' => _translate('Awaiting result'),
        'printResult' => _translate('Print Result'),
        'noDownload' => _translate('Unable to generate download'),
        'outcomes' => [
            'good' => _translate('Normal / Suppressed / Negative'),
            'bad' => _translate('Needs Attention'),
            'warn' => _translate('Failed or Invalid'),
            'rejected' => _translate('Rejected'),
            'pending' => _translate('Pending'),
            'neutral' => _translate('Result'),
        ],
        'insights' => [
            'latestVl' => _translate('Latest Viral Load'),
            'suppressed' => _translate('Suppressed'),
            'notSuppressed' => _translate('Not Suppressed'),
            'monthsAgo' => _translate('%s months ago'),
            'suppressionRate' => _translate('%s of %s viral load results suppressed'),
            'vlOverdue' => _translate('No viral load result in the last %s months'),
            'consecutiveHigh' => _translate('Last two viral load results were at or above the threshold'),
            'rebound' => _translate('Viral load rose above the threshold after a suppressed result'),
            'resuppressed' => _translate('Viral load back below the threshold after a high result'),
            'latestCd4' => _translate('Latest CD4'),
            'cd4Low' => _translate('Latest CD4 below %s cells/mm³'),
            'eidPositive' => _translate('Early Infant Diagnosis result was positive on %s'),
            'pending' => _translate('%s samples awaiting a result, oldest collected %s'),
            'pendingOne' => _translate('1 sample awaiting a result, collected %s'),
            'rejected' => _translate('%s samples rejected'),
            'rejectedOne' => _translate('1 sample rejected'),
            'lastRejection' => _translate('most recently for: %s'),
            'conflict' => _translate('Records under this ID carry different %s: %s. Check that they belong to one patient.'),
            'names' => _translate('names'),
            'sexes' => _translate('sexes'),
            'dobs' => _translate('dates of birth'),
        ],
    ],
];
?>
<div class="pt-app" id="patientTimelineApp">
    <div class="pt-search">
        <form class="pt-search-form" autocomplete="off">
            <div class="pt-search-field">
                <em class="fa-solid fa-magnifying-glass" aria-hidden="true"></em>
                <input type="search" class="form-control pt-search-input" aria-label="<?= _htmlTranslate('Search patients'); ?>">
            </div>
            <button type="submit" class="btn btn-primary pt-search-button"></button>
        </form>
        <p class="pt-search-hint"></p>
    </div>
    <div class="pt-results" aria-live="polite"></div>
    <div class="pt-view" hidden></div>
</div>
<script>
    $(function() {
        ClinicReports.registerTab('patientTestHistoryFormReport', {
            init: function() {
                PatientTimeline.init($('#patientTimelineApp'), <?= json_encode($ptConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>);
            }
        });
    });
</script>
