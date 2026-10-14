<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\VlService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A VL request edit whose form sent no result must not blank the one on record.
 *
 * The request forms disable the result box until a testing platform is chosen, and a
 * browser does not submit a disabled input, so saving such a form used to write the
 * stored result over with nothing.
 */
final class VlServiceKeepSavedResultTest extends TestCase
{
    /**
     * @return array<string, array{0: array<string, mixed>, 1: bool}>
     */
    public static function postProvider(): array
    {
        $request = ['vlSampleId' => '7', 'sampleCode' => 'VL001', 'labId' => '3'];

        return [
            'result box disabled, nothing sent' => [$request, true],
            'accepted, result box disabled' => [$request + ['isSampleRejected' => 'no'], true],
            'result sent' => [$request + ['vlResult' => '1200'], false],
            'result box cleared on purpose' => [$request + ['vlResult' => null], false],
            // vlLog and hivDetection stay enabled beside a disabled result box and are
            // posted on every save; they must not stand in for the result
            'result box disabled, log posted' => [$request + ['vlLog' => '3.08'], true],
            'result box disabled, empty log posted' => [$request + ['vlLog' => null], true],
            'result box disabled, detection posted' => [$request + ['hivDetection' => 'HIV-1 Detected'], true],
            'result and log sent' => [$request + ['vlResult' => '1200', 'vlLog' => '3.08'], false],
            'PNG CPHL result' => [$request + ['cphlVlResult' => '< 20'], false],
            'PNG final result' => [$request + ['finalViralLoadResult' => '400'], false],
            'rejected, result box hidden' => [$request + ['isSampleRejected' => 'yes'], false],
        ];
    }

    /**
     * @param array<string, mixed> $post
     */
    #[DataProvider('postProvider')]
    public function testKeepsTheSavedResultOnlyWhenTheFormSentNone(array $post, bool $keep): void
    {
        self::assertSame($keep, VlService::mustKeepSavedResult($post));
    }

    public function testDropsEveryResultColumnAndNothingElse(): void
    {
        $vlData = [
            'result' => null,
            'cphl_vl_result' => null,
            'result_value_log' => null,
            'result_value_absolute' => null,
            'result_value_absolute_decimal' => null,
            'result_value_text' => null,
            'result_value_hiv_detection' => '',
            'vl_result_category' => null,
            'result_status' => 6,
            'is_sample_rejected' => 'no',
            'tested_by' => 'lab-user',
            'lab_id' => '3',
        ];

        self::assertSame(
            ['result_status' => 6, 'is_sample_rejected' => 'no', 'tested_by' => 'lab-user', 'lab_id' => '3'],
            VlService::withoutResultColumns($vlData)
        );
    }
}
