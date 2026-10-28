<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\HttpHandlers\LegacyRequestHandler;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\STS\RequestsService;
use App\Services\STS\ResultsService;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Tests\Support\LegacyAppHarness;
use Tests\Support\TestRun;

/**
 * A lab's own results, left out of its next request pull.
 *
 * Saving a lab's result moves the row's last_modified_datetime on the STS, so the
 * row came back to that lab in its next window. A lab that resent 26,553 results
 * pulled them all back, and that window of 31,000 rows ran the STS out of memory.
 * The lab already holds that version, so on the routine pull of a lab that asks
 * ("skipOwnResults") the STS leaves the row out, until something changes it on
 * the STS. Older labs, and a pull a lab makes by hand to recover rows after a
 * restore, get every row, as before.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class RequestOwnResultsEchoTest extends TestCase
{
    private const LAB = 7;

    private const SINCE = '2026-08-01';

    private bool $booted = false;

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
        require_once ROOT_PATH . '/app/system/version.php';
        $database = TestRun::database('intelis_request_own_results_echo_test');
        // getTableFieldsAsArray() reads columns from SYSTEM_CONFIG's database.
        if (!defined('SYSTEM_CONFIG')) {
            define('SYSTEM_CONFIG', ['database' => ['db' => $database], 'modules' => ['vl' => true]]);
        }
        $db = LegacyAppHarness::boot($database, [
            'system_config', 'global_config', 's_vlsm_instance', 'r_sample_status', 'form_vl', 'roles',
            'user_details', 'r_vl_sample_rejection_reasons', 'facility_details', 'specimen_manifests',
            'testing_lab_health_facilities_map', 'track_api_requests',
        ]);
        $this->booted = true;
        $db->rawQuery("INSERT INTO r_sample_status (status_id, status_name) VALUES (6, 'Registered'), (7, 'Accepted')");
        $db->insert('global_config', [
            'display_name' => 'Sync interval', 'name' => 'data_sync_interval', 'value' => '30',
        ]);
        $db->insert('facility_details', [
            'facility_id' => self::LAB, 'facility_name' => 'Lab Seven', 'facility_type' => 2, 'status' => 'active',
            'sts_token' => 'lab-seven-token', 'sts_token_expiry' => date('Y-m-d H:i:s', strtotime('+30 days')),
        ]);
        // Requests the STS sent to the lab, on one manifest.
        foreach ([1, 2, 3] as $id) {
            $db->insert('form_vl', [
                'vl_sample_id' => $id, 'unique_id' => "u-$id", 'remote_sample_code' => "R-u-$id",
                'lab_id' => self::LAB, 'facility_id' => 1, 'vlsm_instance_id' => 'sts-instance',
                'result_status' => 6, 'sample_package_code' => 'M-1',
                'last_modified_datetime' => '2026-09-01 10:00:00', 'data_sync' => 1,
            ]);
        }
        return $db;
    }

    /** The lab sends its result for a request. */
    private static function labSends(string $uniqueId, array $extra = [], bool $silent = false): void
    {
        $result = $extra + [
            'unique_id' => $uniqueId, 'remote_sample_code' => "R-$uniqueId", 'sample_code' => "L-$uniqueId",
            'lab_id' => self::LAB, 'facility_id' => 1, 'vlsm_instance_id' => 'lab-instance',
            'result' => '40', 'result_status' => 7,
        ];
        $ack = ContainerRegistry::get(ResultsService::class)
            ->receiveResults('vl', ['labId' => self::LAB, 'results' => [$result]], $silent);
        self::assertSame(["L-$uniqueId"], $ack);
    }

    /**
     * The window as a paging lab gets it, and how many rows the first page said follow it.
     *
     * @return array{0: list<string>, 1: int}
     */
    private static function pagedWindow(
        int $labId = self::LAB,
        ?string $facilities = null,
        bool $skipOwnResults = true
    ): array {
        $ids = [];
        $after = null;
        $firstRemaining = null;
        do {
            $data = ContainerRegistry::get(RequestsService::class)->getRequests(
                'vl',
                $labId,
                $facilities,
                null,
                self::SINCE,
                false,
                false,
                ['size' => 1, 'after' => $after],
                $skipOwnResults
            );
            $ids = [...$ids, ...array_column($data['requests'], 'unique_id')];
            $firstRemaining ??= $data['remaining'];
            $after = $data['nextAfter'];
        } while ($after !== null);
        sort($ids);
        return [$ids, (int) $firstRemaining];
    }

    /**
     * The whole window, as every lab that does not page gets it.
     *
     * @return list<string>
     */
    private static function wholeWindow(?string $manifest = null, bool $skipOwnResults = true): array
    {
        $out = fopen('php://temp', 'w+b');
        ContainerRegistry::get(RequestsService::class)->writeRequests(
            $out,
            'vl',
            self::LAB,
            [],
            $manifest,
            $manifest === null ? self::SINCE : null,
            skipOwnResults: $skipOwnResults
        );
        rewind($out);
        $ids = array_column(json_decode((string) stream_get_contents($out), true), 'unique_id');
        sort($ids);
        return $ids;
    }

    #[RunInSeparateProcess]
    public function testALabsOwnResultIsLeftOutOfItsNextPull(): void
    {
        $this->boot();
        self::assertSame(['u-1', 'u-2', 'u-3'], self::wholeWindow(), 'all three before the result');

        self::labSends('u-1');

        self::assertSame(['u-2', 'u-3'], self::wholeWindow());
        [$paged, $remaining] = self::pagedWindow();
        self::assertSame(['u-2', 'u-3'], $paged);
        self::assertSame(1, $remaining, 'the count after the first page leaves it out too');
    }

    #[RunInSeparateProcess]
    public function testALabThatDoesNotAskGetsItsOwnResultsAsBefore(): void
    {
        $this->boot();
        self::labSends('u-1');

        // Every older lab, and a pull for a number of days after restoring a backup.
        self::assertSame(['u-1', 'u-2', 'u-3'], self::wholeWindow(skipOwnResults: false));
        [$paged, $remaining] = self::pagedWindow(skipOwnResults: false);
        self::assertSame(['u-1', 'u-2', 'u-3'], $paged);
        self::assertSame(2, $remaining);
    }

    private static function post(array $body): array
    {
        // One per test: the legacy handler loads requests.php once a process.
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/remote/v2/requests.php')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer lab-seven-token')
            ->withBody((new StreamFactory())->createStream((string) json_encode($body)));
        $handler = new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));
        $ids = array_column(json_decode((string) $handler->handle($request)->getBody(), true)['requests'], 'unique_id');
        sort($ids);
        return $ids;
    }

    #[RunInSeparateProcess]
    public function testRequestsPhpLeavesThemOutForALabThatAsks(): void
    {
        $this->boot();
        self::labSends('u-1');

        $ids = self::post([
            'labId' => self::LAB, 'testType' => 'vl', 'syncSinceDate' => self::SINCE, 'skipOwnResults' => 1,
        ]);

        self::assertSame(['u-2', 'u-3'], $ids);
    }

    #[RunInSeparateProcess]
    public function testRequestsPhpSendsThemToALabThatDoesNotAsk(): void
    {
        $this->boot();
        self::labSends('u-1');

        self::assertSame(['u-1', 'u-2', 'u-3'], self::post([
            'labId' => self::LAB, 'testType' => 'vl', 'syncSinceDate' => self::SINCE,
        ]));
    }

    #[RunInSeparateProcess]
    public function testAChangeOnTheStsAfterTheResultIsSentAgain(): void
    {
        $db = $this->boot();
        self::labSends('u-1');
        self::labSends('u-2');
        self::labSends('u-3');
        self::assertSame([], self::wholeWindow());

        // An edit on the STS: a new time, and waiting for the lab again.
        $db->rawQuery("UPDATE form_vl SET patient_first_name = 'Ana', data_sync = 0,
            last_modified_datetime = '2030-01-01 10:00:00' WHERE unique_id = 'u-1'");
        // A new time only.
        $db->rawQuery("UPDATE form_vl SET last_modified_datetime = '2030-01-01 10:00:00' WHERE unique_id = 'u-2'");
        // Waiting for the lab again, in the same second as the result.
        $db->rawQuery("UPDATE form_vl SET data_sync = 0 WHERE unique_id = 'u-3'");

        self::assertSame(['u-1', 'u-2', 'u-3'], self::wholeWindow());
    }

    #[RunInSeparateProcess]
    public function testAChangeTheLabHadNotPulledBeforeItsResultStillArrives(): void
    {
        $db = $this->boot();
        // Edited on the STS while the lab was offline, and in flight to it.
        $db->rawQuery("UPDATE form_vl SET patient_first_name = 'Ana', data_sync = 0 WHERE unique_id = 'u-1'");
        $db->rawQuery('UPDATE form_vl SET data_sync = ' . RequestsService::IN_FLIGHT . " WHERE unique_id = 'u-2'");

        // The lab sends its results before it pulls again.
        self::labSends('u-1');
        self::labSends('u-2');
        self::labSends('u-3');

        // Its copies of u-1 and u-2 may lack what the STS holds; u-3 it had.
        self::assertSame(['u-1', 'u-2'], self::wholeWindow());
        [$paged] = self::pagedWindow();
        self::assertSame(['u-1', 'u-2'], $paged);
    }

    #[RunInSeparateProcess]
    public function testAnotherLabStillGetsTheResult(): void
    {
        $this->boot();
        self::labSends('u-1');

        // Lab 8 serves facility 1 as well, so the window it pulls holds lab 7's samples.
        [$paged] = self::pagedWindow(8, '1');

        self::assertSame(['u-1', 'u-2', 'u-3'], $paged);
    }

    #[RunInSeparateProcess]
    public function testAManifestPullStillCarriesTheLabsOwnResult(): void
    {
        $this->boot();
        self::labSends('u-1');

        self::assertSame(['u-1', 'u-2', 'u-3'], self::wholeWindow('M-1'));
    }

    #[RunInSeparateProcess]
    public function testOnlyTheStsRecordsWhoMadeTheChange(): void
    {
        $db = $this->boot();
        $forged = [ResultsService::RESULTS_FROM_LAB => ['labId' => '9', 'at' => '2026-09-01 10:00:00']];

        // A lab's copy of the record is not taken: the STS writes its own.
        self::labSends('u-1', ['form_attributes' => json_encode($forged + ['note' => 'kept'])]);
        $first = json_decode(
            (string) $db->rawQueryValue("SELECT form_attributes FROM form_vl WHERE unique_id = 'u-1' LIMIT 1"),
            true
        );
        self::assertSame('kept', $first['note']);
        self::assertSame((string) self::LAB, $first[ResultsService::RESULTS_FROM_LAB]['labId']);

        // A silent resend keeps the row's timestamp, and with it the STS's record.
        self::labSends('u-1', ['form_attributes' => json_encode($forged), 'result' => '60'], silent: true);
        $after = json_decode(
            (string) $db->rawQueryValue("SELECT form_attributes FROM form_vl WHERE unique_id = 'u-1' LIMIT 1"),
            true
        );
        self::assertSame($first[ResultsService::RESULTS_FROM_LAB], $after[ResultsService::RESULTS_FROM_LAB]);
        self::assertSame('60', $db->rawQueryValue("SELECT result FROM form_vl WHERE unique_id = 'u-1' LIMIT 1"));
        self::assertSame(['u-2', 'u-3'], self::wholeWindow());
    }
}
