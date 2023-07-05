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


    // This function removes control characters from the strings in the CSV file.
    // https://en.wikipedia.org/wiki/Control_character#ASCII_control_characters
    // Also checks UTF-8 encoding and converts if needed
    public function removeCntrlCharsAndEncode($inputString, $encodeToUTF8 = true): string
    {
        return once(function () use ($inputString, $encodeToUTF8) {
            $inputString = preg_replace('/[[:cntrl:]]/', '', $inputString);
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

        $testDateFormat = trim($testDateFormat);

        if ($interpretFormat === true) {
            $find = ['am', 'pm', 'dd', 'mm', 'yyyy', 'yy'];
            $replace = ['', '', 'd', 'm', 'Y', 'y'];
            $testDateFormat = trim(str_ireplace($find, $replace, strtolower($testDateFormat)));
        }

        $testingDateFormat = substr_count($testDate, ':') === 1 ? "$testDateFormat h:i" : "$testDateFormat H:i:s";

        if (stripos($testDate, 'am') !== false || stripos($testDate, 'pm') !== false) {
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
