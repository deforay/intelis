<?php

declare(strict_types=1);

namespace Tests\Unit\Utilities;

use App\Utilities\ClinicReportUtility;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The clinic and data quality reports link to each type's request edit page.
 * When a page moves to SampleAccessService::load(), it refuses the old id
 * formats, so the report must build its link with SampleAccessService too.
 * EID (#476) and CD4 (#504) moved without it, and Edit opened "This link is
 * not valid".
 */
final class ClinicReportEditLinkTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function types(): iterable
    {
        foreach (['vl', 'eid', 'covid19', 'hepatitis', 'tb', 'cd4', 'generic-tests'] as $type) {
            yield $type => [$type];
        }
    }

    #[DataProvider('types')]
    public function testTheLinkUsesTheFormatTheEditPageReads(string $type): void
    {
        $cfg = ClinicReportUtility::type($type);
        $page = (string) file_get_contents(dirname(__DIR__, 3) . '/app' . $cfg['edit']);
        self::assertNotSame('', $page, "{$cfg['edit']} not found");

        $pageUsesSampleAccess = str_contains($page, 'sampleAccess->load(');
        self::assertSame(
            $pageUsesSampleAccess,
            $cfg['editId'] === 'sample',
            $pageUsesSampleAccess
                ? "{$cfg['edit']} opens the sample with SampleAccessService, so set 'editId' => 'sample' for $type"
                : "{$cfg['edit']} does not use SampleAccessService, so its link cannot be 'sample'"
        );
    }
}
