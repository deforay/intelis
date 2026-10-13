<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Registries\ContainerRegistry;
use App\Services\ManifestMoveService;
use App\Services\SampleWithdrawalService;
use mysqli;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyAppHarness;

/**
 * Move Manifest on the STS, and the old lab cancelling its copies.
 *
 * The rule: a manifest moves whole, with every sample on it, unless a lab has
 * tested any of them; a moved sample arrives at the new lab as if just sent
 * there; the lab it was taken from is asked to cancel its copy, and keeps any
 * it has tested since. Rows are read back through a second connection, so a
 * transaction left open shows up as missing data.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class ManifestMoveTest extends TestCase
{
    private const OLD_LAB = 5;
    private const NEW_LAB = 7;
    private const OTHER_LAB = 6;

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
        $this->database = 'intelis_manifest_move_test_' . getmypid();
        if (!defined('SYSTEM_CONFIG')) {
            define('SYSTEM_CONFIG', [
                'database' => ['db' => $this->database],
                'modules' => ['vl' => true, 'cd4' => true, 'tb' => true],
            ]);
        }
        $db = LegacyAppHarness::boot($this->database, [
            'system_config', 'global_config', 's_vlsm_instance', 'r_sample_status', 'form_vl', 'form_cd4',
            'form_tb', 'tb_tests', 'specimen_manifests', 's_lis_remote_commands', 'facility_details',
            'move_samples', 'move_samples_map',
        ]);
        $this->booted = true;
        $db->rawQuery(
            "INSERT INTO r_sample_status (status_id, status_name) VALUES
                (4, 'Rejected'), (5, 'Failed'), (6, 'Registered'), (7, 'Accepted'), (8, 'Awaiting Approval'),
                (9, 'Registered at clinic'), (12, 'Cancelled')"
        );
        foreach ([self::OLD_LAB, self::NEW_LAB, self::OTHER_LAB] as $lab) {
            $db->insert('facility_details', [
                'facility_id' => $lab, 'facility_name' => "Lab $lab", 'facility_type' => 2,
            ]);
        }
        return $db;
    }

    private static function moves(): ManifestMoveService
    {
        return ContainerRegistry::get(ManifestMoveService::class);
    }

    /** The lab's courier has said it handles withdraw-samples. */
    private static function canWithdraw($db, int $labId): void
    {
        $db->rawQuery(
            "UPDATE facility_details SET facility_attributes = JSON_OBJECT(
                'capabilities', JSON_OBJECT('commandPlane', true, 'supports', JSON_ARRAY('ping', 'withdraw-samples')),
                'capabilitiesSeenAt', ?) WHERE facility_id = ?",
            [date('c'), $labId]
        );
    }

    private static function manifest($db, string $code, array $extra = []): int
    {
        $db->insert('specimen_manifests', $extra + [
            'manifest_code' => $code, 'module' => 'vl', 'lab_id' => self::OLD_LAB, 'manifest_type' => 'collection',
            // Recent: the manifest list shows the last 6 months by default.
            'manifest_status' => 'pending', 'request_created_datetime' => date('Y-m-d H:i:s', strtotime('-10 days')),
        ]);
        return (int) $db->getInsertId();
    }

    private static function sample($db, int $manifestId, string $code, string $uniqueId, array $extra = []): void
    {
        $db->insert('form_vl', $extra + [
            'unique_id' => $uniqueId, 'remote_sample_code' => "R-$uniqueId", 'facility_id' => 1,
            'lab_id' => self::OLD_LAB, 'sample_package_id' => $manifestId, 'sample_package_code' => $code,
            'result_status' => 9, 'vlsm_instance_id' => 'sts', 'data_sync' => 1,
            'last_modified_datetime' => '2026-08-05 19:10:00',
        ]);
    }

    /** @return array<string, array<string, mixed>> keyed by $key */
    private function committed(string $table, string $key = 'unique_id'): array
    {
        $mysqli = new mysqli(
            (string) getenv('INTELIS_TEST_DB_HOST'),
            (string) getenv('INTELIS_TEST_DB_USER'),
            (string) (getenv('INTELIS_TEST_DB_PASS') ?: ''),
            $this->database,
            (int) (getenv('INTELIS_TEST_DB_PORT') ?: 3306)
        );
        $rows = $mysqli->query("SELECT * FROM `$table`")->fetch_all(MYSQLI_ASSOC);
        $mysqli->close();
        return array_column($rows, null, $key);
    }

    #[RunInSeparateProcess]
    public function testAManifestMovesWithEverySampleAsIfJustSentToTheNewLab(): void
    {
        $db = $this->boot();
        $id = self::manifest($db, 'M-1', ['manifest_status' => 'received']);
        // Received and coded at the old lab, and one not yet received there.
        self::sample($db, $id, 'M-1', 'u-1', [
            'result_status' => 6, 'sample_code' => 'OLD-1', 'sample_code_key' => 1, 'sample_code_format' => 'MMYY',
            'sample_received_at_lab_datetime' => '2026-08-06 10:00:00',
        ]);
        self::sample($db, $id, 'M-1', 'u-2');

        $outcome = self::moves()->move('vl', ['M-1'], self::NEW_LAB, 'Wrong lab', 'user-1', null, true);

        self::assertSame(['M-1' => 2], $outcome['moved']);
        self::assertSame([], $outcome['refused']);
        $rows = $this->committed('form_vl');
        foreach (['u-1', 'u-2'] as $u) {
            self::assertSame((string) self::NEW_LAB, (string) $rows[$u]['lab_id']);
            self::assertSame((string) self::OLD_LAB, (string) $rows[$u]['referring_lab_id'], 'the lab it came from');
            self::assertNull($rows[$u]['sample_code'], 'a code from the old lab series does not follow it');
            self::assertNull($rows[$u]['sample_received_at_lab_datetime']);
            self::assertSame('9', (string) $rows[$u]['result_status'], 'not yet received at the new lab');
            self::assertSame('0', (string) $rows[$u]['data_sync'], 'the new lab has to pull it');
        }
        self::assertNull($rows['u-1']['sample_code_key']);
        $manifest = $this->committed('specimen_manifests', 'manifest_code')['M-1'];
        self::assertSame((string) self::NEW_LAB, (string) $manifest['lab_id']);
        self::assertSame('dispatch', $manifest['manifest_status'], 'on its way to the new lab');
        $history = json_decode((string) $manifest['manifest_change_history'], true);
        self::assertSame(['Wrong lab', self::OLD_LAB, self::NEW_LAB], [
            $history[0]['reason'], $history[0]['fromLabId'], $history[0]['toLabId'],
        ]);
        self::assertFalse($db->isTransactionActive());
    }

    #[RunInSeparateProcess]
    public function testASampleOnTheManifestByItsCodeAloneMovesAndBlocksToo(): void
    {
        $db = $this->boot();
        $id = self::manifest($db, 'M-1');
        self::sample($db, $id, 'M-1', 'by-id');
        self::sample($db, 0, 'M-1', 'by-code', ['sample_package_id' => null]);
        $tested = self::manifest($db, 'M-2');
        self::sample($db, $tested, 'M-2', 'open');
        self::sample($db, 0, 'M-2', 'tested-by-code', ['sample_package_id' => null, 'result' => '40']);

        $outcome = self::moves()->move('vl', ['M-1', 'M-2'], self::NEW_LAB, 'Wrong lab', 'user-1');

        self::assertSame(['M-1' => 2], $outcome['moved']);
        self::assertSame(['M-2' => 'tested'], $outcome['refused']);
        $row = $this->committed('form_vl')['by-code'];
        self::assertSame((string) self::NEW_LAB, (string) $row['lab_id']);
        self::assertSame((string) $id, (string) $row['sample_package_id'], 'linked by id from now on');
        self::assertNull(
            $this->committed('form_vl')['tested-by-code']['sample_package_id'],
            'a manifest that is not moved is not touched'
        );
    }

    #[RunInSeparateProcess]
    public function testAReceivedManifestMovesOnlyWhenConfirmedAsSentOn(): void
    {
        $db = $this->boot();
        $received = self::manifest($db, 'M-received', ['manifest_status' => 'received']);
        self::sample($db, $received, 'M-received', 'r-1', ['result_status' => 6]);
        $dispatched = self::manifest($db, 'M-sent', ['manifest_status' => 'dispatch']);
        self::sample($db, $dispatched, 'M-sent', 's-1');

        $outcome = self::moves()->move('vl', ['M-received', 'M-sent'], self::NEW_LAB, 'Wrong lab', 'user-1');

        self::assertSame(['M-sent' => 1], $outcome['moved']);
        self::assertSame(['M-received' => 'received'], $outcome['refused']);
        self::assertSame((string) self::OLD_LAB, (string) $this->committed('form_vl')['r-1']['lab_id']);

        $confirmed = self::moves()->move('vl', ['M-received'], self::NEW_LAB, 'Sent on', 'user-1', null, true);
        self::assertSame(['M-received' => 1], $confirmed['moved']);
    }

    #[RunInSeparateProcess]
    public function testAManifestWithAnyTestedSampleIsNotMovedAtAll(): void
    {
        $db = $this->boot();
        // One decided sample per manifest, each decided a different way.
        $decided = [
            'M-result' => ['result' => '40'],
            'M-date' => ['sample_tested_datetime' => '2026-08-07 09:00:00'],
            'M-rejected' => ['result_status' => 4],
            'M-failed' => ['result_status' => 5],
            'M-approval' => ['result_status' => 8],
        ];
        foreach ($decided as $code => $values) {
            $id = self::manifest($db, $code);
            self::sample($db, $id, $code, "$code-a", ['sample_code' => "C-$code"]);
            self::sample($db, $id, $code, "$code-b", $values);
        }

        $outcome = self::moves()->move('vl', array_keys($decided), self::NEW_LAB, 'Wrong lab', 'user-1');

        self::assertSame([], $outcome['moved']);
        self::assertSame(array_fill_keys(array_keys($decided), 'tested'), $outcome['refused']);
        foreach ($this->committed('form_vl') as $row) {
            self::assertSame((string) self::OLD_LAB, (string) $row['lab_id']);
        }
        self::assertSame('C-M-result', $this->committed('form_vl')['M-result-a']['sample_code'], 'untouched');
        foreach ($this->committed('specimen_manifests', 'manifest_code') as $manifest) {
            self::assertSame((string) self::OLD_LAB, (string) $manifest['lab_id']);
        }
    }

    #[RunInSeparateProcess]
    public function testOnlyTheManifestWithATestedSampleStaysBehind(): void
    {
        $db = $this->boot();
        $ok = self::manifest($db, 'M-ok');
        self::sample($db, $ok, 'M-ok', 'ok-1');
        $tested = self::manifest($db, 'M-tested');
        self::sample($db, $tested, 'M-tested', 't-1', ['result' => 'Target Not Detected', 'result_status' => 7]);

        $outcome = self::moves()->move('vl', ['M-ok', 'M-tested'], self::NEW_LAB, 'Wrong lab', 'user-1');

        self::assertSame(['M-ok' => 1], $outcome['moved']);
        self::assertSame(['M-tested' => 'tested'], $outcome['refused']);
        $rows = $this->committed('form_vl');
        self::assertSame((string) self::NEW_LAB, (string) $rows['ok-1']['lab_id']);
        self::assertSame((string) self::OLD_LAB, (string) $rows['t-1']['lab_id']);
    }

    #[RunInSeparateProcess]
    public function testACd4ResultCountsAsTested(): void
    {
        $db = $this->boot();
        $db->insert('specimen_manifests', [
            'manifest_code' => 'C-1', 'module' => 'cd4', 'lab_id' => self::OLD_LAB, 'manifest_type' => 'collection',
        ]);
        $id = (int) $db->getInsertId();
        $db->insert('form_cd4', [
            'unique_id' => 'c-1', 'facility_id' => 1, 'lab_id' => self::OLD_LAB, 'sample_package_id' => $id,
            'sample_package_code' => 'C-1', 'result_status' => 6, 'cd4_result' => '350', 'vlsm_instance_id' => 'sts',
        ]);

        $outcome = self::moves()->move('cd4', ['C-1'], self::NEW_LAB, 'Wrong lab', 'user-1');

        self::assertSame(['C-1' => 'tested'], $outcome['refused']);
    }

    #[RunInSeparateProcess]
    public function testEachLabTheSamplesCameFromIsAskedToCancelItsCopies(): void
    {
        $db = $this->boot();
        self::canWithdraw($db, self::OLD_LAB);
        $id = self::manifest($db, 'M-1');
        self::sample($db, $id, 'M-1', 'u-1');
        self::sample($db, $id, 'M-1', 'u-2');
        // A sample on the manifest held by a lab whose LIS cannot be told.
        self::sample($db, $id, 'M-1', 'u-3', ['lab_id' => self::OTHER_LAB]);

        $outcome = self::moves()->move('vl', ['M-1'], self::NEW_LAB, 'Wrong lab', 'user-1');

        $byLab = array_column($outcome['withdrawals'], null, 'labId');
        self::assertSame(2, $byLab[self::OLD_LAB]['samples']);
        self::assertNotNull($byLab[self::OLD_LAB]['commandId']);
        self::assertNull($byLab[self::OTHER_LAB]['commandId'], 'reported, so it is cancelled there by hand');
        $commands = array_values($this->committed('s_lis_remote_commands', 'command_id'));
        self::assertCount(1, $commands);
        self::assertSame((string) self::OLD_LAB, (string) $commands[0]['lab_id']);
        self::assertSame('withdraw-samples', $commands[0]['command']);
        self::assertSame('pending', $commands[0]['status']);
        $params = json_decode((string) $commands[0]['params'], true);
        self::assertSame(
            ['vl', self::NEW_LAB, ['M-1']],
            [$params['testType'], $params['movedToLabId'], $params['manifestCodes']]
        );
        self::assertEqualsCanonicalizing(['u-1', 'u-2'], $params['uniqueIds']);
    }

    #[RunInSeparateProcess]
    public function testMovingSamplesBackDropsTheWithdrawalTheLabHasNotPickedUp(): void
    {
        $db = $this->boot();
        self::canWithdraw($db, self::OLD_LAB);
        foreach (['M-1' => 'u-1', 'M-2' => 'u-2', 'M-3' => 'u-3'] as $code => $uniqueId) {
            self::sample($db, self::manifest($db, $code), $code, $uniqueId);
        }
        self::moves()->move('vl', ['M-1', 'M-2'], self::NEW_LAB, 'Wrong lab', 'user-1');
        self::moves()->move('vl', ['M-3'], self::NEW_LAB, 'Wrong lab', 'user-1');

        // Moved back before the old lab polled: it must not cancel them now.
        self::moves()->move('vl', ['M-1', 'M-3'], self::OLD_LAB, 'Back again', 'user-1');

        $commands = $this->committed('s_lis_remote_commands', 'command_id');
        $byIds = [];
        foreach ($commands as $command) {
            $byIds[implode(',', json_decode((string) $command['params'], true)['uniqueIds'])] = $command['status'];
        }
        self::assertSame(['u-2' => 'pending', 'u-3' => 'cancelled'], $byIds);
    }

    #[RunInSeparateProcess]
    public function testMovingBackLeavesAnotherTestTypesWithdrawalAlone(): void
    {
        $db = $this->boot();
        // An EID withdrawal pending at the old lab for a sample whose unique id
        // a VL sample happens to share.
        $db->insert('s_lis_remote_commands', [
            'command_id' => 'eid-cmd', 'lab_id' => self::OLD_LAB, 'command' => 'withdraw-samples',
            'params' => json_encode(['testType' => 'eid', 'uniqueIds' => ['u-1']]),
            'status' => 'pending', 'nonce' => 'n-1',
        ]);
        $id = self::manifest($db, 'M-1', ['lab_id' => self::NEW_LAB]);
        self::sample($db, $id, 'M-1', 'u-1', ['lab_id' => self::NEW_LAB]);

        self::moves()->move('vl', ['M-1'], self::OLD_LAB, 'Back again', 'user-1');

        self::assertSame('pending', $this->committed('s_lis_remote_commands', 'command_id')['eid-cmd']['status']);
    }

    #[RunInSeparateProcess]
    public function testNothingIsMovedOrQueuedWhenTheMoveFailsPartWay(): void
    {
        $db = $this->boot();
        self::canWithdraw($db, self::OLD_LAB);
        $id = self::manifest($db, 'M-1');
        self::sample($db, $id, 'M-1', 'u-1');
        // The command cannot be stored, after the samples were moved.
        $db->rawQuery(
            "ALTER TABLE s_lis_remote_commands ADD CONSTRAINT no_withdraw CHECK (command <> 'withdraw-samples')"
        );

        try {
            self::moves()->move('vl', ['M-1'], self::NEW_LAB, 'Wrong lab', 'user-1');
            self::fail('expected the move to fail');
        } catch (\Throwable) {
        }

        self::assertSame((string) self::OLD_LAB, (string) $this->committed('form_vl')['u-1']['lab_id']);
        $manifest = $this->committed('specimen_manifests', 'manifest_code')['M-1'];
        self::assertSame((string) self::OLD_LAB, (string) $manifest['lab_id']);
        self::assertFalse($db->isTransactionActive());
    }

    #[RunInSeparateProcess]
    public function testReferralManifestsAndManifestsAlreadyThereAreNotMoved(): void
    {
        $db = $this->boot();
        $referral = self::manifest($db, 'R-1', ['manifest_type' => 'referral']);
        self::sample($db, $referral, 'R-1', 'r-1');
        $there = self::manifest($db, 'T-1', ['lab_id' => self::NEW_LAB]);
        self::sample($db, $there, 'T-1', 't-1', ['lab_id' => self::NEW_LAB, 'sample_code' => 'NEW-1']);

        $outcome = self::moves()->move('vl', ['R-1', 'T-1', 'NOPE'], self::NEW_LAB, 'Wrong lab', 'user-1');

        self::assertSame(['R-1' => 'referral', 'T-1' => 'already-there', 'NOPE' => 'not-found'], $outcome['refused']);
        self::assertSame('NEW-1', $this->committed('form_vl')['t-1']['sample_code']);
    }

    #[RunInSeparateProcess]
    public function testALabsManifestsAreListedFromTheManifestsAlone(): void
    {
        $db = $this->boot();
        $recent = date('Y-m-d H:i:s', strtotime('-1 month'));
        self::manifest($db, 'M-a', ['request_created_datetime' => $recent, 'number_of_samples' => 2]);
        self::manifest($db, 'M-b', ['request_created_datetime' => date('Y-m-d H:i:s', strtotime('-2 days'))]);
        // Another lab's manifest, this lab's referral manifest, and one older
        // than the default window.
        self::manifest($db, 'M-c', ['lab_id' => self::OTHER_LAB, 'request_created_datetime' => $recent]);
        self::manifest($db, 'M-r', ['manifest_type' => 'referral', 'request_created_datetime' => $recent]);
        self::manifest($db, 'M-old', ['request_created_datetime' => '2020-01-01 10:00:00']);

        $listed = self::moves()->listForLab('vl', self::OLD_LAB);

        self::assertSame(['M-b', 'M-a'], array_column($listed, 'code'));
        self::assertSame(2, $listed[1]['samples']);
        self::assertSame('Lab ' . self::OLD_LAB, $listed[0]['lab'], 'shown to double-check');
        self::assertSame(
            ['M-old'],
            array_column(
                self::moves()->listForLab('vl', self::OLD_LAB, '2020-01-01 00:00:00', '2020-01-01 23:59:59'),
                'code'
            )
        );
    }

    #[RunInSeparateProcess]
    public function testTheOldLabCancelsItsCopiesButKeepsWhatItHasTested(): void
    {
        $db = $this->boot();
        // The old lab's own copies, as its LIS holds them.
        self::sample($db, 0, 'M-1', 'open', ['sample_code' => 'OLD-1', 'result_status' => 6]);
        self::sample($db, 0, 'M-1', 'tested', ['sample_code' => 'OLD-2', 'result' => '40', 'result_status' => 7]);
        self::sample($db, 0, 'M-1', 'gone', ['result_status' => 12]);

        $outcome = ContainerRegistry::get(SampleWithdrawalService::class)
            ->withdraw('vl', ['open', 'tested', 'gone', 'never-here'], self::NEW_LAB);

        self::assertSame(
            [
                'cancelled' => ['open'], 'tested' => ['tested'],
                'alreadyCancelled' => ['gone'], 'notFound' => ['never-here'], 'failed' => [],
            ],
            $outcome
        );
        $rows = $this->committed('form_vl');
        self::assertSame('12', (string) $rows['open']['result_status']);
        self::assertSame((string) self::OLD_LAB, (string) $rows['open']['lab_id'], 'keeps its lab and code');
        self::assertSame('OLD-1', $rows['open']['sample_code']);
        self::assertSame('1', (string) $rows['open']['data_sync'], 'not sent to the STS as cancelled');
        self::assertSame(self::NEW_LAB, json_decode((string) $rows['open']['form_attributes'], true)['movedToLabId']);
        self::assertSame('7', (string) $rows['tested']['result_status'], 'the result is this lab\'s');
        self::assertSame((string) self::OLD_LAB, (string) $rows['tested']['lab_id']);
        self::assertFalse($db->isTransactionActive());
    }

    #[RunInSeparateProcess]
    public function testOneSampleThatCannotBeCancelledDoesNotLeaveTheRestLive(): void
    {
        $db = $this->boot();
        self::sample($db, 0, 'M-1', 'a');
        self::sample($db, 0, 'M-1', 'b');
        self::sample($db, 0, 'M-1', 'c');
        $db->rawQuery(
            "ALTER TABLE form_vl ADD CONSTRAINT no_cancel_b CHECK (NOT (unique_id = 'b' AND result_status = 12))"
        );

        $outcome = ContainerRegistry::get(SampleWithdrawalService::class)
            ->withdraw('vl', ['a', 'b', 'c'], self::NEW_LAB);

        self::assertSame(['a', 'c'], $outcome['cancelled']);
        self::assertSame(['b'], $outcome['failed']);
        self::assertSame('12', (string) $this->committed('form_vl')['c']['result_status']);
        self::assertFalse($db->isTransactionActive());
    }

    #[RunInSeparateProcess]
    public function testATbPreliminaryResultOrTestRowCountsAsTested(): void
    {
        $db = $this->boot();
        foreach (['T-xpert' => 't-xpert', 'T-row' => 't-row', 'T-open' => 't-open'] as $code => $uniqueId) {
            $db->insert('specimen_manifests', [
                'manifest_code' => $code, 'module' => 'tb', 'lab_id' => self::OLD_LAB, 'manifest_type' => 'collection',
            ]);
            $db->insert('form_tb', [
                'unique_id' => $uniqueId, 'facility_id' => 1, 'lab_id' => self::OLD_LAB,
                'sample_package_id' => (int) $db->getInsertId(), 'sample_package_code' => $code,
                'result_status' => 6, 'vlsm_instance_id' => 'sts',
                'xpert_mtb_result' => $uniqueId === 't-xpert' ? 'MTB DETECTED' : null,
            ]);
            $tbId = (int) $db->getInsertId();
            // A test row the request created, with no result yet, is not a test.
            $db->insert('tb_tests', ['tb_id' => $tbId, 'test_result' => $uniqueId === 't-row' ? 'Positive' : null]);
        }

        $outcome = self::moves()->move('tb', ['T-xpert', 'T-row', 'T-open'], self::NEW_LAB, 'Wrong lab', 'user-1');

        self::assertSame(['T-open' => 1], $outcome['moved']);
        self::assertSame(['T-xpert' => 'tested', 'T-row' => 'tested'], $outcome['refused']);
    }

    #[RunInSeparateProcess]
    public function testAUserLimitedToSomeFacilitiesOnlySeesAndMovesTheirManifests(): void
    {
        $db = $this->boot();
        $mine = self::manifest($db, 'M-mine');
        self::sample($db, $mine, 'M-mine', 'm-1', ['facility_id' => 1]);
        $mixed = self::manifest($db, 'M-mixed');
        self::sample($db, $mixed, 'M-mixed', 'x-1', ['facility_id' => 1]);
        self::sample($db, $mixed, 'M-mixed', 'x-2', ['facility_id' => 2]);
        // Another facility's sample on the manifest by its code alone.
        $byCode = self::manifest($db, 'M-code');
        self::sample($db, $byCode, 'M-code', 'c-1', ['facility_id' => 1]);
        self::sample($db, 0, 'M-code', 'c-2', ['facility_id' => 2, 'sample_package_id' => null]);

        $listed = self::moves()->listForLab('vl', self::OLD_LAB, null, null, '1');
        self::assertSame(['M-mine'], array_column($listed, 'code'));
        $outcome = self::moves()->move('vl', ['M-mine', 'M-mixed'], self::NEW_LAB, 'Wrong lab', 'user-1', '1');

        self::assertSame(['M-mine' => 1], $outcome['moved']);
        self::assertSame(['M-mixed' => 'not-found'], $outcome['refused']);
    }

    #[RunInSeparateProcess]
    public function testSamplesAPushGaveBackToTheOldLabAreHealed(): void
    {
        $db = $this->boot();
        $moved = ['manifest_change_history' => '[{"reason": "Wrong lab"}]', 'lab_id' => self::NEW_LAB];
        $id = self::manifest($db, 'M-1', $moved);
        $sent = ['samples_referred_datetime' => '2026-08-11 14:37:21', 'result_status' => 6];
        // Given back to the old lab by the new lab's push, with the new lab's code.
        self::sample($db, $id, 'M-1', 'back', $sent + ['sample_code' => 'NEW-1']);
        self::sample($db, $id, 'M-1', 'clash', $sent + ['sample_code' => 'NEW-9']);
        self::sample($db, $id, 'M-1', 'stayed', $sent + ['lab_id' => self::NEW_LAB, 'sample_code' => 'NEW-9']);
        // Left alone: tested at the old lab, never marked moved, cancelled.
        self::sample($db, $id, 'M-1', 'tested', ['result' => '40', 'result_status' => 7] + $sent);
        self::sample($db, $id, 'M-1', 'unmoved', ['result_status' => 6]);
        self::sample($db, $id, 'M-1', 'cancelled', ['result_status' => 12] + $sent);
        // Moved on its own with Move Samples: afterwards, and long before.
        self::sample($db, $id, 'M-1', 'moved-alone', ['samples_referred_datetime' => '2026-08-20 09:00:00'] + $sent);
        self::sample($db, $id, 'M-1', 'moved-before', $sent);
        foreach (['moved-alone' => '2026-08-20', 'moved-before' => '2026-03-01'] as $u => $on) {
            $db->insert('move_samples', ['moved_on' => $on, 'test_type' => 'vl']);
            $sample = $db->rawQueryOne('SELECT vl_sample_id FROM form_vl WHERE unique_id = ?', [$u]);
            $db->insert('move_samples_map', [
                'move_sample_id' => (int) $db->rawQueryOne('SELECT MAX(move_sample_id) AS id FROM move_samples')['id'],
                'test_type' => 'vl', 'test_type_sample_id' => (int) $sample['vl_sample_id'],
            ]);
        }
        // On the manifest by its code only.
        self::sample($db, 0, 'M-1', 'by-code', $sent + ['sample_package_id' => null]);
        // A manifest never moved.
        $other = self::manifest($db, 'M-2', ['lab_id' => self::NEW_LAB]);
        self::sample($db, $other, 'M-2', 'never', $sent);

        $healed = self::moves()->healRevertedMoves();

        self::assertSame(['form_vl' => 4], $healed);
        $rows = $this->committed('form_vl');
        foreach (['back', 'clash', 'by-code', 'moved-before'] as $u) {
            self::assertSame((string) self::NEW_LAB, (string) $rows[$u]['lab_id'], $u);
            self::assertSame((string) self::OLD_LAB, (string) $rows[$u]['referring_lab_id'], $u);
            self::assertSame('0', (string) $rows[$u]['data_sync'], 'sent to the new lab again');
        }
        self::assertSame('NEW-1', $rows['back']['sample_code'], 'the new lab gave it');
        self::assertNull($rows['clash']['sample_code'], 'the new lab uses it for another sample');
        foreach (['tested', 'unmoved', 'cancelled', 'never', 'moved-alone'] as $u) {
            self::assertSame((string) self::OLD_LAB, (string) $rows[$u]['lab_id'], $u);
        }
        self::assertSame([], self::moves()->healRevertedMoves(), 'nothing left on a second run');
    }

    #[RunInSeparateProcess]
    public function testASampleTheNewLabCannotTakeIsLeftAndTheRestAreHealed(): void
    {
        $db = $this->boot();
        $id = self::manifest($db, 'M-1', ['manifest_change_history' => '[{}]', 'lab_id' => self::NEW_LAB]);
        $sent = ['samples_referred_datetime' => '2026-08-11 14:37:21', 'result_status' => 6];
        // Its mobile-app code is already used at the new lab.
        self::sample($db, $id, 'M-1', 'app-clash', $sent + ['app_sample_code' => 'APP-1']);
        self::sample($db, 0, 'X-1', 'there', ['lab_id' => self::NEW_LAB, 'app_sample_code' => 'APP-1']);
        self::sample($db, $id, 'M-1', 'fine', $sent);

        $healed = self::moves()->healRevertedMoves();

        self::assertSame(['form_vl' => 1], $healed);
        $rows = $this->committed('form_vl');
        self::assertSame((string) self::OLD_LAB, (string) $rows['app-clash']['lab_id']);
        self::assertSame('APP-1', $rows['app-clash']['app_sample_code'], 'not discarded');
        self::assertSame((string) self::NEW_LAB, (string) $rows['fine']['lab_id']);
    }

    #[RunInSeparateProcess]
    public function testAnUnexpectedFailureStopsTheHealSoItRunsAgain(): void
    {
        $db = $this->boot();
        $id = self::manifest($db, 'M-1', ['manifest_change_history' => '[{}]', 'lab_id' => self::NEW_LAB]);
        self::sample($db, $id, 'M-1', 'u-1', [
            'samples_referred_datetime' => '2026-08-11 14:37:21', 'result_status' => 6,
        ]);
        $db->rawQuery('ALTER TABLE form_vl ADD CONSTRAINT not_now CHECK (lab_id <> ' . self::NEW_LAB . ')');

        $this->expectException(\Throwable::class);
        self::moves()->healRevertedMoves();
    }
}
