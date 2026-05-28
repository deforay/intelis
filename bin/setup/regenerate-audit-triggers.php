#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * bin/setup/regenerate-audit-triggers.php — Audit Trail v2 trigger preview.
 *
 * Dry-run only. Prints the DROP + CREATE TRIGGER SQL that the v2 generator
 * would issue against the current `form_*` schemas. Intentionally does NOT
 * apply anything — step 1 of the v2 build is additive, so live capture is
 * still done by the legacy `<form>_data__*` triggers writing into
 * `audit_form_*`. A later step adds the execute path and wires it into the
 * upgrade flow; the cutover step drops the legacy triggers in favor of these.
 *
 * Usage:
 *   php bin/setup/regenerate-audit-triggers.php             # all tracked forms
 *   php bin/setup/regenerate-audit-triggers.php form_vl     # one table
 */

require_once __DIR__ . '/../../bootstrap.php';

use App\Registries\ContainerRegistry;
use App\Services\AuditTriggerService;

/** @var AuditTriggerService $svc */
$svc = ContainerRegistry::get(AuditTriggerService::class);

if (!$svc->auditLogReady()) {
    fwrite(STDERR, "audit_log table is not present yet — run sys/migrations through 5.5.3 first.\n");
    exit(1);
}

$only = $argv[1] ?? null;
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

foreach ($forms as $f) {
    echo "-- ============================================================\n";
    echo "-- {$f['table']}  (pk: {$f['pk']})\n";
    echo "-- ============================================================\n";
    foreach ($svc->buildTriggersFor($f['table'], $f['pk']) as $stmt) {
        echo $stmt . ";\n\n";
    }
}

exit(0);
