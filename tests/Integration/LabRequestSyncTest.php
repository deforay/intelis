<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Registries\ContainerRegistry;
use App\Services\LabRequestSyncService;
use mysqli;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyAppHarness;

/**
 * A lab saving the requests it pulled from the STS (requests-receiver.php).
 *
 * What is reported saved goes back to the STS in the lab's receipt and is not
 * sent again, and what the lab has entered itself (results, its own codes) must
 * survive every later pull. Rows are read back through a second connection, so
 * a transaction left open shows up as missing data.
 *
 * The STS side is a copy of each table (CREATE TABLE ... LIKE), and a payload is
 * SELECT * from it, which is what requests.php sends: every column the STS has,
 * with its defaults.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class LabRequestSyncTest extends TestCase
{
    private const LAB = 7;

    private bool $booted = false;

    private string $database = '';

    protected function tearDown(): void
    {
        if ($this->booted) {
            LegacyAppHarness::shutdown();
        }
    }

    private function boot(): mixed
    {
        if (!getenv('INTELIS_TEST_DB_HOST') || !getenv('INTELIS_TEST_DB_USER')) {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }
        $this->database = 'intelis_lab_request_sync_test_' . getmypid();
        // getTableFieldsAsArray() reads columns from SYSTEM_CONFIG's database.
        if (!defined('SYSTEM_CONFIG')) {
            define('SYSTEM_CONFIG', [
                'database' => ['db' => $this->database],
                'modules' => ['vl' => true, 'tb' => true, 'generic-tests' => true],
            ]);
        }
        $db = LegacyAppHarness::boot($this->database, [
            'system_config', 'global_config', 's_vlsm_instance', 'r_sample_status', 'form_vl', 'form_tb',
            'tb_tests', 'form_generic', 'generic_test_results',
        ]);
        $this->booted = true;
        $db->rawQuery("INSERT INTO r_sample_status (status_id, status_name) VALUES (6, 'Registered'), (7, 'Accepted')");
        foreach (['form_vl', 'form_tb', 'form_generic'] as $table) {
            $db->rawQuery("CREATE TABLE `sts_$table` LIKE `$table`");
        }
        return $db;
    }

    private static function sync(): LabRequestSyncService
    {
        return ContainerRegistry::get(LabRequestSyncService::class);
    }

    /** The STS's copy of a request, as requests.php sends it. */
    private static function stsRow($db, string $table, array $values): array
    {
        $db->insert("sts_$table", $values + [
            'lab_id' => self::LAB, 'facility_id' => 1, 'vlsm_instance_id' => 'sts-instance',
            'sample_collection_date' => '2026-09-01 09:00:00', 'result_status' => 6,
            'last_modified_datetime' => '2026-09-01 10:00:00', 'data_sync' => 0,
        ]);
        return $db->rawQueryOne("SELECT * FROM `sts_$table` WHERE unique_id = ?", [$values['unique_id']]);
    }

    /** A VL request as the STS holds it, before the lab has done anything. */
    private static function vlRequest($db, string $uniqueId, array $values = []): array
    {
        return self::stsRow($db, 'form_vl', $values + [
            'unique_id' => $uniqueId, 'remote_sample_code' => "R-$uniqueId", 'remote_sample' => 'yes',
            'patient_art_no' => "ART-$uniqueId", 'patient_first_name' => 'Ada',
        ]);
    }

    /**
     * What another connection sees: only committed rows.
     *
     * @return array<string, array<string, mixed>> keyed by unique_id
     */
    private function committed(string $table, string $key = 'unique_id'): array
    {
        $mysqli = new mysqli(
            (string) getenv('INTELIS_TEST_DB_HOST'),
            (string) getenv('INTELIS_TEST_DB_USER'),
            (string) (getenv('INTELIS_TEST_DB_PASS') ?: ''),
            $this->database,
            (int) (getenv('INTELIS_TEST_DB_PORT') ?: 3306)
        );
        $rows = $mysqli->query("SELECT * FROM `$table` ORDER BY `$key`")->fetch_all(MYSQLI_ASSOC);
        $mysqli->close();
        return array_column($rows, null, $key);
    }

    #[RunInSeparateProcess]
    public function testANewRequestIsAddedToTheLab(): void
    {
        $db = $this->boot();
        $request = self::vlRequest($db, 'u-1', ['vl_sample_id' => 555]);

        $result = self::sync()->saveModule('vl', [$request], 'tx-1');

        self::assertSame(['u-1'], $result['saved']);
        self::assertSame([], $result['failed']);
        self::assertSame(1, $result['inserts']);
        $row = $this->committed('form_vl')['u-1'] ?? null;
        self::assertNotNull($row, 'committed');
        self::assertNotSame('555', (string) $row['vl_sample_id'], 'the STS row id is not the lab one');
        self::assertSame('0', (string) $row['data_sync'], 'the lab still has to send its result');
        self::assertSame('vlsts', $row['source_of_request']);
        self::assertSame('R-u-1', $row['remote_sample_code']);
        self::assertFalse($db->isTransactionActive());
    }

    #[RunInSeparateProcess]
    public function testPullingAnUnchangedRequestAgainChangesNothingAndReportsItSaved(): void
    {
        $db = $this->boot();
        $request = self::vlRequest($db, 'u-1');
        self::sync()->saveModule('vl', [$request], 'tx-1');
        // Grids sort by this, so a re-pull must not bring the row back to the top.
        $db->rawQuery("UPDATE form_vl SET last_modified_datetime = '2026-09-02 08:00:00'");

        // The STS sends it again: its answer to the receipt was lost.
        $result = self::sync()->saveModule('vl', [$request], 'tx-2');

        self::assertSame(['u-1'], $result['saved'], 'else the STS keeps sending it');
        self::assertSame(0, $result['updates']);
        self::assertCount(1, $this->committed('form_vl'), 'not added twice');
        self::assertSame('2026-09-02 08:00:00', $this->committed('form_vl')['u-1']['last_modified_datetime']);
    }

    #[RunInSeparateProcess]
    public function testTheStsCannotOverwriteWhatTheLabHasEntered(): void
    {
        $db = $this->boot();
        self::sync()->saveModule('vl', [self::vlRequest($db, 'u-1')], 'tx-1');
        // The lab receives, codes and tests the sample.
        $db->rawQuery(
            "UPDATE form_vl SET sample_code = 'LAB-1', lab_assigned_code = 'LAC-9', result = '40',
                result_status = 7, sample_tested_datetime = '2026-09-03 10:00:00', is_sample_rejected = 'no'
                WHERE unique_id = 'u-1'"
        );

        // Then the STS corrects the patient's name and sends its copy, which has
        // none of the lab's work in it.
        $db->rawQuery("UPDATE sts_form_vl SET patient_first_name = 'Adah' WHERE unique_id = 'u-1'");
        $stsCopy = $db->rawQueryOne("SELECT * FROM sts_form_vl WHERE unique_id = 'u-1'");
        $result = self::sync()->saveModule('vl', [$stsCopy], 'tx-2');

        self::assertSame(['u-1'], $result['saved']);
        $row = $this->committed('form_vl')['u-1'];
        self::assertSame('Adah', $row['patient_first_name'], 'the STS owns the request details');
        self::assertSame('LAB-1', $row['sample_code']);
        self::assertSame('LAC-9', $row['lab_assigned_code']);
        self::assertSame('40', $row['result']);
        self::assertSame('7', (string) $row['result_status']);
        self::assertSame('2026-09-03 10:00:00', $row['sample_tested_datetime']);
    }

    #[RunInSeparateProcess]
    public function testCodesTheLabHasNotSetYetAreFilledFromTheSts(): void
    {
        $db = $this->boot();
        // Registered at the lab from a manifest, before the STS had given it a code.
        $db->insert('form_vl', [
            'unique_id' => 'u-1', 'lab_id' => self::LAB, 'facility_id' => 1, 'vlsm_instance_id' => 'lab',
            'sample_collection_date' => '2026-09-01 09:00:00', 'result_status' => 6,
        ]);

        self::sync()->saveModule('vl', [self::vlRequest($db, 'u-1', ['lab_assigned_code' => 'LAC-1'])], 'tx-1');

        $row = $this->committed('form_vl')['u-1'];
        self::assertSame('R-u-1', $row['remote_sample_code']);
        self::assertSame('LAC-1', $row['lab_assigned_code']);
    }

    #[RunInSeparateProcess]
    public function testARequestTheLabCannotSaveIsReportedFailedAndCostsOnlyItself(): void
    {
        $db = $this->boot();
        $requests = [self::vlRequest($db, 'u-1'), self::vlRequest($db, 'u-2'), self::vlRequest($db, 'u-3')];
        // MySQL refuses it: no such status (a foreign key, so strict mode or not).
        $requests[1]['result_status'] = 99;

        $result = self::sync()->saveModule('vl', $requests, 'tx-1');

        self::assertSame(['u-1', 'u-3'], $result['saved']);
        self::assertSame(['u-2'], array_column($result['failed'], 'unique_id'));
        self::assertNotEmpty($result['failed'][0]['reason'], 'the STS shows it to whoever has to fix it');
        self::assertSame(['u-1', 'u-3'], array_keys($this->committed('form_vl')));
        self::assertFalse($db->isTransactionActive());
    }

    #[RunInSeparateProcess]
    public function testARequestWithNothingToMatchOnIsNotAdded(): void
    {
        $db = $this->boot();
        $request = self::vlRequest($db, 'u-x');
        // Nothing left to find it by next time, so adding it would add it again on every pull.
        $request['unique_id'] = $request['remote_sample_code'] = $request['lab_id'] = $request['facility_id'] = null;
        $request['sample_code'] = 'S-1';

        $result = self::sync()->saveModule('vl', [$request], 'tx-1');

        self::assertSame(1, $result['failures']);
        self::assertSame([], $result['saved']);
        self::assertSame([], $this->committed('form_vl'));
    }

    #[RunInSeparateProcess]
    public function testJunkInThePullDoesNotStopTheRest(): void
    {
        $db = $this->boot();

        $result = self::sync()->saveModule('vl', [null, 'junk', [], 42, self::vlRequest($db, 'u-ok')], 'tx-1');

        self::assertSame(['u-ok'], $result['saved']);
        self::assertSame(['u-ok'], array_keys($this->committed('form_vl')));
        self::assertFalse($db->isTransactionActive());
    }

    #[RunInSeparateProcess]
    public function testADryRunSavesNothingAndConfirmsNothing(): void
    {
        $db = $this->boot();

        $result = self::sync()->saveModule('vl', [self::vlRequest($db, 'u-1')], 'tx-1', false, true);

        self::assertSame(1, $result['inserts'], 'it reports what it would do');
        self::assertSame([], $result['saved'], 'and tells the STS nothing');
        self::assertSame([], $this->committed('form_vl'));
        self::assertFalse($db->isTransactionActive());
    }

    /** The lab is ahead of the STS: it has a column the STS does not send. */
    #[RunInSeparateProcess]
    public function testColumnsTheStsDoesNotSendAreLeftAlone(): void
    {
        $db = $this->boot();
        self::sync()->saveModule('vl', [self::vlRequest($db, 'u-1')], 'tx-1');
        $db->rawQuery("UPDATE form_vl SET patient_mobile_number = '0999' WHERE unique_id = 'u-1'");

        $db->rawQuery("UPDATE sts_form_vl SET patient_first_name = 'Adah' WHERE unique_id = 'u-1'");
        $stsCopy = $db->rawQueryOne("SELECT * FROM sts_form_vl WHERE unique_id = 'u-1'");
        unset($stsCopy['patient_mobile_number']);
        self::sync()->saveModule('vl', [$stsCopy], 'tx-2');

        $row = $this->committed('form_vl')['u-1'];
        self::assertSame('Adah', $row['patient_first_name']);
        self::assertSame('0999', $row['patient_mobile_number']);
    }

    #[RunInSeparateProcess]
    public function testATbRequestArrivesWithItsTestsUnderTheLabsOwnRow(): void
    {
        $db = $this->boot();
        $request = self::stsRow($db, 'form_tb', [
            'tb_id' => 900, 'unique_id' => 't-1', 'remote_sample_code' => 'R-t-1',
        ]);
        $request['data_from_tests'] = [[
            'tb_test_id' => 31, 'tb_id' => 900, 'test_result' => 'MTB detected',
            'updated_datetime' => '2026-09-01 11:00:00',
        ]];

        $result = self::sync()->saveModule('tb', [$request], 'tx-1');

        self::assertSame(['t-1'], $result['saved']);
        $tbId = (int) $this->committed('form_tb')['t-1']['tb_id'];
        self::assertNotSame(900, $tbId);
        $tests = $db->rawQuery('SELECT tb_id, test_result FROM tb_tests');
        self::assertSame([['tb_id' => $tbId, 'test_result' => 'MTB detected']], array_map(
            static fn(array $row): array => ['tb_id' => (int) $row['tb_id'], 'test_result' => $row['test_result']],
            $tests
        ));
    }

    #[RunInSeparateProcess]
    public function testATbRequestWhoseTestsCannotBeSavedIsNotSavedHalfway(): void
    {
        $db = $this->boot();
        $request = self::stsRow($db, 'form_tb', ['unique_id' => 't-1', 'remote_sample_code' => 'R-t-1']);
        $request['data_from_tests'] = [['tb_test_id' => 1, 'test_result' => 'refused']];
        // Stands in for any failure saving a test row.
        $db->rawQuery("ALTER TABLE tb_tests ADD CONSTRAINT refuse_test CHECK (test_result <> 'refused')");

        $result = self::sync()->saveModule('tb', [$request], 'tx-1');

        self::assertSame(['t-1'], array_column($result['failed'], 'unique_id'));
        self::assertSame([], $this->committed('form_tb'), 'the request goes with its tests or not at all');
        self::assertSame([], $this->committed('tb_tests', 'tb_test_id'));
    }

    #[RunInSeparateProcess]
    public function testCustomTestsRequestsAreAddedAndOnesWithoutACollectionDateReportedFailed(): void
    {
        $db = $this->boot();
        $good = self::stsRow($db, 'form_generic', ['unique_id' => 'g-1', 'remote_sample_code' => 'R-g-1']);
        $undated = self::stsRow($db, 'form_generic', ['unique_id' => 'g-2', 'remote_sample_code' => 'R-g-2']);
        $undated['sample_collection_date'] = null;

        $result = self::sync()->saveCustomTests([$good, $undated], 'tx-1');

        self::assertSame(['g-1'], $result['saved']);
        self::assertSame(['g-2'], array_column($result['failed'], 'unique_id'));
        self::assertSame(['g-1'], array_keys($this->committed('form_generic')));
        self::assertFalse($db->isTransactionActive());
    }

    #[RunInSeparateProcess]
    public function testTheStsCannotOverwriteACustomTestsResultTheLabHasEntered(): void
    {
        $db = $this->boot();
        $request = self::stsRow($db, 'form_generic', ['unique_id' => 'g-1', 'remote_sample_code' => 'R-g-1']);
        self::sync()->saveCustomTests([$request], 'tx-1');
        $db->rawQuery(
            "UPDATE form_generic SET result = 'Positive', result_status = 7, lab_assigned_code = 'LAC-2'
                WHERE unique_id = 'g-1'"
        );

        $db->rawQuery("UPDATE sts_form_generic SET patient_first_name = 'Adah' WHERE unique_id = 'g-1'");
        $result = self::sync()->saveCustomTests(
            [$db->rawQueryOne("SELECT * FROM sts_form_generic WHERE unique_id = 'g-1'")],
            'tx-2'
        );

        self::assertSame(['g-1'], $result['saved']);
        $row = $this->committed('form_generic')['g-1'];
        self::assertSame('Adah', $row['patient_first_name']);
        self::assertSame('Positive', $row['result']);
        self::assertSame('7', (string) $row['result_status']);
        self::assertSame('LAC-2', $row['lab_assigned_code']);
    }
}
