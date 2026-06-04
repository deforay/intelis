<?php

namespace App\Services;

use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use App\Services\GenericTestsService;
use Psr\Http\Message\ServerRequestInterface;

require_once APPLICATION_PATH . '/header.php';

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var GenericTestsService $generic */
$generic = ContainerRegistry::get(GenericTestsService::class);

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());
$id = (isset($_GET['id'])) ? base64_decode((string) $_GET['id']) : null;

$tQuery = "SELECT * from r_test_types WHERE test_type_id=?";
$testTypeInfo = $db->rawQueryOne($tQuery, [$id]);
$testAttr = json_decode((string) $testTypeInfo['test_form_config'], true);
$testResultAttribute = json_decode((string) $testTypeInfo['test_results_config'], true);

// $stQuery = "SELECT * from r_generic_sample_types where sample_type_status='active'";
$testMethodInfo = $general->getDataByTableAndFields("r_generic_test_methods", ["test_method_id", "test_method_name"], true, "test_method_status='active'");
$testMethodId = $general->getDataByTableAndFields("generic_test_methods_map", ["test_method_id", "test_method_id"], true, "test_type_id=$id");
$categoryInfo = $general->getDataByTableAndFields("r_generic_test_categories", ["test_category_id", "test_category_name"], true, "test_category_status='active'");

$sampleTypeInfo = $general->getDataByTableAndFields("r_generic_sample_types", ["sample_type_id", "sample_type_name"], true, "sample_type_status='active'");
$testReasonInfo = $general->getDataByTableAndFields("r_generic_test_reasons", ["test_reason_id", "test_reason"], true, "test_reason_status='active'");
$testResultUnitInfo = $general->getDataByTableAndFields("r_generic_test_result_units", ["unit_id", "unit_name"], true, "unit_status='active'");

$testFailureReasonInfo = $general->getDataByTableAndFields("r_generic_test_failure_reasons", ["test_failure_reason_id", "test_failure_reason"], true, "test_failure_reason_status='active'");
$sampleRejectionReasonInfo = $general->getDataByTableAndFields("r_generic_sample_rejection_reasons", ["rejection_reason_id", "rejection_reason_name"], true, "rejection_reason_status='active'");
$symptomInfo = $general->getDataByTableAndFields("r_generic_symptoms", ["symptom_id", "symptom_name"], true, "symptom_status='active'");
$testSampleId = $general->getDataByTableAndFields("generic_test_sample_type_map", ["sample_type_id", "sample_type_id"], true, "test_type_id=$id");
$testReasonId = $general->getDataByTableAndFields("generic_test_reason_map", ["test_reason_id", "test_reason_id"], true, "test_type_id=$id");

$testFailureReasonId = $general->getDataByTableAndFields("generic_test_failure_reason_map", ["test_failure_reason_id", "test_failure_reason_id"], true, "test_type_id=$id");
$rejectionReasonId = $general->getDataByTableAndFields("generic_sample_rejection_reason_map", ["rejection_reason_id", "rejection_reason_id"], true, "test_type_id=$id");
$testSymptomsId = $general->getDataByTableAndFields("generic_test_symptoms_map", ["symptom_id", "symptom_id"], true, "test_type_id=$id");
$testResultUnitId = $general->getDataByTableAndFields("generic_test_result_units_map", ["unit_id", "unit_id"], true, "test_type_id=$id");

// Clone keeps the original "Edit Test Type" heading and leaves the name fields
// blank so the user is forced to pick a fresh, unique name for the copy. The
// uniqueness check excludes the source row being cloned.
$formHeading = _translate("Edit Test Type");
$uniqueExclusion = "test_type_id##" . $testTypeInfo['test_type_id'];

require __DIR__ . '/_test-type-form.php';
