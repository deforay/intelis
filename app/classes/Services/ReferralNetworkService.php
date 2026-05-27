<?php

namespace App\Services;

use App\Services\TestsService;
use App\Services\DatabaseService;

/**
 * Aggregates the "which facility sends samples to which testing lab" referral
 * network across every active test type, for the Sample Referral Network map
 * and tables (Admin -> Monitoring).
 *
 * A "flow" is a directed edge: referring facility (facility_id) -> testing lab
 * (lab_id), with the sample count and the most recent request date.
 */
final class ReferralNetworkService
{
    public function __construct(private DatabaseService $db)
    {
    }

    /**
     * Per-(facility, lab, test type) referral rows matching the given filters.
     *
     * @param array{
     *   testTypes?: string[], dateRange?: string,
     *   provinceIds?: int[], districtIds?: int[],
     *   labIds?: int[], facilityIds?: int[]
     * } $filters
     *
     * @return list<array{facility_id:int, lab_id:int, test_type:string, test_name:string, samples:int, latest:?string}>
     */
    public function aggregate(array $filters): array
    {
        $testTypes = !empty($filters['testTypes'])
            ? array_values(array_intersect($filters['testTypes'], TestsService::getActiveTests()))
            : TestsService::getActiveTests();

        if ($testTypes === []) {
            return [];
        }

        $provinceIds = $this->intList($filters['provinceIds'] ?? []);
        $districtIds = $this->intList($filters['districtIds'] ?? []);
        $labIds = $this->intList($filters['labIds'] ?? []);
        $facilityIds = $this->intList($filters['facilityIds'] ?? []);
        $needsFacilityJoin = $provinceIds !== [] || $districtIds !== [];

        $dateClause = '';
        if (!empty($filters['dateRange']) && trim((string) $filters['dateRange']) !== '') {
            // convertDateRange returns controlled 'Y-m-d H:i:s' strings, safe to interpolate.
            [$start, $end] = \App\Utilities\DateUtility::convertDateRange($filters['dateRange'], includeTime: true);
            $dateClause = " AND t.request_created_datetime BETWEEN '$start' AND '$end'";
        }

        $where = $this->buildWhere($dateClause, $labIds, $facilityIds, $provinceIds, $districtIds);
        $join = $needsFacilityJoin
            ? 'JOIN facility_details f ON t.facility_id = f.facility_id'
            : '';

        $rows = [];
        foreach ($testTypes as $testType) {
            try {
                $table = TestsService::getTestTableName($testType);
                $testName = TestsService::getTestName($testType);
            } catch (\Throwable) {
                continue; // unknown / non-table test type
            }

            $sql = "SELECT t.facility_id, t.lab_id,
                           COUNT(*) AS samples,
                           MAX(t.request_created_datetime) AS latest
                      FROM $table t
                      $join
                     WHERE $where
                  GROUP BY t.facility_id, t.lab_id";

            $result = $this->db->rawQuery($sql) ?: [];
            foreach ($result as $r) {
                $rows[] = [
                    'facility_id' => (int) $r['facility_id'],
                    'lab_id' => (int) $r['lab_id'],
                    'test_type' => $testType,
                    'test_name' => $testName,
                    'samples' => (int) $r['samples'],
                    'latest' => $r['latest'] ?? null,
                ];
            }
        }

        return $rows;
    }

    /**
     * Build the shared WHERE clause for a per-test aggregation query.
     *
     * @param int[] $labIds
     * @param int[] $facilityIds
     * @param int[] $provinceIds
     * @param int[] $districtIds
     */
    private function buildWhere(string $dateClause, array $labIds, array $facilityIds, array $provinceIds, array $districtIds): string
    {
        $where = ['t.facility_id IS NOT NULL', 't.lab_id IS NOT NULL'];
        if ($dateClause !== '') {
            $where[] = ltrim($dateClause, ' AND');
        }
        if ($labIds !== []) {
            $where[] = 't.lab_id IN (' . implode(',', $labIds) . ')';
        }
        if ($facilityIds !== []) {
            $where[] = 't.facility_id IN (' . implode(',', $facilityIds) . ')';
        }
        if ($provinceIds !== []) {
            $where[] = 'f.facility_state_id IN (' . implode(',', $provinceIds) . ')';
        }
        if ($districtIds !== []) {
            $where[] = 'f.facility_district_id IN (' . implode(',', $districtIds) . ')';
        }
        return implode(' AND ', $where);
    }

    /**
     * Metadata (name, geo, coordinates, type) for the given facility ids.
     *
     * @param int[] $ids
     * @return array<int, array{name:string, district:string, province:string, lat:?float, lng:?float, type:int}>
     */
    public function getFacilitiesMeta(array $ids): array
    {
        $ids = $this->intList($ids);
        if ($ids === []) {
            return [];
        }

        $sql = "SELECT facility_id, facility_name, facility_district, facility_state,
                       latitude, longitude, facility_type
                  FROM facility_details
                 WHERE facility_id IN (" . implode(',', $ids) . ")";

        $meta = [];
        foreach ($this->db->rawQuery($sql) ?: [] as $r) {
            $lat = ($r['latitude'] !== null && $r['latitude'] !== '') ? (float) $r['latitude'] : null;
            $lng = ($r['longitude'] !== null && $r['longitude'] !== '') ? (float) $r['longitude'] : null;
            $meta[(int) $r['facility_id']] = [
                'name' => (string) $r['facility_name'],
                'district' => (string) $r['facility_district'],
                'province' => (string) $r['facility_state'],
                'lat' => $lat,
                'lng' => $lng,
                'type' => (int) $r['facility_type'],
            ];
        }
        return $meta;
    }

    /** @param mixed[] $values @return int[] */
    private function intList(array $values): array
    {
        $out = [];
        foreach ($values as $v) {
            if ($v === '' || $v === null) {
                continue;
            }
            $out[] = (int) $v;
        }
        return array_values(array_unique(array_filter($out, static fn($i) => $i > 0)));
    }
}
