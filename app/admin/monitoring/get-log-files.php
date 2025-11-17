<?php

use App\Utilities\DateUtility;
use App\Registries\AppRegistry;

// Fast Log Reader Class for Performance
class FastLogReader
{
    private int $chunkSize = 8192; // 8KB chunks
    private int $maxMemoryUsage = 50 * 1024 * 1024; // 50MB max memory
    private int $streamingThreshold = 100 * 1024 * 1024; // 100MB for streaming mode

    public function readLogFileReverse($filePath, $start = 0, $limit = 50, $searchTerm = '')
    {
        if (!file_exists($filePath)) {
            return [];
        }

        $fileSize = filesize($filePath);
        if ($fileSize === 0) {
            return [];
        }

        $handle = fopen($filePath, 'rb');
        if (!$handle) {
            return [];
        }

        $result = [];

        // For very large files, use streaming approach
        if ($fileSize > $this->streamingThreshold) {
            $result = $this->streamingReverseRead($handle, $fileSize, $start, $limit, $searchTerm);
        } else {
            $result = $this->chunkedReverseRead($handle, $fileSize, $start, $limit, $searchTerm);
        }

        fclose($handle);
        return $result;
    }

    /**
     * @return non-falsy-string[]
     */
    private function chunkedReverseRead($handle, int|bool $fileSize, $start, $limit, $searchTerm): array
    {
        $lines = [];
        $buffer = '';
        $position = $fileSize;
        $foundLines = 0;
        $targetEnd = $start + $limit;

        while ($position > 0 && $foundLines < $targetEnd + 50) {
            $chunkStart = max(0, $position - $this->chunkSize);
            $chunkSize = $position - $chunkStart;

            fseek($handle, $chunkStart);
            $chunk = fread($handle, $chunkSize);

            $buffer = $chunk . $buffer;

            $chunkLines = explode("\n", $buffer);

            $buffer = $position > $this->chunkSize ? array_shift($chunkLines) : '';

            for ($i = count($chunkLines) - 1; $i >= 0; $i--) {
                $line = trim($chunkLines[$i]);

                if ($line === '' || $line === '0') {
                    continue;
                }

                if (!empty($searchTerm) && !$this->lineMatchesSearch($line, $searchTerm)) {
                    continue;
                }

                if ($foundLines >= $start && count($lines) < $limit) {
                    $lines[] = $line;
                }

                $foundLines++;

                if (count($lines) >= $limit) {
                    break 2;
                }
            }

            $position = $chunkStart;
        }

        return $lines;
    }

    /**
     * @return non-falsy-string[]
     */
    private function streamingReverseRead($handle, int|bool $fileSize, $start, $limit, $searchTerm): array
    {
        $lines = [];
        $buffer = '';
        $position = $fileSize;
        $foundLines = 0;
        $processedBytes = 0;
        $maxProcessBytes = min($fileSize, 200 * 1024 * 1024); // Process max 200MB

        while ($position > 0 && $foundLines < ($start + $limit + 100) && $processedBytes < $maxProcessBytes) {
            $chunkStart = max(0, $position - $this->chunkSize);
            $chunkSize = $position - $chunkStart;

            fseek($handle, $chunkStart);
            $chunk = fread($handle, $chunkSize);

            $buffer = $chunk . $buffer;
            $processedBytes += $chunkSize;

            $lastNewlinePos = strrpos($buffer, "\n");
            if ($lastNewlinePos !== false && $position > $this->chunkSize) {
                $completeBuffer = substr($buffer, $lastNewlinePos + 1);
                $buffer = substr($buffer, 0, $lastNewlinePos + 1);
            } else {
                $completeBuffer = $buffer;
                $buffer = '';
            }

            $chunkLines = explode("\n", $completeBuffer);

            for ($i = count($chunkLines) - 1; $i >= 0; $i--) {
                $line = trim($chunkLines[$i]);

                if ($line === '' || $line === '0') {
                    continue;
                }

                if (!empty($searchTerm) && !$this->lineMatchesSearch($line, $searchTerm)) {
                    continue;
                }

                if ($foundLines >= $start && count($lines) < $limit) {
                    $lines[] = $line;
                }

                $foundLines++;

                if (count($lines) >= $limit) {
                    break 2;
                }
            }

            $position = $chunkStart;
        }

        return $lines;
    }

    private function lineMatchesSearch(string $line, $searchTerm)
    {
        if (empty($searchTerm)) {
            return true;
        }

        // Use your existing search logic here
        return lineContainsAllSearchTerms($line, $searchTerm);
    }

    public function getFileStats($filePath): ?array
    {
        if (!file_exists($filePath)) {
            return null;
        }

        $stats = [
            'size' => filesize($filePath),
            'modified' => filemtime($filePath),
            'estimated_lines' => 0,
            'mode' => 'standard'
        ];

        // Determine processing mode
        if ($stats['size'] > $this->streamingThreshold) {
            $stats['mode'] = 'streaming';
        } elseif ($stats['size'] > $this->maxMemoryUsage) {
            $stats['mode'] = 'chunked';
        }

        // Estimate line count by sampling
        $handle = fopen($filePath, 'rb');
        if ($handle) {
            $sampleSize = min(16384, $stats['size']); // 16KB sample
            $sample = fread($handle, $sampleSize);
            $sampleLines = substr_count($sample, "\n");

            if ($sampleLines > 0) {
                $stats['estimated_lines'] = intval(($stats['size'] / $sampleSize) * $sampleLines);
            }

            fclose($handle);
        }

        return $stats;
    }
}

// Get request parameters
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());

$logType = $_GET['log_type'] ?? 'application';
$linesPerPage = 50; // Increased for better performance
$start = isset($_GET['start']) ? intval($_GET['start']) : 0;
$searchTerm = isset($_GET['search']) ? trim((string) $_GET['search']) : '';
$exportFormat = $_GET['export_format'] ?? '';

// Initialize fast log reader
$logReader = new FastLogReader();

if ($logType === 'php_error') {
    $file = ini_get('error_log');
} else {
    $date = isset($_GET['date']) ? DateUtility::isoDateFormat($_GET['date']) : date('Y-m-d');
    $file = LOG_PATH . '/' . $date . '-logfile.log';
}

function getMostRecentLogFile(string $logDirectory): ?string
{
    $files = glob($logDirectory . '/*.log');
    if (!$files) {
        return null;
    }

    usort($files, fn($a, $b): int => filemtime($b) - filemtime($a));

    return $files[0];
}

function parseSearchTerms($searchString): array
{
    $terms = [];
    preg_match_all('/"([^"]+)"|\'([^\']+)\'|\^(\S+)|\+(\S+)|(\S+)\$|(\S+)\*|\*(\S+)|\b(\S+)\b/', (string) $searchString, $matches, PREG_SET_ORDER);

    foreach ($matches as $match) {
        if (isset($match[1]) && ($match[1] !== '' && $match[1] !== '0')) {
            $terms[] = ['type' => 'phrase', 'value' => $match[1]];
        } elseif (isset($match[2]) && ($match[2] !== '' && $match[2] !== '0')) {
            $terms[] = ['type' => 'phrase', 'value' => $match[2]];
        } elseif (isset($match[3]) && ($match[3] !== '' && $match[3] !== '0')) {
            $terms[] = ['type' => 'start', 'value' => $match[3]];
        } elseif (isset($match[4]) && ($match[4] !== '' && $match[4] !== '0')) {
            $terms[] = ['type' => 'exact', 'value' => $match[4]];
        } elseif (isset($match[5]) && ($match[5] !== '' && $match[5] !== '0')) {
            $terms[] = ['type' => 'end', 'value' => $match[5]];
        } elseif (isset($match[6]) && ($match[6] !== '' && $match[6] !== '0')) {
            $terms[] = ['type' => 'starts_with', 'value' => $match[6]];
        } elseif (isset($match[7]) && ($match[7] !== '' && $match[7] !== '0')) {
            $terms[] = ['type' => 'ends_with', 'value' => $match[7]];
        } elseif (isset($match[8]) && ($match[8] !== '' && $match[8] !== '0')) {
            $terms[] = ['type' => 'partial', 'value' => $match[8]];
        }
    }

    return array_filter($terms, fn($term): bool => strlen((string) $term['value']) > 0);
}

function lineContainsAllSearchTerms($line, $search): bool
{
    if (empty($search)) {
        return true;
    }

    $terms = parseSearchTerms(trim((string) $search));

    if (empty($terms)) {
        return true;
    }

    foreach ($terms as $term) {
        $found = false;

        switch ($term['type']) {
            case 'exact':
                $pattern = '/\b' . preg_quote((string) $term['value'], '/') . '\b/i';
                $found = preg_match($pattern, (string) $line);
                break;

            case 'start':
                $pattern = '/^' . preg_quote((string) $term['value'], '/') . '/i';
                $found = preg_match($pattern, (string) $line);
                break;

            case 'end':
                $pattern = '/' . preg_quote((string) $term['value'], '/') . '$/i';
                $found = preg_match($pattern, (string) $line);
                break;

            case 'starts_with':
                $pattern = '/\b' . preg_quote((string) $term['value'], '/') . '/i';
                $found = preg_match($pattern, (string) $line);
                break;

            case 'ends_with':
                $pattern = '/' . preg_quote((string) $term['value'], '/') . '\b/i';
                $found = preg_match($pattern, (string) $line);
                break;

            case 'phrase':
                $found = stripos((string) $line, (string) $term['value']) !== false;
                break;

            case 'partial':
            default:
                $found = stripos((string) $line, (string) $term['value']) !== false;
                break;
        }

        if (!$found) {
            return false;
        }
    }

    return true;
}

function detectLogLevel($line): string
{
    $line = strtolower((string) $line);
    if (str_contains($line, 'error') || str_contains($line, 'exception') || str_contains($line, 'fatal')) {
        return 'error';
    } elseif (str_contains($line, 'warn')) {
        return 'warning';
    } elseif (str_contains($line, 'info')) {
        return 'info';
    } elseif (str_contains($line, 'debug')) {
        return 'debug';
    }
    return 'info';
}

function formatApplicationLogEntry($entry): string|array|null
{
    $entry = preg_replace('/\\\\n#(\d+)/', '<br/><span style="color:#e83e8c;font-weight:bold;">#$1</span>', (string) $entry);
    $entry = preg_replace('/\n#(\d+)/', '<br/><span style="color:#e83e8c;font-weight:bold;">#$1</span>', $entry);
    $entry = preg_replace('/\\n#(\d+)/', '<br/><span style="color:#e83e8c;font-weight:bold;">#$1</span>', $entry);

    $entry = str_replace('\n#',  '<br/><span style="color:#e83e8c;font-weight:bold;">#', $entry);

    $entry = preg_replace_callback(
        '/(\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:[+-]\d{2}:\d{2})?)/i',
        function ($matches): string {
            $isoTimestamp = $matches[1];

            try {
                $humanReadable = DateUtility::humanReadableDateFormat($isoTimestamp, includeTime: true, withSeconds: true);
                return '<strong title="' . htmlspecialchars($isoTimestamp) . '">' . $humanReadable . '</strong>';
            } catch (Exception) {
                return '<strong>' . $isoTimestamp . '</strong>';
            }
        },
        $entry
    );

    $patterns = [
        '/(exception|error|fatal|warning|deprecated)/i' => '<span style="color:#dc3545;font-weight:bold;">$1</span>',
        '/(\w+\.php):(\d+)/i' => '<span style="color:#17a2b8;">$1</span>:<span style="color:#fd7e14;font-weight:bold;">$2</span>',
        '/(SELECT|INSERT|UPDATE|DELETE|FROM|WHERE|JOIN|GROUP BY|ORDER BY|HAVING)(?=\s)/i' => '<span style="color:#6610f2;font-weight:bold;">$1</span>'
    ];

    foreach ($patterns as $pattern => $replacement) {
        $entry = preg_replace($pattern, $replacement, $entry);
    }

    return $entry;
}

/**
 * @return mixed[]
 */
function processPHPErrorLog($entries): array
{
    $processedEntries = [];
    $currentEntry = '';
    $inStackTrace = false;

    foreach ($entries as $line) {
        if (preg_match('/^\[\d{2}-\w{3}-\d{4}/', (string) $line)) {
            if (!empty($currentEntry)) {
                $processedEntries[] = $currentEntry;
                $currentEntry = '';
            }
            $inStackTrace = false;
            $currentEntry = $line;
            if (
                stripos((string) $line, 'Fatal error') !== false ||
                stripos((string) $line, 'Uncaught') !== false ||
                stripos((string) $line, 'Exception') !== false
            ) {
                $inStackTrace = true;
            }
        } elseif (preg_match('/^#\d+/', (string) $line)) {
            $currentEntry .= "\n" . $line;
            $inStackTrace = true;
        } elseif ($inStackTrace || trim((string) $line) !== '') {
            $currentEntry .= "\n" . $line;
        }
    }

    if (!empty($currentEntry)) {
        $processedEntries[] = $currentEntry;
    }

    return $processedEntries;
}

function formatPhpErrorLogEntry($entry): string|array|null
{
    $html = nl2br(htmlspecialchars((string) $entry));

    $html = preg_replace('/\[(\d{2}-\w{3}-\d{4}\s\d{2}:\d{2}:\d{2}\s\w+)\]/', '<strong>[$1]</strong>', $html);
    $html = preg_replace('/(#\d+)/', '<span style="color:#e83e8c;font-weight:bold;">$1</span>', (string) $html);
    $html = preg_replace('/(PHP (?:Fatal error|Warning|Notice|Deprecated):)/', '<span style="color:#dc3545;font-weight:bold;">$1</span>', (string) $html);
    $html = preg_replace('/(thrown in)/', '<span style="color:#dc3545;">$1</span>', (string) $html);
    $html = preg_replace('/in (\/[\w\/\.\-]+\.php)/', 'in <span style="color:#17a2b8;">$1</span>', (string) $html);
    return preg_replace('/(on line |:)(\d+)/', '$1<span style="color:#fd7e14;font-weight:bold;">$2</span>', (string) $html);
}

function createLogLine(string $content, string $lineNumber, string $logLevel): string
{
    return '<div class="logLine log-' . $logLevel . '" data-linenumber="' . $lineNumber . '" data-level="' . $logLevel . '" onclick="copyToClipboard(this.innerHTML, ' . $lineNumber . ')">
        <span class="lineNumber">' . $lineNumber . '</span>' . $content . '</div>';
}

$actualLogDate = '';
$performanceInfo = null;

// Get performance stats
if (file_exists($file)) {
    $performanceInfo = $logReader->getFileStats($file);
}

if (file_exists($file)) {
    if ($logType === 'php_error') {
        // For PHP error logs, keep existing processing since they need special handling
        $fileContent = file($file, FILE_IGNORE_NEW_LINES);
        $logEntries = processPHPErrorLog($fileContent);
        $logEntries = array_reverse($logEntries);

        if ($searchTerm !== '' && $searchTerm !== '0') {
            $logEntries = array_filter($logEntries, fn($entry) => lineContainsAllSearchTerms($entry, $searchTerm));
            $logEntries = array_values($logEntries);
        }

        $logEntries = array_slice($logEntries, $start, $linesPerPage);

        echo "<div class='log-header'>" . _translate("Viewing PHP Error Log") . "</div>";

        if ($logEntries === []) {
            echo "<div class='logLine'>No more logs.</div>";
            exit();
        }

        foreach ($logEntries as $index => $entry) {
            $lineNumber = $start + $index + 1;
            $logLevel = 'error';
            $formattedEntry = formatPhpErrorLogEntry($entry);
            echo createLogLine($formattedEntry, $lineNumber, $logLevel);
        }

        if (count($logEntries) < $linesPerPage) {
            echo "<div class='logLine'>No more logs.</div>";
        }
    } else {
        // Use fast log reader for application logs
        $logEntries = $logReader->readLogFileReverse($file, $start, $linesPerPage, $searchTerm);

        $actualLogDate = $_GET['date'] ?? date('d-M-Y');

        echo "<div class='log-header'>" . _translate("Viewing System Log for Date") . " - " . $actualLogDate;

        if ($performanceInfo) {
            $sizeFormatted = number_format($performanceInfo['size'] / 1024, 1);
            $linesFormatted = number_format($performanceInfo['estimated_lines']);
            echo " (File: {$sizeFormatted} KB, ~{$linesFormatted} lines, Mode: {$performanceInfo['mode']})";
        }

        echo "</div>";

        if (empty($logEntries)) {
            echo "<div class='logLine'>No more logs.</div>";
            exit();
        }

        foreach ($logEntries as $index => $entry) {
            $lineNumber = $start + $index + 1;
            $logLevel = detectLogLevel($entry);
            $entry = htmlspecialchars((string) $entry);

            $lines = preg_split('/\\\\n|\\n|\n/', $entry);
            $formattedEntry = '';

            foreach ($lines as $i => $line) {
                if (preg_match('/^#(\d+)/', $line, $matches)) {
                    $line = '<span style="color:#e83e8c;font-weight:bold;">#' . $matches[1] . '</span>' . substr($line, strlen($matches[0]));
                    $formattedEntry .= ($i > 0 ? '<br/>' : '') . $line;
                } else {
                    $formattedEntry .= ($i > 0 ? '<br/>' : '') . $line;
                }
            }

            $formattedEntry = formatApplicationLogEntry($formattedEntry);
            echo createLogLine($formattedEntry, $lineNumber, $logLevel);
        }

        if (count($logEntries) < $linesPerPage) {
            echo "<div class='logLine'>No more logs.</div>";
        }
    }
} elseif ($logType === 'application') {
    $recentFile = getMostRecentLogFile(LOG_PATH);
    if ($recentFile) {
        if (preg_match('/(\d{4}-\d{2}-\d{2})/', basename((string) $recentFile), $matches)) {
            $isoDate = $matches[1];
            $dateObj = new DateTime($isoDate);
            $actualLogDate = $dateObj->format('d-M-Y');
        }

        // Use fast log reader for recent file too
        $logEntries = $logReader->readLogFileReverse($recentFile, $start, $linesPerPage, $searchTerm);
        $recentFileStats = $logReader->getFileStats($recentFile);

        echo "<div class='log-header'>" . _translate("No data found for the selected date") . " - " . ($_GET['date'] ?? date('d-M-Y')) . "<br>" .
            _translate("Showing the most recent log file") . " : " . basename((string) $recentFile);

        if ($recentFileStats) {
            $sizeFormatted = number_format($recentFileStats['size'] / 1024, 1);
            $linesFormatted = number_format($recentFileStats['estimated_lines']);
            echo " (File: {$sizeFormatted} KB, ~{$linesFormatted} lines, Mode: {$recentFileStats['mode']})";
        }

        echo "</div>";

        if (empty($logEntries)) {
            echo "<div class='logLine'>No logs found.</div>";
            exit();
        }

        foreach ($logEntries as $index => $entry) {
            $lineNumber = $start + $index + 1;
            $logLevel = detectLogLevel($entry);
            $entry = htmlspecialchars((string) $entry);

            $lines = preg_split('/\\\\n|\\n|\n/', $entry);
            $formattedEntry = '';

            foreach ($lines as $i => $line) {
                if (preg_match('/^#(\d+)/', $line, $matches)) {
                    $line = '<span style="color:#e83e8c;font-weight:bold;">#' . $matches[1] . '</span>' . substr($line, strlen($matches[0]));
                    $formattedEntry .= ($i > 0 ? '<br/>' : '') . $line;
                } else {
                    $formattedEntry .= ($i > 0 ? '<br/>' : '') . $line;
                }
            }

            $formattedEntry = formatApplicationLogEntry($formattedEntry);
            echo createLogLine($formattedEntry, $lineNumber, $logLevel);
        }

        if (count($logEntries) < $linesPerPage) {
            echo "<div class='logLine'>No more logs.</div>";
        }
    } else {
        echo '<div class="logLine">No log files found.</div>';
    }
} else {
    echo '<div class="logLine">No PHP error log found.</div>';
}

// Send performance info to frontend for display
if ($start === 0 && $performanceInfo) {
    echo "<!-- PERFORMANCE_INFO: " . json_encode($performanceInfo) . " -->";
    echo "<input type='hidden' id='actualLogDate' value='{$actualLogDate}'>";
}
