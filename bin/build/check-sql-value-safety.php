<?php

declare(strict_types=1);

/**
 * Static check: covered report endpoints never place a raw request value into SQL.
 *
 * These endpoints assemble their queries as strings, and their exports re-run the
 * assembled SQL from the session, so prepared statements are not on the table
 * without a much larger rework. The rule instead is that every request value is
 * encoded at the clause site: (int) casts for ids, $db->inIntList() for IN ()
 * lists, $db->escape() / $db->escapeLike() for text, or a whitelist lookup.
 *
 * All of those share one property this check can see: the concatenation dot (or
 * the interpolation brace) never touches $_POST directly. So a direct
 * `. $_POST[...]`, `$_POST[...] .`, or `{$_POST[...]}` in a covered file is a
 * value that skipped its encoder.
 *
 * This is a guardrail against the pattern creeping back in a copy-paste, not a
 * proof of safety: a value laundered through a plain variable first will not be
 * seen. Runs without a database or a container -- it reads source -- so CI can
 * run it on every push.
 *
 * Usage: php bin/build/check-sql-value-safety.php
 */

const REPO_DIR = __DIR__ . '/../..';

/**
 * Files the rule covers. Grown deliberately: a file is added here after it has
 * been swept, so the check locks in the sweep instead of flagging a backlog.
 *
 * @var list<string>
 */
const COVERED_FILES = [
    'app/vl/program-management/getHighVlResultDetails.php',
    'app/vl/program-management/getSampleRejectionReport.php',
    'app/vl/program-management/getResultNotAvailable.php',
    'app/vl/program-management/dataQualityCheck.php',
    'app/vl/program-management/getSampleTestingReport.php',
    'app/vl/program-management/getPatientTestHistoryReport.php',
    'app/vl/program-management/export-virologic-failure-report.php',
    'app/eid/management/get-data-export.php',
    'app/eid/management/getPositiveEidResultDetails.php',
    'app/eid/management/getSampleRejectionReport.php',
    'app/eid/management/getResultNotAvailable.php',
    'app/eid/management/dataQualityCheck.php',
    'app/eid/management/eid-sample-testing-report.php',
    'app/eid/management/getEidSampleTATDetails.php',
    'app/eid/management/getPatientTestHistoryReport.php',
    'app/eid/management/getEidMonthlyThresholdReport.php',
    'app/eid/management/get-rejected-samples.php',
    'app/eid/management/getPmtctCascadeReport.php',
    'app/eid/management/pmtctCascadeReportExport.php',
    'app/cd4/management/get-data-export.php',
    'app/cd4/management/get-positive-cd4-result-details.php',
    'app/cd4/management/get-sample-rejection-report.php',
    'app/cd4/management/get-result-not-available.php',
    'app/cd4/management/data-quality-check.php',
    'app/cd4/management/cd4-sample-testing-report.php',
    'app/cd4/management/get-cd4-sample-tat-details.php',
    'app/cd4/management/get-patient-test-history-report.php',
    'app/cd4/management/get-cd4-monthly-threshold-report.php',
    'app/cd4/management/get-rejected-samples.php',
    'app/cd4/management/get-sample-status.php',
    'app/generic-tests/program-management/get-data-export.php',
    'app/generic-tests/program-management/get-sample-tat-details.php',
    'app/generic-tests/program-management/get-rejection-result.php',
    'app/generic-tests/program-management/get-patient-test-history-report.php',
    'app/generic-tests/program-management/generic-tests-sample-testing-report.php',
    'app/generic-tests/program-management/get-sample-status.php',
    'app/tb/management/get-data-export.php',
    'app/tb/management/getPositiveTbResultDetails.php',
    'app/tb/management/getSampleRejectionReport.php',
    'app/tb/management/getResultNotAvailable.php',
    'app/tb/management/getTbSampleTATDetails.php',
    'app/tb/management/getPatientTestHistoryReport.php',
    'app/tb/management/get-rejected-samples.php',
    'app/tb/management/tb-sample-testing-report.php',
    'app/tb/management/dataQualityCheck.php',
    'app/tb/management/get-tb-monthly-threshold-report.php',
    'app/tb/management/getTbCascadeReport.php',
    'app/tb/management/getSampleStatus.php',
    'app/hepatitis/management/get-data-export.php',
    'app/hepatitis/management/get-positive-hepatitis-result-details.php',
    'app/hepatitis/management/get-sample-rejection-report.php',
    'app/hepatitis/management/get-result-not-available.php',
    'app/hepatitis/management/get-hepatitis-sample-tat-details.php',
    'app/hepatitis/management/get-patient-test-history-report.php',
    'app/hepatitis/management/get-rejected-samples.php',
    'app/hepatitis/management/hepatitis-sample-testing-report.php',
    'app/hepatitis/management/data-quality-check.php',
    'app/hepatitis/management/get-hepatitis-monthly-threshold-report.php',
    'app/hepatitis/management/get-sample-status.php',
    'app/covid-19/management/get-data-export.php',
    'app/covid-19/management/getPositiveCovid19ResultDetails.php',
    'app/covid-19/management/getSampleRejectionReport.php',
    'app/covid-19/management/getResultNotAvailable.php',
    'app/covid-19/management/getCovid19SampleTATDetails.php',
    'app/covid-19/management/getPatientTestHistoryReport.php',
    'app/covid-19/management/get-rejected-samples.php',
    'app/covid-19/management/covid-19-sample-testing-report.php',
    'app/covid-19/management/dataQualityCheck.php',
    'app/covid-19/management/getCovid19MonthlyThresholdReport.php',
    'app/covid-19/management/getSampleStatus.php',
    'app/batch/get-batches.php',
    'app/batch/get-samples-batch.php',
    'app/batch/getBatchCodeHelper.php',
    'app/specimen-referral-manifest/get-manifests.php',
    'app/specimen-referral-manifest/get-samples-for-manifest.php',
    'app/specimen-referral-manifest/get-manifest-package-code.php',
    'app/facilities/getFacilityDetails.php',
    'app/facilities/facilityExportInExcel.php',
    'app/dashboard/getVLTestResultStatusDetails.php',
    'app/admin/monitoring/get-sync-status-details.php',
    'app/admin/monitoring/get-api-sync-history-list.php',
    'app/admin/monitoring/get-samplewise-report.php',
    'app/vl/reference/get-vl-sample-type-helper.php',
    'app/cd4/reference/get-cd4-sample-type-helper.php',
    'app/eid/reference/get-eid-sample-type-helper.php',
    'app/hepatitis/reference/get-hepatitis-sample-type-helper.php',
    'app/covid-19/reference/getCovid19SampleTypeDetails.php',
    'app/tb/reference/getTbSampleTypeDetails.php',
    'app/vl/reference/get-vl-sample-rejection-reasons-helper.php',
    'app/cd4/reference/get-cd4-sample-rejection-reasons-helper.php',
    'app/eid/reference/get-eid-sample-rejection-reasons-helper.php',
    'app/hepatitis/reference/get-hepatitis-sample-rejection-reasons-helper.php',
    'app/covid-19/reference/getCovid19SampleRejectionDetails.php',
    'app/tb/reference/getTbSampleRejectionDetails.php',
];

/**
 * The InteLIS Mobile results endpoints. They read a JSON body into $input rather
 * than $_POST, so the same rule is checked against that name. Swept 2026-09-03:
 * text lists go through $db->escape(), id lists through $db->inIntList().
 *
 * @var list<string>
 */
const API_COVERED_FILES = [
    'app/api/v1.1/vl/fetch-results.php',
    'app/api/v1.1/vl/get-request.php',
    'app/api/v1.1/eid/fetch-results.php',
    'app/api/v1.1/eid/get-request.php',
    'app/api/v1.1/covid-19/fetch-results.php',
    'app/api/v1.1/covid-19/get-request.php',
    'app/api/v1.1/tb/fetch-results.php',
    'app/api/v1.1/tb/get-request.php',
    'app/api/v1.1/generic-tests/fetch-results.php',
    'app/api/v1.1/generic-tests/get-request.php',
];

/**
 * The API files copy request values into plain variables before the SQL line,
 * which the $input patterns above cannot see. These pin the encoder at the
 * point the value becomes SQL instead: a text list is only ever imploded
 * through $db->escape(), and the date and id values are never interpolated
 * bare. Keep the variable names in step with the endpoints.
 */
const API_LAUNDERED_PATTERNS = [
    'list imploded into SQL without $db->escape()' => '/implode\\("\x27,\x27",(?!\\s*array_map\\(\\$db->escape\\(\\.\\.\\.\\))/',
    'date bound interpolated without $db->escape()' => '/\x27\\$(from|to)\x27/',
    'id list interpolated without $db->inIntList()' => '/IN \\(\x27\\$(facilityId|sampleStatus)\x27\\)/',
];

const RAW_VALUE_PATTERNS = [
    'concatenated directly after a string' => '/\.\s*\$_(POST|GET|REQUEST)\s*\[/',
    'concatenated after only a string cast' => '/\.\s*\(string\)\s*\$_(POST|GET|REQUEST)\s*\[/',
    'concatenated directly before a string' => '/\$_(POST|GET|REQUEST)\s*\[[^\]]+\]\s*\./',
    'interpolated inside a double-quoted string' => '/\{\$_(POST|GET|REQUEST)\s*\[/',
];

$violations = [];
$checked = 0;

/**
 * @return array<string, string> label => pattern, for one request-value source
 */
function rawValuePatternsFor(string $source): array
{
    $patterns = [];
    foreach (RAW_VALUE_PATTERNS as $label => $pattern) {
        $patterns[$label] = str_replace('\\$_(POST|GET|REQUEST)', $source, $pattern);
    }
    return $patterns;
}

$covered = [];
foreach (COVERED_FILES as $name) {
    $covered[$name] = ['source' => '\\$_(POST|GET|REQUEST)', 'patterns' => RAW_VALUE_PATTERNS];
}
foreach (API_COVERED_FILES as $name) {
    $covered[$name] = ['source' => '\\$input', 'patterns' => rawValuePatternsFor('\\$input') + API_LAUNDERED_PATTERNS];
}

foreach ($covered as $name => $rule) {
    $path = REPO_DIR . '/' . $name;
    if (!is_file($path)) {
        $violations[] = [
            'where' => $name,
            'hint' => 'listed in COVERED_FILES but missing -- update the list if it moved',
        ];
        continue;
    }
    $checked++;
    $lines = file($path);
    foreach ($lines as $i => $line) {
        // An (int) or (float) cast is itself an encoder, so a cast access is
        // removed before matching -- the dot then touches the cast, not $_POST.
        $line = preg_replace('/\((?:int|float)\)\s*' . $rule['source'] . '\s*\[[^\]]*\]/', 'CAST_ENCODED', $line);
        foreach ($rule['patterns'] as $label => $pattern) {
            if (preg_match($pattern, $line)) {
                $violations[] = [
                    'where' => $name . ':' . ($i + 1),
                    'hint' => 'request value ' . $label . ': ' . trim($line),
                ];
                break;
            }
        }
    }
}

echo "check-sql-value-safety: {$checked} endpoints keep request values out of their SQL" . PHP_EOL;

if ($violations === []) {
    echo 'check-sql-value-safety: no raw request value reaches a query string.' . PHP_EOL;
    exit(0);
}

echo PHP_EOL;
foreach ($violations as $violation) {
    echo "  {$violation['where']}" . PHP_EOL;
    echo "      {$violation['hint']}" . PHP_EOL;
}
echo PHP_EOL;
echo 'A request value goes into SQL only through an encoder: (int) for a single id,' . PHP_EOL;
echo '$db->inIntList() for an IN () list, $db->escape() for text, $db->escapeLike()' . PHP_EOL;
echo 'for LIKE patterns, or a whitelist lookup for identifiers. The concatenation' . PHP_EOL;
echo 'dot must touch the encoder, never $_POST itself.' . PHP_EOL;

exit(1);
