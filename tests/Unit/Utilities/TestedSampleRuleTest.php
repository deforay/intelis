<?php

declare(strict_types=1);

namespace Tests\Unit\Utilities;

use App\Services\TestsService;
use App\Utilities\TestedSampleRule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClassConstant;

/**
 * A sample with any result entered is tested, whichever column the result went
 * into: a missed column lets a move or a withdrawal discard that result.
 */
final class TestedSampleRuleTest extends TestCase
{
    /** @return iterable<string, array{string, array<string, mixed>}> */
    public static function oneResultEach(): iterable
    {
        $columns = (new ReflectionClassConstant(TestedSampleRule::class, 'RESULT_COLUMNS'))->getValue();
        foreach ($columns as $testType => $list) {
            foreach ($list as $column) {
                yield "$testType $column" => [$testType, [$column => '1', 'result_status' => 6]];
            }
        }
    }

    /** @param array<string, mixed> $record */
    #[DataProvider('oneResultEach')]
    public function testAnyResultColumnMakesTheSampleTested(string $testType, array $record): void
    {
        self::assertTrue(TestedSampleRule::isTested($testType, $record));
    }

    public function testTheResultsMissedBeforeAreCounted(): void
    {
        self::assertTrue(TestedSampleRule::isTested('cd4', ['cd4_result_percentage' => '12']));
        self::assertTrue(TestedSampleRule::isTested('cd4', ['crag_test_results' => 'negative']));
        self::assertTrue(TestedSampleRule::isTested('hepatitis', ['hcv_vl_count' => '1500']));
        self::assertTrue(TestedSampleRule::isTested('hepatitis', ['hbv_vl_count' => '20']));
        self::assertTrue(TestedSampleRule::isTested('tb', ['xpert_mtb_result' => 'MTB DETECTED']));
    }

    public function testRequestHistoryIsNotATest(): void
    {
        self::assertFalse(TestedSampleRule::isTested('vl', [
            'result_status' => 6, 'last_viral_load_result' => '1200', 'last_vl_result_routine' => '40',
        ]));
        self::assertFalse(TestedSampleRule::isTested('eid', [
            'result_status' => 9, 'mother_vl_result' => '1000', 'previous_pcr_result' => 'negative',
            'rapid_test_result' => 'positive',
        ]));
        self::assertFalse(TestedSampleRule::isTested('cd4', [
            'last_cd4_result' => '300', 'last_cd4_crag_result' => 'x',
        ]));
    }

    public function testADecidedStatusOrATestDateIsTested(): void
    {
        foreach ([4, 5, 7, 8, 11] as $status) {
            self::assertTrue(TestedSampleRule::isTested('vl', ['result_status' => $status]), "status $status");
        }
        self::assertTrue(TestedSampleRule::isTested('vl', ['sample_tested_datetime' => '2026-08-07 09:00:00']));
        self::assertFalse(TestedSampleRule::isTested('vl', ['result_status' => 6, 'result' => '  ']));
    }

    public function testATestRowWithAResultIsTestedAndAnEmptyOneIsNot(): void
    {
        self::assertTrue(TestedSampleRule::isTested('tb', [], [['test_result' => 'Positive']]));
        self::assertFalse(TestedSampleRule::isTested('tb', [], [['test_result' => null]]));
        // COVID-19 rows arrive keyed by sample and then by test.
        self::assertTrue(TestedSampleRule::isTested('covid19', [], ['55' => ['9' => ['result' => 'positive']]]));
        self::assertTrue(TestedSampleRule::isTested('generic-tests', [], [['result' => 'Reactive']]));
    }

    public function testEveryListedColumnExistsOnItsTable(): void
    {
        $schema = (string) file_get_contents(dirname(__DIR__, 3) . '/sql/init.sql');
        $columns = (new ReflectionClassConstant(TestedSampleRule::class, 'RESULT_COLUMNS'))->getValue();
        foreach ($columns as $testType => $list) {
            $table = TestsService::getTestTableName($testType);
            self::assertSame(1, preg_match("/CREATE TABLE `$table` \\((.*?)\\) ENGINE=/s", $schema, $match), $table);
            foreach ($list as $column) {
                self::assertStringContainsString("`$column`", $match[1], "$table.$column");
            }
        }
    }
}
