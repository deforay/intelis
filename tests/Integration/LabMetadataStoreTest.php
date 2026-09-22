<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Registries\ContainerRegistry;
use App\Services\STS\LabMetadataService;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyAppHarness;

/**
 * The STS storing the storage, instruments and users a lab sends it
 * (remote/remote/lab-metadata-receiver.php, via LabMetadataService).
 *
 * The lab owns these rows, but the STS keeps its own say over users' logins, roles,
 * passwords and status, and a lab on an older release must not blank what it does
 * not have.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class LabMetadataStoreTest extends TestCase
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
        $database = 'intelis_lab_metadata_store_test_' . getmypid();
        // getTableFieldsAsArray() reads columns from SYSTEM_CONFIG's database.
        if (!defined('SYSTEM_CONFIG')) {
            define('SYSTEM_CONFIG', ['database' => ['db' => $database], 'modules' => ['vl' => true]]);
        }
        if (!defined('UPLOAD_PATH')) {
            define('UPLOAD_PATH', VAR_PATH . DIRECTORY_SEPARATOR . 'uploads-' . getmypid());
        }
        $db = LegacyAppHarness::boot($database, [
            'system_config', 'global_config', 'roles', 'user_details', 'instruments', 'instrument_machines',
            'instrument_controls',
        ]);
        $this->booted = true;
        $db->rawQuery("INSERT INTO roles (role_id, role_name, role_code, status) VALUES
            (1, 'Admin', 'AD', 'active'), (4, 'Lab Tech', 'LT', 'active')");
        return $db;
    }

    private static function store(array $tables): int
    {
        $info = [];
        $keys = [
            'user_details' => 'user_id', 'instruments' => 'instrument_id',
            'instrument_machines' => 'config_machine_id', 'instrument_controls' => 'instrument_id',
        ];
        $i = 1;
        foreach ($tables as $table => $rows) {
            $info['primaryKey'][$i] = $keys[$table];
            $info['table'][$i] = $table;
            $info['data'][$i] = $rows;
            $i++;
        }
        return ContainerRegistry::get(LabMetadataService::class)->storeTables($info);
    }

    private static function labUser(string $id, array $values = []): array
    {
        return $values + [
            'user_id' => $id, 'user_name' => "User $id", 'email' => "$id@lab.test", 'login_id' => "lab-$id",
            'password' => 'lab-hash', 'role_id' => 1, 'status' => 'active',
        ];
    }

    #[RunInSeparateProcess]
    public function testAUserTheStsHasIsUpdatedButKeepsItsLoginRolePasswordAndStatus(): void
    {
        $db = $this->boot();
        $db->insert('user_details', [
            'user_id' => 'u-1', 'user_name' => 'Old Name', 'login_id' => 'sts-login', 'password' => 'sts-hash',
            'role_id' => 4, 'status' => 'inactive', 'phone_number' => '0999',
        ]);

        // The lab is on an older release: no phone_number column to send.
        self::store(['user_details' => [self::labUser('u-1', ['user_name' => 'New Name'])]]);

        $row = $db->rawQueryOne("SELECT * FROM user_details WHERE user_id = 'u-1'");
        self::assertSame('New Name', $row['user_name']);
        self::assertSame('sts-login', $row['login_id']);
        self::assertSame('sts-hash', $row['password']);
        self::assertSame(4, (int) $row['role_id']);
        self::assertSame('inactive', $row['status']);
        self::assertSame('0999', $row['phone_number'], 'a column the lab does not have is not blanked');
    }

    #[RunInSeparateProcess]
    public function testANewUserIsAddedWithoutTheLabsLoginOrPassword(): void
    {
        $db = $this->boot();

        self::store(['user_details' => [self::labUser('u-2')]]);

        $row = $db->rawQueryOne("SELECT * FROM user_details WHERE user_id = 'u-2'");
        self::assertSame('User u-2', $row['user_name'] ?? null);
        self::assertNull($row['login_id'], 'a lab cannot hand out a login on the STS');
        self::assertNull($row['password']);
    }

    #[RunInSeparateProcess]
    public function testAUserSignatureIsStoredUnderItsOwnNameOnly(): void
    {
        $db = $this->boot();
        $png = base64_encode("\x89PNG\r\n\x1a\nsignature");

        self::store(['user_details' => [
            self::labUser('u-1', ['user_signature' => 'sig-u1.png', 'signature_image_content' => $png,
                'signature_image_filename' => 'sig-u1.png']),
            // A name that climbs out of the signatures folder lands inside it.
            self::labUser('u-2', ['user_signature' => 'x.png', 'signature_image_content' => $png,
                'signature_image_filename' => '../../escape.png']),
            // Not an image: not written.
            self::labUser('u-3', ['user_signature' => 'x.php', 'signature_image_content' => $png,
                'signature_image_filename' => 'x.php']),
        ]]);

        $dir = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'users-signature';
        self::assertFileExists($dir . DIRECTORY_SEPARATOR . 'sig-u1.png');
        self::assertFileExists($dir . DIRECTORY_SEPARATOR . 'escape.png');
        self::assertFileDoesNotExist(dirname($dir, 2) . DIRECTORY_SEPARATOR . 'escape.png');
        self::assertFileDoesNotExist($dir . DIRECTORY_SEPARATOR . 'x.php');
        self::assertSame(3, (int) $db->rawQueryOne('SELECT COUNT(*) AS n FROM user_details')['n']);
    }

    #[RunInSeparateProcess]
    public function testInstrumentsAreStoredAndColumnsTheLabLacksAreKept(): void
    {
        $db = $this->boot();
        $db->insert('instruments', [
            'instrument_id' => 'i-1', 'machine_name' => 'Old', 'max_no_of_samples_in_a_batch' => 96,
            'lab_id' => 7, 'status' => 'active', 'additional_text' => 'kept',
        ]);

        self::store(['instruments' => [
            [
                'instrument_id' => 'i-1', 'machine_name' => 'Renamed', 'max_no_of_samples_in_a_batch' => 94,
                'lab_id' => 7,
            ],
            ['instrument_id' => 'i-2', 'machine_name' => 'New', 'max_no_of_samples_in_a_batch' => 24, 'lab_id' => 7],
        ]]);

        $rows = array_column($db->rawQuery('SELECT * FROM instruments ORDER BY instrument_id'), null, 'instrument_id');
        self::assertSame('Renamed', $rows['i-1']['machine_name']);
        self::assertSame('kept', $rows['i-1']['additional_text']);
        self::assertSame('New', $rows['i-2']['machine_name']);
    }

    /** 5.7.78: on the STS a machine is known by its instrument and the lab's own id for it. */
    private static function keyMachinesByInstrument(mixed $db): void
    {
        $migration = (string) file_get_contents(ROOT_PATH . '/sys/migrations/5.7.78.sql');
        preg_match_all('/^ALTER TABLE `instrument_machines`[^;]*;/ms', $migration, $statements);
        self::assertNotEmpty($statements[0]);
        foreach ($statements[0] as $sql) {
            $db->rawQuery($sql);
        }
    }

    private static function machine(string $instrumentId, int $id, string $name): array
    {
        return [
            'config_machine_id' => $id, 'instrument_id' => $instrumentId, 'config_machine_name' => $name,
            'file_name' => 'roche.php', 'poc_device' => 'no',
        ];
    }

    /** @return array<string, string> "instrument/id" => name */
    private static function machines(mixed $db): array
    {
        $names = [];
        foreach ($db->rawQuery('SELECT * FROM instrument_machines ORDER BY instrument_id, config_machine_id') as $r) {
            $names[$r['instrument_id'] . '/' . $r['config_machine_id']] = $r['config_machine_name'];
        }
        return $names;
    }

    #[RunInSeparateProcess]
    public function testTwoLabsWithTheSameMachineIdBothKeepTheirMachine(): void
    {
        $db = $this->boot();
        self::keyMachinesByInstrument($db);

        // Each lab numbers its machines from 1; instrument ids are unique.
        self::store(['instrument_machines' => [self::machine('lab-a-roche', 1, 'Roche A1')]]);
        self::store(['instrument_machines' => [
            self::machine('lab-b-abbott', 1, 'Abbott B1'), self::machine('lab-b-abbott', 2, 'Abbott B2'),
        ]]);

        self::assertSame(
            ['lab-a-roche/1' => 'Roche A1', 'lab-b-abbott/1' => 'Abbott B1', 'lab-b-abbott/2' => 'Abbott B2'],
            self::machines($db)
        );
    }

    #[RunInSeparateProcess]
    public function testAMachineIsUpdatedInPlaceAndKeepsTheLabsId(): void
    {
        $db = $this->boot();
        self::keyMachinesByInstrument($db);
        self::store(['instrument_machines' => [self::machine('i-1', 4, 'Old name')]]);

        self::store(['instrument_machines' => [self::machine('i-1', 4, 'New name')]]);

        // Results carry the lab's id for the machine (import_machine_name), so it must not change.
        self::assertSame(['i-1/4' => 'New name'], self::machines($db));
    }

    #[RunInSeparateProcess]
    public function testMachinesMissingFromABatchAreNotRemoved(): void
    {
        $db = $this->boot();
        self::keyMachinesByInstrument($db);
        self::store(['instrument_machines' => [self::machine('i-1', 1, 'One'), self::machine('i-1', 2, 'Two')]]);

        // A lab never deletes a machine: one left out of a batch is still there.
        self::store(['instrument_machines' => [self::machine('i-1', 2, 'Two renamed')]]);

        self::assertSame(['i-1/1' => 'One', 'i-1/2' => 'Two renamed'], self::machines($db));
    }

    #[RunInSeparateProcess]
    public function testTheSameMachinesSentAgainChangeNothing(): void
    {
        $db = $this->boot();
        self::keyMachinesByInstrument($db);
        $batch = ['instrument_machines' => [self::machine('i-1', 1, 'One'), self::machine('i-2', 1, 'Other')]];

        // A lab sends all its machines on every run.
        self::store($batch);
        self::store($batch);
        self::store($batch);

        self::assertSame(['i-1/1' => 'One', 'i-2/1' => 'Other'], self::machines($db));
    }

    #[RunInSeparateProcess]
    public function testAMachineThatCannotBeMatchedIsSkipped(): void
    {
        $db = $this->boot();
        self::keyMachinesByInstrument($db);

        self::store(['instrument_machines' => [
            ['instrument_id' => 'i-1', 'config_machine_name' => 'No id'],
            ['config_machine_id' => 3, 'config_machine_name' => 'No instrument'],
            ['instrument_id' => 'i-1', 'config_machine_id' => 'x', 'config_machine_name' => 'Not an id'],
            self::machine('i-1', 5, 'Good'),
        ]]);

        // Stored without an id, each resend would add another copy.
        self::assertSame(['i-1/5' => 'Good'], self::machines($db));
    }

    #[RunInSeparateProcess]
    public function testBeforeTheMigrationAnotherLabsMachineIsNeverOverwritten(): void
    {
        $db = $this->boot();
        // Code updated, 5.7.78 not yet applied: the key is still config_machine_id alone.
        self::store(['instrument_machines' => [self::machine('lab-a-roche', 1, 'Roche A1')]]);

        self::store(['instrument_machines' => [self::machine('lab-b-abbott', 1, 'Abbott B1')]]);

        self::assertSame(['lab-a-roche/1' => 'Roche A1'], self::machines($db));
    }

    #[RunInSeparateProcess]
    public function testAnInstrumentsControlsAreReplacedNotAddedTo(): void
    {
        $db = $this->boot();
        $control = static fn(string $testType, int $n): array => [
            'instrument_id' => 'i-1', 'test_type' => $testType, 'number_of_in_house_controls' => $n,
        ];

        self::store(['instrument_controls' => [$control('vl', 2), $control('eid', 1)]]);
        self::store(['instrument_controls' => [$control('vl', 3)]]);

        $rows = $db->rawQuery('SELECT test_type, number_of_in_house_controls FROM instrument_controls');
        self::assertSame([['test_type' => 'vl', 'number_of_in_house_controls' => 3]], array_map(
            static fn(array $r): array => [
                'test_type' => $r['test_type'],
                'number_of_in_house_controls' => (int) $r['number_of_in_house_controls'],
            ],
            $rows
        ));
    }

    #[RunInSeparateProcess]
    public function testARowThatCannotBeStoredOrIsJunkCostsOnlyItself(): void
    {
        $db = $this->boot();

        self::store(['instruments' => [
            null,
            'junk',
            // Refused by MySQL: max_no_of_samples_in_a_batch is required.
            ['instrument_id' => 'i-bad', 'machine_name' => 'Bad', 'max_no_of_samples_in_a_batch' => null],
            ['instrument_id' => 'i-ok', 'machine_name' => 'Ok', 'max_no_of_samples_in_a_batch' => 10],
        ]]);

        $stored = array_column($db->rawQuery('SELECT instrument_id FROM instruments'), 'instrument_id');
        self::assertSame(['i-ok'], $stored);
    }
}
