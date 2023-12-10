<?php

namespace App\Services;

use InvalidArgumentException;

class TestsService
{

    private static $testTypes = [
        'vl' => [
            'testName' => _translate('HIV Viral Load'),
            'testShortName' => 'VL',
            'tableName' => 'form_vl',
            'primaryKey' => 'vl_sample_id'
        ],
        'recency' => [
            'testName' => _translate('HIV Recency'),
            'testShortName' => 'Recency',
            'tableName' => 'form_vl',
            'primaryKey' => 'vl_sample_id'
        ],
        'eid' => [
            'testName' => _translate('Early Infant Diagnosis'),
            'testShortName' => 'EID',
            'tableName' => 'form_eid',
            'primaryKey' => 'eid_id'
        ],
        'covid19' => [
            'testName' => _translate('Covid-19'),
            'testShortName' => 'covid19',
            'tableName' => 'form_covid19',
            'primaryKey' => 'covid19_id'
        ],
        'hepatitis' => [
            'testName' => _translate('Hepatitis'),
            'testShortName' => 'hepatitis',
            'tableName' => 'form_hepatitis',
            'primaryKey' => 'hepatitis_id'
        ],
        'tb' => [
            'testName' => _translate('Tubercolosis'),
            'testShortName' => 'tb',
            'tableName' => 'form_tb',
            'primaryKey' => 'tb_id'
        ],
        'generic-tests' => [
            'testName' => _translate('Other Tests'),
            'testShortName' => 'generic-tests',
            'tableName' => 'form_generic',
            'primaryKey' => 'sample_id'
        ]
    ];

    public static function getTestTableName(string $key): string
    {
        return self::$testTypes[$key]['tableName'] ?? throw new InvalidArgumentException("Invalid test type key");
    }

    public static function getTestPrimaryKeyName(string $key): string
    {
        return self::$testTypes[$key]['primaryKey'] ?? throw new InvalidArgumentException("Invalid test type key");
    }

    public static function getTestName(string $key): string
    {
        return self::$testTypes[$key]['testName'] ?? throw new InvalidArgumentException("Invalid test type key");
    }

    public static function getTestShortName(string $key): string
    {
        return self::$testTypes[$key]['testShortName'] ?? throw new InvalidArgumentException("Invalid test type key");
    }

    public static function getAllTableNames(): array
    {
        return array_column(self::$testTypes, 'tableName');
    }
}
