<?php

use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

// only run from command line
if (php_sapi_name() !== 'cli') {
    exit(0);
}

require_once(__DIR__ . '/../bootstrap.php');

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$deleteCd4 = $db->rawQuery("DELETE FROM s_app_menu WHERE module='cd4'");
$getCD4Config = $db->rawQueryOne("SELECT id FROM s_app_menu WHERE display_text='CD4 Config'");
$getCD4ConfigIdDelete = $getCD4Config['id'] ?? null;

if (!empty($getCD4ConfigIdDelete)) {
    $deleteCd4ConfigChildren = $db->rawQuery("DELETE FROM s_app_menu WHERE parent_id = $getCD4ConfigIdDelete");
    $deleteCd4Config = $db->rawQuery("DELETE FROM s_app_menu WHERE id = $getCD4ConfigIdDelete");
}

/** Insert CD4 Header Menu */
$insertHeader = $db->rawQuery("INSERT INTO `s_app_menu` (`id`, `module`, `sub_module`, `is_header`, `display_text`, `link`, `inner_pages`, `show_mode`, `icon`, `has_children`, `additional_class_names`, `parent_id`, `display_order`, `status`, `updated_datetime`) VALUES (NULL, 'cd4', NULL, 'yes', 'CLUSTERS OF DIFFERENTIATION 4', NULL, NULL, 'always', NULL, 'yes', 'header', '0', '179', 'active', CURRENT_TIMESTAMP)");

/** Insert Submenu Of CD4 header */
$headerId = $db->getInsertId();


$insertRequestMenu = $db->rawQuery("INSERT INTO `s_app_menu` (`id`, `module`, `sub_module`, `is_header`, `display_text`, `link`, `inner_pages`, `show_mode`, `icon`, `has_children`, `additional_class_names`, `parent_id`, `display_order`, `status`, `updated_datetime`) VALUES (NULL, 'cd4', NULL, 'no', 'Request Management', NULL, NULL, 'always', 'fa-solid fa-pen-to-square', 'yes', 'treeview request', $headerId, 1, 'active', CURRENT_TIMESTAMP);");
$requestMenuId = $db->getInsertId();

$insertResultMenu = $db->rawQuery("INSERT INTO `s_app_menu` (`id`, `module`, `sub_module`, `is_header`, `display_text`, `link`, `inner_pages`, `show_mode`, `icon`, `has_children`, `additional_class_names`, `parent_id`, `display_order`, `status`, `updated_datetime`) VALUES (NULL, 'cd4', NULL, 'no', 'Test Result Management', NULL, NULL, 'always', 'fa-solid fa-list-check', 'yes', 'treeview test', $headerId, 2, 'active', CURRENT_TIMESTAMP)");
$resultMenuId = $db->getInsertId();

$insertManagementMenu = $db->rawQuery("INSERT INTO `s_app_menu` (`id`, `module`, `sub_module`, `is_header`, `display_text`, `link`, `inner_pages`, `show_mode`, `icon`, `has_children`, `additional_class_names`, `parent_id`, `display_order`, `status`, `updated_datetime`) VALUES (NULL, 'cd4', NULL, 'no', 'Management', NULL, NULL, 'always', 'fa-solid fa-book', 'yes', 'treeview program', $headerId, 3, 'active', CURRENT_TIMESTAMP)");
$managementMenuId = $db->getInsertId();

/** Adding Request Submenu */
$db->rawQuery("INSERT INTO `s_app_menu` (`id`, `module`, `sub_module`, `is_header`, `display_text`, `link`, `inner_pages`, `show_mode`, `icon`, `has_children`, `additional_class_names`, `parent_id`, `display_order`, `status`, `updated_datetime`) VALUES (NULL, 'cd4', NULL, 'no', 'View Test Request', '/cd4/requests/cd4-requests.php', '/cd4/requests/cd4-edit-request.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu cd4RequestMenu', $requestMenuId, 4, 'active', CURRENT_TIMESTAMP)");
$db->rawQuery("INSERT INTO `s_app_menu` (`id`, `module`, `sub_module`, `is_header`, `display_text`, `link`, `inner_pages`, `show_mode`, `icon`, `has_children`, `additional_class_names`, `parent_id`, `display_order`, `status`, `updated_datetime`) VALUES (NULL, 'cd4', NULL, 'no', 'Add New Request', '/cd4/requests/cd4-add-request.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu addVlRequestMenu', $requestMenuId, 5, 'active', CURRENT_TIMESTAMP)");
$db->rawQuery("INSERT INTO `s_app_menu` (`id`, `module`, `sub_module`, `is_header`, `display_text`, `link`, `inner_pages`, `show_mode`, `icon`, `has_children`, `additional_class_names`, `parent_id`, `display_order`, `status`, `updated_datetime`) VALUES (NULL, 'cd4', NULL, 'no', 'Manage Batch', '/batch/batches.php?type=cd4', '/batch/add-batch.php?type=cd4,/batch/edit-batch.php?type=cd4,/batch/add-batch-position.php?type=cd4,/batch/edit-batch-position.php?type=cd4', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu cd4BatchCodeMenu', $requestMenuId, 6, 'active', CURRENT_TIMESTAMP)");
$db->rawQuery("INSERT INTO `s_app_menu` (`id`, `module`, `sub_module`, `is_header`, `display_text`, `link`, `inner_pages`, `show_mode`, `icon`, `has_children`, `additional_class_names`, `parent_id`, `display_order`, `status`, `updated_datetime`) VALUES (NULL, 'cd4', NULL, 'no', 'CD4 Manifest', '/specimen-referral-manifest/view-manifests.php?t=cd4', '/specimen-referral-manifest/add-manifest.php?t=cd4,/specimen-referral-manifest/edit-manifest.php?t=cd4,/specimen-referral-manifest/move-manifest.php?t=cd4', 'sts', 'fa-solid fa-caret-right', 'no', 'allMenu cd4BatchCodeMenu', $requestMenuId, 7, 'active', CURRENT_TIMESTAMP)");
$db->rawQuery("INSERT INTO `s_app_menu` (`id`, `module`, `sub_module`, `is_header`, `display_text`, `link`, `inner_pages`, `show_mode`, `icon`, `has_children`, `additional_class_names`, `parent_id`, `display_order`, `status`, `updated_datetime`) VALUES (NULL, 'cd4', NULL, 'no', 'Add Samples from Manifest', '/cd4/requests/add-samples-from-manifest.php', NULL, 'lis', 'fa-solid fa-caret-right', 'no', 'allMenu addSamplesFromManifestMenu', $requestMenuId, 8, 'active', CURRENT_TIMESTAMP)");

/** Adding Result Submenu */
$db->rawQuery("INSERT INTO `s_app_menu` (`id`, `module`, `sub_module`, `is_header`, `display_text`, `link`, `inner_pages`, `show_mode`, `icon`, `has_children`, `additional_class_names`, `parent_id`, `display_order`, `status`, `updated_datetime`) VALUES (NULL, 'cd4', NULL, 'no', 'Enter Result Manually', '/cd4/results/cd4-manual-results.php', '/cd4/results/cd4-update-result.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu cd4ResultStatus', $resultMenuId, 9, 'active', CURRENT_TIMESTAMP)");
$db->rawQuery("INSERT INTO `s_app_menu` (`id`, `module`, `sub_module`, `is_header`, `display_text`, `link`, `inner_pages`, `show_mode`, `icon`, `has_children`, `additional_class_names`, `parent_id`, `display_order`, `status`, `updated_datetime`) VALUES (NULL, 'cd4', NULL, 'no', 'Manage Results Status', '/cd4/results/cd4-result-status.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu batchCodeMenu', $resultMenuId, 10, 'active', CURRENT_TIMESTAMP)");
$db->rawQuery("INSERT INTO `s_app_menu` (`id`, `module`, `sub_module`, `is_header`, `display_text`, `link`, `inner_pages`, `show_mode`, `icon`, `has_children`, `additional_class_names`, `parent_id`, `display_order`, `status`, `updated_datetime`) VALUES (NULL, 'cd4', NULL, 'no', 'Import Result From File', '/import-result/import-file.php?t=cd4', '/import-result/imported-results.php?t=cd4,/import-result/importedStatistics.php?t=cd4', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu cd4ImportResultMenu', $resultMenuId, 11, 'active', CURRENT_TIMESTAMP)");
$db->rawQuery("INSERT INTO `s_app_menu` (`id`, `module`, `sub_module`, `is_header`, `display_text`, `link`, `inner_pages`, `show_mode`, `icon`, `has_children`, `additional_class_names`, `parent_id`, `display_order`, `status`, `updated_datetime`) VALUES (NULL, 'cd4', NULL, 'no', 'Failed/Hold Samples', '/cd4/results/cd4-failed-results.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu cd4FailedResultsMenu', $resultMenuId, 12, 'active', CURRENT_TIMESTAMP)");
$db->rawQuery("INSERT INTO `s_app_menu` (`id`, `module`, `sub_module`, `is_header`, `display_text`, `link`, `inner_pages`, `show_mode`, `icon`, `has_children`, `additional_class_names`, `parent_id`, `display_order`, `status`, `updated_datetime`) VALUES (NULL, 'cd4', NULL, 'no', 'E-mail Test Result', '/cd4/results/email-results.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu cd4ResultMailMenu', $resultMenuId, 13, 'active', CURRENT_TIMESTAMP)");

/** Adding Management Submenu */
$db->rawQuery("INSERT INTO `s_app_menu` (`id`, `module`, `sub_module`, `is_header`, `display_text`, `link`, `inner_pages`, `show_mode`, `icon`, `has_children`, `additional_class_names`, `parent_id`, `display_order`, `status`, `updated_datetime`) VALUES (NULL, 'cd4', NULL, 'no', 'Sample Status Report', '/cd4/management/cd4-sample-status.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu cd4SampleStatus', $managementMenuId, 14, 'active', CURRENT_TIMESTAMP)");
$db->rawQuery("INSERT INTO `s_app_menu` (`id`, `module`, `sub_module`, `is_header`, `display_text`, `link`, `inner_pages`, `show_mode`, `icon`, `has_children`, `additional_class_names`, `parent_id`, `display_order`, `status`, `updated_datetime`) VALUES (NULL, 'cd4', NULL, 'no', 'Export Results', '/cd4/management/cd4-export-data.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu cd4ExportResult', $managementMenuId, 15, 'active', CURRENT_TIMESTAMP)");
$db->rawQuery("INSERT INTO `s_app_menu` (`id`, `module`, `sub_module`, `is_header`, `display_text`, `link`, `inner_pages`, `show_mode`, `icon`, `has_children`, `additional_class_names`, `parent_id`, `display_order`, `status`, `updated_datetime`) VALUES (NULL, 'cd4', NULL, 'no', 'Print Result', '/cd4/results/cd4-print-results.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu cd4PrintResults', $managementMenuId, 16, 'active', CURRENT_TIMESTAMP), (NULL, 'cd4', NULL, 'no', 'Sample Rejection Report', '/cd4/management/cd4-sample-rejection-report.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu cd4SampleRejectionReport', $managementMenuId, 16, 'active', CURRENT_TIMESTAMP), (NULL, 'cd4', NULL, 'no', 'Clinic Report', '/cd4/management/cd4-clinic-report.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu cd4ClinicReport', $managementMenuId, 17, 'active', CURRENT_TIMESTAMP)");

/** Adding CD4 Config */
$getAdminModule = $db->rawQueryOne("SELECT id FROM s_app_menu WHERE display_text='ADMIN'");
$adminModuleId = $getAdminModule['id'];
$db->rawQuery("INSERT INTO `s_app_menu` (`id`, `module`, `sub_module`, `is_header`, `display_text`, `link`, `inner_pages`, `show_mode`, `icon`, `has_children`, `additional_class_names`, `parent_id`, `display_order`, `status`, `updated_datetime`) VALUES (NULL, 'admin', 'cd4', 'no', 'CD4 Config', NULL, NULL, 'always', 'fa-solid fa-eyedropper', 'yes', 'treeview tb-reference-manage', $adminModuleId, 42, 'active', CURRENT_TIMESTAMP)");
$cd4ConfigId = $db->getInsertId();
$db->rawQuery("INSERT INTO `s_app_menu` (`id`, `module`, `sub_module`, `is_header`, `display_text`, `link`, `inner_pages`, `show_mode`, `icon`, `has_children`, `additional_class_names`, `parent_id`, `display_order`, `status`, `updated_datetime`) VALUES (NULL, 'admin', NULL, 'no', 'Sample Type', '/cd4/reference/cd4-sample-type.php', '/cd4/reference/add-cd4-sample-type.php,/cd4/reference/edit-cd4-sample-type.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu cd4-sample-type', $cd4ConfigId, 43, 'active', CURRENT_TIMESTAMP)");
$db->rawQuery("INSERT INTO `s_app_menu` (`id`, `module`, `sub_module`, `is_header`, `display_text`, `link`, `inner_pages`, `show_mode`, `icon`, `has_children`, `additional_class_names`, `parent_id`, `display_order`, `status`, `updated_datetime`) VALUES (NULL, 'admin', NULL, 'no', 'Test Reasons', '/cd4/reference/cd4-test-reasons.php', '/cd4/reference/add-cd4-test-reasons.php,/cd4/reference/edit-cd4-test-reasons.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu vl-test-reasons', $cd4ConfigId, 44, 'active', CURRENT_TIMESTAMP)");
$db->rawQuery("INSERT INTO `s_app_menu` (`id`, `module`, `sub_module`, `is_header`, `display_text`, `link`, `inner_pages`, `show_mode`, `icon`, `has_children`, `additional_class_names`, `parent_id`, `display_order`, `status`, `updated_datetime`) VALUES (NULL, 'admin', NULL, 'no', 'Rejection Reasons', '/cd4/reference/cd4-sample-rejection-reasons.php', '/cd4/reference/add-cd4-sample-rejection-reasons.php,/cd4/reference/edit-cd4-sample-rejection-reasons.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu cd4-test-reasons', $cd4ConfigId, 45, 'active', CURRENT_TIMESTAMP)");

/** Adding Lab storage menu under system settings */
$systemConfig = $db->rawQueryOne("SELECT id FROM s_app_menu WHERE display_text='System Configuration'");
$systemConfigId = $systemConfig['id'];

$db->rawQuery("INSERT INTO `s_app_menu` (`id`, `module`, `sub_module`, `is_header`, `display_text`, `link`, `inner_pages`, `show_mode`, `icon`, `has_children`, `additional_class_names`, `parent_id`, `display_order`, `status`, `updated_datetime`) VALUES (NULL, 'admin', NULL, 'no', 'Lab Storage', '/common/reference/lab-storage.php', '/common/reference/add-lab-storage.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu common-reference-lab-storage', $systemConfigId, 24, 'active', CURRENT_TIMESTAMP)");

echo $db->getLastError();
