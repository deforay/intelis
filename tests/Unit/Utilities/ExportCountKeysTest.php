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
 * An export whose own listing never writes the key reads whatever another module's
 * listing left behind. The VL high viral load export once did, and refused to export
 * after an EID search found nothing.
 *
 * Every key an export reads must also be written only by its own module: when the
 * EID, CD4, COVID-19 and Hepatitis listings all wrote highViralResult, the VL export
 * ran the EID query whenever the EID report was searched in another tab.
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

    /** @return array<string, array{string, string}> export file => [file, session key] */
    public static function exportsReadingAKey(): array
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
            preg_match_all("/'([A-Za-z0-9_]+)'/", $call[0], $keys);
            foreach ($keys[1] as $key) {
                $relative = substr($file->getPathname(), strlen(self::appPath()) + 1);
                $cases["$relative reads $key"] = [$file->getPathname(), $key];
            }
        }
        ksort($cases);
        return $cases;
    }

    #[DataProvider('exportsReadingAKey')]
    public function testNoOtherFolderWritesTheKey(string $export, string $key): void
    {
        $module = static fn(string $path): string
            => explode('/', substr($path, strlen(self::appPath()) + 1))[0];
        $ownModule = $module($export);
        $elsewhere = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::appPath()));
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php' || $module($file->getPathname()) === $ownModule) {
                continue;
            }
            $source = (string) file_get_contents($file->getPathname());
            if (preg_match('/\$_SESSION\[\'' . preg_quote($key, '/') . '\'\]\s*=/', $source)) {
                $elsewhere[] = substr($file->getPathname(), strlen(self::appPath()) + 1);
            }
        }

        $this->assertSame(
            [],
            $elsewhere,
            basename($export) . " reads \$_SESSION['$key'], which another module's listing also writes"
        );
    }
}
