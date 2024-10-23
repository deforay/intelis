<?php

namespace App\Services;

use App\Services\TbService;
use App\Services\VlService;
use App\Services\CD4Service;
use App\Services\EidService;
use InvalidArgumentException;
use App\Services\Covid19Service;
use App\Services\HepatitisService;
use App\Services\GenericTestsService;

final class TestsService
{
    public static function getTestTypes()
    {
        $testTypes = [
            'vl' => [
                'testName' => _translate('HIV Viral Load'),
                'testShortCode' => 'VL',
                'tableName' => 'form_vl',
                'primaryKey' => 'vl_sample_id',
                'patientId' => 'patient_art_no',
                'patientFirstName' => 'patient_first_name',
                'patientLastName' => 'patient_last_name',
                'specimenType' => 'specimen_type',
                'serviceClass' => VlService::class
            ],
            'recency' => [
                'testName' => _translate('HIV Recency'),
                'testShortCode' => 'VL',
                'tableName' => 'form_vl',
                'primaryKey' => 'vl_sample_id',
                'patientId' => 'patient_art_no',
                'patientFirstName' => 'patient_first_name',
                'patientLastName' => 'patient_last_name',
                'specimenType' => 'specimen_type',
                'serviceClass' => VlService::class
            ],
            'cd4' => [
                'testName' => _translate('CD4'),
                'testShortCode' => 'CD4',
                'tableName' => 'form_cd4',
                'primaryKey' => 'cd4_id',
                'patientId' => 'patient_art_no',
                'patientFirstName' => 'patient_first_name',
                'patientLastName' => 'patient_last_name',
                'specimenType' => 'specimen_type',
                'serviceClass' => CD4Service::class
            ],
            'eid' => [
                'testName' => _translate('Early Infant Diagnosis'),
                'testShortCode' => 'EID',
                'tableName' => 'form_eid',
                'primaryKey' => 'eid_id',
                'patientId' => 'child_id',
                'patientFirstName' => 'child_name',
                'patientLastName' => 'child_surname',
                'specimenType' => 'specimen_type',
                'serviceClass' => EidService::class
            ],
            'covid19' => [
                'testName' => _translate('Covid-19'),
                'testShortCode' => 'C19',
                'tableName' => 'form_covid19',
                'primaryKey' => 'covid19_id',
                'patientId' => 'patient_id',
                'patientFirstName' => 'patient_name',
                'patientLastName' => 'patient_surname',
                'specimenType' => 'specimen_type',
                'serviceClass' => Covid19Service::class
            ],
            'hepatitis' => [
                'testName' => _translate('Hepatitis'),
                'testShortCode' => 'HEP',
                'tableName' => 'form_hepatitis',
                'primaryKey' => 'hepatitis_id',
                'patientId' => 'patient_id',
                'patientFirstName' => 'patient_name',
                'patientLastName' => 'patient_surname',
                'specimenType' => 'specimen_type',
                'serviceClass' => HepatitisService::class
            ],
            'tb' => [
                'testName' => _translate('Tubercolosis'),
                'testShortCode' => 'TB',
                'tableName' => 'form_tb',
                'primaryKey' => 'tb_id',
                'patientId' => 'patient_id',
                'patientFirstName' => 'patient_name',
                'patientLastName' => 'patient_surname',
                'specimenType' => 'specimen_type',
                'serviceClass' => TbService::class
            ],
            'generic-tests' => [
                'testName' => _translate('Other Tests'),
                'testShortCode' => 'T',
                'tableName' => 'form_generic',
                'primaryKey' => 'sample_id',
                'patientId' => 'patient_id',
                'patientFirstName' => 'patient_first_name',
                'patientLastName' => 'patient_last_name',
                'specimenType' => 'specimen_type',
                'serviceClass' => GenericTestsService::class
            ]
        ];

        // Define aliases
        $aliases = [
            'custom-tests' => 'generic-tests',
            'custom-test' => 'generic-tests',
            'covid-19' => 'covid19',
            'covid' => 'covid19'
        ];

        // Resolve aliases
        foreach ($aliases as $alias => $original) {
            if (isset($testTypes[$original])) {
                $testTypes[$alias] = $testTypes[$original];
            }
        }

        return $testTypes;
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

    public static function getTestShortCode(string $testType): string
    {
        return self::getTestTypes()[$testType]['testShortCode'] ?? throw new InvalidArgumentException("Invalid test type key");
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

    public static function getTestServiceClass(string $testType): string
    {
        return self::getTestTypes()[$testType]['serviceClass'] ?? throw new InvalidArgumentException("Invalid test type key");
    }

    public static function getAllTableNames(): array
    {
        return array_column(self::getTestTypes(), 'tableName');
    }
}
