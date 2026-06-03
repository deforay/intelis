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

    /**
     * Configured instruments (analysers) per testing lab, keyed by lab facility id.
     *
     * The same analyser can be modelled two ways in the data: as several physical
     * machines under one instrument config (instrument_machines sub-table), or as
     * several separate instrument rows that happen to share a name. We collapse
     * both into one line per analyser name (case-insensitive) with a total machine
     * count, so e.g. three "GeneXpert"/"Genexpert" rows become "GeneXpert × 3".
     * A config with no sub-machine rows still counts as one machine.
     * Active analysers are listed first, then by name.
     *
     * @param int[] $labIds
     * @return array<int, list<array{name:string, status:string, machines:int}>>
     */
    public function getInstrumentsByLab(array $labIds): array
    {
        $labIds = $this->intList($labIds);
        if ($labIds === []) {
            return [];
        }

        // One row per instrument config, with its physical-machine count.
        $sql = "SELECT i.lab_id, i.machine_name, i.status,
                       COUNT(im.config_machine_id) AS machine_rows
                  FROM instruments i
                  LEFT JOIN instrument_machines im ON im.instrument_id = i.instrument_id
                 WHERE i.lab_id IN (" . implode(',', $labIds) . ")
                   AND i.machine_name IS NOT NULL AND i.machine_name != ''
                 GROUP BY i.instrument_id, i.lab_id, i.machine_name, i.status";

        // Collapse same-named analysers per lab, summing machine counts.
        $grouped = [];
        foreach ($this->db->rawQuery($sql) ?: [] as $r) {
            $labId = (int) $r['lab_id'];
            $name = trim((string) $r['machine_name']);
            $key = mb_strtolower($name);
            // A config with sub-machines counts each one; otherwise it is one machine.
            $machines = max(1, (int) $r['machine_rows']);
            $isActive = ((string) ($r['status'] ?? '')) === 'active';

            if (!isset($grouped[$labId][$key])) {
                $grouped[$labId][$key] = ['name' => $name, 'machines' => 0, 'active' => false];
            }
            $grouped[$labId][$key]['machines'] += $machines;
            $grouped[$labId][$key]['active'] = $grouped[$labId][$key]['active'] || $isActive;
        }

        $out = [];
        foreach ($grouped as $labId => $byName) {
            $list = [];
            foreach ($byName as $g) {
                $list[] = [
                    'name' => $g['name'],
                    'status' => $g['active'] ? 'active' : 'inactive',
                    'machines' => $g['machines'],
                ];
            }
            // Active first, then by name.
            usort($list, static function ($a, $b) {
                $byStatus = ($b['status'] === 'active' ? 1 : 0) <=> ($a['status'] === 'active' ? 1 : 0);
                return $byStatus !== 0 ? $byStatus : strcasecmp($a['name'], $b['name']);
            });
            $out[$labId] = $list;
        }
        return $out;
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
