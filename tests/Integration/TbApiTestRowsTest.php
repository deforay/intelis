<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\TbTestsService;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyAppHarness;

/**
 * TB test rows an API client sends (api/v1.1/tb/save-request.php).
 *
 * The endpoint deleted every tb_tests row of the sample and inserted what the
 * client sent, on every update, and deleted them all on rejection. A client
 * re-posting its whole dataset wiped the tests the lab entered and the ones an
 * analyzer imported.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class TbApiTestRowsTest extends TestCase
{
    private const DATABASE = 'intelis_tb_api_test_rows_test';

    private int $tbId = 0;

    protected function setUp(): void
    {
        if (!getenv('INTELIS_TEST_DB_HOST') || !getenv('INTELIS_TEST_DB_USER')) {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }
        $db = LegacyAppHarness::boot(self::DATABASE . '_' . getmypid(), ['form_tb', 'tb_tests']);
        $db->insert('form_tb', ['unique_id' => 'tb-1', 'sample_collection_date' => '2026-09-01 10:00:00']);
        $this->tbId = (int) $db->getInsertId();
    }

    protected function tearDown(): void
    {
        if (getenv('INTELIS_TEST_DB_HOST') && getenv('INTELIS_TEST_DB_USER')) {
            LegacyAppHarness::shutdown();
        }
    }

    /** @return list<array{actual_no: ?string, test_result: string, lab_id: ?int}> */
    private function rows(): array
    {
        return array_map(
            static fn(array $row): array => [
                'actual_no' => $row['actual_no'],
                'test_result' => $row['test_result'],
                'lab_id' => $row['lab_id'] === null ? null : (int) $row['lab_id'],
            ],
            LegacyAppHarness::db()->rawQuery(
                'SELECT actual_no, test_result, lab_id FROM tb_tests WHERE tb_id = ? ORDER BY tb_test_id',
                [$this->tbId]
            )
        );
    }

    public function testTheLabsOwnTestsSurviveAClientPost(): void
    {
        LegacyAppHarness::db()->insert('tb_tests', [
            'tb_id' => $this->tbId, 'lab_id' => 5, 'test_result' => 'MTB detected',
        ]);

        (new TbTestsService(LegacyAppHarness::db()))
            ->saveApiTests($this->tbId, [['actualNo' => '1', 'testResult' => 'Negative']]);

        self::assertSame([
            ['actual_no' => null, 'test_result' => 'MTB detected', 'lab_id' => 5],
            ['actual_no' => '1', 'test_result' => 'Negative', 'lab_id' => null],
        ], $this->rows());
    }

    public function testALabTestWithTheSameNumberIsNotOverwritten(): void
    {
        LegacyAppHarness::db()->insert('tb_tests', [
            'tb_id' => $this->tbId, 'lab_id' => 5, 'actual_no' => '1', 'test_result' => 'MTB detected',
        ]);

        (new TbTestsService(LegacyAppHarness::db()))
            ->saveApiTests($this->tbId, [['actualNo' => '1', 'testResult' => 'Negative']]);

        self::assertSame([
            ['actual_no' => '1', 'test_result' => 'MTB detected', 'lab_id' => 5],
            ['actual_no' => '1', 'test_result' => 'Negative', 'lab_id' => null],
        ], $this->rows());
    }

    public function testRepostingTheSameResultsAddsNothing(): void
    {
        $service = new TbTestsService(LegacyAppHarness::db());
        $payload = [
            ['actualNo' => '1', 'testResult' => 'Negative'],
            ['testResult' => 'Scanty'],
            ['testResult' => 'Scanty'],
        ];

        $service->saveApiTests($this->tbId, $payload);
        $service->saveApiTests($this->tbId, $payload);

        // Two unnumbered tests with the same result are two tests.
        self::assertSame([
            ['actual_no' => '1', 'test_result' => 'Negative', 'lab_id' => null],
            ['actual_no' => null, 'test_result' => 'Scanty', 'lab_id' => null],
            ['actual_no' => null, 'test_result' => 'Scanty', 'lab_id' => null],
        ], $this->rows());
    }

    public function testANewResultForATestNumberUpdatesThatTest(): void
    {
        $service = new TbTestsService(LegacyAppHarness::db());

        $service->saveApiTests($this->tbId, [['actualNo' => '1', 'testResult' => 'Negative']]);
        $service->saveApiTests($this->tbId, [['actualNo' => '1', 'testResult' => '1+']]);

        self::assertSame([['actual_no' => '1', 'test_result' => '1+', 'lab_id' => null]], $this->rows());
    }

    public function testAChangedUnnumberedResultReplacesTheOldOne(): void
    {
        $service = new TbTestsService(LegacyAppHarness::db());

        $service->saveApiTests($this->tbId, [['testResult' => 'Scanty'], ['testResult' => '1+']]);
        $service->saveApiTests($this->tbId, [['testResult' => '1+'], ['testResult' => 'Negative']]);

        self::assertSame([
            ['actual_no' => null, 'test_result' => 'Negative', 'lab_id' => null],
            ['actual_no' => null, 'test_result' => '1+', 'lab_id' => null],
        ], $this->rows());
    }

    public function testEmptyResultsAreIgnored(): void
    {
        (new TbTestsService(LegacyAppHarness::db()))
            ->saveApiTests($this->tbId, [['actualNo' => '1', 'testResult' => ''], 'not a test']);

        self::assertSame([], $this->rows());
    }
}
