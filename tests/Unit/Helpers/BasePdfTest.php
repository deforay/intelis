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

    public static function documentRootProvider(): array
    {
        return [
            'on the web' => [WEB_ROOT],
            'on the command line, with no DOCUMENT_ROOT' => [''],
            'with DOCUMENT_ROOT outside WEB_ROOT' => [VAR_PATH],
        ];
    }

    #[DataProvider('documentRootProvider')]
    public function testImagesTheResultPdfsUseStillRender(string $documentRoot): void
    {
        $_SERVER['DOCUMENT_ROOT'] = $documentRoot;

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

    public static function svgTextInPngProvider(): array
    {
        return [
            'as a file' => ['file'],
            'as a data image' => ['data'],
            'as a data stream' => ['stream'],
        ];
    }

    #[DataProvider('svgTextInPngProvider')]
    public function testPngWithSvgTextRendersAsPng(string $form): void
    {
        // TCPDF reads an '@' stream holding "<svg ...>...</svg>" anywhere as SVG,
        // and the SVG parser throws on PNG bytes, stopping the print
        $png = base64_decode(self::PNG) . '<svg width="1" height="1"><rect/></svg>';
        $file = WEB_ROOT . '/uploads/base-pdf-test/svg-text.png';
        file_put_contents($file, $png);
        $src = match ($form) {
            'file' => $file,
            'data' => 'data:image/png;base64,' . base64_encode($png),
            'stream' => '@' . base64_encode($png),
        };

        $pdf = self::render('<img src="' . $src . '"/>');

        $this->assertSame(1, self::imageCount($pdf));
    }

    public function testRepeatedFileImageIsStoredOnce(): void
    {
        $pdf = self::render(str_repeat('<img src="' . $this->insideFile . '"/>', 20));

        $this->assertSame(20, self::imageCount($pdf));
        $this->assertSame(1, substr_count($pdf, '/Subtype /Image'));
    }

    public function testRasterFileWithVectorExtensionIsDropped(): void
    {
        // TCPDF sends a .svg file to its SVG parser whatever the file holds
        $file = WEB_ROOT . '/uploads/base-pdf-test/raster.svg';
        file_put_contents($file, base64_decode(self::PNG));

        $pdf = self::render('<img src="' . $file . '"/>');

        $this->assertSame(0, self::imageCount($pdf));
    }

    public function testDocumentRootIsRestoredAfterAnImage(): void
    {
        $_SERVER['DOCUMENT_ROOT'] = WEB_ROOT;

        self::render('<img src="/uploads/base-pdf-test/signature.png"/>');

        $this->assertSame(WEB_ROOT, $_SERVER['DOCUMENT_ROOT']);
    }

    public function testRootRelativePathResolvesOnlyInsideWebRoot(): void
    {
        // TCPDF would prefix DOCUMENT_ROOT and load the file outside WEB_ROOT
        $_SERVER['DOCUMENT_ROOT'] = VAR_PATH;

        $pdf = self::render('<img src="/' . basename($this->outsideFile) . '"/>');

        $this->assertSame(0, self::imageCount($pdf));
    }

    public static function listBulletProvider(): array
    {
        return [
            'the type attribute' => ['<ul type="img|png|10|10|%s"><li>item</li></ul>'],
            'the list-style-type style' => ['<ul style="list-style-type:img|png|10|10|%s"><li>item</li></ul>'],
        ];
    }

    #[DataProvider('listBulletProvider')]
    public function testListImageBulletIsDropped(string $html): void
    {
        $pdf = self::render(sprintf($html, $this->outsideFile));

        $this->assertSame(0, self::imageCount($pdf));
    }

    public static function remoteFetchProvider(): array
    {
        return [
            'an image' => ['<img src="http://%s/image.png"/>'],
            'a stylesheet' => ['<link type="text/css" href="http://%s/style.css"/><p>text</p>'],
        ];
    }

    #[DataProvider('remoteFetchProvider')]
    public function testRemoteUrlIsNeverFetched(string $html): void
    {
        // TCPDF connects to an http(s) URL. A listener that never answers
        // records the connection without the test reaching the network.
        $server = stream_socket_server('tcp://127.0.0.1:0');
        $this->assertNotFalse($server);
        $address = stream_socket_get_name($server, false);

        $pdf = self::render(sprintf($html, $address));
        $connection = @stream_socket_accept($server, 0);
        fclose($server);

        $this->assertFalse($connection, 'TCPDF tried to fetch the remote URL');
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
            '<a href="javascript:app.alert(1)">script</a> <a href="mailto:lab@example.org">mail</a>'
            . ' <a href="https://example.org/report">web</a>'
        );

        $this->assertStringNotContainsString('app.alert', $pdf);
        $this->assertStringNotContainsString('mailto:', $pdf);
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
