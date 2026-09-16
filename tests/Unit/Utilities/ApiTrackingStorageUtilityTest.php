<?php

declare(strict_types=1);

namespace Tests\Unit\Utilities;

use App\Utilities\ApiTrackingStorageUtility;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * The layout of var/track-api, and how it is cleared.
 *
 * Two flat folders there reached millions of files on an STS, and every job
 * that walked them kept the disk busy for hours. These tests hold the rules that
 * keep that from coming back: bodies go in one folder per day, expiry is decided
 * from folder names alone, and an old flat folder is renamed aside whole.
 */
final class ApiTrackingStorageUtilityTest extends TestCase
{
    protected function setUp(): void
    {
        $this->wipe(ApiTrackingStorageUtility::root());
        mkdir(ApiTrackingStorageUtility::kindRoot('requests'), 0775, true);
        mkdir(ApiTrackingStorageUtility::kindRoot('responses'), 0775, true);
    }

    protected function tearDown(): void
    {
        $this->wipe(ApiTrackingStorageUtility::root());
    }

    public function testBodiesGoInTheDayFolderOfTheCall(): void
    {
        self::assertSame(
            ApiTrackingStorageUtility::kindRoot('requests') . '/2026/09/16',
            ApiTrackingStorageUtility::dayDirectory('requests', '2026-09-16 23:59:59')
        );
    }

    public function testViewerLooksInTheDayFolderThenTheFlatFolder(): void
    {
        self::assertSame(
            [
                ApiTrackingStorageUtility::kindRoot('responses') . '/2025/01/02',
                ApiTrackingStorageUtility::kindRoot('responses'),
            ],
            ApiTrackingStorageUtility::candidateDirectories('responses', '2025-01-02 03:04:05')
        );
        self::assertSame(
            [ApiTrackingStorageUtility::kindRoot('responses')],
            ApiTrackingStorageUtility::candidateDirectories('responses', null)
        );
    }

    public function testOnlyDaysOlderThanRetentionExpire(): void
    {
        $root = ApiTrackingStorageUtility::kindRoot('requests');
        foreach (['2026/08/16', '2026/08/17', '2026/09/16', '2025/12/31'] as $day) {
            mkdir("$root/$day", 0775, true);
            touch("$root/$day/body.json.zst");
        }
        mkdir("$root/notes", 0775, true); // not a date: never touched

        $expired = ApiTrackingStorageUtility::expiredDayDirectories(
            'requests',
            new DateTimeImmutable('2026-09-16 10:00:00'),
            30
        );

        self::assertSame(["$root/2025/12/31", "$root/2026/08/16"], $expired);
    }

    public function testEmptyMonthAndYearFoldersAreRemoved(): void
    {
        $root = ApiTrackingStorageUtility::kindRoot('requests');
        mkdir("$root/2025/12", 0775, true);
        mkdir("$root/2026/09/16", 0775, true);

        ApiTrackingStorageUtility::removeEmptyDateFolders('requests');

        self::assertDirectoryDoesNotExist("$root/2025");
        self::assertDirectoryExists("$root/2026/09/16");
    }

    public function testFlatLayoutIsDetectedAndDatedLayoutIsNot(): void
    {
        $root = ApiTrackingStorageUtility::kindRoot('requests');
        mkdir("$root/2026/09/16", 0775, true);
        touch("$root/2026/09/16/a.json.zst");
        touch("$root/.gitkeep");

        self::assertFalse(ApiTrackingStorageUtility::hasFlatFiles('requests'));

        touch("$root/01abc.json.zst");
        self::assertTrue(ApiTrackingStorageUtility::hasFlatFiles('requests'));
    }

    public function testFlatFolderIsMovedAsideAndReplacedWithTheSameMode(): void
    {
        $root = ApiTrackingStorageUtility::kindRoot('responses');
        chmod($root, 0750);
        touch("$root/01abc.json.zst");

        $aside = ApiTrackingStorageUtility::moveAsideFlatFolder(
            'responses',
            new DateTimeImmutable('2026-09-16 14:00:00')
        );

        self::assertSame($root . '.purge-20260916140000', $aside);
        self::assertFileExists("$aside/01abc.json.zst");
        self::assertDirectoryExists($root);
        self::assertSame([], array_values(array_diff(scandir($root), ['.', '..'])));
        self::assertSame(0750, fileperms($root) & 0777);
        self::assertSame([$aside], ApiTrackingStorageUtility::pendingPurges());

        self::assertSame([], ApiTrackingStorageUtility::deleteAtLowPriority([$aside]));
        self::assertDirectoryDoesNotExist($aside);
        self::assertSame([], ApiTrackingStorageUtility::pendingPurges());
    }

    private function wipe(string $dir): void
    {
        if (is_dir($dir)) {
            exec('rm -rf ' . escapeshellarg($dir));
        }
    }
}
