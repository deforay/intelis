<?php

use App\Utilities\DateUtility;
use App\Registries\AppRegistry;

// Fast Log Reader Class for Performance
class FastLogReader
{
    private $chunkSize = 8192; // 8KB chunks
    private $maxMemoryUsage = 50 * 1024 * 1024; // 50MB max memory
    private $streamingThreshold = 100 * 1024 * 1024; // 100MB for streaming mode

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

    private function chunkedReverseRead($handle, $fileSize, $start, $limit, $searchTerm)
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

            if ($position > $this->chunkSize) {
                $buffer = array_shift($chunkLines);
            } else {
                $buffer = '';
            }

            for ($i = count($chunkLines) - 1; $i >= 0; $i--) {
                $line = trim($chunkLines[$i]);

                if (empty($line)) {
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

    private function streamingReverseRead($handle, $fileSize, $start, $limit, $searchTerm)
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

                if (empty($line)) {
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

    private function lineMatchesSearch($line, $searchTerm)
    {
        if (empty($searchTerm)) {
            return true;
        }

        // Use your existing search logic here
        return lineContainsAllSearchTerms($line, $searchTerm);
    }

    public function getFileStats($filePath)
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
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$exportFormat = $_GET['export_format'] ?? '';

// Initialize fast log reader
$logReader = new FastLogReader();

if ($logType === 'php_error') {
    $file = ini_get('error_log');
} else {
    $date = isset($_GET['date']) ? DateUtility::isoDateFormat($_GET['date']) : date('Y-m-d');
    $file = LOG_PATH . '/' . $date . '-logfile.log';
}

function getMostRecentLogFile($logDirectory)
{
    $files = glob($logDirectory . '/*.log');
    if (!$files) {
        return null;
    }

    usort($files, function ($a, $b) {
        return filemtime($b) - filemtime($a);
    });

    return $files[0];
}

function parseSearchTerms($searchString)
{
    $terms = [];
    preg_match_all('/"([^"]+)"|\'([^\']+)\'|\^(\S+)|\+(\S+)|(\S+)\$|(\S+)\*|\*(\S+)|\b(\S+)\b/', $searchString, $matches, PREG_SET_ORDER);

    foreach ($matches as $match) {
        if (!empty($match[1])) {
            $terms[] = ['type' => 'phrase', 'value' => $match[1]];
        } elseif (!empty($match[2])) {
            $terms[] = ['type' => 'phrase', 'value' => $match[2]];
        } elseif (!empty($match[3])) {
            $terms[] = ['type' => 'start', 'value' => $match[3]];
        } elseif (!empty($match[4])) {
            $terms[] = ['type' => 'exact', 'value' => $match[4]];
        } elseif (!empty($match[5])) {
            $terms[] = ['type' => 'end', 'value' => $match[5]];
        } elseif (!empty($match[6])) {
            $terms[] = ['type' => 'starts_with', 'value' => $match[6]];
        } elseif (!empty($match[7])) {
            $terms[] = ['type' => 'ends_with', 'value' => $match[7]];
        } elseif (!empty($match[8])) {
            $terms[] = ['type' => 'partial', 'value' => $match[8]];
        }
    }

    return array_filter($terms, function ($term) {
        return strlen($term['value']) > 0;
    });
}

function lineContainsAllSearchTerms($line, $search)
{
    if (empty($search)) {
        return true;
    }

    $terms = parseSearchTerms(trim($search));

    if (empty($terms)) {
        return true;
    }

    foreach ($terms as $term) {
        $found = false;

        switch ($term['type']) {
            case 'exact':
                $pattern = '/\b' . preg_quote($term['value'], '/') . '\b/i';
                $found = preg_match($pattern, $line);
                break;

            case 'start':
                $pattern = '/^' . preg_quote($term['value'], '/') . '/i';
                $found = preg_match($pattern, $line);
                break;

            case 'end':
                $pattern = '/' . preg_quote($term['value'], '/') . '$/i';
                $found = preg_match($pattern, $line);
                break;

            case 'starts_with':
                $pattern = '/\b' . preg_quote($term['value'], '/') . '/i';
                $found = preg_match($pattern, $line);
                break;

            case 'ends_with':
                $pattern = '/' . preg_quote($term['value'], '/') . '\b/i';
                $found = preg_match($pattern, $line);
                break;

            case 'phrase':
                $found = stripos($line, $term['value']) !== false;
                break;

            case 'partial':
            default:
                $found = stripos($line, $term['value']) !== false;
                break;
        }

        if (!$found) {
            return false;
        }
    }

    return true;
}

function detectLogLevel($line)
{
    $line = strtolower($line);
    if (strpos($line, 'error') !== false || strpos($line, 'exception') !== false || strpos($line, 'fatal') !== false) {
        return 'error';
    } elseif (strpos($line, 'warn') !== false) {
        return 'warning';
    } elseif (strpos($line, 'info') !== false) {
        return 'info';
    } elseif (strpos($line, 'debug') !== false) {
        return 'debug';
    }
    return 'info';
}

function formatApplicationLogEntry($entry)
{
    $entry = preg_replace('/\\\\n#(\d+)/', '<br/><span style="color:#e83e8c;font-weight:bold;">#$1</span>', $entry);
    $entry = preg_replace('/\n#(\d+)/', '<br/><span style="color:#e83e8c;font-weight:bold;">#$1</span>', $entry);
    $entry = preg_replace('/\\n#(\d+)/', '<br/><span style="color:#e83e8c;font-weight:bold;">#$1</span>', $entry);

    $entry = str_replace('\n#',  '<br/><span style="color:#e83e8c;font-weight:bold;">#', $entry);

    $entry = preg_replace_callback(
        '/(\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:[+-]\d{2}:\d{2})?)/i',
        function ($matches) {
            $isoTimestamp = $matches[1];

            try {
                $humanReadable = DateUtility::humanReadableDateFormat($isoTimestamp, includeTime: true, withSeconds: true);
                return '<strong title="' . htmlspecialchars($isoTimestamp) . '">' . $humanReadable . '</strong>';
            } catch (Exception $e) {
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

function processPHPErrorLog($entries)
{
    $processedEntries = [];
    $currentEntry = '';
    $inStackTrace = false;

    foreach ($entries as $line) {
        if (preg_match('/^\[\d{2}-\w{3}-\d{4}/', $line)) {
            if (!empty($currentEntry)) {
                $processedEntries[] = $currentEntry;
                $currentEntry = '';
            }
            $inStackTrace = false;
            $currentEntry = $line;

            if (
                stripos($line, 'Fatal error') !== false ||
                stripos($line, 'Uncaught') !== false ||
                stripos($line, 'Exception') !== false
            ) {
                $inStackTrace = true;
            }
        } else {
            if (preg_match('/^#\d+/', $line)) {
                $currentEntry .= "\n" . $line;
                $inStackTrace = true;
            } else if ($inStackTrace || trim($line) !== '') {
                $currentEntry .= "\n" . $line;
            }
        }
    }

    if (!empty($currentEntry)) {
        $processedEntries[] = $currentEntry;
    }

    return $processedEntries;
}

function formatPhpErrorLogEntry($entry)
{
    $html = nl2br(htmlspecialchars($entry));

    $html = preg_replace('/\[(\d{2}-\w{3}-\d{4}\s\d{2}:\d{2}:\d{2}\s\w+)\]/', '<strong>[$1]</strong>', $html);
    $html = preg_replace('/(#\d+)/', '<span style="color:#e83e8c;font-weight:bold;">$1</span>', $html);
    $html = preg_replace('/(PHP (?:Fatal error|Warning|Notice|Deprecated):)/', '<span style="color:#dc3545;font-weight:bold;">$1</span>', $html);
    $html = preg_replace('/(thrown in)/', '<span style="color:#dc3545;">$1</span>', $html);
    $html = preg_replace('/in (\/[\w\/\.\-]+\.php)/', 'in <span style="color:#17a2b8;">$1</span>', $html);
    $html = preg_replace('/(on line |:)(\d+)/', '$1<span style="color:#fd7e14;font-weight:bold;">$2</span>', $html);

    return $html;
}

function createLogLine($content, $lineNumber, $logLevel)
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

        if (!empty($searchTerm)) {
            $logEntries = array_filter($logEntries, function ($entry) use ($searchTerm) {
                return lineContainsAllSearchTerms($entry, $searchTerm);
            });
            $logEntries = array_values($logEntries);
        }

        $logEntries = array_slice($logEntries, $start, $linesPerPage);

        echo "<div class='log-header'>" . _translate("Viewing PHP Error Log") . "</div>";

        if (empty($logEntries)) {
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
            $entry = htmlspecialchars($entry);

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
} else {
    if ($logType === 'application') {
        $recentFile = getMostRecentLogFile(ROOT_PATH . '/logs');

        if ($recentFile) {
            if (preg_match('/(\d{4}-\d{2}-\d{2})/', basename($recentFile), $matches)) {
                $isoDate = $matches[1];
                $dateObj = new DateTime($isoDate);
                $actualLogDate = $dateObj->format('d-M-Y');
            }

            // Use fast log reader for recent file too
            $logEntries = $logReader->readLogFileReverse($recentFile, $start, $linesPerPage, $searchTerm);
            $recentFileStats = $logReader->getFileStats($recentFile);

            echo "<div class='log-header'>" . _translate("No data found for the selected date") . " - " . ($_GET['date'] ?? date('d-M-Y')) . "<br>" .
                _translate("Showing the most recent log file") . " : " . basename($recentFile);

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
                $entry = htmlspecialchars($entry);

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
}

// Send performance info to frontend for display
if ($start === 0 && $performanceInfo) {
    echo "<!-- PERFORMANCE_INFO: " . json_encode($performanceInfo) . " -->";
    echo "<input type='hidden' id='actualLogDate' value='{$actualLogDate}'>";
}
