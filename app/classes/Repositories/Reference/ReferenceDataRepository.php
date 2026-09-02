<?php

declare(strict_types=1);

namespace App\Repositories\Reference;

use App\Exceptions\SystemException;
use App\Services\DatabaseService;
use App\Utilities\DateUtility;

/**
 * Writes for the per-module reference tables the admin screens manage.
 *
 * Every reference entity exists once per test module, and an entity's six
 * tables share one schema -- the entities differ only in table and column
 * names, so they are rows in a map here rather than a class each. An entity
 * earns a repository of its own only when it grows behavior this shape cannot
 * express.
 *
 * The endpoints whose writes moved here are listed in
 * bin/build/check-repository-boundaries.php, which fails the build if a direct
 * table write creeps back into any of them.
 */
final readonly class ReferenceDataRepository
{
    /**
     * tables: per-module table names, keyed by the TestsService test-type slug.
     * id/name/status: the entity's column names for those three roles.
     * fields: further writable columns, if any.
     * defaults: replacement for a field submitted empty.
     * sync: the modules whose table carries a data_sync column, when it is not
     * all of them -- the test-reason tables for hepatitis, covid-19, and tb
     * were created without one, and writing it there is an SQL error.
     *
     * Custom Tests manage their reference data elsewhere with different
     * schemas, so they are not listed.
     *
     * @var array<string, array{
     *   tables: array<string, string>,
     *   id: string,
     *   name: string,
     *   status: string,
     *   fields?: list<string>,
     *   defaults?: array<string, string>,
     *   sync?: list<string>
     * }>
     */
    private const ENTITIES = [
        'sample-type' => [
            'tables' => [
                'vl' => 'r_vl_sample_type',
                'eid' => 'r_eid_sample_type',
                'cd4' => 'r_cd4_sample_types',
                'tb' => 'r_tb_sample_type',
                'covid19' => 'r_covid19_sample_type',
                'hepatitis' => 'r_hepatitis_sample_type',
            ],
            'id' => 'sample_id',
            'name' => 'sample_name',
            'status' => 'status',
        ],
        'rejection-reason' => [
            'tables' => [
                'vl' => 'r_vl_sample_rejection_reasons',
                'eid' => 'r_eid_sample_rejection_reasons',
                'cd4' => 'r_cd4_sample_rejection_reasons',
                'tb' => 'r_tb_sample_rejection_reasons',
                'covid19' => 'r_covid19_sample_rejection_reasons',
                'hepatitis' => 'r_hepatitis_sample_rejection_reasons',
            ],
            'id' => 'rejection_reason_id',
            'name' => 'rejection_reason_name',
            'status' => 'rejection_reason_status',
            // rejection_type is the free-form grouping the screens offer as an
            // editable select; empty falls back to 'general', the same default
            // the cross-instance reason mapping uses. rejection_reason_code is
            // the portable reason id: the UI requires it, but fleets carry rows
            // without one, so it is stored as given rather than enforced here.
            // Cross-lab contribution (contributed_by_lab_id) stays with
            // RejectionReasonMappingService.
            'fields' => ['rejection_type', 'rejection_reason_code'],
            'defaults' => ['rejection_type' => 'general'],
        ],
        'test-reason' => [
            'tables' => [
                'vl' => 'r_vl_test_reasons',
                'eid' => 'r_eid_test_reasons',
                'cd4' => 'r_cd4_test_reasons',
                'tb' => 'r_tb_test_reasons',
                'covid19' => 'r_covid19_test_reasons',
                'hepatitis' => 'r_hepatitis_test_reasons',
            ],
            'id' => 'test_reason_id',
            'name' => 'test_reason_name',
            'status' => 'test_reason_status',
            // parent_reason holds the id of a parent reason, 0 when top-level.
            'fields' => ['parent_reason'],
            'defaults' => ['parent_reason' => '0'],
            'sync' => ['vl', 'eid', 'cd4'],
        ],
        // The three VL-only vocabularies live in the same map: a single-module
        // entity is just a map with one table.
        'test-failure-reason' => [
            'tables' => ['vl' => 'r_vl_test_failure_reasons'],
            'id' => 'failure_id',
            'name' => 'failure_reason',
            'status' => 'status',
        ],
        'vl-result' => [
            'tables' => ['vl' => 'r_vl_results'],
            'id' => 'result_id',
            'name' => 'result',
            'status' => 'status',
            // available_for_instruments is a JSON list of instrument ids the
            // endpoint assembles, or SQL NULL when the result is unrestricted.
            'fields' => ['interpretation', 'available_for_instruments'],
        ],
        'art-code' => [
            'tables' => ['vl' => 'r_vl_art_regimen'],
            'id' => 'art_id',
            'name' => 'art_code',
            'status' => 'art_status',
            // parent_art holds the id of a parent regimen, 0 when top-level.
            // The reporting alias table is NOT this entity's business; see
            // update-vl-art-code-alias.php for why it is additive-only.
            'fields' => ['parent_art', 'headings'],
            'defaults' => ['parent_art' => '0'],
        ],
    ];

    private const STATUSES = ['active', 'inactive'];

    public function __construct(private DatabaseService $db)
    {
    }

    /**
     * Inserts a reference row, or updates one when $rowId is given. Returns
     * the row id. New rows carry an explicit data_sync = 0 so the
     * reference-data sync picks them up regardless of the column default.
     *
     * @param array<string, string|null> $fields Entity-specific columns beyond
     *        name and status; keys must be declared in the entity's map. A null
     *        value is stored as SQL NULL.
     */
    public function save(
        string $entity,
        string $testType,
        string $name,
        string $status,
        array $fields = [],
        ?int $rowId = null
    ): int {
        $spec = $this->spec($entity);
        $table = $this->table($spec, $entity, $testType);
        $this->assertStatus($status);

        $name = trim($name);
        if ($name === '') {
            throw new SystemException("A $entity needs a name");
        }
        $this->assertPlainText($entity, $name);

        $data = [
            $spec['name'] => $name,
            $spec['status'] => $status,
            'updated_datetime' => DateUtility::getCurrentDateTime(),
        ];
        foreach ($fields as $column => $value) {
            if (!in_array($column, $spec['fields'] ?? [], true)) {
                throw new SystemException("'$column' is not a writable $entity field");
            }
            if ($value !== null) {
                $value = trim($value);
                if ($value === '') {
                    $value = $spec['defaults'][$column] ?? '';
                }
                $this->assertPlainText($entity, $value);
            }
            $data[$column] = $value;
        }

        if ($rowId !== null) {
            // A caller that meant to edit but holds a mangled id must not fall
            // through to an insert -- that manufactures a duplicate row.
            if ($rowId <= 0) {
                throw new SystemException("Invalid $entity id");
            }
            $this->db->where($spec['id'], $rowId);
            $this->db->update($table, $data);
            return $rowId;
        }

        if (in_array($testType, $spec['sync'] ?? array_keys($spec['tables']), true)) {
            $data['data_sync'] = 0;
        }
        $this->db->insert($table, $data);
        return (int) $this->db->getInsertId();
    }

    /**
     * Sets the status on the given rows. Returns the number of rows the
     * update changed. The status endpoints hand over an exploded CSV verbatim,
     * so anything that is not a positive integer id is dropped.
     *
     * @param list<int|string> $rowIds
     */
    public function updateStatus(string $entity, string $testType, array $rowIds, string $status): int
    {
        $spec = $this->spec($entity);
        $table = $this->table($spec, $entity, $testType);
        $this->assertStatus($status);

        // Strictly digits: intval('12abc') would silently address row 12.
        $ids = array_values(array_map(
            intval(...),
            array_filter(
                array_map(static fn ($id): string => trim((string) $id), $rowIds),
                static fn (string $id): bool => ctype_digit($id) && (int) $id > 0
            )
        ));
        if ($ids === []) {
            return 0;
        }

        $this->db->where($spec['id'], $ids, 'IN');
        $this->db->update($table, [
            $spec['status'] => $status,
            'updated_datetime' => DateUtility::getCurrentDateTime(),
        ]);
        return (int) $this->db->count;
    }

    /**
     * @return array{tables: array<string, string>, id: string, name: string,
     *   status: string, fields?: list<string>, defaults?: array<string, string>}
     */
    private function spec(string $entity): array
    {
        return self::ENTITIES[$entity]
            ?? throw new SystemException("Unknown reference entity '$entity'");
    }

    /** @param array{tables: array<string, string>} $spec */
    private function table(array $spec, string $entity, string $testType): string
    {
        return $spec['tables'][$testType]
            ?? throw new SystemException("No $entity table for test type '$testType'");
    }

    private function assertStatus(string $status): void
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new SystemException("Invalid reference status '$status'");
        }
    }

    /**
     * Reference vocabulary is plain text. Values are stored raw so an
     * ampersand survives the round trip, which means nothing between here and
     * the many renderers may be trusted to escape -- so markup is refused at
     * the door instead.
     *
     * Only the sequences that actually open markup are refused: '<' followed
     * by a letter, '/', '!' or '?' is how a tag begins, per the HTML parser's
     * tag-open state. A comparison such as 'Insufficient volume < 1 mL' is
     * legitimate vocabulary and can never become a tag, so it passes.
     */
    private function assertPlainText(string $entity, string $value): void
    {
        if (preg_match('%<[a-zA-Z/!?]%', $value) === 1) {
            throw new SystemException("A $entity value may not contain markup");
        }
    }
}
