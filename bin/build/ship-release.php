<?php

// ship-release.php — take what is on master and publish it, in one command.
//
//   composer ship                 bump the patch level if the current version
//                                 is already tagged, then publish
//   composer ship -- minor        bump the minor level instead
//   composer ship -- 5.8.0        go to an exact version
//   composer ship -- --dry        say what would happen, change nothing
//
// This exists because publishing was six steps, one of which only announced
// itself as an error:
//
//   composer publish              -> "v5.7.11 already exists, bump instead"
//   composer version patch -- -y
//   composer update --lock        -> forgotten, and the vendor build then
//                                    fails on a stale content-hash
//   git add <five files>
//   git commit
//   git push
//   composer publish
//
// None of those steps carried a decision. The one decision worth making —
// whether this work should reach labs at all — is made by choosing to run
// this, and that decision is kept: nothing here tags anything that is not
// already pushed to origin/master.
//
// The pieces are not reimplemented. generate-version.php still owns bumping
// and publish-release.php still owns every safety check around tagging; this
// only decides whether a bump is needed and puts the sequence in one place.

require_once __DIR__ . '/../../bootstrap.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

$root = ROOT_PATH;

$args = array_slice($argv, 1);
$dry  = in_array('--dry', $args, true) || in_array('-n', $args, true);
$bumpArg = null;
foreach ($args as $arg) {
    if ($arg === '--dry' || $arg === '-n' || $arg === '-y' || $arg === '--yes') {
        continue;
    }
    if ($arg === '--help' || $arg === '-h') {
        echo "\n  composer ship [patch|minor|major|X.Y.Z] [--dry]\n\n";
        echo "  Bumps the version if the current one is already tagged, refreshes\n";
        echo "  composer.lock, commits, pushes, then tags and pushes the tag.\n\n";
        exit(0);
    }
    if ($bumpArg !== null) {
        fwrite(STDERR, "Too many arguments. Pass one of: patch, minor, major, X.Y.Z\n");
        exit(1);
    }
    $bumpArg = $arg;
}
$bumpArg ??= 'patch';

function shipRun(string $cmd, ?int &$code = null): string
{
    $output = [];
    exec($cmd . ' 2>&1', $output, $code);
    return implode("\n", $output);
}

function shipFail(string $message, string $detail = ''): never
{
    echo "\n  \u{2717} {$message}\n";
    if ($detail !== '') {
        foreach (explode("\n", rtrim($detail)) as $line) {
            echo "    {$line}\n";
        }
    }
    echo "\n";
    exit(1);
}

function shipOk(string $message): void
{
    echo "  \u{2713} {$message}\n";
}

function shipStep(string $message): void
{
    echo "  \u{2192} {$message}\n";
}

/** The version composer.json currently names. */
function shipCurrentVersion(string $root): string
{
    $data = json_decode((string) file_get_contents($root . '/composer.json'), true, flags: JSON_THROW_ON_ERROR);
    $version = (string) ($data['version'] ?? '');
    if ($version === '') {
        shipFail('composer.json declares no version.');
    }
    return $version;
}

echo "\n";

// --- the state this can run from -------------------------------------------
//
// These are checked before anything is written, so a run that cannot finish
// stops without having bumped a version that then has to be un-bumped by hand.

$branch = trim(shipRun('git -C ' . escapeshellarg($root) . ' rev-parse --abbrev-ref HEAD'));
if ($branch !== 'master') {
    shipFail("On branch {$branch}, not master.", 'Releases are cut from master.');
}

$dirty = trim(shipRun('git -C ' . escapeshellarg($root) . ' status --porcelain'));
if ($dirty !== '') {
    shipFail(
        'Working tree is not clean.',
        "Commit or stash first — a release must name a commit, and these\nchanges are in no commit:\n\n" . $dirty
    );
}

shipRun('git -C ' . escapeshellarg($root) . ' fetch origin --tags --quiet', $fetchCode);
if ($fetchCode !== 0) {
    shipFail('Could not reach origin.', 'A release has to be pushed, so this stops here rather than tagging locally.');
}

$head   = trim(shipRun('git -C ' . escapeshellarg($root) . ' rev-parse HEAD'));
$behind = trim(shipRun('git -C ' . escapeshellarg($root) . ' rev-list --count HEAD..origin/master'));
if ($behind !== '' && (int) $behind > 0) {
    shipFail(
        "master is {$behind} commit(s) behind origin.",
        'Pull first, so the release names what everyone else has too.'
    );
}

// --- does this version still need a number? --------------------------------
//
// A version that is already tagged has been published, and a published release
// is immutable, so shipping again has to mean a new number. A version that is
// not tagged is one somebody already bumped by hand and never published — that
// is the number to use, not the one after it.

$version = shipCurrentVersion($root);
$tagged  = trim(shipRun(
    'git -C ' . escapeshellarg($root) . ' tag --list ' . escapeshellarg('v' . $version)
)) !== '';

if (!$tagged) {
    shipOk("v{$version} is not tagged yet — publishing it as it stands");
} else {
    shipStep("v{$version} is already published, so this needs a new number");

    if ($dry) {
        shipOk("would bump ({$bumpArg}) and publish");
        echo "\n  Dry run — nothing changed.\n\n";
        exit(0);
    }

    $bumpOut = shipRun(sprintf(
        'cd %s && php bin/build/generate-version.php %s -y',
        escapeshellarg($root),
        escapeshellarg($bumpArg)
    ), $bumpCode);
    if ($bumpCode !== 0) {
        shipFail('Version bump failed.', $bumpOut);
    }

    $version = shipCurrentVersion($root);
    shipOk("bumped to {$version}");

    // The lockfile carries a hash of composer.json, so bumping the version in
    // one without refreshing the other leaves a lockfile that no longer
    // describes it — which the vendor-package build catches and nobody else
    // does. Doing it here is the whole reason it stops being forgettable.
    $lockOut = shipRun('cd ' . escapeshellarg($root) . ' && composer update --lock --no-interaction', $lockCode);
    if ($lockCode !== 0) {
        shipFail('composer update --lock failed.', $lockOut);
    }
    shipOk('composer.lock refreshed');

    shipRun('git -C ' . escapeshellarg($root) . ' add -A ' . escapeshellarg($root . '/composer.json')
        . ' ' . escapeshellarg($root . '/composer.lock')
        . ' ' . escapeshellarg($root . '/app/system/version.php')
        . ' ' . escapeshellarg($root . '/sys/migrations'), $addCode);
    if ($addCode !== 0) {
        shipFail('Could not stage the version bump.');
    }

    $staged = trim(shipRun('git -C ' . escapeshellarg($root) . ' diff --cached --name-only'));
    if ($staged === '') {
        shipFail('The bump produced no changes to commit.');
    }

    $commitOut = shipRun(sprintf(
        'git -C %s commit -m %s',
        escapeshellarg($root),
        escapeshellarg("chore: bump version to {$version}")
    ), $commitCode);
    if ($commitCode !== 0) {
        shipFail('Could not commit the version bump.', $commitOut);
    }
    shipOk("committed the bump to {$version}");
}

// --- get it to origin ------------------------------------------------------

$ahead = (int) trim(shipRun('git -C ' . escapeshellarg($root) . ' rev-list --count origin/master..HEAD'));
if ($ahead > 0) {
    if ($dry) {
        shipOk("would push {$ahead} commit(s) to origin/master");
    } else {
        $pushOut = shipRun('git -C ' . escapeshellarg($root) . ' push origin master', $pushCode);
        if ($pushCode !== 0) {
            shipFail('Could not push master.', $pushOut);
        }
        shipOk("pushed {$ahead} commit(s) to origin/master");
    }
} else {
    shipOk('origin/master is already up to date');
}

// --- and publish it --------------------------------------------------------
//
// publish-release.php re-checks everything above from scratch. That is
// deliberate: it is the command people run on its own, so it cannot assume it
// was reached through this one.

echo "\n";
$publishCmd = 'cd ' . escapeshellarg($root) . ' && php bin/build/publish-release.php' . ($dry ? ' --dry' : '');
passthru($publishCmd, $publishCode);
exit($publishCode);
