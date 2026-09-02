<?php

declare(strict_types=1);

namespace App\Repositories\Reference;

use App\Exceptions\SystemException;
use App\Services\DatabaseService;
use App\Utilities\DateUtility;

/**
 * Writes for the per-module sample-type reference tables.
 *
 * One class instead of six cloned helper scripts. The endpoints it replaced are
 * listed in bin/build/check-repository-boundaries.php, which fails the build if
 * a direct table write creeps back into any of them.
 */
final readonly class SampleTypeRepository
{
    /**
     * Keyed by the TestsService test-type slug. All six tables share the same
     * shape (sample_id, sample_name, status, updated_datetime, data_sync);
     * only the CD4 table name is plural. Custom Tests manage their sample
     * types elsewhere with a different schema, so they are not listed here.
     *
     * @var array<string, string>
     */
    private const TABLES = [
        'vl' => 'r_vl_sample_type',
        'eid' => 'r_eid_sample_type',
        'cd4' => 'r_cd4_sample_types',
        'tb' => 'r_tb_sample_type',
        'covid19' => 'r_covid19_sample_type',
        'hepatitis' => 'r_hepatitis_sample_type',
    ];

    private const STATUSES = ['active', 'inactive'];

    public function __construct(private DatabaseService $db)
    {
    }

    /**
     * Inserts a sample type, or updates one when $sampleTypeId is given.
     * Returns the row id. New rows carry an explicit data_sync = 0 so the
     * reference-data sync picks them up regardless of the column default.
     */
    public function save(string $testType, string $name, string $status, ?int $sampleTypeId = null): int
    {
        $table = $this->table($testType);
        $this->assertStatus($status);

        $name = trim($name);
        if ($name === '') {
            throw new SystemException('A sample type needs a name');
        }

        $data = [
            'sample_name' => $name,
            'status' => $status,
            'updated_datetime' => DateUtility::getCurrentDateTime(),
        ];

        if ($sampleTypeId !== null) {
            // A caller that meant to edit but holds a mangled id must not fall
            // through to an insert -- that manufactures a duplicate sample type.
            if ($sampleTypeId <= 0) {
                throw new SystemException('Invalid sample-type id');
            }
            $this->db->where('sample_id', $sampleTypeId);
            $this->db->update($table, $data);
            return $sampleTypeId;
        }

        $data['data_sync'] = 0;
        $this->db->insert($table, $data);
        return (int) $this->db->getInsertId();
    }

    /**
     * Sets the status on the given sample types. Returns the number of rows
     * the update changed.
     *
     * @param list<int|string> $sampleTypeIds
     */
    public function updateStatus(string $testType, array $sampleTypeIds, string $status): int
    {
        $table = $this->table($testType);
        $this->assertStatus($status);

        $ids = array_values(array_filter(
            array_map(intval(...), $sampleTypeIds),
            static fn (int $id): bool => $id > 0
        ));
        if ($ids === []) {
            return 0;
        }

        $this->db->where('sample_id', $ids, 'IN');
        $this->db->update($table, [
            'status' => $status,
            'updated_datetime' => DateUtility::getCurrentDateTime(),
        ]);
        return (int) $this->db->count;
    }

    private function table(string $testType): string
    {
        return self::TABLES[$testType]
            ?? throw new SystemException("No sample-type reference table for test type '$testType'");
    }

    private function assertStatus(string $status): void
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new SystemException("Invalid sample-type status '$status'");
        }
    }
}
