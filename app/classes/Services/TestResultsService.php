<?php

namespace App\Services;

use MysqliDb;
use DateTimeImmutable;

class TestResultsService
{

    protected ?MysqliDb $db = null;

    public function __construct(?MysqliDb $db = null)
    {
        $this->db = $db;
    }
    public function clearPreviousImportsByUser($userId, $module = null)
    {
        $userId = $userId ?? $_SESSION['userId'];
        $this->db->where('imported_by', $userId);
        if (!empty($module)) {
            $this->db->where('module', $module);
        }
        return $this->db->delete('temp_sample_import');
    }

    public function getMaxIDForHoldingSamples()
    {
        $bquery = "SELECT IFNULL(MAX(import_batch_tracking), 0) + 1 AS maxId FROM `hold_sample_import`";
        $result = $this->db->rawQueryOne($bquery);
        return $result['maxId'];
    }


    // This function removes control characters from the strings in the CSV file.
    // https://en.wikipedia.org/wiki/Control_character#ASCII_control_characters
    // Also checks UTF-8 encoding and converts if needed
    public function removeCntrlCharsAndEncode($inputString, $encodeToUTF8 = true): string
    {
        return once(function () use ($inputString, $encodeToUTF8) {
            $inputString = preg_replace('/[[:cntrl:]]/', '', (string) $inputString);
            if ($encodeToUTF8 === true && mb_detect_encoding($inputString, 'UTF-8', true) === false) {
                $inputString = mb_convert_encoding($inputString, 'UTF-8');
            }
            return $inputString;
        });
    }

    public function abbottTestingDateFormatter($testDate, $testDateFormat, $interpretFormat = true): ?array
    {

        if (empty($testDate) || empty($testDateFormat)) {
            return null;
        }

        $testDateFormat = trim((string) $testDateFormat);

        if ($interpretFormat === true) {
            $find = ['am', 'pm', 'dd', 'mm', 'yyyy', 'yy'];
            $replace = ['', '', 'd', 'm', 'Y', 'y'];
            $testDateFormat = trim(str_ireplace($find, $replace, strtolower($testDateFormat)));
        }

        $testingDateFormat = substr_count((string) $testDate, ':') === 1 ? "$testDateFormat h:i" : "$testDateFormat H:i:s";

        if (stripos((string) $testDate, 'am') !== false || stripos((string) $testDate, 'pm') !== false) {
            $testingDateFormat .= ' A';
        }

        $timestamp = DateTimeImmutable::createFromFormat("!" . $testingDateFormat, $testDate);
        $testingDate = $timestamp ? $timestamp->format('Y-m-d H:i') : null;

        return [
            'testingDate' => $testingDate,
            'dateFormat' => $testDateFormat,
        ];
    }
}
