<?php

declare(strict_types=1);

/**
 * Static check: the shared counting rules are applied together, everywhere.
 *
 * This codebase has been bitten twice by the same shape. Four places each wrote
 * their own answer to "is this sample rejected" and returned 412, 419, 421 and 425
 * for one question. Cancelled samples were then excluded one file at a time, and
 * single pages ended up disagreeing with themselves -- a chart excluding them while
 * the drill-down behind it did not.
 *
 * Both were found by hand, months later, by someone grepping. A grep is the wrong
 * tool: the eleven files fixed on 2026-08-27 assembled their WHERE clause in five
 * different shapes, and a regex written to find the gaps missed a real exclusion
 * written as `result_status != ' . CANCELLED` and nearly reported a correct file as
 * broken.
 *
 * So the rule is checked rather than remembered. Runs without a database or a
 * container -- it reads source -- so CI can run it on every push.
 *
 * Usage: php bin/build/check-count-invariants.php
 */

const APP_DIR = __DIR__ . '/../../app';

/**
 * Files that apply the rejection rule and deliberately do not apply the cancelled
 * rule. Each needs a reason, and the reason is the point: an entry here is a claim
 * somebody made on purpose, not a file nobody got to.
 *
 * @var array<string, string>
 */
/**
 * Report surfaces that aggregate over a form table and deliberately do not exclude
 * cancelled samples. Each needs a reason, checked by reading the file -- these six were
 * classified by hand on 2026-08-27 alongside the thirteen that did need fixing.
 *
 * @var array<string, string>
 */
const COUNT_RULE_EXEMPT = [
    'app/covid-19/management/getCovid19MonthlyThresholdReport.php' =>
        'requires result != "", and a cancelled sample carries no result',
    'app/eid/management/getEidMonthlyThresholdReport.php' =>
        'requires result != "", and a cancelled sample carries no result',
    'app/covid-19/management/getPositiveCovid19ResultDetails.php' =>
        'pinned to result_status = ACCEPTED, which cancelled cannot be',
    'app/eid/management/getPositiveEidResultDetails.php' =>
        'pinned to result_status = ACCEPTED, which cancelled cannot be',
    'app/eid/management/getPatientTestHistoryReport.php' =>
        'pinned to result_status = ACCEPTED, which cancelled cannot be',
    'app/tb/management/getTbCascadeReport.php' =>
        'excludes cancelled by hand, in a two-status NOT IN that a later step '
        . 'string-matches in order to strip it for one sub-report -- switching to the '
        . 'shared clause would break that matching, so this one is left as it is',
    'app/generic-tests/program-management/get-sample-status.php' =>
        'a mutually-exclusive status breakdown that has to sum to its own total, like '
        . 'the dashboard -- excluding one status would stop the parts summing',
];

const CANCELLED_RULE_EXEMPT = [
    // The dashboard counts a mutually-exclusive status breakdown that has to sum to
    // the total, so it asks "how many are IN the Rejected state", not "how many were
    // ever rejected". Excluding cancelled there would make the parts stop summing.
    'app/classes/Services/SampleFlowService.php' =>
        'places every registered sample in exactly one stage or exit, and cancelled is '
        . 'one of the exits, so the stages have to add up to everything registered; the '
        . 'CASE tests cancelled before rejected, so a cancelled sample can never be '
        . 'counted as rejected, which is what the shared clause exists to guarantee',
];

/** @return list<string> */
function phpFilesIn(string $dir): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
    sort($files);
    return $files;
}

function relative(string $path): string
{
    $root = realpath(__DIR__ . '/../..') . DIRECTORY_SEPARATOR;
    return str_replace($root, '', (string) realpath($path));
}

$violations = [];
$checked = 0;
$exempt = 0;

foreach (phpFilesIn(APP_DIR) as $path) {
    $source = file_get_contents($path);
    if ($source === false || !str_contains($source, 'SampleRejectionUtility::sqlPredicate')) {
        continue;
    }

    $checked++;
    $name = relative($path);

    if (array_key_exists($name, CANCELLED_RULE_EXEMPT)) {
        $exempt++;
        continue;
    }

    if (str_contains($source, 'SampleCountUtility::')) {
        continue;
    }

    // A hand-written exclusion is not accepted even when it is correct. One
    // definition is the whole point, and a hand-written one is what drifts.
    $handWritten = preg_match('/result_status\s*(!=|<>)[^;]{0,20}(CANCELLED|\b12\b)/', $source) === 1;

    $violations[] = [
        'file' => $name,
        'hint' => $handWritten
            ? 'excludes cancelled by hand -- replace it with SampleCountUtility::countableWhere()'
            : 'applies the rejection rule but never excludes cancelled samples',
    ];
}

// --- second rule: a report that counts samples excludes cancelled ones -------
//
// Scoped to the report directories rather than all of app/. That is the population
// actually read and classified; a broader trigger catches 35 files, most of which are
// not report surfaces at all, and an exemption list written by guesswork would either
// cry wolf or hide a real gap.
$countChecked = 0;
$countExempt = 0;

foreach (phpFilesIn(APP_DIR) as $path) {
    $name = relative($path);
    if (!str_contains($name, '/management/') && !str_contains($name, '/program-management/')) {
        continue;
    }

    $source = file_get_contents($path);
    if ($source === false) {
        continue;
    }
    if (preg_match('/form_(vl|eid|covid19|tb|hepatitis|generic|cd4)/', $source) !== 1) {
        continue;
    }
    if (preg_match('/COUNT\(|SUM\(|GROUP BY|group by/', $source) !== 1) {
        continue;
    }

    $countChecked++;

    if (array_key_exists($name, COUNT_RULE_EXEMPT)) {
        $countExempt++;
        continue;
    }
    if (str_contains($source, 'SampleCountUtility::')) {
        continue;
    }

    $violations[] = [
        'file' => $name,
        'hint' => 'counts samples from a form table but never excludes cancelled ones',
    ];
}

echo "check-count-invariants: {$countChecked} report surfaces count samples";
echo $countExempt > 0 ? ", {$countExempt} exempt by declaration" : '';
echo PHP_EOL;
echo "check-count-invariants: {$checked} files apply the rejection rule";
echo $exempt > 0 ? ", {$exempt} exempt by declaration" : '';
echo PHP_EOL;

if ($violations === []) {
    echo 'check-count-invariants: every one of them also excludes cancelled samples.' . PHP_EOL;
    exit(0);
}

echo PHP_EOL;
foreach ($violations as $violation) {
    echo "  {$violation['file']}" . PHP_EOL;
    echo "      {$violation['hint']}" . PHP_EOL;
}
echo PHP_EOL;
echo 'A sample counts as rejected when the flag or the status says so' . PHP_EOL;
echo '(SampleRejectionUtility). A cancelled sample was called off before testing and' . PHP_EOL;
echo 'counts nowhere (SampleCountUtility::countableWhere). A surface that applies the' . PHP_EOL;
echo 'first rule without the second disagrees with every surface that applies both.' . PHP_EOL;
echo PHP_EOL;
echo 'Add the clause where the file assembles its WHERE, or add the file to' . PHP_EOL;
echo 'CANCELLED_RULE_EXEMPT in this script with the reason it is different.' . PHP_EOL;

exit(1);
