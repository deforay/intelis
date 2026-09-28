<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\VlServiceFactory;

/**
 * A GeneXpert result is stored as the HIV detection followed by the figure, e.g.
 * "HIV-1 Detected 121", and the detection is also kept on its own in
 * result_value_hiv_detection. The web forms strip the detection off before showing the
 * result for editing, but an API client that posts a saved record back sends the whole
 * stored result along with hivDetection. Each such re-post used to put the detection in
 * front once more, and records built up "HIV-1 Detected HIV-1 Detected HIV-1 Detected 121".
 */
final class VlServiceHivDetectionPrefixTest extends TestCase
{
    /**
     * @return array<string, array{0: array<string, mixed>, 1: ?string}>
     */
    public static function composedResultProvider(): array
    {
        $detected = 'HIV-1 Detected';
        $notDetected = 'HIV-1 Not Detected';

        return [
            'web form: figure only' => [
                ['vlResult' => '121', 'hivDetection' => $detected],
                'HIV-1 Detected 121',
            ],
            'web form: not detected, no figure' => [
                ['vlResult' => '', 'hivDetection' => $notDetected],
                'HIV-1 Not Detected',
            ],
            'api re-post of a stored detected result' => [
                ['vlResult' => 'HIV-1 Detected 121', 'hivDetection' => $detected],
                'HIV-1 Detected 121',
            ],
            'api re-post of a stored not-detected result, via result' => [
                ['result' => 'HIV-1 Not Detected', 'hivDetection' => $notDetected],
                'HIV-1 Not Detected',
            ],
            'already repeated, mixed case and spelling' => [
                [
                    'vlResult' => 'HIV-1 Not Detected HIV-1 not detected HIV1 NotDetected',
                    'hivDetection' => $notDetected,
                ],
                'HIV-1 Not Detected',
            ],
            'already repeated, with a figure' => [
                ['vlResult' => 'HIV-1 Detected HIV-1 Detected HIV-1 Detected 121', 'hivDetection' => $detected],
                'HIV-1 Detected 121',
            ],
            'no detection given: result kept as entered' => [
                ['vlResult' => 'HIV-1 Detected 121'],
                'HIV-1 Detected 121',
            ],
            // A result that contradicts the chosen detection is left for a person to see,
            // not silently resolved in favour of either.
            'opposite detection in the result is not removed' => [
                ['vlResult' => 'HIV-1 Not Detected', 'hivDetection' => $detected],
                'HIV-1 Detected HIV-1 Not Detected',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $params
     */
    #[DataProvider('composedResultProvider')]
    public function testTheDetectionIsStoredOnce(array $params, ?string $expected): void
    {
        $processed = VlServiceFactory::build()->processViralLoadResultFromForm($params);

        $this->assertSame($expected, $processed['finalResult']);
    }

    public function testRepostingTheStoredResultLeavesItUnchanged(): void
    {
        $vlService = VlServiceFactory::build();

        foreach (['HIV-1 Detected' => '121', 'HIV-1 Not Detected' => ''] as $detection => $figure) {
            $stored = $vlService->processViralLoadResultFromForm(
                ['vlResult' => $figure, 'hivDetection' => $detection]
            )['finalResult'];

            for ($i = 0; $i < 3; $i++) {
                $reposted = $vlService->processViralLoadResultFromForm(
                    ['result' => $stored, 'hivDetection' => $detection]
                )['finalResult'];

                $this->assertSame($stored, $reposted);
            }
        }
    }
}
