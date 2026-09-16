<?php

declare(strict_types=1);

namespace App\Services\STS;

use DateTimeImmutable;

/**
 * What a lab's metadata sync request asks the STS for.
 *
 * A lab sends a *LastModified date for every reference table of every test type
 * it runs -- null for a table it has never synced -- and gets back the rows
 * changed since then. These two questions decide what goes back, and both used
 * to be answered wrongly in app/remote/remote/sts-metadata-sender.php.
 */
final class MetadataSyncScope
{
    /**
     * One key per test type, sent by every lab running that test type since the
     * type's sync was added (VL, EID and COVID-19 in 2020, hepatitis late 2020,
     * TB 2023, CD4 2024), in the same change that taught the lab to apply its
     * tables.
     */
    private const MODULE_PROBE_KEYS = [
        'generic-tests' => 'rTestTypesLastModified',
        'vl' => 'vlSampleTypesLastModified',
        'eid' => 'eidSampleTypesLastModified',
        'covid19' => 'covid19SampleTypesLastModified',
        'hepatitis' => 'hepatitisSampleTypesLastModified',
        'tb' => 'tbSampleTypesLastModified',
        'cd4' => 'cd4SampleTypesLastModified',
    ];

    /**
     * Whether to send a test type's reference tables.
     *
     * A lab sends no keys for a test type it does not run, and discards those
     * tables if they arrive. The STS read the missing keys as "never synced" and
     * sent the whole tables on every call: 554 rows per sync to a VL-only lab in
     * Rwanda, all of them thrown away. A request naming no test type at all comes
     * from a lab too old to send these keys, and still gets everything.
     *
     * @param array<string, mixed> $request
     * @param array<string, mixed> $enabledModules SYSTEM_CONFIG['modules'] on this STS
     */
    public static function sendsModule(array $request, string $module, array $enabledModules): bool
    {
        if (($enabledModules[$module] ?? false) !== true || !isset(self::MODULE_PROBE_KEYS[$module])) {
            return false;
        }

        $namesAnyModule = array_intersect_key($request, array_flip(self::MODULE_PROBE_KEYS)) !== [];

        return !$namesAnyModule || array_key_exists(self::MODULE_PROBE_KEYS[$module], $request);
    }

    /**
     * "$column > '<date the lab sent for $key>'", or [] when there is no usable
     * date, which fetches the whole table.
     *
     * The endpoint takes no login token and these dates go straight into SQL, so
     * only a plain Y-m-d or Y-m-d H:i:s value is accepted, and it is rewritten in
     * that exact form. Anything else is treated as no date.
     *
     * @param array<string, mixed> $request
     * @return string|array{}
     */
    public static function sinceCondition(
        array $request,
        string $key,
        string $column = 'updated_datetime'
    ): string|array {
        $value = $request[$key] ?? null;
        if (!is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $value) !== 1) {
            return [];
        }

        $normalised = strlen($value) === 10 ? "$value 00:00:00" : $value;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $normalised);
        if ($date === false || $date->format('Y-m-d H:i:s') !== $normalised) {
            return [];
        }

        return "$column > '" . $date->format('Y-m-d H:i:s') . "'";
    }
}
