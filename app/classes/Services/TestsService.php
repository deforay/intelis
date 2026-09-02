<?php

namespace App\Services;

use App\Services\TbService;
use App\Services\VlService;
use App\Services\CD4Service;
use App\Services\EidService;
use App\Utilities\MemoUtility;
use App\Services\Covid19Service;
use App\Services\HepatitisService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;
use App\Services\GenericTestsService;

final class TestsService
{
    /**
     * Check if a test is active.
     */
    public static function isTestActive(string $module): bool
    {

        $module = trim($module);
        if ($module === '') {
            return false;
        }

        $activeModules = self::getActiveTests();
        $activeLower = array_map('strtolower', $activeModules);

        return in_array(strtolower($module), $activeLower, true);
    }

    public static function getActiveTests(): array
    {
        return MemoUtility::remember(function (): array {
            $activeModules = SystemService::getActiveModules(onlyTests: true);
            $activeTests = [];
            foreach ($activeModules as $module) {
                if (isset(self::getTestTypes()[strtolower($module)])) {
                    $activeTests[] = strtolower($module);
                }
            }
            return $activeTests;
        });
    }


    /**
     * Per-module registry.
     *
     * The retest keys (testPlatformColumn, childResultTable, childResultKey,
     * deleteChildOnRetest, clearOnRetest) live here rather than alongside the archiving
     * code so that adding a module means editing one map. They were previously implicit,
     * duplicated across seven copies of failed-results-retest.php, which is how TB and
     * Hepatitis ended up recording their history under the wrong test type.
     *
     * clearOnRetest lists the columns wiped when a failed sample is sent back for
     * re-testing. result_status is set separately by the caller and is not listed.
     * TestAttemptService consumes these; see app/classes/Services/TestAttemptService.php.
     */
    public static function getTestTypes(): array
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
                'serviceClass' => VlService::class,
                'isReferrable' => false,
                'testPlatformColumn' => 'vl_test_platform',
                'childResultTable' => null,
                'childResultKey' => null,
                'deleteChildOnRetest' => false,
                'clearOnRetest' => [
                    'result', 'result_value_log', 'result_value_absolute', 'result_value_text',
                    'result_value_absolute_decimal', 'sample_tested_datetime', 'sample_batch_id',
                    'lot_expiration_date', 'lot_number',
                    // Previously left stale, so a wiped sample kept looking categorised.
                    'vl_result_category',
                ]
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
                'serviceClass' => VlService::class,
                'isReferrable' => false,
                'testPlatformColumn' => 'vl_test_platform',
                'childResultTable' => null,
                'childResultKey' => null,
                'deleteChildOnRetest' => false,
                'clearOnRetest' => [
                    'result', 'result_value_log', 'result_value_absolute', 'result_value_text',
                    'result_value_absolute_decimal', 'sample_tested_datetime', 'sample_batch_id',
                    'lot_expiration_date', 'lot_number', 'vl_result_category',
                ]
            ],
            'cd4' => [
                'testName' => _translate('CD4', escapeTextOrContext: true),
                'testShortCode' => 'CD4',
                'tableName' => 'form_cd4',
                'primaryKey' => 'cd4_id',
                'patientId' => 'patient_art_no',
                'patientFirstName' => 'patient_first_name',
                'patientLastName' => 'patient_last_name',
                'resultColumn' => 'cd4_result',
                'specimenType' => 'specimen_type',
                'specimenTypeTable' => 'r_vl_sample_type',
                'serviceClass' => CD4Service::class,
                'isReferrable' => false,
                'testPlatformColumn' => 'cd4_test_platform',
                'childResultTable' => null,
                'childResultKey' => null,
                'deleteChildOnRetest' => false,
                'clearOnRetest' => [
                    'cd4_result', 'sample_tested_datetime', 'sample_batch_id',
                ]
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
                'serviceClass' => EidService::class,
                'isReferrable' => false,
                'testPlatformColumn' => 'eid_test_platform',
                'childResultTable' => null,
                'childResultKey' => null,
                'deleteChildOnRetest' => false,
                'clearOnRetest' => [
                    'result', 'sample_tested_datetime', 'sample_batch_id',
                    'lot_expiration_date', 'lot_number',
                ]
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
                'serviceClass' => Covid19Service::class,
                'isReferrable' => false,
                'testPlatformColumn' => 'covid19_test_platform',
                'childResultTable' => 'covid19_tests',
                'childResultKey' => 'covid19_id',
                'deleteChildOnRetest' => true,
                'clearOnRetest' => [
                    'result', 'sample_tested_datetime', 'sample_batch_id',
                    'lot_expiration_date', 'lot_number',
                ]
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
                'serviceClass' => HepatitisService::class,
                'isReferrable' => false,
                'testPlatformColumn' => 'hepatitis_test_platform',
                'childResultTable' => null,
                'childResultKey' => null,
                'deleteChildOnRetest' => false,
                'clearOnRetest' => [
                    'result', 'sample_tested_datetime', 'sample_batch_id',
                    'lot_expiration_date', 'lot_number',
                ]
            ],
            'tb' => [
                'testName' => _translate('Tuberculosis', escapeTextOrContext: true),
                'testShortCode' => 'TB',
                'tableName' => 'form_tb',
                'primaryKey' => 'tb_id',
                'patientId' => 'patient_id',
                'patientFirstName' => 'patient_name',
                'patientLastName' => 'patient_surname',
                'resultColumn' => 'result',
                'specimenType' => 'specimen_type',
                'specimenTypeTable' => 'r_tb_sample_type',
                'serviceClass' => TbService::class,
                'isReferrable' => true,
                'testPlatformColumn' => 'tb_test_platform',
                'childResultTable' => 'tb_tests',
                'childResultKey' => 'tb_id',
                'deleteChildOnRetest' => true,
                'clearOnRetest' => [
                    'result', 'xpert_mtb_result', 'sample_tested_datetime', 'sample_batch_id',
                ]
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
                'serviceClass' => GenericTestsService::class,
                'isReferrable' => true,
                'testPlatformColumn' => 'test_platform',
                'childResultTable' => 'generic_test_results',
                'childResultKey' => 'generic_id',
                // Custom Tests keep their per-test rows on retest, matching this module's
                // existing behaviour. They are archived either way.
                'deleteChildOnRetest' => false,
                'clearOnRetest' => [
                    'result', 'sample_tested_datetime', 'sample_batch_id',
                    'lot_expiration_date', 'lot_number',
                ]
            ]
        ];

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

    /**
     * Whether this test type is referrable to other labs. Only TB and
     * Custom/Generic tests are; the other modules are not.
     *
     * Drives two behaviours:
     *  - the per-sample referral workflow: referrable tables carry a
     *    referral_manifest_code column (others use sample_package_code only);
     *  - the lab-aware sample-code postfix: referrable types encode the testing
     *    lab on both LIS and STS (see AbstractTestService::labPostfix()).
     */
    public static function isReferrable(string $testType): bool
    {
        return !empty(self::getTestTypes()[$testType]['isReferrable']);
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

    /**
     * Reverse lookup: form_* table name to test type key.
     *
     * 'recency' shares form_vl with 'vl', so the first match wins and resolves to 'vl' --
     * correct for anything keyed off the table, which cannot tell the two apart anyway.
     */
    public static function getTestTypeByTable(string $tableName): ?string
    {
        foreach (self::getTestTypes() as $testType => $module) {
            if (($module['tableName'] ?? null) === $tableName) {
                return $testType;
            }
        }

        return null;
    }

    /** Column holding the testing platform. Named differently in every module. */
    public static function getTestPlatformColumn(string $testType): string
    {
        return self::getTestTypes()[$testType]['testPlatformColumn'] ?? throw new SystemException("Invalid test type key");
    }

    /**
     * Per-test child result table for modules that store one row per sub-test
     * (tb_tests, covid19_tests, generic_test_results), or null where the result is flat.
     *
     * @return array{table: string, key: string}|null
     */
    public static function getChildResultTable(string $testType): ?array
    {
        $module = self::getTestTypes()[$testType] ?? throw new SystemException("Invalid test type key");

        if (empty($module['childResultTable']) || empty($module['childResultKey'])) {
            return null;
        }

        return ['table' => $module['childResultTable'], 'key' => $module['childResultKey']];
    }

    /** Whether sending a sample back for re-testing also deletes its child result rows. */
    public static function deletesChildOnRetest(string $testType): bool
    {
        return (bool) (self::getTestTypes()[$testType]['deleteChildOnRetest'] ?? false);
    }

    /**
     * Columns wiped when a failed sample is sent back for re-testing. result_status is
     * set by the caller and is deliberately not in this list.
     *
     * @return string[]
     */
    public static function getColumnsClearedOnRetest(string $testType): array
    {
        return self::getTestTypes()[$testType]['clearOnRetest'] ?? throw new SystemException("Invalid test type key");
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

    /**
     * Get sample types for a specific test type.
     * 
     * @param string $testType The test type (e.g., 'vl', 'eid', 'covid19')
     * @return array The sample types array
     */
    public static function getSampleTypes(string $testType): array
    {
        $serviceClass = self::getTestServiceClass($testType);
        $service = ContainerRegistry::get($serviceClass);

        $methodMap = [
            'vl' => 'getVlSampleTypes',
            'recency' => 'getVlSampleTypes',
            'eid' => 'getEidSampleTypes',
            'covid19' => 'getCovid19SampleTypes',
            'hepatitis' => 'getHepatitisSampleTypes',
            'tb' => 'getTbSampleTypes',
            'cd4' => 'getCd4SampleTypes',
            'generic-tests' => 'getGenericSampleTypes',
        ];

        $method = $methodMap[$testType] ?? null;

        if ($method === null || !method_exists($service, $method)) {
            return [];
        }

        return $service->$method();
    }
}
