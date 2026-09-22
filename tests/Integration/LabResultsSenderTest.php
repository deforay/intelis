<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Registries\ContainerRegistry;
use App\Services\LabResultsSenderService;
use mysqli;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyAppHarness;

/**
 * The lab side of a results push, from selecting rows to marking them synced, with
 * the STS replaced by a closure that records what was posted and answers as told.
 *
 * What must hold: a row is marked synced only when the STS acknowledged it and
 * nothing changed it since it was read; anything that is not an acknowledgment
 * stops the module and leaves its rows pending; only finished work is sent.
 *
 * Rows are read back on a second connection, so what is checked is what the server
 * holds. One boot per test, in its own process, as the harness requires.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class LabResultsSenderTest extends TestCase
{
    private const LAB = 7;
    private const ACCEPTED = 7;
    private const RECEIVED_AT_CLINIC = 9;

    private bool $booted = false;
    private string $database = '';

    /** @var list<array<string, mixed>> what the fake STS received, decoded */
    private array $posts = [];

    protected function tearDown(): void
    {
        if ($this->booted) {
            LegacyAppHarness::shutdown();
        }
    }

    private function boot(): void
    {
        if (!getenv('INTELIS_TEST_DB_HOST') || !getenv('INTELIS_TEST_DB_USER')) {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }
        $this->database = 'intelis_results_sender_test_' . getmypid();
        if (!defined('SYSTEM_CONFIG')) {
            define('SYSTEM_CONFIG', [
                'database' => ['db' => $this->database],
                'system' => ['api_tracking_bodies' => 'off'],
            ]);
        }
        $db = LegacyAppHarness::boot($this->database, [
            'system_config', 'global_config', 's_vlsm_instance', 'r_sample_status', 'roles', 'user_details',
            'track_api_requests', 'specimen_manifests',
            'form_vl', 'form_tb', 'tb_tests', 'form_generic', 'generic_test_results', 'form_covid19', 'covid19_tests',
        ]);
        $this->booted = true;
        $db->rawQuery(
            "INSERT INTO r_sample_status (status_id, status_name)
                VALUES (6, 'Registered'), (7, 'Accepted'), (9, 'Received at clinic')"
        );
        $db->insert('s_vlsm_instance', ['vlsm_instance_id' => 'lab-instance']);
        $db->insert('user_details', ['user_id' => 'approver-1', 'user_name' => 'Dr Approver']);
    }

    private static function vl(string $uniqueId, array $columns = []): void
    {
        LegacyAppHarness::db()->insert('form_vl', $columns + [
            'unique_id' => $uniqueId, 'sample_code' => "S-$uniqueId", 'vlsm_instance_id' => 'lab-instance',
            'lab_id' => self::LAB, 'result_status' => self::ACCEPTED, 'result' => '40', 'data_sync' => 0,
            'result_approved_by' => 'approver-1',
            'last_modified_datetime' => '2026-09-20 10:00:00',
        ]);
    }

    /** @return array<string, int> data_sync by unique_id, as the server has it */
    private function dataSync(string $table = 'form_vl'): array
    {
        $mysqli = new mysqli(
            (string) getenv('INTELIS_TEST_DB_HOST'),
            (string) getenv('INTELIS_TEST_DB_USER'),
            (string) (getenv('INTELIS_TEST_DB_PASS') ?: ''),
            $this->database,
            (int) (getenv('INTELIS_TEST_DB_PORT') ?: 3306)
        );
        $rows = $mysqli->query("SELECT unique_id, data_sync FROM `$table` ORDER BY unique_id")->fetch_all(MYSQLI_ASSOC);
        $mysqli->close();
        return array_map('intval', array_column($rows, 'data_sync', 'unique_id'));
    }

    /**
     * An STS that answers every post with $answer, or with what $answer returns for
     * the decoded payload. The default acknowledges everything it was sent, by unique_id.
     */
    private function sts(mixed $answer = null): callable
    {
        return function (string $url, string $json) use ($answer): array|string|null {
            $payload = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
            $this->posts[] = $payload;
            if (is_callable($answer)) {
                return $answer($payload);
            }
            if ($answer !== null) {
                return $answer;
            }
            return self::ack(self::idsIn($payload));
        };
    }

    /**
     * Flat rows carry their unique_id; nested ones are keyed by it. Flat rows past the
     * first chunk keep their position as key, so the key alone does not tell.
     *
     * @return list<string>
     */
    private static function idsIn(array $payload): array
    {
        $ids = [];
        foreach ($payload['results'] as $key => $row) {
            $ids[] = isset($row['form_data']) ? (string) $key : (string) $row['unique_id'];
        }
        return $ids;
    }

    /** @param list<string> $ids */
    private static function ack(array $ids, bool $byUniqueId = true): array
    {
        return [
            'httpStatusCode' => 200,
            'body' => json_encode($ids),
            'headers' => $byUniqueId ? ['X-Ack-Format' => 'unique_id'] : [],
        ];
    }

    /** @return array{selected: int, acknowledged: int, chunks: int} */
    private function send(string $testType, callable $sts, array $run = [], int $chunkSize = 100): array
    {
        $sender = ContainerRegistry::get(LabResultsSenderService::class);
        return $sender->sendModule($testType, $run + [
            'labId' => self::LAB,
            'url' => 'https://sts.test/remote/v2/results.php',
            'remoteUrl' => 'https://sts.test',
            'transactionId' => 'TXN',
            'syncSinceDate' => null,
            'sampleCode' => null,
            'maxChunkSize' => $chunkSize,
            'maxPayloadBytes' => 2 * 1024 * 1024,
            'dryRun' => false,
            'silent' => false,
        ], $chunkSize, null, $sts);
    }

    /** @return list<string> unique_ids of every result posted, in order */
    private function postedIds(): array
    {
        return array_merge(...array_map(self::idsIn(...), $this->posts ?: [['results' => []]]));
    }

    // ---- The STS does not acknowledge -------------------------------------------

    #[RunInSeparateProcess]
    public function testAnErrorAnswerStopsTheModuleAndLeavesItsRowsPending(): void
    {
        $this->boot();
        self::vl('a');
        self::vl('b');
        self::vl('c');

        $result = $this->send('vl', $this->sts([
            'httpStatusCode' => 500, 'body' => '{"error":"Internal Server Error"}', 'headers' => [],
        ]), chunkSize: 1);

        $this->assertCount(1, $this->posts, 'nothing more is sent to an STS that refused the first chunk');
        $this->assertSame(0, $result['acknowledged']);
        // The row that was sent stays in flight, and the next run puts it back to pending.
        $this->assertSame(['a' => 2, 'b' => 0, 'c' => 0], $this->dataSync());
    }

    #[RunInSeparateProcess]
    public function testNoAnswerStopsTheModule(): void
    {
        $this->boot();
        self::vl('a');
        self::vl('b');

        $this->send('vl', $this->sts(fn() => null), chunkSize: 1);

        $this->assertCount(1, $this->posts);
        $this->assertNotContains(1, $this->dataSync());
    }

    #[RunInSeparateProcess]
    public function testAnAnswerThatIsNotAnAcknowledgmentStopsTheModule(): void
    {
        $this->boot();
        self::vl('a');
        self::vl('b');

        $this->send('vl', $this->sts(['httpStatusCode' => 200, 'body' => '<html>Login</html>']), chunkSize: 1);

        $this->assertCount(1, $this->posts);
        $this->assertNotContains(1, $this->dataSync());
    }

    #[RunInSeparateProcess]
    public function testOnlyTheAcknowledgedRowsAreMarkedSynced(): void
    {
        $this->boot();
        self::vl('a');
        self::vl('b');
        self::vl('c');

        $result = $this->send('vl', $this->sts(self::ack(['a', 'c'])));

        $this->assertSame(2, $result['acknowledged']);
        $this->assertSame(['a' => 1, 'b' => 2, 'c' => 1], $this->dataSync());
    }

    #[RunInSeparateProcess]
    public function testARowEditedWhileItsResultWasOutStaysPending(): void
    {
        $this->boot();
        self::vl('a');
        self::vl('b');

        $this->send('vl', $this->sts(function (array $payload): array {
            // A user edits b between the read and the STS's answer; saving a
            // record sets data_sync back to 0.
            LegacyAppHarness::db()->rawQuery("UPDATE form_vl SET data_sync = 0, result = '50' WHERE unique_id = 'b'");
            return self::ack(['a', 'b']);
        }));

        $this->assertSame(['a' => 1, 'b' => 0], $this->dataSync(), 'the edit still has to go');
    }

    #[RunInSeparateProcess]
    public function testAnOlderStsThatAcknowledgesBySampleCodeIsUnderstood(): void
    {
        $this->boot();
        self::vl('a');
        self::vl('b');

        $this->send('vl', $this->sts(self::ack(['S-a'], byUniqueId: false)));

        $this->assertSame(['a' => 1, 'b' => 2], $this->dataSync());
    }

    #[RunInSeparateProcess]
    public function testAnAcknowledgmentForARowThatWasNotSentMarksNothing(): void
    {
        $this->boot();
        self::vl('sent');
        self::vl('not-a-result-yet', ['result_status' => self::RECEIVED_AT_CLINIC]);

        $this->send('vl', $this->sts(self::ack(['sent', 'not-a-result-yet'])));

        $this->assertSame(['not-a-result-yet' => 0, 'sent' => 1], $this->dataSync());
    }

    // ---- What is selected -------------------------------------------------------

    #[RunInSeparateProcess]
    public function testOnlyResultsWithASampleCodeAreSent(): void
    {
        $this->boot();
        self::vl('result');
        self::vl('at-clinic', ['result_status' => self::RECEIVED_AT_CLINIC]);
        self::vl('no-code', ['sample_code' => '']);
        self::vl('null-code', ['sample_code' => null]);

        $this->send('vl', $this->sts());

        $this->assertSame(['result'], $this->postedIds());
    }

    #[RunInSeparateProcess]
    public function testSyncedRowsAreNotSentAgainButRowsLeftInFlightAre(): void
    {
        $this->boot();
        self::vl('synced', ['data_sync' => 1]);
        self::vl('pending', ['data_sync' => 0]);
        self::vl('left-in-flight', ['data_sync' => 2]);

        $this->send('vl', $this->sts());

        $this->assertEqualsCanonicalizing(['pending', 'left-in-flight'], $this->postedIds());
        foreach ($this->posts[0]['results'] as $row) {
            $this->assertSame(0, (int) $row['data_sync'], 'the in-flight marker is this lab\'s, never sent');
        }
        $this->assertSame(['left-in-flight' => 1, 'pending' => 1, 'synced' => 1], $this->dataSync());
    }

    #[RunInSeparateProcess]
    public function testASinceDateResendsWhatChangedSinceEvenIfSynced(): void
    {
        $this->boot();
        self::vl('changed-synced', ['data_sync' => 1, 'last_modified_datetime' => '2026-09-15 09:00:00']);
        self::vl('old-pending', ['data_sync' => 0, 'last_modified_datetime' => '2026-09-01 09:00:00']);

        $this->send('vl', $this->sts(), ['syncSinceDate' => '2026-09-10 00:00:00']);

        $this->assertSame(['changed-synced'], $this->postedIds());
    }

    #[RunInSeparateProcess]
    public function testASampleCodeSendsOnlyThatSample(): void
    {
        $this->boot();
        self::vl('a');
        self::vl('b');

        $this->send('vl', $this->sts(), ['sampleCode' => 'S-b']);

        $this->assertSame(['b'], $this->postedIds());
        $this->assertSame(['a' => 0, 'b' => 1], $this->dataSync());
    }

    #[RunInSeparateProcess]
    public function testASampleCodeFromTheQueryStringIsOnlyEverAValue(): void
    {
        $this->boot();
        self::vl('a');

        $result = $this->send('vl', $this->sts(), ['sampleCode' => "x' OR '1'='1"]);

        $this->assertSame(0, $result['selected']);
        $this->assertSame([], $this->posts);
    }

    #[RunInSeparateProcess]
    public function testADryRunSendsNothingAndChangesNothing(): void
    {
        $this->boot();
        self::vl('a');
        self::vl('b', ['data_sync' => 2]);

        $result = $this->send('vl', $this->sts(), ['dryRun' => true]);

        $this->assertSame(2, $result['selected']);
        $this->assertSame([], $this->posts);
        $this->assertSame(['a' => 0, 'b' => 2], $this->dataSync());
        $tracked = LegacyAppHarness::db()->rawQueryOne('SELECT COUNT(*) AS n FROM track_api_requests');
        $this->assertSame(0, (int) $tracked['n']);
    }

    #[RunInSeparateProcess]
    public function testNothingToSendPostsNothing(): void
    {
        $this->boot();
        self::vl('a', ['data_sync' => 1]);

        $result = $this->send('vl', $this->sts());

        $this->assertSame(['selected' => 0, 'acknowledged' => 0, 'chunks' => 0], $result);
        $this->assertSame([], $this->posts);
    }

    // ---- How it is sent ---------------------------------------------------------

    #[RunInSeparateProcess]
    public function testRowsGoInChunksAndEveryChunkIsMarked(): void
    {
        $this->boot();
        foreach (['a', 'b', 'c', 'd', 'e'] as $id) {
            self::vl($id);
        }

        $result = $this->send('vl', $this->sts(), chunkSize: 2);

        $this->assertSame([2, 2, 1], array_map(static fn(array $p): int => count($p['results']), $this->posts));
        $this->assertSame(['selected' => 5, 'acknowledged' => 5, 'chunks' => 3], $result);
        $this->assertSame(['a' => 1, 'b' => 1, 'c' => 1, 'd' => 1, 'e' => 1], $this->dataSync());
    }

    #[RunInSeparateProcess]
    public function testAFailedChunkKeepsWhatEarlierChunksHadMarked(): void
    {
        $this->boot();
        foreach (['a', 'b', 'c', 'd'] as $id) {
            self::vl($id);
        }
        $answers = 0;

        $this->send('vl', $this->sts(function (array $payload) use (&$answers): array {
            return ++$answers === 1
                ? self::ack(self::idsIn($payload))
                : ['httpStatusCode' => 401, 'body' => '{"error":"Unauthorized"}'];
        }), chunkSize: 2);

        $this->assertCount(2, $this->posts);
        $this->assertSame(['a' => 1, 'b' => 1, 'c' => 2, 'd' => 2], $this->dataSync());
    }

    #[RunInSeparateProcess]
    public function testThePayloadCarriesWhatTheStsReads(): void
    {
        $this->boot();
        self::vl('a');

        $this->send('vl', $this->sts());

        $post = $this->posts[0];
        $this->assertSame(self::LAB, $post['labId']);
        $this->assertSame('vl', $post['testType']);
        $this->assertSame('unique_id', $post['ackFormat'], 'an updated STS then acknowledges by unique_id');
        $this->assertSame('lab-instance', $post['instanceId']);
        $this->assertSame('Dr Approver', $post['results'][0]['approved_by_name']);
        $this->assertSame('40', $post['results'][0]['result']);
        $this->assertArrayNotHasKey('manifests', $post);
        $this->assertArrayNotHasKey('silent', $post);
    }

    #[RunInSeparateProcess]
    public function testEachChunkAndTheModuleAreTracked(): void
    {
        $this->boot();
        foreach (['a', 'b', 'c'] as $id) {
            self::vl($id);
        }

        $this->send('vl', $this->sts(), chunkSize: 2);

        $rows = LegacyAppHarness::db()->rawQuery(
            'SELECT transaction_id, number_of_records FROM track_api_requests ORDER BY api_track_id'
        );
        $this->assertSame(
            [['TXN-vl-001', 2], ['TXN-vl-002', 1], ['TXN', 3]],
            array_map(static fn(array $r): array => [$r['transaction_id'], (int) $r['number_of_records']], $rows)
        );
    }

    // ---- Modules with child test rows --------------------------------------------

    #[RunInSeparateProcess]
    public function testTbResultsCarryTheirTestsAndReferralManifests(): void
    {
        $this->boot();
        $db = LegacyAppHarness::db();
        $db->insert('form_tb', [
            'tb_id' => 1, 'unique_id' => 'tb-1', 'sample_code' => 'TB-1', 'sample_code_key' => 1,
            'result_status' => self::ACCEPTED, 'data_sync' => 0, 'referral_manifest_code' => 'REF-9',
            'sample_collection_date' => '2026-09-01 00:00:00',
        ]);
        $db->insert('tb_tests', ['tb_id' => 1, 'test_result' => 'positive']);
        $db->insert('specimen_manifests', [
            'manifest_code' => 'REF-9', 'manifest_type' => 'referral', 'module' => 'tb', 'lab_id' => self::LAB,
        ]);
        $db->insert('specimen_manifests', [
            'manifest_code' => 'OTHER', 'manifest_type' => 'referral', 'module' => 'tb', 'lab_id' => self::LAB,
        ]);

        $this->send('tb', $this->sts());

        $post = $this->posts[0];
        $this->assertSame(['tb-1'], array_keys($post['results']));
        $this->assertSame(['positive'], array_column($post['results']['tb-1']['data_from_tests'], 'test_result'));
        $this->assertSame(['REF-9'], array_column($post['manifests'], 'manifest_code'));
        $this->assertSame(['tb-1' => 1], $this->dataSync('form_tb'));
    }

    #[RunInSeparateProcess]
    public function testCustomTestResultsCarryTheirTestsAndTheSilentFlag(): void
    {
        $this->boot();
        $db = LegacyAppHarness::db();
        $db->insert('form_generic', [
            'sample_id' => 1, 'unique_id' => 'gen-1', 'sample_code' => 'G-1', 'vlsm_instance_id' => 'lab-instance',
            'request_created_by' => 'test', 'result_status' => self::ACCEPTED, 'data_sync' => 0,
        ]);
        $db->insert('generic_test_results', ['generic_id' => 1, 'test_name' => 'Culture', 'result' => 'negative']);

        $this->send('generic-tests', $this->sts(), ['silent' => true]);

        $post = $this->posts[0];
        $this->assertTrue($post['silent']);
        $this->assertSame(['Culture'], array_column($post['results']['gen-1']['data_from_tests'], 'test_name'));
        $this->assertSame(['gen-1' => 1], $this->dataSync('form_generic'));
    }

    #[RunInSeparateProcess]
    public function testCovidResultsCarryTheirTests(): void
    {
        $this->boot();
        $db = LegacyAppHarness::db();
        $db->insert('form_covid19', [
            'covid19_id' => 1, 'unique_id' => 'c-1', 'sample_code' => 'C-1', 'result_status' => self::ACCEPTED,
            'data_sync' => 0, 'sample_collection_date' => '2026-09-01 00:00:00',
        ]);
        $db->insert('covid19_tests', [
            'covid19_id' => 1, 'test_name' => 'PCR', 'result' => 'negative',
            'sample_tested_datetime' => '2026-09-02 00:00:00',
        ]);

        $this->send('covid19', $this->sts());

        $post = $this->posts[0];
        // COVID-19 tests go keyed by the parent's id, as they always have.
        $this->assertSame(['PCR'], array_column($post['results']['c-1']['data_from_tests']['1'], 'test_name'));
        $this->assertSame(['c-1' => 1], $this->dataSync('form_covid19'));
    }

    #[RunInSeparateProcess]
    public function testAChildModuleRowEditedWhileOutStaysPending(): void
    {
        $this->boot();
        $db = LegacyAppHarness::db();
        foreach ([1, 2] as $id) {
            $db->insert('form_tb', [
                'tb_id' => $id, 'unique_id' => "tb-$id", 'sample_code' => "TB-$id", 'sample_code_key' => $id,
                'result_status' => self::ACCEPTED, 'data_sync' => 0, 'sample_collection_date' => '2026-09-01 00:00:00',
            ]);
        }

        $this->send('tb', $this->sts(function (array $payload): array {
            LegacyAppHarness::db()->rawQuery("UPDATE form_tb SET data_sync = 0 WHERE unique_id = 'tb-2'");
            return self::ack(self::idsIn($payload));
        }));

        $this->assertSame(['tb-1' => 1, 'tb-2' => 0], $this->dataSync('form_tb'));
    }
}
