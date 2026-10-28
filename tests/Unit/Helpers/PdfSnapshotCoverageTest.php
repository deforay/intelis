<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use PHPUnit\Framework\TestCase;
use Tests\Support\PdfOutputTestCase;

/**
 * The PDF snapshot cases are split across several classes. A snapshot that no
 * class lists is never compared, and nothing else would notice.
 */
final class PdfSnapshotCoverageTest extends TestCase
{
    public function testEverySnapshotIsCheckedExactlyOnce(): void
    {
        $tests = dirname(__DIR__, 2);
        $listed = [];
        foreach (glob($tests . '/Integration/Pdf*OutputTest.php') ?: [] as $file) {
            $class = 'Tests\\Integration\\' . basename($file, '.php');
            if (!is_subclass_of($class, PdfOutputTestCase::class)) {
                continue;
            }
            foreach ($class::pdfProvider() as [$name]) {
                $listed[] = $name;
            }
        }

        $snapshots = array_map(
            static fn(string $file): string => basename($file, '.json'),
            glob($tests . '/Fixtures/pdf/*.json') ?: []
        );

        sort($listed);
        sort($snapshots);
        self::assertNotSame([], $snapshots);
        self::assertSame($snapshots, $listed);
    }
}
