<?php

namespace App\Services;

use InvalidArgumentException;

class TestsService
{

    private static $testTypes = [
        'vl' => [
            'tableName' => 'form_vl',
            'primaryKey' => 'vl_sample_id'
        ],
        'recency' => [
            'tableName' => 'form_vl',
            'primaryKey' => 'vl_sample_id'
        ],
        'eid' => [
            'tableName' => 'form_eid',
            'primaryKey' => 'eid_id'
        ],
        'covid19' => [
            'tableName' => 'form_covid19',
            'primaryKey' => 'covid19_id'
        ],
        'hepatitis' => [
            'tableName' => 'form_hepatitis',
            'primaryKey' => 'hepatitis_id'
        ],
        'tb' => [
            'tableName' => 'form_tb',
            'primaryKey' => 'tb_id'
        ],
        'generic-tests' => [
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
    public static function getAllTableNames(): array
    {
        return array_column(self::$testTypes, 'tableName');
    }
}
