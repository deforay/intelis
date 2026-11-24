<?php

declare(strict_types=1);

namespace Tests\Unit\Utilities;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * sury_source_active in scripts/shared-functions.sh, run through bash.
 *
 * use_sury_repo returns early when the packages.sury.org source is there. It
 * used to grep every file under /etc/apt for the URL, so a source that
 * do-release-upgrade had commented out, a deb822 "Enabled: no", or a
 * .distUpgrade copy all counted as present, and the source was never put back.
 */
final class SurySourceActiveTest extends TestCase
{
    private const LINE = 'deb [signed-by=/k.gpg] https://packages.sury.org/php/ noble main';

    private string $dir = '';

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/intelis-sury-' . getmypid() . '-' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/sources.list.d', 0700, true);
        file_put_contents($this->dir . '/sources.list', '');
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/sources.list.d/*') ?: [] as $file) {
            unlink($file);
        }
        @unlink($this->dir . '/sources.list');
        @rmdir($this->dir . '/sources.list.d');
        @rmdir($this->dir);
    }

    /** @return array<string, array{bool, string, array<string, string>}> */
    public static function sources(): array
    {
        $deb822 = "Types: deb\nURIs: https://packages.sury.org/php/\nSuites: noble\nComponents: main\n";
        $other = "Types: deb\nURIs: https://x.example/\nSuites: noble\n";

        return [
            'an active .list' => [true, 'php', ['sources.list.d/sury-php.list' => self::LINE . "\n"]],
            'a line in sources.list' => [true, 'php', ['sources.list' => self::LINE . "\n"]],
            'commented out by a release upgrade' => [false, 'php', [
                'sources.list.d/sury-php.list' => '# ' . self::LINE . " # disabled on upgrade to noble\n",
            ]],
            'only a .distUpgrade copy' => [false, 'php', [
                'sources.list.d/sury-php.list.distUpgrade' => self::LINE . "\n",
            ]],
            'only a .save copy' => [false, 'php', ['sources.list.d/sury-php.list.save' => self::LINE . "\n"]],
            'a deb822 source' => [true, 'php', ['sources.list.d/sury.sources' => $deb822]],
            'a deb822 source with Enabled: yes' => [true, 'php', [
                'sources.list.d/sury.sources' => $deb822 . "Enabled: yes\n",
            ]],
            'a deb822 source with Enabled: no' => [false, 'php', [
                'sources.list.d/sury.sources' => $deb822 . "Enabled: no\n",
            ]],
            'a deb822 source in the second stanza' => [true, 'php', [
                'sources.list.d/sury.sources' => $other . "\n" . $deb822,
            ]],
            'a disabled deb822 source beside an enabled one' => [false, 'php', [
                'sources.list.d/sury.sources' => $deb822 . "Enabled: no\n\n" . $other,
            ]],
            'the apache2 source asked about php' => [false, 'php', [
                'sources.list.d/sury-apache2.list' => "deb https://packages.sury.org/apache2/ noble main\n",
            ]],
            'the apache2 source' => [true, 'apache2', [
                'sources.list.d/sury-apache2.list' => "deb https://packages.sury.org/apache2/ noble main\n",
            ]],
            'only a deb-src line' => [false, 'php', [
                'sources.list.d/sury-php.list' => "deb-src https://packages.sury.org/php/ noble main\n",
            ]],
            'no source at all' => [false, 'php', []],
        ];
    }

    /** @param array<string, string> $files */
    #[DataProvider('sources')]
    public function testOnlyASourceAptReadsCounts(bool $active, string $component, array $files): void
    {
        foreach ($files as $path => $content) {
            file_put_contents($this->dir . '/' . $path, $content);
        }

        $script = dirname(__DIR__, 3) . '/scripts/shared-functions.sh';
        $range = escapeshellarg('/^sury_source_active\(\) \{/,/^}/');
        $function = shell_exec('awk ' . $range . ' ' . escapeshellarg($script));
        $this->assertNotEmpty($function, 'scripts/shared-functions.sh has no sury_source_active.');

        $command = 'SURY_APT_DIR=' . escapeshellarg($this->dir) . ' bash -c '
            . escapeshellarg($function . "\nsury_source_active " . $component);
        exec($command, $output, $status);

        $this->assertSame($active ? 0 : 1, $status);
    }
}
