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
];

const RAW_VALUE_PATTERNS = [
    'concatenated directly after a string' => '/\.\s*\$_(POST|GET|REQUEST)\s*\[/',
    'concatenated after only a string cast' => '/\.\s*\(string\)\s*\$_(POST|GET|REQUEST)\s*\[/',
    'concatenated directly before a string' => '/\$_(POST|GET|REQUEST)\s*\[[^\]]+\]\s*\./',
    'interpolated inside a double-quoted string' => '/\{\$_(POST|GET|REQUEST)\s*\[/',
];

$violations = [];
$checked = 0;

foreach (COVERED_FILES as $name) {
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
        $line = preg_replace('/\((?:int|float)\)\s*\$_(POST|GET|REQUEST)\s*\[[^\]]*\]/', 'CAST_ENCODED', $line);
        foreach (RAW_VALUE_PATTERNS as $label => $pattern) {
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

echo "check-sql-value-safety: {$checked} report endpoints keep request values out of their SQL" . PHP_EOL;

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
