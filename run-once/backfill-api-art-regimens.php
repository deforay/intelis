<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Services\VlService;
use App\Utilities\MiscUtility;
use App\Utilities\RunOnceUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/*
 * Registers ART regimens that reached form_vl over the API before 5.6.4 taught
 * api/v1.1/vl/save-request.php to resolve them.
 *
 * Until that release the endpoint wrote `artRegimen` into current_regimen verbatim.
 * A caller with its own regimen vocabulary — an EMR may send catalogue labels
 * like "08 - TDF/3TC/DTG 300 mg/300 mg/50 mg" against our "1a = TDF+3TC+DTG" — left
 * rows whose regimen prints correctly on the result PDF and both exports, all of which
 * read the column directly, but shows as an empty dropdown on the request form, which
 * is the one surface that matches the value against r_vl_art_regimen. Saving that form
 * then writes the blank select over the stored value.
 *
 * Passing each distinct value through VlService::resolveArtRegimen() registers the ones
 * that match nothing, which is what makes them selectable and closes that overwrite.
 * form_vl itself is never rewritten: the stored strings are already correct and are what
 * the newly registered art_code rows match, so touching them would be pointless churn
 * across the audit triggers and the sync flags.
 *
 * Scoped to source_of_request = 'API'. Values entered through the request form came out
 * of the dropdown and so already match, apart from any added via the "other" box, which
 * addVlRequestHelper.php has always registered at the point of entry. Anything else
 * unmatched arrived by an import or an interface path whose provenance this script
 * cannot establish, and registering it would mean promoting unreviewed data — possibly a
 * typo — into the reference catalogue the dropdown offers everyone. Those are counted and
 * reported instead, so they are visible without being acted on.
 *
 * Idempotent: resolveArtRegimen() registers only what does not already match an art_code,
 * so a second run finds everything registered and does nothing. Mappings made through
 * r_vl_art_regimen_alias do not change that — an alias groups two codes for reporting and
 * leaves both registered, so a mapped code is still matched here and still left alone.
 */
RunOnceUtility::run(__FILE__, function (DatabaseService $db): void {
    /** @var VlService $vlService */
    $vlService = ContainerRegistry::get(VlService::class);

    // DISTINCT on the raw column, trimmed. Grouping in SQL rather than resolving row by
    // row keeps this to one pass per distinct string instead of one per sample, which on
    // a fleet instance is the difference between a handful of lookups and millions.
    $rows = $db->rawQuery(
        "SELECT TRIM(current_regimen) AS regimen, COUNT(*) AS sample_count
         FROM form_vl
         WHERE source_of_request = 'API'
           AND current_regimen IS NOT NULL
           AND TRIM(current_regimen) <> ''
         GROUP BY TRIM(current_regimen)
         ORDER BY sample_count DESC"
    );

    $rows = is_array($rows) ? $rows : [];

    if ($rows === []) {
        MiscUtility::safeCliEcho("Backfilling API ART regimens… no API samples carry one." . PHP_EOL);
        return;
    }

    $registered = 0;
    $alreadyKnown = 0;
    $failed = 0;

    foreach ($rows as $row) {
        $regimen = (string) ($row['regimen'] ?? '');
        $sampleCount = (int) ($row['sample_count'] ?? 0);
        if ($regimen === '') {
            continue;
        }

        // Ask before resolving, so the report can distinguish a value that was already
        // known from one this run created. resolveArtRegimen() returns the string either
        // way and deliberately does not say which happened — it fails open, and a caller
        // in the API path has nothing useful to do with the difference.
        // Status-agnostic, matching the resolver: a code retired from the dropdown is
        // still registered, and re-registering it would create a duplicate.
        $known = $db->rawQueryOne(
            "SELECT art_id FROM r_vl_art_regimen WHERE art_code = ? LIMIT 1",
            [$regimen]
        );

        if (!empty($known)) {
            $alreadyKnown++;
            continue;
        }

        try {
            $vlService->resolveArtRegimen($regimen, 'api');
            $registered++;
            MiscUtility::safeCliEcho(
                sprintf('  registered "%s" (%d sample%s)', $regimen, $sampleCount, $sampleCount === 1 ? '' : 's') . PHP_EOL
            );
        } catch (Throwable $e) {
            $failed++;
            MiscUtility::safeCliEcho(sprintf('  FAILED "%s": %s', $regimen, $e->getMessage()) . PHP_EOL);
        }
    }

    // Unmatched values from every other source. Reported, never registered — see the
    // scoping note above. A non-zero count here is worth an administrator's attention,
    // since those samples show a blank dropdown too.
    $others = $db->rawQuery(
        "SELECT TRIM(v.current_regimen) AS regimen, COUNT(*) AS sample_count
         FROM form_vl v
         WHERE (v.source_of_request IS NULL OR v.source_of_request <> 'API')
           AND v.current_regimen IS NOT NULL
           AND TRIM(v.current_regimen) <> ''
         GROUP BY TRIM(v.current_regimen)"
    );
    $others = is_array($others) ? $others : [];

    $otherUnmatched = 0;
    $otherSamples = 0;
    foreach ($others as $row) {
        $regimen = (string) ($row['regimen'] ?? '');
        if ($regimen === '') {
            continue;
        }
        $known = $db->rawQueryOne(
            "SELECT art_id FROM r_vl_art_regimen WHERE art_code = ? LIMIT 1",
            [$regimen]
        );
        if (empty($known)) {
            $otherUnmatched++;
            $otherSamples += (int) ($row['sample_count'] ?? 0);
        }
    }

    MiscUtility::safeCliEcho(
        sprintf(
            'Backfilling API ART regimens… %d distinct value%s seen, %d registered, %d already known, %d failed.',
            count($rows),
            count($rows) === 1 ? '' : 's',
            $registered,
            $alreadyKnown,
            $failed
        ) . PHP_EOL
    );

    if ($otherUnmatched > 0) {
        MiscUtility::safeCliEcho(
            sprintf(
                '  Note: %d further regimen value%s (%d sample%s) from non-API sources match nothing in '
                    . 'r_vl_art_regimen. Left alone deliberately; review before registering.',
                $otherUnmatched,
                $otherUnmatched === 1 ? '' : 's',
                $otherSamples,
                $otherSamples === 1 ? '' : 's'
            ) . PHP_EOL
        );
    }

    if ($registered > 0) {
        MiscUtility::safeCliEcho(
            '  Map external codes onto canonical regimens via r_vl_art_regimen_alias to keep '
                . 'regimen reporting from splitting one cohort across two codes.' . PHP_EOL
        );
    }
});
