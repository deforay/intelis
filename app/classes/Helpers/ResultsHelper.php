<?php

namespace App\Helpers;

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use DateTimeImmutable;

class ResultsHelper
{
    public function __construct()
    {
    }

    // $interpretFormat = true will try to parse and change the format to d/m/Y or m/d/Y
    // $interpretFormat = false will keep the format as is
    public static function abbottTestingDateFormatter($inputTestingDate, $inputTestingDateFormat, $interpretFormat = true)
    {

        if (empty($inputTestingDate)) {
            return null;
        }

        /** @var MysqliDb $db */
        $db = ContainerRegistry::get('db');

        /** @var CommonService $general */
        $general = ContainerRegistry::get(CommonService::class);

        if ($interpretFormat === true) {
            $find =     ['am', 'pm', 'dd', 'mm', 'yyyy', 'yy'];
            $replace =  ['',   '',    'd', 'm',  'Y',    'y'];
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
