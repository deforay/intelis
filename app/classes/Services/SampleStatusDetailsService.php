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
 * slice drawn for one lab and one batch listed every lab and every batch.
 *
 * What is worth seeing depends on the status: a rejected sample needs its
 * reason, a sample waiting at the lab needs how long it has waited, an
 * accepted one needs who approved it. So columns are chosen per status, on top
 * of a few every listing shares.
 *
 * Only the VL form table is wired so far (vl and recency share it). Table and
 * column names come from this class, never from a request.
 */
final class SampleStatusDetailsService
{
    /** Test types this listing can read, mapped to the module that grants the dashboard. */
    private const TEST_TYPES = [
        'vl' => 'vl',
        'recency' => 'vl',
    ];

    /** The report page each test type's pie lives on; its privilege grants this listing too. */
    private const STATUS_REPORT_PAGES = [
        'vl' => '/vl/program-management/vl-sample-status.php',
        'recency' => '/vl/program-management/vl-sample-status.php',
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
     * who can open the Sample Status Report, or who sees the test type on the
     * dashboard, which draws the same pie.
     */
    public static function canView(string $testType): bool
    {
        if (!isset(self::TEST_TYPES[$testType])) {
            return false;
        }
        if (_isAllowed(self::pageUrl($testType))) {
            return true;
        }
        return isset($_SESSION['modules'][self::TEST_TYPES[$testType]]);
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
        $conditions = [SampleCountUtility::countableWhere('sample')];
        $params = [];

        if (!$this->general->isSTSInstance()) {
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

        $batchCode = trim((string) ($filters['batchCode'] ?? ''));
        if ($batchCode !== '') {
            $conditions[] = 'batch.batch_code = ?';
            $params[] = $batchCode;
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
            $conditions[] = 'sample.specimen_type = ?';
            $params[] = (int) $filters['sampleType'];
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
    public static function columns(int $status): array
    {
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
                'rejectedBy' => _translate('Rejected At'),
            ],
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
    public function getSamples(array $req, int $offset, int $limit, string $search = '', ?string $orderKey = null, string $orderDir = 'asc'): array
    {
        [$from, $params] = $this->fromClause($req);
        $total = (int) ($this->db->rawQueryOne("SELECT COUNT(*) AS total $from", $params)['total'] ?? 0);

        $searchSql = '';
        $search = trim($search);
        if ($search !== '') {
            $like = "'%" . $this->db->escapeLike($search) . "%'";
            $searchSql = " AND (sample.sample_code LIKE $like OR sample.remote_sample_code LIKE $like
                           OR sample.patient_art_no LIKE $like OR f.facility_name LIKE $like)";
        }

        $filtered = $total;
        if ($searchSql !== '') {
            $filtered = (int) ($this->db->rawQueryOne("SELECT COUNT(*) AS total $from $searchSql", $params)['total'] ?? 0);
        }

        $sql = $this->selectClause() . " $from $searchSql ORDER BY " . $this->orderBy($req['status'], $orderKey, $orderDir)
            . " LIMIT " . max(0, $offset) . ", " . max(1, $limit);

        $rows = [];
        foreach ($this->db->rawQuery($sql, $params) ?: [] as $row) {
            $rows[] = $this->present($row, $req['status']);
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
        $sql = $this->selectClause() . " $from ORDER BY " . $this->orderBy($req['status'], null, 'asc');
        foreach ($this->db->rawQueryGenerator($sql, $params) as $row) {
            yield $this->present($row, $req['status']);
        }
    }

    /** @return array{0: string, 1: list<mixed>} */
    private function fromClause(array $req): array
    {
        [$conditions, $params] = $this->conditions($req['testType'], $req['filters']);
        $conditions[] = 'sample.result_status = ' . (int) $req['status'];

        $from = "FROM form_vl AS sample
                 LEFT JOIN batch_details AS batch ON batch.batch_id = sample.sample_batch_id
                 LEFT JOIN facility_details AS f ON f.facility_id = sample.facility_id
                 LEFT JOIN facility_details AS l ON l.facility_id = sample.lab_id
                 LEFT JOIN r_vl_sample_type AS st ON st.sample_id = sample.specimen_type
                 WHERE " . implode(' AND ', $conditions);

        return [$from, $params];
    }

    /**
     * Lookups only a few statuses show are subqueries rather than joins, so a
     * listing that does not show them does not pay for them.
     */
    private function selectClause(): string
    {
        return "SELECT sample.vl_sample_id,
                       sample.sample_code,
                       sample.remote_sample_code,
                       sample.patient_art_no,
                       sample.is_encrypted,
                       sample.sample_collection_date,
                       sample.request_created_datetime,
                       sample.sample_package_code,
                       sample.sample_received_at_lab_datetime,
                       sample.sample_tested_datetime,
                       sample.result,
                       sample.result_approved_datetime,
                       sample.rejection_on,
                       sample.vl_test_platform,
                       sample.samples_referred_datetime,
                       sample.last_modified_datetime,
                       COALESCE(sample.result_dispatched_datetime, sample.result_printed_datetime,
                                sample.result_printed_on_sts_datetime, sample.result_printed_on_lis_datetime,
                                sample.result_mail_datetime, sample.result_sms_sent_datetime,
                                sample.result_sent_to_source_datetime, sample.result_pulled_via_api_datetime) AS released_datetime,
                       batch.batch_code,
                       f.facility_name,
                       f.facility_district,
                       l.facility_name AS lab_name,
                       st.sample_name,
                       (SELECT rr.rejection_reason_name FROM r_vl_sample_rejection_reasons AS rr
                         WHERE rr.rejection_reason_id = sample.reason_for_sample_rejection LIMIT 1) AS rejection_reason_name,
                       (SELECT rf.facility_name FROM facility_details AS rf
                         WHERE rf.facility_id = sample.sample_rejection_facility LIMIT 1) AS rejection_facility_name,
                       (SELECT fr.failure_reason FROM r_vl_test_failure_reasons AS fr
                         WHERE fr.failure_id = sample.reason_for_failure LIMIT 1) AS failure_reason,
                       (SELECT rl.facility_name FROM facility_details AS rl
                         WHERE rl.facility_id = sample.referring_lab_id LIMIT 1) AS referring_lab_name,
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
        'sampleType' => 'st.sample_name',
        'collected' => 'sample.sample_collection_date',
        'requestCreated' => 'sample.request_created_datetime',
        'manifestCode' => 'sample.sample_package_code',
        'receivedAtLab' => 'sample.sample_received_at_lab_datetime',
        'batchCode' => 'batch.batch_code',
        'tested' => 'sample.sample_tested_datetime',
        'result' => 'sample.result',
        'approved' => 'sample.result_approved_datetime',
        'rejectedOn' => 'sample.rejection_on',
        'referredOn' => 'sample.samples_referred_datetime',
        'lastModified' => 'sample.last_modified_datetime',
        'platform' => 'sample.vl_test_platform',
    ];

    /**
     * The requested column when it is sortable, else the one worth chasing
     * first: the longest-waiting sample for a queue, the latest for a finished
     * or exited one.
     */
    private function orderBy(int $status, ?string $orderKey, string $orderDir): string
    {
        $dir = strtolower($orderDir) === 'desc' ? 'DESC' : 'ASC';
        if ($orderKey !== null && isset(self::SORT_EXPRESSIONS[$orderKey])) {
            return self::SORT_EXPRESSIONS[$orderKey] . " $dir, sample.vl_sample_id ASC";
        }
        if ($orderKey === 'daysWaiting') {
            // More days waiting is an earlier milestone.
            $flipped = $dir === 'DESC' ? 'ASC' : 'DESC';
            return $this->waitingFrom($status) . " $flipped, sample.vl_sample_id ASC";
        }

        return match ($status) {
            RECEIVED_AT_CLINIC, RECEIVED_AT_TESTING_LAB, PENDING_APPROVAL =>
                $this->waitingFrom($status) . " ASC, sample.vl_sample_id ASC",
            ACCEPTED, TEST_FAILED => "sample.sample_tested_datetime DESC, sample.vl_sample_id DESC",
            REJECTED => "sample.rejection_on DESC, sample.vl_sample_id DESC",
            REFERRED => "sample.samples_referred_datetime DESC, sample.vl_sample_id DESC",
            default => "sample.sample_collection_date ASC, sample.vl_sample_id ASC",
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

    /** @return array<string, mixed> one row keyed as columns($status) */
    private function present(array $row, int $status): array
    {
        $patientId = (string) ($row['patient_art_no'] ?? '');
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
            'sampleType' => (string) ($row['sample_name'] ?? ''),
            'collected' => self::date($row['sample_collection_date'] ?? null),
            'requestCreated' => self::date($row['request_created_datetime'] ?? null),
            'manifestCode' => (string) ($row['sample_package_code'] ?? ''),
            'receivedAtLab' => self::date($row['sample_received_at_lab_datetime'] ?? null),
            'batchCode' => (string) ($row['batch_code'] ?? ''),
            'tested' => self::date($row['sample_tested_datetime'] ?? null),
            'result' => (string) ($row['result'] ?? ''),
            'testedBy' => (string) ($row['tested_by_name'] ?? ''),
            'approvedBy' => (string) ($row['approved_by_name'] ?? ''),
            'approved' => self::date($row['result_approved_datetime'] ?? null),
            'released' => self::date($row['released_datetime'] ?? null),
            'turnaround' => self::daysBetween($row['sample_collection_date'] ?? null, $row['sample_tested_datetime'] ?? null),
            'rejectionReason' => SampleRejectionUtility::reasonLabel($row['rejection_reason_name'] ?? null),
            'rejectedOn' => self::date($row['rejection_on'] ?? null),
            'rejectedBy' => (string) ($row['rejection_facility_name'] ?? ''),
            'failureReason' => (string) ($row['failure_reason'] ?? ''),
            'platform' => (string) ($row['vl_test_platform'] ?? ''),
            'referringLab' => (string) ($row['referring_lab_name'] ?? ''),
            'referredOn' => self::date($row['samples_referred_datetime'] ?? null),
            'lastModified' => self::date($row['last_modified_datetime'] ?? null),
            'lastModifiedBy' => (string) ($row['last_modified_by_name'] ?? ''),
            'daysWaiting' => self::daysBetween(match ($status) {
                RECEIVED_AT_TESTING_LAB => self::firstDate($row['sample_received_at_lab_datetime'] ?? null, $row['sample_collection_date'] ?? null),
                PENDING_APPROVAL => self::firstDate($row['sample_tested_datetime'] ?? null, $row['sample_received_at_lab_datetime'] ?? null, $row['sample_collection_date'] ?? null),
                default => self::firstDate($row['sample_collection_date'] ?? null, $row['request_created_datetime'] ?? null),
            }, date('Y-m-d H:i:s')),
        ];

        $out = [];
        foreach (array_keys(self::columns($status)) as $key) {
            $out[$key] = $all[$key];
        }
        return $out;
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
