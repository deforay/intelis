<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\TestAttemptService;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyAppHarness;

use const SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
use const SAMPLE_STATUS\TEST_FAILED;

/**
 * Sending failed samples back for re-testing, limited to the caller's lab.
 *
 * The Failed Results grid posts sample ids, and the reset cleared whatever ids
 * arrived. On a cloud instance serving several labs, a user of one lab could
 * post another lab's ids and wipe its results. The endpoints now pass the lab
 * predicate and ids outside it are dropped.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class RetestLabScopeTest extends TestCase
{
    private const DATABASE = 'intelis_retest_lab_scope_test';

    private static function booted(): bool
    {
        return getenv('INTELIS_TEST_DB_HOST') !== false
            && getenv('INTELIS_TEST_DB_HOST') !== ''
            && getenv('INTELIS_TEST_DB_USER') !== false
            && getenv('INTELIS_TEST_DB_USER') !== '';
    }

    protected function setUp(): void
    {
        if (!self::booted()) {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }

        LegacyAppHarness::boot(
            self::DATABASE . '_' . getmypid(),
            ['r_sample_status', 'form_vl', 'test_result_attempts']
        );
        LegacyAppHarness::withSession();
        foreach ([TEST_FAILED, RECEIVED_AT_TESTING_LAB] as $status) {
            LegacyAppHarness::db()->insert('r_sample_status', [
                'status_id' => $status,
                'status_name' => "status $status",
            ]);
        }
    }

    protected function tearDown(): void
    {
        if (self::booted()) {
            LegacyAppHarness::shutdown();
        }
    }

    private function seedFailed(string $sampleCode, int $labId): int
    {
        $db = LegacyAppHarness::db();
        $db->insert('form_vl', [
            'sample_code' => $sampleCode,
            'lab_id' => $labId,
            'result' => 'Failed',
            'result_status' => TEST_FAILED,
            'data_sync' => 1,
        ]);

        return (int) $db->getInsertId();
    }

    private static function statusOf(int $id): int
    {
        $row = LegacyAppHarness::db()->rawQueryOne('SELECT result_status FROM form_vl WHERE vl_sample_id = ?', [$id]);
        return (int) ($row['result_status'] ?? 0);
    }

    public function testAnotherLabsSampleIsLeftAlone(): void
    {
        $own = $this->seedFailed('OWN-1', 5);
        $other = $this->seedFailed('OTHER-1', 9);

        $reset = (new TestAttemptService(LegacyAppHarness::db()))
            ->resetForRetest('vl', [$own, $other], RECEIVED_AT_TESTING_LAB, ' (lab_id = 5 OR lab_id IS NULL) ');

        $this->assertSame(1, $reset);
        $this->assertSame(RECEIVED_AT_TESTING_LAB, self::statusOf($own));
        $this->assertSame(TEST_FAILED, self::statusOf($other));
    }

    public function testNoScopeResetsEverySample(): void
    {
        $a = $this->seedFailed('A-1', 5);
        $b = $this->seedFailed('B-1', 9);

        $reset = (new TestAttemptService(LegacyAppHarness::db()))
            ->resetForRetest('vl', [$a, $b], RECEIVED_AT_TESTING_LAB);

        $this->assertSame(2, $reset);
        $this->assertSame(RECEIVED_AT_TESTING_LAB, self::statusOf($b));
    }

    /**
     * A retest clears the result on the lab. Unless the row is marked unsent, the
     * STS keeps showing the failed result and the next pull brings it back.
     */
    public function testRetestMarksTheSampleForSending(): void
    {
        $sent = $this->seedFailed('SENT-1', 5);
        $untouched = $this->seedFailed('UNTOUCHED-1', 9);

        (new TestAttemptService(LegacyAppHarness::db()))
            ->resetForRetest('vl', [$sent], RECEIVED_AT_TESTING_LAB);

        $row = LegacyAppHarness::db()->rawQueryOne(
            'SELECT data_sync, last_modified_datetime FROM form_vl WHERE vl_sample_id = ?',
            [$sent]
        );
        $this->assertSame(0, (int) $row['data_sync']);
        $this->assertNotNull($row['last_modified_datetime']);

        $other = LegacyAppHarness::db()->rawQueryOne(
            'SELECT data_sync FROM form_vl WHERE vl_sample_id = ?',
            [$untouched]
        );
        $this->assertSame(1, (int) $other['data_sync']);
    }
}
