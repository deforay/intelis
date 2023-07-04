<?php

namespace App\Services;

use App\Registries\ContainerRegistry;
use MysqliDb;



class InstrumentsService
{

    protected ?MysqliDb $db = null;
    protected string $table = 'instruments';

    public function __construct($db = null)
    {
        $this->db = $db ?? ContainerRegistry::get('db');
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
                $response[$row['config_id']] = $row['machine_name'];
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


    // This function removes control characters from the strings in the CSV file.
    // https://en.wikipedia.org/wiki/Control_character#ASCII_control_characters
    // Also checks UTF-8 encoding and converts if needed
    public function removeCntrlCharsAndEncode($inputString, $encodeToUTF8 = true): string
    {
        return once(function () use ($inputString, $encodeToUTF8) {
            $inputString = preg_replace('/[[:cntrl:]]/', '', $inputString);
            if ($encodeToUTF8 && mb_detect_encoding($inputString, 'UTF-8', true) === false) {
                $inputString = mb_convert_encoding($inputString, 'UTF-8');
            }
            return $inputString;
        });
    }
}
