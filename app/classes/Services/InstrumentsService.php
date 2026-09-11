<?php

namespace App\Services;

use App\Services\DatabaseService;
use App\Utilities\FileCacheUtility;
use App\Utilities\SampleCountUtility;

final class InstrumentsService
{
    protected string $table = 'instruments';

    public function __construct(protected DatabaseService $db)
    {
    }

    public function getInstruments($testType = null, $dropDown = false, $withFacility = false)
    {
        $this->db->where('ins.status', 'active');
        if (!empty($testType)) {
            $this->db->where("(JSON_SEARCH(ins.supported_tests, 'all', '$testType') IS NOT NULL) AND (ins.supported_tests IS NOT NULL)");
        }

        if ($withFacility) {
            $this->db->join("facility_details l", "l.facility_id = ins.lab_id", "LEFT");
        }

        $this->db->orderBy('ins.machine_name', 'ASC');
        $result = $this->db->get($this->table . ' ins');
        if ($dropDown) {
            foreach ($result as $row) {
                $response[$row['instrument_id']] = $withFacility ? $row['machine_name'] . ' - ' . $row['facility_name'] : $row['machine_name'];
            }
            return $response;
        } else {
            return $result;
        }
    }
    public function getInstrumentByName($instrumentName)
    {
        $this->db->where('machine_name', $instrumentName);
        return $this->db->getOne($this->table);
    }

    public function getSingleInstrument(string $instrumentId, string|array $columns = '*')
    {
        if ($instrumentId === '' || $instrumentId === '0' || $instrumentId === '') {
            return null;
        }

        $this->db->where('instrument_id', $instrumentId);
        return $this->db->getOne($this->table, $columns ?? '*');
    }

    /**
     * The instruments each lab has actually run this test on, over the samples
     * registered in the period, most used first.
     *
     * Read off the lab's finished work, not the instruments table: a central
     * system does not hold every lab's configured machines, but it does hold
     * their results. Rows written since instruments became a managed list carry
     * an instrument_id and take the configured name; older rows carry only the
     * typed-in platform, which stands in where there is no configured name, so
     * one machine is not listed twice under two spellings.
     *
     * One query for every lab asked about, not one per lab.
     *
     * @param list<int> $labIds
     * @return array<int, string> lab id => instrument names, comma separated
     */
    public function labInstrumentNames(string $testKey, array $labIds, string $startDate = '', string $endDate = ''): array
    {
        $labIds = array_values(array_filter(array_map('intval', $labIds), static fn(int $id): bool => $id > 0));
        if ($labIds === []) {
            return [];
        }

        $table = TestsService::getTestTableName($testKey);
        $result = 't.' . TestsService::getResultColumn($testKey);
        $platform = 't.' . TestsService::getTestPlatformColumn($testKey);
        $name = "COALESCE(NULLIF(TRIM(i.machine_name), ''), TRIM(COALESCE($platform, '')))";

        $where = [
            "t.lab_id IN (" . $this->db->inIntList($labIds) . ")",
            "TRIM(COALESCE($result, '')) <> ''",
            "$name <> ''",
        ];
        if ($startDate !== '' && $endDate !== '') {
            $where[] = SampleCountUtility::registeredBetween('t', $startDate, $endDate);
        }
        if ($discriminator = SampleFlowService::testTypeDiscriminator($testKey, 't')) {
            $where[] = $discriminator;
        }

        $rows = $this->db->rawQuery(
            "SELECT t.lab_id AS lab_id, $name AS instrument, COUNT(*) AS tests
               FROM $table AS t
               LEFT JOIN instruments AS i ON i.instrument_id = t.instrument_id
              WHERE " . implode(' AND ', $where) . "
              GROUP BY t.lab_id, instrument
              ORDER BY t.lab_id, tests DESC, instrument ASC"
        ) ?: [];

        $out = [];
        foreach ($rows as $row) {
            $labId = (int) $row['lab_id'];
            $out[$labId] = isset($out[$labId])
                ? $out[$labId] . ', ' . (string) $row['instrument']
                : (string) $row['instrument'];
        }
        return $out;
    }
}
