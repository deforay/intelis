<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Registries\ContainerRegistry;
use App\Services\STS\ResultsService;
use mysqli;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyAppHarness;

/**
 * The STS saving the results a lab sends (remote/v2/results.php).
 *
 * What the STS returns is what the lab marks synced and never sends again, so
 * the rule every test here checks is: a result is acknowledged only if it is
 * committed, and a bad record costs only itself. Rows are read back through a
 * second connection, because the saving connection sees its own uncommitted
 * work and would hide a transaction left open.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class StsResultsReceiveTest extends TestCase
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
        $this->database = 'intelis_sts_results_receive_test_' . getmypid();
        // getTableFieldsAsArray() reads columns from SYSTEM_CONFIG's database.
        if (!defined('SYSTEM_CONFIG')) {
            define('SYSTEM_CONFIG', [
                'database' => ['db' => $this->database],
                'modules' => ['vl' => true, 'tb' => true, 'covid19' => true],
            ]);
        }
        $db = LegacyAppHarness::boot($this->database, [
            'system_config', 'global_config', 's_vlsm_instance', 'r_sample_status', 'form_vl', 'form_tb',
            'tb_tests', 'roles', 'user_details', 'r_vl_sample_rejection_reasons', 'r_tb_sample_rejection_reasons',
            'facility_details', 'specimen_manifests', 'form_covid19', 'covid19_tests', 'audit_log',
        ]);
        $this->booted = true;
        $db->rawQuery("INSERT INTO r_sample_status (status_id, status_name) VALUES (6, 'Registered'), (7, 'Accepted')");
        return $db;
    }

    private static function sts(): ResultsService
    {
        return ContainerRegistry::get(ResultsService::class);
    }

    /** A request the STS sent to the lab, waiting for its result. */
    private static function stsRequest($db, string $table, string $uniqueId, array $extra = []): void
    {
        $db->insert($table, $extra + [
            'unique_id' => $uniqueId, 'remote_sample_code' => "R-$uniqueId", 'lab_id' => self::LAB,
            'facility_id' => 1, 'vlsm_instance_id' => 'sts-instance', 'result_status' => 6,
            'last_modified_datetime' => '2026-09-01 10:00:00', 'data_sync' => 1,
        ]);
    }

    /** The lab's copy of that request, now with a result. */
    private static function vlResult(string $uniqueId, array $extra = []): array
    {
        return $extra + [
            'unique_id' => $uniqueId, 'remote_sample_code' => "R-$uniqueId", 'sample_code' => "L-$uniqueId",
            'lab_id' => self::LAB, 'facility_id' => 1, 'vlsm_instance_id' => 'lab-instance',
            'result' => '40', 'result_status' => 7,
        ];
    }

    private static function payload(array $results): array
    {
        return ['labId' => self::LAB, 'results' => $results];
    }

    /**
     * What another connection sees: only committed rows.
     *
     * @return array<string, array<string, mixed>> keyed by unique_id
     */
    private function committed(string $table, string $columns = '*'): array
    {
        $mysqli = new mysqli(
            (string) getenv('INTELIS_TEST_DB_HOST'),
            (string) getenv('INTELIS_TEST_DB_USER'),
            (string) (getenv('INTELIS_TEST_DB_PASS') ?: ''),
            $this->database,
            (int) (getenv('INTELIS_TEST_DB_PORT') ?: 3306)
        );
        $rows = $mysqli->query("SELECT $columns FROM `$table` ORDER BY unique_id")->fetch_all(MYSQLI_ASSOC);
        $mysqli->close();
        return array_column($rows, null, 'unique_id');
    }

    #[RunInSeparateProcess]
    public function testAResultForARequestTheStsSentIsSavedOnThatRequest(): void
    {
        $db = $this->boot();
        self::stsRequest($db, 'form_vl', 'u-1', ['vl_sample_id' => 1]);

        // The lab's own row id means nothing on the STS and must not move the row.
        $ack = self::sts()->receiveResults('vl', self::payload([self::vlResult('u-1', ['vl_sample_id' => 999])]));

        self::assertSame(['L-u-1'], $ack);
        $rows = $this->committed('form_vl');
        self::assertCount(1, $rows, 'updated in place, not inserted again');
        self::assertSame('1', (string) $rows['u-1']['vl_sample_id']);
        self::assertSame('40', $rows['u-1']['result']);
        self::assertSame('L-u-1', $rows['u-1']['sample_code']);
        self::assertSame('1', (string) $rows['u-1']['data_sync']);
        self::assertFalse($db->isTransactionActive());
    }

    #[RunInSeparateProcess]
    public function testAResultTheStsHasNeverSeenIsAdded(): void
    {
        $this->boot();

        // Registered at the lab itself, so the STS gets it first with its result.
        $result = self::vlResult('u-lab', ['remote_sample_code' => null]);
        $ack = self::sts()->receiveResults('vl', self::payload([$result]));

        self::assertSame(['L-u-lab'], $ack);
        self::assertSame('40', $this->committed('form_vl')['u-lab']['result'] ?? null);
    }

    #[RunInSeparateProcess]
    public function testResendingTheSameResultChangesNothingAndIsStillAcknowledged(): void
    {
        $db = $this->boot();
        self::stsRequest($db, 'form_vl', 'u-1');
        self::sts()->receiveResults('vl', self::payload([self::vlResult('u-1')]));
        // Grids sort by this, so a resend must not bring the row back to the top.
        $db->rawQuery("UPDATE form_vl SET last_modified_datetime = '2026-09-02 08:00:00'");

        // A lab resends after losing the answer to a timeout.
        $ack = self::sts()->receiveResults('vl', self::payload([self::vlResult('u-1')]));

        self::assertSame(['L-u-1'], $ack, 'else the lab keeps sending it');
        self::assertSame('2026-09-02 08:00:00', $this->committed('form_vl')['u-1']['last_modified_datetime']);
    }

    #[RunInSeparateProcess]
    public function testASilentResendKeepsTheStsTimestamp(): void
    {
        $db = $this->boot();
        self::stsRequest($db, 'form_vl', 'u-1');

        self::sts()->receiveResults('vl', self::payload([self::vlResult('u-1', ['result' => '60'])]), true);

        $row = $this->committed('form_vl')['u-1'];
        self::assertSame('60', $row['result']);
        self::assertSame('2026-09-01 10:00:00', $row['last_modified_datetime']);
    }

    #[RunInSeparateProcess]
    public function testColumnsOnlyTheLabHasAreIgnoredAndStsOnlyColumnsAreKept(): void
    {
        $db = $this->boot();
        self::stsRequest($db, 'form_vl', 'u-1', [
            'result_printed_on_sts_datetime' => '2026-09-01 12:00:00', 'sample_package_code' => 'PKG-1',
        ]);

        // A lab on a newer release sends a column this STS does not have yet, and
        // its own print and manifest details, which are not the STS's.
        $ack = self::sts()->receiveResults('vl', self::payload([self::vlResult('u-1', [
            'column_added_in_a_later_release' => 'x',
            'result_printed_on_sts_datetime' => null,
            'sample_package_code' => 'LAB-PKG',
        ])]));

        self::assertSame(['L-u-1'], $ack);
        $row = $this->committed('form_vl')['u-1'];
        self::assertSame('40', $row['result']);
        self::assertSame('2026-09-01 12:00:00', $row['result_printed_on_sts_datetime']);
        self::assertSame('PKG-1', $row['sample_package_code']);
    }

    /**
     * The STS is updated first, so it usually has columns its labs do not. The
     * payloads here are short on purpose: every column they leave out is one the
     * lab's release does not have.
     */
    #[RunInSeparateProcess]
    public function testALabOnAnOlderReleaseDoesNotBlankWhatItDoesNotHave(): void
    {
        $db = $this->boot();
        self::stsRequest($db, 'form_vl', 'u-1', ['remote_sample' => 'yes', 'is_result_mail_sent' => 'yes']);

        self::sts()->receiveResults('vl', self::payload([self::vlResult('u-1')]));

        $row = $this->committed('form_vl')['u-1'];
        self::assertSame('40', $row['result']);
        self::assertSame('yes', $row['remote_sample'], 'what the sync counts count on');
        self::assertSame('yes', $row['is_result_mail_sent'], 'else the result is emailed again');
    }

    #[RunInSeparateProcess]
    public function testANewResultFromALabOnAnOlderReleaseIsStillAdded(): void
    {
        $db = $this->boot();

        // is_result_mail_sent is NOT NULL: sent as NULL, MySQL refused the insert.
        $ack = self::sts()->receiveResults('vl', self::payload([self::vlResult('u-lab')]));

        self::assertSame(['L-u-lab'], $ack);
        $row = $this->committed('form_vl')['u-lab'];
        self::assertSame('40', $row['result']);
        self::assertSame('no', $row['is_result_mail_sent'], 'the column default, not a blank');
    }

    #[RunInSeparateProcess]
    public function testARecordThatCannotBeSavedCostsOnlyItself(): void
    {
        $db = $this->boot();
        foreach (['u-1', 'u-2', 'u-3'] as $id) {
            self::stsRequest($db, 'form_vl', $id);
        }

        // u-2 is refused by MySQL: no such status (a foreign key, so strict mode or not).
        $ack = self::sts()->receiveResults('vl', self::payload([
            self::vlResult('u-1'),
            self::vlResult('u-2', ['result_status' => 99]),
            self::vlResult('u-3'),
        ]), false, true);

        self::assertSame(['u-1', 'u-3'], $ack, 'the failed one is not acknowledged, so the lab sends it again');
        $rows = $this->committed('form_vl');
        self::assertSame('40', $rows['u-1']['result']);
        self::assertNull($rows['u-2']['result'], 'nothing half-saved');
        self::assertSame('40', $rows['u-3']['result']);
        self::assertFalse($db->isTransactionActive());
    }

    #[RunInSeparateProcess]
    public function testARecordWithNothingToMatchOnIsNeitherSavedNorAcknowledged(): void
    {
        $db = $this->boot();

        $ack = self::sts()->receiveResults('vl', self::payload([
            ['sample_code' => 'NO-LAB', 'result' => '40', 'result_status' => 7],
            self::vlResult('u-ok', ['remote_sample_code' => null]),
        ]));

        self::assertSame(['L-u-ok'], $ack);
        self::assertSame(['u-ok'], array_keys($this->committed('form_vl')), 'no orphan row to duplicate next sync');
        self::assertFalse($db->isTransactionActive());
    }

    #[RunInSeparateProcess]
    public function testAPayloadThatIsNotUsableSavesNothing(): void
    {
        $db = $this->boot();

        self::assertSame([], self::sts()->receiveResults('vl', '{"labId": 7, "results": [ {"unique_'));
        self::assertSame([], self::sts()->receiveResults('vl', ['labId' => self::LAB]));
        self::assertSame([], self::sts()->receiveResults('vl', self::payload([null, [], ''])));
        self::assertSame([], $this->committed('form_vl'));
        self::assertFalse($db->isTransactionActive());
    }

    #[RunInSeparateProcess]
    public function testATbRecordWithoutFormDataDoesNotLoseTheRecordsAfterIt(): void
    {
        $db = $this->boot();
        self::stsRequest($db, 'form_tb', 't-2');

        $ack = self::sts()->receiveResults('tb', self::payload([
            ['data_from_tests' => []],
            ['form_data' => self::vlResult('t-2', ['result' => 'Positive']), 'data_from_tests' => []],
        ]));

        self::assertSame(['L-t-2'], $ack);
        self::assertFalse($db->isTransactionActive(), 'a skipped record left its transaction open');
        self::assertSame(
            'Positive',
            $this->committed('form_tb')['t-2']['result'] ?? null,
            'acknowledged, so it has to be committed: the lab will not send it again'
        );
    }

    #[RunInSeparateProcess]
    public function testTbTestRowsAreReplacedNotAddedOnResend(): void
    {
        $db = $this->boot();
        self::stsRequest($db, 'form_tb', 't-1', ['tb_id' => 1]);
        $record = static fn(array $tests): array => [
            'form_data' => self::vlResult('t-1', ['result' => 'Positive']),
            'data_from_tests' => $tests,
        ];
        $test = static fn(int $id, string $result): array => [
            'tb_test_id' => $id, 'tb_id' => 55, 'test_result' => $result, 'updated_datetime' => '2026-09-01 11:00:00',
        ];

        self::sts()->receiveResults('tb', self::payload([$record([$test(1, 'MTB'), $test(2, 'RIF')])]));
        // The lab re-tested: one test now, with a new result.
        self::sts()->receiveResults('tb', self::payload([$record([$test(3, 'MTB not detected')])]));

        $tests = $db->rawQuery('SELECT tb_id, test_result FROM tb_tests ORDER BY tb_test_id');
        self::assertSame([['tb_id' => 1, 'test_result' => 'MTB not detected']], array_map(
            static fn(array $row): array => ['tb_id' => (int) $row['tb_id'], 'test_result' => $row['test_result']],
            $tests
        ), 'on the STS row, not the lab row id');
    }

    /** A TB result with its test rows, as the lab sends it. */
    private static function tbRecord(?array $tests, array $form = []): array
    {
        $record = ['form_data' => self::vlResult('t-1', $form + ['result' => 'Positive'])];
        if ($tests !== null) {
            $record['data_from_tests'] = $tests;
        }
        return $record;
    }

    private static function tbTest(string $result): array
    {
        return [
            'tb_test_id' => 9, 'tb_id' => 55, 'test_result' => $result, 'updated_datetime' => '2026-09-01 11:00:00',
        ];
    }

    #[RunInSeparateProcess]
    public function testTbTestRowsTheLabRemovedAreRemovedOnTheSts(): void
    {
        $db = $this->boot();
        self::stsRequest($db, 'form_tb', 't-1', ['tb_id' => 1]);

        self::sts()->receiveResults('tb', self::payload([self::tbRecord([self::tbTest('MTB')])]));
        // The lab deleted its only test.
        self::sts()->receiveResults('tb', self::payload([self::tbRecord([])]));

        self::assertSame(0, (int) $db->rawQueryOne('SELECT COUNT(*) AS n FROM tb_tests')['n']);
    }

    #[RunInSeparateProcess]
    public function testTbTestRowsAreKeptWhenTheLabSentNone(): void
    {
        $db = $this->boot();
        self::stsRequest($db, 'form_tb', 't-1', ['tb_id' => 1]);

        self::sts()->receiveResults('tb', self::payload([self::tbRecord([self::tbTest('MTB')])]));
        // No data_from_tests at all: the sender said nothing about test rows.
        self::sts()->receiveResults('tb', self::payload([self::tbRecord(null)]));

        self::assertSame(1, (int) $db->rawQueryOne('SELECT COUNT(*) AS n FROM tb_tests')['n']);
    }

    #[RunInSeparateProcess]
    public function testALabThatReferredTheSampleAwayDoesNotClearTheTestRows(): void
    {
        $db = $this->boot();
        self::stsRequest($db, 'form_tb', 't-1', ['tb_id' => 1]);

        self::sts()->receiveResults('tb', self::payload([self::tbRecord([self::tbTest('MTB')])]));
        // The sample is referred to lab 8, so lab 7 has no rows of its own.
        $db->rawQuery('UPDATE form_tb SET referred_to_lab_id = 8');
        self::sts()->receiveResults('tb', self::payload([self::tbRecord([], ['referred_to_lab_id' => 8])]));
        $db->rawQuery('UPDATE form_tb SET referred_to_lab_id = NULL');
        // A lab that is not the sample's testing lab cannot claim to be it.
        self::sts()->receiveResults('tb', ['labId' => 9, 'results' => [self::tbRecord([], ['lab_id' => 9])]]);

        self::assertSame(1, (int) $db->rawQueryOne('SELECT COUNT(*) AS n FROM tb_tests')['n']);
    }

    /** @return list<string> */
    private static function tbResults($db): array
    {
        return array_column($db->rawQuery('SELECT test_result FROM tb_tests ORDER BY tb_test_id'), 'test_result');
    }

    #[RunInSeparateProcess]
    public function testTheReferringLabDoesNotReplaceTheReceivingLabsTests(): void
    {
        $db = $this->boot();
        self::stsRequest($db, 'form_tb', 't-1', ['tb_id' => 1, 'referred_to_lab_id' => 8]);

        self::sts()->receiveResults('tb', ['labId' => 8, 'results' => [self::tbRecord([self::tbTest('MTB')])]]);
        // Lab 7 referred the sample away and resends its older copy.
        self::sts()->receiveResults('tb', self::payload([self::tbRecord([self::tbTest('Negative')])]));

        self::assertSame(['MTB'], self::tbResults($db));
    }

    #[RunInSeparateProcess]
    public function testTheReceivingLabCanClearItsTests(): void
    {
        $db = $this->boot();
        self::stsRequest($db, 'form_tb', 't-1', ['tb_id' => 1, 'referred_to_lab_id' => 8]);

        self::sts()->receiveResults('tb', ['labId' => 8, 'results' => [self::tbRecord([self::tbTest('MTB')])]]);
        self::sts()->receiveResults('tb', ['labId' => 8, 'results' => [self::tbRecord([])]]);

        self::assertSame([], self::tbResults($db));
    }

    #[RunInSeparateProcess]
    public function testResendingTheSameTestsKeepsTheRowsAndLogsNothing(): void
    {
        $db = $this->boot();
        self::stsRequest($db, 'form_tb', 't-1', ['tb_id' => 1]);

        self::sts()->receiveResults('tb', self::payload([self::tbRecord([self::tbTest('MTB')])]));
        $id = $db->rawQueryOne('SELECT tb_test_id FROM tb_tests')['tb_test_id'];
        self::sts()->receiveResults('tb', self::payload([self::tbRecord([self::tbTest('MTB')])]));

        self::assertSame($id, $db->rawQueryOne('SELECT tb_test_id FROM tb_tests')['tb_test_id']);
        self::assertSame(0, (int) $db->rawQueryOne('SELECT COUNT(*) AS n FROM audit_log')['n']);
    }

    #[RunInSeparateProcess]
    public function testReplacedTestRowsAreKeptInTheAuditLog(): void
    {
        $db = $this->boot();
        self::stsRequest($db, 'form_tb', 't-1', ['tb_id' => 1]);

        self::sts()->receiveResults('tb', self::payload([self::tbRecord([self::tbTest('MTB')])]));
        self::sts()->receiveResults('tb', self::payload([self::tbRecord([self::tbTest('Negative')])]));

        self::assertSame(['Negative'], self::tbResults($db));
        $kept = $db->rawQueryOne("SELECT row_data FROM audit_log WHERE form_table = 'tb_tests'");
        self::assertSame('MTB', json_decode((string) $kept['row_data'], true)['test_result'] ?? null);
    }

    #[RunInSeparateProcess]
    public function testClearedTestRowsAreKeptInTheAuditLog(): void
    {
        $db = $this->boot();
        self::stsRequest($db, 'form_tb', 't-1', ['tb_id' => 1]);

        self::sts()->receiveResults('tb', self::payload([self::tbRecord([self::tbTest('MTB')])]));
        self::sts()->receiveResults('tb', self::payload([self::tbRecord([])]));

        $kept = $db->rawQueryOne("SELECT action, row_data FROM audit_log WHERE form_table = 'tb_tests'");
        self::assertSame('delete', $kept['action'] ?? null);
        self::assertSame('MTB', json_decode((string) $kept['row_data'], true)['test_result'] ?? null);
    }

    #[RunInSeparateProcess]
    public function testCovid19TestRowsArriveWithTheirValues(): void
    {
        $db = $this->boot();
        self::stsRequest($db, 'form_covid19', 'c-1', ['covid19_id' => 1]);

        // The lab sends COVID-19 rows keyed by its own sample id, then by test id.
        self::sts()->receiveResults('covid19', self::payload([[
            'form_data' => self::vlResult('c-1', ['result' => 'negative']),
            'data_from_tests' => [55 => [
                3 => ['test_id' => 3, 'covid19_id' => 55, 'test_name' => 'PCR', 'result' => 'negative'],
                4 => ['test_id' => 4, 'covid19_id' => 55, 'test_name' => 'Antigen', 'result' => 'positive'],
            ]],
        ]]));

        $tests = $db->rawQuery('SELECT covid19_id, test_name, result FROM covid19_tests ORDER BY test_id');
        self::assertSame([
            ['covid19_id' => 1, 'test_name' => 'PCR', 'result' => 'negative'],
            ['covid19_id' => 1, 'test_name' => 'Antigen', 'result' => 'positive'],
        ], array_map(
            static fn(array $row): array => ['covid19_id' => (int) $row['covid19_id']] + $row,
            $tests
        ));
    }

    /** A referral manifest as a lab sends it with its TB results. */
    private static function manifest(string $code, array $values = []): array
    {
        return $values + [
            'manifest_id' => 77, 'manifest_code' => $code, 'manifest_type' => 'referral', 'module' => 'tb',
            'added_by' => 'lab-user', 'manifest_status' => 'pending', 'lab_id' => self::LAB,
            'number_of_samples' => 2, 'last_modified_datetime' => '2026-09-01 10:00:00',
        ];
    }

    #[RunInSeparateProcess]
    public function testReferralManifestsAreAddedUpdatedAndLeftAloneWhenUnchanged(): void
    {
        $db = $this->boot();

        self::assertSame(
            ['inserted' => 1, 'updated' => 0, 'skipped' => 0, 'errors' => 0],
            self::sts()->receiveReferralManifests('tb', [self::manifest('REF-1')])
        );
        self::assertSame(
            ['inserted' => 0, 'updated' => 0, 'skipped' => 1, 'errors' => 0],
            self::sts()->receiveReferralManifests('tb', [self::manifest('REF-1')]),
            'sent again unchanged'
        );
        self::assertSame(
            ['inserted' => 0, 'updated' => 1, 'skipped' => 0, 'errors' => 0],
            self::sts()->receiveReferralManifests('tb', [self::manifest('REF-1', ['number_of_samples' => 3])])
        );

        $rows = $db->rawQuery('SELECT * FROM specimen_manifests');
        self::assertCount(1, $rows);
        self::assertNotSame(77, (int) $rows[0]['manifest_id'], 'the lab row id is not the STS one');
        self::assertSame(3, (int) $rows[0]['number_of_samples']);
    }

    #[RunInSeparateProcess]
    public function testOnlyReferralManifestsWithACodeAreTaken(): void
    {
        $db = $this->boot();

        $stats = self::sts()->receiveReferralManifests('tb', [
            self::manifest('COL-1', ['manifest_type' => 'collection']),
            self::manifest('', ['manifest_code' => null]),
            null,
            'junk',
            self::manifest('REF-1'),
        ]);

        self::assertSame(['inserted' => 1, 'updated' => 0, 'skipped' => 4, 'errors' => 0], $stats);
        $stored = array_column($db->rawQuery('SELECT manifest_code FROM specimen_manifests'), 'manifest_code');
        self::assertSame(['REF-1'], $stored);
    }

    #[RunInSeparateProcess]
    public function testAManifestFromALabOnAnOlderReleaseDoesNotBlankWhatItDoesNotHave(): void
    {
        $db = $this->boot();
        self::sts()->receiveReferralManifests('tb', [self::manifest('REF-1')]);
        $db->rawQuery(
            "UPDATE specimen_manifests SET manifest_print_history = JSON_ARRAY('printed') WHERE manifest_code = 'REF-1'"
        );

        $older = self::manifest('REF-1', ['manifest_status' => 'received']);
        unset($older['manifest_print_history']);
        self::sts()->receiveReferralManifests('tb', [$older]);

        $row = $db->rawQueryOne("SELECT * FROM specimen_manifests WHERE manifest_code = 'REF-1'");
        self::assertSame('received', $row['manifest_status']);
        self::assertSame('["printed"]', $row['manifest_print_history']);
    }

    #[RunInSeparateProcess]
    public function testAManifestThatCannotBeSavedCostsOnlyItself(): void
    {
        $db = $this->boot();
        // Stands in for any failure saving a manifest.
        $db->rawQuery(
            "ALTER TABLE specimen_manifests ADD CONSTRAINT refuse_manifest CHECK (manifest_status <> 'refused')"
        );

        $stats = self::sts()->receiveReferralManifests('tb', [
            self::manifest('REF-1'),
            self::manifest('REF-2', ['manifest_status' => 'refused']),
            self::manifest('REF-3'),
        ]);

        self::assertSame(['inserted' => 2, 'updated' => 0, 'skipped' => 0, 'errors' => 1], $stats);
        $stored = $db->rawQuery('SELECT manifest_code FROM specimen_manifests ORDER BY manifest_code');
        self::assertSame(['REF-1', 'REF-3'], array_column($stored, 'manifest_code'));
    }
}
