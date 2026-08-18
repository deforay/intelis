<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use App\Services\RejectionReasonMappingService;

/**
 * The parts of the mapping that decide identity. The database-facing methods are
 * not covered here: DatabaseService is final, so it cannot be doubled, and a test
 * that reached a real database would be an integration test wearing this one's
 * clothes.
 */
final class RejectionReasonMappingTest extends TestCase
{
    public function testNamesThatDifferOnlyByCaseOrSpacingAreTheSameReason(): void
    {
        $expected = RejectionReasonMappingService::normalizeName('Tube cassé');

        $this->assertSame($expected, RejectionReasonMappingService::normalizeName('  Tube cassé  '));
        $this->assertSame($expected, RejectionReasonMappingService::normalizeName('TUBE CASSÉ'));
        $this->assertSame($expected, RejectionReasonMappingService::normalizeName("Tube\tcassé"));
        $this->assertSame($expected, RejectionReasonMappingService::normalizeName('Tube   cassé'));
    }

    /**
     * Accents and spelling are left alone on purpose. Merging "casse" into "cassé"
     * would be a guess, and a wrong merge cannot be undone once samples point at
     * the surviving row -- while a duplicate is a five-second edit.
     */
    public function testNamesThatDifferByAccentOrSpellingStayDistinct(): void
    {
        $this->assertNotSame(
            RejectionReasonMappingService::normalizeName('Tube cassé'),
            RejectionReasonMappingService::normalizeName('Tube casse')
        );
        $this->assertNotSame(
            RejectionReasonMappingService::normalizeName('Hemolysed sample'),
            RejectionReasonMappingService::normalizeName('Haemolysed sample')
        );
    }

    public function testAnEmptyNameNormalizesToNothingSoItIsNeverMatchedOn(): void
    {
        $this->assertSame('', RejectionReasonMappingService::normalizeName(null));
        $this->assertSame('', RejectionReasonMappingService::normalizeName(''));
        $this->assertSame('', RejectionReasonMappingService::normalizeName("   \t "));
    }

    public function testEveryModuleWithReasonsResolvesToItsOwnTable(): void
    {
        $this->assertSame('r_vl_sample_rejection_reasons', RejectionReasonMappingService::reasonTableFor('vl'));
        $this->assertSame(
            'r_generic_sample_rejection_reasons',
            RejectionReasonMappingService::reasonTableFor('generic-tests')
        );
        $this->assertNull(RejectionReasonMappingService::reasonTableFor('not-a-module'));
    }

    /** Recency shares form_vl and its reasons, so it must not be sent as a second copy. */
    public function testRecencyIsNotSyncedSeparatelyFromVl(): void
    {
        $syncable = RejectionReasonMappingService::syncableTestTypes();

        $this->assertContains('vl', $syncable);
        $this->assertNotContains('recency', $syncable);
        $this->assertSame(
            RejectionReasonMappingService::reasonTableFor('vl'),
            RejectionReasonMappingService::reasonTableFor('recency')
        );
    }

    /** Every syncable type must resolve to a distinct table, or a lab sends the same rows twice. */
    public function testSyncableTypesMapToDistinctTables(): void
    {
        $tables = array_map(
            static fn(string $t) => RejectionReasonMappingService::reasonTableFor($t),
            RejectionReasonMappingService::syncableTestTypes()
        );

        $this->assertNotContains(null, $tables);
        $this->assertSame($tables, array_unique($tables));
    }

    /** The payload key has to survive the round trip the receiver parses it back out of. */
    public function testPayloadKeyIdentifiesTheTestTypeItCameFrom(): void
    {
        foreach (RejectionReasonMappingService::syncableTestTypes() as $testType) {
            $key = RejectionReasonMappingService::payloadKeyFor($testType);
            $this->assertStringStartsWith('rejectionReasons:', $key);
            $this->assertSame($testType, substr($key, strlen('rejectionReasons:')));
        }
    }
}
