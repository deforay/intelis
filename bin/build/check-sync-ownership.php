<?php

declare(strict_types=1);

/**
 * Static check: the columns a lab owns cannot be overwritten by an incoming sync.
 *
 * requests-receiver.php pulls test requests down from the STS and applies them to
 * the local form tables. Some columns belong to the lab and to nobody else: what the
 * sample's status is, whether it was rejected, when it was reviewed, whether it still
 * needs syncing. The STS's copy of those is, by definition, older than the lab's.
 *
 * Each test type declares them in its own excludeUpdateKeys list, so there are six
 * near-identical blocks and one of them dropping a column would be invisible. The
 * effect would not be: a lab cancels a sample, the STS sends the request down again,
 * and the cancellation is quietly undone.
 *
 * The list below is not an opinion. It is the intersection of what all six blocks
 * already protect, so it holds today by construction and only fails when a block
 * starts protecting less than the others.
 *
 * Usage: php bin/build/check-sync-ownership.php
 */

const RECEIVER = __DIR__ . '/../../app/tasks/remote/requests-receiver.php';

/** Test types that must each declare an excludeUpdateKeys list. */
const TEST_TYPES = ['vl', 'eid', 'covid19', 'hepatitis', 'tb', 'cd4'];

/**
 * Columns the lab owns. An incoming update must never carry them.
 */
const LOCALLY_OWNED = [
    'data_sync',
    'is_sample_rejected',
    'lab_id',
    'last_modified_by',
    'last_modified_datetime',
    'result_dispatched_datetime',
    'result_printed_datetime',
    'result_printed_on_sts_datetime',
    'result_reviewed_by',
    'result_reviewed_datetime',
    'result_status',
    'sample_code',
    'sample_code_format',
    'sample_code_key',
];

/**
 * Read one bracketed list by label, starting the search at $from.
 *
 * Matched by counting brackets rather than by regex: each test type declares two
 * lists (removeKeys and excludeUpdateKeys) and a regex reading to the first ']'
 * silently reports on whichever it reached first. That mistake was made once
 * already, and it read as six failures on correct code.
 *
 * @return list<string>|null
 */
function listAfter(string $source, string $label, int $from): ?array
{
    $labelPos = strpos($source, "'$label' => [", $from);
    if ($labelPos === false) {
        return null;
    }

    $open = strpos($source, '[', $labelPos);
    $depth = 0;
    for ($i = $open; $i < strlen($source); $i++) {
        if ($source[$i] === '[') {
            $depth++;
        } elseif ($source[$i] === ']') {
            $depth--;
            if ($depth === 0) {
                preg_match_all("/'([a-z0-9_]+)'/", substr($source, $open, $i - $open), $matches);
                return $matches[1];
            }
        }
    }

    return null;
}

$source = file_get_contents(RECEIVER);
if ($source === false) {
    fwrite(STDERR, 'check-sync-ownership: could not read ' . RECEIVER . PHP_EOL);
    exit(1);
}

$problems = [];

foreach (TEST_TYPES as $testType) {
    $blockPos = preg_match("/^    '$testType' => \[/m", $source, $m, PREG_OFFSET_CAPTURE) === 1
        ? $m[0][1]
        : null;

    if ($blockPos === null) {
        $problems[] = "$testType: no config block found (was it renamed?)";
        continue;
    }

    $protected = listAfter($source, 'excludeUpdateKeys', $blockPos);
    if ($protected === null) {
        $problems[] = "$testType: no excludeUpdateKeys list";
        continue;
    }

    $missing = array_values(array_diff(LOCALLY_OWNED, $protected));
    if ($missing !== []) {
        $problems[] = "$testType: an incoming sync could overwrite " . implode(', ', $missing);
    }
}

$count = count(TEST_TYPES);
echo "check-sync-ownership: {$count} test types, " . count(LOCALLY_OWNED) . ' locally owned columns' . PHP_EOL;

if ($problems === []) {
    echo 'check-sync-ownership: every one of them is protected from an incoming update.' . PHP_EOL;
    exit(0);
}

echo PHP_EOL;
foreach ($problems as $problem) {
    echo "  $problem" . PHP_EOL;
}
echo PHP_EOL;
echo 'These columns belong to the lab. The STS copy of them is older by definition,' . PHP_EOL;
echo 'so an incoming request that carries one undoes work somebody did locally --' . PHP_EOL;
echo 'a cancelled sample coming back uncancelled, a rejection coming back accepted.' . PHP_EOL;
echo PHP_EOL;
echo 'Add the column to that test type\'s excludeUpdateKeys in' . PHP_EOL;
echo 'app/tasks/remote/requests-receiver.php.' . PHP_EOL;

exit(1);
