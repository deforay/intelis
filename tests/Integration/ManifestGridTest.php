<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\HttpHandlers\LegacyRequestHandler;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyAppHarness;
use Tests\Support\TestRun;

/**
 * The Manage Specimen Referral Manifest grid, through the page itself.
 *
 * The grid answered with an error for every STS administrator: its Move buttons
 * read the rows before the grid did, and the rows came as a generator, which
 * can be read only once.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class ManifestGridTest extends TestCase
{
    private const LAB = 5;

    private bool $booted = false;

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
        $database = TestRun::database('intelis_manifest_grid_test');
        if (!defined('SYSTEM_CONFIG')) {
            define('SYSTEM_CONFIG', ['database' => ['db' => $database], 'modules' => ['vl' => true]]);
        }
        $db = LegacyAppHarness::boot($database, [
            'system_config', 'global_config', 's_vlsm_instance', 'r_sample_status', 'form_vl', 'specimen_manifests',
            'facility_details',
        ]);
        $this->booted = true;
        $db->rawQuery("INSERT INTO r_sample_status (status_id, status_name) VALUES (9, 'Registered at clinic')");
        $db->insert('facility_details', ['facility_id' => self::LAB, 'facility_name' => 'Lab', 'facility_type' => 2]);
        return $db;
    }

    private static function manifest($db, string $code, array $extra = []): int
    {
        $db->insert('specimen_manifests', $extra + [
            'manifest_code' => $code, 'module' => 'vl', 'lab_id' => self::LAB, 'manifest_type' => 'collection',
            'manifest_status' => 'pending', 'number_of_samples' => 1,
            'request_created_datetime' => date('Y-m-d H:i:s', strtotime('-1 day')),
        ]);
        $id = (int) $db->getInsertId();
        $db->insert('form_vl', [
            'unique_id' => "u-$code", 'facility_id' => 1, 'lab_id' => self::LAB,
            'sample_package_id' => $id, 'sample_package_code' => $code, 'result_status' => 9,
        ]);
        return $id;
    }

    /** @return array<string, string> manifest code => its row's buttons */
    private function grid(string $instanceType): array
    {
        LegacyAppHarness::withSession([
            'userId' => 'admin', 'roleId' => 1, 'instance' => ['type' => $instanceType],
        ]);
        $request = LegacyAppHarness::withPost(
            ['module' => 'vl', 'sEcho' => '1', 'iDisplayStart' => '0', 'iDisplayLength' => '10'],
            '/specimen-referral-manifest/get-manifests.php'
        );
        $handler = new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));
        $reply = json_decode((string) $handler->handle($request)->getBody(), true);
        self::assertIsArray($reply, 'the grid answered');
        return array_column(
            array_map(static fn(array $row): array => [$row[0], $row[5]], $reply['aaData']),
            1,
            0
        );
    }

    #[RunInSeparateProcess]
    public function testAnStsAdministratorSeesMoveOnlyWhereAManifestCanMove(): void
    {
        $db = $this->boot();
        self::manifest($db, 'M-ok');
        $tested = self::manifest($db, 'M-tested');
        $db->rawQuery("UPDATE form_vl SET result = '40' WHERE sample_package_id = ?", [$tested]);
        self::manifest($db, 'M-referral', ['manifest_type' => 'referral']);

        $buttons = $this->grid('remoteuser');

        self::assertSame(['M-ok', 'M-referral', 'M-tested'], self::sorted($buttons));
        self::assertStringContainsString('move-manifest.php?t=vl&code=M-ok', $buttons['M-ok']);
        self::assertStringNotContainsString('move-manifest.php', $buttons['M-tested'], 'a tested sample');
        self::assertStringNotContainsString('move-manifest.php', $buttons['M-referral'], 'a referral manifest');
    }

    #[RunInSeparateProcess]
    public function testALabSeesNoMoveButton(): void
    {
        $db = $this->boot();
        self::manifest($db, 'M-ok');

        $buttons = $this->grid('vluser');

        self::assertArrayHasKey('M-ok', $buttons);
        self::assertStringNotContainsString('move-manifest.php', $buttons['M-ok']);
    }

    /**
     * @param array<string, string> $buttons
     * @return list<string>
     */
    private static function sorted(array $buttons): array
    {
        $codes = array_keys($buttons);
        sort($codes);
        return $codes;
    }
}
