#!/usr/bin/env php
<?php

/**
 * bin/build/publish-release.php — tag the current version so labs can have it.
 *
 * Labs upgrade to the newest vN.N.N tag rather than to the tip of master, so a
 * merged change reaches nobody until it is tagged. That separation is the point:
 * it puts a deliberate act between "this is on master" and "every lab is running
 * it". This is that act, reduced to one command so the separation costs nothing.
 *
 * Deliberately does NOT bump the version. Bumping and publishing are different
 * decisions and belong at different moments — `composer version` writes the
 * number and its migration file, that goes through review and lands on master,
 * and only then is there a commit worth pointing a tag at. Doing both at once
 * would let a tag name code that never merged.
 *
 * What it checks before tagging, in order of how badly each would hurt:
 *
 *   - the version is not already tagged, so a release is never silently moved
 *   - the working tree is clean, so the tag cannot mean something the repository
 *     does not contain
 *   - HEAD is on the main branch and matches the remote, so the tag lands on
 *     what everyone else can see rather than on a local commit
 *   - app/system/version.php and composer.json agree, since they are what an
 *     instance reports and what preflight compares
 *
 * Usage:
 *   composer release            check everything, then tag and push
 *   composer release -- --dry   report what it would do and stop
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

// --- refuse to move a release that already exists ---------------------------

run('git rev-parse -q --verify refs/tags/' . escapeshellarg($tag), $code);
if ($code === 0) {
    fail(
        "{$tag} already exists locally.",
        'A published release is immutable — bump the version instead: composer version patch -- -y'
    );
}

$remoteTag = run('git ls-remote --tags origin ' . escapeshellarg('refs/tags/' . $tag));
if ($remoteTag !== '') {
    fail(
        "{$tag} is already published on origin.",
        'A published release is immutable — bump the version instead: composer version patch -- -y'
    );
}
ok("{$tag} is not yet published");

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
echo run('git log --oneline -8 ' . escapeshellarg($range) . ' | sed "s/^/    /"') . "\n\n";

if ($dry) {
    echo "  Dry run — nothing tagged. Remove --dry to publish.\n\n";
    exit(0);
}

// --- publish ---------------------------------------------------------------

$message = "Release {$version}";
run(sprintf('git tag -a %s -m %s', escapeshellarg($tag), escapeshellarg($message)), $tagCode);
if ($tagCode !== 0) {
    fail("Could not create tag {$tag}.");
}
ok("tagged {$tag}");

$pushOut = run('git push origin ' . escapeshellarg($tag), $pushCode);
if ($pushCode !== 0) {
    // Leave no half-published state: a local tag that origin never got is a
    // release nobody can install and a name that cannot be reused.
    run('git tag -d ' . escapeshellarg($tag));
    fail("Could not push {$tag} (local tag removed).", $pushOut);
}
ok("pushed {$tag} to origin");

echo "\n  {$tag} is published. Labs tracking releases will take it on their next";
echo "\n  upgrade; instances pinned with INTELIS_TRACK will not.\n\n";
exit(0);
