<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\HttpHandlers\LegacyRequestHandler;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyAppHarness;

use const SAMPLE_STATUS\CANCELLED;
use const SAMPLE_STATUS\REFERRED;

/**
 * Moving referred samples to a different receiving lab.
 *
 * The change left data_sync alone, so the STS never heard of it and kept routing
 * the sample to the old lab. The endpoint also took any sample id it was handed:
 * a lab could move another lab's referrals, and cancelled samples, too.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class ReferralLabUpdateTest extends TestCase
{
    private const DATABASE = 'intelis_referral_lab_update_test';

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

        LegacyAppHarness::boot(self::DATABASE . '_' . getmypid(), [
            'form_tb', 'form_generic', 'activity_log', 'system_config', 'global_config',
            'facility_details', 'testing_labs',
        ]);
        LegacyAppHarness::withSession(['labId' => 5]);
        foreach ([5 => 'active', 7 => 'active', 8 => 'active', 6 => 'inactive'] as $lab => $status) {
            LegacyAppHarness::db()->insert('facility_details', [
                'facility_id' => $lab, 'facility_name' => "Lab $lab", 'facility_type' => 2, 'status' => $status,
            ]);
            foreach (['tb', 'generic-tests'] as $testType) {
                LegacyAppHarness::db()->insert('testing_labs', ['test_type' => $testType, 'facility_id' => $lab]);
            }
        }
    }

    protected function tearDown(): void
    {
        if (self::booted()) {
            LegacyAppHarness::shutdown();
        }
    }

    private function seedReferral(string $table, string $code, int $referredBy, int $status = REFERRED): int
    {
        $db = LegacyAppHarness::db();
        $db->insert($table, [
            'sample_code' => $code,
            'unique_id' => $code,
            'sample_collection_date' => '2026-09-01 10:00:00',
            'lab_id' => $referredBy,
            'referred_by_lab_id' => $referredBy,
            'referred_to_lab_id' => 7,
            'referral_manifest_code' => 'REF-1',
            'result_status' => $status,
            'data_sync' => 1,
        ]);

        return (int) $db->getInsertId();
    }

    private function drive(string $path, array $post): array
    {
        $request = LegacyAppHarness::withPost($post, $path);
        $handler = new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));
        $body = (string) $handler->handle($request)->getBody();

        return json_decode($body, true) ?? [];
    }

    private static function row(string $table, string $key, int $id): array
    {
        return LegacyAppHarness::db()->rawQueryOne(
            "SELECT referred_to_lab_id, data_sync FROM $table WHERE $key = ?",
            [$id]
        );
    }

    #[RunInSeparateProcess]
    public function testAnotherLabsReferralIsNotMoved(): void
    {
        $other = $this->seedReferral('form_tb', 'TB-OTHER', 9);

        $response = $this->drive('/tb/results/update-tb-referral-helper.php', [
            'newReferralLabId' => 8,
            'sampleIds' => [$other],
        ]);

        $this->assertSame('error', $response['status'] ?? null);
        $this->assertSame(7, (int) self::row('form_tb', 'tb_id', $other)['referred_to_lab_id']);
    }

    #[RunInSeparateProcess]
    public function testACancelledSampleIsNotMoved(): void
    {
        $cancelled = $this->seedReferral('form_tb', 'TB-CANCELLED', 5, CANCELLED);

        $this->drive('/tb/results/update-tb-referral-helper.php', [
            'newReferralLabId' => 8,
            'sampleIds' => [$cancelled],
        ]);

        $this->assertSame(7, (int) self::row('form_tb', 'tb_id', $cancelled)['referred_to_lab_id']);
    }

    #[RunInSeparateProcess]
    public function testASampleCannotBeMovedToTheSendingLab(): void
    {
        $own = $this->seedReferral('form_tb', 'TB-OWN', 5);

        $response = $this->drive('/tb/results/update-tb-referral-helper.php', [
            'newReferralLabId' => 5,
            'sampleIds' => [$own],
        ]);

        $this->assertSame('error', $response['status'] ?? null);
        $this->assertSame(7, (int) self::row('form_tb', 'tb_id', $own)['referred_to_lab_id']);
    }

    /** @return array<string, array{int}> */
    public static function notTestingLabs(): array
    {
        return ['an inactive lab' => [6], 'no such facility' => [4040]];
    }

    #[RunInSeparateProcess]
    #[DataProvider('notTestingLabs')]
    public function testASampleCannotBeMovedToALabThatIsNotAnActiveTestingLab(int $notALab): void
    {
        $own = $this->seedReferral('form_tb', 'TB-OWN', 5);

        $response = $this->drive('/tb/results/update-tb-referral-helper.php', [
            'newReferralLabId' => $notALab,
            'sampleIds' => [$own],
        ]);

        $this->assertSame('error', $response['status'] ?? null);
        $this->assertSame(7, (int) self::row('form_tb', 'tb_id', $own)['referred_to_lab_id']);
    }

    #[RunInSeparateProcess]
    public function testACloudLisOperatorWithoutALabMovesNothing(): void
    {
        LegacyAppHarness::withSession([
            'instance' => ['type' => 'remoteuser'], 'roleId' => 4, 'accessType' => 'testing-lab',
        ]);
        $other = $this->seedReferral('form_tb', 'TB-OTHER', 9);

        $response = $this->drive('/tb/results/update-tb-referral-helper.php', [
            'newReferralLabId' => 8,
            'sampleIds' => [$other],
        ]);

        $this->assertSame('error', $response['status'] ?? null);
        $this->assertSame(7, (int) self::row('form_tb', 'tb_id', $other)['referred_to_lab_id']);
    }

    #[RunInSeparateProcess]
    public function testMovingATbReferralMarksItForSending(): void
    {
        $own = $this->seedReferral('form_tb', 'TB-OWN', 5);

        $response = $this->drive('/tb/results/update-tb-referral-helper.php', [
            'newReferralLabId' => 8,
            'sampleIds' => [$own],
        ]);

        $this->assertSame('success', $response['status'] ?? null);
        $row = self::row('form_tb', 'tb_id', $own);
        $this->assertSame(8, (int) $row['referred_to_lab_id']);
        $this->assertSame(0, (int) $row['data_sync']);
    }

    #[RunInSeparateProcess]
    public function testMovingACustomTestReferralMarksItForSending(): void
    {
        $own = $this->seedReferral('form_generic', 'GEN-OWN', 5);

        $response = $this->drive('/generic-tests/results/update-generic-referral-helper.php', [
            'newReferralLabId' => 8,
            'sampleIds' => [$own],
        ]);

        $this->assertSame('success', $response['status'] ?? null);
        $row = self::row('form_generic', 'sample_id', $own);
        $this->assertSame(8, (int) $row['referred_to_lab_id']);
        $this->assertSame(0, (int) $row['data_sync']);
    }
}
