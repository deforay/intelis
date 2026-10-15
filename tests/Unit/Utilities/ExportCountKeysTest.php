<?php

declare(strict_types=1);

namespace Tests\Unit\Utilities;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * An export that hands ExportJobUtility a row-count key trusts that count: 0 answers
 * "no data" without running the query, and the size picks inline or background.
 *
 * Several modules share one key (highViralResultCount is written by the EID, CD4,
 * COVID-19 and Hepatitis result listings), so an export whose own listing never
 * writes the key reads whatever another module's listing left behind. The VL high
 * viral load export did, and refused to export after an EID search found nothing.
 */
final class ExportCountKeysTest extends TestCase
{
    private static function appPath(): string
    {
        return dirname(__DIR__, 3) . '/app';
    }

    /** @return array<string, array{string, string}> export file => [file, count key] */
    public static function exportsReadingACount(): array
    {
        $cases = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::appPath()));
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $source = (string) file_get_contents($file->getPathname());
            if (!preg_match('/queueRequested\(__FILE__[^)]*\)/', $source, $call)) {
                continue;
            }
            preg_match_all("/'([A-Za-z_]+Count)'/", $call[0], $keys);
            foreach ($keys[1] as $key) {
                $relative = substr($file->getPathname(), strlen(self::appPath()) + 1);
                $cases["$relative reads $key"] = [$file->getPathname(), $key];
            }
        }
        ksort($cases);
        return $cases;
    }

    public function testThereAreExportsToCheck(): void
    {
        $this->assertGreaterThan(10, count(self::exportsReadingACount()));
    }

    #[DataProvider('exportsReadingACount')]
    public function testTheExportsOwnFolderWritesTheCount(string $export, string $key): void
    {
        $writers = [];
        foreach (glob(dirname($export) . '/*.php') ?: [] as $file) {
            $source = (string) file_get_contents($file);
            if (preg_match('/\$_SESSION\[\'' . preg_quote($key, '/') . '\'\]\s*=/', $source)) {
                $writers[] = basename($file);
            }
        }

        $this->assertNotEmpty(
            $writers,
            "Nothing beside " . basename($export) . " writes \$_SESSION['$key'], so it reads another listing's count"
        );
    }
}
