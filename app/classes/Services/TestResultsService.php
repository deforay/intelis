<?php

namespace App\Services;

use MysqliDb;
use DateTimeImmutable;
use App\Registries\ContainerRegistry;

class TestResultsService
{

    protected ?MysqliDb $db = null;
    protected CommonService $commonService;

    public function __construct(?MysqliDb $db = null, CommonService $commonService = null)
    {
        $this->db = $db ?? ContainerRegistry::get('db');
        $this->commonService = $commonService;
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

    public function abbottTestingDateFormatter($inputTestingDate, $inputTestingDateFormat, $interpretFormat = true): ?array
    {

        if (empty($inputTestingDate)) {
            return null;
        }

        if ($interpretFormat === true) {
            $find = ['am', 'pm', 'dd', 'mm', 'yyyy', 'yy'];
            $replace = ['', '', 'd', 'm', 'Y', 'y'];
            $dateFormat = trim(str_ireplace($find, $replace, strtolower($inputTestingDateFormat)));
        } else {
            $dateFormat = trim($inputTestingDateFormat);
        }

        $numberOfColons = substr_count($inputTestingDate, ':');

        if ($numberOfColons === 1) {
            $testingDateFormat = "$dateFormat h:i";
        } elseif ($numberOfColons === 2) {
            $testingDateFormat = "$dateFormat H:i:s";
        }

        $checkIf12HourFormat = $this->commonService->checkIfStringExists($inputTestingDate, ['am', 'pm']);
        if ($checkIf12HourFormat !== false) {
            $testingDateFormat = "$testingDateFormat A";
        }
        $timestamp = DateTimeImmutable::createFromFormat("!" . $testingDateFormat, $inputTestingDate);
        if (!empty($timestamp)) {
            $testingDate = $timestamp->format('Y-m-d H:i');
        } else {
            $testingDate = null;
        }

        return [
            'testingDate' => $testingDate,
            'dateFormat' => $dateFormat,
        ];
    }

}
