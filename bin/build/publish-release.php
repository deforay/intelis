#!/usr/bin/env php
<?php

/**
 * bin/build/publish-release.php — tag the current version.
 *
 * Delivery does not run through here any more. Labs upgrade to the `stable`
 * branch, which CI fast-forwards to any commit on master that passed Verify, so
 * a fix reaches installations without anybody tagging anything. What a tag names
 * now is the version — the schema/feature level that sc_version and preflight
 * compare — which is what the number was always supposed to mean. Forgetting to
 * tag therefore costs an accurate version number rather than everybody's fixes.
 *
 * Deliberately does NOT bump the version. Bumping and publishing are different
 * decisions and belong at different moments — `composer version` writes the
 * number and its migration file, that goes through review and lands on master,
 * and only then is there a commit worth pointing a tag at. Doing both at once
 * would let a tag name code that never merged.
 *
 * What it checks before tagging, in order of how badly each would hurt:
 *
 *   - the version does not already name a different commit, so a release is
 *     never silently moved (already naming this one is not an error: it means
 *     the work is done, and running this again says so and stops)
 *   - the working tree is clean, so the tag cannot mean something the repository
 *     does not contain
 *   - HEAD is on the main branch and matches the remote, so the tag lands on
 *     what everyone else can see rather than on a local commit
 *   - no pull request has auto-merge armed, so the tag cannot exclude work that
 *     is about to land on its own (see the check itself for why this is the one
 *     kind of open pull request worth blocking on)
 *   - app/system/version.php and composer.json agree, since they are what an
 *     instance reports and what preflight compares
 *
 * Usage:
 *   composer publish            check everything, then tag and push
 *   composer publish -- --dry   report what it would do and stop
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$dry = in_array('--dry', $argv, true) || in_array('-n', $argv, true);

function run(string $cmd, ?int &$code = null): string
{
    $out = [];
    exec($cmd . ' 2>&1', $out, $code);
    return trim(implode("\n", $out));
}

function fail(string $message, string $fix = ''): never
{
    fwrite(STDERR, "\n  ✗ {$message}\n");
    if ($fix !== '') {
        fwrite(STDERR, "    {$fix}\n");
    }
    fwrite(STDERR, "\n");
    exit(1);
}

function ok(string $message): void
{
    echo "  ✓ {$message}\n";
}

chdir($root);

// --- the version this release would be -------------------------------------

require_once $root . '/app/system/version.php';
$version = defined('VERSION') ? (string) constant('VERSION') : '';

if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) {
    fail("app/system/version.php does not define a plain X.Y.Z version (got '{$version}').");
}

$composerJson = json_decode((string) file_get_contents($root . '/composer.json'), true);
$composerVersion = (string) ($composerJson['version'] ?? '');

// These two are bumped together by `composer version`, and preflight reports it
// as a finding when they drift. A release is the wrong moment to discover it.
if ($composerVersion !== $version) {
    fail(
        "composer.json says {$composerVersion} but version.php says {$version}.",
        'Run: composer version <patch|minor|major> -- -y'
    );
}

$tag = 'v' . $version;
echo "\n  Publishing {$tag}\n\n";

// --- what, if anything, this version already names --------------------------
//
// A tag with the right name is not the same fact as a release being published,
// and the difference decides whether running this again is a mistake:
//
//   naming this commit    the work is done; saying so and stopping is the
//                         honest answer, not an error
//   naming another commit a release is being asked to move, which is the one
//                         thing that must never happen quietly
//
// Telling them apart is what makes a second run safe to attempt. Treating both
// as failure was worse than noisy — it advised bumping the version to escape a
// state where nothing was wrong.

$head = run('git rev-parse HEAD');

$localTagCommit = '';
run('git rev-parse -q --verify refs/tags/' . escapeshellarg($tag), $code);
if ($code === 0) {
    $localTagCommit = run('git rev-parse ' . escapeshellarg($tag . '^{commit}'));
}

// ls-remote prints an annotated tag twice: the tag object, then a '^{}' line
// carrying the commit it points at. The second is the one worth comparing.
$remoteTagCommit = '';
foreach (explode("\n", run('git ls-remote --tags origin ' . escapeshellarg('refs/tags/' . $tag . '*'))) as $line) {
    if (preg_match('/^([0-9a-f]{40})\s+refs\/tags\/' . preg_quote($tag, '/') . '(\^\{\})?$/', trim($line), $m)) {
        if ($remoteTagCommit === '' || isset($m[2])) {
            $remoteTagCommit = $m[1];
        }
    }
}

foreach ([[$localTagCommit, 'locally'], [$remoteTagCommit, 'on origin']] as [$existing, $where]) {
    if ($existing !== '' && $existing !== $head) {
        fail(
            "{$tag} already exists {$where}, naming " . substr($existing, 0, 8) . ' rather than HEAD.',
            'A published release is immutable — bump the version instead: composer version patch -- -y'
        );
    }
}

if ($remoteTagCommit === $head && $head !== '') {
    echo "  ✓ {$tag} is already published at " . substr($head, 0, 8) . "\n";
    echo "\n  Nothing to do.\n\n";
    exit(0);
}

if ($localTagCommit === $head && $head !== '') {
    // Tagged but never pushed — the tail of a run that failed at the push. The
    // tag is correct, so finish it rather than asking for a version nobody needs.
    ok("{$tag} is tagged locally but not on origin — will push it");
} else {
    ok("{$tag} is not yet published");
}

// --- the tag must mean what the repository contains -------------------------

if (run('git status --porcelain') !== '') {
    fail(
        'The working tree has uncommitted changes.',
        'A tag would name a commit that does not include them. Commit or stash first.'
    );
}
ok('working tree is clean');

$branch = run('git rev-parse --abbrev-ref HEAD');
$mainBranch = run('git symbolic-ref -q --short refs/remotes/origin/HEAD');
$mainBranch = $mainBranch !== '' ? preg_replace('#^origin/#', '', $mainBranch) : 'master';

if ($branch !== $mainBranch) {
    fail(
        "On branch '{$branch}', not '{$mainBranch}'.",
        "Release from {$mainBranch}, so the tag names what everyone else has."
    );
}
ok("on {$mainBranch}");

run('git fetch -q origin ' . escapeshellarg($mainBranch), $fetchCode);
if ($fetchCode !== 0) {
    fail('Could not reach origin to compare.', 'Check the network and try again.');
}

$local = run('git rev-parse HEAD');
$remote = run('git rev-parse ' . escapeshellarg('origin/' . $mainBranch));

if ($local !== $remote) {
    $ahead = (int) run('git rev-list --count ' . escapeshellarg("origin/{$mainBranch}..HEAD"));
    $behind = (int) run('git rev-list --count ' . escapeshellarg("HEAD..origin/{$mainBranch}"));
    fail(
        "Local {$mainBranch} differs from origin ({$ahead} ahead, {$behind} behind).",
        'Push or pull first — a tag on an unpushed commit publishes nothing.'
    );
}
ok("{$mainBranch} matches origin (" . substr($local, 0, 8) . ')');

// --- nothing may be queued to land after this tag ---------------------------

// `git land` has two exit states. When the repository allows auto-merge and a
// check is still running, it arms the merge and returns immediately, leaving
// the work on a branch and the main branch rewound. Every check above still
// passes -- clean tree, on the main branch, matching origin -- and the tag
// would name a release that silently excludes the very change being released.
//
// Only pull requests with auto-merge armed count. Those are going to land on
// their own, without anyone deciding again. One left open on purpose is not in
// flight, and blocking a release on it would make this check something people
// learn to route around.
//
// gh is optional tooling: missing, unauthenticated or offline, this check is
// skipped rather than failing a release that is very probably fine.
$queued = run(
    'gh pr list --base ' . escapeshellarg($mainBranch) . ' --state open'
        . ' --json number,title,autoMergeRequest'
        . ' --jq \'.[] | select(.autoMergeRequest != null) | "#\(.number) \(.title)"\'',
    $ghCode
);

if ($ghCode !== 0) {
    echo "  · skipped the queued-merge check (gh unavailable)\n";
} elseif ($queued !== '') {
    fail(
        'A pull request has auto-merge armed but has not landed yet.',
        "This tag would exclude it. Wait for it to merge, pull, then publish:\n\n"
            . preg_replace('/^/m', '    ', $queued)
    );
} else {
    ok('nothing is queued to land');
}

// --- what this release contains --------------------------------------------

$previous = run("git tag --list 'v[0-9]*' --sort=-v:refname | head -1");
$range = $previous !== '' ? "{$previous}..HEAD" : 'HEAD';
$commitCount = (int) run('git rev-list --count ' . escapeshellarg($range));

echo "\n";
if ($previous !== '') {
    echo "  {$commitCount} commit(s) since {$previous}:\n";
} else {
    echo "  First release. Recent commits:\n";
}
// Indent in PHP rather than piping through sed: run() trims what it captures,
// which strips the indent from the first line only and leaves the block looking
// ragged.
$log = run('git log --oneline -8 ' . escapeshellarg($range));
foreach (explode("\n", $log) as $line) {
    if ($line !== '') {
        echo '    ' . $line . "\n";
    }
}
echo "\n";

if ($dry) {
    echo "  Dry run — nothing tagged. Remove --dry to publish.\n\n";
    exit(0);
}

// --- publish ---------------------------------------------------------------

$createdTag = false;
if ($localTagCommit !== $head) {
    run(sprintf('git tag -a %s -m %s', escapeshellarg($tag), escapeshellarg("Release {$version}")), $tagCode);
    if ($tagCode !== 0) {
        fail("Could not create tag {$tag}.");
    }
    $createdTag = true;
    ok("tagged {$tag}");
}

$pushOut = run('git push origin ' . escapeshellarg($tag), $pushCode);
if ($pushCode !== 0) {
    // Leave no half-published state: a local tag that origin never got is a
    // release nobody can install and a name that cannot be reused. Only undo a
    // tag this run made — one that was already here is a previous run's attempt
    // to publish this same commit, and deleting it would throw away the state
    // that lets the next run pick up where this one stopped.
    if ($createdTag) {
        run('git tag -d ' . escapeshellarg($tag));
        fail("Could not push {$tag} (local tag removed).", $pushOut);
    }
    fail("Could not push {$tag}.", $pushOut);
}
ok("pushed {$tag} to origin");

echo "\n  {$tag} is published. Labs tracking releases will take it on their next";
echo "\n  upgrade; instances pinned with INTELIS_TRACK will not.\n\n";
exit(0);
