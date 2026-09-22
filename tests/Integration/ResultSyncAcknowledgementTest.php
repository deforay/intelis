<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Registries\ContainerRegistry;
use App\Services\STS\ResultsService;
use App\Utilities\ResultSyncAcknowledgement;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyAppHarness;

final class ResultSyncAcknowledgementTest extends TestCase
{
    private bool $booted = false;

    protected function tearDown(): void
    {
        if ($this->booted) {
            LegacyAppHarness::shutdown();
        }
    }

    private function requireDatabase(): void
    {
        if (!getenv('INTELIS_TEST_DB_HOST') || !getenv('INTELIS_TEST_DB_USER')) {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }
    }

    /** @return array<int, array<string, mixed>> */
    private static function syncState($db): array
    {
        return array_column(
            $db->rawQuery(
                'SELECT vl_sample_id, data_sync, result_sent_to_source, last_modified_datetime
                    FROM form_vl ORDER BY vl_sample_id'
            ),
            null,
            'vl_sample_id'
        );
    }

    #[RunInSeparateProcess]
    public function testOnlyRowsStillInFlightAreMarkedSynced(): void
    {
        $this->requireDatabase();
        $db = LegacyAppHarness::boot('intelis_result_sync_ack_test_' . getmypid(), ['r_sample_status', 'form_vl']);
        $this->booted = true;
        $db->rawQuery("INSERT INTO r_sample_status (status_id, status_name) VALUES (7, 'Accepted')");

        $sent = [];
        foreach ([1 => 'u-1', 2 => 'u-2', 3 => 'u-3', 4 => 'u-4'] as $id => $uniqueId) {
            $row = [
                'unique_id' => $uniqueId, 'sample_code' => "S-$id", 'last_modified_datetime' => '2026-09-01 10:00:00',
            ];
            $db->insert('form_vl', [
                'vl_sample_id' => $id, 'data_sync' => 0, 'vlsm_instance_id' => 'test', 'result_status' => 7,
            ] + $row);
            $sent[] = ['vl_sample_id' => $id] + $row;
        }
        ResultSyncAcknowledgement::markInFlight($db, 'form_vl', 'vl_sample_id', [1, 2, 3, 4]);

        // While the request is out: row 2 is edited, row 3 is printed for the first
        // time. The print, like generate-result-pdf.php, leaves the timestamp alone.
        $db->where('vl_sample_id', 2);
        $db->update('form_vl', ['result' => '50', 'data_sync' => 0, 'last_modified_datetime' => '2026-09-01 10:00:05']);
        $db->where('vl_sample_id', 3);
        $db->update('form_vl', ['result_printed_on_lis_datetime' => '2026-09-01 10:00:05', 'data_sync' => 0]);

        // The STS acknowledged 1, 2 and 3; 4 was not acknowledged.
        $acknowledged = ResultSyncAcknowledgement::acknowledgedRows($sent, ['u-1', 'u-2', 'u-3'], true);
        $marked = ResultSyncAcknowledgement::markSynced($db, 'form_vl', 'vl_sample_id', $acknowledged);

        self::assertSame(1, $marked);
        $state = self::syncState($db);
        self::assertSame(1, (int) $state[1]['data_sync'], 'unchanged and acknowledged');
        self::assertSame('sent', $state[1]['result_sent_to_source']);
        self::assertSame(0, (int) $state[2]['data_sync'], 'edited in flight stays pending');
        self::assertSame(0, (int) $state[3]['data_sync'], 'first printed in flight stays pending');
        self::assertSame(
            '2026-09-01 10:00:00',
            $state[3]['last_modified_datetime'],
            'printing is not an update in the grids'
        );
        self::assertSame(
            ResultSyncAcknowledgement::IN_FLIGHT,
            (int) $state[4]['data_sync'],
            'not acknowledged, still out'
        );
        self::assertSame(0, ResultSyncAcknowledgement::markSynced($db, 'form_vl', 'vl_sample_id', []));

        // The next run starts by putting what the last one left in flight back to pending.
        self::assertSame(1, ResultSyncAcknowledgement::releaseInFlight($db, 'form_vl'));
        self::assertSame(0, (int) self::syncState($db)[4]['data_sync']);
    }

    #[RunInSeparateProcess]
    public function testAResendOfRowsAlreadySyncedCountsThemAsMarked(): void
    {
        $this->requireDatabase();
        $db = LegacyAppHarness::boot('intelis_result_sync_resend_test_' . getmypid(), ['r_sample_status', 'form_vl']);
        $this->booted = true;
        $db->rawQuery("INSERT INTO r_sample_status (status_id, status_name) VALUES (7, 'Accepted')");

        // A days or since-date resend reads rows that are already synced.
        $sent = [];
        foreach ([1 => 'u-1', 2 => 'u-2', 3 => 'u-3'] as $id => $uniqueId) {
            $row = ['unique_id' => $uniqueId, 'sample_code' => "S-$id"];
            $db->insert('form_vl', [
                'vl_sample_id' => $id, 'data_sync' => 1, 'result_sent_to_source' => 'sent',
                'vlsm_instance_id' => 'test', 'result_status' => 7,
            ] + $row);
            $sent[] = ['vl_sample_id' => $id] + $row;
        }
        ResultSyncAcknowledgement::markInFlight($db, 'form_vl', 'vl_sample_id', [1, 2, 3]);

        $acknowledged = ResultSyncAcknowledgement::acknowledgedRows($sent, ['u-1', 'u-2', 'u-3'], true);

        self::assertSame(3, ResultSyncAcknowledgement::markSynced($db, 'form_vl', 'vl_sample_id', $acknowledged));
        self::assertSame(['1', '1', '1'], array_map('strval', array_column(self::syncState($db), 'data_sync')));
    }

    #[RunInSeparateProcess]
    public function testTheStsAnswersWithTheLabsIdentifiersOnlyWhenAsked(): void
    {
        $this->requireDatabase();
        // getTableFieldsAsArray() reads columns from SYSTEM_CONFIG's database.
        if (!defined('SYSTEM_CONFIG')) {
            define('SYSTEM_CONFIG', [
                'database' => ['db' => 'intelis_result_sync_ack_sts_test_' . getmypid()],
                'modules' => ['vl' => true],
            ]);
        }
        $db = LegacyAppHarness::boot('intelis_result_sync_ack_sts_test_' . getmypid(), [
            'system_config', 'global_config', 's_vlsm_instance', 'r_sample_status', 'form_vl', 'roles', 'user_details',
            'r_vl_sample_rejection_reasons', 'facility_details',
        ]);
        $this->booted = true;
        $db->rawQuery("INSERT INTO r_sample_status (status_id, status_name) VALUES (7, 'Accepted')");
        /** @var ResultsService $sts */
        $sts = ContainerRegistry::get(ResultsService::class);

        $request = static fn(string $uniqueId, string $code): array => [
            'labId' => 7,
            'testType' => 'vl',
            'results' => [[
                'unique_id' => $uniqueId, 'sample_code' => $code, 'lab_id' => 7, 'facility_id' => 1,
                'vlsm_instance_id' => 'lab-instance', 'result' => '40', 'result_status' => 7,
            ]],
        ];

        // The STS only updates records it already holds (requests it sent to the lab).
        foreach (['u-1' => 'S-1', 'u-2' => 'S-2', 'u-3' => 'S-3'] as $uniqueId => $code) {
            $db->insert('form_vl', [
                'unique_id' => $uniqueId, 'sample_code' => $code, 'lab_id' => 7, 'facility_id' => 1,
                'vlsm_instance_id' => 'sts-instance', 'result_status' => 7,
            ]);
        }
        // Default: the plain list of saved sample codes older labs expect.
        self::assertSame(['S-1'], $sts->receiveResults('vl', $request('u-1', 'S-1')));
        // Asked: the lab's own unique_id.
        self::assertSame(['u-2'], $sts->receiveResults('vl', $request('u-2', 'S-2'), false, true));
        // Legacy JSON-string callers still work.
        self::assertSame(['S-3'], $sts->receiveResults('vl', json_encode($request('u-3', 'S-3'))));
        self::assertSame(
            ['40', '40', '40'],
            array_column($db->rawQuery('SELECT result FROM form_vl ORDER BY unique_id'), 'result')
        );

        // Requests created on the STS carry no lab code until the lab assigns one.
        // Another instance already holds DUP for this lab, so the STS keeps the
        // arriving sample under a numbered variant of the code.
        foreach (['u-other' => 'DUP', 'u-8' => null, 'u-9' => null] as $uniqueId => $code) {
            $db->insert('form_vl', [
                'unique_id' => $uniqueId, 'sample_code' => $code, 'lab_id' => 7, 'facility_id' => 1,
                'vlsm_instance_id' => 'sts-instance', 'result_status' => 7,
            ]);
        }
        $legacy = $sts->receiveResults('vl', $request('u-8', 'DUP'));
        self::assertCount(1, $legacy);
        self::assertNotSame('DUP', $legacy[0], 'the legacy ack names the variant, which the lab never sent');
        self::assertStringStartsWith('DUP', $legacy[0]);
        // With unique_id acks the lab gets back the identifier it sent.
        self::assertSame(['u-9'], $sts->receiveResults('vl', $request('u-9', 'DUP'), false, true));
        self::assertSame(
            'DUP',
            ($db->rawQueryOne("SELECT sample_code FROM form_vl WHERE unique_id = 'u-other'") ?? [])['sample_code']
                ?? null,
            'the sample already holding the code keeps it'
        );
    }
}
