<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\TestsService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * When a lab pastes a manifest code on the wrong module's Add Samples page, the
 * error links to the right page. Each link must open the page that activates
 * manifests of that test type, or the lab lands on the wrong page again.
 */
final class ManifestActivationPageTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function manifestTestTypes(): iterable
    {
        foreach (['vl', 'eid', 'covid19', 'hepatitis', 'tb', 'cd4', 'generic-tests'] as $testType) {
            yield $testType => [$testType];
        }
    }

    #[DataProvider('manifestTestTypes')]
    public function testTheLinkOpensThePageForThatTestType(string $testType): void
    {
        $page = TestsService::getManifestActivationPage($testType);

        self::assertNotNull($page);
        self::assertNotSame('', $page['label']);
        $file = dirname(__DIR__, 3) . '/app' . $page['url'];
        self::assertFileExists($file);
        self::assertMatchesRegularExpression(
            "/'testType'\s*=>\s*'" . preg_quote($testType, '/') . "'/",
            (string) file_get_contents($file),
            "{$page['url']} must activate $testType manifests."
        );
    }

    public function testATestTypeWithoutThePageHasNoLink(): void
    {
        self::assertNull(TestsService::getManifestActivationPage('recency'));
        self::assertNull(TestsService::getManifestActivationPage(''));
    }
}
