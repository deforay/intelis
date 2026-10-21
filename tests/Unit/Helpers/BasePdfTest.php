<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use App\Helpers\BasePdf;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * BasePdf drops HTML that would make TCPDF 6 fetch URLs, embed server files,
 * add form fields or script links, or throw. Each case renders real HTML and
 * reads the PDF TCPDF produced, so a case passes only if TCPDF itself never
 * acted on the tag.
 */
final class BasePdfTest extends TestCase
{
    // A 1x1 RGB PNG
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGP4z8AAAAMBAQDJ/pLvAAAAAElFTkSuQmCC';

    private string $documentRoot;
    private string $insideFile;
    private string $outsideFile;

    protected function setUp(): void
    {
        $this->documentRoot = (string) ($_SERVER['DOCUMENT_ROOT'] ?? '');

        $folder = WEB_ROOT . '/uploads/base-pdf-test';
        if (!is_dir($folder)) {
            mkdir($folder, 0777, true);
        }
        $this->insideFile = $folder . '/signature.png';
        file_put_contents($this->insideFile, base64_decode(self::PNG));

        $this->outsideFile = VAR_PATH . '/base-pdf-test-outside.png';
        file_put_contents($this->outsideFile, base64_decode(self::PNG));

        $link = $folder . '/link-to-outside.png';
        if (!is_link($link)) {
            symlink($this->outsideFile, $link);
        }
    }

    protected function tearDown(): void
    {
        $_SERVER['DOCUMENT_ROOT'] = $this->documentRoot;
    }

    private static function render(string $html): string
    {
        $pdf = new BasePdf();
        $pdf->setCompression(false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();
        $pdf->writeHTML($html);
        return $pdf->Output('', 'S');
    }

    private static function imageCount(string $pdf): int
    {
        // Count placements, since TCPDF stores identical image data once
        return preg_match_all('#/I\d+ Do#', $pdf);
    }

    public function testImagesTheResultPdfsUseStillRender(): void
    {
        $_SERVER['DOCUMENT_ROOT'] = WEB_ROOT;

        $pdf = self::render(
            '<img src="/uploads/base-pdf-test/signature.png"/>'
            . '<img src="' . $this->insideFile . '"/>'
            . '<img src="data:image/png;base64,' . self::PNG . '"/>'
            . '<img src="@' . self::PNG . '"/>'
        );

        $this->assertSame(4, self::imageCount($pdf));
    }

    public static function blockedImageProvider(): array
    {
        // An SVG that embeds a server file outside WEB_ROOT
        $svg = fn (self $test): string => base64_encode(
            '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="10" height="10">'
            . '<image xlink:href="' . $test->outsideFile . '" x="0" y="0" width="10" height="10"/></svg>'
        );

        return [
            'a server file outside WEB_ROOT' => [fn (self $test): string => $test->outsideFile],
            'a link inside WEB_ROOT to a file outside it' => [
                fn (): string => WEB_ROOT . '/uploads/base-pdf-test/link-to-outside.png',
            ],
            'an SVG data image' => [fn (self $test): string => 'data:image/svg+xml;base64,' . $svg($test)],
            'an SVG data stream' => [fn (self $test): string => '@' . $svg($test)],
            'a file inside WEB_ROOT that is not an image' => [fn (): string => WEB_ROOT . '/uploads/base-pdf-test'],
        ];
    }

    #[DataProvider('blockedImageProvider')]
    public function testBlockedImageIsDropped(callable $src): void
    {
        $pdf = self::render('<p>before</p><img src="' . $src($this) . '"/><p>after</p>');

        $this->assertSame(0, self::imageCount($pdf));
    }

    public function testRemoteImageIsNeverFetched(): void
    {
        // TCPDF connects to an http(s) image URL. A listener that never answers
        // records the connection without the test reaching the network.
        $server = stream_socket_server('tcp://127.0.0.1:0');
        $this->assertNotFalse($server);
        $address = stream_socket_get_name($server, false);

        $pdf = self::render('<img src="http://' . $address . '/image.png"/>');
        $connection = @stream_socket_accept($server, 0);
        fclose($server);

        $this->assertFalse($connection, 'TCPDF tried to fetch the remote image');
        $this->assertSame(0, self::imageCount($pdf));
    }

    public function testFormWithoutActionRendersAsText(): void
    {
        // TCPDF throws on a <form> without an action, which stops the whole print run
        $pdf = self::render('<form><input type="text" name="n" value="x"/><select name="s"></select></form>');

        $this->assertStringNotContainsString('/Widget', $pdf);
    }

    public function testCheckboxIsNotAFormField(): void
    {
        $pdf = self::render('<input type="checkbox" name="c" value="1" onclick="app.alert(1)"/>');

        $this->assertStringNotContainsString('/Widget', $pdf);
        $this->assertStringNotContainsString('app.alert', $pdf);
    }

    public function testScriptLinkIsDroppedAndWebLinkKept(): void
    {
        $pdf = self::render(
            '<a href="javascript:app.alert(1)">script</a> <a href="https://example.org/report">web</a>'
        );

        $this->assertStringNotContainsString('app.alert', $pdf);
        $this->assertStringContainsString('https://example.org/report', $pdf);
    }

    public function testEveryPdfClassExtendsBasePdf(): void
    {
        $offenders = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(ROOT_PATH . '/app'));
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php' || $file->getFilename() === 'BasePdf.php') {
                continue;
            }
            $code = (string) file_get_contents($file->getPathname());
            $parent = '\\\\?(?:TCPDF|Fpdi|setasign\\\\Fpdi\\\\Tcpdf\\\\Fpdi)';
            if (preg_match('/\bclass\s+\w+\s+extends\s+' . $parent . '\b/i', $code)) {
                $offenders[] = substr($file->getPathname(), strlen(ROOT_PATH) + 1);
            }
        }

        $this->assertSame([], $offenders, 'A PDF class must extend App\Helpers\BasePdf');
    }
}
