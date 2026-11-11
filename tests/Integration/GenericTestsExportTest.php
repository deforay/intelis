<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Registries\ContainerRegistry;
use App\Services\GenericTestsExportService;
use OpenSpout\Reader\XLSX\Reader;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyAppHarness;
use Tests\Support\TestRun;

/**
 * The Custom Tests export adds a column for each field of the exported samples'
 * test type forms, after the common fields, filled from each sample's saved form.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class GenericTestsExportTest extends TestCase
{
    private const DATABASE = 'intelis_generic_tests_export_test';

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
            'system_config', 'global_config', 'facility_details', 'r_sample_status', 'batch_details',
            'r_funding_sources', 'r_implementation_partners', 'instruments', 'roles', 'user_details',
            'r_test_types', 'r_generic_sample_types', 'r_generic_test_reasons',
            'r_generic_sample_rejection_reasons', 'form_generic',
        ]);

        $db->insert('r_test_types', [
            'test_type_id' => 1,
            'test_standard_name' => 'Malaria',
            'test_generic_name' => 'malaria',
            'test_short_code' => 'MAL',
            'test_status' => 'active',
            'test_form_config' => json_encode([
                'requestSection' => ['f_species' => ['field_name' => 'Species']],
                'otherSection' => ['Travel' => ['f_area' => ['field_name' => 'Endemic Area']]],
            ]),
        ]);
        foreach ([1 => ['f_species' => 'P. falciparum', 'f_area' => ['North', 'East']], 2 => null] as $id => $form) {
            $db->insert('form_generic', [
                'sample_id' => $id,
                'unique_id' => "u$id",
                'vlsm_instance_id' => 'test',
                'test_type' => 1,
                'sample_code' => "MAL-$id",
                'test_type_form' => $form === null ? null : json_encode($form),
            ]);
        }
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        if (self::booted()) {
            LegacyAppHarness::shutdown();
        }
    }

    /** @return list<list<mixed>> */
    private function sheet(string $file): array
    {
        $reader = new Reader();
        $reader->open($file);
        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
            break;
        }
        $reader->close();
        unlink($file);
        return $rows;
    }

    #[RunInSeparateProcess]
    public function testEachFormFieldGetsAColumnFilledFromTheSample(): void
    {
        $service = ContainerRegistry::get(GenericTestsExportService::class);
        $rows = $this->sheet($service->export(
            'SELECT sample_id FROM form_generic ORDER BY sample_id',
            'check',
            false
        ));

        [$headings, $first, $second] = $rows;
        $species = array_search('Species', $headings, true);
        $area = array_search('Endemic Area', $headings, true);

        self::assertNotFalse($species);
        self::assertSame($species + 1, $area, 'the form fields sit together, in form order');
        self::assertSame(
            array_search('Request Created On', $headings, true) + 1,
            $species,
            'after the common fields'
        );
        self::assertSame(['MAL-1', 'P. falciparum', 'North, East'], [$first[2], $first[$species], $first[$area]]);
        self::assertSame(['MAL-2', '', ''], [$second[2], $second[$species], $second[$area]]);
        self::assertNotContains('Patient Name', $headings, 'patient details were not asked for');
    }

    #[RunInSeparateProcess]
    public function testSamplesSavedWithoutAFormAddNoColumns(): void
    {
        $service = ContainerRegistry::get(GenericTestsExportService::class);
        $rows = $this->sheet(
            $service->export('SELECT sample_id FROM form_generic WHERE sample_id = 2', 'check', false)
        );

        self::assertNotContains('Species', $rows[0]);
        self::assertCount(2, $rows);
    }
}
