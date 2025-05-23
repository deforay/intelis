<?php

namespace App\Services;

use App\Services\DatabaseService;
use App\Utilities\FileCacheUtility;

final class InstrumentsService
{
    protected DatabaseService $db;
    protected string $table = 'instruments';
    protected $fileCache;

    public function __construct(DatabaseService $db, FileCacheUtility $fileCache)
    {
        $this->fileCache = $fileCache;
        $this->fileCache->setPrefix('instruments_');
        $this->db = $db;
    }

    public function getInstrumentsCount()
    {
        $key = 'instruments_count';
        return $this->fileCache->get($key, function ()  {
            $this->db->where("status", "active");
            return $this->db->getValue("instruments", "count(*)");
        }, ['instruments']);
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
        if (empty($instrumentId) || $instrumentId === '') {
            return null;
        }

        $this->db->where('instrument_id', $instrumentId);
        return $this->db->getOne($this->table, $columns ?? '*');
    }
}
