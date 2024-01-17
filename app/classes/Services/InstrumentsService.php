<?php

namespace App\Services;

use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;



class InstrumentsService
{

    protected ?DatabaseService $db;
    protected string $table = 'instruments';

    public function __construct(?DatabaseService $db)
    {
        $this->db = $db ?? ContainerRegistry::get(DatabaseService::class);
    }


    public function getInstruments($testType = null, $dropDown = false)
    {
        $db = $this->db;
        $db->where('status', 'active');
        if (!empty($testType)) {
            $db->where("(JSON_SEARCH(supported_tests, 'all', '$testType') IS NOT NULL) AND (supported_tests IS NOT NULL)");
        }
        $db->orderBy('machine_name', 'ASC');
        $result = $db->get($this->table);
        if ($dropDown) {
            foreach ($result as $row) {
                $response[$row['instrument_id']] = $row['machine_name'];
            }
            return $response;
        } else {
            return $result;
        }
    }
    public function getInstrumentByName($instrumentName)
    {
        $db = $this->db;
        $db->where('machine_name', $instrumentName);
        return $db->getOne($this->table);
    }
}
