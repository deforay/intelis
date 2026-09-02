<?php

declare(strict_types=1);

/**
 * Static check: endpoints whose writes moved into a repository stay that way.
 *
 * Each entry in COVERED_FILES had its table writes extracted into a class under
 * App\Repositories. The endpoint keeps request parsing, session messages,
 * activity logging, and redirects; the repository is the only thing that
 * touches the table. A direct write call reappearing in a covered file is a
 * copy-paste regression -- the pattern these endpoints came from is cloned
 * across modules, so it creeps back easily.
 *
 * Like check-sql-value-safety, this reads source without a database or a
 * container, so CI runs it on every push. And like that check, it is a
 * guardrail, not a proof: only listed files are checked.
 *
 * Usage: php bin/build/check-repository-boundaries.php
 */

const REPO_DIR = __DIR__ . '/../..';

/**
 * Files whose writes live in a repository. Grown deliberately: a file is added
 * here when its extraction ships, so the check locks in the move.
 *
 * @var array<string, string> file => the repository that owns its writes
 */
const COVERED_FILES = [
    'app/vl/reference/save-vl-sample-type-helper.php' => 'Reference\ReferenceDataRepository',
    'app/cd4/reference/save-cd4-sample-type-helper.php' => 'Reference\ReferenceDataRepository',
    'app/eid/reference/save-eid-sample-type-helper.php' => 'Reference\ReferenceDataRepository',
    'app/hepatitis/reference/save-hepatitis-sample-type-helper.php' => 'Reference\ReferenceDataRepository',
    'app/covid-19/reference/add-sample-type-helper.php' => 'Reference\ReferenceDataRepository',
    'app/tb/reference/add-sample-type-helper.php' => 'Reference\ReferenceDataRepository',
    'app/vl/reference/update-vl-sample-status.php' => 'Reference\ReferenceDataRepository',
    'app/cd4/reference/update-cd4-sample-status.php' => 'Reference\ReferenceDataRepository',
    'app/eid/reference/update-eid-sample-status.php' => 'Reference\ReferenceDataRepository',
    'app/hepatitis/reference/update-hepatitis-sample-status.php' => 'Reference\ReferenceDataRepository',
    'app/covid-19/reference/update-covid19-sample-status.php' => 'Reference\ReferenceDataRepository',
    'app/tb/reference/update-tb-sample-type-status.php' => 'Reference\ReferenceDataRepository',
    'app/vl/reference/save-vl-sample-rejection-reasons-helper.php' => 'Reference\ReferenceDataRepository',
    'app/cd4/reference/save-cd4-sample-rejection-reasons-helper.php' => 'Reference\ReferenceDataRepository',
    'app/eid/reference/save-eid-sample-rejection-reasons-helper.php' => 'Reference\ReferenceDataRepository',
    'app/hepatitis/reference/save-hepatitis-sample-rejection-reasons-helper.php' => 'Reference\ReferenceDataRepository',
    'app/covid-19/reference/add-rejection-reason-helper.php' => 'Reference\ReferenceDataRepository',
    'app/tb/reference/add-rejection-reason-helper.php' => 'Reference\ReferenceDataRepository',
    'app/vl/reference/update-vl-rejection-status.php' => 'Reference\ReferenceDataRepository',
    'app/cd4/reference/update-cd4-rejection-status.php' => 'Reference\ReferenceDataRepository',
    'app/eid/reference/update-eid-sample-rejection-status.php' => 'Reference\ReferenceDataRepository',
    'app/hepatitis/reference/update-hepatitis-rejection-status.php' => 'Reference\ReferenceDataRepository',
    'app/covid-19/reference/update-covid19-rejection-status.php' => 'Reference\ReferenceDataRepository',
    'app/tb/reference/update-tb-rejection-status.php' => 'Reference\ReferenceDataRepository',
    'app/vl/reference/save-vl-test-reasons-helper.php' => 'Reference\ReferenceDataRepository',
    'app/cd4/reference/save-cd4-test-reasons-helper.php' => 'Reference\ReferenceDataRepository',
    'app/eid/reference/save-eid-test-reasons-helper.php' => 'Reference\ReferenceDataRepository',
    'app/hepatitis/reference/save-hepatitis-test-reasons-helper.php' => 'Reference\ReferenceDataRepository',
    'app/covid-19/reference/add-test-reasons-helper.php' => 'Reference\ReferenceDataRepository',
    'app/tb/reference/add-test-reasons-helper.php' => 'Reference\ReferenceDataRepository',
    'app/tb/reference/edit-test-reasons-helper.php' => 'Reference\ReferenceDataRepository',
    'app/vl/reference/update-vl-test-reason-status.php' => 'Reference\ReferenceDataRepository',
    'app/cd4/reference/update-cd4-test-reason-status.php' => 'Reference\ReferenceDataRepository',
    'app/eid/reference/update-eid-test-reason-status.php' => 'Reference\ReferenceDataRepository',
    'app/hepatitis/reference/update-hepatitis-test-reason-status.php' => 'Reference\ReferenceDataRepository',
    'app/covid-19/reference/update-covid19-test-reason-status.php' => 'Reference\ReferenceDataRepository',
    'app/tb/reference/update-tb-test-reason-status.php' => 'Reference\ReferenceDataRepository',
    'app/vl/reference/save-vl-test-failure-reason-helper.php' => 'Reference\ReferenceDataRepository',
    'app/vl/reference/save-vl-results-helper.php' => 'Reference\ReferenceDataRepository',
    'app/vl/reference/save-vl-art-code-details-helper.php' => 'Reference\ReferenceDataRepository',
    'app/vl/reference/update-vl-test-failure-reason-status.php' => 'Reference\ReferenceDataRepository',
    'app/vl/reference/update-vl-result-status.php' => 'Reference\ReferenceDataRepository',
    'app/vl/reference/update-vl-art-code-status.php' => 'Reference\ReferenceDataRepository',
];

/**
 * A covered endpoint has no business calling any of these on a database
 * handle; that is the repository's job.
 */
const WRITE_CALL_PATTERN = '/->\s*(insert|insertMulti|update|upsert|replace|delete|rawQuery|rawQueryOne|rawQueryValue|query)\s*\(/';

$violations = [];
$checked = 0;

foreach (COVERED_FILES as $name => $repository) {
    $path = REPO_DIR . '/' . $name;
    if (!is_file($path)) {
        $violations[] = [
            'where' => $name,
            'hint' => 'listed in COVERED_FILES but missing -- update the list if it moved',
        ];
        continue;
    }
    $checked++;
    foreach (file($path) as $i => $line) {
        if (preg_match(WRITE_CALL_PATTERN, $line)) {
            $violations[] = [
                'where' => $name . ':' . ($i + 1),
                'hint' => 'database call outside its repository (' . $repository . '): ' . trim($line),
            ];
        }
    }
}

echo "check-repository-boundaries: {$checked} endpoints keep their writes in a repository" . PHP_EOL;

if ($violations === []) {
    echo 'check-repository-boundaries: no covered endpoint touches the database directly.' . PHP_EOL;
    exit(0);
}

echo PHP_EOL;
foreach ($violations as $violation) {
    echo "  {$violation['where']}" . PHP_EOL;
    echo "      {$violation['hint']}" . PHP_EOL;
}
echo PHP_EOL;
echo 'These endpoints moved their table writes into a repository class under' . PHP_EOL;
echo 'app/classes/Repositories. Add the write there (or a new typed method), and' . PHP_EOL;
echo 'keep the endpoint to request parsing, messages, logging, and redirects.' . PHP_EOL;

exit(1);
