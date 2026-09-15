<?php

declare(strict_types=1);

/**
 * Static check: covered report endpoints never place a raw request value into SQL.
 *
 * These endpoints assemble their queries as strings, and their exports re-run the
 * assembled SQL from the session, so prepared statements are not on the table
 * without a much larger rework. The rule instead is that every request value is
 * encoded at the clause site: (int) casts for ids, $db->inIntList() for IN ()
 * lists, $db->escape() / $db->escapeLike() for text, or a whitelist lookup.
 *
 * All of those share one property this check can see: the concatenation dot (or
 * the interpolation brace) never touches $_POST directly. So a direct
 * `. $_POST[...]`, `$_POST[...] .`, or `{$_POST[...]}` in a covered file is a
 * value that skipped its encoder.
 *
 * This is a guardrail against the pattern creeping back in a copy-paste, not a
 * proof of safety: a value laundered through a plain variable first will not be
 * seen. Runs without a database or a container -- it reads source -- so CI can
 * run it on every push.
 *
 * Usage: php bin/build/check-sql-value-safety.php
 */

const REPO_DIR = __DIR__ . '/../..';

/**
 * Files the rule covers. Grown deliberately: a file is added here after it has
 * been swept, so the check locks in the sweep instead of flagging a backlog.
 *
 * @var list<string>
 */
const COVERED_FILES = [
    'app/vl/program-management/getHighVlResultDetails.php',
    'app/vl/program-management/getSampleStatus.php',
    'app/eid/management/getSampleStatus.php',
    'app/reports/get-sample-status-details.php',
    'app/reports/get-patient-timeline.php',
    'app/reports/sample-status-details.php',
    'app/vl/program-management/getSampleRejectionReport.php',
    'app/vl/program-management/getResultNotAvailable.php',
    'app/vl/program-management/dataQualityCheck.php',
    'app/vl/program-management/getSampleTestingReport.php',
    'app/vl/program-management/export-virologic-failure-report.php',
    'app/eid/management/get-data-export.php',
    'app/eid/management/getPositiveEidResultDetails.php',
    'app/eid/management/getSampleRejectionReport.php',
    'app/eid/management/getResultNotAvailable.php',
    'app/eid/management/dataQualityCheck.php',
    'app/eid/management/eid-sample-testing-report.php',
    'app/eid/management/getEidSampleTATDetails.php',
    'app/eid/management/getEidMonthlyThresholdReport.php',
    'app/eid/management/get-rejected-samples.php',
    'app/eid/management/getPmtctCascadeReport.php',
    'app/eid/management/pmtctCascadeReportExport.php',
    'app/eid/requests/get-request-list.php',
    'app/eid/results/get-failed-results.php',
    'app/eid/results/get-results-for-print.php',
    'app/eid/results/eid-samples-for-manual-result-entry.php',
    'app/eid/results/get-eid-result-status.php',
    'app/cd4/management/get-data-export.php',
    'app/cd4/management/get-positive-cd4-result-details.php',
    'app/cd4/management/get-sample-rejection-report.php',
    'app/cd4/management/get-result-not-available.php',
    'app/cd4/management/data-quality-check.php',
    'app/cd4/management/cd4-sample-testing-report.php',
    'app/cd4/management/get-cd4-sample-tat-details.php',
    'app/cd4/management/get-cd4-monthly-threshold-report.php',
    'app/cd4/management/get-rejected-samples.php',
    'app/cd4/management/get-sample-status.php',
    'app/generic-tests/program-management/get-data-export.php',
    'app/generic-tests/program-management/get-sample-tat-details.php',
    'app/generic-tests/program-management/get-rejection-result.php',
    'app/generic-tests/program-management/generic-tests-sample-testing-report.php',
    'app/generic-tests/program-management/get-sample-status.php',
    'app/tb/management/get-data-export.php',
    'app/tb/management/getPositiveTbResultDetails.php',
    'app/tb/management/getSampleRejectionReport.php',
    'app/tb/management/getResultNotAvailable.php',
    'app/tb/management/getTbSampleTATDetails.php',
    'app/tb/management/get-rejected-samples.php',
    'app/tb/management/tb-sample-testing-report.php',
    'app/tb/management/dataQualityCheck.php',
    'app/tb/management/get-tb-monthly-threshold-report.php',
    'app/tb/management/getTbCascadeReport.php',
    'app/tb/management/getSampleStatus.php',
    'app/hepatitis/management/get-data-export.php',
    'app/hepatitis/management/get-positive-hepatitis-result-details.php',
    'app/hepatitis/management/get-sample-rejection-report.php',
    'app/hepatitis/management/get-result-not-available.php',
    'app/hepatitis/management/get-hepatitis-sample-tat-details.php',
    'app/hepatitis/management/get-rejected-samples.php',
    'app/hepatitis/management/hepatitis-sample-testing-report.php',
    'app/hepatitis/management/data-quality-check.php',
    'app/hepatitis/management/get-hepatitis-monthly-threshold-report.php',
    'app/hepatitis/management/get-sample-status.php',
    'app/covid-19/management/get-data-export.php',
    'app/covid-19/management/getPositiveCovid19ResultDetails.php',
    'app/covid-19/management/getSampleRejectionReport.php',
    'app/covid-19/management/getResultNotAvailable.php',
    'app/covid-19/management/getCovid19SampleTATDetails.php',
    'app/covid-19/management/get-rejected-samples.php',
    'app/covid-19/management/covid-19-sample-testing-report.php',
    'app/covid-19/management/dataQualityCheck.php',
    'app/covid-19/management/getCovid19MonthlyThresholdReport.php',
    'app/covid-19/management/getSampleStatus.php',
    'app/batch/get-batches.php',
    'app/batch/get-samples-batch.php',
    'app/batch/getBatchCodeHelper.php',
    'app/specimen-referral-manifest/get-manifests.php',
    'app/specimen-referral-manifest/get-samples-for-manifest.php',
    'app/specimen-referral-manifest/get-manifest-package-code.php',
    'app/facilities/getFacilityDetails.php',
    'app/facilities/facilityExportInExcel.php',
    'app/admin/monitoring/get-sync-status-details.php',
    'app/admin/monitoring/get-api-sync-history-list.php',
    'app/admin/monitoring/get-samplewise-report.php',
    'app/vl/reference/get-vl-sample-type-helper.php',
    'app/cd4/reference/get-cd4-sample-type-helper.php',
    'app/eid/reference/get-eid-sample-type-helper.php',
    'app/hepatitis/reference/get-hepatitis-sample-type-helper.php',
    'app/covid-19/reference/getCovid19SampleTypeDetails.php',
    'app/tb/reference/getTbSampleTypeDetails.php',
    'app/vl/reference/get-vl-sample-rejection-reasons-helper.php',
    'app/cd4/reference/get-cd4-sample-rejection-reasons-helper.php',
    'app/eid/reference/get-eid-sample-rejection-reasons-helper.php',
    'app/hepatitis/reference/get-hepatitis-sample-rejection-reasons-helper.php',
    'app/covid-19/reference/getCovid19SampleRejectionDetails.php',
    'app/tb/reference/getTbSampleRejectionDetails.php',
    'app/eid/qa/get-qa-monitoring-data.php',
    // Listing, lookup and PDF endpoints across every test type, swept 2026-09-15:
    // filters through ListingFilterClauseBuilder, paging through DataTableUtility::paging().
    'app/admin/api-dashboard/get-missing-samples-detail.php',
    'app/admin/monitoring/get-sample-code-list.php',
    'app/admin/monitoring/get-test-results-report.php',
    'app/batch/delete-batch.php',
    'app/cd4/reference/get-cd4-test-reasons-helper.php',
    'app/cd4/requests/get-request-list.php',
    'app/cd4/results/email-results-confirm.php',
    'app/cd4/results/generate-result-pdf.php',
    'app/cd4/results/get-cd4-result-status.php',
    'app/cd4/results/get-failed-results.php',
    'app/cd4/results/get-manual-results.php',
    'app/cd4/results/get-results-for-print.php',
    'app/cd4/results/getRequestSampleCodeDetails.php',
    'app/common/reference/get-corrective-actions-helper.php',
    'app/common/reference/get-funding-sources-helper.php',
    'app/common/reference/get-geographical-divisions-helper.php',
    'app/common/reference/get-implementation-partners-helper.php',
    'app/common/reference/get-lab-storage-helper.php',
    'app/covid-19/mail/get-samples-for-mail.php',
    'app/covid-19/reference/edit-covid19-qc-test-kit.php',
    'app/covid-19/reference/get-covid19-qc-test-kits-helper.php',
    'app/covid-19/reference/get-covid19-results-helper.php',
    'app/covid-19/reference/getCovid19ComorbiditiesDetails.php',
    'app/covid-19/reference/getCovid19SymptomDetails.php',
    'app/covid-19/reference/getCovid19TestReasonsDetails.php',
    'app/covid-19/requests/check-sample-duplicate.php',
    'app/covid-19/requests/get-province-district-list.php',
    'app/covid-19/requests/get-request-list.php',
    'app/covid-19/requests/patientModal.php',
    'app/covid-19/results/covid-19-add-confirmation-manifest-helper.php',
    'app/covid-19/results/covid-19-samples-for-manual-result-entry.php',
    'app/covid-19/results/covid-19-update-result.php',
    'app/covid-19/results/edit-covid-19-qc-data.php',
    'app/covid-19/results/email-results-confirm.php',
    'app/covid-19/results/generate-result-pdf.php',
    'app/covid-19/results/get-covid-19-result-status.php',
    'app/covid-19/results/get-covid19-qc-data-list.php',
    'app/covid-19/results/get-failed-results.php',
    'app/covid-19/results/get-record-confirmatory-tests.php',
    'app/covid-19/results/get-results-for-print.php',
    'app/covid-19/results/getConfirmManifestInGridHelper.php',
    'app/covid-19/results/getRequestSampleCodeDetails.php',
    'app/covid-19/results/update-record-confirmatory-tests.php',
    'app/eid/reference/get-eid-results-helper.php',
    'app/eid/reference/get-eid-test-reasons-helper.php',
    'app/eid/requests/check-sample-duplicate.php',
    'app/eid/requests/checkPatientExist.php',
    'app/eid/results/email-results-confirm.php',
    'app/eid/results/generate-result-pdf.php',
    'app/eid/results/getRequestSampleCodeDetails.php',
    'app/generic-tests/configuration/getTestTypeDetails.php',
    'app/generic-tests/configuration/sample-rejection-reasons/generic-edit-sample-rejection-reasons.php',
    'app/generic-tests/configuration/sample-rejection-reasons/get-generic-sample-rejection-reasons-helper.php',
    'app/generic-tests/configuration/sample-types/generic-edit-sample-type.php',
    'app/generic-tests/configuration/sample-types/get-generic-sample-type-helper.php',
    'app/generic-tests/configuration/symptoms/generic-edit-symptoms.php',
    'app/generic-tests/configuration/symptoms/get-symptoms-helper.php',
    'app/generic-tests/configuration/test-categories/generic-edit-test-categories.php',
    'app/generic-tests/configuration/test-categories/get-test-categories-helper.php',
    'app/generic-tests/configuration/test-failure-reasons/generic-edit-test-failure-reason.php',
    'app/generic-tests/configuration/test-failure-reasons/get-test-failure-reason-helper.php',
    'app/generic-tests/configuration/test-methods/generic-edit-test-methods.php',
    'app/generic-tests/configuration/test-methods/get-test-methods-helper.php',
    'app/generic-tests/configuration/test-result-units/generic-edit-test-result-units.php',
    'app/generic-tests/configuration/test-result-units/get-test-result-units-helper.php',
    'app/generic-tests/configuration/testing-reasons/generic-edit-testing-reason.php',
    'app/generic-tests/configuration/testing-reasons/get-testing-reason-helper.php',
    'app/generic-tests/mail/get-samples-for-mail.php',
    'app/generic-tests/requests/checkSampleDuplicate.php',
    'app/generic-tests/requests/get-request-list.php',
    'app/generic-tests/requests/getTestTypeForm.php',
    'app/generic-tests/results/email-results-confirm.php',
    'app/generic-tests/results/generate-result-pdf.php',
    'app/generic-tests/results/get-generic-failed-results-details.php',
    'app/generic-tests/results/get-generic-results-for-approval.php',
    'app/generic-tests/results/get-generic-test-result-details.php',
    'app/generic-tests/results/get-manual-results.php',
    'app/generic-tests/results/getRequestSampleCodeDetails.php',
    'app/generic-tests/results/pdf/generate-generic-manifest.php',
    'app/global-config/getGlobalConfigDetails.php',
    'app/hepatitis/mail/get-samples-for-mail.php',
    'app/hepatitis/reference/get-hepatitis-results-helper.php',
    'app/hepatitis/reference/get-hepatitis-risk-factor-helper.php',
    'app/hepatitis/reference/get-hepatitis-test-reasons-helper.php',
    'app/hepatitis/reference/getHepatitisComorbiditiesDetails.php',
    'app/hepatitis/requests/check-sample-duplicate.php',
    'app/hepatitis/requests/get-request-list.php',
    'app/hepatitis/results/email-results-confirm.php',
    'app/hepatitis/results/generate-result-pdf.php',
    'app/hepatitis/results/get-failed-results.php',
    'app/hepatitis/results/get-hepatitis-result-status.php',
    'app/hepatitis/results/get-results-for-print.php',
    'app/hepatitis/results/getRequestSampleCodeDetails.php',
    'app/hepatitis/results/hepatitis-samples-for-manual-result-entry.php',
    'app/import-result/getImportedResults.php',
    'app/import-result/imported-results.php',
    'app/includes/checkDuplicate.php',
    'app/includes/get-data-list-for-generic.php',
    'app/includes/get-data-list.php',
    'app/includes/get-sample-type.php',
    'app/includes/write-samples-storage-template.php',
    'app/instruments/get-instruments.php',
    'app/mail/getRequestSampleCodeDetails.php',
    'app/move-samples/get-move-samples-codes.php',
    'app/move-samples/get-moved-samples-lists.php',
    'app/patients/get-patients-helper.php',
    'app/roles/editRole.php',
    'app/roles/getRoleDetails.php',
    'app/specimen-referral-manifest/generateCD4Manifest.php',
    'app/specimen-referral-manifest/generateEIDManifest.php',
    'app/specimen-referral-manifest/generateGenericManifest.php',
    'app/specimen-referral-manifest/generateHepatitisManifest.php',
    'app/specimen-referral-manifest/generateTBManifest.php',
    'app/specimen-referral-manifest/generateVLManifest.php',
    'app/system-admin/api-stats/getApiStatsDetails.php',
    'app/system-admin/user-login-history/getUserLoginHistoryDetails.php',
    'app/tb/reference/get-tb-results-helper.php',
    'app/tb/reference/getTbTestReasonsDetails.php',
    'app/tb/requests/get-request-list.php',
    'app/tb/requests/patientModal.php',
    'app/tb/results/email-results-confirm.php',
    'app/tb/results/generate-result-pdf.php',
    'app/tb/results/get-failed-results.php',
    'app/tb/results/get-results-for-print.php',
    'app/tb/results/get-samples.php',
    'app/tb/results/get-tb-result-status.php',
    'app/tb/results/getRequestSampleCodeDetails.php',
    'app/tb/results/getTbReferralDetails.php',
    'app/tb/results/pdf/generate-generic-manifest.php',
    'app/tb/results/tb-samples-for-manual-result-entry.php',
    'app/users/getFacilitiesHelper.php',
    'app/users/getUserDetails.php',
    'app/vl/program-management/addContactNotes.php',
    'app/vl/program-management/generateVlWeeklyReportExcel.php',
    'app/vl/program-management/get-data-export.php',
    'app/vl/program-management/getControlChart.php',
    'app/vl/program-management/getStorageReportDetails.php',
    'app/vl/program-management/getVlMonitoringResultDetails.php',
    'app/vl/program-management/getVlMonthlyThresholdReport.php',
    'app/vl/program-management/getVlSampleTATDetails.php',
    'app/vl/program-management/getVlWeeklyFemaleReport.php',
    'app/vl/program-management/getVlWeeklyReport.php',
    'app/vl/program-management/vlMonitoringExportInExcel.php',
    'app/vl/program-management/vlSampleTatFilters.php',
    'app/vl/reference/edit-vl-results.php',
    'app/vl/reference/get-vl-art-code-details-helper.php',
    'app/vl/reference/get-vl-results-helper.php',
    'app/vl/reference/get-vl-test-failure-reasons-helper.php',
    'app/vl/reference/get-vl-test-reasons-helper.php',
    'app/vl/requests/checkPatientExist.php',
    'app/vl/requests/checkSampleDuplicate.php',
    'app/vl/requests/get-request-list.php',
    'app/vl/requests/getFacilitiesModalDetails.php',
    'app/vl/requests/getVlRequestModalDetails.php',
    'app/vl/requests/sample-storage.php',
    'app/vl/result-mail/getResultEmailConfigDetails.php',
    'app/vl/results/email-results-confirm.php',
    'app/vl/results/generate-result-pdf.php',
    'app/vl/results/get-manual-results.php',
    'app/vl/results/get-results-for-print.php',
    'app/vl/results/getRequestSampleCodeDetails.php',
    'app/vl/results/getVlFailedResultsDetails.php',
    'app/vl/results/getVlResultsForApproval.php',
    // Result mail senders: attachments resolved through MailAttachmentUtility.
    'app/cd4/results/email-results-helper.php',
    'app/covid-19/mail/covid-19-result-mail-helper.php',
    'app/covid-19/results/email-results-helper.php',
    'app/eid/results/email-results-helper.php',
    'app/generic-tests/mail/generic-tests-result-mail-helper.php',
    'app/generic-tests/results/email-results-helper.php',
    'app/hepatitis/mail/hepatitis-result-mail-helper.php',
    'app/hepatitis/results/email-results-helper.php',
    'app/includes/checkFileExists.php',
    'app/mail/vlResultMailHelper.php',
    'app/tb/results/email-results-helper.php',
    'app/vl/results/email-results-helper.php',
];

/**
 * The InteLIS Mobile results endpoints. They read a JSON body into $input rather
 * than $_POST, so the same rule is checked against that name. Swept 2026-09-03:
 * text lists go through $db->escape(), id lists through $db->inIntList().
 *
 * @var list<string>
 */
const API_COVERED_FILES = [
    'app/api/v1.1/vl/fetch-results.php',
    'app/api/v1.1/vl/get-request.php',
    'app/api/v1.1/eid/fetch-results.php',
    'app/api/v1.1/eid/get-request.php',
    'app/api/v1.1/covid-19/fetch-results.php',
    'app/api/v1.1/covid-19/get-request.php',
    'app/api/v1.1/tb/fetch-results.php',
    'app/api/v1.1/tb/get-request.php',
    'app/api/v1.1/generic-tests/fetch-results.php',
    'app/api/v1.1/generic-tests/get-request.php',
    'app/api/v1.1/init.php',
    'app/api/v1.1/sample-status.php',
    'app/api/v1.1/cancel-requests.php',
    'app/api/v1.1/generate-manifest.php',
];

/**
 * The API files copy request values into plain variables before the SQL line,
 * which the $input patterns above cannot see. These pin the encoder at the
 * point the value becomes SQL instead: a text list is only ever imploded
 * through $db->escape(), and the date and id values are never interpolated
 * bare. Keep the variable names in step with the endpoints.
 */
const API_LAUNDERED_PATTERNS = [
    'list imploded into SQL without $db->escape()' => '/implode\\("\x27,\x27",(?!\\s*array_map\\(\\$db->escape\\(\\.\\.\\.\\))/',
    'date bound interpolated without $db->escape()' => '/\x27\\$(from|to)\x27/',
    'id list interpolated without $db->inIntList()' => '/IN \\(\x27\\$(facilityId|sampleStatus)\x27\\)/',
];

/**
 * Positive form of the same rule for the API files: whenever an endpoint reads a
 * given filter, the encoder for that filter has to be present somewhere in the
 * file. Deleting an $db->escape() or $db->inIntList() call turns the file red
 * even though the remaining line is a spelling the negative patterns never saw.
 * marker => required, both regexes.
 */
const API_REQUIRED_ENCODERS = [
    '/\\$input\\[\x27uniqueId\x27\\]/' => '/array_map\\(\\$db->escape\\(\\.\\.\\.\\), (\\(array\\) )?\\$uniqueId\\)/',
    '/\\$input\\[\x27sampleCode\x27\\]/' => '/array_map\\(\\$db->escape\\(\\.\\.\\.\\), (\\(array\\) )?\\$sampleCode\\)/',
    '/\\$input\\[\x27facility\x27\\]/' => '/\\$db->inIntList\\((\\$facilityId|\\$input\\[\x27facility\x27\\])\\)/',
    '/\\$input\\[\x27sampleStatus\x27\\]/' => '/\\$db->inIntList\\(\\$sampleStatus\\)/',
    '/\\$input\\[\x27sampleCollectionDate\x27\\]\\[0\\]/' => '/\\$db->escape\\(\\$from\\)/',
    '/\\$input\\[\x27sampleCollectionDate\x27\\]\\[1\\]/' => '/\\$db->escape\\(\\$to\\)/',
    '/\\$input\\[\x27lastModifiedDateTime\x27\\]/' => '/\\$db->escape\\(DateUtility::isoDateFormat\\(\\$input\\[\x27lastModifiedDateTime\x27\\]\\)\\)/',
    '/\\$input\\[\x27patientName\x27\\]/' => '/\\$db->escape\\(\\$input\\[\x27patientName\x27\\]\\)/',
    '/\\$input\\[\x27childName\x27\\]/' => '/\\$db->escape\\(\\$input\\[\x27childName\x27\\]\\)/',
    '/\\$input\\[\x27patientId\x27\\]/' => '/array_map\\(\\$db->escape\\(\\.\\.\\.\\), /',
];

const RAW_VALUE_PATTERNS = [
    'concatenated directly after a string' => '/\.\s*\$_(POST|GET|REQUEST)\s*\[/',
    'concatenated after only a string cast' => '/\.\s*\(string\)\s*\$_(POST|GET|REQUEST)\s*\[/',
    'concatenated directly before a string' => '/\$_(POST|GET|REQUEST)\s*\[[^\]]+\]\s*\./',
    'interpolated inside a double-quoted string' => '/\{\$_(POST|GET|REQUEST)\s*\[/',
    // $sOffset/$sLimit end up in LIMIT unquoted; DataTableUtility::paging() casts both.
    'paging value kept uncast' => '/=\s*\$_(POST|GET|REQUEST)\s*\[\s*[\x27"]iDisplay(Start|Length)[\x27"]\s*\]\s*;/',
];

$violations = [];
$checked = 0;

/**
 * @return array<string, string> label => pattern, for one request-value source
 */
function rawValuePatternsFor(string $source): array
{
    $patterns = [];
    foreach (RAW_VALUE_PATTERNS as $label => $pattern) {
        $patterns[$label] = str_replace('\\$_(POST|GET|REQUEST)', $source, $pattern);
    }
    return $patterns;
}

$covered = [];
foreach (COVERED_FILES as $name) {
    $covered[$name] = ['source' => '\\$_(POST|GET|REQUEST)', 'patterns' => RAW_VALUE_PATTERNS];
}
foreach (API_COVERED_FILES as $name) {
    $covered[$name] = ['source' => '\\$input', 'patterns' => rawValuePatternsFor('\\$input') + API_LAUNDERED_PATTERNS];
}

foreach ($covered as $name => $rule) {
    $path = REPO_DIR . '/' . $name;
    if (!is_file($path)) {
        $violations[] = [
            'where' => $name,
            'hint' => 'listed in COVERED_FILES but missing -- update the list if it moved',
        ];
        continue;
    }
    $checked++;
    $lines = file($path);
    foreach ($lines as $i => $line) {
        // An (int) or (float) cast is itself an encoder, so a cast access is
        // removed before matching -- the dot then touches the cast, not $_POST.
        $line = preg_replace('/\((?:int|float)\)\s*' . $rule['source'] . '\s*\[[^\]]*\]/', 'CAST_ENCODED', $line);
        foreach ($rule['patterns'] as $label => $pattern) {
            if (preg_match($pattern, $line)) {
                $violations[] = [
                    'where' => $name . ':' . ($i + 1),
                    'hint' => 'request value ' . $label . ': ' . trim($line),
                ];
                break;
            }
        }
    }
}

// Positive check for the API files: every filter read has its encoder present.
foreach (API_COVERED_FILES as $name) {
    $path = REPO_DIR . '/' . $name;
    if (!is_file($path)) {
        continue;
    }
    $source = (string) file_get_contents($path);
    foreach (API_REQUIRED_ENCODERS as $marker => $required) {
        if (preg_match($marker, $source) && !preg_match($required, $source)) {
            $violations[] = [
                'where' => $name,
                'hint' => 'reads ' . trim($marker, '/') . ' but its encoder is missing (expected ' . trim($required, '/') . ')',
            ];
        }
    }
}

echo "check-sql-value-safety: {$checked} endpoints keep request values out of their SQL" . PHP_EOL;

if ($violations === []) {
    echo 'check-sql-value-safety: no raw request value reaches a query string.' . PHP_EOL;
    exit(0);
}

echo PHP_EOL;
foreach ($violations as $violation) {
    echo "  {$violation['where']}" . PHP_EOL;
    echo "      {$violation['hint']}" . PHP_EOL;
}
echo PHP_EOL;
echo 'A request value goes into SQL only through an encoder: (int) for a single id,' . PHP_EOL;
echo '$db->inIntList() for an IN () list, $db->escape() for text, $db->escapeLike()' . PHP_EOL;
echo 'for LIKE patterns, or a whitelist lookup for identifiers. The concatenation' . PHP_EOL;
echo 'dot must touch the encoder, never $_POST itself.' . PHP_EOL;

exit(1);
