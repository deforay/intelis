<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\TestsService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Pages every module shares (batches, manifests, result import) looked the same
 * for each module, so labs worked on the CD4 page with a TB code. Their heading
 * now names the module the page is serving.
 */
final class ModuleHeadingTagTest extends TestCase
{
    /** Pages opened from each module's menu with the module in the query string. */
    private const SHARED_PAGES = [
        'app/batch/batches.php',
        'app/batch/add-batch.php',
        'app/batch/edit-batch.php',
        'app/batch/add-batch-position.php',
        'app/batch/edit-batch-position.php',
        'app/specimen-referral-manifest/view-manifests.php',
        'app/specimen-referral-manifest/add-manifest.php',
        'app/specimen-referral-manifest/edit-manifest.php',
        'app/specimen-referral-manifest/move-manifest.php',
        'app/import-result/imported-results.php',
        'app/import-result/import-file.php',
        'app/common/reference/recommended-corrective-actions.php',
    ];

    /** @return iterable<string, array{string}> */
    public static function sharedPages(): iterable
    {
        foreach (self::SHARED_PAGES as $path) {
            yield $path => [$path];
        }
    }

    #[DataProvider('sharedPages')]
    public function testTheHeadingNamesTheModule(string $path): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 3) . '/' . $path);

        self::assertMatchesRegularExpression(
            '~<h1>(?:(?!</h1>).)*TestsService::moduleHeadingTag\(~s',
            $source,
            "$path must name the module in its <h1>."
        );
    }

    public function testEveryModuleHasATag(): void
    {
        foreach (['vl', 'eid', 'covid19', 'covid-19', 'hepatitis', 'tb', 'cd4', 'generic-tests'] as $testType) {
            $tag = TestsService::moduleHeadingTag($testType);
            self::assertStringContainsString('module-heading-tag', $tag, $testType);
        }
        self::assertStringContainsString('Clusters of Differentiation 4 (CD4)', TestsService::moduleHeadingTag('cd4'));
    }

    public function testAnUnknownOrMissingTypeHasNoTag(): void
    {
        self::assertSame('', TestsService::moduleHeadingTag(null));
        self::assertSame('', TestsService::moduleHeadingTag(''));
        self::assertSame('', TestsService::moduleHeadingTag('<script>'));
    }
}
