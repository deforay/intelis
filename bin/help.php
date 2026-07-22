#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Lists bin/ scripts with a one-line summary, grouped by purpose.
 *
 * Default view shows production-facing scripts only. Subsystem dispatchers
 * (smart-connect/*, tb/*, external/*) and build helpers are hidden until you
 * ask for them.
 *
 * Usage:
 *   php bin/help.php              production-facing scripts only
 *   php bin/help.php subsystems   subsystem dispatchers (smart-connect, tb, external)
 *   php bin/help.php build        build / release helpers
 *   php bin/help.php all          everything under bin/
 *   php bin/help.php <pattern>    filter by substring across everything
 *   php bin/help.php --help       print this docblock
 *
 * Add a new script? Drop a /** ... *\/ block at the top (first paragraph
 * becomes the summary) and slot it into $CATEGORY_MAP below.
 */

require __DIR__ . '/lib/help.php';
bin_help_if_requested(__FILE__);

$BIN_DIR = __DIR__;
$ROOT = dirname($BIN_DIR);

// Category → display order. Lower number = earlier in output.
$CATEGORIES = [
    'Scheduled (cron)' => 10,
    'Sync & remote' => 20,
    'Setup & install' => 30,
    'Database & migrations' => 40,
    'Maintenance' => 50,
    'Admin one-shots' => 60,
    'Ops' => 70,
    'Subsystems' => 80,
    'Build & release' => 85,
    'Dev tools' => 87,
    'Help' => 90,
    'Other' => 99,
];

// Script path (relative to bin/) → category.
$CATEGORY_MAP = [
    // Scheduled (cron) — automation; see sys/cron/ScheduledTasks.php.
    'housekeeping.php' => 'Scheduled (cron)',
    'sample-code-generator.php' => 'Scheduled (cron)',
    'backup-configs.php' => 'Scheduled (cron)',
    'update-sample-status.php' => 'Scheduled (cron)',
    'update-vl-suppression.php' => 'Scheduled (cron)',
    'prune-remote-commands.php' => 'Scheduled (cron)',
    'sync-interface-sqlite-mysql.php' => 'Scheduled (cron)',
    'interface.php' => 'Scheduled (cron)',
    'run-jobs.php' => 'Scheduled (cron)',

    // Sync & remote (STS connectivity).
    'token.php' => 'Sync & remote',

    // Setup & install.
    'provision.php' => 'Setup & install',
    'setup/setup-sts.php' => 'Setup & install',
    'setup/system-admin.php' => 'Setup & install',
    'setup/update-privileges.php' => 'Setup & install',
    'setup/fix-app-menu.php' => 'Setup & install',
    'setup/regenerate-audit-triggers.php' => 'Setup & install',
    'setup/change-db-collation.php' => 'Setup & install',

    // Database & migrations.
    'migrate.php' => 'Database & migrations',
    'reset-seq.php' => 'Database & migrations',
    'interface-migrate.php' => 'Database & migrations',

    // Maintenance / retention.
    'clear-logs.php' => 'Maintenance',

    // Admin one-shots.
    'create-api-user.php' => 'Admin one-shots',
    'reset-user-password.php' => 'Admin one-shots',
    'send-email.php' => 'Admin one-shots',
    'geocode-facilities.php' => 'Admin one-shots',

    // Ops.
    'health.php' => 'Ops',
    'httpd-graceful.php' => 'Ops',

    // Subsystems (hidden by default).
    'smart-connect/metadata.php' => 'Subsystems',
    'smart-connect/vl.php' => 'Subsystems',
    'smart-connect/eid.php' => 'Subsystems',
    'smart-connect/covid19.php' => 'Subsystems',
    'referrals.php' => 'Subsystems',
    'external/results.php' => 'Subsystems',

    // Build / release.
    'build/generate-version.php' => 'Build & release',

    // Dev tools (hidden by default; dev machines only).
    'dev/switch-mode.php' => 'Dev tools',

    // Help.
    'help.php' => 'Help',

    // Untagged → 'Other':
    //   scan.php
];

// Parse mode: subsystems / build / all / <pattern> / null.
$arg = $argv[1] ?? null;
$mode = 'default';
$filter = null;
if ($arg !== null) {
    $lower = strtolower($arg);
    if (in_array($lower, ['subsystems', 'build', 'all'], true)) {
        $mode = $lower;
    } else {
        $mode = 'filter';
        $filter = $lower;
    }
}

// Default view hides subsystem dispatchers + build helpers — they're machine-
// invoked, not human-invoked. Filter mode searches everything.
$HIDDEN_IN_DEFAULT = ['Subsystems', 'Build & release', 'Dev tools'];

// 1. Discover scripts (top-level + curated subdirs).
$scripts = [];
$SUBDIRS_TO_SCAN = ['setup', 'smart-connect', 'tb', 'external', 'build', 'dev'];

foreach (scandir($BIN_DIR) as $entry) {
    if ($entry === '.' || $entry === '..') {
        continue;
    }
    $path = $BIN_DIR . '/' . $entry;
    if (is_file($path) && str_ends_with($entry, '.php')) {
        $scripts[] = $entry;
    } elseif (is_dir($path) && in_array($entry, $SUBDIRS_TO_SCAN, true)) {
        foreach (scandir($path) as $sub) {
            if ($sub === '.' || $sub === '..') {
                continue;
            }
            $subPath = $path . '/' . $sub;
            if (is_file($subPath) && str_ends_with($sub, '.php')) {
                $scripts[] = $entry . '/' . $sub;
            }
        }
    }
}
sort($scripts);

// 2. Extract summary for each, apply visibility filter.
$rows = [];
foreach ($scripts as $name) {
    $summary = extract_summary($BIN_DIR . '/' . $name);
    $category = $CATEGORY_MAP[$name] ?? 'Other';

    // Visibility per mode.
    if ($mode === 'default' && in_array($category, $HIDDEN_IN_DEFAULT, true)) {
        continue;
    }
    if ($mode === 'subsystems' && $category !== 'Subsystems') {
        continue;
    }
    if ($mode === 'build' && $category !== 'Build & release') {
        continue;
    }

    if ($filter !== null) {
        $hay = strtolower($name . ' ' . $summary);
        if (!str_contains($hay, $filter)) {
            continue;
        }
    }

    $rows[$category][] = ['name' => $name, 'summary' => $summary];
}

if ($rows === []) {
    fwrite(STDERR, 'no scripts matched: ' . ($filter ?? '(none)') . "\n");
    exit(1);
}

// 3. Print, ordered by category weight.
$useColor = stream_isatty(STDOUT);
$bold = $useColor ? "\033[1m" : '';
$dim = $useColor ? "\033[2m" : '';
$cyan = $useColor ? "\033[36m" : '';
$reset = $useColor ? "\033[0m" : '';

$orderedCategories = array_keys($rows);
usort($orderedCategories, fn($a, $b) => ($CATEGORIES[$a] ?? 99) <=> ($CATEGORIES[$b] ?? 99));

$maxName = 0;
foreach ($rows as $list) {
    foreach ($list as $r) {
        $maxName = max($maxName, strlen($r['name']));
    }
}

foreach ($orderedCategories as $cat) {
    echo "\n{$bold}{$cat}{$reset}\n";
    foreach ($rows[$cat] as $r) {
        $pad = str_repeat(' ', $maxName - strlen($r['name']) + 2);
        $summary = $r['summary'] !== '' ? $r['summary'] : "{$dim}(no description){$reset}";
        echo "  {$cyan}{$r['name']}{$reset}{$pad}{$summary}\n";
    }
}

if ($mode === 'default') {
    echo "\n{$dim}Hidden: {$reset}{$cyan}php bin/help.php subsystems{$reset}{$dim}  php bin/help.php build  php bin/help.php all{$reset}\n";
}
echo "\n";

/**
 * Pull the first useful line out of a script's leading docblock or `//` comments.
 *
 * - Skips shebang + `<?php` tag.
 * - Accepts a leading /** ... *\/ docblock OR a contiguous run of // comments.
 * - Ignores inline /** @var Foo $x *\/ — those are type hints, not summaries.
 * - Treats a comment line that is JUST the script path (e.g. `// bin/foo.php`)
 *   as a header and skips it in favour of the next real comment line.
 * - Stops at the first line of actual code.
 */
function extract_summary(string $path): string
{
    $fh = @fopen($path, 'r');
    if (!$fh) {
        return '';
    }

    $inDocblock = false;
    $lines = [];
    $sawPhpTag = false;
    $lineCount = 0;

    while (($line = fgets($fh)) !== false && $lineCount++ < 80) {
        $trim = trim($line);

        if ($lineCount === 1 && str_starts_with($trim, '#!')) {
            continue;
        }
        if (!$sawPhpTag && str_starts_with($trim, '<?php')) {
            $sawPhpTag = true;
            continue;
        }
        if ($trim === '') {
            // Inside a docblock, blank lines are paragraph breaks — keep going.
            // Outside (between // runs or after a paragraph), blank ends discovery.
            if (!$inDocblock && $lines !== []) {
                break;
            }
            continue;
        }
        if (str_starts_with($trim, 'declare(') || str_starts_with($trim, 'namespace ')) {
            continue;
        }

        // /** ... */ docblock
        if (!$inDocblock && str_starts_with($trim, '/**')) {
            // Single-line /** ... */ — skip if it's a @tag (type hint), else use.
            if (str_ends_with($trim, '*/')) {
                $inner = trim(substr($trim, 3, -2));
                if ($inner === '' || str_starts_with($inner, '@')) {
                    continue;
                }
                $lines[] = $inner;
                break;
            }
            $inDocblock = true;
            continue;
        }
        if ($inDocblock) {
            if (str_contains($trim, '*/')) {
                break;
            }
            $body = ltrim($trim, '* ');
            if ($body === '') {
                continue; // inner blank line — keep collecting
            }
            if (str_starts_with($body, '@')) {
                break; // hit @param/@return etc. — no prose to follow
            }
            $lines[] = $body;
            continue;
        }

        // Leading `// ...` comment run.
        if ($sawPhpTag && str_starts_with($trim, '//')) {
            $body = ltrim(substr($trim, 2));
            if ($body !== '') {
                $lines[] = $body;
            }
            continue;
        }

        // First line of real code ends discovery.
        break;
    }
    fclose($fh);

    foreach ($lines as $candidate) {
        $stripped = strip_path_prefix($candidate);
        if ($stripped !== '') {
            return $stripped;
        }
    }
    return '';
}

/**
 * Drop redundant `bin/foo.php` self-references at the start of a summary line.
 * - `bin/foo.php — Description` → `Description`
 * - `bin/foo.php` (alone)       → ``  (caller skips empty and tries next line)
 */
function strip_path_prefix(string $line): string
{
    $line = preg_replace('#^bin/[a-z0-9/.-]+\s+(?:—|-|:)\s*#u', '', $line);
    if (preg_match('#^bin/[a-z0-9/.-]+\.php\s*$#u', $line)) {
        return '';
    }
    return $line;
}
