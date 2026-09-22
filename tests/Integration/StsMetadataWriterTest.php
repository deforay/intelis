<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\StsMetadataWriter;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyAppHarness;

/**
 * A lab writing the metadata it pulls from the STS (sts-metadata-receiver.php).
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class StsMetadataWriterTest extends TestCase
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
        $database = 'intelis_sts_metadata_writer_test_' . getmypid();
        if (!defined('SYSTEM_CONFIG')) {
            define('SYSTEM_CONFIG', ['database' => ['db' => $database], 'modules' => ['vl' => true]]);
        }
        $db = LegacyAppHarness::boot($database, [
            'system_config', 'global_config', 'roles', 'user_details', 'facility_details',
        ]);
        $this->booted = true;
        $db->rawQuery("INSERT INTO roles (role_id, role_name, role_code, status) VALUES
            (1, 'Admin', 'AD', 'active'), (4, 'Lab Tech', 'LT', 'active')");
        return $db;
    }

    private static function write(string $table, mixed $row, string $primaryKey): ?array
    {
        $fields = ContainerRegistry::get(CommonService::class)->getTableFieldsAsArray($table);
        return ContainerRegistry::get(StsMetadataWriter::class)->upsertRow($table, $row, $fields, $primaryKey);
    }

    #[RunInSeparateProcess]
    public function testAFacilityIsAddedThenUpdatedFromTheSts(): void
    {
        $db = $this->boot();
        $facility = ['facility_id' => 10, 'facility_name' => 'Clinic', 'facility_type' => 1, 'status' => 'active'];

        self::write('facility_details', $facility, 'facility_id');
        self::write('facility_details', ['facility_name' => 'Clinic Ten'] + $facility, 'facility_id');

        $rows = $db->rawQuery('SELECT facility_id, facility_name FROM facility_details');
        self::assertCount(1, $rows);
        self::assertSame('Clinic Ten', $rows[0]['facility_name']);
    }

    #[RunInSeparateProcess]
    public function testColumnsTheStsDoesNotSendAreLeftAlone(): void
    {
        $db = $this->boot();
        $db->insert('facility_details', [
            'facility_id' => 10, 'facility_name' => 'Clinic', 'facility_type' => 1, 'status' => 'active',
            'facility_emails' => 'lab@clinic.test',
        ]);

        // The STS runs an older release than the lab: no facility_emails column.
        self::write('facility_details', [
            'facility_id' => 10, 'facility_name' => 'Clinic Ten', 'facility_type' => 1, 'status' => 'active',
        ], 'facility_id');

        $row = $db->rawQueryOne('SELECT * FROM facility_details WHERE facility_id = 10');
        self::assertSame('Clinic Ten', $row['facility_name']);
        self::assertSame('lab@clinic.test', $row['facility_emails']);
    }

    #[RunInSeparateProcess]
    public function testAUsersLoginRolePasswordAndStatusStayTheLabs(): void
    {
        $db = $this->boot();
        $db->insert('user_details', [
            'user_id' => 'u-1', 'user_name' => 'Old', 'login_id' => 'lab-login', 'password' => 'lab-hash',
            'role_id' => 4, 'status' => 'active',
        ]);

        self::write('user_details', [
            'user_id' => 'u-1', 'user_name' => 'New', 'login_id' => 'sts-login', 'password' => 'sts-hash',
            'role_id' => 1, 'status' => 'inactive',
        ], 'user_id');

        $row = $db->rawQueryOne("SELECT * FROM user_details WHERE user_id = 'u-1'");
        self::assertSame('New', $row['user_name']);
        self::assertSame('lab-login', $row['login_id']);
        self::assertSame('lab-hash', $row['password']);
        self::assertSame(4, (int) $row['role_id']);
        self::assertSame('active', $row['status']);
    }

    #[RunInSeparateProcess]
    public function testARowThatIsNotOneIsSkipped(): void
    {
        $db = $this->boot();

        self::assertNull(self::write('facility_details', null, 'facility_id'));
        self::assertNull(self::write('facility_details', 'junk', 'facility_id'));
        self::assertNull(self::write('facility_details', ['not_a_column' => 1], 'facility_id'));
        self::assertSame([], $db->rawQuery('SELECT * FROM facility_details'));
    }
}
