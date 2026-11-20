<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\InstrumentsService;
use PHPUnit\Framework\TestCase;

final class ImportedResultPathTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        // Keep test runs out of the real uploads folder.
        if (!defined('UPLOAD_PATH')) {
            define('UPLOAD_PATH', sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'intelis-uploads-' . getmypid());
        }
    }

    private function importedResultsDir(): string
    {
        $dir = realpath(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'imported-results');
        $this->assertNotFalse($dir);
        return $dir;
    }

    public function testScriptNameGivesAPathInsideImportedResults(): void
    {
        $path = InstrumentsService::importedResultPath('abbott.php', 'xlsx');

        $this->assertSame($this->importedResultsDir(), dirname($path));
        $this->assertStringStartsWith('abbott-', basename($path));
        $this->assertStringEndsWith('.xlsx', $path);
    }

    public function testTraversalInScriptNameStaysInsideImportedResults(): void
    {
        $path = InstrumentsService::importedResultPath('../../../public/abbott.php', 'xlsx');

        $this->assertSame($this->importedResultsDir(), dirname($path));
        $this->assertStringNotContainsString('..', $path);
        $this->assertStringStartsWith('abbott-', basename($path));
    }

    public function testTwoCallsGiveTwoDifferentPaths(): void
    {
        $this->assertNotSame(
            InstrumentsService::importedResultPath('abbott.php', 'xlsx'),
            InstrumentsService::importedResultPath('abbott.php', 'xlsx')
        );
    }

    public function testExtensionKeepsOnlyLettersAndDigits(): void
    {
        $path = InstrumentsService::importedResultPath('x.php', 'php/../x');

        $this->assertSame($this->importedResultsDir(), dirname($path));
        $this->assertMatchesRegularExpression('/^x-[A-Za-z0-9]{12}\.[A-Za-z0-9]+$/', basename($path));
    }
}
