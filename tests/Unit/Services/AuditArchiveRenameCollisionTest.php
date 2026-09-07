<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\AuditArchiveService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * What a reader does when two archived columns resolve to one current name.
 *
 * This is the seam between two features that were built separately and only
 * collide once both are switched on:
 *
 *   - A rewrite takes the union of the audit table's columns and the archive
 *     file's own, so a column dropped from the form keeps its history instead
 *     of being blanked on the next append.
 *   - Read-time aliases (audit_column_aliases) map a column's historical name
 *     onto its current one, so revisions written before a rename still display
 *     under the name the column has now.
 *
 * Put together, a renamed column is in the file twice -- old name and new name
 * -- and the alias map sends both to the new name. Only one of the pair is
 * filled in on any given revision: the old name before the rename, the new name
 * after it. Assigning blindly let the last header win, and the union appends
 * retired columns after the table's own, so the last header is the OLD name --
 * empty on every revision since the rename. Every post-rename value would have
 * read back blank.
 *
 * Latent rather than live: nothing writes to audit_column_aliases yet, so the
 * map is empty on every install and no header collides today. It arms itself
 * the moment the first alias is registered, which is the point of the feature.
 * Hence a test now, while the reasoning is written down.
 *
 * assembleRow() is private and static, so it is reached by reflection without
 * a database or a container -- and it is what readAuditDataFromCsvFlexible()
 * calls, not a copy of the rule.
 */
final class AuditArchiveRenameCollisionTest extends TestCase
{
    private static function assemble(array $headers, array $row): array
    {
        $method = new ReflectionMethod(AuditArchiveService::class, 'assembleRow');
        return $method->invoke(null, $headers, $row);
    }

    /** A revision written BEFORE the rename: the value is under the old name. */
    public function testAPreRenameValueSurfacesUnderTheCurrentName(): void
    {
        // Both headers resolved to 'sample_type'; the retired one comes last.
        $row = self::assemble(['sample_type', 'revision'], ['', '1']);
        $this->assertSame('', $row['sample_type'], 'Nothing recorded under either name yet.');

        $row = self::assemble(['sample_type', 'revision', 'sample_type'], ['', '1', 'plasma']);
        $this->assertSame(
            'plasma',
            $row['sample_type'],
            'The old name holds the value on a pre-rename revision, and that is the value.'
        );
    }

    /**
     * A revision written AFTER the rename. This is the case that regressed.
     *
     * The current column holds the value and the retired one is empty, so a
     * blind assignment blanks it.
     */
    public function testAPostRenameValueIsNotBlankedByTheEmptyRetiredColumn(): void
    {
        $row = self::assemble(['sample_type', 'revision', 'sample_type'], ['serum', '4', '']);

        $this->assertSame(
            'serum',
            $row['sample_type'],
            'The empty retired column must not overwrite the value recorded since the rename.'
        );
    }

    /** Neither side filled in stays empty rather than becoming missing. */
    public function testAColumnEmptyOnBothSidesIsStillPresent(): void
    {
        $row = self::assemble(['sample_type', 'sample_type'], ['', '']);

        $this->assertArrayHasKey('sample_type', $row, 'The column is still a column.');
        $this->assertSame('', $row['sample_type']);
    }

    /**
     * With both filled in, the current column wins.
     *
     * Should not arise -- a revision writes one name or the other -- but the
     * answer must be defined, and the table's own column is the authority.
     */
    public function testWhenBothAreFilledTheCurrentColumnWins(): void
    {
        $row = self::assemble(['sample_type', 'sample_type'], ['serum', 'plasma']);

        $this->assertSame('serum', $row['sample_type'], 'The union puts the current column first.');
    }

    /** Ordinary rows, where nothing collides, are unchanged. */
    public function testRowsWithoutACollisionAreUnaffected(): void
    {
        $this->assertSame(
            ['action' => 'edit', 'revision' => '2', 'result' => '40'],
            self::assemble(['action', 'revision', 'result'], ['edit', '2', '40'])
        );
    }

    /**
     * A short row still yields every column.
     *
     * Legacy files exist whose rows carry fewer cells than the header, and the
     * reader has always padded them rather than dropping the columns.
     */
    public function testAShortRowIsPaddedRatherThanTruncated(): void
    {
        $this->assertSame(
            ['action' => 'edit', 'revision' => '', 'result' => ''],
            self::assemble(['action', 'revision', 'result'], ['edit'])
        );
    }

    /**
     * "null" is a value the archiver writes, not a way of saying empty.
     *
     * json_encode(null) is the string "null", and the reader shows it as-is.
     * Treating it as absent would let a retired column overwrite it.
     */
    public function testALiteralNullIsAValueAndIsNotOverwritten(): void
    {
        $row = self::assemble(['sample_type', 'sample_type'], ['null', '']);

        $this->assertSame('null', $row['sample_type'], 'A recorded null is a recorded value.');
    }
}
