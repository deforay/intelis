<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Repositories\Reference\ReferenceDataRepository;
use App\Services\DatabaseService;
use App\Services\GeoLocationsService;
use App\Services\PatientsService;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyAppHarness;

/**
 * Lookups that used to paste a value into their SQL: a request value (the
 * patient code prefix) or one read back from a stored row (a province name, a
 * reason for testing), where anyone who could save the form put it.
 *
 * A value that closes its quote must match nothing extra, and a legitimate value
 * with an apostrophe -- which used to break the query outright -- must find its
 * row.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class StoredValueQueryTest extends TestCase
{
    private DatabaseService $db;

    protected function setUp(): void
    {
        if ((getenv('INTELIS_TEST_DB_HOST') ?: '') === '' || (getenv('INTELIS_TEST_DB_USER') ?: '') === '') {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }

        // Named per process: two runs against one MySQL would drop each other's fixtures.
        $this->db = LegacyAppHarness::boot('intelis_stored_value_query_' . getmypid(), [
            'geographical_divisions', 'r_vl_test_reasons', 'patients',
        ]);

        $this->db->rawQuery(
            "INSERT INTO geographical_divisions (geo_id, geo_name, geo_code, geo_parent, geo_status) VALUES
                (1, 'North', 'N', 0, 'active'), (2, 'Cote d''Ivoire Est', 'CE', 0, 'active')"
        );
        $this->db->rawQuery(
            "INSERT INTO r_vl_test_reasons (test_reason_id, test_reason_name, test_reason_status) VALUES
                (5, 'routine', 'active'), (6, 'patient''s request', 'active')"
        );
        $this->db->rawQuery(
            "INSERT INTO patients (system_patient_code, patient_code_prefix, patient_code_key) VALUES
                ('sys-1', 'P', 3), ('sys-2', 'X', 50)"
        );
    }

    protected function tearDown(): void
    {
        if ((getenv('INTELIS_TEST_DB_HOST') ?: '') !== '') {
            LegacyAppHarness::shutdown();
        }
    }

    public function testQuotedValuesStayOneLiteral(): void
    {
        $this->assertSame("'patient\\'s'", $this->db->quote("patient's"));
        $this->assertSame("'vl','x\\' OR \\'1\\'=\\'1'", $this->db->inTextList(['vl', "x' OR '1'='1"]));
        $this->assertSame(
            ['North'],
            array_column(
                $this->db->rawQuery(
                    'SELECT geo_name FROM geographical_divisions WHERE geo_code IN ('
                    . $this->db->inTextList(['N', "Z' OR '1'='1"]) . ')'
                ),
                'geo_name'
            )
        );
    }

    public function testAProvinceNameWithAnApostropheIsFoundAndAnInjectedOneMatchesNothing(): void
    {
        $geo = new GeoLocationsService($this->db);

        $this->assertSame([2], array_column($geo->findByName("Cote d'Ivoire Est"), 'geo_id'));
        $this->assertSame([], $geo->findByName("x' OR '1'='1"));
        $this->assertSame([], $geo->findByName(null));
    }

    public function testAReasonForTestingIsFoundByIdOrByName(): void
    {
        $repository = new ReferenceDataRepository($this->db);

        $this->assertSame([5], array_column($repository->findByIdOrName('test-reason', 'vl', '5'), 'test_reason_id'));
        $this->assertSame(
            [6],
            array_column($repository->findByIdOrName('test-reason', 'vl', "patient's request"), 'test_reason_id')
        );
        $this->assertSame([], $repository->findByIdOrName('test-reason', 'vl', "x' OR '1'='1"));
    }

    public function testAnInjectedPatientCodePrefixCannotReadOtherPrefixes(): void
    {
        $patients = \App\Registries\ContainerRegistry::get(PatientsService::class);

        $this->assertSame(4, json_decode($patients->generatePatientId('P'), true)['patientCodeKey']);
        // Closing the quote used to widen the MAX() to every prefix, handing out 51.
        $this->assertSame(1, json_decode($patients->generatePatientId("Z' OR '1'='1"), true)['patientCodeKey']);
    }
}
