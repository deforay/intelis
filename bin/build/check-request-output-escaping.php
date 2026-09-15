<?php

declare(strict_types=1);

/**
 * Static check: request values are escaped where a page prints them.
 *
 * _sanitizeInput() runs HTML Purifier, which strips tags but leaves quotes. A
 * request value echoed into an attribute or a JS string can still close it and
 * add its own script, so every page that prints one has to escape it for the
 * place it lands: _escapeRequestValue() in HTML, _jsEscape() in <script>, and
 * _jsAttributeEscape() inside an inline handler such as onclick.
 *
 * Two shapes are caught, across every PHP file under app/:
 *
 * - a superglobal printed as is: `<?= $_GET['x'] ?>`, `echo $_POST['x']`, or the
 *   request URI. Comparisons (`echo $_GET['x'] == 'y' ? ... : ...`) are fine.
 * - htmlspecialchars() output inside a quoted JS string in an on*="..." handler.
 *   The browser decodes the attribute before running the handler, so &#039;
 *   turns back into a quote that ends the JS string.
 *
 * A value copied into a variable first is not traced: this is a guardrail
 * against the copy-paste shapes the sweep removed, not a proof.
 *
 * Usage: php bin/build/check-request-output-escaping.php
 */

const REPO_DIR = __DIR__ . '/../..';

const SUPERGLOBAL = '(?:\$_(?:GET|POST|REQUEST|COOKIE)\[|'
    . '\$_SERVER\[\s*[\'"](?:REQUEST_URI|QUERY_STRING|PHP_SELF|HTTP_REFERER)[\'"]\s*\])';

/** `<?=`, echo or print followed straight by a superglobal (parentheses allowed). */
const RAW_ECHO_PATTERN = '/(?:<\?=|\becho\b|\bprint\b)[\s(]*' . SUPERGLOBAL . '/';

/** The superglobal is only compared, never printed. */
const COMPARISON_PATTERN = '/' . SUPERGLOBAL . '[^\]]*\]\s*(?:==|!=|<>)/';

/** An inline handler whose quoted JS string holds htmlspecialchars() output. */
const HANDLER_PATTERN = '/\bon[a-z]+\s*=\s*"[^"]*\'<\?(?:=|php\s+echo)[^?]*htmlspecialchars/i';

$violations = [];
$checked = 0;

$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(REPO_DIR . '/app', FilesystemIterator::SKIP_DOTS)
);
foreach ($files as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }
    $name = substr($file->getPathname(), strlen(REPO_DIR) + 1);
    if (str_starts_with($name, 'app/locales/')) {
        continue;
    }
    $checked++;
    foreach (file($file->getPathname()) as $i => $line) {
        if (preg_match(RAW_ECHO_PATTERN, $line) && !preg_match(COMPARISON_PATTERN, $line)) {
            $violations[] = [
                'where' => $name . ':' . ($i + 1),
                'hint' => 'request value printed without escaping: ' . trim($line),
            ];
        } elseif (preg_match(HANDLER_PATTERN, $line)) {
            $violations[] = [
                'where' => $name . ':' . ($i + 1),
                'hint' => 'htmlspecialchars() inside an inline handler\'s JS string: ' . trim($line),
            ];
        }
    }
}

echo "check-request-output-escaping: {$checked} files scanned" . PHP_EOL;

if ($violations === []) {
    echo 'check-request-output-escaping: no page prints a request value unescaped.' . PHP_EOL;
    exit(0);
}

echo PHP_EOL;
foreach ($violations as $violation) {
    echo "  {$violation['where']}" . PHP_EOL;
    echo "      {$violation['hint']}" . PHP_EOL;
}
echo PHP_EOL;
echo 'Escape for where the value lands: _escapeRequestValue() in HTML text or a' . PHP_EOL;
echo 'quoted attribute, _jsEscape() in <script> (it adds the quotes), and' . PHP_EOL;
echo '_jsAttributeEscape() inside onclick="..." and other handlers.' . PHP_EOL;

exit(1);
