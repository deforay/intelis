<?php

/**
 * Import a Custom Test (test type) that was exported from another instance.
 *
 * Flow:
 *   1. GET  -> show a small upload form.
 *   2. POST (multipart with the exported .json) -> resolve every referenced
 *      entity (category, sample types, methods, reasons, units, symptoms) to a
 *      LOCAL id, creating any that don't exist yet, then render the same
 *      pre-filled form used by "Clone". The user reviews/edits and clicks
 *      Submit, which runs addTestTypeHelper.php and creates a brand-new test
 *      type (fresh auto-increment id -> no id clash).
 *
 * Name / generic name / short code that already exist locally are auto-suffixed
 * so the form loads clean; the user can rename before saving. A duplicate LOINC
 * code is cleared (suffixing a standardised code would corrupt it).
 */

use App\Services\CommonService;
use App\Utilities\MiscUtility;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\AppRegistry;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

require_once APPLICATION_PATH . '/header.php';

// Importing creates configuration, so it is not available on a pure LIS instance
// (mirrors the "Add Test Type" gating on the listing page).
if ($general->isLISInstance()) {
    echo "<script>window.location.href='test-type.php';</script>";
    require_once APPLICATION_PATH . '/footer.php';
    return;
}

$importError = null;
$test = null;

$uploadPresent = isset($_FILES['importFile']) && is_array($_FILES['importFile'])
    && (($_FILES['importFile']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE);

if ($uploadPresent) {
    if (($_FILES['importFile']['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        $importError = _translate("The file could not be uploaded. Please try again.");
    } elseif (($_FILES['importFile']['size'] ?? 0) > 5 * 1024 * 1024) {
        $importError = _translate("The file is too large to be a valid Custom Test export.");
    } else {
        $raw = file_get_contents($_FILES['importFile']['tmp_name']);
        $payload = json_decode((string) $raw, true);
        if (
            !is_array($payload)
            || ($payload['format'] ?? '') !== 'intelis.custom-test'
            || empty($payload['test'])
            || !is_array($payload['test'])
        ) {
            $importError = _translate("This file is not a valid Custom Test export.");
        } else {
            $test = $payload['test'];
        }
    }
}

if ($test === null) {
    // ---------------------------------------------------------------
    // Step 1: upload form
    // ---------------------------------------------------------------
    ?>
    <div class="content-wrapper">
        <section class="content-header">
            <h1><em class="fa-solid fa-file-import"></em> <?php echo _translate("Import Test Type"); ?></h1>
            <ol class="breadcrumb">
                <li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _translate("Home"); ?></a></li>
                <li><a href="test-type.php"><?php echo _translate("Test Type Configuration"); ?></a></li>
                <li class="active"><?php echo _translate("Import Test Type"); ?></li>
            </ol>
        </section>
        <section class="content">
            <div class="row">
                <div class="col-md-8 col-md-offset-2">
                    <?php if ($importError !== null) { ?>
                        <div class="alert alert-danger">
                            <em class="fa-solid fa-triangle-exclamation"></em> <?php echo htmlspecialchars($importError); ?>
                        </div>
                    <?php } ?>
                    <div class="box box-primary">
                        <div class="box-header with-border">
                            <h3 class="box-title"><?php echo _translate("Upload an exported test"); ?></h3>
                        </div>
                        <form method="post" action="import-test-type.php" enctype="multipart/form-data"
                            class="form-horizontal">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>" />
                            <div class="box-body">
                                <p class="text-muted">
                                    <?php echo _translate("Select a Custom Test file (.json) exported from another InteLIS instance. You will be taken to an editable form where you can review and adjust the test before saving it here."); ?>
                                </p>
                                <div class="form-group">
                                    <label for="importFile"
                                        class="col-lg-3 control-label"><?php echo _translate("Export file"); ?>
                                        <span class="mandatory">*</span></label>
                                    <div class="col-lg-7">
                                        <input type="file" name="importFile" id="importFile" accept=".json,application/json"
                                            class="form-control" required />
                                    </div>
                                </div>
                            </div>
                            <div class="box-footer">
                                <button type="submit" class="btn btn-primary">
                                    <em class="fa-solid fa-upload"></em> <?php echo _translate("Upload &amp; Continue"); ?>
                                </button>
                                <a href="test-type.php" class="btn btn-default"><?php echo _translate("Cancel"); ?></a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    </div>
    <?php
    require_once APPLICATION_PATH . '/footer.php';
    return;
}

// ---------------------------------------------------------------
// Step 2: resolve names -> local ids (creating any that are missing)
// ---------------------------------------------------------------

/**
 * Find a lookup row by name, returning its id; create it (active) if missing.
 * $idCol / $nameCol are code constants, never user input.
 */
$resolveOrCreate = function (string $table, string $idCol, string $nameCol, string $name, array $extra) use ($db): ?int {
    $name = trim($name);
    if ($name === '') {
        return null;
    }
    $row = $db->rawQueryOne("SELECT $idCol AS id FROM $table WHERE $nameCol = ? LIMIT 1", [$name]);
    if (!empty($row['id'])) {
        return (int) $row['id'];
    }
    $db->insert($table, array_merge([$nameCol => $name], $extra));
    return (int) $db->getInsertId();
};

$resolveMany = function (array $names, string $table, string $idCol, string $nameCol, callable $extraFn) use ($resolveOrCreate): array {
    $ids = [];
    foreach ($names as $name) {
        $id = $resolveOrCreate($table, $idCol, $nameCol, (string) $name, $extraFn());
        if ($id !== null) {
            $ids[$id] = $id;
        }
    }
    return array_values($ids);
};

$categoryId = '';
if (!empty($test['category'])) {
    $categoryId = $resolveOrCreate('r_generic_test_categories', 'test_category_id', 'test_category_name', (string) $test['category'], ['test_category_status' => 'active']) ?? '';
}

$testMethodId = $resolveMany($test['test_methods'] ?? [], 'r_generic_test_methods', 'test_method_id', 'test_method_name', static fn() => ['test_method_status' => 'active']);
$testSampleId = $resolveMany($test['sample_types'] ?? [], 'r_generic_sample_types', 'sample_type_id', 'sample_type_name', static fn() => ['sample_type_code' => MiscUtility::generateRandomString(5), 'sample_type_status' => 'active']);
$testReasonId = $resolveMany($test['testing_reasons'] ?? [], 'r_generic_test_reasons', 'test_reason_id', 'test_reason', static fn() => ['test_reason_code' => MiscUtility::generateRandomString(5), 'test_reason_status' => 'active']);
$testFailureReasonId = $resolveMany($test['test_failure_reasons'] ?? [], 'r_generic_test_failure_reasons', 'test_failure_reason_id', 'test_failure_reason', static fn() => ['test_failure_reason_code' => MiscUtility::generateRandomString(5), 'test_failure_reason_status' => 'active']);
$rejectionReasonId = $resolveMany($test['sample_rejection_reasons'] ?? [], 'r_generic_sample_rejection_reasons', 'rejection_reason_id', 'rejection_reason_name', static fn() => ['rejection_reason_code' => MiscUtility::generateRandomString(5), 'rejection_reason_status' => 'active']);
$testSymptomsId = $resolveMany($test['symptoms'] ?? [], 'r_generic_symptoms', 'symptom_id', 'symptom_name', static fn() => ['symptom_code' => MiscUtility::generateRandomString(5), 'symptom_status' => 'active']);

// Result units: the quantitative config carries the selected unit names; fall
// back to the top-level list. Map them to local ids for the select + config.
$resultsConfig = (!empty($test['test_results_config']) && is_array($test['test_results_config'])) ? $test['test_results_config'] : [];
$resultUnitNames = (!empty($resultsConfig['test_result_unit']) && is_array($resultsConfig['test_result_unit']))
    ? $resultsConfig['test_result_unit']
    : ($test['result_units'] ?? []);
$testResultUnitId = $resolveMany($resultUnitNames, 'r_generic_test_result_units', 'unit_id', 'unit_name', static fn() => ['unit_status' => 'active']);
$resultsConfig['test_result_unit'] = $testResultUnitId;

// ---------------------------------------------------------------
// Active lookup lists for the form (now include anything just created)
// ---------------------------------------------------------------
$testMethodInfo = $general->getDataByTableAndFields("r_generic_test_methods", ["test_method_id", "test_method_name"], true, "test_method_status='active'");
$categoryInfo = $general->getDataByTableAndFields("r_generic_test_categories", ["test_category_id", "test_category_name"], true, "test_category_status='active'");
$sampleTypeInfo = $general->getDataByTableAndFields("r_generic_sample_types", ["sample_type_id", "sample_type_name"], true, "sample_type_status='active'");
$testReasonInfo = $general->getDataByTableAndFields("r_generic_test_reasons", ["test_reason_id", "test_reason"], true, "test_reason_status='active'");
$testResultUnitInfo = $general->getDataByTableAndFields("r_generic_test_result_units", ["unit_id", "unit_name"], true, "unit_status='active'");
$testFailureReasonInfo = $general->getDataByTableAndFields("r_generic_test_failure_reasons", ["test_failure_reason_id", "test_failure_reason"], true, "test_failure_reason_status='active'");
$sampleRejectionReasonInfo = $general->getDataByTableAndFields("r_generic_sample_rejection_reasons", ["rejection_reason_id", "rejection_reason_name"], true, "rejection_reason_status='active'");
$symptomInfo = $general->getDataByTableAndFields("r_generic_symptoms", ["symptom_id", "symptom_name"], true, "symptom_status='active'");

// ---------------------------------------------------------------
// Auto-suffix any name that collides with an existing test type
// ---------------------------------------------------------------
$nameExists = function (string $field, ?string $val) use ($db): bool {
    if ($val === null || trim($val) === '') {
        return false;
    }
    $row = $db->rawQueryOne("SELECT 1 AS x FROM r_test_types WHERE $field = ? LIMIT 1", [trim($val)]);
    return !empty($row);
};

$uniqueText = function (string $field, string $base) use ($nameExists): string {
    $base = trim($base);
    if ($base === '' || !$nameExists($field, $base)) {
        return $base;
    }
    $i = 1;
    do {
        $candidate = $base . ' (Imported' . ($i > 1 ? ' ' . $i : '') . ')';
        $i++;
    } while ($nameExists($field, $candidate) && $i < 1000);
    return $candidate;
};

$importNotices = [];

$prefillStandardName = $uniqueText('test_standard_name', (string) ($test['test_standard_name'] ?? ''));
$prefillGenericName = $uniqueText('test_generic_name', (string) ($test['test_generic_name'] ?? ''));

$shortBase = preg_replace('/[^A-Z0-9-]/', '', strtoupper((string) ($test['test_short_code'] ?? '')));
if ($shortBase === '' || !$nameExists('test_short_code', $shortBase)) {
    $prefillShortCode = $shortBase;
} else {
    $i = 1;
    do {
        $candidate = $shortBase . '-IMP' . ($i > 1 ? $i : '');
        $i++;
    } while ($nameExists('test_short_code', $candidate) && $i < 1000);
    $prefillShortCode = $candidate;
}

if (
    $prefillStandardName !== (string) ($test['test_standard_name'] ?? '')
    || $prefillGenericName !== (string) ($test['test_generic_name'] ?? '')
    || $prefillShortCode !== $shortBase
) {
    $importNotices[] = _translate("A test with the same name or short code already exists, so the imported values were adjusted. Review and rename as needed before saving.");
}

$prefillLoincCode = (string) ($test['test_loinc_code'] ?? '');
if ($prefillLoincCode !== '' && $nameExists('test_loinc_code', $prefillLoincCode)) {
    $prefillLoincCode = '';
    $importNotices[] = _translate("The LOINC code was already in use and has been cleared.");
}

// ---------------------------------------------------------------
// Variables consumed by the shared form partial
// ---------------------------------------------------------------
$status = (($test['test_status'] ?? 'active') === 'inactive') ? 'inactive' : 'active';
$testTypeInfo = [
    'test_type_id' => 0,
    'test_category' => $categoryId,
    'test_status' => $status,
];

$testAttr = (!empty($test['test_form_config']) && is_array($test['test_form_config'])) ? $test['test_form_config'] : [];

$testResultAttribute = $resultsConfig;
$testResultAttribute['result'] = $testResultAttribute['result'] ?? [];
$testResultAttribute['quantitative_result'] = $testResultAttribute['quantitative_result'] ?? [];

$formHeading = _translate("Import Test Type");
// No local source row to exclude from the uniqueness check on import.
$uniqueExclusion = 'null';

if (!empty($importNotices)) {
    $noticeJs = json_encode(implode("\n", $importNotices), JSON_UNESCAPED_UNICODE);
    echo "<script>document.addEventListener('DOMContentLoaded',function(){try{toast.success($noticeJs);}catch(e){alert($noticeJs);}});</script>";
}

try {
    require __DIR__ . '/_test-type-form.php';
} catch (Throwable $e) {
    LoggerUtility::logError($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
    throw $e;
}
