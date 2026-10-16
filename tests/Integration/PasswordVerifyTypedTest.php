<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Registries\ContainerRegistry;
use App\Services\UsersService;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyAppHarness;

/**
 * A password with & or < signs in whichever way it was stored. Until 2026-08-12
 * the forms hashed the HTML-sanitized text ("Pass&amp;word1"); since then they
 * hash what was typed. Sign-in compares what was typed and falls back to the
 * old form, keeping that hash as it is.
 *
 * Every test runs in its own process.
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class PasswordVerifyTypedTest extends TestCase
{
    private const DATABASE = 'intelis_password_verify_typed_test';

    private const TYPED = 'Pass&word1';

    private static function booted(): bool
    {
        return getenv('INTELIS_TEST_DB_HOST') !== false
            && getenv('INTELIS_TEST_DB_HOST') !== ''
            && getenv('INTELIS_TEST_DB_USER') !== false
            && getenv('INTELIS_TEST_DB_USER') !== '';
    }

    protected function setUp(): void
    {
        if (!self::booted()) {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }
        $db = LegacyAppHarness::boot(self::DATABASE . '_' . getmypid(), ['roles', 'user_details']);
        $db->insert('roles', ['role_id' => 2, 'role_name' => 'Lab', 'role_code' => 'lab', 'status' => 'active']);
    }

    protected function tearDown(): void
    {
        if (self::booted()) {
            LegacyAppHarness::shutdown();
        }
    }

    private static function addUser(string $hashOf): void
    {
        LegacyAppHarness::db()->insert('user_details', [
            'user_id' => 'u-1',
            'user_name' => 'Test Person',
            'login_id' => 'tester',
            'password' => password_hash($hashOf, PASSWORD_BCRYPT),
            'role_id' => 2,
            'status' => 'active',
        ]);
    }

    private static function storedHash(): string
    {
        $row = LegacyAppHarness::db()->rawQueryOne("SELECT password FROM user_details WHERE user_id = 'u-1'");
        return (string) $row['password'];
    }

    private static function verify(string $typed): bool
    {
        return (bool) ContainerRegistry::get(UsersService::class)->passwordVerify('tester', $typed, self::storedHash());
    }

    #[RunInSeparateProcess]
    public function testTheOldFormIsWhatTheSanitizerMadeOfIt(): void
    {
        self::assertSame('Pass&amp;word1', UsersService::sanitizedAsBefore(self::TYPED));
    }

    #[RunInSeparateProcess]
    public function testAPasswordStoredAsTypedSignsIn(): void
    {
        self::addUser(self::TYPED);

        self::assertTrue(self::verify(self::TYPED));
    }

    #[RunInSeparateProcess]
    public function testAPasswordStoredInTheOldFormSignsInAndKeepsItsHash(): void
    {
        self::addUser(UsersService::sanitizedAsBefore(self::TYPED));
        $before = self::storedHash();

        self::assertTrue(self::verify(self::TYPED));
        // Rewriting it could lock out someone whose password really is "Pass&amp;word1"
        self::assertSame($before, self::storedHash());
    }

    #[RunInSeparateProcess]
    public function testARehashRewritesOnlyTheUserWhoSignedIn(): void
    {
        // login_id is not unique; a weaker-cost hash is rehashed on sign-in
        $db = LegacyAppHarness::db();
        foreach (['u-1' => self::TYPED, 'u-2' => 'Other&pass2'] as $userId => $password) {
            $db->insert('user_details', [
                'user_id' => $userId, 'user_name' => 'Twin', 'login_id' => 'tester',
                'password' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 4]),
                'role_id' => 2, 'status' => 'active',
            ]);
        }

        $users = ContainerRegistry::get(UsersService::class);
        self::assertTrue($users->passwordVerify('tester', self::TYPED, self::storedHash(), 'u-1'));

        $other = $db->rawQueryOne("SELECT password FROM user_details WHERE user_id = 'u-2'");
        self::assertTrue(password_verify('Other&pass2', (string) $other['password']), "u-2 keeps its own password");
    }

    #[RunInSeparateProcess]
    public function testAWrongPasswordStillFailsAndTheHashStays(): void
    {
        self::addUser(UsersService::sanitizedAsBefore(self::TYPED));
        $before = self::storedHash();

        self::assertFalse(self::verify('Pass&word2'));
        self::assertSame($before, self::storedHash());
    }

    #[RunInSeparateProcess]
    public function testTypingTheOldFormDoesNotSignIn(): void
    {
        // The old form is only ever derived from what was typed, never accepted as typed
        self::addUser(self::TYPED);

        self::assertFalse(self::verify('Pass&amp;word1'));
    }
}
