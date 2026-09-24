<?php

namespace App\Services;

use Override;
use const COUNTRY\PNG;
use const SAMPLE_STATUS\RECEIVED_AT_CLINIC;
use const SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
use const SAMPLE_STATUS\PENDING_APPROVAL;
use const SAMPLE_STATUS\ACCEPTED;
use const SAMPLE_STATUS\REJECTED;
use App\Utilities\MiscUtility;
use COUNTRY;
use Throwable;
use SAMPLE_STATUS;
use App\Utilities\DateUtility;
use App\Utilities\LoggerUtility;
use App\Exceptions\SystemException;
use App\Abstracts\AbstractTestService;


final class GenericTestsService extends AbstractTestService
{
    public string $testType = 'generic-tests';



    #[Override]
    public function getSampleCode($params)
    {
        if (empty($params['sampleCollectionDate'])) {
            throw new SystemException("Sample Collection Date is required to generate Sample ID", 400);
        } else {
            $globalConfig = $this->commonService->getGlobalConfig();
            $params['sampleCodeFormat'] = $globalConfig['sample_code'] ?? 'MMYY';
            $params['prefix'] ??= $params['testType'] ?? $this->shortCode;
            // Lab-aware postfix; behaviour is driven by TestsService::isReferrable()
            // (Custom/Generic tests are referrable, so the testing lab is encoded on
            // both LIS and STS). See AbstractTestService::labPostfix().
            $params['postfix'] ??= $this->labPostfix($params);

            try {
                return $this->generateSampleCode($this->table, $params);
            } catch (Throwable $e) {
                LoggerUtility::logError('Unable to generate Sample ID : ' . $e->getMessage(), [
                    'exception' => $e,
                    'file' => $e->getFile(), // File where the error occurred
                    'line' => $e->getLine(), // Line number of the error
                    'stacktrace' => $e->getTraceAsString()
                ]);
                return json_encode([]);
            }
        }
    }

    public function getGenericSampleTypes($updatedDateTime = null): array
    {
        $query = "SELECT * FROM r_generic_sample_types where sample_type_status='active'";
        if ($updatedDateTime) {
            $query .= " AND updated_datetime >= " . $this->db->quote($updatedDateTime) . " ";
        }
        $results = $this->db->rawQuery($query);
        $response = [];
        foreach ($results as $row) {
            $response[$row['sample_type_id']] = $row['sample_type_name'];
        }
        return $response;
    }

    #[Override]
    public function insertSample($params, $returnSampleData = false): int|array
    {
        try {

            // Start a new transaction (this starts a new transaction if not already started)
            // see the beginTransaction() function implementation to understand how this works
            $this->db->beginTransaction();

            $formId = (int) $this->commonService->getGlobalConfig('vl_form');

            $this->testType = $params['testType'] ?? $this->testType ?? 'generic-tests';
            $provinceId = $params['provinceId'] ?? null;
            $sampleCollectionDate = $params['sampleCollectionDate'] ?? null;

            // PNG FORM (formId = 5) CANNOT HAVE PROVINCE EMPTY
            // Sample Collection Date Cannot be Empty
            // Test Type cannot be empty
            if (empty($this->testType) || empty($sampleCollectionDate) || ($formId == PNG && empty($provinceId))) {
                return 0;
            }

            $uniqueId = $params['uniqueId'] ?? MiscUtility::generateULID();
            // Session first: an interactive user's own role is authoritative. The API/sync
            // path (save-request.php) is token-authenticated with no session, so it falls
            // through to the per-sample accessType passed in $params.
            $accessType = $_SESSION['accessType'] ?? $params['accessType'] ?? null;
            $params['prefix'] ??= $params['testType'] ?? $this->shortCode;
            $params['postfix'] ??= '';

            // Insert into the Code Generation Queue
            $this->testRequestsService->addToSampleCodeQueue(
                $uniqueId,
                'generic-tests',
                DateUtility::isoDateFormat($sampleCollectionDate, true),
                $params['provinceCode'] ?? null,
                $params['sampleCodeFormat'] ?? null,
                $params['prefix'] ?? $this->shortCode,
                $accessType,
                (int) ($params['labId'] ?? 0) ?: null
            );

            $id = 0;
            $tesRequestData = [
                'vlsm_country_id' => $formId,
                'sample_reordered' => $params['sampleReordered'] ?? 'no',
                'unique_id' => $uniqueId,
                'facility_id' => $params['facilityId'] ?? null,
                'lab_id' => $params['labId'] ?? null,
                'app_sample_code' => $params['appSampleCode'] ?? null,
                'sample_collection_date' => DateUtility::isoDateFormat($sampleCollectionDate, true),
                'vlsm_instance_id' => $_SESSION['instanceId'] ?? $this->commonService->getInstanceId() ?? null,
                'province_id' => _castVariable($provinceId, 'int'),
                'test_type' => $this->testType,
                'request_created_by' => $_SESSION['userId'] ?? $params['userId'] ?? null,
                'form_attributes' => $params['formAttributes'] ?? "{}",
                'request_created_datetime' => DateUtility::getCurrentDateTime(),
                'last_modified_by' => $_SESSION['userId'] ?? $params['userId'] ?? null,
                'last_modified_datetime' => DateUtility::getCurrentDateTime(),
                'is_result_sms_sent'  => 'no',
                'is_result_mail_sent'  => 'no',
                'locked' => 'no'
            ];

            if ($this->commonService->isSTSInstance()) {
                $tesRequestData['remote_sample'] = 'yes';
                // Only collection-site samples stay at the clinic; every other role
                // (testing-lab, or an unset/legacy access_type) works the lab side.
                $tesRequestData['result_status'] = ($accessType === 'collection-site')
                    ? RECEIVED_AT_CLINIC
                    : RECEIVED_AT_TESTING_LAB;
            } else {
                $tesRequestData['remote_sample'] = 'no';
                $tesRequestData['result_status'] = RECEIVED_AT_TESTING_LAB;
            }

            $formAttributes = [
                'applicationVersion' => $this->commonService->getAppVersion(),
                'ip_address' => $this->commonService->getClientIpAddress()
            ];

            $tesRequestData['form_attributes'] = json_encode($formAttributes);
            $this->db->insert("form_generic", $tesRequestData);
            $id = $this->db->getInsertId();
            if ($this->db->getLastErrno() > 0) {
                throw new SystemException($this->db->getLastErrno() . " | " .  $this->db->getLastError());
            }
            // Commit the transaction after the successful insert
            $this->db->commitTransaction();
        } catch (Throwable $e) {
            // Rollback the current transaction to release locks and undo changes
            $this->db->rollbackTransaction();

            LoggerUtility::logError('Insert Generic Sample : ' . $e->getMessage(), [
                'exception' => $e,
                'file' => $e->getFile(), // File where the error occurred
                'line' => $e->getLine(), // Line number of the error
                'stacktrace' => $e->getTraceAsString()
            ]);
            $id = 0;
        }
        if ($returnSampleData === true) {
            return [
                'id' => max($id, 0),
                'uniqueId' => $uniqueId
            ];
        } else {
            return max($id, 0);
        }
    }

    public function getDynamicFields($genericTestId): array
    {
        $return = [];
        if ($genericTestId > 0) {
            $labelsResponse = $dynamicJson = $testTypes = [];
            $this->db->where("sample_id", $genericTestId);
            $generic = $this->db->getOne('form_generic');
            if ($generic['test_type_form']) {
                $dynamicJson = (array) json_decode((string) $generic['test_type_form']);
                $this->db->where('test_type_id', $generic['test_type']);
                $testTypes = $this->db->getOne('r_test_types');
                $labels = json_decode((string) $testTypes['test_form_config'], true);

                foreach ($labels['field_id'] as $key => $le) {
                    $labelsResponse[$le] = $labels['field_name'][$key];
                }
                unset(
                    $testTypes['test_form_config'],
                    $testTypes['test_results_config']
                );
            }
            $return = ['dynamicValue' => $dynamicJson, 'dynamicLabel' => $labelsResponse, 'testDetails' => $testTypes];
        }
        return $return;
    }

    /**
     * Multi-test (TB-style) config for a test type, shared by every page that
     * renders _test-section.php (result update, edit request, add request via
     * getTestTypeForm.php). Each Result Group in test_results_config declares the
     * TEST METHODS (assays) that produce it, plus its result definition
     * (qualitative answers, or numeric + unit). So a method resolves to its group,
     * and that group decides the Test Result control. Many methods can share a
     * group; a test can have several groups (e.g. Ebola RT-PCR vs Antigen).
     *
     * Returns:
     *   methodOptions => [['id' => .., 'name' => ..], ...]   the test's methods
     *   methodGroups  => [methodName => ['result_type' => .., 'results' => [..]]]
     *   defaultGroup  => ['result_type' => .., 'results' => [..]]
     *   unitOptions   => [['id' => .., 'name' => ..], ...]
     */
    public function getMultiTestConfig(int $testTypeId): array
    {
        $testMethods = $this->getTestMethod($testTypeId);
        $testResultUnits = $this->getTestResultUnit($testTypeId);

        $testTypeRow = $this->db->rawQueryOne("SELECT test_results_config FROM r_test_types WHERE test_type_id = ?", [$testTypeId]);
        $resultConfig = json_decode((string) ($testTypeRow['test_results_config'] ?? ''), true) ?: [];

        // Method id -> name (the test's configured methods).
        $methodNameById = [];
        foreach (($testMethods ?: []) as $m) {
            $mid = (int) ($m['test_method_id'] ?? 0);
            $mname = trim((string) ($m['test_method_name'] ?? ''));
            if ($mid > 0 && $mname !== '') {
                $methodNameById[$mid] = $mname;
            }
        }

        // Per-group result definition: groupKey => [result_type, results[]].
        $groupDefs = [];
        foreach ((array) ($resultConfig['result_type'] ?? []) as $gk => $rt) {
            $type = ($rt === 'quantitative') ? 'quantitative' : 'qualitative';
            $results = [];
            if ($type === 'qualitative' && !empty($resultConfig['qualitative']['expectedResult'][$gk])) {
                foreach ((array) $resultConfig['qualitative']['expectedResult'][$gk] as $rv) {
                    $rv = trim((string) $rv);
                    if ($rv !== '' && !in_array($rv, $results, true)) {
                        $results[] = $rv;
                    }
                }
            }
            $groupDefs[$gk] = ['result_type' => $type, 'results' => $results];
        }
        if (empty($groupDefs)) {
            $groupDefs[1] = ['result_type' => 'qualitative', 'results' => []];
        }
        $firstGroupKey = array_key_first($groupDefs);

        // Invert config['methods'] (groupKey => [methodId]) to methodId => groupKey.
        $methodIdGroup = [];
        foreach ((array) ($resultConfig['methods'] ?? []) as $gk => $mids) {
            foreach ((array) $mids as $mid) {
                $methodIdGroup[(int) $mid] = $gk;
            }
        }

        // Resolve each method (keyed by NAME -- what the card stores and submits) to its group.
        $methodGroups = [];
        $methodOptions = [];
        foreach ($methodNameById as $mid => $mname) {
            $gk = $methodIdGroup[$mid] ?? $firstGroupKey;
            if (!isset($groupDefs[$gk])) {
                $gk = $firstGroupKey;
            }
            $methodGroups[$mname] = $groupDefs[$gk];
            $methodOptions[] = ['id' => $mid, 'name' => $mname];
        }

        $unitOptions = [];
        foreach (($testResultUnits ?: []) as $u) {
            $unitOptions[] = ['id' => $u['unit_id'], 'name' => $u['unit_name']];
        }

        return [
            'methodOptions' => $methodOptions,
            'methodGroups' => $methodGroups,
            'defaultGroup' => $groupDefs[$firstGroupKey],
            'unitOptions' => $unitOptions,
        ];
    }

    /**
     * Persist multi-test (TB-style) results for a sample: one generic_test_results
     * row per test card, then sync the LATEST test + the sample-level Final
     * Interpretation onto form_generic. This is the SINGLE source of truth for
     * multi-test result writes — called from the result-update page and the
     * add/edit request flow alike, so results are never written in two places.
     *
     * Re-asserts facility authorization for $sampleId before the destructive
     * delete, so every caller is guarded independently of any prior check.
     *
     * $post is the submitted payload (the $_POST array); it must carry
     * $post['testResult'] (the per-card arrays) plus the sample-level fields.
     * The caller owns the HTTP response (activity log, alert, redirect).
     *
     * $userId is a string because user_details.user_id is a VARCHAR(50) holding a
     * UUID. It used to be typed int here, which kept only the leading run of digits
     * -- a user id became 19, or 0 for any UUID starting with a hex letter -- and
     * that truncated value was written to revised_by, last_modified_by and the
     * change-history "usr" field, where it matches no user and reads as blank.
     */
    public function saveMultiTestResults(int $sampleId, array $post, string $userId): void
    {
        $tableName = 'form_generic';
        $testTableName = 'generic_test_results';

        // Re-assert authorization for the exact sample this will DELETE/UPDATE:
        // resolve the facility from sample_id and confirm it is allowed. A missing
        // sample (facility 0) is refused so the destructive delete can never run on
        // an unauthorized / non-existent id.
        $this->db->where('sample_id', $sampleId);
        $targetFacilityId = (int) ($this->db->getValue($tableName, 'facility_id') ?? 0);
        if ($sampleId <= 0 || $targetFacilityId <= 0) {
            throw new SystemException(_translate('Invalid or unknown sample.'), 400);
        }
        $this->commonService->assertFacilityAllowed($targetFacilityId);

        // An edit that only removes saved tests posts deletedTestIds[] and no cards.
        $tr = (array) ($post['testResult'] ?? []);
        $cardLabIds = (array) ($tr['labId'] ?? []);

        // The rows, the sample's summary of them and the kept copy of the result they
        // replace are one change: all of it lands or none of it does.
        $this->db->beginTransaction();
        try {
            $this->writeMultiTestResults($sampleId, $post, $userId, $tr, $cardLabIds);
            $this->db->commitTransaction();
        } catch (Throwable $e) {
            $this->db->rollbackTransaction();
            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $tr         The posted testResult[field][] arrays.
     * @param array<int, mixed>    $cardLabIds testResult[labId][]
     */
    private function writeMultiTestResults(int $sampleId, array $post, string $userId, array $tr, array $cardLabIds): void
    {
        $tableName = 'form_generic';
        $testTableName = 'generic_test_results';

        // Sample-level outcome (from the SAMPLE OUTCOME section, NOT inferred from a card):
        // rejecting the whole sample and entering a final interpretation are mutually exclusive --
        // a rejected sample has no final interpretation.
        $sampleRejected = (($post['sampleRejected'] ?? '') === 'yes');
        $finalInterp = (!$sampleRejected && ($post['isResultFinalized'] ?? '') === 'yes')
            ? trim((string) ($post['finalResult'] ?? '')) : null;
        if ($finalInterp === '') {
            $finalInterp = null;
        }

        // Existing rows keyed by test_id -- drives three things: server-side accumulation of
        // the per-test change history (cannot be spoofed by the client), upserting each card
        // in place (stable test_id), and the recoverable snapshot before a confirmed delete.
        $existingById = [];
        foreach ($this->db->rawQuery("SELECT * FROM $testTableName WHERE generic_id = ?", [$sampleId]) as $r) {
            $existingById[(string) $r['test_id']] = $r;
        }

        // A request edit posts its cards on every save. One that changes no result adds
        // no copy of the same result, and leaves an approved sample approved (below).
        // Otherwise the result these cards replace is kept first, as on every other
        // result edit.
        $changesAResult = $this->changesAResult(
            $sampleId,
            $post,
            $tr,
            $cardLabIds,
            $existingById,
            $sampleRejected,
            $finalInterp
        );
        if ($changesAResult) {
            (new TestAttemptService($this->db))->archive('generic-tests', $sampleId, TestAttemptService::BY_RESULT_EDIT);
        }

        $keptIds = [];   // existing test_ids updated in place this save (so we never delete them)
        foreach ($cardLabIds as $k => $labId) {
            // A new card without a lab is a blank card. A saved test posted without
            // one keeps its own lab: the edits are saved rather than dropped.
            $postedTestId = trim((string) ($tr['testId'][$k] ?? ''));
            if (empty($labId)) {
                if ($postedTestId === '' || !isset($existingById[$postedTestId])) {
                    continue;
                }
                $labId = $existingById[$postedTestId]['lab_id'];
            }
            $rejected = $tr['isSampleRejected'][$k] ?? null;
            $isRej = ($rejected === 'yes');

            $submittedTestId = trim((string) ($tr['testId'][$k] ?? ''));
            $isExisting = ($submittedTestId !== '' && isset($existingById[$submittedTestId]));

            $hist = MiscUtility::parseResultChangeHistory($isExisting ? ($existingById[$submittedTestId]['reason_for_result_change'] ?? null) : null);
            $reasonText = trim((string) ($tr['reasonForChange'][$k] ?? ''));
            $revisedBy = null;
            $revisedOn = null;
            if ($reasonText !== '') {
                $hist[] = ['usr' => $userId, 'dtime' => DateUtility::getCurrentDateTime(), 'msg' => $reasonText];
                $revisedBy = $userId;
                $revisedOn = DateUtility::getCurrentDateTime();
            }

            $resultVal = $isRej ? '' : trim((string) ($tr['testResult'][$k] ?? ''));
            $row = [
                'generic_id' => $sampleId,
                'lab_id' => $labId ?: null,
                'facility_id' => $labId ?: null,
                'test_name' => (string) ($tr['testType'][$k] ?? ''),   // the method / assay (Test Type)
                'sub_test_name' => null,
                'result' => $resultVal,
                'final_result' => $isRej ? null : ($resultVal ?: null),
                'result_unit' => (isset($tr['resultUnit'][$k]) && $tr['resultUnit'][$k] !== '') ? $tr['resultUnit'][$k] : null,
                'sample_received_at_lab_datetime' => DateUtility::isoDateFormat($tr['sampleReceivedDate'][$k] ?? '', true),
                'is_sample_rejected' => $rejected ?: null,
                'reason_for_sample_rejection' => $isRej ? ($tr['sampleRejectionReason'][$k] ?? null) : null,
                'rejection_on' => $isRej ? DateUtility::isoDateFormat($tr['rejectionDate'][$k] ?? '') : null,
                'comments' => (isset($tr['comments'][$k]) && trim((string) $tr['comments'][$k]) !== '') ? trim((string) $tr['comments'][$k]) : null,
                'tested_by' => $tr['testedBy'][$k] ?? null,
                'sample_tested_datetime' => DateUtility::isoDateFormat($tr['sampleTestedDateTime'][$k] ?? '', true),
                'result_reviewed_by' => $tr['reviewedBy'][$k] ?? null,
                'result_reviewed_datetime' => DateUtility::isoDateFormat($tr['reviewedOn'][$k] ?? '', true),
                'result_approved_by' => $tr['approvedBy'][$k] ?? null,
                'result_approved_datetime' => DateUtility::isoDateFormat($tr['approvedOn'][$k] ?? '', true),
                'revised_by' => $revisedBy,
                'revised_on' => $revisedOn,
                'reason_for_result_change' => !empty($hist) ? json_encode($hist) : null,
                'updated_datetime' => DateUtility::getCurrentDateTime(),
                'data_sync' => 0,
            ];
            // Upsert: update the existing row in place (test_id preserved) or insert a new one.
            // No blanket delete -- a card the user did not touch is updated, never recreated.
            if ($isExisting) {
                $this->db->where('test_id', (int) $submittedTestId);
                $this->db->where('generic_id', $sampleId);
                $written = $this->db->update($testTableName, $row);
                $keptIds[$submittedTestId] = true;
            } else {
                $written = $this->db->insert($testTableName, $row);
            }
            if (!$written) {
                throw new SystemException("Could not save a test on sample $sampleId: " . $this->db->getLastError(), 500);
            }
        }

        // Targeted delete: ONLY the existing tests the user explicitly confirmed removing
        // (client sends deletedTestIds[]); its audit trigger keeps a copy. An existing test that
        // was neither updated nor confirmed-deleted is PRESERVED -- never silently wiped. Ids that
        // do not belong to THIS sample are ignored.
        foreach (array_unique(array_map('strval', (array) ($post['deletedTestIds'] ?? []))) as $delId) {
            if ($delId === '' || !isset($existingById[$delId]) || isset($keptIds[$delId])) {
                continue;
            }
            $this->db->where('test_id', (int) $delId);
            $this->db->where('generic_id', $sampleId);
            if (!$this->db->delete($testTableName)) {
                throw new SystemException("Could not delete test $delId on sample $sampleId", 500);
            }
        }

        // The sample's latest test is the one tested last, of all its tests -- including
        // any this form did not post -- and until one is tested, the last one added.
        $latestRow = $this->db->rawQueryOne(
            "SELECT * FROM $testTableName WHERE generic_id = ?
                ORDER BY sample_tested_datetime IS NULL, sample_tested_datetime DESC, test_id DESC
                LIMIT 1",
            [$sampleId]
        ) ?: [];


        // Always-written columns: the result + the per-test-derived chain (from the latest test).
        // Sample-level rejection is NOT derived from the latest card -- it is written below from the
        // SAMPLE OUTCOME control (present-only).
        // With no tests left the sample keeps its lab and dates rather than losing them.
        $latestColumns = $latestRow === [] ? [] : [
            'lab_id' => $latestRow['lab_id'] ?? null,
            'sample_received_at_lab_datetime' => $latestRow['sample_received_at_lab_datetime'] ?? null,
            'tested_by' => $latestRow['tested_by'] ?? null,
            'sample_tested_datetime' => $latestRow['sample_tested_datetime'] ?? null,
            'result_reviewed_by' => $latestRow['result_reviewed_by'] ?? null,
            'result_reviewed_datetime' => $latestRow['result_reviewed_datetime'] ?? null,
            'result_approved_by' => $latestRow['result_approved_by'] ?? null,
            'result_approved_datetime' => $latestRow['result_approved_datetime'] ?? null,
        ];
        $formUpdate = $latestColumns + [
            'result' => $finalInterp,
            'final_result_interpretation' => $finalInterp,
            'manual_result_entry' => 'yes',
            // REJECTED when the sample is rejected; Awaiting Approval once there is a final
            // interpretation; otherwise stay at the testing-lab status (remains on the entry list).
            'result_status' => $sampleRejected ? REJECTED : ($finalInterp !== null ? PENDING_APPROVAL : RECEIVED_AT_TESTING_LAB),
            'data_sync' => 0,
            'last_modified_by' => $userId,
            'last_modified_datetime' => DateUtility::getCurrentDateTime(),
        ];
        // An approved sample saved with nothing changed stays approved. Any other
        // status out of step with the sample -- a rejection lifted, a result waiting
        // for approval -- still moves.
        $stored = (int) ($this->db->rawQueryOne(
            'SELECT result_status FROM form_generic WHERE sample_id = ?',
            [$sampleId]
        )['result_status'] ?? 0);
        if (!$changesAResult && $stored === ACCEPTED && $formUpdate['result_status'] === PENDING_APPROVAL) {
            unset($formUpdate['result_status']);
        }

        // Sample-level fields that come straight from a form input are written ONLY when the
        // form actually submitted them. A field absent from POST (e.g. not rendered in this
        // mode -- focal person no longer renders in multi-test) is LEFT UNCHANGED, never
        // nulled. This is what keeps the helper safe across add/edit/result, which each
        // submit a different subset of the form.
        $gtDt = static fn($v): ?string => (trim((string) ($v ?? '')) !== '') ? DateUtility::isoDateFormat((string) $v, true) : null;
        $setIfPresent = static function (string $col, string $key, callable $map) use (&$formUpdate, $post): void {
            if (array_key_exists($key, $post)) {
                $formUpdate[$col] = $map($post[$key]);
            }
        };
        $nz = static fn($v) => (trim((string) ($v ?? '')) !== '') ? $v : null;
        $setIfPresent('testing_lab_focal_person', 'vlFocalPerson', $nz);
        $setIfPresent('testing_lab_focal_person_phone_number', 'vlFocalPersonPhoneNumber', $nz);
        $setIfPresent('sample_received_at_hub_datetime', 'sampleReceivedAtHubOn', $gtDt);
        $setIfPresent('result_dispatched_datetime', 'resultDispatchedOn', $gtDt);
        $setIfPresent('lab_tech_comments', 'labComments', static fn($v) => (trim((string) ($v ?? '')) !== '') ? trim((string) $v) : null);
        $setIfPresent('reason_for_testing', 'reasonForTesting', $nz);

        // Sample-level rejection: the deliberate "the whole sample is rejected" decision from the
        // SAMPLE OUTCOME control -- present-only, and NOT inferred from a card. When rejected, the
        // reason/date come from the sample-level inputs; when not, they are cleared.
        if (array_key_exists('sampleRejected', $post)) {
            $rej = ($post['sampleRejected'] === 'yes');
            $formUpdate['is_sample_rejected'] = $rej ? 'yes' : 'no';
            $formUpdate['reason_for_sample_rejection'] = $rej ? $nz($post['sampleRejectionReason'] ?? null) : null;
            $formUpdate['rejection_on'] = $rej ? $gtDt($post['sampleRejectionDate'] ?? null) : null;
        }

        $this->db->where('sample_id', $sampleId);
        if ($this->db->update($tableName, $formUpdate) !== true) {
            throw new SystemException("Could not save sample $sampleId: " . $this->db->getLastError(), 500);
        }
    }

    /**
     * Whether a multi-test save changes a result: the sample's final interpretation or
     * rejection, a saved test's result, unit, method, tested time or rejection, a new
     * test with a result, or a removed saved test.
     *
     * @param array<string, mixed>                $tr
     * @param array<int, mixed>                   $cardLabIds
     * @param array<string, array<string, mixed>> $existingById
     */
    private function changesAResult(
        int $sampleId,
        array $post,
        array $tr,
        array $cardLabIds,
        array $existingById,
        bool $sampleRejected,
        ?string $finalInterp
    ): bool {
        $sample = $this->db->rawQueryOne(
            'SELECT result, is_sample_rejected, result_status FROM form_generic WHERE sample_id = ?',
            [$sampleId]
        ) ?: [];
        if (trim((string) ($sample['result'] ?? '')) !== (string) $finalInterp) {
            return true;
        }
        // The request edit writes the sample's own fields first and can blank the flag,
        // so a rejected status counts as rejected too.
        $wasRejected = ($sample['is_sample_rejected'] ?? '') === 'yes'
            || (int) ($sample['result_status'] ?? 0) === REJECTED;
        if (array_key_exists('sampleRejected', $post) && $wasRejected !== $sampleRejected) {
            return true;
        }
        foreach ((array) ($post['deletedTestIds'] ?? []) as $deletedId) {
            if (isset($existingById[(string) $deletedId])) {
                return true;
            }
        }
        foreach ($cardLabIds as $k => $labId) {
            $testId = trim((string) ($tr['testId'][$k] ?? ''));
            $cardRejected = ($tr['isSampleRejected'][$k] ?? null) === 'yes';
            $posted = $cardRejected ? '' : trim((string) ($tr['testResult'][$k] ?? ''));
            if (!isset($existingById[$testId])) {
                // A new test changes the sample only when it says something.
                if (!empty($labId) && ($posted !== '' || $cardRejected)) {
                    return true;
                }
                continue;
            }
            $saved = $existingById[$testId];
            $postedUnit = (string) ($tr['resultUnit'][$k] ?? '');
            $postedTestedAt = (string) DateUtility::isoDateFormat($tr['sampleTestedDateTime'][$k] ?? '', true);
            if (
                $posted !== trim((string) ($saved['result'] ?? ''))
                || $postedUnit !== (string) ($saved['result_unit'] ?? '')
                || (string) ($tr['testType'][$k] ?? '') !== (string) ($saved['test_name'] ?? '')
                || substr($postedTestedAt, 0, 16) !== substr((string) ($saved['sample_tested_datetime'] ?? ''), 0, 16)
                || $cardRejected !== (($saved['is_sample_rejected'] ?? '') === 'yes')
            ) {
                return true;
            }
        }
        return false;
    }

    public function getReasonForFailure($option = true, $updatedDateTime = null)
    {
        $result = [];
        $this->db->where('test_failure_reason_status', 'active');
        if ($updatedDateTime) {
            $this->db->where("updated_datetime >= " . $this->db->quote($updatedDateTime));
        }
        $results = $this->db->get('r_generic_test_failure_reasons');
        if ($option) {
            foreach ($results as $row) {
                $result[$row['test_failure_reason_id']] = $row['test_failure_reason'];
            }
            return $result;
        } else {
            return $results;
        }
    }

    public function getInterpretationResults($testType, $result)
    {
        if (empty($result) || empty($testType)) {
            return null;
        }

        $this->db->where('test_type_id', $testType);
        $testTypeResult = $this->db->getOne('r_test_types');

        if (empty($testTypeResult['test_results_config'])) {
            return null;
        }

        $resultConfig = json_decode((string) $testTypeResult['test_results_config'], true);
        $return = null;

        if (isset($resultConfig['result_type'][1]) && $resultConfig['result_type'][1] == 'quantitative') {
            if (is_numeric($result)) {
                if ($result > $resultConfig['high_value']) {
                    $return = $resultConfig['above_threshold'];
                } elseif ($result == $resultConfig['threshold_value']) {
                    $return = $resultConfig['at_threshold'];
                } elseif ($result < $resultConfig['low_value']) {
                    $return = $resultConfig['below_threshold'];
                }
            } else {
                $resultIndex = (isset($result) && isset($resultConfig['quantitative_result']) && in_array($result, $resultConfig['quantitative_result'])) ? array_search(strtolower((string) $result), array_map('strtolower', $resultConfig['quantitative_result'])) : '';
                $return = $resultConfig['quantitative_result_interpretation'][$resultIndex];
            }
        } elseif (isset($resultConfig['result_type'][1]) && $resultConfig['result_type'][1] == 'qualitative') {
            //echo '<pre>'; print_r($resultConfig); die;
            $resultIndex = (isset($result) && isset($resultConfig['result']) && in_array($result, $resultConfig['result'])) ? array_search(strtolower((string) $result), array_map('strtolower', $resultConfig['result'])) : '';
            $return = $resultConfig['result_interpretation'][$resultIndex];
        }

        return $return;
    }

    public function getTestsByGenericSampleIds($genericSampleIds = null): ?array
    {
        $response = [];

        if (!empty($genericSampleIds) && is_array($genericSampleIds)) {
            $placeholders = implode(',', array_fill(0, count($genericSampleIds), '?'));
            // DESC matches the single-id branch below (orderBy() defaults to DESC).
            // The STS re-inserts child rows in the order it receives them.
            $results = $this->db->rawQuery("SELECT * FROM generic_test_results
                                            WHERE `generic_id` IN ($placeholders)
                                            ORDER BY test_id DESC", $genericSampleIds);
            foreach ($results as $row) {
                $response[$row['generic_id']][$row['test_id']] = $row;
            }
        } elseif (!empty($genericSampleIds) && !is_array($genericSampleIds)) {
            $this->db->orderBy('test_id');
            $this->db->where('generic_id', $genericSampleIds);
            $response = $this->db->get('generic_test_results');
        } else {
            $response = $this->db->rawQuery("SELECT * FROM generic_test_results
                                            ORDER BY test_id ASC");
        }

        return $response;
    }

    public function getSampleType($testTypeId)
    {
        $sampleTypeQry = "SELECT *
                            FROM r_generic_sample_types as st
                            INNER JOIN generic_test_sample_type_map as map ON map.sample_type_id=st.sample_type_id
                            WHERE map.test_type_id=$testTypeId
                            AND st.sample_type_status='active'";
        return $this->db->query($sampleTypeQry);
    }

    public function getTestReason($testTypeId)
    {
        $testReasonQry = "SELECT *
                            FROM r_generic_test_reasons as tr
                            INNER JOIN generic_test_reason_map as map ON map.test_reason_id=tr.test_reason_id
                            WHERE map.test_type_id=$testTypeId
                            AND tr.test_reason_status='active'";
        return $this->db->query($testReasonQry);
    }

    public function getTestMethod($testTypeId)
    {
        $testMethodQry = "SELECT *
                            FROM r_generic_test_methods as tm
                            INNER JOIN generic_test_methods_map as map ON map.test_method_id=tm.test_method_id
                            WHERE map.test_type_id=" . (int) $testTypeId . "
                            AND tm.test_method_status='active'";
        return $this->db->query($testMethodQry);
    }

    public function getTestResultUnit($testTypeId)
    {
        $testResultUnitQry = "SELECT *
                                FROM r_generic_test_result_units as tu
                                INNER JOIN generic_test_result_units_map as map ON map.unit_id=tu.unit_id
                                WHERE map.test_type_id=" . (int) $testTypeId . "
                                AND tu.unit_status='active'";
        return $this->db->query($testResultUnitQry);
    }

    public function fetchRelaventDataUsingTestAttributeId($fcode)
    {
        if (!empty($fcode)) {
            // First get the collection of fcode from the following fcode
            $this->db->where("(JSON_SEARCH(test_form_config, 'one', " . $this->db->quote($fcode) . ") IS NOT NULL) OR (test_form_config IS NOT NULL)");

            $this->db->orderBy('updated_datetime');
            $testTypeResult = $this->db->getOne('r_test_types', 'test_form_config');
            $testType = json_decode((string) $testTypeResult['test_form_config'], true);
            $fcodes = [];
            if (isset($testType) && !empty($testType)) {
                foreach ($testType as $sectionArray) {
                    foreach ($sectionArray as $key => $value) {
                        if ($value['field_code'] == $fcode) {
                            $fcodes[] = $key;
                        }
                    }
                }
            }
            // print_r($fcodes);echo "<br>";
            // After that we get the list of available values from following fcodes
            if (isset($fcodes) && $fcodes !== []) {
                foreach ($fcodes as $value) {
                    $this->db->where("(JSON_SEARCH(test_type_form, 'all', " . $this->db->quote($value) . ") IS NOT NULL) OR (test_type_form IS NOT NULL)");
                }
                $this->db->orderBy('last_modified_datetime');
                $result =  $this->db->getOne('form_generic', 'test_type_form');
                if ($result) {
                    $response = [];
                    foreach ((array) json_decode((string) $result['test_type_form']) as $key => $value) {
                        if (in_array($key, $fcodes)) {
                            $response[] = $value;
                        }
                        // print_r($key);echo "<br>";
                    }
                    // print_r($response);
                    return $response[0];
                } else {
                    return null;
                }
            }

            return null;
        } else {
            return null;
        }
    }
}
