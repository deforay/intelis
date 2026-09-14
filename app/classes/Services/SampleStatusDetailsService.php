<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\SystemException;
use App\Utilities\DateUtility;
use App\Utilities\SampleCountUtility;
use App\Utilities\SampleRejectionUtility;

use const SAMPLE_STATUS\ACCEPTED;
use const SAMPLE_STATUS\PENDING_APPROVAL;
use const SAMPLE_STATUS\RECEIVED_AT_CLINIC;
use const SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
use const SAMPLE_STATUS\REFERRED;
use const SAMPLE_STATUS\REJECTED;
use const SAMPLE_STATUS\TEST_FAILED;

/**
 * The samples behind one slice of a Sample Status Report pie.
 *
 * The pie and this listing read the same filter set through conditions(), so
 * the number of rows here is the number on the slice. They used to build their
 * own filters, and the drilldown only carried the collection date across, so a
 * slice drawn for one lab and one batch listed every lab and every batch. It
 * also only ever read form_vl, whichever module's pie it was opened from.
 *
 * What is worth seeing depends on the status: a rejected sample needs its
 * reason, a sample waiting at the lab needs how long it has waited, an
 * accepted one needs who approved it. So columns are chosen per status, on top
 * of a few every listing shares.
 *
 * Table and column names come from TEST_TYPES, never from a request.
 */
final class SampleStatusDetailsService
{
    /**
     * What differs between the modules. The two condition flags reproduce what
     * each module's pie already did, so moving a pie onto conditions() changes
     * none of its counts:
     *
     *   excludeClinicOnLis  VL and Custom Tests leave samples still registered
     *                       at a health centre out of a lab's pie.
     *   excludeCancelled    every pie but Custom Tests leaves cancelled samples
     *                       out; that one is a status breakdown that has to sum
     *                       to its own total, so it shows them as a slice.
     *   sampleTypeIsText    specimen_type is a text column, so the filter is
     *                       bound as a string.
     */
    private const TEST_TYPES = [
        'vl' => [
            'table' => 'form_vl',
            'primaryKey' => 'vl_sample_id',
            'module' => 'vl',
            'statusReport' => '/vl/program-management/vl-sample-status.php',
            'patientId' => 'patient_art_no',
            'resultColumns' => ['result'],
            'sampleTypes' => ['r_vl_sample_type', 'sample_id', 'sample_name'],
            'rejectionReasons' => 'r_vl_sample_rejection_reasons',
            'failureReasons' => 'shared',
            'platform' => 'vl_test_platform',
            'rejectionFacility' => true,
            'smsChannel' => true,
            'referralColumns' => false,
            'excludeClinicOnLis' => true,
            'excludeCancelled' => true,
            'sampleTypeIsText' => false,
        ],
        'recency' => [
            'table' => 'form_vl',
            'primaryKey' => 'vl_sample_id',
            'module' => 'vl',
            'statusReport' => '/vl/program-management/vl-sample-status.php',
            'patientId' => 'patient_art_no',
            'resultColumns' => ['result'],
            'sampleTypes' => ['r_vl_sample_type', 'sample_id', 'sample_name'],
            'rejectionReasons' => 'r_vl_sample_rejection_reasons',
            'failureReasons' => 'shared',
            'platform' => 'vl_test_platform',
            'rejectionFacility' => true,
            'smsChannel' => true,
            'referralColumns' => false,
            'excludeClinicOnLis' => true,
            'excludeCancelled' => true,
            'sampleTypeIsText' => false,
        ],
        'eid' => [
            'table' => 'form_eid',
            'primaryKey' => 'eid_id',
            'module' => 'eid',
            'statusReport' => '/eid/management/eid-sample-status.php',
            'patientId' => 'child_id',
            'resultColumns' => ['result'],
            'sampleTypes' => ['r_eid_sample_type', 'sample_id', 'sample_name'],
            'rejectionReasons' => 'r_eid_sample_rejection_reasons',
            'failureReasons' => 'shared',
            'platform' => 'eid_test_platform',
            'rejectionFacility' => false,
            'smsChannel' => false,
            'referralColumns' => false,
            'excludeClinicOnLis' => false,
            'excludeCancelled' => true,
            'sampleTypeIsText' => true,
        ],
        'tb' => [
            'table' => 'form_tb',
            'primaryKey' => 'tb_id',
            'module' => 'tb',
            'statusReport' => '/tb/management/tb-sample-status.php',
            'patientId' => 'patient_id',
            'resultColumns' => ['result'],
            'sampleTypes' => ['r_tb_sample_type', 'sample_id', 'sample_name'],
            'rejectionReasons' => 'r_tb_sample_rejection_reasons',
            'failureReasons' => 'shared',
            'platform' => 'tb_test_platform',
            'rejectionFacility' => false,
            'smsChannel' => false,
            'referralColumns' => true,
            'excludeClinicOnLis' => false,
            'excludeCancelled' => true,
            'sampleTypeIsText' => true,
        ],
        'cd4' => [
            'table' => 'form_cd4',
            'primaryKey' => 'cd4_id',
            'module' => 'cd4',
            'statusReport' => '/cd4/management/cd4-sample-status.php',
            'patientId' => 'patient_art_no',
            'resultColumns' => ['cd4_result'],
            'sampleTypes' => ['r_cd4_sample_types', 'sample_id', 'sample_name'],
            'rejectionReasons' => 'r_cd4_sample_rejection_reasons',
            'failureReasons' => 'shared',
            'platform' => 'cd4_test_platform',
            'rejectionFacility' => true,
            'smsChannel' => true,
            'referralColumns' => false,
            'excludeClinicOnLis' => false,
            'excludeCancelled' => true,
            'sampleTypeIsText' => false,
        ],
        'hepatitis' => [
            'table' => 'form_hepatitis',
            'primaryKey' => 'hepatitis_id',
            'module' => 'hepatitis',
            'statusReport' => '/hepatitis/management/hepatitis-sample-status.php',
            'patientId' => 'patient_id',
            // The result page writes `result`; the analyzer import writes only
            // the HBV or HCV count, so a row can carry a count and no result.
            'resultColumns' => ['result', 'hcv_vl_count', 'hbv_vl_count'],
            'sampleTypes' => ['r_hepatitis_sample_type', 'sample_id', 'sample_name'],
            'rejectionReasons' => 'r_hepatitis_sample_rejection_reasons',
            'failureReasons' => 'shared',
            'platform' => 'hepatitis_test_platform',
            'rejectionFacility' => false,
            'smsChannel' => false,
            'referralColumns' => false,
            'excludeClinicOnLis' => false,
            'excludeCancelled' => true,
            'sampleTypeIsText' => true,
        ],
        'generic-tests' => [
            'table' => 'form_generic',
            'primaryKey' => 'sample_id',
            'module' => 'generic-tests',
            'statusReport' => '/generic-tests/program-management/generic-sample-status.php',
            'patientId' => 'patient_id',
            'resultColumns' => ['result'],
            'sampleTypes' => ['r_generic_sample_types', 'sample_type_id', 'sample_type_name'],
            'rejectionReasons' => 'r_generic_sample_rejection_reasons',
            'failureReasons' => 'generic',
            'platform' => 'test_platform',
            'rejectionFacility' => true,
            'smsChannel' => true,
            'referralColumns' => true,
            'excludeClinicOnLis' => true,
            'excludeCancelled' => false,
            'sampleTypeIsText' => false,
        ],
    ];

    public const PAGE = '/reports/sample-status-details.php';

    /** Request fields the pie is filtered by, which the drilldown link carries across. */
    public const FILTER_FIELDS = [
        'sampleCollectionDate',
        'sampleReceivedDateAtLab',
        'sampleTestedDate',
        'batchCode',
        'sampleType',
        'labName',
    ];

    public function __construct(
        private readonly DatabaseService $db,
        private readonly CommonService $general
    ) {
    }

    /** @return list<string> every test type this listing can read */
    public static function testTypes(): array
    {
        return array_keys(self::TEST_TYPES);
    }

    /**
     * Normalizes raw request input. Throws on a test type or status this
     * listing does not know, so neither reaches a query unchecked.
     *
     * @return array{testType: string, status: int, filters: array<string, string>}
     */
    public function resolveRequest(array $input): array
    {
        $testType = strtolower(trim((string) ($input['testType'] ?? '')));
        if (!isset(self::TEST_TYPES[$testType])) {
            throw new SystemException('Invalid test type for the sample status details');
        }

        $status = (int) ($input['status'] ?? 0);
        if ($this->statusName($status) === null) {
            throw new SystemException('Invalid sample status');
        }

        $filters = [];
        foreach (self::FILTER_FIELDS as $field) {
            $filters[$field] = trim((string) ($input[$field] ?? ''));
        }

        return ['testType' => $testType, 'status' => $status, 'filters' => $filters];
    }

    /**
     * Whether the signed-in user may list samples for this test type: anyone
     * who can open that module's Sample Status Report, or who sees the module
     * on the dashboard, which draws the same pie.
     */
    public static function canView(string $testType): bool
    {
        if (!isset(self::TEST_TYPES[$testType])) {
            return false;
        }
        if (_isAllowed(self::pageUrl($testType))) {
            return true;
        }
        return isset($_SESSION['modules'][self::TEST_TYPES[$testType]['module']]);
    }

    /** Display name of a test type, for the page heading. */
    public static function testName(string $testType): string
    {
        return match ($testType) {
            'vl' => _translate('VL'),
            'recency' => _translate('Recency'),
            'eid' => _translate('EID'),
            'tb' => _translate('TB'),
            'cd4' => _translate('CD4'),
            'hepatitis' => _translate('Hepatitis'),
            'generic-tests' => _translate('Custom Tests'),
            default => $testType,
        };
    }

    /** The sample type name for an id, from the test type's own lookup table. */
    public function sampleTypeName(string $testType, int $sampleTypeId): ?string
    {
        [$table, $idColumn, $nameColumn] = self::TEST_TYPES[$testType]['sampleTypes'];
        $row = $this->db->rawQueryOne("SELECT `$nameColumn` AS name FROM `$table` WHERE `$idColumn` = ?", [$sampleTypeId]);
        return $row['name'] ?? null;
    }

    /**
     * The drilldown link for one slice. testType comes first because the
     * access check grants the page by its path and leading query parameter.
     */
    public static function pageUrl(string $testType, ?int $status = null, array $filters = []): string
    {
        $query = ['testType' => $testType];
        if ($status !== null) {
            $query['status'] = $status;
        }
        foreach (self::FILTER_FIELDS as $field) {
            $value = trim((string) ($filters[$field] ?? ''));
            if ($value !== '') {
                $query[$field] = $value;
            }
        }
        return self::PAGE . '?' . http_build_query($query);
    }

    /**
     * The WHERE conditions and bound parameters for the pie's population, over
     * the aliases `sample` (the form table) and `batch` (batch_details).
     *
     * @param array<string, mixed> $filters raw filter values, keyed as FILTER_FIELDS
     * @return array{0: list<string>, 1: list<mixed>}
     */
    public function conditions(string $testType, array $filters): array
    {
        $config = self::TEST_TYPES[$testType] ?? throw new SystemException('Invalid test type for the sample status details');

        $conditions = [];
        $params = [];

        if ($config['excludeCancelled']) {
            $conditions[] = SampleCountUtility::countableWhere('sample');
        }
        if ($config['excludeClinicOnLis'] && !$this->general->isSTSInstance()) {
            $conditions[] = "sample.result_status != " . RECEIVED_AT_CLINIC;
        }
        if (!empty($_SESSION['facilityMap'])) {
            $conditions[] = "sample.facility_id IN (" . $_SESSION['facilityMap'] . ")";
        }
        if ($labScope = $this->general->labScopeWhere('sample')) {
            $conditions[] = $labScope;
        }
        if ($discriminator = SampleFlowService::testTypeDiscriminator($testType, 'sample')) {
            $conditions[] = $discriminator;
        }

        if (!empty($filters['batchCode'])) {
            $conditions[] = 'batch.batch_code = ?';
            $params[] = (string) $filters['batchCode'];
        }

        $dateRanges = [
            'sampleCollectionDate' => 'sample.sample_collection_date',
            'sampleReceivedDateAtLab' => 'sample.sample_received_at_lab_datetime',
            'sampleTestedDate' => 'sample.sample_tested_datetime',
        ];
        foreach ($dateRanges as $field => $column) {
            $range = trim((string) ($filters[$field] ?? ''));
            if ($range === '') {
                continue;
            }
            [$start, $end] = DateUtility::convertDateRange($range);
            if ($start !== '' && $end !== '') {
                $conditions[] = "DATE($column) BETWEEN ? AND ?";
                $params[] = $start;
                $params[] = $end;
            }
        }

        if (!empty($filters['sampleType'])) {
            // Bound the way each module's pie bound it: EID, TB and Hepatitis
            // keep specimen_type as text, where an integer would match
            // differently.
            $conditions[] = 'sample.specimen_type = ?';
            $params[] = $config['sampleTypeIsText'] ? (string) $filters['sampleType'] : (int) $filters['sampleType'];
        }
        if (!empty($filters['labName'])) {
            $conditions[] = 'sample.lab_id = ?';
            $params[] = (int) $filters['labName'];
        }

        return [$conditions, $params];
    }

    public function statusName(int $status): ?string
    {
        $row = $this->db->rawQueryOne("SELECT status_name FROM r_sample_status WHERE status_id = ?", [$status]);
        return $row['status_name'] ?? null;
    }

    /**
     * Column keys and headings for one status, in listing order.
     *
     * @return array<string, string>
     */
    public static function columns(int $status, string $testType = 'vl'): array
    {
        $config = self::TEST_TYPES[$testType] ?? self::TEST_TYPES['vl'];

        $specific = match ($status) {
            RECEIVED_AT_CLINIC => [
                'requestCreated' => _translate('Request Created On'),
                'manifestCode' => _translate('Manifest Code'),
                'daysWaiting' => _translate('Days Since Collection'),
            ],
            RECEIVED_AT_TESTING_LAB => [
                'receivedAtLab' => _translate('Received at Lab'),
                'batchCode' => _translate('Batch Code'),
                'daysWaiting' => _translate('Days Since Received'),
            ],
            PENDING_APPROVAL => [
                'receivedAtLab' => _translate('Received at Lab'),
                'tested' => _translate('Tested On'),
                'result' => _translate('Result'),
                'testedBy' => _translate('Tested By'),
                'daysWaiting' => _translate('Days Since Tested'),
            ],
            ACCEPTED => [
                'receivedAtLab' => _translate('Received at Lab'),
                'tested' => _translate('Tested On'),
                'result' => _translate('Result'),
                'approvedBy' => _translate('Approved By'),
                'approved' => _translate('Approved On'),
                'released' => _translate('Result Released On'),
                'turnaround' => _translate('Collection to Test (Days)'),
            ],
            REJECTED => [
                'receivedAtLab' => _translate('Received at Lab'),
                'rejectionReason' => _translate('Rejection Reason'),
                'rejectedOn' => _translate('Rejected On'),
            ] + ($config['rejectionFacility'] ? ['rejectedBy' => _translate('Rejected At')] : []),
            TEST_FAILED => [
                'receivedAtLab' => _translate('Received at Lab'),
                'tested' => _translate('Tested On'),
                'result' => _translate('Result'),
                'failureReason' => _translate('Reason for Failure'),
                'platform' => _translate('Testing Platform'),
            ],
            REFERRED => [
                'referringLab' => _translate('Referred From'),
                'referredOn' => _translate('Referred On'),
                'manifestCode' => _translate('Manifest Code'),
            ],
            default => [
                'receivedAtLab' => _translate('Received at Lab'),
                'lastModified' => _translate('Last Updated On'),
                'lastModifiedBy' => _translate('Last Updated By'),
                'daysWaiting' => _translate('Days Since Collection'),
            ],
        };

        return [
            'sampleCode' => _translate('Sample ID'),
            'remoteSampleCode' => _translate('Remote Sample ID'),
            'patientId' => _translate('Patient ID'),
            'facility' => _translate('Facility Name'),
            'district' => _translate('District/County'),
            'lab' => _translate('Testing Lab'),
            'sampleType' => _translate('Sample Type'),
            'collected' => _translate('Sample Collection Date'),
        ] + $specific;
    }

    /**
     * One page of the listing, with the total the pie counted.
     *
     * @param array{testType: string, status: int, filters: array<string, string>} $req
     * @return array{rows: list<array<string, mixed>>, total: int, filtered: int}
     */
    public function getSamples(
        array $req,
        int $offset,
        int $limit,
        string $search = '',
        ?string $orderKey = null,
        string $orderDir = 'asc'
    ): array {
        $config = self::TEST_TYPES[$req['testType']];
        [$from, $params] = $this->fromClause($req);
        $total = (int) ($this->db->rawQueryOne("SELECT COUNT(*) AS total $from", $params)['total'] ?? 0);

        $searchSql = '';
        $search = trim($search);
        if ($search !== '') {
            $like = "'%" . $this->db->escapeLike($search) . "%'";
            $searchSql = " AND (sample.sample_code LIKE $like OR sample.remote_sample_code LIKE $like
                           OR sample.{$config['patientId']} LIKE $like OR f.facility_name LIKE $like)";
        }

        $filtered = $total;
        if ($searchSql !== '') {
            $filtered = (int) ($this->db->rawQueryOne("SELECT COUNT(*) AS total $from $searchSql", $params)['total'] ?? 0);
        }

        $sql = $this->selectClause($req['testType']) . " $from $searchSql ORDER BY "
            . $this->orderBy($req['testType'], $req['status'], $orderKey, $orderDir)
            . " LIMIT " . max(0, $offset) . ", " . max(1, $limit);

        $rows = [];
        foreach ($this->db->rawQuery($sql, $params) ?: [] as $row) {
            $rows[] = $this->present($row, $req['testType'], $req['status']);
        }
        return ['rows' => $rows, 'total' => $total, 'filtered' => $filtered];
    }

    /**
     * Every sample in the listing, one at a time, for the export.
     *
     * @return \Generator<int, array<string, mixed>>
     */
    public function streamSamples(array $req): \Generator
    {
        [$from, $params] = $this->fromClause($req);
        $sql = $this->selectClause($req['testType']) . " $from ORDER BY "
            . $this->orderBy($req['testType'], $req['status'], null, 'asc');
        foreach ($this->db->rawQueryGenerator($sql, $params) as $row) {
            yield $this->present($row, $req['testType'], $req['status']);
        }
    }

    /** @return array{0: string, 1: list<mixed>} */
    private function fromClause(array $req): array
    {
        $config = self::TEST_TYPES[$req['testType']];
        [$table, $idColumn] = $config['sampleTypes'];

        [$conditions, $params] = $this->conditions($req['testType'], $req['filters']);
        $conditions[] = 'sample.result_status = ' . (int) $req['status'];

        $from = "FROM {$config['table']} AS sample
                 LEFT JOIN batch_details AS batch ON batch.batch_id = sample.sample_batch_id
                 LEFT JOIN facility_details AS f ON f.facility_id = sample.facility_id
                 LEFT JOIN facility_details AS l ON l.facility_id = sample.lab_id
                 LEFT JOIN $table AS st ON st.$idColumn = sample.specimen_type
                 WHERE " . implode(' AND ', $conditions);

        return [$from, $params];
    }

    /**
     * Lookups only a few statuses show are subqueries rather than joins, so a
     * listing that does not show them does not pay for them.
     */
    private function selectClause(string $testType): string
    {
        $config = self::TEST_TYPES[$testType];
        [, , $sampleTypeName] = $config['sampleTypes'];

        $releaseChannels = [
            'sample.result_dispatched_datetime', 'sample.result_printed_datetime',
            'sample.result_printed_on_sts_datetime', 'sample.result_printed_on_lis_datetime',
            'sample.result_mail_datetime', 'sample.result_sent_to_source_datetime',
            'sample.result_pulled_via_api_datetime',
        ];
        if ($config['smsChannel']) {
            $releaseChannels[] = 'sample.result_sms_sent_datetime';
        }

        $results = [];
        foreach ($config['resultColumns'] as $i => $column) {
            $results[] = "sample.$column AS result_$i";
        }

        $failureReason = $config['failureReasons'] === 'generic'
            ? "(SELECT gfr.test_failure_reason FROM r_generic_test_failure_reasons AS gfr
                 WHERE gfr.test_failure_reason_id = sample.reason_for_failure LIMIT 1)"
            // The shared vocabulary, falling back to the legacy VL table for
            // ids recorded before the two were merged.
            : "COALESCE(
                 (SELECT fr.failure_reason FROM r_test_failure_reasons AS fr
                   WHERE fr.failure_id = sample.reason_for_failure LIMIT 1),
                 (SELECT lfr.failure_reason FROM r_vl_test_failure_reasons AS lfr
                   WHERE lfr.failure_id = sample.reason_for_failure LIMIT 1))";

        $rejectionFacility = $config['rejectionFacility']
            ? "(SELECT rf.facility_name FROM facility_details AS rf
                 WHERE rf.facility_id = sample.sample_rejection_facility LIMIT 1)"
            : "NULL";

        // TB and Custom Tests record a lab-to-lab referral in their own columns;
        // the other modules only have the referring lab.
        $referringLab = $config['referralColumns']
            ? "COALESCE(sample.referred_by_lab_id, sample.referring_lab_id)"
            : "sample.referring_lab_id";
        $manifestCode = $config['referralColumns']
            ? "COALESCE(NULLIF(sample.referral_manifest_code, ''), sample.sample_package_code)"
            : "sample.sample_package_code";

        return "SELECT sample.{$config['primaryKey']} AS record_id,
                       sample.sample_code,
                       sample.remote_sample_code,
                       sample.{$config['patientId']} AS patient_id,
                       sample.is_encrypted,
                       sample.sample_collection_date,
                       sample.request_created_datetime,
                       $manifestCode AS manifest_code,
                       sample.sample_received_at_lab_datetime,
                       sample.sample_tested_datetime,
                       " . implode(",\n                       ", $results) . ",
                       sample.result_approved_datetime,
                       sample.rejection_on,
                       sample.{$config['platform']} AS test_platform,
                       sample.samples_referred_datetime,
                       sample.last_modified_datetime,
                       COALESCE(" . implode(', ', $releaseChannels) . ") AS released_datetime,
                       batch.batch_code,
                       f.facility_name,
                       f.facility_district,
                       l.facility_name AS lab_name,
                       st.$sampleTypeName AS sample_type_name,
                       (SELECT rr.rejection_reason_name FROM {$config['rejectionReasons']} AS rr
                         WHERE rr.rejection_reason_id = sample.reason_for_sample_rejection LIMIT 1) AS rejection_reason_name,
                       $rejectionFacility AS rejection_facility_name,
                       $failureReason AS failure_reason,
                       (SELECT rl.facility_name FROM facility_details AS rl
                         WHERE rl.facility_id = $referringLab LIMIT 1) AS referring_lab_name,
                       (SELECT ut.user_name FROM user_details AS ut
                         WHERE ut.user_id = sample.tested_by LIMIT 1) AS tested_by_name,
                       (SELECT ua.user_name FROM user_details AS ua
                         WHERE ua.user_id = sample.result_approved_by LIMIT 1) AS approved_by_name,
                       (SELECT um.user_name FROM user_details AS um
                         WHERE um.user_id = sample.last_modified_by LIMIT 1) AS last_modified_by_name";
    }

    /** SQL each sortable column orders by. Keys not listed here sort by the default. */
    private const SORT_EXPRESSIONS = [
        'sampleCode' => 'sample.sample_code',
        'remoteSampleCode' => 'sample.remote_sample_code',
        'facility' => 'f.facility_name',
        'district' => 'f.facility_district',
        'lab' => 'l.facility_name',
        'sampleType' => 'sample_type_name',
        'collected' => 'sample.sample_collection_date',
        'requestCreated' => 'sample.request_created_datetime',
        'manifestCode' => 'manifest_code',
        'receivedAtLab' => 'sample.sample_received_at_lab_datetime',
        'batchCode' => 'batch.batch_code',
        'tested' => 'sample.sample_tested_datetime',
        'result' => 'result_0',
        'approved' => 'sample.result_approved_datetime',
        'rejectedOn' => 'sample.rejection_on',
        'referredOn' => 'sample.samples_referred_datetime',
        'lastModified' => 'sample.last_modified_datetime',
        'platform' => 'test_platform',
    ];

    /**
     * The requested column when it is sortable, else the one worth chasing
     * first: the longest-waiting sample for a queue, the latest for a finished
     * or exited one.
     */
    private function orderBy(string $testType, int $status, ?string $orderKey, string $orderDir): string
    {
        $id = 'sample.' . self::TEST_TYPES[$testType]['primaryKey'];
        $dir = strtolower($orderDir) === 'desc' ? 'DESC' : 'ASC';
        if ($orderKey !== null && isset(self::SORT_EXPRESSIONS[$orderKey])) {
            return self::SORT_EXPRESSIONS[$orderKey] . " $dir, $id ASC";
        }
        if ($orderKey === 'daysWaiting') {
            // More days waiting is an earlier milestone.
            $flipped = $dir === 'DESC' ? 'ASC' : 'DESC';
            return $this->waitingFrom($status) . " $flipped, $id ASC";
        }

        return match ($status) {
            RECEIVED_AT_CLINIC, RECEIVED_AT_TESTING_LAB, PENDING_APPROVAL =>
                $this->waitingFrom($status) . " ASC, $id ASC",
            ACCEPTED, TEST_FAILED => "sample.sample_tested_datetime DESC, $id DESC",
            REJECTED => "sample.rejection_on DESC, $id DESC",
            REFERRED => "sample.samples_referred_datetime DESC, $id DESC",
            default => "sample.sample_collection_date ASC, $id ASC",
        };
    }

    /** The milestone a status's "days waiting" counts from. */
    private function waitingFrom(int $status): string
    {
        return match ($status) {
            RECEIVED_AT_TESTING_LAB => "COALESCE(sample.sample_received_at_lab_datetime, sample.sample_collection_date)",
            PENDING_APPROVAL => "COALESCE(sample.sample_tested_datetime, sample.sample_received_at_lab_datetime, sample.sample_collection_date)",
            default => SampleCountUtility::registeredOn('sample'),
        };
    }

    /** @return array<string, mixed> one row keyed as columns($status, $testType) */
    private function present(array $row, string $testType, int $status): array
    {
        $config = self::TEST_TYPES[$testType];

        $patientId = (string) ($row['patient_id'] ?? '');
        if ($patientId !== '' && ($row['is_encrypted'] ?? '') === 'yes') {
            $key = (string) $this->general->getGlobalConfig('key');
            $patientId = (string) CommonService::crypto('decrypt', $patientId, $key);
        }

        $all = [
            'sampleCode' => (string) ($row['sample_code'] ?? ''),
            'remoteSampleCode' => (string) ($row['remote_sample_code'] ?? ''),
            'patientId' => $patientId,
            'facility' => (string) ($row['facility_name'] ?? ''),
            'district' => (string) ($row['facility_district'] ?? ''),
            'lab' => (string) ($row['lab_name'] ?? ''),
            'sampleType' => (string) ($row['sample_type_name'] ?? ''),
            'collected' => self::date($row['sample_collection_date'] ?? null),
            'requestCreated' => self::date($row['request_created_datetime'] ?? null),
            'manifestCode' => (string) ($row['manifest_code'] ?? ''),
            'receivedAtLab' => self::date($row['sample_received_at_lab_datetime'] ?? null),
            'batchCode' => (string) ($row['batch_code'] ?? ''),
            'tested' => self::date($row['sample_tested_datetime'] ?? null),
            'result' => self::result($row, $config['resultColumns']),
            'testedBy' => (string) ($row['tested_by_name'] ?? ''),
            'approvedBy' => (string) ($row['approved_by_name'] ?? ''),
            'approved' => self::date($row['result_approved_datetime'] ?? null),
            'released' => self::date($row['released_datetime'] ?? null),
            'turnaround' => self::daysBetween($row['sample_collection_date'] ?? null, $row['sample_tested_datetime'] ?? null),
            'rejectionReason' => SampleRejectionUtility::reasonLabel($row['rejection_reason_name'] ?? null),
            'rejectedOn' => self::date($row['rejection_on'] ?? null),
            'rejectedBy' => (string) ($row['rejection_facility_name'] ?? ''),
            'failureReason' => (string) ($row['failure_reason'] ?? ''),
            'platform' => (string) ($row['test_platform'] ?? ''),
            'referringLab' => (string) ($row['referring_lab_name'] ?? ''),
            'referredOn' => self::date($row['samples_referred_datetime'] ?? null),
            'lastModified' => self::date($row['last_modified_datetime'] ?? null),
            'lastModifiedBy' => (string) ($row['last_modified_by_name'] ?? ''),
            'daysWaiting' => self::daysBetween(match ($status) {
                RECEIVED_AT_TESTING_LAB => self::firstDate(
                    $row['sample_received_at_lab_datetime'] ?? null,
                    $row['sample_collection_date'] ?? null
                ),
                PENDING_APPROVAL => self::firstDate(
                    $row['sample_tested_datetime'] ?? null,
                    $row['sample_received_at_lab_datetime'] ?? null,
                    $row['sample_collection_date'] ?? null
                ),
                default => self::firstDate($row['sample_collection_date'] ?? null, $row['request_created_datetime'] ?? null),
            }, date('Y-m-d H:i:s')),
        ];

        $out = [];
        foreach (array_keys(self::columns($status, $testType)) as $key) {
            $out[$key] = $all[$key];
        }
        return $out;
    }

    /**
     * The result as one cell. Where a module has more than one result column
     * (Hepatitis), the interpretation wins, else the counts are named.
     *
     * @param list<string> $columns
     */
    private static function result(array $row, array $columns): string
    {
        $main = trim((string) ($row['result_0'] ?? ''));
        if ($main !== '' || count($columns) === 1) {
            return $main;
        }
        $labels = ['hcv_vl_count' => 'HCV', 'hbv_vl_count' => 'HBV'];
        $parts = [];
        foreach ($columns as $i => $column) {
            $value = trim((string) ($row["result_$i"] ?? ''));
            if ($i > 0 && $value !== '') {
                $parts[] = ($labels[$column] ?? $column) . ': ' . $value;
            }
        }
        return implode(', ', $parts);
    }

    /** A blank for a missing date and for the zero date legacy rows carry instead of NULL. */
    private static function date(mixed $value): string
    {
        $value = (string) ($value ?? '');
        if ($value === '' || str_starts_with($value, '0000-00-00')) {
            return '';
        }
        return (string) (DateUtility::humanReadableDateFormat($value) ?? '');
    }

    private static function firstDate(mixed ...$values): ?string
    {
        foreach ($values as $value) {
            $value = (string) ($value ?? '');
            if ($value !== '' && !str_starts_with($value, '0000-00-00')) {
                return $value;
            }
        }
        return null;
    }

    /** Whole days from one date to another, or a blank when either is missing. */
    private static function daysBetween(mixed $from, mixed $to): string
    {
        $from = self::firstDate($from);
        $to = self::firstDate($to);
        if ($from === null || $to === null) {
            return '';
        }
        $days = (int) floor((strtotime(substr($to, 0, 10)) - strtotime(substr($from, 0, 10))) / 86400);
        return (string) max(0, $days);
    }
}
