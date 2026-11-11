<?php

declare(strict_types=1);

/**
 * Static check: every operator script loads the shared-functions.sh it was
 * written for.
 *
 * setup.sh, upgrade.sh and the other scripts in scripts/ each download
 * shared-functions.sh when they start, and the intelis command downloads the
 * newest upgrade.sh. Three things keep the pair in step, and
 * this check holds all three:
 *
 *   1. The intelis-loader block is byte-identical in every script that has it.
 *      It resolves the commit both files come from, so they always match.
 *   2. shared-functions.sh declares SHARED_FN_VERSION, and every script that
 *      loads it calls intelis_require_shared_fn_version with that same number.
 *      A machine left with an older copy then stops before changing anything.
 *   3. A change to shared-functions.sh raises SHARED_FN_VERSION. Without that,
 *      rule 2 cannot tell an old copy from a new one.
 *
 * To raise the version everywhere: php bin/dev/bump-shared-fn-version.php
 *
 * Usage: php bin/build/check-shell-loaders.php
 * The base for rule 3 is $SHIP_BASE when git-ship sets it, otherwise the merge
 * base with origin/master. With neither, rule 3 is skipped.
 */

const SHARED = 'scripts/shared-functions.sh';
const BLOCK_START = '# >>> intelis-loader';
const BLOCK_END = '# <<< intelis-loader';

/**
 * Scripts that load shared-functions.sh but must not stop on an old copy.
 * The doctor exists to diagnose a broken machine, so it keeps working with
 * whatever copy is there and falls back to its own helpers.
 */
const GATE_EXEMPT = [
    'scripts/intelis-doctor.sh',
];

$root = dirname(__DIR__, 2);
chdir($root);
$problems = [];

$shared = (string) file_get_contents(SHARED);
if (!preg_match('/^SHARED_FN_VERSION=(\d+)$/m', $shared, $m)) {
    fwrite(STDERR, "check-shell-loaders: " . SHARED . " does not declare SHARED_FN_VERSION=<number>.\n");
    exit(1);
}
$version = (int) $m[1];

$canonical = null;
$canonicalFile = null;
foreach (array_merge(glob('scripts/*.sh'), ['intelis']) as $file) {
    if ($file === SHARED) {
        continue;
    }
    $text = (string) file_get_contents($file);
    $loads = (bool) preg_match('/^\s*(source|\.)\s+"\$\{?SHARED_FN_PATH\}?"/m', $text);
    $start = strpos($text, BLOCK_START);
    $end = strpos($text, BLOCK_END);

    if ($start === false || $end === false) {
        if ($loads) {
            $problems[] = "{$file} loads shared-functions.sh but has no intelis-loader block. Copy it from {$canonicalFile}.";
        }
        continue;
    }

    // Rule 1: the block is identical everywhere.
    $block = substr($text, $start, $end - $start + strlen(BLOCK_END));
    if ($canonical === null) {
        $canonical = $block;
        $canonicalFile = $file;
    } elseif ($block !== $canonical) {
        $problems[] = "{$file}: the intelis-loader block differs from the one in {$canonicalFile}. Make them identical.";
    }

    // The block only defines functions. Outside it, the commit has to be
    // resolved before anything uses it.
    $outside = substr($text, 0, $start) . substr($text, $end + strlen(BLOCK_END));
    $resolveAt = preg_match('/^\s*intelis_resolve_scripts_ref\s*$/m', $outside, $rm, PREG_OFFSET_CAPTURE) ? $rm[0][1] : null;
    // A use is any non-comment line reading the value, except ${...:+...},
    // which only passes it on when it is already set.
    $useAt = preg_match('/^(?!\s*#).*\$\{INTELIS_SCRIPTS_REF(?!:\+)/m', $outside, $um, PREG_OFFSET_CAPTURE) ? $um[0][1] : false;
    if ($resolveAt === null) {
        $problems[] = "{$file} never calls intelis_resolve_scripts_ref, so it downloads from the master branch instead of a commit.";
    } elseif ($useAt !== false && $useAt < $resolveAt) {
        $problems[] = "{$file} uses \${INTELIS_SCRIPTS_REF} before calling intelis_resolve_scripts_ref. Move the call above it.";
    }

    if (preg_match('~raw\.githubusercontent\.com/deforay/intelis/master/scripts/shared-functions\.sh~', $text)
        && preg_match('/^SHARED_FN_URL="[^"]*\/master\//m', $text)) {
        $problems[] = "{$file}: SHARED_FN_URL names the master branch. Use \${INTELIS_SCRIPTS_REF} so it comes from the same commit.";
    }

    // Rule 2: the gate names the current version.
    if ($loads && !in_array($file, GATE_EXEMPT, true)) {
        $sourceAt = preg_match('/^(source|\.)\s+"\$\{?SHARED_FN_PATH\}?"/m', $text, $sm, PREG_OFFSET_CAPTURE) ? $sm[0][1] : null;
        $gateAt = preg_match('/^intelis_require_shared_fn_version \d+$/m', $text, $gm, PREG_OFFSET_CAPTURE) ? $gm[0][1] : null;
        if ($gateAt !== null && $sourceAt !== null && $gateAt < $sourceAt) {
            $problems[] = "{$file} checks the shared-functions version before loading the file. Put intelis_require_shared_fn_version right after the source line.";
        }
        if (!preg_match_all('/^intelis_require_shared_fn_version (\d+)$/m', $text, $gates)) {
            $problems[] = "{$file} loads shared-functions.sh but never calls intelis_require_shared_fn_version. Add it right after the source line.";
        } else {
            foreach ($gates[1] as $need) {
                if ((int) $need !== $version) {
                    $problems[] = "{$file} asks for shared-functions version {$need}, and shared-functions.sh is version {$version}. Run: php bin/dev/bump-shared-fn-version.php --sync";
                }
            }
        }
    }
}

// Rule 3: a change to shared-functions.sh raises the version.
$base = getenv('SHIP_BASE') ?: '';
if ($base === '') {
    $hasOrigin = trim((string) shell_exec('git rev-parse --verify --quiet refs/remotes/origin/master 2>/dev/null'));
    if ($hasOrigin !== '') {
        $base = trim((string) shell_exec('git merge-base origin/master HEAD 2>/dev/null'));
    }
}
if ($base !== '') {
    $before = shell_exec('git show ' . escapeshellarg($base . ':' . SHARED) . ' 2>/dev/null');
    if (is_string($before) && $before !== $shared) {
        $beforeVersion = preg_match('/^SHARED_FN_VERSION=(\d+)$/m', $before, $bm) ? (int) $bm[1] : 0;
        if ($version <= $beforeVersion) {
            $problems[] = SHARED . " changed since " . substr($base, 0, 10) . " but SHARED_FN_VERSION is still {$version}. Run: php bin/dev/bump-shared-fn-version.php";
        }
    }
}

if ($problems !== []) {
    fwrite(STDERR, "check-shell-loaders: " . count($problems) . " problem(s)\n");
    foreach ($problems as $problem) {
        fwrite(STDERR, "  - {$problem}\n");
    }
    exit(1);
}

echo "check-shell-loaders: every script loads shared-functions.sh version {$version} from the same commit.\n";
