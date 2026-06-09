<?php

/**
 * Export a single Custom Test (test type) as a portable JSON file.
 *
 * IDs are instance-specific, so everything a test type references (category,
 * sample types, methods, reasons, units, symptoms) is exported BY NAME. The
 * companion import-test-type.php resolves those names back to local ids (or
 * creates them) on the destination instance.
 */

use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\AppRegistry;
use App\Registries\ContainerRegistry;
use Psr\Http\Message\ServerRequestInterface;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());

$id = isset($_GET['id']) ? (int) base64_decode((string) $_GET['id']) : 0;

try {
    $testType = $db->rawQueryOne("SELECT * FROM r_test_types WHERE test_type_id = ?", [$id]);
    if (empty($testType)) {
        http_response_code(404);
        echo _translate("Test type not found");
        return;
    }

    // Resolve the category id to its name.
    $categoryName = null;
    if (!empty($testType['test_category'])) {
        $cat = $db->rawQueryOne(
            "SELECT test_category_name FROM r_generic_test_categories WHERE test_category_id = ?",
            [$testType['test_category']]
        );
        $categoryName = $cat['test_category_name'] ?? null;
    }

    // Pull the names linked through each mapping table.
    $mappedNames = static function (string $sql) use ($db, $id): array {
        $rows = $db->rawQuery($sql, [$id]);
        $names = array_map(static fn($r) => $r['name'] ?? null, $rows ?: []);
        return array_values(array_filter($names, static fn($v) => $v !== null && $v !== ''));
    };

    $sampleTypes = $mappedNames("SELECT st.sample_type_name AS name
        FROM generic_test_sample_type_map m
        JOIN r_generic_sample_types st ON st.sample_type_id = m.sample_type_id
        WHERE m.test_type_id = ?");

    $testingReasons = $mappedNames("SELECT r.test_reason AS name
        FROM generic_test_reason_map m
        JOIN r_generic_test_reasons r ON r.test_reason_id = m.test_reason_id
        WHERE m.test_type_id = ?");

    $symptoms = $mappedNames("SELECT s.symptom_name AS name
        FROM generic_test_symptoms_map m
        JOIN r_generic_symptoms s ON s.symptom_id = m.symptom_id
        WHERE m.test_type_id = ?");

    $failureReasons = $mappedNames("SELECT fr.test_failure_reason AS name
        FROM generic_test_failure_reason_map m
        JOIN r_generic_test_failure_reasons fr ON fr.test_failure_reason_id = m.test_failure_reason_id
        WHERE m.test_type_id = ?");

    $rejectionReasons = $mappedNames("SELECT rr.rejection_reason_name AS name
        FROM generic_sample_rejection_reason_map m
        JOIN r_generic_sample_rejection_reasons rr ON rr.rejection_reason_id = m.rejection_reason_id
        WHERE m.test_type_id = ?");

    $resultUnits = $mappedNames("SELECT u.unit_name AS name
        FROM generic_test_result_units_map m
        JOIN r_generic_test_result_units u ON u.unit_id = m.unit_id
        WHERE m.test_type_id = ?");

    $testMethods = $mappedNames("SELECT tm.test_method_name AS name
        FROM generic_test_methods_map m
        JOIN r_generic_test_methods tm ON tm.test_method_id = m.test_method_id
        WHERE m.test_type_id = ?");

    $formConfig = json_decode((string) $testType['test_form_config'], true) ?: [];
    $resultsConfig = json_decode((string) $testType['test_results_config'], true) ?: [];

    // The results config stores result unit ids inline; translate them to names
    // so the configuration travels intact to an instance with different ids.
    if (!empty($resultsConfig['test_result_unit']) && is_array($resultsConfig['test_result_unit'])) {
        $unitNames = [];
        foreach ($resultsConfig['test_result_unit'] as $unitId) {
            $u = $db->rawQueryOne("SELECT unit_name FROM r_generic_test_result_units WHERE unit_id = ?", [$unitId]);
            if (!empty($u['unit_name'])) {
                $unitNames[] = $u['unit_name'];
            }
        }
        $resultsConfig['test_result_unit'] = $unitNames;
    }

    // Each Result Group lists the method ids that produce it; translate those to
    // names so the group -> methods mapping survives the move to an instance with
    // different ids (import-test-type.php resolves them back).
    if (!empty($resultsConfig['methods']) && is_array($resultsConfig['methods'])) {
        $methodNameById = [];
        foreach ($resultsConfig['methods'] as $groupKey => $methodIds) {
            $names = [];
            foreach ((array) $methodIds as $mid) {
                if (!array_key_exists($mid, $methodNameById)) {
                    $r = $db->rawQueryOne("SELECT test_method_name FROM r_generic_test_methods WHERE test_method_id = ?", [$mid]);
                    $methodNameById[$mid] = $r['test_method_name'] ?? null;
                }
                if (!empty($methodNameById[$mid])) {
                    $names[] = $methodNameById[$mid];
                }
            }
            $resultsConfig['methods'][$groupKey] = $names;
        }
    }

    // advancedFormConfig is form config that only piggybacks inside test_results_config
    // in the DB; hoist it to its own top-level key in the file (import folds it back).
    $advancedFormConfig = (isset($resultsConfig['advancedFormConfig']) && is_array($resultsConfig['advancedFormConfig']))
        ? $resultsConfig['advancedFormConfig'] : [];
    unset($resultsConfig['advancedFormConfig']);

    // Portable result-group shape: collapse the DB's parallel-arrays into clean
    // self-contained sub-test objects (version 2). import-test-type.php converts
    // it back; older (v1) files that carry the DB shape verbatim still import.
    require_once __DIR__ . '/_portable-results-config.php';
    $resultsConfig = portableResultsConfigToV2($resultsConfig);

    $export = [
        'format' => 'intelis.custom-test',
        'version' => 2,
        'exported_at' => date('c'),
        'test' => [
            // Portable identity. import-test-type.php matches on test_type_uuid to
            // offer an in-place update of the same test on ANY instance. test_type_id
            // is kept only as a same-instance fallback for pre-UUID exports.
            'test_type_uuid' => (string) ($testType['test_type_uuid'] ?? ''),
            'test_type_id' => (int) $testType['test_type_id'],
            'test_standard_name' => $testType['test_standard_name'],
            'test_generic_name' => $testType['test_generic_name'],
            'test_short_code' => $testType['test_short_code'],
            'test_loinc_code' => $testType['test_loinc_code'],
            'test_status' => $testType['test_status'],
            'category' => $categoryName,
            'test_form_config' => $formConfig,
            'advanced_form_config' => (object) $advancedFormConfig,
            'test_results_config' => $resultsConfig,
            'sample_types' => $sampleTypes,
            'testing_reasons' => $testingReasons,
            'symptoms' => $symptoms,
            'test_failure_reasons' => $failureReasons,
            'sample_rejection_reasons' => $rejectionReasons,
            'result_units' => $resultUnits,
            'test_methods' => $testMethods,
        ],
    ];

    $slug = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) ($testType['test_short_code'] ?: $testType['test_standard_name']));
    $slug = trim((string) $slug, '-');
    if ($slug === '') {
        $slug = 'custom-test';
    }
    $filename = 'custom-test-' . $slug . '-' . date('Ymd') . '.json';

    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    LoggerUtility::logError($e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
    http_response_code(500);
    echo _translate("Could not export the test type");
}
