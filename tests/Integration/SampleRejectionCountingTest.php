<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\DatabaseService;
use App\Utilities\SampleRejectionUtility;
use mysqli;
use PHPUnit\Framework\TestCase;

use const SAMPLE_STATUS\REJECTED;

/**
 * Rejection counting, against a real server.
 *
 * The unit suite already covers isRejected() row by row and checks the shape of the
 * SQL string. Neither can answer the question that actually caused the incidents:
 * whether the SQL predicate and the PHP predicate pick the same rows out of MySQL.
 * A grid counts with one and the export taken from it counts with the other, so any
 * gap between them is a report disagreeing with itself.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class SampleRejectionCountingTest extends TestCase
{
    private const DATABASE = 'intelis_rejection_test';

    private static ?DatabaseService $db = null;

    /**
     * label, is_sample_rejected, result_status, reason_for_sample_rejection, result
     *
     * @var list<array{0: string, 1: string|null, 2: int|null, 3: int|null, 4: string|null}>
     */
    private const FIXTURES = [
        // Both records agree.
        ['flag-and-status',       'yes',  REJECTED, 3,    null],
        // result_status alone: rejections from older paths never wrote the flag.
        ['status-only',           null,   REJECTED, null, null],
        // The flag says no and the status says rejected. The union counts it, because
        // the flag is the record that is not always written.
        ['status-over-flag-no',   'no',   REJECTED, null, null],
        // Flag alone: the sample was rejected, then re-ordered, so the status moved on.
        ['flag-only',             'yes',  1,        3,    null],
        // A reason left behind on a sample that was un-rejected is not a rejection.
        ['leftover-reason-only',  'no',   1,        3,    null],
        ['clean-accepted',        'no',   1,        null, '1000'],
        // Flags as they can arrive from an import or an API payload.
        ['flag-uppercase',        'YES',  1,        3,    null],
        ['flag-mixed-case',       'Yes',  1,        3,    null],
        ['flag-trailing-space',   'yes ', 1,        3,    null],
        ['flag-leading-space',    ' yes', 1,        3,    null],
        ['nothing-recorded',      null,   null,     null, null],
        // Rejected and carrying a result: a contradiction, and still a rejection.
        ['rejected-with-result',  'yes',  REJECTED, 3,    '2500'],
        // A reason id minted by a lab, which the STS has no row for.
        ['reason-unknown-to-sts', 'yes',  REJECTED, 9999, null],
    ];

    /** Every fixture the utility should count, by label. */
    private const EXPECTED_REJECTED = [
        'flag-and-status',
        'status-only',
        'flag-only',
        'flag-uppercase',
        'flag-mixed-case',
        'flag-trailing-space',
        'flag-leading-space',
        'rejected-with-result',
        'status-over-flag-no',
        'reason-unknown-to-sts',
    ];

    public static function setUpBeforeClass(): void
    {
        $host = getenv('INTELIS_TEST_DB_HOST');
        $user = getenv('INTELIS_TEST_DB_USER');

        if ($host === false || $host === '' || $user === false || $user === '') {
            return;
        }

        $port = (int) (getenv('INTELIS_TEST_DB_PORT') ?: 3306);
        $password = (string) (getenv('INTELIS_TEST_DB_PASS') ?: '');

        $bootstrap = new mysqli($host, $user, $password, null, $port);
        $bootstrap->query('DROP DATABASE IF EXISTS `' . self::DATABASE . '`');
        $bootstrap->query('CREATE DATABASE `' . self::DATABASE . '`');
        $bootstrap->select_db(self::DATABASE);
        $bootstrap->query(
            'CREATE TABLE form_vl (
                vl_sample_id INT AUTO_INCREMENT PRIMARY KEY,
                label VARCHAR(64) NOT NULL,
                is_sample_rejected VARCHAR(10) NULL,
                result_status INT NULL,
                reason_for_sample_rejection INT NULL,
                result VARCHAR(32) NULL
            ) ENGINE=InnoDB'
        );
        $bootstrap->query(
            'CREATE TABLE r_vl_sample_rejection_reasons (
                rejection_reason_id INT PRIMARY KEY,
                rejection_reason_name VARCHAR(128) NOT NULL
            ) ENGINE=InnoDB'
        );
        $bootstrap->query(
            "INSERT INTO r_vl_sample_rejection_reasons VALUES (3, 'Haemolysed sample')"
        );
        $bootstrap->close();

        self::$db = new DatabaseService([
            'host' => $host,
            'username' => $user,
            'password' => $password,
            'db' => self::DATABASE,
            'port' => $port,
        ]);

        foreach (self::FIXTURES as [$label, $flag, $status, $reason, $result]) {
            self::$db->insert('form_vl', [
                'label' => $label,
                'is_sample_rejected' => $flag,
                'result_status' => $status,
                'reason_for_sample_rejection' => $reason,
                'result' => $result,
            ]);
        }
    }

    protected function setUp(): void
    {
        if (!self::$db instanceof DatabaseService) {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }
    }

    /** @return list<string> */
    private function labelsMatching(string $predicate): array
    {
        $rows = self::$db->rawQuery("SELECT label FROM form_vl AS vl WHERE $predicate ORDER BY label") ?: [];
        return array_column($rows, 'label');
    }

    /** @return list<string> */
    private function sorted(array $labels): array
    {
        sort($labels);
        return $labels;
    }

    public function testTheSqlPredicateCountsExactlyTheRejections(): void
    {
        self::assertSame(
            $this->sorted(self::EXPECTED_REJECTED),
            $this->labelsMatching(SampleRejectionUtility::sqlPredicate('vl'))
        );
    }

    /**
     * The one the unit suite cannot ask. A grid counts through the SQL predicate and
     * the export taken from that grid counts through isRejected(); if they part
     * company anywhere, the two disagree in the field and nothing says so.
     */
    public function testTheSqlAndPhpPredicatesAgreeOnEveryRow(): void
    {
        $fromSql = $this->labelsMatching(SampleRejectionUtility::sqlPredicate('vl'));

        $allRows = self::$db->rawQuery('SELECT * FROM form_vl ORDER BY label') ?: [];
        $fromPhp = array_values(array_map(
            static fn(array $row): string => $row['label'],
            array_filter($allRows, static fn(array $row): bool => SampleRejectionUtility::isRejected($row))
        ));

        self::assertSame($fromSql, $fromPhp);
    }

    /**
     * A reason is a detail of a rejection, not evidence one happened. Counting it as
     * evidence is what made the Excel exports disagree with the report they came from.
     */
    public function testAReasonLeftBehindOnAnAcceptedSampleIsNotCounted(): void
    {
        $counted = $this->labelsMatching(SampleRejectionUtility::sqlPredicate('vl'));

        self::assertNotContains('leftover-reason-only', $counted);
        self::assertSame(
            3,
            (int) (self::$db->rawQueryOne(
                "SELECT reason_for_sample_rejection AS r FROM form_vl WHERE label = 'leftover-reason-only'"
            )['r'] ?? 0),
            'the fixture must still carry a reason, or this proves nothing'
        );
    }

    /**
     * The incident: a listing INNER JOINed the reason table, so rejections whose reason
     * id the STS had never seen vanished from the table while the chart above it still
     * counted them -- 28 rows shown under a total of 417. A LEFT JOIN keeps them.
     */
    public function testARejectionWhoseReasonIsUnknownStillAppearsInTheListing(): void
    {
        $predicate = SampleRejectionUtility::sqlPredicate('vl');

        $leftJoined = array_column(self::$db->rawQuery(
            "SELECT vl.label, rsrr.rejection_reason_name
               FROM form_vl AS vl
               LEFT JOIN r_vl_sample_rejection_reasons AS rsrr
                 ON rsrr.rejection_reason_id = vl.reason_for_sample_rejection
              WHERE $predicate ORDER BY vl.label"
        ) ?: [], 'label');

        $innerJoined = array_column(self::$db->rawQuery(
            "SELECT vl.label
               FROM form_vl AS vl
               INNER JOIN r_vl_sample_rejection_reasons AS rsrr
                 ON rsrr.rejection_reason_id = vl.reason_for_sample_rejection
              WHERE $predicate ORDER BY vl.label"
        ) ?: [], 'label');

        self::assertContains('reason-unknown-to-sts', $leftJoined);
        self::assertNotContains(
            'reason-unknown-to-sts',
            $innerJoined,
            'an INNER JOIN must still drop it, or this test would pass for the wrong reason'
        );
        self::assertSame(
            count($this->labelsMatching($predicate)),
            count($leftJoined),
            'the listing and the count on the same page must see the same rows'
        );
    }

    /** An unmatched reason is named rather than dropped. */
    public function testAnUnknownReasonIsLabelledRatherThanLeftBlank(): void
    {
        $row = self::$db->rawQueryOne(
            "SELECT rsrr.rejection_reason_name AS name
               FROM form_vl AS vl
               LEFT JOIN r_vl_sample_rejection_reasons AS rsrr
                 ON rsrr.rejection_reason_id = vl.reason_for_sample_rejection
              WHERE vl.label = 'reason-unknown-to-sts'"
        );

        self::assertNull($row['name'] ?? null);
        self::assertNotSame('', SampleRejectionUtility::reasonLabel($row['name'] ?? null));
    }

    /**
     * A sample is rejected before it is tested, so a rejection carrying a result is a
     * row whose two records contradict each other and wants correcting, not counting.
     */
    public function testTheContradictionPredicateFindsRejectionsCarryingAResult(): void
    {
        self::assertSame(
            ['rejected-with-result'],
            $this->labelsMatching(SampleRejectionUtility::contradictionPredicate('vl'))
        );
    }
}
