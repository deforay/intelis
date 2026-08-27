<?php

declare(strict_types=1);

namespace Tests\Unit\Utilities;

use App\Utilities\ActivityLogUtility;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ActivityLogUtilityTest extends TestCase
{
    public static function actionTypeProvider(): array
    {
        return [
            'failed login is login-fail, not login' => ['Login Failed', 'login-fail'],
            'plain login' => ['User Login', 'login'],
            'logout' => ['User Logout', 'logout'],
            'log-out with hyphen' => ['User Log-Out', 'logout'],
            'delete' => ['Record Delete', 'delete'],
            'remove maps to delete' => ['Item Removed', 'delete'],
            'import' => ['Data Import', 'import'],
            'add maps to create' => ['Record Add', 'create'],
            'create' => ['User Created', 'create'],
            'update' => ['Record Update', 'update'],
            'edit maps to update' => ['Record Edit', 'update'],
            'modif maps to update' => ['Record Modified', 'update'],
            'export maps to download' => ['Report Export', 'download'],
            'download' => ['File Download', 'download'],
            'mail maps to message' => ['Mail Sent', 'message'],
            'email maps to message' => ['Email Notification', 'message'],
            'unmatched event falls back to other' => ['Some Random Event', 'other'],
            'empty string falls back to other' => ['', 'other'],
            'case insensitive matching' => ['LOGIN FAILED', 'login-fail'],
        ];
    }

    #[DataProvider('actionTypeProvider')]
    public function testActivityActionType(string $eventType, string $expected): void
    {
        $this->assertSame($expected, ActivityLogUtility::activityActionType($eventType));
    }

    public static function initialsProvider(): array
    {
        return [
            'two word name gives two initials' => ['John Smith', 'JS'],
            'single word name gives one initial' => ['Madonna', 'M'],
            'three word name still caps at two initials' => ['John Paul Smith', 'JP'],
            'extra whitespace between words is ignored' => ['John    Smith', 'JS'],
            'leading and trailing whitespace trimmed' => ['  John Smith  ', 'JS'],
            'lowercase input is upper-cased' => ['john smith', 'JS'],
            'empty string yields question mark' => ['', '?'],
            'whitespace-only string yields question mark' => ['   ', '?'],
        ];
    }

    #[DataProvider('initialsProvider')]
    public function testActivityInitials(string $name, string $expected): void
    {
        $this->assertSame($expected, ActivityLogUtility::activityInitials($name));
    }

    public function testNonAlphaLeadingCharacterIsSkipped(): void
    {
        // A name segment that doesn't start with a letter (e.g. a stray symbol) is
        // skipped rather than counted toward the initials.
        $result = ActivityLogUtility::activityInitials('123 Smith');
        $this->assertSame('S', $result);
    }
}
