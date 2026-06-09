<?php

/**
 * Portable (export/import) shape for a Custom Test's test_results_config.
 *
 * The DB column (and all ~21 runtime readers) store the "result group" as a
 * struct-of-parallel-arrays keyed by sub-test index: sub_test_name[k], methods[k],
 * result_type[k], qualitative.{expectedResult,resultCode,sortOrder}[k], and
 * quantitative.{high,threshold,low}_range[k]. That shape is hard to read in an
 * exported file, so the portable format (version 2) collapses each sub-test into
 * one self-contained object under `sub_tests`, with a test-level `units` list.
 *
 * These two functions are pure and exact inverses. The DB shape NEVER changes;
 * export converts DB->v2 on the way out, import converts v2->DB on the way in,
 * so existing rows and every reader are untouched. Older (version 1) files carry
 * the DB shape verbatim and import reads them directly, with no conversion.
 */

if (!function_exists('portableResultsConfigToV2')) {
    /**
     * DB parallel-array shape -> clean v2 (sub_tests array-of-objects).
     * Non result-group keys (advancedFormConfig, any future keys) pass through.
     */
    function portableResultsConfigToV2(array $cfg): array
    {
        $resultGroupKeys = ['methods', 'sub_test_name', 'result_type', 'qualitative', 'quantitative', 'test_result_unit'];
        $out = [];
        foreach ($cfg as $k => $v) {
            if (!in_array($k, $resultGroupKeys, true)) {
                $out[$k] = $v;
            }
        }

        $units = array_values((array) ($cfg['test_result_unit'] ?? []));
        if ($units !== []) {
            $out['units'] = $units;
        }

        $subTestNames = (array) ($cfg['sub_test_name'] ?? []);
        $methods      = (array) ($cfg['methods'] ?? []);
        $resultType   = (array) ($cfg['result_type'] ?? []);
        $qual         = (array) ($cfg['qualitative'] ?? []);
        $quant        = (array) ($cfg['quantitative'] ?? []);

        $keys = array_keys($subTestNames);
        sort($keys, SORT_NATURAL);

        $subTests = [];
        foreach ($keys as $k) {
            $type = (($resultType[$k] ?? 'qualitative') === 'quantitative') ? 'quantitative' : 'qualitative';
            $st = [
                'name'        => (string) $subTestNames[$k],
                'result_type' => $type,
                'methods'     => array_values((array) ($methods[$k] ?? [])),
            ];
            if ($type === 'quantitative') {
                $st['ranges'] = [
                    'low'       => (string) ($quant['low_range'][$k] ?? ''),
                    'threshold' => (string) ($quant['threshold_range'][$k] ?? ''),
                    'high'      => (string) ($quant['high_range'][$k] ?? ''),
                ];
            } else {
                $vals  = (array) ($qual['expectedResult'][$k] ?? []);
                $codes = (array) ($qual['resultCode'][$k] ?? []);
                $sorts = (array) ($qual['sortOrder'][$k] ?? []);
                $optKeys = array_keys($vals);
                sort($optKeys, SORT_NATURAL);
                $options = [];
                foreach ($optKeys as $ok) {
                    $options[] = [
                        'value' => (string) ($vals[$ok] ?? ''),
                        'code'  => (string) ($codes[$ok] ?? ''),
                        'sort'  => (string) ($sorts[$ok] ?? ''),
                    ];
                }
                $st['options'] = $options;
            }
            $subTests[] = $st;
        }
        $out['sub_tests'] = $subTests;
        return $out;
    }
}

if (!function_exists('portableResultsConfigFromV2')) {
    /**
     * Clean v2 (sub_tests array-of-objects) -> DB parallel-array shape.
     * Sub-tests are re-keyed 1..N in array order (the save helper does the same).
     * Quantitative ranges are emitted for every sub-test ('' for qualitative ones),
     * matching the convention the form/save helper produces.
     */
    function portableResultsConfigFromV2(array $cfg): array
    {
        $out = [];
        foreach ($cfg as $k => $v) {
            if (!in_array($k, ['units', 'sub_tests'], true)) {
                $out[$k] = $v;
            }
        }
        $out['test_result_unit'] = array_values((array) ($cfg['units'] ?? []));

        $methods = $subTestName = $resultType = [];
        $qualExpected = $qualCode = $qualSort = [];
        $quantHigh = $quantThreshold = $quantLow = [];

        $i = 1;
        foreach ((array) ($cfg['sub_tests'] ?? []) as $st) {
            $key  = (string) $i;
            $type = (($st['result_type'] ?? 'qualitative') === 'quantitative') ? 'quantitative' : 'qualitative';
            $subTestName[$key] = (string) ($st['name'] ?? '');
            $resultType[$key]  = $type;
            $methods[$key]     = array_values((array) ($st['methods'] ?? []));

            // Quantitative ranges exist for every sub-test in the DB shape.
            $r = (array) ($st['ranges'] ?? []);
            $quantHigh[$key]      = $type === 'quantitative' ? (string) ($r['high'] ?? '') : '';
            $quantThreshold[$key] = $type === 'quantitative' ? (string) ($r['threshold'] ?? '') : '';
            $quantLow[$key]       = $type === 'quantitative' ? (string) ($r['low'] ?? '') : '';

            // Qualitative option maps exist only for qualitative sub-tests.
            if ($type === 'qualitative') {
                $j = 1;
                foreach ((array) ($st['options'] ?? []) as $opt) {
                    $ok = (string) $j;
                    $qualExpected[$key][$ok] = (string) ($opt['value'] ?? '');
                    $qualCode[$key][$ok]     = (string) ($opt['code'] ?? '');
                    $qualSort[$key][$ok]     = (string) ($opt['sort'] ?? $j);
                    $j++;
                }
            }
        $i++;
        }

        $out['methods']      = $methods;
        $out['sub_test_name'] = $subTestName;
        $out['result_type']  = $resultType;
        $out['qualitative']  = [
            'expectedResult' => $qualExpected,
            'resultCode'     => $qualCode,
            'sortOrder'      => $qualSort,
        ];
        $out['quantitative'] = [
            'high_range'      => $quantHigh,
            'threshold_range' => $quantThreshold,
            'low_range'       => $quantLow,
        ];
        return $out;
    }
}
