<?php

namespace Vlsm\Helpers;

use Exception;
use DateTimeImmutable;

class Results
{
    public function __construct()
    {
    }



    // $interpretFormat = true will try to parse and change the format to d/m/Y or m/d/Y
    // $interpretFormat = false will keep the format as is
    public static function abbottTestingDateFormatter($inputTestingDate, $inputTestingDateFormat, $interpretFormat = true)
    {
        $general = new \Vlsm\Models\General();

        if ($interpretFormat === true) {
            $find = ['am', 'pm', 'dd', 'mm', 'yyyy'];
            $replace = ['', '', 'd', 'm', 'Y'];
            $dateFormat = trim(str_ireplace($find, $replace, strtolower($inputTestingDateFormat)));
        } else {
            $dateFormat = trim($inputTestingDateFormat);
        }

        $numberOfColons = substr_count($inputTestingDate, ':');

        if ($numberOfColons === 2) {
            $testingDateFormat = "!$dateFormat H:i:s";
        } else {
            $testingDateFormat = "!$dateFormat h:i";
        }

        $checkIf12HourFormat = $general->checkIfStringExists($inputTestingDate, ['am', 'pm']);
        if ($checkIf12HourFormat !== false) {
            $testingDateFormat = "$testingDateFormat A";
        }
        $timestamp = DateTimeImmutable::createFromFormat("!" . $testingDateFormat, $inputTestingDate);
        if ($timestamp !== false && !empty($timestamp)) {
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
