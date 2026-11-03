<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;
use App\Services\SampleAccessService;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyAppHarness;
use Tests\Support\TestRun;

/**
 * SampleAccessService::load(): the sample a link opens, and who may open it.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class SampleAccessLoadTest extends TestCase
{
    private bool $booted = false;

    protected function tearDown(): void
    {
        if ($this->booted) {
            LegacyAppHarness::shutdown();
        }
    }

    /** An STS with samples 123 and 15948, at facilities 1 and 2. */
    private function boot(array $session): SampleAccessService
    {
        if (!getenv('INTELIS_TEST_DB_HOST') || !getenv('INTELIS_TEST_DB_USER')) {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }
        $database = TestRun::database('intelis_sample_access_load_test');
        if (!defined('SYSTEM_CONFIG')) {
            define('SYSTEM_CONFIG', ['database' => ['db' => $database], 'modules' => ['eid' => true]]);
        }
        $db = LegacyAppHarness::boot($database, ['system_config', 'global_config', 'form_eid']);
        $this->booted = true;
        $db->insert('system_config', [
            'display_name' => 'Instance type', 'name' => 'sc_user_type', 'value' => 'remoteuser',
        ]);
        foreach ([123 => 1, 15948 => 2] as $eidId => $facilityId) {
            $db->insert('form_eid', [
                'eid_id' => $eidId, 'unique_id' => "eid-$eidId", 'facility_id' => $facilityId,
                'sample_code' => "EID-$eidId",
            ]);
        }
        $_SESSION = $session + ['instance' => ['type' => 'remoteuser'], 'roleId' => 2];
        return ContainerRegistry::get(SampleAccessService::class);
    }

    #[RunInSeparateProcess]
    public function testTheLinkOpensItsOwnSample(): void
    {
        $access = $this->boot([]);
        $sample = $access->load('eid', $access->encode(123));
        self::assertSame(123, (int) $sample['eid_id']);
        self::assertSame('EID-123', $sample['sample_code']);
    }

    #[RunInSeparateProcess]
    public function testTheOldLinkFor123DoesNotOpenSample15948(): void
    {
        $access = $this->boot([]);
        // desqid(base64_encode('123')) is 15948, and that sample exists here.
        try {
            $access->load('eid', base64_encode('123'));
            self::fail('an old-format link must not open any sample');
        } catch (SystemException $e) {
            self::assertSame(404, $e->getCode());
        }
    }

    #[RunInSeparateProcess]
    public function testASampleThatDoesNotExistIsNotFound(): void
    {
        $access = $this->boot([]);
        try {
            $access->load('eid', $access->encode(999));
            self::fail('a missing sample must stop the page');
        } catch (SystemException $e) {
            self::assertSame(404, $e->getCode());
        }
    }

    #[RunInSeparateProcess]
    public function testAMappedUserCannotOpenAnotherFacilitysSample(): void
    {
        $access = $this->boot(['facilityMap' => '1']);
        self::assertSame(123, (int) $access->load('eid', $access->encode(123))['eid_id']);
        try {
            $access->load('eid', $access->encode(15948));
            self::fail('a sample outside the facility map must stop the page');
        } catch (SystemException $e) {
            self::assertSame(403, $e->getCode());
        }
    }

    #[RunInSeparateProcess]
    public function testAnUnmappedUserOpensEverySample(): void
    {
        $access = $this->boot(['facilityMap' => '']);
        self::assertSame(15948, (int) $access->load('eid', $access->encode(15948))['eid_id']);
    }
}
