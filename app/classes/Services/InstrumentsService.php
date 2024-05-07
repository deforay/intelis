<?php

namespace App\Services;

use App\Services\DatabaseService;

final class InstrumentsService
{
    protected DatabaseService $db;
    protected string $table = 'instruments';

    public function __construct(DatabaseService $db)
    {
        $this->db = $db;
    }

    public function getInstruments($testType = null, $dropDown = false)
    {
        $this->db->where('ins.status', 'active');
        if (!empty($testType)) {
            $this->db->where("(JSON_SEARCH(ins.supported_tests, 'all', '$testType') IS NOT NULL) AND (ins.supported_tests IS NOT NULL)");
        }
        $this->db->join("facility_details l", "l.facility_id = ins.lab_id", "LEFT");

        $this->db->orderBy('ins.machine_name', 'ASC');
        $result = $this->db->get($this->table .' ins');
        if ($dropDown) {
            foreach ($result as $row) {
                $response[$row['instrument_id']] = $row['machine_name']. ' - ' .$row['facility_name'];
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

    public function getInstrument(string $instrumentId, string|array $columns = '*')
    {
        if (empty($instrumentId) || $instrumentId === '') {
            return null;
        }

        $this->db->where('instrument_id', $instrumentId);
        return $this->db->getOne($this->table, $columns ?? '*');
    }
}
