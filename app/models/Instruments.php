<?php

namespace Vlsm\Models;

/**
 * General functions
 *
 * @author Amit
 */

class Instruments
{

    protected $db = null;
    protected $table = 'import_config';

    public function __construct($db = null)
    {
        $this->db = !empty($db) ? $db : \MysqliDb::getInstance();
    }


    public function getInstruments($testType = null)
    {
        $db = $this->db;
        $db->where('status', 'active');
        if (!empty($testType)) {
            $db->where("(JSON_SEARCH(supported_tests, 'all', '$testType') IS NOT NULL) AND (supported_tests IS NOT NULL)");
        }
        $db->orderBy('machine_name', 'ASC');
        $instruments = $db->get($this->table);
        return $instruments;
    }
    public function getInstrumentByName($instrumentName)
    {
        $db = $this->db;
        $db->where('machine_name', $instrumentName);
        return $db->getOne($this->table);
    }
}
