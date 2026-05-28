#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * bin/setup/regenerate-audit-triggers.php
 *
 * Audit Trail v2 trigger lifecycle for upgrade.sh:
 *
 *   php bin/setup/regenerate-audit-triggers.php                     # dry-run (default)
 *   php bin/setup/regenerate-audit-triggers.php form_vl             # dry-run, scoped
 *   php bin/setup/regenerate-audit-triggers.php --apply drop-all    # drop BOTH legacy
 *                                                                     <form>_data__* and
 *                                                                     v2 <form>_audit_*
 *                                                                     triggers. Called by
 *                                                                     upgrade.sh BEFORE
 *                                                                     migrations so renames
 *                                                                     / drops never break a
 *                                                                     write against a stale
 *                                                                     trigger.
 *   php bin/setup/regenerate-audit-triggers.php --apply rebuild     # (re)create v2 triggers
 *                                                                     from the live schema.
 *                                                                     Called by upgrade.sh
 *                                                                     AFTER migrations.
 *
 * Triggers are generated from information_schema (no hand-maintained column
 * lists). Each form's three triggers (ai/au/bd) are emitted as a fresh DROP +
 * CREATE pair. Idempotent: safe to re-run.
 */

require_once __DIR__ . '/../../bootstrap.php';

use App\Services\AuditTriggerService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

[$mode, $only] = parseArgs(array_slice($argv, 1));

/** @var AuditTriggerService $svc */
$svc = ContainerRegistry::get(AuditTriggerService::class);

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);
$mysqli = $db->mysqli();

$forms = $svc->trackedForms();
if ($only !== null) {
    $forms = array_values(array_filter($forms, static fn(array $f) => $f['table'] === $only));
    if ($forms === []) {
        fwrite(STDERR, "No tracked form matches '{$only}'.\n");
        exit(1);
    }
}

if ($forms === []) {
    fwrite(STDERR, "No tracked form tables found on this instance.\n");
    exit(0);
}

// --apply rebuild also requires the v5.5.3 audit_log table — without it the
// trigger body references a non-existent table and capture would fail.
if ($mode === 'rebuild' && !$svc->auditLogReady()) {
    fwrite(STDERR, "Audit Trail v2: audit_log not present yet — run sys/migrations through 5.5.3 first.\n");
    exit(1);
}

$exitCode = 0;
foreach ($forms as $f) {
    $statements = collectStatements($svc, $f['table'], $f['pk'], $mode);
    if ($mode === null) {
        // dry-run print
        echo "-- ============================================================\n";
        echo "-- {$f['table']}  (pk: {$f['pk']})\n";
        echo "-- ============================================================\n";
        foreach ($statements as $sql) {
            echo $sql . ";\n\n";
        }
        continue;
    }

    fwrite(STDERR, "[" . strtoupper($mode) . "] {$f['table']}\n");
    foreach ($statements as $sql) {
        if (!$mysqli->query($sql)) {
            fwrite(STDERR, "  FAIL: " . $mysqli->error . "\n");
            fwrite(STDERR, "  --- sql ---\n{$sql}\n");
            $exitCode = 1;
            // Keep going across the remaining forms — partial success on a
            // mixed-state cutover is still better than aborting.
        }
    }
}

exit($exitCode);


// ---- helpers ----

/**
 * @return array{0:?string, 1:?string}  [mode, onlyForm]
 *   mode: null (dry-run), 'drop-all', 'rebuild'
 */
function parseArgs(array $args): array
{
    $mode = null;
    $only = null;
    while ($args !== []) {
        $a = array_shift($args);
        if ($a === '--apply') {
            $next = array_shift($args);
            if (!in_array($next, ['drop-all', 'rebuild'], true)) {
                fwrite(STDERR, "Usage: --apply drop-all | --apply rebuild\n");
                exit(2);
            }
            $mode = $next;
            continue;
        }
        // Positional: the optional form-table filter (e.g. 'form_vl').
        if ($only === null) {
            $only = $a;
        }
    }
    return [$mode, $only];
}

/**
 * @return list<string>
 */
function collectStatements(AuditTriggerService $svc, string $formTable, string $pk, ?string $mode): array
{
    // drop-all: drop BOTH legacy <form>_data__* and v2 <form>_audit_* triggers.
    if ($mode === 'drop-all') {
        return [...$svc->buildDropLegacyTriggers($formTable), ...$svc->buildDropTriggersFor($formTable)];
    }
    // rebuild / dry-run: idempotent re-create of v2 triggers (DROP IF EXISTS + CREATE).
    return $svc->buildTriggersFor($formTable, $pk);
}
