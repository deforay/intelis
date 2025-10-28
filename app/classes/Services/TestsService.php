<?php

namespace App\Services;

use App\Services\TbService;
use App\Services\VlService;
use App\Services\CD4Service;
use App\Services\EidService;
use App\Services\Covid19Service;
use App\Services\HepatitisService;
use App\Exceptions\SystemException;
use App\Services\GenericTestsService;

final class TestsService
{
    public static function getTestTypes()
    {
        $testTypes = [
            'vl' => [
                'testName' => _translate('HIV Viral Load', escapeTextOrContext: true),
                'testShortCode' => 'VL',
                'tableName' => 'form_vl',
                'primaryKey' => 'vl_sample_id',
                'patientId' => 'patient_art_no',
                'patientFirstName' => 'patient_first_name',
                'patientLastName' => 'patient_last_name',
                'resultColumn' => 'result',
                'specimenType' => 'specimen_type',
                'specimenTypeTable' => 'r_vl_sample_type',
                'serviceClass' => VlService::class
            ],
            'recency' => [
                'testName' => _translate('HIV Recency', escapeTextOrContext: true),
                'testShortCode' => 'VL',
                'tableName' => 'form_vl',
                'primaryKey' => 'vl_sample_id',
                'patientId' => 'patient_art_no',
                'patientFirstName' => 'patient_first_name',
                'patientLastName' => 'patient_last_name',
                'resultColumn' => 'result',
                'specimenType' => 'specimen_type',
                'specimenTypeTable' => 'r_vl_sample_type',
                'serviceClass' => VlService::class
            ],
            'cd4' => [
                'testName' => _translate('CD4', escapeTextOrContext: true),
                'testShortCode' => 'CD4',
                'tableName' => 'form_cd4',
                'primaryKey' => 'cd4_id',
                'patientId' => 'patient_art_no',
                'patientFirstName' => 'patient_first_name',
                'patientLastName' => 'patient_last_name',
                'resultColumn' => 'result_cd4',
                'specimenType' => 'specimen_type',
                'specimenTypeTable' => 'r_vl_sample_type',
                'serviceClass' => CD4Service::class
            ],
            'eid' => [
                'testName' => _translate('Early Infant Diagnosis', escapeTextOrContext: true),
                'testShortCode' => 'EID',
                'tableName' => 'form_eid',
                'primaryKey' => 'eid_id',
                'patientId' => 'child_id',
                'patientFirstName' => 'child_name',
                'patientLastName' => 'child_surname',
                'resultColumn' => 'result',
                'specimenType' => 'specimen_type',
                'specimenTypeTable' => 'r_eid_sample_type',
                'serviceClass' => EidService::class
            ],
            'covid19' => [
                'testName' => _translate('Covid-19', escapeTextOrContext: true),
                'testShortCode' => 'C19',
                'tableName' => 'form_covid19',
                'primaryKey' => 'covid19_id',
                'patientId' => 'patient_id',
                'patientFirstName' => 'patient_name',
                'patientLastName' => 'patient_surname',
                'resultColumn' => 'result',
                'specimenType' => 'specimen_type',
                'specimenTypeTable' => 'r_covid19_sample_type',
                'serviceClass' => Covid19Service::class
            ],
            'hepatitis' => [
                'testName' => _translate('Hepatitis', escapeTextOrContext: true),
                'testShortCode' => 'HEP',
                'tableName' => 'form_hepatitis',
                'primaryKey' => 'hepatitis_id',
                'patientId' => 'patient_id',
                'patientFirstName' => 'patient_name',
                'patientLastName' => 'patient_surname',
                'resultColumn' => 'result',
                'specimenType' => 'specimen_type',
                'specimenTypeTable' => 'r_hepatitis_sample_type',
                'serviceClass' => HepatitisService::class
            ],
            'tb' => [
                'testName' => _translate('Tubercolosis', escapeTextOrContext: true),
                'testShortCode' => 'TB',
                'tableName' => 'form_tb',
                'primaryKey' => 'tb_id',
                'patientId' => 'patient_id',
                'patientFirstName' => 'patient_name',
                'patientLastName' => 'patient_surname',
                'resultColumn' => 'result',
                'specimenType' => 'specimen_type',
                'specimenTypeTable' => 'r_tb_sample_type',
                'serviceClass' => TbService::class
            ],
            'generic-tests' => [
                'testName' => _translate('Other Tests', escapeTextOrContext: true),
                'testShortCode' => 'T',
                'tableName' => 'form_generic',
                'primaryKey' => 'sample_id',
                'patientId' => 'patient_id',
                'patientFirstName' => 'patient_first_name',
                'patientLastName' => 'patient_last_name',
                'resultColumn' => 'result',
                'specimenType' => 'specimen_type',
                'specimenTypeTable' => 'r_generic_sample_types',
                'serviceClass' => GenericTestsService::class
            ]
        ];

        // Define aliases
        $aliases = [
            'custom-tests' => 'generic-tests',
            'custom-test' => 'generic-tests',
            'covid-19' => 'covid19',
            'covid' => 'covid19',
            'recency' => 'vl'
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
        return self::getTestTypes()[$testType]['tableName'] ?? throw new SystemException("Invalid test type key");
    }

    public static function getPrimaryColumn(string $testType): string
    {
        return self::getTestTypes()[$testType]['primaryKey'] ?? throw new SystemException("Invalid test type key");
    }

    public static function getTestName(string $testType): string
    {
        return self::getTestTypes()[$testType]['testName'] ?? throw new SystemException("Invalid test type key");
    }

    public static function getTestShortCode(string $testType): string
    {
        return self::getTestTypes()[$testType]['testShortCode'] ?? throw new SystemException("Invalid test type key");
    }

    public static function getPatientIdColumn(string $testType): string
    {
        return self::getTestTypes()[$testType]['patientId'] ?? throw new SystemException("Invalid test type key");
    }

    public static function getPatientFirstNameColumn(string $testType): string
    {
        return self::getTestTypes()[$testType]['patientFirstName'] ?? throw new SystemException("Invalid test type key");
    }

    public static function getPatientLastNameColumn(string $testType): string
    {
        return self::getTestTypes()[$testType]['patientLastName'] ?? throw new SystemException("Invalid test type key");
    }

    public static function getSpecimenTypeColumn(string $testType): string
    {
        return self::getTestTypes()[$testType]['specimenType'] ?? throw new SystemException("Invalid test type key");
    }

    public static function getSpecimenTypeTable(string $testType): string
    {
        return self::getTestTypes()[$testType]['specimenTypeTable'] ?? throw new SystemException("Invalid test type key");
    }


    public static function getResultColumn(string $testType): string
    {
        return self::getTestTypes()[$testType]['resultColumn'] ?? throw new SystemException("Invalid test type key");
    }

    public static function getTestServiceClass(string $testType): string
    {
        return self::getTestTypes()[$testType]['serviceClass'] ?? throw new SystemException("Invalid test type key");
    }

    public static function getAllTableNames(): array
    {
        return array_column(self::getTestTypes(), 'tableName');
    }
}
