<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyAppHarness;

/**
 * The testing lab a request edit may save (CommonService::resolveRequestLabId).
 *
 * A sample on a manifest keeps the manifest's lab: an edit that picked another
 * lab left the manifest naming one lab and its sample another, on the STS where
 * the lab is otherwise a free choice. Moving it is Move Manifest's job.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class RequestLabOnManifestTest extends TestCase
{
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
        $database = 'intelis_request_lab_on_manifest_test_' . getmypid();
        if (!defined('SYSTEM_CONFIG')) {
            define('SYSTEM_CONFIG', ['database' => ['db' => $database], 'modules' => ['vl' => true]]);
        }
        $db = LegacyAppHarness::boot($database, [
            'system_config', 'global_config', 's_vlsm_instance', 'r_sample_status', 'form_vl',
        ]);
        $this->booted = true;
        // An STS, where the lab on a request is otherwise the operator's choice.
        $db->insert('system_config', [
            'display_name' => 'Instance type', 'name' => 'sc_user_type', 'value' => 'remoteuser',
        ]);
        $db->rawQuery("INSERT INTO r_sample_status (status_id, status_name) VALUES (9, 'Clinic')");
        $_SESSION = ['instance' => ['type' => 'remoteuser']];
        $samples = ['on-manifest' => [22026, 'VL-M-1'], 'by-code' => [null, 'VL-M-2'], 'loose' => [null, null]];
        foreach ($samples as $uniqueId => [$manifestId, $manifestCode]) {
            $db->insert('form_vl', [
                'unique_id' => $uniqueId, 'vlsm_instance_id' => 'sts', 'facility_id' => 1, 'lab_id' => 5,
                'result_status' => 9, 'sample_package_id' => $manifestId, 'sample_package_code' => $manifestCode,
            ]);
        }
        return $db;
    }

    private static function idOf($db, string $uniqueId): int
    {
        $row = $db->rawQueryOne('SELECT vl_sample_id FROM form_vl WHERE unique_id = ?', [$uniqueId]);
        return (int) $row['vl_sample_id'];
    }

    #[RunInSeparateProcess]
    public function testASampleOnAManifestKeepsItsLab(): void
    {
        $db = $this->boot();
        $general = ContainerRegistry::get(CommonService::class);
        $id = self::idOf($db, 'on-manifest');

        self::assertSame(5, $general->resolveRequestLabId(7, 'form_vl', 'vl_sample_id', $id));
        self::assertStringContainsString('manifest', $_SESSION['labKeptOnManifest'] ?? '', 'the user is told why');
        self::assertStringNotContainsString('VL-M-1', $_SESSION['labKeptOnManifest'], 'before the save checks access');
        self::assertSame(5, $general->resolveRequestLabId(null, 'form_vl', 'vl_sample_id', $id), 'nor blanked');
        self::assertSame(5, $general->resolveRequestLabId(5, 'form_vl', 'vl_sample_id', $id));
        // On older installs a sample can be on a manifest by its code alone.
        self::assertSame(5, $general->resolveRequestLabId(7, 'form_vl', 'vl_sample_id', self::idOf($db, 'by-code')));
    }

    #[RunInSeparateProcess]
    public function testASampleOnNoManifestAndANewRequestTakeTheChosenLab(): void
    {
        $db = $this->boot();
        $general = ContainerRegistry::get(CommonService::class);

        self::assertSame(7, $general->resolveRequestLabId(7, 'form_vl', 'vl_sample_id', self::idOf($db, 'loose')));
        self::assertSame(7, $general->resolveRequestLabId(7));
        self::assertArrayNotHasKey('labKeptOnManifest', $_SESSION);
    }

    #[RunInSeparateProcess]
    public function testSomeoneWhoCannotSeeTheSampleIsNotTold(): void
    {
        $db = $this->boot();
        $_SESSION['facilityMap'] = '2';
        $general = ContainerRegistry::get(CommonService::class);

        $id = self::idOf($db, 'on-manifest');
        self::assertSame(5, $general->resolveRequestLabId(7, 'form_vl', 'vl_sample_id', $id));
        self::assertArrayNotHasKey('labKeptOnManifest', $_SESSION, 'the notice would say it is on a manifest');
    }
}
