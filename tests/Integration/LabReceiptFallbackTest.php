<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\AuditTriggerService;
use App\Services\DatabaseService;
use App\Services\LabReceiptService;
use mysqli;
use PHPUnit\Framework\TestCase;

use const SAMPLE_STATUS\ACCEPTED;
use const SAMPLE_STATUS\CANCELLED;
use const SAMPLE_STATUS\EXPIRED;
use const SAMPLE_STATUS\LOST_OR_MISSING;
use const SAMPLE_STATUS\RECEIVED_AT_CLINIC;
use const SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
use const SAMPLE_STATUS\REJECTED;

/**
 * A sample that reached a lab with no reception date is given its collection
 * date, against a real server.
 *
 * The rule is applied in two places -- the BEFORE triggers on every write, and
 * the sweep over stored rows -- and both are pinned here against the same cases,
 * so the two cannot come to disagree about which samples count as received.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class LabReceiptFallbackTest extends TestCase
{
    private const DATABASE = 'intelis_lab_receipt_test';

    private const COLLECTED = '2026-09-01 10:00:00';
    private const ENTERED = '2026-09-03 08:30:00';

    private static ?DatabaseService $db = null;
    private static string $database = self::DATABASE;
    private static ?mysqli $mysqli = null;

    /**
     * label => [remote_sample, result_status, collection date, reception date as saved, expected reception date]
     *
     * @return array<string, array{0:?string, 1:?int, 2:?string, 3:?string, 4:?string}>
     */
    private static function cases(): array
    {
        return [
            // Entered at a lab.
            'lab-registered-no-date' => ['no', RECEIVED_AT_TESTING_LAB, self::COLLECTED, null, self::COLLECTED],
            'lab-registered-date-entered' => [
                'no', RECEIVED_AT_TESTING_LAB, self::COLLECTED, self::ENTERED, self::ENTERED,
            ],
            'lab-accepted-remote-unset' => [null, ACCEPTED, self::COLLECTED, null, self::COLLECTED],
            'lab-rejected' => ['no', REJECTED, self::COLLECTED, null, self::COLLECTED],
            'lab-expired' => ['no', EXPIRED, self::COLLECTED, null, self::COLLECTED],
            'lab-cancelled' => ['no', CANCELLED, self::COLLECTED, null, null],
            'lab-health-center-status' => ['no', RECEIVED_AT_CLINIC, self::COLLECTED, null, null],
            'lab-no-collection-date' => ['no', RECEIVED_AT_TESTING_LAB, null, null, null],
            'lab-no-status' => ['no', null, self::COLLECTED, null, null],

            // Requested from a collection site on STS.
            'sts-registered-at-clinic' => ['yes', RECEIVED_AT_CLINIC, self::COLLECTED, null, null],
            'sts-rejected' => ['yes', REJECTED, self::COLLECTED, null, null],
            'sts-expired' => ['yes', EXPIRED, self::COLLECTED, null, null],
            'sts-lost' => ['yes', LOST_OR_MISSING, self::COLLECTED, null, null],
            'sts-at-lab-no-date' => ['yes', RECEIVED_AT_TESTING_LAB, self::COLLECTED, null, self::COLLECTED],
            'sts-accepted-date-entered' => ['yes', ACCEPTED, self::COLLECTED, self::ENTERED, self::ENTERED],
            'sts-accepted-no-date' => ['yes', ACCEPTED, self::COLLECTED, null, self::COLLECTED],
        ];
    }

    public static function setUpBeforeClass(): void
    {
        $host = getenv('INTELIS_TEST_DB_HOST');
        $user = getenv('INTELIS_TEST_DB_USER');

        if ($host === false || $host === '' || $user === false || $user === '') {
            return;
        }

        $port = (int) (getenv('INTELIS_TEST_DB_PORT') ?: 3306);
        $password = (string) (getenv('INTELIS_TEST_DB_PASS') ?: '');

        // Per process, so a second run against the same server cannot drop this one's tables.
        self::$database = self::DATABASE . '_' . getmypid();

        $bootstrap = new mysqli($host, $user, $password, null, $port);
        $bootstrap->query('DROP DATABASE IF EXISTS `' . self::$database . '`');
        $bootstrap->query('CREATE DATABASE `' . self::$database . '`');
        $bootstrap->select_db(self::$database);

        $columns = 'label VARCHAR(64) NOT NULL,
                remote_sample VARCHAR(255) NULL,
                result_status INT NULL,
                sample_collection_date DATETIME NULL,
                sample_received_at_lab_datetime DATETIME NULL,
                last_modified_datetime DATETIME NULL';
        // Carries the triggers.
        $bootstrap->query("CREATE TABLE form_eid (eid_id INT AUTO_INCREMENT PRIMARY KEY, {$columns}) ENGINE=InnoDB");
        // No triggers: rows land exactly as written, for the sweep to repair.
        $bootstrap->query(
            "CREATE TABLE form_vl (vl_sample_id INT AUTO_INCREMENT PRIMARY KEY, {$columns}) ENGINE=InnoDB"
        );
        // Missing the rule's columns, like user_details.
        $bootstrap->query(
            'CREATE TABLE form_other (id INT AUTO_INCREMENT PRIMARY KEY, result_status INT NULL) ENGINE=InnoDB'
        );

        self::$mysqli = $bootstrap;

        self::$db = new DatabaseService([
            'host' => $host,
            'username' => $user,
            'password' => $password,
            'db' => self::$database,
            'port' => $port,
        ]);

        foreach ((new AuditTriggerService(self::$db))->buildReceiptTriggersFor('form_eid') as $sql) {
            $bootstrap->query($sql);
        }
    }

    public static function tearDownAfterClass(): void
    {
        self::$mysqli?->query('DROP DATABASE IF EXISTS `' . self::$database . '`');
        self::$mysqli?->close();
        self::$mysqli = null;
        self::$db = null;
    }

    protected function setUp(): void
    {
        if (self::$db === null) {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }
        self::$mysqli->query('TRUNCATE form_eid');
        self::$mysqli->query('TRUNCATE form_vl');
    }

    private function seed(string $table, string $label, array $case, string $lastModified = '2026-09-14 09:00:00'): int
    {
        [$remote, $status, $collected, $received] = $case;
        self::$db->insert($table, [
            'label' => $label,
            'remote_sample' => $remote,
            'result_status' => $status,
            'sample_collection_date' => $collected,
            'sample_received_at_lab_datetime' => $received,
            'last_modified_datetime' => $lastModified,
        ]);
        return (int) self::$db->getInsertId();
    }

    /** @return array<string, ?string> label => stored reception date */
    private function receptionDates(string $table): array
    {
        $rows = self::$db->rawQuery(
            "SELECT label, sample_received_at_lab_datetime AS received FROM {$table} ORDER BY label"
        );
        return array_column($rows, 'received', 'label');
    }

    /** @return array<string, ?string> */
    private function expected(): array
    {
        $out = array_map(static fn(array $case): ?string => $case[4], self::cases());
        ksort($out);
        return $out;
    }

    public function testTriggerFillsOnlySamplesThatReachedALabWithNoDate(): void
    {
        foreach (self::cases() as $label => $case) {
            $this->seed('form_eid', $label, $case);
        }

        $this->assertSame($this->expected(), $this->receptionDates('form_eid'));
    }

    public function testSweepReachesTheSameAnswerAsTheTrigger(): void
    {
        foreach (self::cases() as $label => $case) {
            $this->seed('form_vl', $label, $case);
        }

        $filled = (new LabReceiptService(self::$db))->stampMissing('form_vl', 'vl_sample_id');

        $this->assertSame($this->expected(), $this->receptionDates('form_vl'));
        $this->assertSame(
            count(array_filter(self::cases(), static fn(array $c): bool => $c[3] === null && $c[4] !== null)),
            $filled
        );
    }

    public function testClinicSampleIsFilledOnlyWhenTheLabTakesIt(): void
    {
        $id = $this->seed('form_eid', 'moves', ['yes', RECEIVED_AT_CLINIC, self::COLLECTED, null]);
        $this->assertNull($this->receptionDates('form_eid')['moves']);

        self::$db->where('eid_id', $id);
        self::$db->update('form_eid', ['result_status' => RECEIVED_AT_TESTING_LAB]);

        $this->assertSame(self::COLLECTED, $this->receptionDates('form_eid')['moves']);
    }

    public function testADateEnteredLaterReplacesTheFallback(): void
    {
        $id = $this->seed('form_eid', 'corrected', ['no', RECEIVED_AT_TESTING_LAB, self::COLLECTED, null]);
        $this->assertSame(self::COLLECTED, $this->receptionDates('form_eid')['corrected']);

        self::$db->where('eid_id', $id);
        self::$db->update('form_eid', ['sample_received_at_lab_datetime' => self::ENTERED]);

        $this->assertSame(self::ENTERED, $this->receptionDates('form_eid')['corrected']);
    }

    public function testRecentSweepLeavesOlderRowsForTheOneTimePass(): void
    {
        $case = ['no', RECEIVED_AT_TESTING_LAB, self::COLLECTED, null];
        $this->seed('form_vl', 'recent', $case, date('Y-m-d H:i:s'));
        $this->seed('form_vl', 'old', $case, '2020-01-01 00:00:00');

        $filled = (new LabReceiptService(self::$db))->stampMissing('form_vl', 'vl_sample_id', 3);

        $this->assertSame(1, $filled);
        $this->assertSame(['old' => null, 'recent' => self::COLLECTED], $this->receptionDates('form_vl'));
    }

    public function testTableWithoutTheRuleColumnsGetsNoTrigger(): void
    {
        $this->assertSame([], (new AuditTriggerService(self::$db))->buildReceiptTriggersFor('form_other'));
        $this->assertSame(0, (new LabReceiptService(self::$db))->stampMissing('form_other', 'id'));
    }
}
