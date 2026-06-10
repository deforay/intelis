<?php

namespace App\Services;

use App\Registries\ContainerRegistry;
use App\Services\GenericTestsService;
use App\Services\CommonService;

require_once APPLICATION_PATH . '/header.php';

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
/** @var GenericTestsService $generic */
$generic = ContainerRegistry::get(GenericTestsService::class);

// Non-AJAX lookups the add form needs up front. Category / test-reason / rejection /
// failure-reason lists are AJAX-driven on the add form, so the shared partial leaves
// those empty here and the page fills them client-side.
$sampleTypeInfo = $general->getDataByTableAndFields("r_generic_sample_types", ["sample_type_id", "sample_type_name"], true, "sample_type_status='active'");
$symptomInfo = $general->getDataByTableAndFields("r_generic_symptoms", ["symptom_id", "symptom_name"], true, "symptom_status='active'");
$testResultUnitInfo = $general->getDataByTableAndFields("r_generic_test_result_units", ["unit_id", "unit_name"], true, "unit_status='active'");

$formMode = 'add';
$formHeading = _translate("Add Test Type");
$formAction = 'addTestTypeHelper.php';

require __DIR__ . '/_test-type-form.php';
