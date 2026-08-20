<?php

declare(strict_types=1);

namespace App\Utilities;

use Symfony\Component\Process\Process;

/**
 * "Pick one row from a list" prompt for the bin/ CLI scripts.
 *
 * Three tiers, best first:
 *   1. fzf, when it is on PATH — the UX the other two imitate.
 *   2. A built-in fzf-alike: raw-mode terminal, fuzzy filter as you type,
 *      arrow keys, Enter to select, Esc to cancel. Needs a TTY and stty.
 *   3. A plain numbered list, for Windows, dumb terminals and pipes.
 *
 * Every tier is self-contained, so a script must never refuse to run just
 * because fzf is missing. setup.sh installs fzf on real instances; dev boxes,
 * minimal containers and non-Debian machines get tier 2 and are none the worse
 * for it.
 */
final class CliPickerUtility
{
    /** Rows shown per page by the numbered-list fallback. */
    private const PAGE = 20;

    public static function hasCommand(string $cmd): bool
    {
        $probe = PHP_OS_FAMILY === 'Windows'
            ? 'where ' . escapeshellarg($cmd) . ' 2>NUL'
            : 'command -v ' . escapeshellarg($cmd) . ' 2>/dev/null';

        return trim((string) shell_exec($probe)) !== '';
    }

    /**
     * Show $rows and return the one chosen, or null if the user cancelled.
     *
     * @param list<array<string,mixed>> $rows
     * @param list<string> $columns keys to display, in order
     * @return array<string,mixed>|null
     */
    public static function pick(array $rows, array $columns, string $prompt = 'Select', string $header = ''): ?array
    {
        $rows = array_values($rows);
        if ($rows === []) {
            return null;
        }

        $display = self::renderRows($rows, $columns);

        if (self::isTty() && self::hasCommand('fzf') && Process::isTtySupported()) {
            return self::pickViaFzf($rows, $display, $prompt, $header);
        }
        if (self::canUseRawMode()) {
            return self::pickInteractively($rows, $display, $prompt, $header);
        }
        return self::pickFromNumberedList($rows, $display, $prompt, $header);
    }

    // ---- rendering ---------------------------------------------------------

    /**
     * Flatten each row to one aligned, single-line label. Columns are padded to
     * a common width so the list stays readable without fzf's preview pane.
     *
     * @param list<array<string,mixed>> $rows
     * @param list<string> $columns
     * @return list<string>
     */
    private static function renderRows(array $rows, array $columns): array
    {
        $cells = [];
        foreach ($rows as $i => $row) {
            foreach (array_values($columns) as $c => $key) {
                $value = str_replace(["\r", "\n", "\t"], ' ', (string) ($row[$key] ?? ''));
                $cells[$i][$c] = trim((string) preg_replace('/\s+/', ' ', $value));
            }
        }

        $widths = [];
        foreach ($cells as $rowCells) {
            foreach ($rowCells as $c => $value) {
                $widths[$c] = max($widths[$c] ?? 0, mb_strlen($value));
            }
        }

        $last = count($columns) - 1;
        $lines = [];
        foreach ($cells as $rowCells) {
            $parts = [];
            foreach ($rowCells as $c => $value) {
                $parts[] = $c === $last ? $value : self::pad($value, $widths[$c]);
            }
            $lines[] = rtrim(implode('  ', $parts));
        }

        return $lines;
    }

    private static function pad(string $value, int $width): string
    {
        return $value . str_repeat(' ', max(0, $width - mb_strlen($value)));
    }

    private static function clip(string $line, int $width): string
    {
        return mb_strlen($line) > $width ? mb_substr($line, 0, max(1, $width - 1)) . '…' : $line;
    }

    // ---- tier 1: fzf -------------------------------------------------------

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $display
     * @return array<string,mixed>|null
     */
    private static function pickViaFzf(array $rows, array $display, string $prompt, string $header): ?array
    {
        $inFile = tempnam(sys_get_temp_dir(), 'picker_in_');
        $outFile = tempnam(sys_get_temp_dir(), 'picker_out_');

        // Row index rides along as a hidden first field: --with-nth=2.. keeps it
        // out of both the display and the search, and it maps the chosen line
        // back to its row even when two rows render identically.
        $lines = [];
        foreach ($display as $i => $line) {
            $lines[] = $i . "\t" . $line;
        }
        file_put_contents($inFile, implode(PHP_EOL, $lines));

        $cmd = sprintf(
            'cat %s | fzf --ansi --height=80%% --reverse --border --cycle' .
            ' --delimiter="\t" --with-nth=2.. --prompt=%s%s > %s',
            escapeshellarg($inFile),
            escapeshellarg(rtrim($prompt) . ' > '),
            $header === '' ? '' : ' --header=' . escapeshellarg($header . ' • Enter to select, Esc to cancel'),
            escapeshellarg($outFile)
        );

        // TTY mode swallows the child's stdout, hence the redirect to a file.
        $process = Process::fromShellCommandline($cmd);
        $process->setTimeout(null);
        try {
            $process->setTty(true);
        } catch (\RuntimeException) {
            // Fallback when TTY cannot be enabled (e.g. running detached).
        }
        $process->run();

        MiscUtility::deleteFile($inFile);
        $out = @file_get_contents($outFile);
        MiscUtility::deleteFile($outFile);

        $out = $out === false ? '' : trim((string) $out);
        if ($out === '' || !str_contains($out, "\t")) {
            return null; // aborted, or nothing selected
        }

        $index = (int) explode("\t", $out, 2)[0];

        return $rows[$index] ?? null;
    }

    // ---- tier 2: built-in fzf-alike ---------------------------------------

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $display
     * @return array<string,mixed>|null
     */
    private static function pickInteractively(array $rows, array $display, string $prompt, string $header): ?array
    {
        $tty = @fopen('/dev/tty', 'r');
        if ($tty === false) {
            return self::pickFromNumberedList($rows, $display, $prompt, $header);
        }

        $saved = trim((string) shell_exec('stty -g < /dev/tty 2>/dev/null'));
        $restored = false;
        $restore = static function () use ($saved, &$restored): void {
            if ($restored) {
                return;
            }
            $restored = true;
            shell_exec(($saved !== '' ? 'stty ' . escapeshellarg($saved) : 'stty sane') . ' < /dev/tty 2>/dev/null');
            echo "\033[?25h\033[?1049l"; // show the cursor, leave the alternate screen
        };
        register_shutdown_function($restore);

        // raw also disables isig, so Ctrl+C arrives as a byte we handle below
        // rather than as a signal that would leave the terminal wedged.
        shell_exec('stty raw -echo < /dev/tty 2>/dev/null');
        echo "\033[?1049h\033[?25l";

        $query = '';
        $cursor = 0;
        $offset = 0;
        $picked = null;

        while (true) {
            $matches = self::filter($display, $query);
            $cursor = max(0, min($cursor, count($matches) - 1));

            [$height, $width] = self::terminalSize();
            $visible = max(3, $height - 4);
            $offset = min($offset, $cursor);
            if ($cursor >= $offset + $visible) {
                $offset = $cursor - $visible + 1;
            }
            $offset = max(0, min($offset, max(0, count($matches) - $visible)));

            $out = "\033[H\033[2J";
            $out .= "\033[1m" . rtrim($prompt) . " > \033[0m" . $query . "\033[7m \033[0m\r\n";
            $out .= "\033[2m  " . count($matches) . '/' . count($display)
                . ($header === '' ? '' : '  •  ' . $header)
                . "  •  fzf not installed, using the built-in picker\033[0m\r\n\r\n";

            if ($matches === []) {
                $out .= "\033[2m  no matches\033[0m\r\n";
            }
            for ($i = $offset; $i < min(count($matches), $offset + $visible); $i++) {
                $line = self::clip($display[$matches[$i]], $width - 3);
                $out .= $i === $cursor
                    ? "\033[7m> " . self::pad($line, $width - 3) . "\033[0m\r\n"
                    : '  ' . $line . "\r\n";
            }
            echo $out;

            $key = self::readKey($tty);
            if ($key === '' || $key === "\033" || $key === "\x03" || $key === "\x04") {
                break; // EOF, Esc, Ctrl+C, Ctrl+D
            }
            if ($key === "\r" || $key === "\n") {
                if ($matches !== []) {
                    $picked = $rows[$matches[$cursor]] ?? null;
                }
                break;
            }
            if ($key === "\033[A" || $key === "\x10") { // up, Ctrl+P
                $cursor--;
                continue;
            }
            if ($key === "\033[B" || $key === "\x0e") { // down, Ctrl+N
                $cursor++;
                continue;
            }
            if ($key === "\x7f" || $key === "\x08") { // backspace
                $query = mb_substr($query, 0, max(0, mb_strlen($query) - 1));
                $cursor = 0;
                continue;
            }
            if ($key === "\x15") { // Ctrl+U
                $query = '';
                $cursor = 0;
                continue;
            }
            if (mb_strlen($key) === 1 && ord($key) >= 0x20) {
                $query .= $key;
                $cursor = 0;
            }
        }

        $restore();
        fclose($tty);

        return $picked;
    }

    /**
     * One keypress, with escape sequences (arrow keys) read as a unit. A bare
     * Esc is distinguished from Esc-[-A by nothing following it within 20ms.
     *
     * @param resource $stream
     */
    private static function readKey($stream): string
    {
        $char = fread($stream, 1);
        if ($char === false || $char === '') {
            return '';
        }
        if ($char !== "\033") {
            return $char;
        }

        $sequence = '';
        while (true) {
            $read = [$stream];
            $write = $except = [];
            if (@stream_select($read, $write, $except, 0, 20000) < 1) {
                break;
            }
            $next = fread($stream, 1);
            if ($next === false || $next === '') {
                break;
            }
            $sequence .= $next;
            if (preg_match('/[A-Za-z~]$/', $sequence)) {
                break;
            }
        }

        return "\033" . $sequence;
    }

    /**
     * @return array{0:int,1:int} rows, columns
     *
     * Some pseudo-terminals (`script`, some CI runners) answer "0 0", which
     * would otherwise clip every row to a single character, so anything
     * implausibly small falls through to COLUMNS/LINES and then to 24x80.
     */
    private static function terminalSize(): array
    {
        $size = trim((string) shell_exec('stty size < /dev/tty 2>/dev/null'));
        if (preg_match('/^(\d+)\s+(\d+)$/', $size, $m) === 1) {
            $rows = (int) $m[1];
            $columns = (int) $m[2];
            if ($rows >= 6 && $columns >= 20) {
                return [$rows, $columns];
            }
        }

        $rows = (int) (getenv('LINES') ?: 0);
        $columns = (int) (getenv('COLUMNS') ?: 0);

        return [$rows >= 6 ? $rows : 24, $columns >= 20 ? $columns : 80];
    }

    // ---- tier 3: numbered list --------------------------------------------

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $display
     * @return array<string,mixed>|null
     */
    private static function pickFromNumberedList(array $rows, array $display, string $prompt, string $header): ?array
    {
        $query = '';
        $page = 0;

        while (true) {
            $matches = self::filter($display, $query);
            $pages = max(1, (int) ceil(count($matches) / self::PAGE));
            $page = $page % $pages;

            echo PHP_EOL . rtrim($prompt) . ' — ' . count($matches) . ' of ' . count($display);
            echo $query === '' ? '' : " matching '$query'";
            echo $pages > 1 ? '  (page ' . ($page + 1) . ' of ' . $pages . ')' : '';
            echo PHP_EOL;
            if ($header !== '') {
                echo '      ' . $header . PHP_EOL;
            }

            foreach (array_slice($matches, $page * self::PAGE, self::PAGE, true) as $ordinal => $index) {
                echo sprintf('%4d) %s', $ordinal + 1, $display[$index]) . PHP_EOL;
            }
            if ($matches === []) {
                echo '      no matches' . PHP_EOL;
            }

            echo PHP_EOL . 'Number to select, text to filter'
                . ($pages > 1 ? ', [Enter] for the next page' : '')
                . ', q to cancel: ';

            $line = fgets(STDIN);
            if ($line === false) {
                return null; // EOF
            }
            $line = trim($line);

            if ($line === 'q' || $line === 'Q') {
                return null;
            }
            if ($line === '') {
                $page++;
                continue;
            }
            if (ctype_digit($line)) {
                $choice = (int) $line;
                if ($choice >= 1 && $choice <= count($matches)) {
                    return $rows[$matches[$choice - 1]] ?? null;
                }
                echo 'No entry numbered ' . $choice . '.' . PHP_EOL;
                continue;
            }

            $query = $line;
            $page = 0;
        }
    }

    // ---- matching ----------------------------------------------------------

    /**
     * Indexes of $display matching $query, best match first. Empty query keeps
     * the original order.
     *
     * @param list<string> $display
     * @return list<int>
     */
    private static function filter(array $display, string $query): array
    {
        if (trim($query) === '') {
            return array_keys($display);
        }

        $needle = mb_strtolower($query);
        $scored = [];
        foreach ($display as $i => $line) {
            $score = self::fuzzyScore(mb_strtolower($line), $needle);
            if ($score !== null) {
                $scored[] = [$score, $i];
            }
        }
        usort($scored, static fn(array $a, array $b): int => ($b[0] <=> $a[0]) ?: ($a[1] <=> $b[1]));

        return array_map(static fn(array $s): int => $s[1], $scored);
    }

    /**
     * fzf-style subsequence match: every character of the needle must appear in
     * order. Contiguous runs and word starts score higher, so "adug" ranks
     * "Amit Dugar" above an incidental scattering of the same letters.
     */
    private static function fuzzyScore(string $haystack, string $needle): ?int
    {
        $score = 0;
        $from = 0;
        $previous = -2;

        $length = mb_strlen($needle);
        for ($n = 0; $n < $length; $n++) {
            $char = mb_substr($needle, $n, 1);
            if ($char === ' ') {
                continue; // spaces loosen the match rather than having to match
            }
            $at = mb_strpos($haystack, $char, $from);
            if ($at === false) {
                return null;
            }
            $score += $at === $previous + 1 ? 8 : 1;
            if ($at === 0 || mb_substr($haystack, $at - 1, 1) === ' ') {
                $score += 4;
            }
            $previous = $at;
            $from = $at + 1;
        }

        return $score;
    }

    // ---- capability probes -------------------------------------------------

    private static function isTty(): bool
    {
        return function_exists('stream_isatty') && @stream_isatty(STDIN);
    }

    private static function canUseRawMode(): bool
    {
        return PHP_OS_FAMILY !== 'Windows'
            && self::isTty()
            && is_readable('/dev/tty')
            && self::hasCommand('stty');
    }
}
