<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\HttpHandlers\LegacyRequestHandler;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyAppHarness;
use Tests\Support\TestRun;

/**
 * The VL Results Not Available list, driven the way the clinic report page
 * drives it.
 *
 * Each seeded sample is one case the list has to tell apart. The other five
 * modules share the same rule, written out in their own endpoints.
 *
 * Every test runs in its own process and drives the handler once.
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class ResultsNotAvailableEndpointTest extends TestCase
{
    private const DATABASE = 'intelis_results_not_available_test';

    private const LAB_ID = 801;
    private const FACILITY_ID = 802;

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

        $db = LegacyAppHarness::boot(TestRun::database(self::DATABASE), [
            // Lookup tables first: form_vl holds foreign keys to some of them.
            'r_sample_status', 'facility_details', 'r_vl_sample_type', 'batch_details',
            'r_vl_art_regimen', 'r_implementation_partners',
            'form_vl',
            'system_config', 'global_config',
        ]);
        LegacyAppHarness::withSession(['roleId' => 1]);

        $db->rawQuery(
            "INSERT INTO facility_details
                (facility_id, facility_name, facility_state, facility_district, facility_type, vlsm_instance_id, status)
             VALUES (" . self::LAB_ID . ", 'Central Lab', 'North', 'Capital', 2, 'test', 'active'),
                    (" . self::FACILITY_ID . ", 'Riverside Clinic', 'North', 'Capital', 1, 'test', 'active')"
        );
        $db->rawQuery(
            "INSERT INTO r_sample_status (status_id, status_name, status) VALUES
                (4, 'Rejected', 'active'), (5, 'Failed/Invalid', 'active'),
                (6, 'Sample Registered at Testing Lab', 'active'), (7, 'Accepted', 'active'),
                (12, 'Cancelled', 'active')"
        );
        $db->rawQuery("INSERT INTO r_vl_sample_type (sample_id, sample_name, status) VALUES (1, 'Plasma', 'active')");

        $this->seed('WAITING', ['result_status' => 6]);
        // A failed sample was tested and failed, so it is never awaiting a
        // result, whatever its result field holds.
        $this->seed('FAILED-EMPTY', ['result_status' => 5]);
        $this->seed('FAILED-TEXT', ['result_status' => 5, 'result' => 'Failed']);
        $this->seed('INVALID-TEXT', ['result_status' => 5, 'result' => 'Invalid']);
        $this->seed('ACCEPTED', ['result_status' => 7, 'result' => '<20']);
        $this->seed('REJECTED', ['result_status' => 4, 'is_sample_rejected' => 'yes']);
        $this->seed('CANCELLED', ['result_status' => 12]);
    }

    protected function tearDown(): void
    {
        if (self::booted()) {
            LegacyAppHarness::shutdown();
        }
    }

    /** @param array<string, mixed> $columns */
    private function seed(string $sampleCode, array $columns): void
    {
        $row = $columns + [
            'unique_id' => 'uid-' . $sampleCode,
            'vlsm_instance_id' => 'test',
            'vlsm_country_id' => 1,
            'sample_code' => $sampleCode,
            'facility_id' => self::FACILITY_ID,
            'lab_id' => self::LAB_ID,
            'specimen_type' => 1,
            'sample_collection_date' => '2026-03-10 09:00:00',
        ];
        $names = [];
        $values = [];
        foreach ($row as $name => $value) {
            $names[] = "`$name`";
            $values[] = "'" . LegacyAppHarness::db()->escape((string) $value) . "'";
        }
        LegacyAppHarness::db()->rawQuery(
            "INSERT INTO form_vl (" . implode(', ', $names) . ") VALUES (" . implode(', ', $values) . ")"
        );
    }

    /** @return list<string> sample codes in the list */
    private function listed(): array
    {
        $request = LegacyAppHarness::withPost([
            'sEcho' => 1, 'iDisplayStart' => 0, 'iDisplayLength' => 25, 'sSearch' => '',
        ], '/vl/program-management/getResultNotAvailable.php');
        $handler = new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));
        $body = (string) $handler->handle($request)->getBody();
        $json = json_decode($body, true);
        self::assertIsArray($json, "Endpoint did not return JSON: $body");

        $codes = array_column($json['aaData'], 0);
        sort($codes);
        return $codes;
    }

    #[RunInSeparateProcess]
    public function testFailedSamplesAreNeverListed(): void
    {
        self::assertSame(
            ['WAITING'],
            $this->listed(),
            'failed, accepted, rejected and cancelled samples are left out'
        );
    }
}
