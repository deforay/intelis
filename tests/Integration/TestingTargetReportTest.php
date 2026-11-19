<?php

declare(strict_types=1);

namespace Tests\Integration;

use mysqli;
use Tests\Support\TestRun;
use PHPUnit\Framework\TestCase;
use App\Services\DatabaseService;
use App\Services\TestingTargetReportService;

use const SAMPLE_STATUS\ACCEPTED;
use const SAMPLE_STATUS\CANCELLED;
use const SAMPLE_STATUS\REJECTED;

/**
 * The Testing Target Report sets each lab's monthly counts against its monthly
 * target, against a real server.
 *
 * Before 5.8.9 every test's report grouped by lab, facility and month and
 * compared the lab's target with one facility's share, so nearly every row
 * read as a miss. These cases pin the lab-month rule and each count's date.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class TestingTargetReportTest extends TestCase
{
    private const DATABASE = 'intelis_testing_target_test';

    private const LAB_A = 10;
    private const LAB_B = 20;
    private const LAB_C = 30;
    private const FACILITY_1 = 101;
    private const FACILITY_2 = 102;

    private static ?DatabaseService $db = null;
    private static string $database = '';
    private static ?mysqli $mysqli = null;

    public static function setUpBeforeClass(): void
    {
        $host = getenv('INTELIS_TEST_DB_HOST');
        $user = getenv('INTELIS_TEST_DB_USER');
        if ($host === false || $host === '' || $user === false || $user === '') {
            return;
        }
        $port = (int) (getenv('INTELIS_TEST_DB_PORT') ?: 3306);
        $password = (string) (getenv('INTELIS_TEST_DB_PASS') ?: '');

        self::$database = TestRun::database(self::DATABASE);
        $bootstrap = new mysqli($host, $user, $password, null, $port);
        $bootstrap->query('DROP DATABASE IF EXISTS `' . self::$database . '`');
        $bootstrap->query('CREATE DATABASE `' . self::$database . '`');
        $bootstrap->select_db(self::$database);

        $bootstrap->query('CREATE TABLE form_eid (
            eid_id INT AUTO_INCREMENT PRIMARY KEY,
            lab_id INT NULL,
            facility_id INT NULL,
            result_status INT NULL,
            is_sample_rejected VARCHAR(10) NULL,
            sample_received_at_lab_datetime DATETIME NULL,
            sample_tested_datetime DATETIME NULL
        ) ENGINE=InnoDB');
        $bootstrap->query('CREATE TABLE testing_labs (
            test_type VARCHAR(24) NOT NULL,
            facility_id INT NOT NULL,
            monthly_target VARCHAR(255) NULL,
            PRIMARY KEY (test_type, facility_id)
        ) ENGINE=InnoDB');
        $bootstrap->query('CREATE TABLE facility_details (
            facility_id INT PRIMARY KEY,
            facility_name VARCHAR(255) NULL
        ) ENGINE=InnoDB');

        self::$mysqli = $bootstrap;
        self::$db = new DatabaseService([
            'host' => $host,
            'username' => $user,
            'password' => $password,
            'db' => self::$database,
            'port' => $port,
        ]);
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
        foreach (['form_eid', 'testing_labs', 'facility_details'] as $table) {
            self::$mysqli->query("TRUNCATE $table");
        }
        self::$mysqli->query("INSERT INTO facility_details VALUES
            (10, 'Lab A'), (20, 'Lab B'), (30, 'Lab C'), (101, 'Clinic 1'), (102, 'Clinic 2')");
        // Lab A must test 3 a month. Lab B has no target. Lab C must test 5.
        // Lab D (40) has a blank target, which means none.
        self::$mysqli->query("INSERT INTO testing_labs VALUES
            ('eid', 10, '3'), ('eid', 30, '5'), ('eid', 40, ''), ('vl', 20, '100')");
    }

    private function sample(
        int $lab,
        int $facility,
        ?string $received,
        ?string $tested,
        int $status = ACCEPTED,
        ?string $rejected = 'no'
    ): void {
        $stmt = self::$mysqli->prepare('INSERT INTO form_eid
            (lab_id, facility_id, result_status, is_sample_rejected,
             sample_received_at_lab_datetime, sample_tested_datetime)
            VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('iiisss', $lab, $facility, $status, $rejected, $received, $tested);
        $stmt->execute();
    }

    private function service(): TestingTargetReportService
    {
        return new TestingTargetReportService(self::$db);
    }

    /** @return array<string, array<string, mixed>> keyed lab|month */
    private function byLabMonth(array $rows): array
    {
        $keyed = [];
        foreach ($rows as $row) {
            $keyed[$row['labName'] . '|' . $row['month']] = $row;
        }
        return $keyed;
    }

    public function testOneRowPerLabPerMonthComparedWithTheLabTarget(): void
    {
        // Lab A, September: three tests from two clinics. Each clinic is below
        // the target of 3 on its own, the lab is not.
        $this->sample(self::LAB_A, self::FACILITY_1, '2026-09-02', '2026-09-03');
        $this->sample(self::LAB_A, self::FACILITY_1, '2026-09-02', '2026-09-04');
        $this->sample(self::LAB_A, self::FACILITY_2, '2026-09-05', '2026-09-06');

        $rows = $this->byLabMonth($this->service()->rows('eid', '2026-09-01', '2026-09-30'));

        $this->assertSame(3, $rows['Lab A|2026-09']['tested']);
        $this->assertSame(3, $rows['Lab A|2026-09']['target']);
        $this->assertTrue($rows['Lab A|2026-09']['met']);
        $this->assertCount(1, array_filter(array_keys($rows), static fn($k) => str_starts_with($k, 'Lab A|')));
    }

    public function testEachCountTakesItsOwnDate(): void
    {
        // Received in September, tested in October.
        $this->sample(self::LAB_A, self::FACILITY_1, '2026-09-28', '2026-10-02');
        // Received and rejected in September, never tested.
        $this->sample(self::LAB_A, self::FACILITY_1, '2026-09-29', null, REJECTED, 'yes');
        // Rejected by flag only, with a test date: not tested.
        $this->sample(self::LAB_A, self::FACILITY_1, '2026-10-01', '2026-10-01', ACCEPTED, ' YES ');
        // Cancelled: counts nowhere.
        $this->sample(self::LAB_A, self::FACILITY_1, '2026-09-10', '2026-09-11', CANCELLED);
        // No rejection flag at all: still tested.
        $this->sample(self::LAB_A, self::FACILITY_1, '2026-10-03', '2026-10-04', ACCEPTED, null);

        $rows = $this->byLabMonth($this->service()->rows('eid', '2026-09-01', '2026-10-31'));

        $this->assertSame(['received' => 2, 'rejected' => 1, 'tested' => 0], [
            'received' => $rows['Lab A|2026-09']['received'],
            'rejected' => $rows['Lab A|2026-09']['rejected'],
            'tested' => $rows['Lab A|2026-09']['tested'],
        ]);
        $this->assertSame(['received' => 2, 'rejected' => 1, 'tested' => 2], [
            'received' => $rows['Lab A|2026-10']['received'],
            'rejected' => $rows['Lab A|2026-10']['rejected'],
            'tested' => $rows['Lab A|2026-10']['tested'],
        ]);
    }

    public function testALabWithATargetAndNoWorkShowsAtZero(): void
    {
        $rows = $this->byLabMonth($this->service()->rows('eid', '2026-09-01', '2026-10-31'));

        $this->assertSame(0, $rows['Lab C|2026-09']['tested']);
        $this->assertFalse($rows['Lab C|2026-09']['met']);
        $this->assertArrayHasKey('Lab C|2026-10', $rows);
        // A blank target is no target, so a lab with no work and no target is not listed.
        $this->assertArrayNotHasKey('40|2026-09', $rows);
        // A target for another test type does not apply.
        $this->assertArrayNotHasKey('Lab B|2026-09', $rows);
    }

    public function testALabWithoutATargetIsListedButNeverMetOrMissed(): void
    {
        $this->sample(self::LAB_B, self::FACILITY_1, '2026-09-02', '2026-09-03');
        $service = $this->service();

        $all = $this->byLabMonth($service->rows('eid', '2026-09-01', '2026-09-30'));
        $this->assertNull($all['Lab B|2026-09']['target']);
        $this->assertNull($all['Lab B|2026-09']['met']);

        $notMet = $service->rows('eid', '2026-09-01', '2026-09-30', [], TestingTargetReportService::TARGET_NOT_MET);
        $met = $service->rows('eid', '2026-09-01', '2026-09-30', [], TestingTargetReportService::TARGET_MET);
        $this->assertNotContains('Lab B', array_column($notMet, 'labName'));
        $this->assertNotContains('Lab B', array_column($met, 'labName'));
    }

    public function testTheRangeWidensToWholeMonths(): void
    {
        $this->sample(self::LAB_A, self::FACILITY_1, '2026-09-02', '2026-09-03');
        $this->sample(self::LAB_A, self::FACILITY_1, '2026-09-25', '2026-09-26');

        $rows = $this->byLabMonth($this->service()->rows('eid', '2026-09-20', '2026-09-22'));

        $this->assertSame(2, $rows['Lab A|2026-09']['tested']);
    }

    public function testLabAndScopeFiltersNarrowTheRows(): void
    {
        $this->sample(self::LAB_A, self::FACILITY_1, '2026-09-02', '2026-09-03');
        $this->sample(self::LAB_A, self::FACILITY_2, '2026-09-02', '2026-09-03');
        $service = $this->service();

        $onlyA = $service->rows('eid', '2026-09-01', '2026-09-30', [self::LAB_A]);
        $this->assertSame(['Lab A'], array_values(array_unique(array_column($onlyA, 'labName'))));

        $clinic1 = $this->byLabMonth($service->rows(
            'eid',
            '2026-09-01',
            '2026-09-30',
            [],
            TestingTargetReportService::TARGET_ALL,
            ['t.facility_id IN (' . self::FACILITY_1 . ')'],
            false
        ));
        $this->assertSame(1, $clinic1['Lab A|2026-09']['tested']);
        // Without filling, Lab C has nothing from Clinic 1 and is not listed.
        $this->assertArrayNotHasKey('Lab C|2026-09', $clinic1);
    }

    public function testTheDashboardCountsLabsOverCompletedMonthsOnly(): void
    {
        // Lab A meets September, misses October (1 of 3).
        foreach (['03', '04', '05'] as $day) {
            $this->sample(self::LAB_A, self::FACILITY_1, "2026-09-$day", "2026-09-$day");
        }
        $this->sample(self::LAB_A, self::FACILITY_1, '2026-10-03', '2026-10-03');
        // Lab C does nothing at all. November is in progress on the 19th.
        $service = $this->service();

        // Lab A (October) and Lab C (September, October): two labs, not three months.
        $this->assertSame(2, $service->labsBelowTarget('eid', '2026-09-01', '2026-11-19', today: '2026-11-19'));
        // A range inside the current month has no completed month.
        $this->assertSame(0, $service->labsBelowTarget('eid', '2026-11-01', '2026-11-19', today: '2026-11-19'));
        // The dashboard's 29-day range ending on the 19th judges all of October.
        $this->assertSame(2, $service->labsBelowTarget('eid', '2026-10-22', '2026-11-19', today: '2026-11-19'));
    }

    public function testMonthsCoversEveryMonthTheRangeTouches(): void
    {
        $this->assertSame(
            ['2026-11', '2026-12', '2027-01'],
            TestingTargetReportService::months('2026-11-30', '2027-01-01')
        );
        $this->assertSame([], TestingTargetReportService::months('2026-12-01', '2026-11-01'));
    }
}
