<?php

declare(strict_types=1);

namespace Tests\Unit\Utilities;

use App\Utilities\JsonUtility;
use App\Utilities\ResultSyncPayload;
use JsonMachine\Items;
use JsonMachine\JsonDecoder\ExtJsonDecoder;
use PHPUnit\Framework\TestCase;

final class ResultSyncPayloadTest extends TestCase
{
    private static function envelope(array $results): array
    {
        return ['labId' => 1, 'results' => $results, 'testType' => 'vl', 'instanceId' => 'test', 'timestamp' => 'now'];
    }

    public function testSmallPayloadAndItsEncodedJsonRemainUnchanged(): void
    {
        $rows = [7 => ['sample_code' => 'A'], 8 => ['sample_code' => 'B']];
        $requests = iterator_to_array(ResultSyncPayload::requests([$rows], self::envelope(...), 4096, fn() => 100));
        self::assertCount(1, $requests);
        self::assertSame(self::envelope($rows), $requests[0]['payload']);
        self::assertSame(JsonUtility::encodeUtf8Json(self::envelope($rows)), $requests[0]['json']);
        self::assertSame(strlen($requests[0]['json']), $requests[0]['bytes']);
        self::assertFalse($requests[0]['oversized']);
    }

    public function testByteLimitIncludesEnvelopeAndPreservesFlatKeysAndRecordOrder(): void
    {
        $rows = [];
        foreach (range(0, 5) as $id) {
            $rows[$id] = ['sample_code' => "S$id", 'result' => str_repeat('é', 80)];
        }
        $sent = [];
        $count = 0;
        foreach (ResultSyncPayload::requests([$rows], self::envelope(...), 500, fn() => 100) as $request) {
            self::assertLessThanOrEqual(500, strlen($request['json']));
            self::assertSame($request['payload'], json_decode($request['json'], true));
            foreach ($request['payload']['results'] as $key => $row) {
                self::assertArrayNotHasKey($key, $sent);
                $sent[$key] = $row;
            }
            $count++;
        }
        self::assertGreaterThan(1, $count);
        self::assertSame($rows, $sent);
    }

    public function testSplittingKeepsSamplesWholeAndOnlyAttachesTheirManifests(): void
    {
        $rows = [];
        foreach (['A', 'B', 'A'] as $id => $code) {
            $rows["uuid-$id"] = [
                'form_data' => ['sample_code' => "S$id", 'referral_manifest_code' => $code],
                'data_from_tests' => [['result' => str_repeat('x', 100)]],
            ];
        }
        $builds = 0;
        $build = static function (array $chunk) use (&$builds): array {
            $builds++;
            return self::envelope($chunk) + ['manifests' => [
                ['manifest_code' => 'A', 'notes' => str_repeat('a', 100)],
                ['manifest_code' => 'B', 'notes' => str_repeat('b', 100)],
            ]];
        };
        $sent = [];
        foreach (ResultSyncPayload::requests([$rows], $build, 600, fn() => 100) as $request) {
            self::assertLessThanOrEqual(600, $request['bytes']);
            self::assertCount(1, $request['payload']['results']);
            $row = reset($request['payload']['results']);
            self::assertSame(
                [$row['form_data']['referral_manifest_code']],
                array_column($request['payload']['manifests'], 'manifest_code')
            );
            $sent += $request['payload']['results'];
        }
        self::assertSame($rows, $sent);
        self::assertSame(1, $builds, 'Splitting must not query child data or manifests again.');
    }

    public function testOversizedSingleSampleIsFlaggedAndDoesNotDropFollowingSamples(): void
    {
        $rows = [
            'large' => ['sample_code' => 'A', 'result' => str_repeat('x', 2000)],
            'small' => ['sample_code' => 'B', 'result' => 'negative'],
        ];
        $requests = iterator_to_array(ResultSyncPayload::requests([$rows], self::envelope(...), 500, fn() => 100));
        self::assertCount(2, $requests);
        self::assertTrue($requests[0]['oversized']);
        self::assertFalse($requests[1]['oversized']);
        self::assertSame($rows, $requests[0]['payload']['results'] + $requests[1]['payload']['results']);
    }

    public function testResponseHintCanShrinkTheRemainingPreparedPayload(): void
    {
        $rows = array_map(static fn(int $id): array => ['sample_code' => "S$id"], range(1, 6));
        $size = 3;
        $sizes = [];
        $sent = [];
        $requests = ResultSyncPayload::requests([$rows], self::envelope(...), 4096, function () use (&$size): int {
            return $size;
        });
        foreach ($requests as $request) {
            $sizes[] = count($request['payload']['results']);
            $sent += $request['payload']['results'];
            $size = 1;
        }
        self::assertSame([3, 1, 1, 1], $sizes);
        self::assertSame($rows, $sent);
    }

    public function testDryRunDoesNotPrepareOrSerializeAnyPayload(): void
    {
        $requests = ResultSyncPayload::requests([], static function (): never {
            self::fail('Empty input must not build a payload.');
        }, 4096, fn() => 100);
        self::assertSame([], iterator_to_array($requests));
    }

    public function testDecodedAndLegacyInputsMatchThePreviousJsonMachineParser(): void
    {
        foreach (
            [
            self::envelope([['sample_code' => 'A', 'result' => 'é', 'value' => 0, 'flag' => false]]),
            self::envelope(['uuid' => ['form_data' => ['sample_code' => 'B'], 'data_from_tests' => []]]),
            self::envelope([]),
            ] as $payload
        ) {
            $json = JsonUtility::encodeUtf8Json($payload);
            $legacy = iterator_to_array(Items::fromString($json, ['decoder' => new ExtJsonDecoder(true)]));
            self::assertSame($legacy, ResultSyncPayload::decode($json));
            self::assertSame($legacy, ResultSyncPayload::decode($payload));
        }
        foreach ([null, false, 123, 'broken JSON', 'null', '123'] as $invalid) {
            self::assertNull(ResultSyncPayload::decode($invalid));
        }
    }
}
