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

    /**
     * Labs have always sent Custom Test children newest first: the per-sample
     * read uses orderBy('test_id'), which defaults to DESC. The STS re-inserts
     * child rows in the order received, so the bulk read must keep that order.
     */
    #[RunInSeparateProcess]
    public function testCustomTestChildrenKeepLegacyOrderAcrossInterleavedTestIds(): void
    {
        if (!getenv('INTELIS_TEST_DB_HOST') || !getenv('INTELIS_TEST_DB_USER')) {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }
        $db = LegacyAppHarness::boot('intelis_result_sync_generic_order_test', [
            'system_config', 'global_config', 's_vlsm_instance', 'form_generic', 'generic_test_results',
        ]);
        $this->booted = true;
        $generic = ContainerRegistry::get(GenericTestsService::class);

        foreach (range(1, 4) as $id) {
            $db->insert('form_generic', [
                'sample_id' => $id, 'vlsm_instance_id' => 'test', 'request_created_by' => 'test', 'result_status' => 7,
            ]);
        }
        // Inserted out of order and interleaved across samples. Sample 4 has none.
        foreach ([[7, 1], [5, 2], [2, 1], [8, 3], [1, 2], [9, 1], [3, 3], [6, 3]] as [$testId, $sampleId]) {
            $db->insert('generic_test_results', [
                'test_id' => $testId, 'generic_id' => $sampleId, 'test_name' => "Test $testId", 'result' => 'negative',
            ]);
        }

        // Parents deliberately not in ID order.
        $ids = [2, 4, 1, 3];
        $rows = $legacy = [];
        foreach ($ids as $id) {
            $row = ['sample_id' => $id, 'unique_id' => "uuid-$id"];
            $rows[] = $row;
            $legacy[$row['unique_id']] = [
                'form_data' => $row,
                'data_from_tests' => $generic->getTestsByGenericSampleIds((string) $id),
            ];
        }
        $bulk = ResultSyncBatch::nestedPayload($rows, $generic->getTestsByGenericSampleIds($ids) ?? [], 'sample_id');

        self::assertSame(json_encode($legacy, JSON_THROW_ON_ERROR), json_encode($bulk, JSON_THROW_ON_ERROR));

        $expected = ['uuid-2' => [5, 1], 'uuid-4' => [], 'uuid-1' => [9, 7, 2], 'uuid-3' => [8, 6, 3]];
        self::assertSame(array_keys($expected), array_keys($bulk));
        foreach ($expected as $uniqueId => $testIds) {
            $children = $bulk[$uniqueId]['data_from_tests'];
            self::assertTrue(array_is_list($children), "$uniqueId children must be a list");
            self::assertSame($testIds, array_map('intval', array_column($children, 'test_id')), $uniqueId);
            // A list encodes as a JSON array, never an object keyed by test_id.
            self::assertStringStartsWith('[', json_encode($children, JSON_THROW_ON_ERROR));
        }
    }
}
