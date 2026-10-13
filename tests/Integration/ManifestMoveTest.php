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
                'modules' => ['vl' => true, 'cd4' => true],
            ]);
        }
        $db = LegacyAppHarness::boot($this->database, [
            'system_config', 'global_config', 's_vlsm_instance', 'r_sample_status', 'form_vl', 'form_cd4',
            'specimen_manifests', 's_lis_remote_commands', 'facility_details',
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
            'manifest_status' => 'pending', 'request_created_datetime' => '2026-08-05 19:10:00',
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

        $outcome = self::moves()->move('vl', ['M-1'], self::NEW_LAB, 'Wrong lab', 'user-1');

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
    public function testALabsManifestsAreListedWithTheirTestedSamples(): void
    {
        $db = $this->boot();
        $a = self::manifest($db, 'M-a', ['request_created_datetime' => '2026-08-01 10:00:00']);
        self::sample($db, $a, 'M-a', 'a-1');
        self::sample($db, $a, 'M-a', 'a-2', ['result' => '40', 'result_status' => 7]);
        // The manifest names another lab, but its samples are at this one.
        $b = self::manifest($db, 'M-b', [
            'lab_id' => self::OTHER_LAB, 'request_created_datetime' => '2026-08-02 10:00:00',
        ]);
        self::sample($db, $b, 'M-b', 'b-1');
        // Another lab's manifest, and this lab's referral manifest.
        $c = self::manifest($db, 'M-c', ['lab_id' => self::OTHER_LAB]);
        self::sample($db, $c, 'M-c', 'c-1', ['lab_id' => self::OTHER_LAB]);
        $r = self::manifest($db, 'M-r', ['manifest_type' => 'referral']);
        self::sample($db, $r, 'M-r', 'r-1');

        $listed = self::moves()->listForLab('vl', self::OLD_LAB);

        self::assertSame(
            [['M-b', 1, 0], ['M-a', 2, 1]],
            array_map(static fn(array $m): array => [$m['code'], $m['samples'], $m['tested']], $listed)
        );
        self::assertSame(
            ['M-a'],
            array_column(
                self::moves()->listForLab('vl', self::OLD_LAB, '2026-08-01 00:00:00', '2026-08-01 23:59:59'),
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
                'alreadyCancelled' => ['gone'], 'notFound' => ['never-here'],
            ],
            $outcome
        );
        $rows = $this->committed('form_vl');
        self::assertSame('12', (string) $rows['open']['result_status']);
        self::assertSame(
            (string) self::NEW_LAB,
            (string) $rows['open']['lab_id'],
            'so a later push cannot claim it back'
        );
        self::assertSame('1', (string) $rows['open']['data_sync'], 'not sent to the STS as cancelled');
        self::assertSame(self::NEW_LAB, json_decode((string) $rows['open']['form_attributes'], true)['movedToLabId']);
        self::assertSame('7', (string) $rows['tested']['result_status'], 'the result is this lab\'s');
        self::assertSame((string) self::OLD_LAB, (string) $rows['tested']['lab_id']);
        self::assertFalse($db->isTransactionActive());
    }
}
