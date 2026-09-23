<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\InterfacingService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * How a GeneXpert MTB/RIF Ultra result stored by the Interfacing Tool is read.
 *
 * The tool stores the first outcome that has a value as the result and the others
 * in the notes: a detected run reads "DETECTED LOW" with "RIF Resistance NOT
 * DETECTED" in the notes, a trace run "MTB Trace DETECTED" with "RIF Resistance
 * INDETERMINATE". Rows stored before Interfacing Tool 4.7.0 have no notes, so a
 * detected result from then carries no rifampicin reading at all.
 */
final class InterfacingTbReadingTest extends TestCase
{
    /** @return iterable<string, array{string, string, ?string, ?string}> */
    public static function results(): iterable
    {
        // result, notes, per-test form entry, Xpert code
        yield 'negative' => ['NOT DETECTED', '', 'MTB not detected', 'N'];
        yield 'trace' => [
            'MTB Trace DETECTED',
            'RIF Resistance INDETERMINATE',
            'MTB detected TRACE/RIF indeterminate',
            'TT'
        ];
        yield 'low, sensitive' => [
            'DETECTED LOW',
            'RIF Resistance NOT DETECTED',
            'MTB Detected Low/RIF not detected',
            'T'
        ];
        yield 'very low, resistant' => [
            'DETECTED VERY LOW',
            'RIF Resistance DETECTED',
            'MTB Detected Very Low/RIF detected',
            'RR'
        ];
        yield 'medium, form capitalises differently' => [
            'DETECTED MEDIUM',
            'RIF Resistance NOT DETECTED',
            'MTB Detected Medium/RIF Not Detected',
            'T'
        ];
        yield 'high, resistant' => ['DETECTED HIGH', 'RIF Resistance DETECTED', 'MTB Detected High/RIF Detected', 'RR'];
        yield 'rifampicin indeterminate is not on the per-test form' => [
            'DETECTED LOW',
            'RIF Resistance INDETERMINATE',
            null,
            'TI'
        ];
        yield 'MTB/RIF without a level' => ['DETECTED', 'RIF Resistance DETECTED', null, 'RR'];
        yield 'error, with the error in the notes' => [
            'ERROR',
            'MTB Trace ERROR | RIF Resistance ERROR | Error 2014: probe check failed',
            'No result/ invalid',
            'I'
        ];
        yield 'invalid' => ['INVALID', '', 'No result/ invalid', 'I'];
        yield 'no result' => ['NO RESULT', '', 'No result/ invalid', 'I'];
    }

    #[DataProvider('results')]
    public function testAResultIsReadIntoWhatEachFormRecords(
        string $result,
        string $notes,
        ?string $perTestForm,
        ?string $code
    ): void {
        $reading = InterfacingService::readXpertMtbRif($result, $notes);

        self::assertNotNull($reading);
        self::assertSame($perTestForm, InterfacingService::perTestFormUltraResult($reading));
        self::assertSame($code, InterfacingService::xpertResultCode($reading));
    }

    public function testADetectedResultWithoutItsRifampicinReadingIsNotRead(): void
    {
        // How a detected run was stored before Interfacing Tool 4.7.0.
        self::assertNull(InterfacingService::readXpertMtbRif('DETECTED LOW', ''));
        self::assertNull(InterfacingService::readXpertMtbRif('DETECTED LOW', null));
    }

    public function testTheRifampicinReadingIsNotTakenFromAnotherOutcome(): void
    {
        // "NOT DETECTED" must not be read as "DETECTED".
        self::assertSame(
            'not_detected',
            InterfacingService::readXpertMtbRif('DETECTED LOW', 'RIF Resistance NOT DETECTED')['rif']
        );
    }

    public function testAnythingElseIsNotRead(): void
    {
        self::assertNull(InterfacingService::readXpertMtbRif('', ''));
        self::assertNull(InterfacingService::readXpertMtbRif(null, null));
        self::assertNull(InterfacingService::readXpertMtbRif('Target Not Detected', ''));
        self::assertNull(InterfacingService::readXpertMtbRif('DETECTED SOMEWHAT', 'RIF Resistance DETECTED'));
    }

    public function testTheResultIsReadWhateverItsCaseAndSpacing(): void
    {
        self::assertSame(
            ['mtb' => 'detected', 'level' => 'Very Low', 'rif' => 'not_detected'],
            InterfacingService::readXpertMtbRif(' detected  very low ', 'rif resistance not detected')
        );
    }

    public function testAPoolListsItsMembersHoweverTheyAreSeparated(): void
    {
        self::assertSame(['0734', '0735', '0736', '0737'], InterfacingService::poolMembers('0734,0735,0736,0737'));
        self::assertSame(['A12P', 'B34-N'], InterfacingService::poolMembers('A12P, B34-N'));
        self::assertSame(['732', '733'], InterfacingService::poolMembers('732,,733,732,'));
        self::assertSame(['0733'], InterfacingService::poolMembers('0733'));
        self::assertSame(['Xpert M 000000000001'], InterfacingService::poolMembers('Xpert M 000000000001'));
    }
}
