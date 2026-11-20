<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Registries\ContainerRegistry;
use App\HttpHandlers\LegacyRequestHandler;
use App\Services\CommonService;
use App\Services\ImportedSampleMatcher;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyAppHarness;
use Tests\Support\TestRun;

/**
 * EID and COVID-19 results imported from an instrument file are saved the way the
 * manual result pages save them.
 *
 * - A COVID-19 file without a lot number imports. covid19_tests.test_name is required,
 *   and the import passed the lot number as the test name.
 * - The update log names the test the result is for, as the result pages do, not "vl".
 * - A sample the import creates has a request date, set by the import rather than left
 *   to the column default.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class ResultImportEidCovid19Test extends TestCase
{
    private const DATABASE = 'intelis_result_import_eid_covid19_test';

    private static function booted(): bool
    {
        return getenv('INTELIS_TEST_DB_HOST') !== false
            && getenv('INTELIS_TEST_DB_HOST') !== ''
            && getenv('INTELIS_TEST_DB_USER') !== false
            && getenv('INTELIS_TEST_DB_USER') !== '';
    }

    protected function setUp(): void
    {
        if (!self::booted()) {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }

        // The page copies the imported file under UPLOAD_PATH once the results are in.
        if (!defined('UPLOAD_PATH')) {
            define('UPLOAD_PATH', sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'intelis-uploads-' . getmypid());
        }
        $db = LegacyAppHarness::boot(TestRun::database(self::DATABASE), [
            'r_sample_status', 'system_config', 'global_config', 'facility_details', 'roles', 'user_details',
            'form_vl', 'form_eid', 'test_result_attempts', 'temp_sample_import', 'log_result_updates',
            'result_import_stats', 'hold_sample_import', 'vl_imported_controls', 's_vlsm_instance',
            'instruments', 'instrument_controls', 'r_vl_sample_rejection_reasons', 'batch_details', 'r_sample_controls',
            'testing_labs', 'form_covid19', 'covid19_tests',
        ]);
        LegacyAppHarness::addMigrationColumns('5.7.82', ['form_vl', 'form_eid']);
        LegacyAppHarness::addMigrationColumns('5.7.85', ['form_vl', 'form_eid']);
        LegacyAppHarness::addMigrationColumns('5.7.86', ['temp_sample_import']);

        $db->rawQuery(
            "INSERT INTO r_sample_status (status_id, status_name)
             VALUES (5, 'Failed'), (6, 'Received at lab'), (7, 'Accepted'), (8, 'Awaiting approval')"
        );
        $db->rawQuery(
            "INSERT INTO facility_details (facility_id, facility_name, facility_type)
             VALUES (1, 'Lab One', 2), (10, 'Clinic', 1)"
        );
        $db->insert('global_config', ['display_name' => 'Form', 'name' => 'vl_form', 'value' => '1']);
        $db->rawQuery("INSERT INTO s_vlsm_instance (vlsm_instance_id) VALUES ('test')");
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        if (self::booted()) {
            LegacyAppHarness::shutdown();
            @rmdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'imported-results');
            @rmdir(UPLOAD_PATH);
        }
    }

    /** A sample of lab 1 waiting for its result. */
    private function sample(string $module, string $code): int
    {
        $table = $module === 'eid' ? 'form_eid' : 'form_covid19';
        LegacyAppHarness::db()->insert($table, [
            'vlsm_instance_id' => 'test',
            'unique_id' => uniqid('u', true),
            'sample_code' => $code,
            'facility_id' => 10,
            'lab_id' => 1,
            'result_status' => 6,
        ]);
        return (int) LegacyAppHarness::db()->getInsertId();
    }

    /**
     * A row of an imported file, staged as the parsers stage it, for lab 1.
     *
     * @param array<string, mixed> $columns
     */
    private function staged(string $module, string $code, string $result, array $columns = []): int
    {
        $data = ContainerRegistry::get(ImportedSampleMatcher::class)->stage($module, $columns + [
            'module' => $module,
            'lab_id' => 1,
            'sample_code' => $code,
            'sample_type' => 'S',
            'sample_tested_datetime' => '2026-10-01 10:00:00',
            'result' => $result,
            'result_status' => 7,
            'import_machine_file_name' => 'run.csv',
            'imported_by' => 'importer',
        ]);
        LegacyAppHarness::db()->insert('temp_sample_import', $data);
        return (int) LegacyAppHarness::db()->getInsertId();
    }

    /** Submits the staged row as accepted. One request per test: the handler loads the page with require_once. */
    private function process(string $module, int $tempId): void
    {
        LegacyAppHarness::withSession([
            'userId' => 'importer', 'userName' => 'Importer', 'instance' => ['type' => 'vluser'],
        ]);
        $request = LegacyAppHarness::withPost([
            'module' => $module, 'value' => (string) $tempId, 'status' => '7', 'rejectReasonId' => '',
            'comments' => '', 'testBy' => 'tester', 'reviewedBy' => 'reviewer', 'appBy' => 'approver',
        ], '/import-result/processImportedResults.php');
        $handler = new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));
        $reply = trim((string) $handler->handle($request)->getBody());
        self::assertSame('importedStatistics.php', $reply, 'the page answered');
    }

    /**
     * Every table has defaulted the column to the current time since 5.1.3. Take the
     * default away, so the date found is the one the import wrote.
     */
    private function withoutRequestDateDefault(string $table): void
    {
        LegacyAppHarness::db()->rawQuery(
            "ALTER TABLE `{$table}` MODIFY `request_created_datetime` TIMESTAMP NULL DEFAULT NULL"
        );
    }

    /** @return list<string> */
    private function loggedTestTypes(int $sampleId): array
    {
        return array_column(LegacyAppHarness::db()->rawQuery(
            "SELECT test_type FROM log_result_updates WHERE vl_sample_id = ? AND result_method = 'import'",
            [$sampleId]
        ), 'test_type');
    }

    #[RunInSeparateProcess]
    public function testACovid19FileWithoutALotNumberImports(): void
    {
        $db = LegacyAppHarness::db();
        $covidId = $this->sample('covid19', 'C19-1');
        $tempId = $this->staged('covid19', 'C19-1', 'negative', ['lot_number' => null]);

        $this->process('covid19', $tempId);

        self::assertSame(
            'negative',
            $db->rawQueryValue('SELECT result FROM form_covid19 WHERE covid19_id = ? LIMIT 1', [$covidId])
        );
        // The result page saves no test row without a test name.
        self::assertSame(0, (int) $db->rawQueryValue(
            'SELECT COUNT(*) FROM covid19_tests WHERE covid19_id = ? LIMIT 1',
            [$covidId]
        ), 'no test row without a test name');
    }

    #[RunInSeparateProcess]
    public function testACovid19FileWithALotNumberSavesItsTestRow(): void
    {
        $db = LegacyAppHarness::db();
        $covidId = $this->sample('covid19', 'C19-2');
        $tempId = $this->staged('covid19', 'C19-2', 'positive', ['lot_number' => 'KIT-1']);

        $this->process('covid19', $tempId);

        $tests = $db->rawQuery('SELECT test_name, result FROM covid19_tests WHERE covid19_id = ?', [$covidId]);
        self::assertCount(1, $tests);
        self::assertSame('KIT-1', $tests[0]['test_name']);
        self::assertSame('positive', $tests[0]['result']);
    }

    #[RunInSeparateProcess]
    public function testTheUpdateLogNamesEid(): void
    {
        $eidId = $this->sample('eid', 'EID-1');
        $tempId = $this->staged('eid', 'EID-1', 'positive');

        $this->process('eid', $tempId);

        self::assertSame(['eid'], $this->loggedTestTypes($eidId), 'as the EID result page logs it');
    }

    #[RunInSeparateProcess]
    public function testTheUpdateLogNamesCovid19(): void
    {
        $covidId = $this->sample('covid19', 'C19-3');
        $tempId = $this->staged('covid19', 'C19-3', 'negative', ['lot_number' => 'KIT-1']);

        $this->process('covid19', $tempId);

        self::assertSame(['covid19'], $this->loggedTestTypes($covidId), 'as the COVID-19 result page logs it');
    }

    #[RunInSeparateProcess]
    public function testAnEidSampleTheImportCreatesHasARequestDate(): void
    {
        $this->withoutRequestDateDefault('form_eid');
        $tempId = $this->staged('eid', 'EID-NEW', 'negative');

        $this->process('eid', $tempId);

        $row = LegacyAppHarness::db()->rawQueryOne(
            "SELECT result, request_created_datetime FROM form_eid WHERE sample_code = 'EID-NEW'"
        );
        self::assertNotNull($row, 'the sample was created');
        self::assertSame('negative', $row['result']);
        self::assertNotNull($row['request_created_datetime']);
        self::assertEqualsWithDelta(time(), strtotime((string) $row['request_created_datetime']), 300);
    }

    #[RunInSeparateProcess]
    public function testACovid19SampleTheImportCreatesHasARequestDate(): void
    {
        $this->withoutRequestDateDefault('form_covid19');
        $tempId = $this->staged('covid19', 'C19-NEW', 'negative', ['lot_number' => 'KIT-1']);

        $this->process('covid19', $tempId);

        $row = LegacyAppHarness::db()->rawQueryOne(
            "SELECT result, request_created_datetime FROM form_covid19 WHERE sample_code = 'C19-NEW'"
        );
        self::assertNotNull($row, 'the sample was created');
        self::assertSame('negative', $row['result']);
        self::assertNotNull($row['request_created_datetime']);
        self::assertEqualsWithDelta(time(), strtotime((string) $row['request_created_datetime']), 300);
    }
}
