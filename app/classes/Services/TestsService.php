<?php

namespace App\Services;

use InvalidArgumentException;

class TestsService
{
    public static function getTestTypes()
    {
        return [
            'vl' => [
                'testName' => _translate('HIV Viral Load'),
                'testShortName' => 'VL',
                'tableName' => 'form_vl',
                'primaryKey' => 'vl_sample_id',
                'patientId' => 'patient_art_no',
                'patientFirstName' => 'patient_first_name',
                'patientLastName' => 'patient_last_name',
                'specimenType' => 'specimen_type'
            ],
            'recency' => [
                'testName' => _translate('HIV Recency'),
                'testShortName' => 'Recency',
                'tableName' => 'form_vl',
                'primaryKey' => 'vl_sample_id',
                'patientId' => 'patient_art_no',
                'patientFirstName' => 'patient_first_name',
                'patientLastName' => 'patient_last_name',
                'specimenType' => 'specimen_type'
            ],
            'cd4' => [
                'testName' => _translate('CD4'),
                'testShortName' => 'CD4',
                'tableName' => 'form_cd4',
                'primaryKey' => 'cd4_id',
                'patientId' => 'patient_art_no',
                'patientFirstName' => 'patient_first_name',
                'patientLastName' => 'patient_last_name',
                'specimenType' => 'specimen_type'
            ],
            'eid' => [
                'testName' => _translate('Early Infant Diagnosis'),
                'testShortName' => 'EID',
                'tableName' => 'form_eid',
                'primaryKey' => 'eid_id',
                'patientId' => 'child_id',
                'patientFirstName' => 'child_name',
                'patientLastName' => 'child_surname',
                'specimenType' => 'specimen_type'
            ],
            'covid19' => [
                'testName' => _translate('Covid-19'),
                'testShortName' => 'covid19',
                'tableName' => 'form_covid19',
                'primaryKey' => 'covid19_id',
                'patientId' => 'patient_id',
                'patientFirstName' => 'patient_name',
                'patientLastName' => 'patient_surname',
                'specimenType' => 'specimen_type'
            ],
            'hepatitis' => [
                'testName' => _translate('Hepatitis'),
                'testShortName' => 'hepatitis',
                'tableName' => 'form_hepatitis',
                'primaryKey' => 'hepatitis_id',
                'patientId' => 'patient_id',
                'patientFirstName' => 'patient_name',
                'patientLastName' => 'patient_surname',
                'specimenType' => 'specimen_type'
            ],
            'tb' => [
                'testName' => _translate('Tubercolosis'),
                'testShortName' => 'tb',
                'tableName' => 'form_tb',
                'primaryKey' => 'tb_id',
                'patientId' => 'patient_id',
                'patientFirstName' => 'patient_name',
                'patientLastName' => 'patient_surname',
                'specimenType' => 'specimen_type'
            ],
            'generic-tests' => [
                'testName' => _translate('Other Tests'),
                'testShortName' => 'generic-tests',
                'tableName' => 'form_generic',
                'primaryKey' => 'sample_id',
                'patientId' => 'patient_id',
                'patientFirstName' => 'patient_first_name',
                'patientLastName' => 'patient_last_name',
                'specimenType' => 'specimen_type'
            ]
        ];
    }

    public static function getAllData($testType): array
    {
        return self::getTestTypes()[$testType];
    }

    public static function getTestTableName(string $testType): string
    {
        return self::getTestTypes()[$testType]['tableName'] ?? throw new InvalidArgumentException("Invalid test type key");
    }

    public static function getTestPrimaryKeyColumn(string $testType): string
    {
        return self::getTestTypes()[$testType]['primaryKey'] ?? throw new InvalidArgumentException("Invalid test type key");
    }

    public static function getTestName(string $testType): string
    {
        return self::getTestTypes()[$testType]['testName'] ?? throw new InvalidArgumentException("Invalid test type key");
    }

    public static function getTestShortName(string $testType): string
    {
        return self::getTestTypes()[$testType]['testShortName'] ?? throw new InvalidArgumentException("Invalid test type key");
    }

    public static function getPatientIdColumn(string $testType): string
    {
        return self::getTestTypes()[$testType]['patientId'] ?? throw new InvalidArgumentException("Invalid test type key");
    }

    public static function getPatientFirstNameColumn(string $key): string
    {
        return self::getTestTypes()[$key]['patientFirstName'] ?? throw new InvalidArgumentException("Invalid test type key");
    }

    public static function getPatientLastNameColumn(string $testType): string
    {
        return self::getTestTypes()[$testType]['patientLastName'] ?? throw new InvalidArgumentException("Invalid test type key");
    }

    public static function getSpecimenTypeColumn(string $testType): string
    {
        return self::getTestTypes()[$testType]['specimenType'] ?? throw new InvalidArgumentException("Invalid test type key");
    }

    public static function getAllTableNames(): array
    {
        return array_column(self::getTestTypes(), 'tableName');
    }
}
