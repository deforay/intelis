<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyAppHarness;

/**
 * sys/migrations/5.7.81.sql against real rows.
 *
 * South Sudan's VL and EID forms hold a patient's whole name in one field, while
 * the app posted names in parts. The migration joins each name into the column the
 * form reads, on South Sudan rows only, leaving encrypted names alone; and it makes
 * the infant's phone number text without losing the digits already stored.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class MigrationSouthSudanNamesTest extends TestCase
{
    private const DATABASE = 'intelis_migration_south_sudan_names_test';

    protected function setUp(): void
    {
        if (!getenv('INTELIS_TEST_DB_HOST') || !getenv('INTELIS_TEST_DB_USER')) {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }
        $db = LegacyAppHarness::boot(self::DATABASE . '_' . getmypid(), [
            'system_config', 'global_config', 'facility_details', 'r_sample_status', 'form_vl', 'form_eid',
        ]);
        foreach (range(1, 12) as $statusId) {
            $db->insert('r_sample_status', [
                'status_id' => $statusId, 'status_name' => "Status $statusId", 'status' => 'active',
            ]);
        }
    }

    protected function tearDown(): void
    {
        if (getenv('INTELIS_TEST_DB_HOST') && getenv('INTELIS_TEST_DB_USER')) {
            LegacyAppHarness::shutdown();
        }
    }

    private function migrate(): void
    {
        $sql = (string) file_get_contents(ROOT_PATH . '/sys/migrations/5.7.81.sql');
        $sql = preg_replace('/^--.*$/m', '', $sql);
        foreach (array_filter(array_map('trim', explode(";\n", (string) $sql))) as $statement) {
            LegacyAppHarness::db()->rawQuery(rtrim($statement, ';'));
        }
    }

    /** @param array<string, mixed> $row */
    private function eid(int $id, ?int $country, array $row): void
    {
        LegacyAppHarness::db()->insert('form_eid', [
            'eid_id' => $id, 'unique_id' => "eid-$id", 'vlsm_country_id' => $country,
            'last_modified_datetime' => '2026-01-01 00:00:00',
        ] + $row);
    }

    /** @param array<string, mixed> $row */
    private function vl(int $id, ?int $country, array $row): void
    {
        LegacyAppHarness::db()->insert('form_vl', [
            'vl_sample_id' => $id, 'unique_id' => "vl-$id", 'vlsm_country_id' => $country, 'result_status' => 1,
            'last_modified_datetime' => '2026-01-01 00:00:00',
        ] + $row);
    }

    /** @return array<string, mixed> */
    private function row(string $sql): array
    {
        return LegacyAppHarness::db()->rawQueryOne($sql) ?: [];
    }

    public function testSouthSudanNamesAreJoinedIntoTheColumnTheFormReads(): void
    {
        $this->eid(1, 1, ['child_name' => 'Deng', 'child_surname' => 'Garang']);
        $this->eid(2, 1, ['child_name' => null, 'child_surname' => ' Garang ']);
        $this->vl(1, 1, ['patient_first_name' => 'Achol', 'patient_middle_name' => '', 'patient_last_name' => 'Deng']);
        $this->vl(2, 1, [
            'patient_first_name' => 'Achol', 'patient_middle_name' => 'Nyibol', 'patient_last_name' => 'Deng',
        ]);

        $this->migrate();

        self::assertSame(
            ['child_name' => 'Deng Garang', 'child_surname' => null],
            $this->row('SELECT child_name, child_surname FROM form_eid WHERE eid_id = 1')
        );
        self::assertSame('Garang', $this->row('SELECT child_name FROM form_eid WHERE eid_id = 2')['child_name']);
        self::assertSame(
            ['patient_first_name' => 'Achol Deng', 'patient_middle_name' => null, 'patient_last_name' => null],
            $this->row('SELECT patient_first_name, patient_middle_name, patient_last_name FROM form_vl
                        WHERE vl_sample_id = 1')
        );
        self::assertSame(
            'Achol Nyibol Deng',
            $this->row('SELECT patient_first_name FROM form_vl WHERE vl_sample_id = 2')['patient_first_name']
        );
        // Not re-sent or re-sorted.
        self::assertSame(
            '2026-01-01 00:00:00',
            $this->row('SELECT last_modified_datetime FROM form_eid WHERE eid_id = 1')['last_modified_datetime']
        );
    }

    public function testOtherCountriesAndEncryptedNamesAreLeftAlone(): void
    {
        $this->eid(1, 7, ['child_name' => 'Deng', 'child_surname' => 'Garang']);
        $this->eid(2, 1, ['child_name' => 'x1', 'child_surname' => 'x2', 'is_encrypted' => 'yes']);
        $this->vl(1, 7, ['patient_first_name' => 'Achol', 'patient_last_name' => 'Deng']);

        $this->migrate();

        self::assertSame(
            ['child_name' => 'Deng', 'child_surname' => 'Garang'],
            $this->row('SELECT child_name, child_surname FROM form_eid WHERE eid_id = 1')
        );
        self::assertSame(
            ['child_name' => 'x1', 'child_surname' => 'x2'],
            $this->row('SELECT child_name, child_surname FROM form_eid WHERE eid_id = 2')
        );
        $vl = $this->row('SELECT patient_last_name FROM form_vl WHERE vl_sample_id = 1');
        self::assertSame('Deng', $vl['patient_last_name']);
    }

    public function testARowWithNoCountryFollowsTheInstancesForm(): void
    {
        $this->eid(1, null, ['child_name' => 'Deng', 'child_surname' => 'Garang']);
        LegacyAppHarness::db()->insert('global_config', ['name' => 'vl_form', 'value' => '1']);

        $this->migrate();

        self::assertSame('Deng Garang', $this->row('SELECT child_name FROM form_eid WHERE eid_id = 1')['child_name']);
    }

    public function testTheInfantPhoneBecomesTextAndKeepsItsDigits(): void
    {
        $this->eid(1, 7, ['infant_phone' => 690123456]);

        $this->migrate();

        self::assertSame('690123456', $this->row('SELECT infant_phone FROM form_eid WHERE eid_id = 1')['infant_phone']);
        LegacyAppHarness::db()->rawQuery("UPDATE form_eid SET infant_phone = '+211 0912 345 678' WHERE eid_id = 1");
        self::assertSame(
            '+211 0912 345 678',
            $this->row('SELECT infant_phone FROM form_eid WHERE eid_id = 1')['infant_phone']
        );
    }

    /** A joined name longer than the column is left in its parts rather than cut short. */
    public function testANameTooLongForTheColumnIsLeftInItsParts(): void
    {
        LegacyAppHarness::db()->rawQuery('ALTER TABLE form_vl MODIFY patient_first_name VARCHAR(20) NULL');
        LegacyAppHarness::db()->rawQuery('ALTER TABLE form_eid MODIFY child_name VARCHAR(20) NULL');
        $this->vl(1, 1, ['patient_first_name' => 'Achol Nyibol', 'patient_last_name' => 'Deng Garang Mabior']);
        $this->eid(1, 1, ['child_name' => 'Achol Nyibol', 'child_surname' => 'Deng Garang Mabior']);

        $this->migrate();

        self::assertSame(
            ['child_name' => 'Achol Nyibol', 'child_surname' => 'Deng Garang Mabior'],
            $this->row('SELECT child_name, child_surname FROM form_eid WHERE eid_id = 1')
        );
        self::assertSame(
            ['patient_first_name' => 'Achol Nyibol', 'patient_last_name' => 'Deng Garang Mabior'],
            $this->row('SELECT patient_first_name, patient_last_name FROM form_vl WHERE vl_sample_id = 1')
        );
    }
}
