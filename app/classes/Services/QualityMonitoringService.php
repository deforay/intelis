<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\SystemException;
use App\Utilities\DateUtility;
use App\Utilities\SampleCountUtility;

use const SAMPLE_STATUS\ACCEPTED;
use const SAMPLE_STATUS\PENDING_APPROVAL;

/**
 * Query layer for the Quality Monitoring module: the samples that are still
 * waiting, split by which side of the workflow is holding them, so the people
 * responsible for each side can say why.
 *
 * Two views, and a sample is in exactly one of them:
 *
 *   clinic - registered at a collection point, no lab has recorded receiving
 *            it. The delay is on the way to the lab, so the clinic side and
 *            its implementing partner are the ones who can explain it.
 *   lab    - a lab has the sample and no usable result has come out of it yet,
 *            whether it is untested or tested and still unapproved. The delay
 *            is inside the lab.
 *
 * Placement and age come from SampleFlowService, which already reads a
 * sample's position off the milestone timestamps rather than off result_status
 * alone. Sharing that means this module and the Sample Ageing report can never
 * disagree about where a sample is or how long it has been there.
 *
 * Samples that have left the pipeline - rejected, expired, lost, cancelled -
 * are not waiting on anybody, so they never appear here. They are excluded by
 * construction: the stage expression names them, and neither view lists them.
 *
 * Lab and facility scoping is applied inside the query because the endpoint is
 * reachable directly and AJAX requests bypass the access control layer.
 *
 * EID only, deliberately: the listing names child and mother columns that only
 * form_eid has. Table and key still come from the registry so they move with
 * it, but pointing this at another module means giving it that module's patient
 * columns first, not just passing a different test key.
 */
final class QualityMonitoringService
{
    private const TEST_KEY = 'eid';

    /** The stages each side is answerable for, in the order they are listed. */
    public const VIEWS = [
        'clinic' => ['atFacility'],
        'lab' => ['atLab', 'awaitingApproval'],
    ];

    /**
     * Days waiting at which a sample is called late, and very late. Both are
     * shown as counts on the page and colour the age cell. EID turnaround
     * targets differ by country; these are the two marks the ageing report
     * already uses, kept the same so the two pages agree.
     */
    public const LATE_DAYS = 14;
    public const VERY_LATE_DAYS = 30;

    /** How many rows one mother-name lookup covers. Bounds the IN () list. */
    private const MOTHER_LOOKUP_BATCH = 200;

    public function __construct(
        private readonly DatabaseService $db,
        private readonly CommonService $general
    ) {
    }

    /**
     * Normalizes raw request input into the filter set every query takes.
     * Nothing here reaches a query as written: dates are rebuilt from a parsed
     * date, ids are cast, and the age bucket must be one of the fixed set.
     *
     * @return array{startDate: string, endDate: string, labIds: string, facilityIds: string, provinceId: int, districtId: int, partnerId: int, bucket: string}
     */
    public function resolveFilters(array $input): array
    {
        [$startDate, $endDate] = DateUtility::convertDateRange((string) ($input['dateRange'] ?? ''));

        $bucket = (string) ($input['bucket'] ?? '');
        if ($bucket !== '' && !isset(SampleFlowService::AGE_BUCKETS[$bucket])) {
            throw new SystemException('Invalid age bucket for quality monitoring');
        }

        return [
            'startDate' => (string) $startDate,
            'endDate' => (string) $endDate,
            // inIntList() drops anything non-numeric and yields "0" when empty,
            // so these are safe to place in an IN () unquoted.
            'labIds' => $this->idList($input['labId'] ?? null),
            'facilityIds' => $this->idList($input['facilityId'] ?? null),
            'provinceId' => (int) ($input['provinceId'] ?? 0),
            'districtId' => (int) ($input['districtId'] ?? 0),
            'partnerId' => (int) ($input['partnerId'] ?? 0),
            'bucket' => $bucket,
        ];
    }

    /** @return string an IN () list of ids, or '' when nothing was selected */
    private function idList(mixed $value): string
    {
        if ($value === null || $value === '' || $value === []) {
            return '';
        }
        $list = $this->db->inIntList($value);
        return $list === '0' ? '' : $list;
    }

    /**
     * The headline counts: how many samples each side is holding, and how many
     * of those have been waiting past the two marks. One pass over the same
     * placed set the grid lists, so the tiles and the grid can never disagree.
     *
     * @return array{clinic: array{total: int, late: int, veryLate: int}, lab: array{total: int, late: int, veryLate: int}, stages: array<string, int>}
     */
    public function getSummary(array $f): array
    {
        $rows = $this->db->rawQuery(
            "SELECT stage,
                    COUNT(*) AS total,
                    SUM(age >= " . self::LATE_DAYS . ") AS late,
                    SUM(age >= " . self::VERY_LATE_DAYS . ") AS very_late
               FROM (" . $this->placedSamples($f) . ") AS placed
              WHERE stage IN (" . $this->stageList(array_merge(...array_values(self::VIEWS))) . ")
              GROUP BY stage"
        ) ?: [];

        $stages = [];
        foreach (array_merge(...array_values(self::VIEWS)) as $stage) {
            $stages[$stage] = 0;
        }
        $summary = [];
        foreach (array_keys(self::VIEWS) as $view) {
            $summary[$view] = ['total' => 0, 'late' => 0, 'veryLate' => 0];
        }

        foreach ($rows as $row) {
            $stage = (string) $row['stage'];
            $stages[$stage] = (int) $row['total'];
            $view = $this->viewOfStage($stage);
            if ($view === null) {
                continue;
            }
            $summary[$view]['total'] += (int) $row['total'];
            $summary[$view]['late'] += (int) $row['late'];
            $summary[$view]['veryLate'] += (int) $row['very_late'];
        }

        $summary['stages'] = $stages;
        return $summary;
    }

    /**
     * One page of the waiting samples for a view, oldest first by default -
     * the oldest is the one somebody has to chase.
     *
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function getSamples(
        array $f,
        string $view,
        int $offset,
        int $limit,
        string $search = '',
        string $sortKey = '',
        string $sortDir = 'desc'
    ): array {
        $sql = $this->samplesQuery($f, $view, $search, $sortKey, $sortDir);
        [$rows, $total] = $this->db->getDataAndCount($sql, null, $limit, $offset, false);

        $out = [];
        foreach ($rows ?: [] as $row) {
            $out[] = $this->presentSample($row);
        }
        return ['rows' => $this->attachMothersFromVl($out), 'total' => $total];
    }

    /**
     * Every waiting sample in a view, one at a time, for an export that must
     * not hold the whole backlog in memory.
     *
     * @return \Generator<int, array<string, mixed>>
     */
    public function streamSamples(array $f, string $view): \Generator
    {
        // Buffered, because the mother lookup is one query for a batch of rows
        // and not one query per row. The buffer is what bounds that query, so
        // it stays small even though the export itself does not.
        $buffer = [];
        foreach ($this->db->rawQueryGenerator($this->samplesQuery($f, $view, '', '', 'desc')) as $row) {
            $buffer[] = $this->presentSample($row);
            if (count($buffer) >= self::MOTHER_LOOKUP_BATCH) {
                yield from $this->attachMothersFromVl($buffer);
                $buffer = [];
            }
        }
        if ($buffer !== []) {
            yield from $this->attachMothersFromVl($buffer);
        }
    }

    /**
     * Fills in a mother's name from her own viral load record when the EID
     * request did not carry one.
     *
     * The link is the one the PMTCT cascade report already uses: the mother id
     * on the EID request against the patient's ART number on the VL request.
     * Nothing else ties a mother to her infant on this system.
     *
     * Two deliberate limits. The lookup runs against the indexed column rather
     * than TRIM() of it, with both the raw and the trimmed id in the list, so
     * whitespace on the EID side is caught without turning a paged grid into a
     * full scan of a seven-figure table. The collation is NO PAD, so a VL row
     * whose own ART number was stored with surrounding whitespace is missed --
     * about one row in six hundred, against a full scan on every page draw and
     * on every batch of an export, which is not a trade worth making. And a
     * request stored with PII encryption on holds its ART number encrypted, so
     * it matches only another encrypted value; the name is then simply left
     * blank, which is what it was before.
     *
     * The borrowed name is kept in its own field, never merged into the one the
     * EID form recorded: on this page the difference between "the clinic wrote
     * this down" and "we found it elsewhere" is the whole point.
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function attachMothersFromVl(array $rows): array
    {
        $wanted = [];
        foreach ($rows as $row) {
            if ($row['motherName'] === '' && $row['motherId'] !== '') {
                $wanted[$row['motherId']] = true;
                $wanted[trim($row['motherId'])] = true;
            }
        }
        if ($wanted === []) {
            return $rows;
        }

        $list = implode(', ', array_map(
            fn(string $id): string => "'" . $this->db->escape($id) . "'",
            array_keys($wanted)
        ));

        // Newest last: the loop below keeps overwriting, so the most recent
        // request a mother made is the name that survives.
        $found = $this->db->rawQuery(
            "SELECT TRIM(v.patient_art_no) AS mother_id,
                    v.patient_first_name,
                    v.patient_middle_name,
                    v.patient_last_name,
                    v.is_encrypted
               FROM form_vl AS v
              WHERE v.patient_art_no IN ($list)
                AND TRIM(COALESCE(v.patient_first_name, '')) <> ''
              ORDER BY v.vl_sample_id ASC"
        ) ?: [];

        $names = [];
        $key = null;
        foreach ($found as $row) {
            if (($row['is_encrypted'] ?? '') === 'yes') {
                $key ??= (string) $this->general->getGlobalConfig('key');
            }
            $part = static function (mixed $value) use ($row, $key): string {
                $value = trim((string) ($value ?? ''));
                if ($value === '' || ($row['is_encrypted'] ?? '') !== 'yes') {
                    return $value;
                }
                return trim((string) CommonService::crypto('decrypt', $value, (string) $key));
            };
            $name = trim(implode(' ', array_filter([
                $part($row['patient_first_name']),
                $part($row['patient_middle_name']),
                $part($row['patient_last_name']),
            ])));
            if ($name !== '') {
                $names[(string) $row['mother_id']] = $name;
            }
        }

        foreach ($rows as $index => $row) {
            if ($row['motherName'] === '' && $row['motherId'] !== '') {
                $rows[$index]['motherNameFromVl'] = $names[trim($row['motherId'])] ?? '';
            }
        }
        return $rows;
    }

    /**
     * The grid, in order. 'sort' is the expression the column orders by, or
     * null for a column that cannot be ordered. The page renders its header
     * and its DataTables column list from this, so the two stay in step.
     *
     * Child and mother are one column each on screen: an id, a name and a date
     * of birth are one person, and thirteen narrow columns is a table nobody
     * reads. The export keeps them apart -- see exportColumns() -- because a
     * spreadsheet gets sorted and filtered on the parts.
     *
     * The two views differ in one column, the date that means anything on that
     * side: a sample no lab has received has no receipt date to show, and its
     * dispatch date is the one that says whether it has even left the facility.
     *
     * @return array<string, array{label: string, sort: ?string, numeric?: bool}>
     */
    public static function sampleColumns(string $view): array
    {
        $milestone = $view === 'clinic'
            ? ['dispatched' => ['label' => _translate('Dispatched'), 'sort' => 'placed.sample_dispatched_datetime']]
            : ['receivedAtLab' => ['label' => _translate('Received at Lab'), 'sort' => 'placed.sample_received_at_lab_datetime']];

        return [
            'select' => ['label' => '', 'sort' => null],
            'sampleCode' => ['label' => _translate('Sample ID'), 'sort' => 'placed.sample_code'],
            'child' => ['label' => _translate('Child'), 'sort' => 'placed.child_id'],
            'mother' => ['label' => _translate('Mother'), 'sort' => 'placed.mother_id'],
            'facility' => ['label' => _translate('Collection Facility'), 'sort' => 'f.facility_name'],
            'province' => ['label' => _translate('Province/State'), 'sort' => 'f.facility_state'],
            'lab' => ['label' => _translate('Testing Lab'), 'sort' => 'l.facility_name'],
            'partner' => ['label' => _translate('Implementing Partner'), 'sort' => 'p.i_partner_name'],
            'collected' => ['label' => _translate('Collected'), 'sort' => 'placed.sample_collection_date'],
        ] + $milestone + [
            'stage' => ['label' => _translate('Stage'), 'sort' => 'placed.stage'],
            'age' => ['label' => _translate('Days Waiting'), 'sort' => 'placed.age', 'numeric' => true],
            'notes' => ['label' => _translate('Notes'), 'sort' => null],
        ];
    }

    /**
     * The workbook, in order: one value per column, so the parts the grid
     * clubs together can be sorted and filtered on their own.
     *
     * @return array<string, string> row key => heading
     */
    public static function exportColumns(): array
    {
        return [
            'sampleCode' => _translate('Sample ID'),
            'remoteSampleCode' => _translate('Remote Sample ID'),
            'childId' => _translate('Child ID'),
            'childName' => _translate('Child Name'),
            'childDob' => _translate('Child Date of Birth'),
            'childAge' => _translate('Child Age'),
            'motherId' => _translate('Mother ID'),
            'motherName' => _translate('Mother Name'),
            'motherNameFromVl' => _translate('Mother Name (from VL record)'),
            'facility' => _translate('Collection Facility'),
            'province' => _translate('Province/State'),
            'district' => _translate('District/County'),
            'lab' => _translate('Testing Lab'),
            'partner' => _translate('Implementing Partner'),
            'collected' => _translate('Collected'),
            'dispatched' => _translate('Dispatched'),
            'receivedAtLab' => _translate('Received at Lab'),
            'stageLabel' => _translate('Stage'),
            'age' => _translate('Days Waiting'),
            'status' => _translate('Recorded Status'),
            'dataIssue' => _translate('Data Issue'),
        ];
    }

    /** Display names for the stages this module lists. */
    public static function stageLabels(): array
    {
        return [
            'atFacility' => _translate('At facility, not yet at a lab'),
            'atLab' => _translate('At lab, awaiting test'),
            'awaitingApproval' => _translate('Tested, awaiting approval'),
        ];
    }

    /** What each side is being asked to explain, as the heading of its tab. */
    public static function viewLabels(): array
    {
        return [
            'clinic' => _translate('Not yet at the lab'),
            'lab' => _translate('At the lab, no result yet'),
        ];
    }

    /**
     * The preset reasons each side picks from, grouped so a long list stays
     * readable. A note always carries one of these plus optional free text:
     * the preset is what can be counted across facilities and months, and the
     * free text is what makes a single note useful to the person reading it.
     *
     * The keys are stable identifiers, not the displayed wording, so the
     * labels can be reworded or translated without stranding stored notes.
     *
     * @return array<string, array<string, string>> group heading => key => label
     */
    public static function noteReasons(string $view): array
    {
        if ($view === 'clinic') {
            return [
                _translate('Transport to the lab') => [
                    'awaiting_transport' => _translate('Waiting for the next scheduled transport run'),
                    'no_transport' => _translate('No transport available (vehicle, fuel or rider)'),
                    'rider_unavailable' => _translate('Rider or driver unavailable'),
                    'batching_for_pickup' => _translate('Held to batch with other samples before pick-up'),
                    'access_blocked' => _translate('Poor road access, weather or insecurity'),
                    'lost_in_transit' => _translate('Sample lost in transit'),
                    'dispatched_not_acknowledged' => _translate('Dispatched, but the lab has not recorded receiving it'),
                ],
                _translate('At the collection point') => [
                    'supplies_stockout' => _translate('Collection supplies stockout (DBS cards, tubes, swabs)'),
                    'no_trained_staff' => _translate('No trained staff available to collect or package the sample'),
                    'dbs_not_dry' => _translate('DBS card not fully dried before packing'),
                    'cold_chain' => _translate('Cold chain not available (no ice packs, refrigerator failure)'),
                    'incomplete_request_form' => _translate('Request form incomplete, sample held back'),
                    'manifest_not_created' => _translate('Consignment or manifest not yet prepared'),
                    'facility_closed' => _translate('Facility closed (holiday, strike, insecurity)'),
                    'system_downtime' => _translate('System or network downtime at the facility'),
                ],
                _translate('Something else') => [
                    'data_entry_error' => _translate('Data entry error, the sample is not actually pending'),
                    'other' => _translate('Other (describe below)'),
                ],
            ];
        }

        return [
            _translate('Reagents and supplies') => [
                'reagent_stockout' => _translate('Reagent stockout'),
                'consumable_stockout' => _translate('Extraction kit or consumables stockout'),
                'controls_unavailable' => _translate('Controls or calibrators unavailable'),
                'reagents_expired' => _translate('Reagents received expired or damaged'),
            ],
            _translate('Equipment and infrastructure') => [
                'instrument_breakdown' => _translate('Instrument breakdown, awaiting service engineer'),
                'instrument_maintenance' => _translate('Instrument under maintenance or calibration'),
                'power_outage' => _translate('Power outage or generator fuel shortage'),
                'lab_infrastructure' => _translate('Water supply or cold chain failure at the lab'),
                'system_downtime' => _translate('System or network downtime at the lab'),
            ],
            _translate('Testing workflow') => [
                'awaiting_full_run' => _translate('Waiting to fill a complete run'),
                'run_failed' => _translate('Run failed, sample queued for repeat testing'),
                'invalid_result' => _translate('Invalid or indeterminate result, repeat testing needed'),
                'qc_failed' => _translate('Quality control failed, run being repeated'),
                'insufficient_volume' => _translate('Insufficient sample volume, recollection requested'),
                'awaiting_recollection' => _translate('Sample unusable, awaiting recollection'),
                'backlog' => _translate('Backlog beyond the daily testing capacity'),
                'referred_to_other_lab' => _translate('Referred to another testing laboratory'),
            ],
            _translate('Staff and data') => [
                'no_technician' => _translate('No trained technician available'),
                'result_not_entered' => _translate('Result is on the instrument but not yet entered'),
                'awaiting_approval' => _translate('Result entered, awaiting review or approval'),
            ],
            _translate('Something else') => [
                'data_entry_error' => _translate('Data entry error, the sample is not actually pending'),
                'other' => _translate('Other (describe below)'),
            ],
        ];
    }

    /** The view a stage belongs to, or null when no side owns it. */
    private function viewOfStage(string $stage): ?string
    {
        foreach (self::VIEWS as $view => $stages) {
            if (in_array($stage, $stages, true)) {
                return $view;
            }
        }
        return null;
    }

    /** A quoted list of stage names, all of them from the fixed VIEWS map. */
    private function stageList(array $stages): string
    {
        return "'" . implode("', '", array_map(fn(string $s): string => $this->db->escape($s), $stages)) . "'";
    }

    private function assertView(string $view): void
    {
        if (!isset(self::VIEWS[$view])) {
            throw new SystemException('Invalid view for quality monitoring');
        }
    }

    /**
     * Every sample in range placed in its stage, with its age and the columns
     * the grid shows. The filters that can use an index sit in here, against
     * the sample table itself; the dimension tables are joined outside.
     */
    private function placedSamples(array $f, bool $withDetail = false): string
    {
        $table = TestsService::getTestTableName(self::TEST_KEY);
        $primaryKey = TestsService::getPrimaryColumn(self::TEST_KEY);

        $detail = '';
        if ($withDetail) {
            $detail = ",
                       t.$primaryKey AS record_id,
                       t.sample_code,
                       t.remote_sample_code,
                       t.is_encrypted,
                       TRIM(COALESCE(t.result, '')) <> '' AS has_result,
                       t.result_approved_datetime,
                       t.child_id,
                       t.child_name,
                       t.child_surname,
                       t.child_dob,
                       t.child_age,
                       t.mother_id,
                       t.mother_name,
                       t.mother_surname,
                       t.sample_collection_date,
                       t.sample_dispatched_datetime,
                       t.sample_received_at_lab_datetime,
                       t.sample_tested_datetime,
                       t.result_status";
        }

        return "SELECT " . SampleFlowService::stageExpression(self::TEST_KEY) . " AS stage,
                       " . SampleFlowService::ageExpression() . " AS age,
                       t.facility_id,
                       t.lab_id,
                       t.implementing_partner$detail
                  FROM $table AS t
                " . $this->buildWhere($f);
    }

    private function samplesQuery(array $f, string $view, string $search, string $sortKey, string $sortDir): string
    {
        $this->assertView($view);

        $where = ["placed.stage IN (" . $this->stageList(self::VIEWS[$view]) . ")"];

        if ($f['bucket'] !== '') {
            [$from, $to] = SampleFlowService::AGE_BUCKETS[$f['bucket']];
            $where[] = $to === null ? "placed.age >= $from" : "placed.age BETWEEN $from AND $to";
        }

        $search = trim($search);
        if ($search !== '') {
            $like = "'%" . $this->db->escapeLike($search) . "%'";
            $where[] = "(placed.sample_code LIKE $like OR placed.remote_sample_code LIKE $like
                         OR placed.child_id LIKE $like OR placed.mother_id LIKE $like
                         OR f.facility_name LIKE $like OR l.facility_name LIKE $like)";
        }

        $columns = self::sampleColumns($view);
        $order = 'placed.age DESC, placed.sample_code ASC';
        if (isset($columns[$sortKey]) && $columns[$sortKey]['sort'] !== null) {
            $order = $columns[$sortKey]['sort'] . ' ' . (strtolower($sortDir) === 'asc' ? 'ASC' : 'DESC');
        }

        return "SELECT placed.*,
                       f.facility_name AS facility_name,
                       f.facility_state,
                       f.facility_district,
                       l.facility_name AS lab_name,
                       p.i_partner_name,
                       ts.status_name
                  FROM (" . $this->placedSamples($f, true) . ") AS placed
                  LEFT JOIN facility_details AS f ON f.facility_id = placed.facility_id
                  LEFT JOIN facility_details AS l ON l.facility_id = placed.lab_id
                  LEFT JOIN r_implementation_partners AS p ON p.i_partner_id = placed.implementing_partner
                  LEFT JOIN r_sample_status AS ts ON ts.status_id = placed.result_status
                 WHERE " . implode(' AND ', $where) . "
                 ORDER BY $order";
    }

    /** @return array<string, mixed> one grid row, keyed as sampleColumns() */
    private function presentSample(array $row): array
    {
        // Only the four identifying fields are stored encrypted; the surnames
        // are not, which is why the name is assembled after the decryption.
        $encrypted = ($row['is_encrypted'] ?? '') === 'yes';
        $key = $encrypted ? (string) $this->general->getGlobalConfig('key') : '';
        $plain = static function (mixed $value) use ($encrypted, $key): string {
            $value = trim((string) ($value ?? ''));
            if ($value === '' || !$encrypted) {
                return $value;
            }
            return (string) CommonService::crypto('decrypt', $value, $key);
        };
        $fullName = static fn(string $first, mixed $last): string => trim($first . ' ' . trim((string) ($last ?? '')));

        // A blank cell for a missing date, and for the zero date legacy rows
        // carry instead of NULL, which the formatter would render as year -1.
        $date = static function (mixed $value): string {
            $value = (string) ($value ?? '');
            if ($value === '' || str_starts_with($value, '0000-00-00')) {
                return '';
            }
            return (string) (DateUtility::humanReadableDateFormat($value) ?? '');
        };

        $stage = (string) $row['stage'];
        $childAge = trim((string) ($row['child_age'] ?? ''));
        $status = (string) ($row['status_name'] ?? '');

        // The stage is read from the milestones and the status is whatever was
        // last written, and on real data the two disagree: rows marked Accepted,
        // even carrying an approval date, that hold no result at all. Reading
        // "At lab, awaiting test" beside "Accepted" looks like a bug in this
        // page; it is a bug in the record, and saying so is this module's job.
        // Left in the listing rather than filtered out, because somebody has to
        // go and fix it -- and the preset reasons both sides pick from carry
        // "the sample is not actually pending" for exactly this.
        //
        // The two cases are told apart because they are different mistakes. A
        // status of Received at Lab with no result is not wrong at all; what is
        // wrong on such a row is the stray approval date.
        $conflict = '';
        if (empty($row['has_result'])) {
            $recordedStatus = (int) ($row['result_status'] ?? 0);
            $approved = !empty($row['result_approved_datetime'])
                && !str_starts_with((string) $row['result_approved_datetime'], '0000-00-00');

            if (in_array($recordedStatus, [ACCEPTED, PENDING_APPROVAL], true)) {
                $conflict = $status !== ''
                    ? sprintf(_translate('%s, but no result is recorded'), $status)
                    : _translate('Marked as complete, but no result is recorded');
            } elseif ($approved) {
                $conflict = _translate('Carries an approval date but no result');
            }
        }

        return [
            'recordId' => (int) $row['record_id'],
            'sampleCode' => (string) ($row['sample_code'] ?? ''),
            'remoteSampleCode' => (string) ($row['remote_sample_code'] ?? ''),
            'childId' => $plain($row['child_id'] ?? null),
            'childName' => $fullName($plain($row['child_name'] ?? null), $row['child_surname'] ?? null),
            'childDob' => $date($row['child_dob'] ?? null),
            'childAge' => $childAge === '0' ? '' : $childAge,
            'motherId' => $plain($row['mother_id'] ?? null),
            'motherName' => $fullName($plain($row['mother_name'] ?? null), $row['mother_surname'] ?? null),
            // Filled in afterwards, in one query for a batch of rows, and only
            // where the EID request recorded no name of its own.
            'motherNameFromVl' => '',
            'facility' => (string) ($row['facility_name'] ?? ''),
            'province' => (string) ($row['facility_state'] ?? ''),
            'district' => (string) ($row['facility_district'] ?? ''),
            'lab' => (string) ($row['lab_name'] ?? ''),
            'partner' => (string) ($row['i_partner_name'] ?? ''),
            'collected' => $date($row['sample_collection_date'] ?? null),
            'dispatched' => $date($row['sample_dispatched_datetime'] ?? null),
            'receivedAtLab' => $date($row['sample_received_at_lab_datetime'] ?? null),
            'stage' => $stage,
            'stageLabel' => self::stageLabels()[$stage] ?? $stage,
            'status' => $status,
            'dataIssue' => $conflict,
            'age' => (int) $row['age'],
        ];
    }

    /**
     * The period, the selected filters, and every restriction on what the
     * reader may see.
     */
    private function buildWhere(array $f): string
    {
        $clauses = [];

        if ($f['startDate'] !== '' && $f['endDate'] !== '') {
            $clauses[] = SampleCountUtility::registeredBetween('t', $f['startDate'], $f['endDate']);
        }
        if ($f['labIds'] !== '') {
            $clauses[] = "t.lab_id IN (" . $f['labIds'] . ")";
        }
        if ($f['facilityIds'] !== '') {
            $clauses[] = "t.facility_id IN (" . $f['facilityIds'] . ")";
        }
        // Province and district live on facility_details. Selecting the
        // facilities first keeps the sample table's own index on facility_id in
        // play; joining the dimension table in and filtering on it would not.
        if ($f['districtId'] > 0) {
            $clauses[] = "t.facility_id IN (SELECT facility_id FROM facility_details
                                             WHERE facility_district_id = " . $f['districtId'] . ")";
        } elseif ($f['provinceId'] > 0) {
            $clauses[] = "t.facility_id IN (SELECT facility_id FROM facility_details
                                             WHERE facility_state_id = " . $f['provinceId'] . ")";
        }
        if ($f['partnerId'] > 0) {
            $clauses[] = "t.implementing_partner = " . $f['partnerId'];
        }
        if ($labScope = $this->general->labScopeWhere('t')) {
            $clauses[] = $labScope;
        }
        if (!empty($_SESSION['facilityMap'])) {
            $clauses[] = "t.facility_id IN (" . $_SESSION['facilityMap'] . ")";
        }

        return $clauses === [] ? '' : ' WHERE ' . implode(' AND ', $clauses);
    }
}
