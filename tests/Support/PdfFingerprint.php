<?php

declare(strict_types=1);

namespace Tests\Support;

use PHPUnit\Framework\Assert;

/**
 * What a reader of a PDF sees, reduced to data a test can compare: the page
 * count, every word with its position, and where every image is drawn and
 * at what size. Bytes are no use for this, because TCPDF writes a new creation date
 * and file ID on every run, and a change of PDF engine changes every byte.
 *
 * Built with poppler's pdfinfo, pdftotext and pdftohtml. Word positions come
 * from `pdftotext -bbox`; `-layout` reorders text and so reads as a content
 * change where there is none. A coarse ink map from `pdftoppm` covers vector
 * drawing, such as QR codes. Image bounds come from `pdftohtml -xml`, with a
 * hash of each image's decoded pixels, so a logo or signature that moves,
 * rescales or changes fails. The pixels are hashed rather than the extracted
 * file, because poppler versions may encode the same image differently.
 *
 * A test freezes the clock with Carbon::setTestNow(), but a page that calls
 * date() prints the real clock. Today's date in any format the app prints is
 * masked, with the times on the same line, and positions are not compared on
 * that line: a month name changes the width of the line after it. Every other
 * date is data, and stays compared, so a date printed wrong still fails.
 * poppler 24.02 (Ubuntu 24.04, on CI) and 26.10 give the same words and
 * positions for every snapshot. If a later version does not, the failure names
 * both versions; rewrite the snapshots with the version CI runs.
 *
 * A snapshot is a JSON file under tests/Fixtures/pdf/. Set
 * PDF_FINGERPRINT_UPDATE=1 to write it, after checking the PDF by eye.
 */
final class PdfFingerprint
{
    private const SNAPSHOT_PATH = __DIR__ . '/../Fixtures/pdf/';

    /** The date formats the app prints */
    private const TODAY_FORMATS = ['d-M-Y', 'd/M/Y', 'd M Y', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'Y-m-d'];

    private const TIME_PATTERN = '/\d{1,2}:\d{2}(?::\d{2})?(?::?[AP]M)?/';

    private const MASK = '<date>';

    /** Ink map resolution, cell size in pixels, and how many levels a cell may move */
    private const INK_DPI = 18;
    private const INK_CELL = 5;
    private const INK_TOLERANCE = 2;

    /** Whether the poppler tools are installed. */
    public static function available(): bool
    {
        foreach (['pdfinfo', 'pdftotext', 'pdftohtml', 'pdftoppm'] as $tool) {
            exec('command -v ' . $tool, $output, $status);
            if ($status !== 0) {
                return false;
            }
        }
        return true;
    }

    /**
     * @return array{
     *     poppler: string,
     *     pages: int,
     *     images: list<string>,
     *     ink: list<string>,
     *     words: list<array{0: int, 1: string, 2: float, 3: float}>
     * }
     */
    public static function of(string $pdf): array
    {
        $file = tempnam(sys_get_temp_dir(), 'pdf-fingerprint-');
        file_put_contents($file, $pdf);
        try {
            $arg = escapeshellarg($file);
            preg_match('/^Pages:\s+(\d+)/m', (string) shell_exec("pdfinfo $arg 2>/dev/null"), $pages);
            preg_match('/version ([\d.]+)/', (string) shell_exec('pdftotext -v 2>&1'), $version);

            return [
                'poppler' => $version[1] ?? '',
                'pages' => (int) ($pages[1] ?? 0),
                'images' => self::images($file),
                'ink' => self::ink($file),
                'words' => self::words((string) shell_exec("pdftotext -bbox $arg - 2>/dev/null")),
            ];
        } finally {
            unlink($file);
        }
    }

    /**
     * Compare a PDF with its snapshot, or write the snapshot when
     * PDF_FINGERPRINT_UPDATE is set or none exists yet.
     */
    public static function assertMatchesSnapshot(string $name, string $pdf): void
    {
        $actual = self::of($pdf);
        $path = self::SNAPSHOT_PATH . $name . '.json';

        if (getenv('PDF_FINGERPRINT_UPDATE') || !is_file($path)) {
            if (!is_dir(self::SNAPSHOT_PATH)) {
                mkdir(self::SNAPSHOT_PATH, 0777, true);
            }
            file_put_contents($path, self::encode($actual));
            $copy = sys_get_temp_dir() . "/pdf-snapshot-$name.pdf";
            file_put_contents($copy, $pdf);
            Assert::markTestIncomplete("Wrote the PDF snapshot $name.json. Check $copy, then commit the snapshot.");
        }

        $expected = json_decode((string) file_get_contents($path), true);
        Assert::assertSame($expected['pages'], $actual['pages'], "$name: page count");
        Assert::assertSame($expected['images'], $actual['images'], "$name: images (page top left width height)");
        Assert::assertSame(
            self::text($expected['words']),
            self::text($actual['words']),
            "$name: words, in reading order"
        );
        Assert::assertSame(
            self::positions($expected['words']),
            self::positions($actual['words']),
            "$name: word positions (snapshot made with poppler {$expected['poppler']}, this run {$actual['poppler']})"
        );
        self::assertInkMatches($name, $expected['ink'], $actual['ink']);
    }

    /** The PDF's text, for a test that checks what it says. */
    public static function plainText(string $pdf): string
    {
        $file = tempnam(sys_get_temp_dir(), 'pdf-fingerprint-');
        file_put_contents($file, $pdf);
        try {
            return (string) shell_exec('pdftotext ' . escapeshellarg($file) . ' - 2>/dev/null');
        } finally {
            unlink($file);
        }
    }

    /**
     * How dark each cell of each page is, from 0 (blank) to 9, on a grid of
     * cells about 7mm square. Vector drawing has no words or images to compare:
     * a QR code drawn by write2DBarcode(), a stamp, a shaded box. Its ink does.
     * A QR code encodes an encrypted link with a random IV, so its pattern
     * changes on every run, but the cells it covers do not.
     *
     * @return list<string> one "page row digits" entry per row of cells
     */
    private static function ink(string $file): array
    {
        $dir = sys_get_temp_dir() . '/pdf-fingerprint-' . bin2hex(random_bytes(6));
        mkdir($dir);
        try {
            $args = escapeshellarg($file) . ' ' . escapeshellarg("$dir/p");
            shell_exec("pdftoppm -r " . self::INK_DPI . " -gray $args 2>/dev/null");
            $pages = glob("$dir/p-*.pgm") ?: [];
            natsort($pages);
            $rows = [];
            foreach (array_values($pages) as $index => $pgm) {
                foreach (self::inkRows((string) file_get_contents($pgm)) as $row => $digits) {
                    $rows[] = ($index + 1) . " $row $digits";
                }
            }
            return $rows;
        } finally {
            array_map('unlink', glob("$dir/*") ?: []);
            rmdir($dir);
        }
    }

    /** @return list<string> one string of 0-9 darkness digits per row of cells */
    private static function inkRows(string $pgm): array
    {
        // Binary PGM: P5, width, height, maximum value, then one byte per pixel
        if (!preg_match('/^P5\s+(\d+)\s+(\d+)\s+(\d+)\s/', $pgm, $header)) {
            return [];
        }
        [$all, $width, $height] = [$header[0], (int) $header[1], (int) $header[2]];
        $pixels = substr($pgm, strlen($all));
        $cell = self::INK_CELL;
        $rows = [];
        for ($top = 0; $top < $height; $top += $cell) {
            $digits = '';
            for ($left = 0; $left < $width; $left += $cell) {
                $dark = 0;
                $count = 0;
                for ($y = $top; $y < min($top + $cell, $height); $y++) {
                    for ($x = $left; $x < min($left + $cell, $width); $x++) {
                        $dark += 255 - ord($pixels[$y * $width + $x]);
                        $count++;
                    }
                }
                $digits .= (string) min(9, intdiv($dark * 10, 255 * $count));
            }
            $rows[] = $digits;
        }
        return $rows;
    }

    /**
     * Every cell within INK_TOLERANCE of the snapshot. Renderers anti-alias text
     * differently, so a cell may shift by a level or two; a QR code that is
     * missing or moved changes its cells by more.
     *
     * @param list<string> $expected
     * @param list<string> $actual
     */
    private static function assertInkMatches(string $name, array $expected, array $actual): void
    {
        Assert::assertSame(count($expected), count($actual), "$name: ink rows");
        $differences = [];
        foreach ($expected as $index => $line) {
            [$page, $row, $want] = explode(' ', $line);
            $have = explode(' ', $actual[$index])[2] ?? '';
            for ($column = 0; $column < strlen($want); $column++) {
                if (abs((int) $want[$column] - (int) ($have[$column] ?? 0)) > self::INK_TOLERANCE) {
                    $now = $have[$column] ?? '-';
                    $differences[] = "page $page row $row column $column: {$want[$column]} -> $now";
                }
            }
        }
        Assert::assertSame([], $differences, "$name: ink (cells darker or lighter than the snapshot)");
    }

    /**
     * Where each image is drawn, and what it shows. pdftohtml writes every
     * image out as a file beside its XML, so it runs in a directory of its own.
     *
     * @return list<string> one "page top left width height pixels" entry per image
     */
    private static function images(string $file): array
    {
        $dir = sys_get_temp_dir() . '/pdf-fingerprint-' . bin2hex(random_bytes(6));
        mkdir($dir);
        try {
            $args = escapeshellarg($file) . ' ' . escapeshellarg("$dir/out");
            shell_exec("pdftohtml -xml -q -nodrm $args 2>/dev/null");
            $xml = is_file("$dir/out.xml") ? (string) file_get_contents("$dir/out.xml") : '';

            $images = [];
            $page = 0;
            $pattern = '/<page number="(\d+)"|<image top="([\d.-]+)" left="([\d.-]+)" width="([\d.]+)" '
                . 'height="([\d.]+)" src="([^"]+)"/';
            preg_match_all($pattern, $xml, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                if (($match[1] ?? '') !== '') {
                    $page = (int) $match[1];
                    continue;
                }
                $pixels = self::pixelHash($dir . '/' . basename($match[6]));
                $images[] = "$page {$match[2]} {$match[3]} {$match[4]} {$match[5]} $pixels";
            }
            return $images;
        } finally {
            array_map('unlink', glob("$dir/*") ?: []);
            rmdir($dir);
        }
    }

    /** A short hash of an image's decoded pixels, or "unreadable". */
    private static function pixelHash(string $file): string
    {
        $image = is_file($file) ? @imagecreatefromstring((string) file_get_contents($file)) : false;
        if ($image === false) {
            return 'unreadable';
        }
        $width = imagesx($image);
        $height = imagesy($image);
        $hash = hash_init('md5');
        hash_update($hash, "{$width}x{$height}");
        for ($y = 0; $y < $height; $y++) {
            $row = '';
            for ($x = 0; $x < $width; $x++) {
                $row .= pack('N', imagecolorat($image, $x, $y));
            }
            hash_update($hash, $row);
        }
        return substr(hash_final($hash), 0, 12);
    }

    /** @return list<array{0: int, 1: string, 2: float, 3: float}> page, word, x, y */
    private static function words(string $html): array
    {
        $words = [];
        $page = 0;
        $pattern = '/<page |<word xMin="([\d.]+)" yMin="([\d.]+)"[^>]*>(.*?)<\/word>/';
        preg_match_all($pattern, $html, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            if (!isset($match[1])) {
                $page++;
                continue;
            }
            $text = html_entity_decode($match[3], ENT_QUOTES | ENT_XML1, 'UTF-8');
            $words[] = [$page, $text, round((float) $match[1], 1), round((float) $match[2], 1)];
        }
        return self::maskToday($words);
    }

    /**
     * Mask today's date, and the times on its line. A date printed with spaces
     * is several words, so the date is found in the line's text and every word
     * it covers is masked.
     *
     * @param list<array{0: int, 1: string, 2: float, 3: float}> $words
     * @return list<array{0: int, 1: string, 2: float, 3: float}>
     */
    private static function maskToday(array $words): array
    {
        $today = array_unique(array_map(fn (string $format): string => date($format), self::TODAY_FORMATS));

        $lines = [];
        foreach ($words as $index => [$page, , , $y]) {
            $lines["$page:$y"][] = $index;
        }
        foreach ($lines as $indexes) {
            // Each word's start and end offset in the line's text
            $text = '';
            $spans = [];
            foreach ($indexes as $index) {
                $start = $text === '' ? 0 : strlen($text) + 1;
                $text .= ($text === '' ? '' : ' ') . $words[$index][1];
                $spans[$index] = [$start, strlen($text)];
            }
            $masked = false;
            foreach ($today as $date) {
                for ($at = strpos($text, $date); $at !== false; $at = strpos($text, $date, $at + 1)) {
                    foreach ($spans as $index => [$start, $end]) {
                        if ($start < $at + strlen($date) && $end > $at) {
                            $words[$index][1] = self::MASK;
                            $masked = true;
                        }
                    }
                }
            }
            if ($masked) {
                foreach ($indexes as $index) {
                    $words[$index][1] = preg_replace(self::TIME_PATTERN, self::MASK, $words[$index][1]) ?? '';
                }
            }
        }
        return $words;
    }

    /** @param list<array{0: int, 1: string, 2: float, 3: float}> $words */
    private static function text(array $words): string
    {
        $lines = [];
        foreach ($words as [$page, $text, , $y]) {
            $lines["$page:$y"][] = $text;
        }
        return implode("\n", array_map(fn (array $line): string => implode(' ', $line), $lines));
    }

    /**
     * Positions of every word on a line with no masked date.
     *
     * @param list<array{0: int, 1: string, 2: float, 3: float}> $words
     * @return list<string>
     */
    private static function positions(array $words): array
    {
        $masked = [];
        foreach ($words as [$page, $text, , $y]) {
            if (str_contains($text, self::MASK)) {
                $masked["$page:$y"] = true;
            }
        }
        $positions = [];
        foreach ($words as [$page, $text, $x, $y]) {
            if (!isset($masked["$page:$y"])) {
                $positions[] = "$page $x $y $text";
            }
        }
        return $positions;
    }

    /** One word per line, so a snapshot diff in review shows what moved. */
    private static function encode(array $fingerprint): string
    {
        $words = array_map(
            fn (array $word): string => (string) json_encode($word, JSON_UNESCAPED_UNICODE),
            $fingerprint['words']
        );
        unset($fingerprint['words']);
        $head = json_encode($fingerprint, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return substr($head, 0, -2) . ",\n    \"words\": [\n        " . implode(",\n        ", $words) . "\n    ]\n}\n";
    }
}
