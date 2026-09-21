<?php

declare(strict_types=1);

namespace Tests\Unit\Utilities;

use App\Utilities\ResultSyncAcknowledgement;
use PHPUnit\Framework\TestCase;

final class ResultSyncAcknowledgementTest extends TestCase
{
    public function testSentRowsFlattensNestedAndFlatPayloads(): void
    {
        $flat = [['vl_sample_id' => 1, 'sample_code' => 'A'], ['vl_sample_id' => 2, 'sample_code' => 'B']];
        self::assertSame($flat, ResultSyncAcknowledgement::sentRows($flat));

        $nested = [
            'uuid-1' => ['form_data' => ['tb_id' => 1, 'unique_id' => 'uuid-1'], 'data_from_tests' => []],
            'uuid-2' => ['form_data' => ['tb_id' => 2, 'unique_id' => 'uuid-2'], 'data_from_tests' => [['x' => 1]]],
        ];
        self::assertSame(
            [['tb_id' => 1, 'unique_id' => 'uuid-1'], ['tb_id' => 2, 'unique_id' => 'uuid-2']],
            ResultSyncAcknowledgement::sentRows($nested)
        );
    }

    public function testUniqueIdAcknowledgmentMatchesTheLabsOwnIdEvenWhenTheStsRenamedTheCode(): void
    {
        $sent = [
            ['id' => 1, 'unique_id' => 'u-1', 'sample_code' => 'X'],
            ['id' => 2, 'unique_id' => 'u-2', 'sample_code' => 'Y'],
        ];
        // The STS saved X as X-2; it now answers with the lab's unique_id.
        self::assertSame([$sent[0]], ResultSyncAcknowledgement::acknowledgedRows($sent, ['u-1'], true));
        // Under the legacy format, the renamed code matches nothing, which is the old loop.
        self::assertSame([], ResultSyncAcknowledgement::acknowledgedRows($sent, ['X-2'], false));
    }

    public function testRowsWithoutUniqueIdFallBackToSampleCodeUnderEitherFormat(): void
    {
        $sent = [
            ['id' => 1, 'unique_id' => null, 'sample_code' => 'OLD-1'],
            ['id' => 2, 'unique_id' => '', 'sample_code' => 'OLD-2'],
            ['id' => 3, 'unique_id' => 'u-3', 'sample_code' => 'NEW-3'],
        ];
        self::assertSame(
            [$sent[0], $sent[2]],
            ResultSyncAcknowledgement::acknowledgedRows($sent, ['OLD-1', 'u-3'], true)
        );
        // A row with a unique_id is not matched by its code under the unique_id format.
        self::assertSame([], ResultSyncAcknowledgement::acknowledgedRows([$sent[2]], ['NEW-3'], true));
    }

    public function testLegacyAcknowledgmentOnlyCoversRowsSentInTheRequest(): void
    {
        $sent = [['id' => 1, 'unique_id' => 'u-1', 'sample_code' => 'A']];
        // B was not in this request, so nothing about it may be marked.
        self::assertSame($sent, ResultSyncAcknowledgement::acknowledgedRows($sent, ['A', 'B'], false));
        self::assertSame([], ResultSyncAcknowledgement::acknowledgedRows($sent, ['B'], false));
    }

    public function testDuplicateCodesInOneRequestAreAllMatchedUnderTheLegacyFormat(): void
    {
        // Two labs on one instance can share a code; the old STS cannot say which saved.
        $sent = [
            ['id' => 1, 'unique_id' => 'u-1', 'sample_code' => 'A', 'lab_id' => 1],
            ['id' => 2, 'unique_id' => 'u-2', 'sample_code' => 'A', 'lab_id' => 2],
        ];
        self::assertSame($sent, ResultSyncAcknowledgement::acknowledgedRows($sent, ['A'], false));
        self::assertSame([$sent[1]], ResultSyncAcknowledgement::acknowledgedRows($sent, ['u-2'], true));
    }
}
