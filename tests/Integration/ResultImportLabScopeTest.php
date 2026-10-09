<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Registries\ContainerRegistry;
use App\HttpHandlers\LegacyRequestHandler;
use App\Services\CommonService;
use App\Services\FacilitiesService;
use App\Services\ImportedSampleMatcher;
use App\Services\UsersService;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyAppHarness;

/**
 * Results imported from an instrument file reach only the samples of the lab the file
 * was imported for.
 *
 * A sample code is not unique: on a server shared by many labs one code is used by
 * several of them, and a few codes repeat within one lab. The import used to update
 * every sample with the code, in every lab.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class ResultImportLabScopeTest extends TestCase
{
    private const DATABASE = 'intelis_result_import_lab_scope_test';

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
        $db = LegacyAppHarness::boot(self::DATABASE . '_' . getmypid(), [
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
             VALUES (1, 'Lab One', 2), (2, 'Lab Two', 2), (10, 'Clinic', 1)"
        );
        $db->insert('global_config', ['display_name' => 'Form', 'name' => 'vl_form', 'value' => '1']);
        $db->rawQuery("INSERT INTO s_vlsm_instance (vlsm_instance_id) VALUES ('test')");
        $db->rawQuery("INSERT INTO roles (role_id, role_name, status) VALUES (4, 'Lab Technician', 'active')");
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

    /** @param array<string, mixed> $columns */
    private function sample(string $code, ?int $labId, array $columns = []): int
    {
        LegacyAppHarness::db()->insert('form_vl', $columns + [
            'vlsm_instance_id' => 'test',
            'unique_id' => uniqid('u', true),
            'sample_code' => $code,
            'facility_id' => 10,
            'lab_id' => $labId,
            'result_status' => 6,
        ]);
        return (int) LegacyAppHarness::db()->getInsertId();
    }

    /** A row of an imported file, staged as the parsers stage it, for lab 1. */
    private function staged(string $code, string $result = '500', int $status = 7): int
    {
        $data = ContainerRegistry::get(ImportedSampleMatcher::class)->stage('vl', [
            'module' => 'vl',
            'lab_id' => 1,
            'sample_code' => $code,
            'sample_type' => 'S',
            'sample_tested_datetime' => '2026-10-01 10:00:00',
            'result' => $result,
            'result_value_absolute' => $result,
            'result_value_absolute_decimal' => $result,
            'result_status' => $status,
            'import_machine_file_name' => 'run.csv',
            'imported_by' => 'importer',
        ]);
        LegacyAppHarness::db()->insert('temp_sample_import', $data);
        return (int) LegacyAppHarness::db()->getInsertId();
    }

    /**
     * One request per test: the handler loads the page with require_once.
     *
     * @param array<string, mixed> $post
     */
    private function process(array $post = []): void
    {
        LegacyAppHarness::withSession([
            'userId' => 'importer', 'userName' => 'Importer', 'instance' => ['type' => 'vluser'],
        ]);
        $request = LegacyAppHarness::withPost($post + [
            'module' => 'vl', 'value' => '', 'status' => '', 'rejectReasonId' => '', 'comments' => '',
            'testBy' => 'tester', 'reviewedBy' => 'reviewer', 'appBy' => 'approver',
        ], '/import-result/processImportedResults.php');
        $handler = new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));
        $reply = trim((string) $handler->handle($request)->getBody());
        self::assertSame('importedStatistics.php', $reply, 'the page answered');
    }

    /** @return array<string, mixed> */
    private function row(int $id): array
    {
        return LegacyAppHarness::db()->rawQueryOne('SELECT * FROM form_vl WHERE vl_sample_id = ?', [$id]);
    }

    #[RunInSeparateProcess]
    public function testAcceptedResultsReachOnlyTheImportingLabsSample(): void
    {
        $own = $this->sample('VL-1', 1);
        $other = $this->sample('VL-1', 2);
        $this->staged('VL-1');

        $this->process();

        self::assertSame('500', $this->row($own)['result'], "the importing lab's sample got the result");
        self::assertSame('1', (string) $this->row($own)['lab_id']);
        self::assertNull($this->row($other)['result'], "another lab's sample with the same code is untouched");
        self::assertSame('2', (string) $this->row($other)['lab_id']);
    }

    #[RunInSeparateProcess]
    public function testCheckedResultsReachOnlyTheImportingLabsSample(): void
    {
        $own = $this->sample('VL-1', 1);
        $other = $this->sample('VL-1', 2);
        // Accepted with Accept All, then submitted from the screen like every row shown.
        $tempId = $this->staged('VL-1', '500', 7);

        $this->process(['value' => (string) $tempId, 'status' => '7']);

        self::assertSame('500', $this->row($own)['result']);
        self::assertNull($this->row($other)['result'], "another lab's sample with the same code is untouched");
        self::assertSame(
            0,
            (int) LegacyAppHarness::db()->rawQueryValue('SELECT COUNT(*) FROM test_result_attempts LIMIT 1'),
            'the result just imported is not archived as a replaced attempt'
        );
        self::assertSame('reviewer', $this->row($own)['result_reviewed_by'], 'the reviewer chosen on the screen');
        self::assertSame(
            1,
            (int) LegacyAppHarness::db()->rawQueryOne(
                'SELECT COALESCE(SUM(no_of_results_imported), 0) AS n FROM result_import_stats'
            )['n'],
            'the result is counted once'
        );
    }

    #[RunInSeparateProcess]
    public function testAReplacedResultIsPrintedAgain(): void
    {
        $own = $this->sample('VL-6', 1, [
            'result' => '40', 'result_status' => 7,
            'result_printed_datetime' => '2026-09-01 10:00:00', 'result_dispatched_datetime' => '2026-09-01 11:00:00',
        ]);
        $tempId = $this->staged('VL-6', '500', 7);

        $this->process(['value' => (string) $tempId, 'status' => '7']);

        $row = $this->row($own);
        self::assertSame('500', $row['result']);
        self::assertNull($row['result_printed_datetime'], 'back on the list of results to print');
        self::assertNull($row['result_dispatched_datetime']);
    }

    #[RunInSeparateProcess]
    public function testRowsStagedBeforeTheUpgradeAreMatchedNotDropped(): void
    {
        $db = LegacyAppHarness::db();
        $own = $this->sample('VL-7', 1);
        $this->sample('VL-7', 2);
        $db->insert('global_config', [
            'display_name' => 'Import', 'name' => 'import_non_matching_sample', 'value' => 'no',
        ]);
        // As staged by the version before: no match recorded.
        $db->insert('temp_sample_import', [
            'module' => 'vl', 'lab_id' => 1, 'sample_code' => 'VL-7', 'sample_type' => 'S', 'result' => '500',
            'result_status' => 6, 'sample_details' => 'Result already exists', 'imported_by' => 'importer',
        ]);

        // Another lab's sample: kept for the importer to correct, never written.
        $this->sample('VL-9', 2);
        $db->insert('temp_sample_import', [
            'module' => 'vl', 'lab_id' => 1, 'sample_code' => 'VL-9', 'sample_type' => 'S', 'result' => '700',
            'result_status' => 6, 'imported_by' => 'importer',
        ]);
        // Staged for EID by the same importer: not a row of the viral load list.
        $db->insert('temp_sample_import', [
            'module' => 'eid', 'lab_id' => 1, 'sample_code' => 'EID-9', 'sample_type' => 'S', 'result' => 'positive',
            'result_status' => 6, 'matched_sample_id' => $own, 'imported_by' => 'importer',
        ]);

        LegacyAppHarness::withSession([
            'userId' => 'importer', 'userName' => 'Importer', 'instance' => ['type' => 'vluser'],
        ]);
        $request = LegacyAppHarness::withPost(
            ['module' => 'vl', 'sEcho' => '1', 'iDisplayStart' => '0', 'iDisplayLength' => '-1', 'sSearch' => ''],
            '/import-result/getImportedResults.php'
        );
        $handler = new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));
        $reply = json_decode((string) $handler->handle($request)->getBody(), true);

        self::assertIsArray($reply, 'the page answered');
        $staged = $db->rawQueryOne("SELECT * FROM temp_sample_import WHERE sample_code = 'VL-7'");
        self::assertNotNull($staged, 'not deleted as non-matching');
        self::assertSame($own, (int) $staged['matched_sample_id']);
        $otherLab = $db->rawQueryOne("SELECT * FROM temp_sample_import WHERE sample_code = 'VL-9'");
        self::assertNotNull($otherLab, "another lab's code is kept to be corrected");
        self::assertSame(ImportedSampleMatcher::DETAILS_OTHER_LAB, $otherLab['sample_details']);
        self::assertCount(2, $reply['aaData'], 'the viral load rows only');
    }

    #[RunInSeparateProcess]
    public function testAnotherLabsSampleIsNeitherUpdatedNorCopied(): void
    {
        $other = $this->sample('VL-2', 2);
        $tempId = $this->staged('VL-2', '500', 8);

        $staged = LegacyAppHarness::db()->rawQueryOne('SELECT * FROM temp_sample_import');
        self::assertSame(ImportedSampleMatcher::DETAILS_OTHER_LAB, $staged['sample_details']);
        self::assertNull($staged['matched_sample_id']);
        self::assertNull($staged['facility_id'], "the other lab's clinic is not shown as the sample's");

        // Importing results for unknown samples is allowed, and still no copy is made.
        $this->process(['value' => (string) $tempId, 'status' => '7']);

        self::assertNull($this->row($other)['result']);
        self::assertSame(1, (int) LegacyAppHarness::db()->rawQueryValue(
            "SELECT COUNT(*) FROM form_vl WHERE sample_code = 'VL-2' LIMIT 1"
        ));
    }

    #[RunInSeparateProcess]
    public function testACodeOnSeveralOfTheLabsSamplesUpdatesNone(): void
    {
        $first = $this->sample('VL-3', 1);
        $second = $this->sample('VL-3', 1);
        $this->staged('VL-3');

        $staged = LegacyAppHarness::db()->rawQueryOne('SELECT * FROM temp_sample_import');
        self::assertSame(ImportedSampleMatcher::DETAILS_AMBIGUOUS, $staged['sample_details']);

        $this->process();

        self::assertNull($this->row($first)['result']);
        self::assertNull($this->row($second)['result']);
    }

    #[RunInSeparateProcess]
    public function testTheLabsOwnSampleWinsOverAnUnassignedOne(): void
    {
        $own = $this->sample('VL-4', 1);
        $unassigned = $this->sample('VL-4', null);
        $this->staged('VL-4');

        $this->process();

        self::assertSame('500', $this->row($own)['result']);
        self::assertNull($this->row($unassigned)['result']);
    }

    #[RunInSeparateProcess]
    public function testASampleNoLabHasTakenYetIsMatched(): void
    {
        $unassigned = $this->sample('VL-5', null);
        $this->sample('VL-5', 2);
        $this->staged('VL-5');

        $staged = LegacyAppHarness::db()->rawQueryOne('SELECT * FROM temp_sample_import');
        self::assertSame($unassigned, (int) $staged['matched_sample_id']);
        self::assertSame(10, (int) $staged['facility_id']);

        $this->process();

        self::assertSame('500', $this->row($unassigned)['result']);
        self::assertSame('1', (string) $this->row($unassigned)['lab_id'], 'the importing lab takes the sample on');
    }

    #[RunInSeparateProcess]
    public function testTheOperatorIsFoundAmongTheLabsUsers(): void
    {
        $db = LegacyAppHarness::db();
        $db->insert('user_details', [
            'user_id' => 'john-2', 'user_name' => 'john', 'status' => 'active', 'testing_lab_id' => 2,
        ]);
        $db->insert('user_details', ['user_id' => 'mary', 'user_name' => 'mary', 'status' => 'active']);
        $users = ContainerRegistry::get(UsersService::class);

        $created = $users->getOrCreateUser('john', labId: 1);
        self::assertNotSame('john-2', $created, "another lab's john is not this lab's operator");
        self::assertSame('1', (string) $db->rawQueryValue(
            'SELECT testing_lab_id FROM user_details WHERE user_id = ? LIMIT 1',
            [$created]
        ));
        self::assertSame($created, $users->getOrCreateUser('john', labId: 1), 'found again, not created twice');
        self::assertSame('john-2', $users->getOrCreateUser('john', labId: 2));
        self::assertSame('mary', $users->getOrCreateUser('mary', labId: 1), 'a user of no lab is still found');
    }

    #[RunInSeparateProcess]
    public function testEidResultsReachOnlyTheImportingLabsSample(): void
    {
        $db = LegacyAppHarness::db();
        $sample = static function (int $labId) use ($db): int {
            $db->insert('form_eid', [
                'vlsm_instance_id' => 'test', 'unique_id' => uniqid('u', true), 'sample_code' => 'EID-1',
                'facility_id' => 10, 'lab_id' => $labId, 'result_status' => 6,
            ]);
            return (int) $db->getInsertId();
        };
        $own = $sample(1);
        $other = $sample(2);
        $db->insert('temp_sample_import', ContainerRegistry::get(ImportedSampleMatcher::class)->stage('eid', [
            'module' => 'eid', 'lab_id' => 1, 'sample_code' => 'EID-1', 'sample_type' => 'S',
            'sample_tested_datetime' => '2026-10-01 10:00:00', 'result' => 'positive', 'result_status' => 7,
            'import_machine_file_name' => 'run.csv', 'imported_by' => 'importer',
        ]));
        $tempId = (int) $db->getInsertId();

        $this->process(['module' => 'eid', 'value' => (string) $tempId, 'status' => '7']);

        $result = static fn(int $id): ?string
            => $db->rawQueryOne('SELECT result FROM form_eid WHERE eid_id = ?', [$id])['result'];
        self::assertSame('positive', $result($own));
        self::assertNull($result($other), "another lab's sample with the same code is untouched");
        self::assertSame(
            0,
            (int) $db->rawQueryValue('SELECT COUNT(*) FROM test_result_attempts LIMIT 1'),
            'the result just imported is not archived as a replaced attempt'
        );
    }

    #[RunInSeparateProcess]
    public function testAnOperatorImportsOnlyForTheirOwnLab(): void
    {
        LegacyAppHarness::db()->rawQuery(
            "INSERT INTO testing_labs (test_type, facility_id) VALUES ('vl', 1), ('vl', 2)"
        );
        $facilities = ContainerRegistry::get(FacilitiesService::class);

        // One lab's operator on a server shared by many labs.
        $_SESSION = [
            'instance' => ['type' => 'remoteuser'], 'accessType' => 'testing-lab', 'roleId' => 4, 'labId' => 1,
        ];
        self::assertTrue($facilities->canUploadResultsFor('vl', '1'));
        self::assertFalse(
            $facilities->canUploadResultsFor('vl', '2'),
            "another lab's samples are out of reach"
        );
        self::assertSame(
            [1],
            array_map('intval', array_column($facilities->getFacilitiesForResultUpload('vl'), 'facility_id'))
        );

        // The administrator of the shared server, and a lab's own installation, keep every lab.
        $_SESSION['roleId'] = 1;
        self::assertTrue($facilities->canUploadResultsFor('vl', '2'));
        $_SESSION = ['instance' => ['type' => 'vluser'], 'roleId' => 4, 'labId' => 1];
        self::assertTrue($facilities->canUploadResultsFor('vl', '2'));
        self::assertFalse($facilities->canUploadResultsFor('vl', '10'), 'a clinic is no testing lab');
    }

    #[RunInSeparateProcess]
    public function testCovid19SavesTheStatusChosenOnTheScreen(): void
    {
        $db = LegacyAppHarness::db();
        $db->insert('form_covid19', [
            'vlsm_instance_id' => 'test', 'unique_id' => uniqid('u', true), 'sample_code' => 'C19-1',
            'facility_id' => 10, 'lab_id' => 1, 'result_status' => 6,
        ]);
        $covidId = (int) $db->getInsertId();
        $db->insert('temp_sample_import', ContainerRegistry::get(ImportedSampleMatcher::class)->stage('covid19', [
            'module' => 'covid19', 'lab_id' => 1, 'sample_code' => 'C19-1', 'sample_type' => 'S',
            'sample_tested_datetime' => '2026-10-01 10:00:00', 'result' => 'negative', 'result_status' => 7,
            'lot_number' => 'KIT-1', 'import_machine_file_name' => 'run.csv', 'imported_by' => 'importer',
        ]));

        $this->process(['module' => 'covid19', 'value' => (string) $db->getInsertId(), 'status' => '7']);

        $row = $db->rawQueryOne('SELECT result, result_status FROM form_covid19 WHERE covid19_id = ?', [$covidId]);
        self::assertSame('negative', $row['result']);
        self::assertSame(7, (int) $row['result_status'], 'accepted, as chosen');
    }

    #[RunInSeparateProcess]
    public function testAnInvalidRunAcceptedOnTheScreenIsAFailedTest(): void
    {
        $own = $this->sample('VL-8', 1);
        $tempId = $this->staged('VL-8', 'Invalid', 7);

        $this->process(['value' => (string) $tempId, 'status' => '7']);

        self::assertSame(5, (int) $this->row($own)['result_status']);
    }

    #[RunInSeparateProcess]
    public function testAHeldResultKeepsItsSample(): void
    {
        $own = $this->sample('VL-10', 1);
        $tempId = $this->staged('VL-10', '500', 8);

        $this->process(['value' => (string) $tempId, 'status' => '1']);

        self::assertNull($this->row($own)['result'], 'held, not written');
        $staged = LegacyAppHarness::db()->rawQueryOne(
            'SELECT temp_sample_status, matched_sample_id FROM temp_sample_import WHERE temp_sample_id = ?',
            [$tempId]
        );
        self::assertSame(1, (int) $staged['temp_sample_status'], 'processed');
        self::assertSame($own, (int) $staged['matched_sample_id']);
    }
}
