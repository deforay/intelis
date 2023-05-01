<?php

namespace App\Services;

use App\Registries\ContainerRegistry;
use MysqliDb;

/**
 * General functions
 *
 * @author Amit
 */

class InstrumentsService
{

    /** @var MysqliDb $db */
    protected $db = null;
    protected $table = 'instruments';

    public function __construct($db = null)
    {
        $this->db = $db ?? ContainerRegistry::get('db');
    }


    public function getInstruments($testType = null,$dropDown = false)
    {
        $db = $this->db;
        $db->where('status', 'active');
        if (!empty($testType)) {
            $db->where("(JSON_SEARCH(supported_tests, 'all', '$testType') IS NOT NULL) AND (supported_tests IS NOT NULL)");
        }
        $db->orderBy('machine_name', 'ASC');
        $result = $db->get($this->table);
        if($dropDown)
        {
            foreach ($result as $row) {
                $response[$row['config_id']] = $row['machine_name'];
            }
            return $response;
        }
        else
        {
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
