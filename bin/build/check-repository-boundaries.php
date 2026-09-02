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
    'app/vl/reference/save-vl-sample-type-helper.php' => 'Reference\SampleTypeRepository',
    'app/cd4/reference/save-cd4-sample-type-helper.php' => 'Reference\SampleTypeRepository',
    'app/eid/reference/save-eid-sample-type-helper.php' => 'Reference\SampleTypeRepository',
    'app/hepatitis/reference/save-hepatitis-sample-type-helper.php' => 'Reference\SampleTypeRepository',
    'app/covid-19/reference/add-sample-type-helper.php' => 'Reference\SampleTypeRepository',
    'app/tb/reference/add-sample-type-helper.php' => 'Reference\SampleTypeRepository',
    'app/vl/reference/update-vl-sample-status.php' => 'Reference\SampleTypeRepository',
    'app/cd4/reference/update-cd4-sample-status.php' => 'Reference\SampleTypeRepository',
    'app/eid/reference/update-eid-sample-status.php' => 'Reference\SampleTypeRepository',
    'app/hepatitis/reference/update-hepatitis-sample-status.php' => 'Reference\SampleTypeRepository',
    'app/covid-19/reference/update-covid19-sample-status.php' => 'Reference\SampleTypeRepository',
    'app/tb/reference/update-tb-sample-type-status.php' => 'Reference\SampleTypeRepository',
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
