<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\DatabaseService;
use App\Utilities\SampleCountUtility;
use App\Utilities\SampleRejectionUtility;
use mysqli;
use PHPUnit\Framework\TestCase;

use const SAMPLE_STATUS\ACCEPTED;
use const SAMPLE_STATUS\CANCELLED;
use const SAMPLE_STATUS\EXPIRED;
use const SAMPLE_STATUS\LOST_OR_MISSING;
use const SAMPLE_STATUS\NO_RESULT;
use const SAMPLE_STATUS\ON_HOLD;
use const SAMPLE_STATUS\PENDING_APPROVAL;
use const SAMPLE_STATUS\RECEIVED_AT_CLINIC;
use const SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
use const SAMPLE_STATUS\REFERRED;
use const SAMPLE_STATUS\REJECTED;
use const SAMPLE_STATUS\REORDERED_FOR_TESTING;
use const SAMPLE_STATUS\TEST_FAILED;

/**
 * Which samples count as real work, against a real server.
 *
 * A cancelled sample was called off before testing, so it belongs in no total, rate,
 * chart or export. The clause is one line, but the things that can go wrong with it
 * are things only MySQL can answer: which statuses survive it, what it does with a
 * NULL, and whether the alias constrains the table the caller meant.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class SampleCountingTest extends TestCase
{
    private const DATABASE = 'intelis_counting_test';

    private static ?DatabaseService $db = null;

    /** Every status a sample can hold, so nothing but CANCELLED is excluded by accident. */
    private const EVERY_STATUS = [
        'on-hold' => ON_HOLD,
        'lost-or-missing' => LOST_OR_MISSING,
        'reordered' => REORDERED_FOR_TESTING,
        'rejected' => REJECTED,
        'test-failed' => TEST_FAILED,
        'received-at-lab' => RECEIVED_AT_TESTING_LAB,
        'accepted' => ACCEPTED,
        'pending-approval' => PENDING_APPROVAL,
        'received-at-clinic' => RECEIVED_AT_CLINIC,
        'expired' => EXPIRED,
        'no-result' => NO_RESULT,
        'cancelled' => CANCELLED,
        'referred' => REFERRED,
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
        // Nullable on purpose. form_vl declares result_status NOT NULL, but form_eid
        // and form_covid19 do not, so the NULL case is reachable in those modules by
        // schema even though no row carries one today.
        $bootstrap->query(
            'CREATE TABLE form_vl (
                vl_sample_id INT AUTO_INCREMENT PRIMARY KEY,
                label VARCHAR(64) NOT NULL,
                result_status INT NULL,
                is_sample_rejected VARCHAR(10) NULL
            ) ENGINE=InnoDB'
        );
        // A second table carrying the same column name, so an alias that reached the
        // wrong one would show up rather than pass quietly.
        $bootstrap->query(
            'CREATE TABLE form_eid (
                eid_id INT AUTO_INCREMENT PRIMARY KEY,
                vl_sample_id INT NOT NULL,
                result_status INT NULL
            ) ENGINE=InnoDB'
        );
        $bootstrap->close();

        self::$db = new DatabaseService([
            'host' => $host,
            'username' => $user,
            'password' => $password,
            'db' => self::DATABASE,
            'port' => $port,
        ]);

        foreach (self::EVERY_STATUS as $label => $status) {
            self::$db->insert('form_vl', [
                'label' => $label,
                'result_status' => $status,
                'is_sample_rejected' => $status === REJECTED ? 'yes' : 'no',
            ]);
        }

        // A sample that was rejected and then cancelled: it belongs in no count, but
        // the rejection predicate alone still sees it.
        self::$db->insert('form_vl', [
            'label' => 'rejected-then-cancelled',
            'result_status' => CANCELLED,
            'is_sample_rejected' => 'yes',
        ]);

        self::$db->insert('form_vl', [
            'label' => 'status-never-written',
            'result_status' => null,
            'is_sample_rejected' => 'no',
        ]);
    }

    protected function setUp(): void
    {
        if (!self::$db instanceof DatabaseService) {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }
    }

    /** @return list<string> */
    private function labelsMatching(string $predicate, string $from = 'form_vl AS vl'): array
    {
        $rows = self::$db->rawQuery("SELECT vl.label FROM $from WHERE $predicate ORDER BY vl.label") ?: [];
        return array_column($rows, 'label');
    }

    public function testACancelledSampleIsNotCounted(): void
    {
        $counted = $this->labelsMatching(SampleCountUtility::countableWhere('vl'));

        self::assertNotContains('cancelled', $counted);
        self::assertNotContains('rejected-then-cancelled', $counted);
    }

    /**
     * The clause must exclude cancelled and nothing else. A status quietly dropped
     * here would shrink every total in the application at once.
     */
    public function testEveryOtherStatusIsStillCounted(): void
    {
        $counted = $this->labelsMatching(SampleCountUtility::countableWhere('vl'));

        foreach (self::EVERY_STATUS as $label => $status) {
            if ($status === CANCELLED) {
                continue;
            }
            self::assertContains($label, $counted, "status $label ($status) must still count");
        }
    }

    /**
     * `!= 12` against a NULL yields NULL, so a row whose status was never written is
     * excluded. That is what the hand-written clauses this replaced already did, and
     * no production row carries a NULL, so it is pinned rather than changed: if this
     * ever needs to count those rows, that should be a decision someone makes with
     * this test in front of them.
     */
    public function testARowWithNoStatusIsExcludedRatherThanCounted(): void
    {
        $counted = $this->labelsMatching(SampleCountUtility::countableWhere('vl'));

        self::assertNotContains('status-never-written', $counted);
        self::assertSame(
            1,
            (int) (self::$db->rawQueryOne(
                'SELECT COUNT(*) AS c FROM form_vl WHERE result_status IS NULL'
            )['c'] ?? 0),
            'the fixture must still hold a NULL row, or this proves nothing'
        );
    }

    /**
     * The reason every rejection report needs both clauses, not one. A cancelled
     * sample that was rejected first is still a rejection by the rejection rule, so
     * only the countable clause keeps it out of the report.
     */
    public function testACancelledRejectionSurvivesTheRejectionRuleAlone(): void
    {
        $rejectionOnly = $this->labelsMatching(SampleRejectionUtility::sqlPredicate('vl'));
        $both = $this->labelsMatching(
            SampleRejectionUtility::sqlPredicate('vl') . ' AND ' . SampleCountUtility::countableWhere('vl')
        );

        self::assertContains('rejected-then-cancelled', $rejectionOnly);
        self::assertNotContains('rejected-then-cancelled', $both);
        self::assertContains('rejected', $both, 'an ordinary rejection must survive both clauses');
    }

    /**
     * Reports join several tables that each carry result_status. The alias decides
     * which one is constrained, and getting it wrong is silent.
     */
    public function testTheAliasConstrainsOnlyTheTableItNames(): void
    {
        self::$db->insert('form_eid', [
            'vl_sample_id' => (int) (self::$db->rawQueryOne(
                "SELECT vl_sample_id AS id FROM form_vl WHERE label = 'accepted'"
            )['id'] ?? 0),
            'result_status' => CANCELLED,
        ]);

        $joined = 'form_vl AS vl INNER JOIN form_eid AS eid ON eid.vl_sample_id = vl.vl_sample_id';

        // Constraining vl leaves the cancelled eid row alone.
        self::assertSame(['accepted'], $this->labelsMatching(SampleCountUtility::countableWhere('vl'), $joined));
        // Constraining eid removes it.
        self::assertSame([], $this->labelsMatching(SampleCountUtility::countableWhere('eid'), $joined));

        self::$db->rawQuery('DELETE FROM form_eid');
    }
}
