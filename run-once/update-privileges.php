<?php

use App\Registries\ContainerRegistry;

require_once(__DIR__ . '/../bootstrap.php');

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

$sharedPrivileges = [
    '/import-result/imported-results.php?t=vl' => '/import-result/import-file.php?t=vl',
    '/import-result/imported-results.php?t=eid' => '/import-result/import-file.php?t=eid',
    '/import-result/imported-results.php?t=covid19' => '/import-result/import-file.php?t=covid19',
    '/import-result/imported-results.php?t=hepatitis' => '/import-result/import-file.php?t=hepatitis',
    '/import-result/imported-results.php?t=tb' => '/import-result/import-file.php?t=tb',
    '/import-result/imported-results.php?t=generic-tests' => '?t=generic-tests',
    '/import-result/importedStatistics.php?t=vl' => '/import-result/import-file.php?t=vl',
    '/import-result/importedStatistics.php?t=eid' => '/import-result/import-file.php?t=eid',
    '/import-result/importedStatistics.php?t=covid19' => '/import-result/import-file.php?t=covid19',
    '/import-result/importedStatistics.php?t=hepatitis' => '/import-result/import-file.php?t=hepatitis',
    '/import-result/importedStatistics.php?t=tb' => '/import-result/import-file.php?t=tb',
    '/import-result/importedStatistics.php?t=generic-tests' => '?t=generic-tests',
    'mapTestType.php' => 'addFacility.php',
    'implementation-partners.php' => 'geographical-divisions-details.php',
    'add-implementation-partners.php' => 'geographical-divisions-details.php',
    'edit-implementation-partners.php' => 'geographical-divisions-details.php',
    'funding-sources.php' => 'geographical-divisions-details.php',
    'add-funding-sources.php' => 'geographical-divisions-details.php',
    'edit-funding-sources.php' => 'geographical-divisions-details.php'
];

//Generic Tests Module Shared Privileges
$sharedGenericPrivileges = [
    '/batch/delete-batch.php?type=generic-tests' => '/batch/edit-batch.php?type=generic-tests',
    '/batch/generate-batch-pdf.php?type=generic-tests' => '/batch/batches.php?type=generic-tests',
    '/batch/add-batch-position.php?type=generic-tests' => '/batch/add-batch.php?type=generic-tests',
    '/batch/edit-batch-position.php?type=generic-tests' => '/batch/edit-batch.php?type=generic-tests',
    '/generic-tests/results/update-generic-test-result.php' => '/generic-tests/results/generic-test-results.php',
    '/generic-tests/configuration/add-test-type.php' => '/generic-tests/configuration/test-type.php',
    '/generic-tests/configuration/edit-test-type.php' => '/generic-tests/configuration/test-type.php',
    '/generic-tests/configuration/sample-types/generic-add-sample-type.php' => '/generic-tests/configuration/sample-types/generic-sample-type.php',
    '/generic-tests/configuration/sample-types/generic-edit-sample-type.php' => '/generic-tests/configuration/sample-types/generic-sample-type.php',
    '/generic-tests/configuration/testing-reasons/generic-add-testing-reason.php' => '/generic-tests/configuration/testing-reasons/generic-testing-reason.php',
    '/generic-tests/configuration/testing-reasons/generic-edit-testing-reason.php' => '/generic-tests/configuration/testing-reasons/generic-testing-reason.php',
    '/generic-tests/configuration/symptoms/generic-add-symptoms.php' => '/generic-tests/configuration/symptoms/generic-symptoms.php',
    '/generic-tests/configuration/symptoms/generic-edit-symptoms.php' => '/generic-tests/configuration/symptoms/generic-symptoms.php',
    '/generic-tests/configuration/sample-rejection-reasons/generic-add-sample-rejection-reasons.php' => '/generic-tests/configuration/sample-rejection-reasons/generic-sample-rejection-reasons.php',
    '/generic-tests/configuration/sample-rejection-reasons/generic-edit-sample-rejection-reasons.php' => '/generic-tests/configuration/sample-rejection-reasons/generic-sample-rejection-reasons.php',
    '/generic-tests/configuration/test-failure-reasons/generic-add-test-failure-reason.php' => '/generic-tests/configuration/test-failure-reasons/generic-test-failure-reason.php',
    '/generic-tests/configuration/test-failure-reasons/generic-edit-test-failure-reason.php' => '/generic-tests/configuration/test-failure-reasons/generic-test-failure-reason.php',
    '/generic-tests/configuration/test-result-units/generic-add-test-result-units.php' => '/generic-tests/configuration/test-result-units/generic-test-result-units.php',
    '/generic-tests/configuration/test-result-units/generic-edit-test-result-units.php' => '/generic-tests/configuration/test-result-units/generic-test-result-units.php',
    '/generic-tests/configuration/test-methods/generic-add-test-methods.php' => '/generic-tests/configuration/test-methods/generic-test-methods.php',
    '/generic-tests/configuration/test-methods/generic-edit-test-methods.php' => '/generic-tests/configuration/test-methods/generic-test-methods.php',
    '/generic-tests/configuration/test-categories/generic-add-test-categories.php' => '/generic-tests/configuration/test-categories/generic-test-categories.php',
    '/generic-tests/configuration/test-categories/generic-edit-test-categories.php' => '/generic-tests/configuration/test-categories/generic-test-categories.php',
];

$sharedPrivileges = array_merge($sharedPrivileges, $sharedGenericPrivileges);

//VL Module Shared Privileges
$sharedVLPrivileges = [
    '/batch/delete-batch.php?type=vl' => '/batch/edit-batch.php?type=vl',
    '/batch/generate-batch-pdf.php?type=vl' => '/batch/batches.php?type=vl',
    '/batch/add-batch-position.php?type=vl' => '/batch/add-batch.php?type=vl',
    '/batch/edit-batch-position.php?type=vl' => '/batch/edit-batch.php?type=vl',
    '/vl/results/updateVlTestResult.php' => '/vl/results/vlTestResult.php',
    '/vl/results/vl-failed-results.php' => '/vl/results/vlTestResult.php',
    '/vl/reference/add-vl-art-code-details.php' => '/vl/reference/vl-art-code-details.php',
    '/vl/reference/edit-vl-art-code-details.php' => '/vl/reference/vl-art-code-details.php',
    '/vl/reference/add-vl-results.php' => '/vl/reference/vl-art-code-details.php',
    '/vl/reference/edit-vl-results.php' => '/vl/reference/vl-art-code-details.php',
    '/vl/reference/vl-sample-rejection-reasons.php' => '/vl/reference/vl-art-code-details.php',
    '/vl/reference/add-vl-sample-rejection-reasons.php' => '/vl/reference/vl-art-code-details.php',
    '/vl/reference/edit-vl-sample-rejection-reasons.php' => '/vl/reference/vl-art-code-details.php',
    '/vl/reference/vl-sample-type.php' => '/vl/reference/vl-art-code-details.php',
    '/vl/reference/edit-vl-sample-type.php' => '/vl/reference/vl-art-code-details.php',
    '/vl/reference/add-vl-sample-type.php' => '/vl/reference/vl-art-code-details.php',
    '/vl/reference/vl-test-reasons.php' => '/vl/reference/vl-art-code-details.php',
    '/vl/reference/add-vl-test-reasons.php' => '/vl/reference/vl-art-code-details.php',
    '/vl/reference/edit-vl-test-reasons.php' => '/vl/reference/vl-art-code-details.php',
    '/vl/reference/vl-test-failure-reasons.php' => '/vl/reference/vl-art-code-details.php',
    '/vl/referencea/dd-vl-test-failure-reason.php' => '/vl/reference/vl-art-code-details.php',
    '/vl/reference/edit-vl-test-failure-reason.php' => '/vl/reference/vl-art-code-details.php',
    '/vl/program-management/vlTestingTargetReport.php' => '/vl/program-management/vlMonthlyThresholdReport.php',
    '/vl/program-management/vlSuppressedTargetReport.php' => '/vl/program-management/vlMonthlyThresholdReport.php'
];

$sharedPrivileges = array_merge($sharedPrivileges, $sharedVLPrivileges);

//EID Module Shared Privileges
$sharedEIDPrivileges = [
    '/batch/delete-batch.php?type=eid' => '/batch/edit-batch.php?type=eid',
    '/batch/generate-batch-pdf.php?type=eid' => '/batch/batches.php?type=eid',
    '/batch/add-batch-position.php?type=eid' => '/batch/add-batch.php?type=eid',
    '/batch/edit-batch-position.php?type=eid' => '/batch/edit-batch.php?type=eid',
    '/eid/results/eid-update-result.php' => '/eid/results/eid-manual-results.php',
    '/eid/results/eid-failed-results.php' => '/eid/results/eid-manual-results.php',
    '/eid/requests/eid-bulk-import-request.php' => '/eid/requests/eid-add-request.php',
    '/eid/reference/eid-sample-rejection-reasons.php' => '/eid/reference/eid-sample-type.php',
    '/eid/reference/add-eid-sample-rejection-reasons.php' => '/eid/reference/eid-sample-type.php',
    'edit-eid-sample-rejection-reasons.php' => '/eid/reference/eid-sample-type.php',
    '/eid/reference/add-eid-sample-type.php' => '/eid/reference/eid-sample-type.php',
    '/eid/reference/edit-eid-sample-type.php' => '/eid/reference/eid-sample-type.php',
    '/eid/reference/eid-test-reasons.php' => '/eid/reference/eid-sample-type.php',
    '/eid/reference/add-eid-test-reasons.php' => '/eid/reference/eid-sample-type.php',
    '/eid/reference/edit-eid-test-reasons.php' => '/eid/reference/eid-sample-type.php',
    '/eid/reference/eid-results.php' => '/eid/reference/eid-sample-type.php',
    '/eid/reference/add-eid-results.php' => '/eid/reference/eid-sample-type.php',
    '/eid/reference/edit-eid-results.php' => '/eid/reference/eid-sample-type.php',
    '/eid/management/eidTestingTargetReport.php' => '/eid/management/eidMonthlyThresholdReport.php',
    '/eid/management/eidSuppressedTargetReport.php' => '/eid/management/eidMonthlyThresholdReport.php'
];
$sharedPrivileges = array_merge($sharedPrivileges, $sharedEIDPrivileges);

//Covid19 Module Shared Privileges
$sharedCovid19Privileges = [
    '/batch/delete-batch.php?type=covid19' => '/batch/edit-batch.php?type=covid19',
    '/batch/generate-batch-pdf.php?type=covid19' => '/batch/batches.php?type=covid19',
    '/batch/add-batch-position.php?type=covid19' => '/batch/add-batch.php?type=covid19',
    '/batch/edit-batch-position.php?type=covid19' => '/batch/edit-batch.php?type=covid19',
    '/covid-19/mail/mail-covid-19-results.php' => '/covid-19/results/covid-19-print-results.php',
    '/covid-19/mail/covid-19-result-mail-confirm.php' => '/covid-19/results/covid-19-print-results.php',
    '/covid-19/results/covid-19-update-result.php' => '/covid-19/results/covid-19-manual-results.php',
    '/covid-19/results/covid-19-failed-results.php' => '/covid-19/results/covid-19-manual-results.php',
    '/covid-19/requests/covid-19-bulk-import-request.php' => '/covid-19/requests/covid-19-add-request.php',
    '/covid-19/requests/covid-19-quick-add.php' => '/covid-19/requests/covid-19-add-request.php',
    '/covid-19/reference/covid19-sample-rejection-reasons.php' => '/covid-19/reference/covid19-sample-type.php',
    '/covid-19/reference/add-covid19-sample-rejection-reason.php' => '/covid-19/reference/covid19-sample-type.php',
    '/covid-19/reference/covid19-comorbidities.php' => '/covid-19/reference/covid19-sample-type.php',
    '/covid-19/reference/add-covid19-comorbidities.php' => '/covid-19/reference/covid19-sample-type.php',
    '/covid-19/reference/covid19-symptoms.php' => '/covid-19/reference/covid19-sample-type.php',
    '/covid-19/reference/add-covid19-sample-type.php' => '/covid-19/reference/covid19-sample-type.php',
    '/covid-19/reference/covid19-test-symptoms.php' => '/covid-19/reference/covid19-sample-type.php',
    '/covid-19/reference/add-covid19-symptoms.php' => '/covid-19/reference/covid19-sample-type.php',
    '/covid-19/reference/covid19-test-reasons.php' => '/covid-19/reference/covid19-sample-type.php',
    '/covid-19/reference/add-covid19-test-reasons.php' => '/covid-19/reference/covid19-sample-type.php',
    '/covid-19/reference/covid19-results.php' => '/covid-19/reference/covid19-sample-type.php',
    '/covid-19/reference/add-covid19-results.php' => '/covid-19/reference/covid19-sample-type.php',
    '/covid-19/management/covid19TestingTargetReport.php' => '/covid-19/management/covid19MonthlyThresholdReport.php',
    '/covid-19/management/covid19SuppressedTargetReport.php' => '/covid-19/management/covid19MonthlyThresholdReport.php',
    '/covid-19/interop/dhis2/covid-19-init.php' => '/covid-19/requests/covid-19-dhis2.php',
    '/covid-19/interop/dhis2/covid-19-send.php' => '/covid-19/requests/covid-19-dhis2.php',
    '/covid-19/interop/dhis2/covid-19-receive.php' => '/covid-19/requests/covid-19-dhis2.php',
    '/covid-19/reference/covid19-qc-test-kits.php' => '/covid-19/reference/covid19-sample-type.php',
    '/covid-19/reference/add-covid19-qc-test-kit.php' => '/covid-19/reference/covid19-sample-type.php',
    '/covid-19/reference/edit-covid19-qc-test-kit.php' => '/covid-19/reference/covid19-sample-type.php'
];
$sharedPrivileges = array_merge($sharedPrivileges, $sharedCovid19Privileges);

//Hepatitis Module Shared Privileges
$sharedHepPrivileges = [
    '/batch/delete-batch.php?type=hepatitis' => '/batch/edit-batch.php?type=hepatitis',
    '/batch/generate-batch-pdf.php?type=hepatitis' => '/batch/batches.php?type=hepatitis',
    '/batch/add-batch-position.php?type=hepatitis' => '/batch/add-batch.php?type=hepatitis',
    '/batch/edit-batch-position.php?type=hepatitis' => '/batch/edit-batch.php?type=hepatitis',
    '/hepatitis/results/hepatitis-update-result.php' => '/hepatitis/results/hepatitis-manual-results.php',
    '/hepatitis/results/hepatitis-failed-results.php' => '/hepatitis/results/hepatitis-manual-results.php',
    '/hepatitis/mail/mail-hepatitis-results.php' => '/hepatitis/results/hepatitis-print-results.php',
    'hepatitis-result-mail-confirm.php' => '/hepatitis/results/hepatitis-print-results.php',
    '/hepatitis/reference/hepatitis-sample-rejection-reasons.php' => '/hepatitis/reference/hepatitis-sample-type.php',
    '/hepatitis/reference/add-hepatitis-sample-rejection-reasons.php' => '/hepatitis/reference/hepatitis-sample-type.php',
    '/hepatitis/reference/hepatitis-comorbidities.php' => '/hepatitis/reference/hepatitis-sample-type.php',
    '/hepatitis/reference/add-hepatitis-comorbidities.php' => '/hepatitis/reference/hepatitis-sample-type.php',
    '/hepatitis/reference/add-hepatitis-sample-type.php' => '/hepatitis/reference/hepatitis-sample-type.php',
    '/hepatitis/reference/hepatitis-results.php' => '/hepatitis/reference/hepatitis-sample-type.php',
    '/hepatitis/reference/add-hepatitis-results.php' => '/hepatitis/reference/hepatitis-sample-type.php',
    '/hepatitis/reference/hepatitis-risk-factors.php' => '/hepatitis/reference/hepatitis-sample-type.php',
    '/hepatitis/reference/add-hepatitis-risk-factors.php' => '/hepatitis/reference/hepatitis-sample-type.php',
    '/hepatitis/reference/hepatitis-test-reasons.php' => '/hepatitis/reference/hepatitis-sample-type.php',
    '/hepatitis/reference/add-hepatitis-test-reasons.php' => '/hepatitis/reference/hepatitis-sample-type.php',
    '/hepatitis/interop/dhis2/hepatitis-init.php' => '/hepatitis/requests/hepatitis-dhis2.php',
    '/hepatitis/interop/dhis2/hepatitis-send.php' => '/hepatitis/requests/hepatitis-dhis2.php',
    '/hepatitis/interop/dhis2/hepatitis-receive.php' => '/hepatitis/requests/hepatitis-dhis2.php'
];
$sharedPrivileges = array_merge($sharedPrivileges, $sharedHepPrivileges);

//TB Module Shared Privileges
$sharedTbPrivileges = [
    '/batch/delete-batch.php?type=tb' => '/batch/edit-batch.php?type=tb',
    '/batch/generate-batch-pdf.php?type=tb' => '/batch/batches.php?type=tb',
    '/batch/add-batch-position.php?type=tb' => '/batch/add-batch.php?type=tb',
    '/batch/edit-batch-position.php?type=tb' => '/batch/edit-batch.php?type=tb',
    '/tb/results/tb-update-result.php' => '/tb/results/tb-manual-results.php',
    '/tb/results/tb-failed-results.php' => '/tb/results/tb-manual-results.php',
    '/tb/reference/add-tb-sample-type.php' => 'tb-sample-type.php',
    '/tb/reference/tb-sample-rejection-reasons.php' => 'tb-sample-type.php',
    '/tb/reference/add-tb-sample-rejection-reason.php' => 'tb-sample-type.php',
    '/tb/reference/tb-test-reasons.php' => 'tb-sample-type.php',
    '/tb/reference/add-tb-test-reasons.php' => 'tb-sample-type.php',
    '/tb/reference/tb-results.php' => 'tb-sample-type.php',
    '/tb/reference/add-tb-results.php' => 'tb-sample-type.php',
];
$sharedPrivileges = array_merge($sharedPrivileges, $sharedTbPrivileges);

$sql = "UPDATE `privileges` SET `shared_privileges` = NULL";
$db->rawQuery($sql);


$privilegesToUpdate = [];
foreach ($sharedPrivileges as $key => $value) {
    if (!isset($privilegesToUpdate[$value])) {
        $privilegesToUpdate[$value] = [];
    }
    $privilegesToUpdate[$value][] = $key;
}

foreach ($privilegesToUpdate as $privilegeName => $sharedPrivileges) {
    $sharedPrivilegesJson = json_encode($sharedPrivileges);
    $sql = "UPDATE `privileges` SET `shared_privileges` = ? WHERE privilege_name = ?";
    $db->rawQuery($sql, [$sharedPrivilegesJson, $privilegeName]);
}
