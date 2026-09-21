<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Registries\ContainerRegistry;
use App\Services\Covid19Service;
use App\Services\GenericTestsService;
use App\Services\TbService;
use App\Utilities\ResultSyncBatch;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyAppHarness;

final class ResultSyncChildPayloadTest extends TestCase
{
    private bool $booted = false;

    protected function tearDown(): void
    {
        if ($this->booted) {
            LegacyAppHarness::shutdown();
        }
    }

    #[RunInSeparateProcess]
    public function testBulkChildReadsProduceTheSameJsonAsTheLegacyPerSampleReads(): void
    {
        if (!getenv('INTELIS_TEST_DB_HOST') || !getenv('INTELIS_TEST_DB_USER')) {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }
        $db = LegacyAppHarness::boot('intelis_result_sync_children_test', [
            'system_config', 'global_config', 's_vlsm_instance',
            'form_generic', 'form_covid19', 'form_tb', 'generic_test_results', 'covid19_tests', 'tb_tests',
        ]);
        $this->booted = true;
        $generic = ContainerRegistry::get(GenericTestsService::class);
        $covid = ContainerRegistry::get(Covid19Service::class);
        $tb = ContainerRegistry::get(TbService::class);

        foreach (range(1, 3) as $id) {
            $db->insert('form_generic', [
                'sample_id' => $id, 'vlsm_instance_id' => 'test', 'request_created_by' => 'test', 'result_status' => 7,
            ]);
            $db->insert('form_covid19', ['covid19_id' => $id, 'sample_collection_date' => '2026-01-01 00:00:00']);
            $db->insert('form_tb', [
                'tb_id' => $id, 'sample_code_key' => $id, 'sample_collection_date' => '2026-01-01 00:00:00',
            ]);
            if ($id === 3) {
                continue; // Include a parent with no child tests.
            }
            foreach (range(1, 2) as $number) {
                $db->insert('generic_test_results', [
                    'generic_id' => $id, 'test_name' => "Test $number", 'result' => 'negative',
                ]);
                $db->insert('covid19_tests', [
                    'covid19_id' => $id, 'test_name' => "Test $number", 'result' => 'negative',
                    'sample_tested_datetime' => '2026-01-02 00:00:00',
                ]);
                $db->insert('tb_tests', ['tb_id' => $id, 'test_result' => 'negative']);
            }
        }
        foreach (
            [
            [$generic->getTestsByGenericSampleIds(...), 'sample_id', false],
            [$covid->getCovid19TestsByFormId(...), 'covid19_id', true],
            [$tb->getTbTestsByFormId(...), 'tb_id', false],
            ] as [$read, $field, $wrap]
        ) {
            $rows = $legacy = [];
            foreach (range(1, 3) as $id) {
                $row = [$field => $id, 'unique_id' => "uuid-$id"];
                $rows[] = $row;
                $legacy[$row['unique_id']] = ['form_data' => $row, 'data_from_tests' => $read((string) $id)];
            }
            $bulk = ResultSyncBatch::nestedPayload($rows, $read([1, 2, 3]), $field, $wrap);
            self::assertSame(json_encode($legacy, JSON_THROW_ON_ERROR), json_encode($bulk, JSON_THROW_ON_ERROR));
        }
    }
}
